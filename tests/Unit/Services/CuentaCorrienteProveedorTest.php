<?php

namespace Tests\Unit\Services;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use App\Models\Tesoreria;
use App\Services\CompraService;
use App\Services\CuentaCorrienteProveedorService;
use App\Services\PagoProveedorService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Cta cte de proveedores (RF-18/19, D12/D14/D16/D17): ledger de pasivo,
 * pagos por origen de fondos, anticipos, anulaciones y cancelación con pagos.
 */
class CuentaCorrienteProveedorTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected CompraService $compraService;

    protected PagoProveedorService $pagoService;

    protected CuentaCorrienteProveedorService $ccService;

    protected Proveedor $proveedor;

    protected Caja $caja;

    protected array $fpEfectivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->compraService = app(CompraService::class);
        $this->pagoService = app(PagoProveedorService::class);
        $this->ccService = app(CuentaCorrienteProveedorService::class);
        $this->proveedor = Proveedor::create(['nombre' => 'Proveedor CC '.uniqid(), 'activo' => true, 'tiene_cuenta_corriente' => true]);
        $this->caja = $this->crearCajaAbierta($this->sucursalId, ['saldo_actual' => 100000, 'saldo_inicial' => 100000]);
        $this->fpEfectivo = $this->crearFormaPagoEfectivo();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Ledger al confirmar ====================

    public function test_compra_cta_cte_genera_haber_por_el_total(): void
    {
        // Criterio del spec: compra cta cte de proveedor CC genera HABER por el total.
        $compra = $this->confirmarCompraCtaCte(1210.0);

        $movimiento = MovimientoCuentaCorrienteProveedor::where('compra_id', $compra->id)->first();
        $this->assertEquals(1210.0, (float) $movimiento->haber);
        $this->assertEquals(0.0, (float) $movimiento->debe);

        $this->assertEquals(1210.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
        $this->assertEquals(1210.0, (float) $this->proveedor->fresh()->saldo_cache);
    }

    public function test_contado_total_genera_par_haber_debe_con_saldo_cero(): void
    {
        // Criterio del spec: contado total ⇒ par HABER/DEBE, saldo 0, extracto completo.
        $compra = $this->confirmarCompraContado(1210.0);

        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
        $this->assertEquals(0.0, (float) $compra->fresh()->saldo_pendiente);

        // El pago se materializó como OP (un solo camino de escritura).
        $op = PagoProveedor::where('proveedor_id', $this->proveedor->id)->first();
        $this->assertNotNull($op);
        $this->assertEquals(1210.0, (float) $op->monto_total);

        // Egreso de caja por el efectivo.
        $this->assertEquals(100000 - 1210, (float) $this->caja->fresh()->saldo_actual);
    }

    public function test_pago_inicial_parcial_baja_el_saldo(): void
    {
        // Criterio del spec: pago inicial al confirmar cta cte baja el saldo.
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 400.0);

        $this->assertEquals(600.0, (float) $compra->fresh()->saldo_pendiente);
        $this->assertEquals(600.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
    }

    public function test_pago_inicial_igual_al_total_es_invalido(): void
    {
        $this->expectExceptionMessage('MENOR al total');

        $this->confirmarCompraCtaCte(1000.0, pagoInicial: 1000.0);
    }

    public function test_cta_cte_requiere_proveedor_habilitado(): void
    {
        $proveedorSinCc = Proveedor::create(['nombre' => 'Sin CC '.uniqid(), 'activo' => true]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $compra = $this->compraService->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedorSinCc->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
            'forma_pago' => 'cta_cte',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 1, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);

        $this->expectExceptionMessage('no tiene cuenta corriente');
        $this->compraService->confirmarCompra($compra, 1);
    }

    // ==================== Pagos posteriores ====================

    public function test_pago_fifo_a_dos_compras_baja_saldos_y_registra_debe(): void
    {
        // Criterio del spec: pago parcial a 2 compras (FIFO) genera los DEBE,
        // baja el saldo_pendiente de ambas y egresa de caja según el desglose.
        $compraA = $this->confirmarCompraCtaCte(1000.0);
        $compraB = $this->confirmarCompraCtaCte(500.0);

        $pendientes = $this->ccService->obtenerComprasPendientes($this->proveedor->id, $this->sucursalId);
        $distribucion = $this->pagoService->distribuirMontoFIFO(1200.0, $pendientes);

        $op = $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $this->caja->id,
        ], $distribucion, [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 1200.0],
        ]);

        $this->assertEquals(0.0, (float) $compraA->fresh()->saldo_pendiente);
        $this->assertEquals(300.0, (float) $compraB->fresh()->saldo_pendiente);
        $this->assertEquals(300.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));

        // Dos DEBE (uno por compra) + snapshots de auditoría.
        $this->assertEquals(2, $op->compras()->count());
        $this->assertEquals(1000.0, (float) $op->compras->firstWhere('compra_id', $compraA->id)->monto_aplicado);
        $this->assertEquals(200.0, (float) $op->compras->firstWhere('compra_id', $compraB->id)->monto_aplicado);
    }

    public function test_anticipo_genera_saldo_favor_y_el_pago_siguiente_lo_consume(): void
    {
        // Criterio del spec: anticipo genera saldo a favor nuestro; el pago
        // siguiente lo consume (uso_saldo_favor).
        $this->pagoService->registrarAnticipo([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $this->caja->id,
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 500.0],
        ]);

        $this->assertEquals(500.0, MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedor->id));

        $compra = $this->confirmarCompraCtaCte(800.0);

        $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $this->caja->id,
            'saldo_favor_usado' => 500.0,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 800.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 300.0],
        ]);

        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedor->id));
        $this->assertEquals(0.0, (float) $compra->fresh()->saldo_pendiente);
        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
    }

    public function test_usar_mas_saldo_favor_del_disponible_falla(): void
    {
        $compra = $this->confirmarCompraCtaCte(500.0);

        $this->expectExceptionMessage('saldo a favor');

        $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'saldo_favor_usado' => 100.0,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 100.0],
        ], []);
    }

    public function test_pago_desde_tesoreria_egresa_y_valida_saldo(): void
    {
        // D14: origen tesorería usa el NUEVO registrarEgresoExterno.
        $tesoreria = Tesoreria::create(['sucursal_id' => $this->sucursalId, 'nombre' => 'Tesorería Test', 'saldo_actual' => 1000, 'activo' => true]);
        $compra = $this->confirmarCompraCtaCte(800.0);

        $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 800.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 800.0, 'origen' => 'tesoreria'],
        ]);

        $this->assertEquals(200.0, (float) $tesoreria->fresh()->saldo_actual);

        // Y valida saldo: no puede quedar negativa.
        $compra2 = $this->confirmarCompraCtaCte(500.0);
        $this->expectExceptionMessage('Tesorería');
        $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
        ], [
            ['compra_id' => $compra2->id, 'monto_aplicado' => 500.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 500.0, 'origen' => 'tesoreria'],
        ]);
    }

    public function test_pago_efectivo_valida_saldo_de_caja(): void
    {
        $cajaChica = $this->crearCajaAbierta($this->sucursalId, ['saldo_actual' => 50, 'saldo_inicial' => 50]);
        $compra = $this->confirmarCompraCtaCte(500.0);

        $this->expectExceptionMessage('Saldo insuficiente en la caja');

        $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $cajaChica->id,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 500.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 500.0],
        ]);
    }

    // ==================== Anulación de OP ====================

    public function test_anular_op_contraasienta_ledger_caja_y_restaura_saldos(): void
    {
        // Criterio del spec: anular una OP contraasienta ledger + origen y
        // restaura los saldo_pendiente.
        $compra = $this->confirmarCompraCtaCte(1000.0);

        $op = $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $this->caja->id,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 600.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 600.0],
        ]);

        $saldoCajaTrasPago = (float) $this->caja->fresh()->saldo_actual;

        $this->pagoService->anularPago($op->id, 'error de carga', 1);

        $this->assertEquals(1000.0, (float) $compra->fresh()->saldo_pendiente);
        $this->assertEquals(1000.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
        $this->assertEquals('anulado', $op->fresh()->estado);

        // La plata volvió a la caja (contraasiento del egreso).
        $this->assertEquals($saldoCajaTrasPago + 600.0, (float) $this->caja->fresh()->saldo_actual);
    }

    public function test_anular_op_con_renglon_caja_cerrado_bloquea(): void
    {
        // D16: bloqueo por turno cerrado SOLO en renglones de caja.
        $compra = $this->confirmarCompraCtaCte(500.0);

        $op = $this->pagoService->registrarPago([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'caja_id' => $this->caja->id,
        ], [
            ['compra_id' => $compra->id, 'monto_aplicado' => 500.0],
        ], [
            ['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => 500.0],
        ]);

        $op->pagos->first()->update(['cierre_turno_id' => 999]);

        $this->expectExceptionMessage('cerrado en un turno');
        $this->pagoService->anularPago($op->id, 'tarde', 1);
    }

    // ==================== D17: cancelar compra con pagos ====================

    public function test_cancelar_compra_con_pagos_exige_eleccion(): void
    {
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 400.0);

        $this->expectExceptionMessage('D17');
        $this->compraService->cancelarCompra($compra->fresh(), 1, 'error');
    }

    public function test_cancelar_con_cascada_anula_los_pagos(): void
    {
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 400.0);
        $saldoCaja = (float) $this->caja->fresh()->saldo_actual;

        $this->compraService->cancelarCompra($compra->fresh(), 1, 'error de carga', manejoPagos: 'anular_pagos');

        // Todo revertido: deuda 0, favor 0, plata de vuelta en caja.
        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedor->id));
        $this->assertEquals($saldoCaja + 400.0, (float) $this->caja->fresh()->saldo_actual);
        $this->assertEquals('anulado', PagoProveedor::where('proveedor_id', $this->proveedor->id)->first()->estado);
    }

    public function test_cancelar_con_saldo_favor_conserva_la_plata_pagada(): void
    {
        // D17: la plata salió de verdad — queda como saldo a favor nuestro.
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 400.0);
        $saldoCaja = (float) $this->caja->fresh()->saldo_actual;

        $this->compraService->cancelarCompra($compra->fresh(), 1, 'compra cancelada', manejoPagos: 'saldo_favor');

        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
        $this->assertEquals(400.0, MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedor->id));
        // La caja NO se toca (la OP queda activa).
        $this->assertEquals($saldoCaja, (float) $this->caja->fresh()->saldo_actual);
        $this->assertEquals('activo', PagoProveedor::where('proveedor_id', $this->proveedor->id)->first()->estado);
    }

    // ==================== NC contra el saldo (RF-21) ====================

    public function test_nc_baja_el_saldo_de_la_origen_y_el_excedente_va_a_favor(): void
    {
        // Criterio del spec: DEBE que baja el saldo de la compra origen hasta
        // cubrirlo; el excedente genera saldo a favor.
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 700.0); // saldo 300

        $nc = $this->compraService->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'compra_origen_id' => $compra->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_NO_FISCAL,
        ], [
            ['articulo_id' => $this->articuloDe($compra), 'cantidad_comprada' => 4, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        $this->compraService->confirmarCompra($nc, 1);

        // NC de 400 contra saldo 300: aplica 300, 100 a favor.
        $this->assertEquals(0.0, (float) $compra->fresh()->saldo_pendiente);
        $this->assertEquals(100.0, MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedor->id));
        $this->assertEquals(0.0, MovimientoCuentaCorrienteProveedor::calcularSaldoDeuda($this->proveedor->id, $this->sucursalId));
    }

    // ==================== Extracto ====================

    public function test_extracto_acumula_saldo_de_pasivo(): void
    {
        $compra = $this->confirmarCompraCtaCte(1000.0, pagoInicial: 400.0);

        $extracto = $this->ccService->obtenerExtractoResumido($this->proveedor->id, $this->sucursalId);

        $this->assertCount(2, $extracto); // compra (haber) + pago (debe), más reciente primero
        $this->assertEquals(600.0, $extracto->first()['saldo_deuda']);
    }

    // ==================== Helpers ====================

    private function confirmarCompraCtaCte(float $total, float $pagoInicial = 0): Compra
    {
        $compra = $this->borradorNoFiscal($total, 'cta_cte');

        $pago = $pagoInicial > 0
            ? ['pagos' => [['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => $pagoInicial]], 'caja_id' => $this->caja->id]
            : [];

        return $this->compraService->confirmarCompra($compra, 1, $pago);
    }

    private function confirmarCompraContado(float $total): Compra
    {
        $compra = $this->borradorNoFiscal($total, 'efectivo');

        return $this->compraService->confirmarCompra($compra, 1, [
            'pagos' => [['forma_pago_id' => $this->fpEfectivo['formaPago']->id, 'monto' => $total]],
            'caja_id' => $this->caja->id,
        ]);
    }

    private function borradorNoFiscal(float $total, string $formaPago): Compra
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        return $this->compraService->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
            'forma_pago' => $formaPago,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 10, 'factor_conversion' => 1, 'precio_unitario' => $total / 10],
        ]);
    }

    private function articuloDe(Compra $compra): int
    {
        return $compra->detalles()->first()->articulo_id;
    }
}
