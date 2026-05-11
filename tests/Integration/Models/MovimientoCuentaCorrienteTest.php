<?php

namespace Tests\Integration\Models;

use App\Models\Cobro;
use App\Models\CobroVenta;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Venta;
use App\Models\VentaPago;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class MovimientoCuentaCorrienteTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Helper: crea un movimiento CC directo con DB insert para tests de calculo.
     */
    private function crearMovimientoDirecto(int $clienteId, array $overrides = []): MovimientoCuentaCorriente
    {
        return MovimientoCuentaCorriente::create(array_merge([
            'cliente_id' => $clienteId,
            'sucursal_id' => $this->sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => MovimientoCuentaCorriente::TIPO_VENTA,
            'debe' => 0,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => 0,
            'documento_tipo' => 'ajuste',
            'documento_id' => 0,
            'concepto' => 'Test directo',
            'estado' => 'activo',
            'usuario_id' => 1,
        ], $overrides));
    }

    /**
     * Helper: crea un Cobro de prueba.
     */
    private function crearCobroPrueba(int $clienteId, array $overrides = []): Cobro
    {
        return Cobro::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'cliente_id' => $clienteId,
            'caja_id' => $this->cajaId,
            'numero_recibo' => 'REC-'.uniqid(),
            'tipo' => 'cobro',
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i:s'),
            'monto_cobrado' => 0,
            'interes_aplicado' => 0,
            'descuento_aplicado' => 0,
            'monto_aplicado_a_deuda' => 0,
            'monto_a_favor' => 0,
            'saldo_favor_usado' => 0,
            'estado' => 'activo',
            'usuario_id' => 1,
        ], $overrides));
    }

    public function test_calcular_saldo_deudor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Movimiento con debe=5000
        $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 5000,
            'haber' => 0,
        ]);

        // Movimiento con haber=2000
        $this->crearMovimientoDirecto($cliente->id, [
            'tipo' => MovimientoCuentaCorriente::TIPO_COBRO,
            'debe' => 0,
            'haber' => 2000,
        ]);

        $saldo = MovimientoCuentaCorriente::calcularSaldoDeudor($cliente->id, $this->sucursalId);

        $this->assertEquals(3000, $saldo);
    }

    public function test_calcular_saldo_deudor_solo_activos(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Movimiento activo con debe=5000
        $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 5000,
        ]);

        // Movimiento anulado con debe=3000 (no debe contar)
        $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 3000,
            'estado' => 'anulado',
        ]);

        $saldo = MovimientoCuentaCorriente::calcularSaldoDeudor($cliente->id, $this->sucursalId);

        $this->assertEquals(5000, $saldo);
    }

    public function test_calcular_saldo_favor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $this->crearMovimientoDirecto($cliente->id, [
            'tipo' => MovimientoCuentaCorriente::TIPO_ANTICIPO,
            'saldo_favor_haber' => 1000,
        ]);

        $saldoFavor = MovimientoCuentaCorriente::calcularSaldoFavor($cliente->id);

        $this->assertEquals(1000, $saldoFavor);
    }

    public function test_crear_contraasiento_invierte_debe_haber(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $original = $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 5000,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => 0,
        ]);

        $contraasiento = MovimientoCuentaCorriente::crearContraasiento($original, 'Anulacion test', 1);

        // Contraasiento invierte debe/haber
        $this->assertEquals('0.00', $contraasiento->debe);
        $this->assertEquals('5000.00', $contraasiento->haber);
    }

    public function test_crear_contraasiento_ambos_quedan_activos(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $original = $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 5000,
        ]);

        $contraasiento = MovimientoCuentaCorriente::crearContraasiento($original, 'Anulacion test', 1);

        // Ambos deben estar activos
        $original->refresh();
        $this->assertEquals('activo', $original->estado);
        $this->assertEquals('activo', $contraasiento->estado);

        // El original tiene referencia al contraasiento
        $this->assertEquals($contraasiento->id, $original->anulado_por_movimiento_id);
    }

    public function test_crear_movimiento_venta(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 5000);
        $ventaPago = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        // Asegurar que la relacion venta esta cargada
        $ventaPago->load('venta');

        $movimiento = MovimientoCuentaCorriente::crearMovimientoVenta($ventaPago, 1);

        $this->assertEquals('5000.00', $movimiento->debe);
        $this->assertEquals('0.00', $movimiento->haber);
        $this->assertEquals(MovimientoCuentaCorriente::TIPO_VENTA, $movimiento->tipo);
        $this->assertEquals($cliente->id, $movimiento->cliente_id);
        $this->assertEquals($venta->id, $movimiento->venta_id);
        $this->assertEquals($ventaPago->id, $movimiento->venta_pago_id);
    }

    public function test_crear_movimiento_cobro(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 5000);
        $ventaPago = VentaPago::where('venta_id', $venta->id)->first();

        $cobro = $this->crearCobroPrueba($cliente->id, [
            'monto_cobrado' => 2000,
            'monto_aplicado_a_deuda' => 2000,
        ]);

        $cobroVenta = CobroVenta::create([
            'cobro_id' => $cobro->id,
            'venta_id' => $venta->id,
            'venta_pago_id' => $ventaPago->id,
            'monto_aplicado' => 2000,
            'interes_aplicado' => 0,
            'saldo_anterior' => 5000,
            'saldo_posterior' => 3000,
        ]);

        $movimiento = MovimientoCuentaCorriente::crearMovimientoCobro($cobro, $cobroVenta, 1);

        $this->assertEquals('0.00', $movimiento->debe);
        $this->assertEquals('2000.00', $movimiento->haber);
        $this->assertEquals(MovimientoCuentaCorriente::TIPO_COBRO, $movimiento->tipo);
        $this->assertEquals($cobro->id, $movimiento->cobro_id);
    }

    public function test_crear_movimiento_anticipo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $cobro = $this->crearCobroPrueba($cliente->id, [
            'tipo' => 'anticipo',
            'monto_cobrado' => 1000,
            'monto_a_favor' => 1000,
        ]);

        $movimiento = MovimientoCuentaCorriente::crearMovimientoAnticipo($cobro, 1000, 1);

        $this->assertEquals('0.00', $movimiento->debe);
        $this->assertEquals('0.00', $movimiento->haber);
        $this->assertEquals('0.00', $movimiento->saldo_favor_debe);
        $this->assertEquals('1000.00', $movimiento->saldo_favor_haber);
        $this->assertEquals(MovimientoCuentaCorriente::TIPO_ANTICIPO, $movimiento->tipo);
    }

    public function test_crear_movimiento_uso_saldo_favor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $cobro = $this->crearCobroPrueba($cliente->id, [
            'saldo_favor_usado' => 500,
        ]);

        $movimiento = MovimientoCuentaCorriente::crearMovimientoUsoSaldoFavor($cobro, 500, 1);

        $this->assertEquals('0.00', $movimiento->debe);
        $this->assertEquals('500.00', $movimiento->haber);
        $this->assertEquals('500.00', $movimiento->saldo_favor_debe);
        $this->assertEquals('0.00', $movimiento->saldo_favor_haber);
        $this->assertEquals(MovimientoCuentaCorriente::TIPO_USO_SALDO_FAVOR, $movimiento->tipo);
    }

    public function test_obtener_saldos_retorna_ambos(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Crear movimiento de deuda
        $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 5000,
        ]);

        // Crear movimiento de saldo a favor
        $this->crearMovimientoDirecto($cliente->id, [
            'tipo' => MovimientoCuentaCorriente::TIPO_ANTICIPO,
            'saldo_favor_haber' => 1000,
        ]);

        $saldos = MovimientoCuentaCorriente::obtenerSaldos($cliente->id, $this->sucursalId);

        $this->assertArrayHasKey('saldo_deudor', $saldos);
        $this->assertArrayHasKey('saldo_favor', $saldos);
        $this->assertEquals(5000, $saldos['saldo_deudor']);
        $this->assertEquals(1000, $saldos['saldo_favor']);
    }

    // =========================================================================
    // PR L (Repaso 3): snapshot id+tasa de moneda en MovimientoCuentaCorriente
    // =========================================================================

    /**
     * Helper: crea un VentaPago de cuenta corriente con o sin moneda extranjera.
     */
    private function crearVentaPagoCC(Venta $venta, float $monto, array $monedaData = []): VentaPago
    {
        $ccData = $this->crearFormaPagoCC();

        return VentaPago::create(array_merge([
            'venta_id' => $venta->id,
            'forma_pago_id' => $ccData['formaPago']->id,
            'concepto_pago_id' => $ccData['concepto']->id,
            'monto_base' => $monto,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $monto,
            'saldo_pendiente' => $monto,
            'es_cuenta_corriente' => true,
            'afecta_caja' => false,
            'estado' => 'activo',
        ], $monedaData));
    }

    public function test_crear_movimiento_venta_propaga_moneda_extranjera(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasica(['cliente_id' => $cliente->id]);

        // Pago CC en USD: monto_final 1500 ARS = 1 USD × tasa 1500
        $ventaPago = $this->crearVentaPagoCC($venta, 1500, [
            'moneda_id' => 7,
            'tipo_cambio_id' => 42,
            'tipo_cambio_tasa' => 1500.000000,
            'monto_moneda_original' => 1.00,
        ]);

        $mov = MovimientoCuentaCorriente::crearMovimientoVenta($ventaPago, 1);

        $this->assertEquals(7, $mov->moneda_id, 'Preserva moneda_id del VentaPago');
        $this->assertEquals(42, $mov->tipo_cambio_id, 'Preserva tipo_cambio_id (snapshot)');
        $this->assertEquals('1500.000000', $mov->tipo_cambio_tasa);
        $this->assertEquals('1.00', $mov->monto_moneda_original);
        $this->assertEquals('1500.00', $mov->debe);
    }

    public function test_crear_movimiento_venta_sin_moneda_deja_campos_null(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasica(['cliente_id' => $cliente->id]);

        // Pago CC en ARS (sin moneda_id)
        $ventaPago = $this->crearVentaPagoCC($venta, 1000);

        $mov = MovimientoCuentaCorriente::crearMovimientoVenta($ventaPago, 1);

        $this->assertNull($mov->moneda_id);
        $this->assertNull($mov->tipo_cambio_id);
        $this->assertNull($mov->tipo_cambio_tasa);
        $this->assertNull($mov->monto_moneda_original);
    }

    public function test_crear_movimiento_cobro_propaga_moneda_del_venta_pago_original(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasica(['cliente_id' => $cliente->id]);
        $ventaPago = $this->crearVentaPagoCC($venta, 1500, [
            'moneda_id' => 7,
            'tipo_cambio_id' => 42,
            'tipo_cambio_tasa' => 1500.000000,
            'monto_moneda_original' => 1.00,
        ]);

        $cobro = $this->crearCobroPrueba($cliente->id);
        $cobroVenta = CobroVenta::create([
            'cobro_id' => $cobro->id,
            'venta_id' => $venta->id,
            'venta_pago_id' => $ventaPago->id,
            'monto_aplicado' => 1500,
            'interes_aplicado' => 0,
            'saldo_anterior' => 1500,
            'saldo_posterior' => 0,
        ]);

        $mov = MovimientoCuentaCorriente::crearMovimientoCobro($cobro, $cobroVenta, 1);

        $this->assertEquals(7, $mov->moneda_id, 'Cobro hereda moneda de la deuda original');
        $this->assertEquals(42, $mov->tipo_cambio_id);
        $this->assertEquals('1500.000000', $mov->tipo_cambio_tasa);
        $this->assertEquals('1.00', $mov->monto_moneda_original);
        $this->assertEquals('1500.00', $mov->haber);
    }

    public function test_contraasiento_preserva_snapshot_de_moneda(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $original = $this->crearMovimientoDirecto($cliente->id, [
            'debe' => 1500,
            'moneda_id' => 7,
            'tipo_cambio_id' => 42,
            'tipo_cambio_tasa' => 1500.000000,
            'monto_moneda_original' => 1.00,
        ]);

        $contraasiento = MovimientoCuentaCorriente::crearContraasiento($original, 'Test', 1);
        $original->refresh();

        $this->assertEquals(7, $contraasiento->moneda_id);
        $this->assertEquals(42, $contraasiento->tipo_cambio_id);
        $this->assertEquals('1500.000000', $contraasiento->tipo_cambio_tasa);
        $this->assertEquals('1.00', $contraasiento->monto_moneda_original);
        $this->assertEquals('1500.00', $contraasiento->haber, 'Invierte debe/haber');
        $this->assertEquals($contraasiento->id, $original->anulado_por_movimiento_id);
    }
}
