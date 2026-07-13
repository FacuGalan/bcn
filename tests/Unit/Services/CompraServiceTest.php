<?php

namespace Tests\Unit\Services;

use App\Models\ArticuloCosto;
use App\Models\Compra;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\HistorialCosto;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\MovimientoStock;
use App\Models\Proveedor;
use App\Models\Stock;
use App\Services\CompraService;
use Exception;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Integración compra → costo → ledger → NC (Fase 4 del spec compras-costos).
 */
class CompraServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected CompraService $servicio;

    protected Proveedor $proveedor;

    protected array $cuitsCreados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->servicio = app(CompraService::class);
        $this->proveedor = Proveedor::create(['nombre' => 'Proveedor Test '.uniqid(), 'activo' => true]);
        Impuesto::firstOrCreate(
            ['codigo' => 'iva_credito'],
            ['nombre' => 'IVA crédito', 'tipo' => Impuesto::TIPO_IVA, 'naturaleza_default' => 'credito_fiscal', 'jurisdiccion' => 'AR', 'es_sistema' => true, 'activo' => true],
        );
    }

    protected function tearDown(): void
    {
        if ($this->cuitsCreados !== []) {
            // El DELETE selectivo de WithTenant corre recién en el setUp del
            // próximo test: los movimientos fiscales de estos cuits se limpian
            // acá para que la FK no bloquee el borrado.
            DB::connection('pymes_tenant')->table('movimientos_fiscales')->whereIn('cuit_id', $this->cuitsCreados)->delete();
            DB::connection('pymes_tenant')->table('cuit_sucursal')->whereIn('cuit_id', $this->cuitsCreados)->delete();
            DB::connection('pymes_tenant')->table('cuits')->whereIn('id', $this->cuitsCreados)->delete();
        }
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Borradores ====================

    public function test_borrador_no_tiene_efectos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $compra = $this->borradorFacturaA($articulo, cantidad: 10, precio: 100);

        $this->assertEquals(Compra::ESTADO_BORRADOR, $compra->estado);
        $this->assertEquals(0.0, $this->stockDe($articulo->id));
        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $compra->id)->count());
        $this->assertNull(ArticuloCosto::where('articulo_id', $articulo->id)->first());
        // Totales informativos: 10×100 + IVA 210 = 1210
        $this->assertEquals(1210.0, (float) $compra->total);
    }

    public function test_actualizar_borrador_reemplaza_renglones(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->borradorFacturaA($articulo, 10, 100);

        $actualizada = $this->servicio->actualizarBorrador($compra, [
            'proveedor_id' => $this->proveedor->id,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => '2026-07-01',
            'cuit_id' => $compra->cuit_id,
            'usuario_id' => 1,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 5, 'factor_conversion' => 1, 'precio_unitario' => 200, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], ['ivas' => [['alicuota' => 21, 'base_imponible' => 1000, 'importe' => 210]]]);

        $this->assertCount(1, $actualizada->detalles);
        $this->assertEquals(5.0, (float) $actualizada->detalles->first()->cantidad);
        $this->assertEquals(1210.0, (float) $actualizada->total);
    }

    public function test_borrador_se_elimina_sin_reversas(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->borradorFacturaA($articulo, 10, 100);

        $this->servicio->eliminarBorrador($compra);

        $this->assertNull(Compra::find($compra->id));
        $this->assertEquals(0, DB::connection('pymes_tenant')->table('compras_detalle')->where('compra_id', $compra->id)->count());
    }

    // ==================== Confirmación: circuito completo ====================

    public function test_confirmar_factura_a_de_ri_mueve_stock_costos_y_credito(): void
    {
        // Criterio del spec: factura A de comprador RI ⇒ costo = neto con
        // descuentos; el IVA va al ledger como crédito; percepciones al ledger.
        $percIibb = Impuesto::firstOrCreate(
            ['codigo' => 'perc_iibb_test'],
            ['nombre' => 'Perc IIBB', 'tipo' => Impuesto::TIPO_IIBB, 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B', 'es_sistema' => true, 'activo' => true],
        );
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);

        $compra = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'numero_comprobante_proveedor' => '0001-00001111',
            'fecha_comprobante' => '2026-06-15', // período JUNIO, cargada en julio
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 10, 'factor_conversion' => 1, 'precio_unitario' => 100, 'descuentos' => [10], 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 900, 'importe' => 189]],
            'percepciones' => [['impuesto_id' => $percIibb->id, 'base_imponible' => 900, 'alicuota' => 3, 'monto' => 27]],
        ]);

        $confirmada = $this->servicio->confirmarCompra($compra, 1);

        // Estado + totales: 900 neto + 189 IVA + 27 percepción = 1116.
        $this->assertEquals(Compra::ESTADO_COMPLETADA, $confirmada->estado);
        $this->assertEquals(1116.0, (float) $confirmada->total);
        $this->assertEquals(1116.0, (float) $confirmada->saldo_pendiente);

        // Stock: +10 con costo computable NETO (90) en el movimiento.
        $this->assertEquals(10.0, $this->stockDe($articulo->id));
        $mov = MovimientoStock::where('compra_id', $confirmada->id)->first();
        $this->assertEquals(90.0, (float) $mov->costo_unitario);

        // Costos: neto con descuento (100 × 0,90 = 90).
        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(90.0, (float) $costo->costo_ultimo);

        // Ledger: crédito 189 en JUNIO (fecha_comprobante) + percepción 27.
        $credito = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $confirmada->id)
            ->where('naturaleza', MovimientoFiscal::NATURALEZA_CREDITO_FISCAL)->first();
        $this->assertEquals(189.0, (float) $credito->monto);
        $this->assertEquals('2026-06', $credito->periodo_fiscal);

        $percepcion = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $confirmada->id)
            ->where('naturaleza', MovimientoFiscal::NATURALEZA_PERCEPCION)->first();
        $this->assertEquals(27.0, (float) $percepcion->monto);
    }

    public function test_confirmar_factura_b_de_ri_todo_al_costo_sin_credito(): void
    {
        // Criterio del spec: factura B de RI ⇒ costo = total pagado, nada al ledger de IVA.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);

        $compra = $this->borrador($articulo, Compra::TIPO_FACTURA_B, $cuit->id, cantidad: 10, precio: 121);
        $confirmada = $this->servicio->confirmarCompra($compra, 1);

        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(121.0, (float) $costo->costo_ultimo); // precio FINAL, tal cual
        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $confirmada->id)->count());
    }

    public function test_factura_a_con_comprador_monotributo_carga_sin_credito_y_con_iva_al_costo(): void
    {
        // Criterio del spec (RG 5003/2021): factura A bajo CUIT monotributista
        // SE PUEDE cargar; sin crédito; todo lo pagado (IVA incluido) es costo.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_MONOTRIBUTO);

        $compra = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => '2026-07-01',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 10, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 1000, 'importe' => 210]],
        ]);

        $confirmada = $this->servicio->confirmarCompra($compra, 1);

        // Costo computable = neto 100 × 1,21 = 121 (el IVA no recuperable ES costo).
        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(121.0, (float) $costo->costo_ultimo);

        // Sin crédito ni percepciones al ledger (gate del caller: comprador no RI).
        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $confirmada->id)->count());
    }

    public function test_compra_no_fiscal_sin_nada_al_ledger(): void
    {
        // Criterio del spec (D15): toggle NO FISCAL ⇒ sin compra_ivas, sin
        // percepciones, nada al ledger; el total pagado es el costo.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $compra = $this->borrador($articulo, Compra::TIPO_NO_FISCAL, null, 10, 121);
        $confirmada = $this->servicio->confirmarCompra($compra, 1);

        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(121.0, (float) $costo->costo_ultimo);
        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $confirmada->id)->count());
    }

    public function test_descuento_global_y_concepto_flete_prorratean_al_costo(): void
    {
        // Criterio del spec: descuento global −5% prorrateado + flete que computa.
        $articuloA = $this->crearArticuloConStock($this->sucursalId, 0);
        $articuloB = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);

        $compra = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => '2026-07-01',
            'descuento_global_porcentaje' => 5,
        ], [
            ['articulo_id' => $articuloA->id, 'cantidad_comprada' => 6, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
            ['articulo_id' => $articuloB->id, 'cantidad_comprada' => 4, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 950, 'importe' => 199.5]],
            'conceptos' => [
                ['tipo' => 'flete', 'monto' => 100, 'computa_costo' => true],
                ['tipo' => 'envases', 'monto' => 50, 'computa_costo' => false],
            ],
        ]);

        $confirmada = $this->servicio->confirmarCompra($compra, 1);

        // Renglón A: (600 − 30 global + 60 flete) / 6 = 105; renglón B ídem = 105.
        $costoA = ArticuloCosto::where('articulo_id', $articuloA->id)->where('sucursal_id', $this->sucursalId)->first();
        $costoB = ArticuloCosto::where('articulo_id', $articuloB->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(105.0, (float) $costoA->costo_ultimo);
        $this->assertEquals(105.0, (float) $costoB->costo_ultimo);

        // Total: 1000 − 50 + 150 conceptos + 199,50 IVA = 1299,50 (el flete que
        // computa costo igual suma al total del comprobante).
        $this->assertEquals(1299.50, (float) $confirmada->total);
    }

    public function test_factor_de_conversion_stockea_unidades(): void
    {
        // Criterio del spec: 2 bultos x12 @ $1200 ⇒ stock +24, costo unitario 100.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $compra = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 2, 'factor_conversion' => 12, 'precio_unitario' => 1200],
        ]);

        $this->servicio->confirmarCompra($compra, 1);

        $this->assertEquals(24.0, $this->stockDe($articulo->id));
        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(100.0, (float) $costo->costo_ultimo);
    }

    // ==================== Validaciones ====================

    public function test_fecha_comprobante_obligatoria_en_fiscales(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);
        $compra = $this->borrador($articulo, Compra::TIPO_FACTURA_A, $cuit->id, 1, 100, fechaComprobante: null);

        $this->expectExceptionMessage('fecha del comprobante');
        $this->servicio->confirmarCompra($compra, 1);
    }

    public function test_anti_duplicado_bloquea_activa_y_permite_recargar_cancelada(): void
    {
        // Criterio del spec: cancelar y recargar la misma factura es posible;
        // cargarla dos veces activa, NO.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $data = [
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
            'numero_comprobante_proveedor' => '0003-00012345',
        ];
        $renglones = [['articulo_id' => $articulo->id, 'cantidad_comprada' => 1, 'factor_conversion' => 1, 'precio_unitario' => 100]];

        $primera = $this->servicio->crearBorrador($data, $renglones);
        $this->servicio->confirmarCompra($primera, 1);

        try {
            $this->servicio->crearBorrador($data, $renglones);
            $this->fail('Debió bloquear el duplicado activo');
        } catch (Exception $e) {
            $this->assertStringContainsString('Ya existe una compra activa', $e->getMessage());
        }

        $this->servicio->cancelarCompra($primera->fresh(), 1, 'error de carga');

        $recargada = $this->servicio->crearBorrador($data, $renglones);
        $this->assertNotNull($recargada->id);
    }

    public function test_confirmar_dos_veces_falla(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->borrador($articulo, Compra::TIPO_NO_FISCAL, null, 1, 100);
        $this->servicio->confirmarCompra($compra, 1);

        $this->expectExceptionMessage('Solo se puede confirmar un borrador');
        $this->servicio->confirmarCompra($compra->fresh(), 1);
    }

    // ==================== Cancelación ====================

    public function test_cancelar_revierte_stock_costos_y_ledger_cross_periodo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);

        $compra = $this->borradorFacturaA($articulo, 10, 100, $cuit->id, fechaComprobante: '2026-05-15');
        $this->servicio->confirmarCompra($compra, 1);

        $cancelada = $this->servicio->cancelarCompra($compra->fresh(), 1, 'prueba');

        $this->assertEquals(Compra::ESTADO_CANCELADA, $cancelada->estado);
        $this->assertEquals(0.0, $this->stockDe($articulo->id));

        // Ledger: original activo en 2026-05 + reversa negativa en el período ACTUAL.
        $movs = MovimientoFiscal::activos()->where('origen_tipo', 'Compra')->where('origen_id', $compra->id)->get();
        $this->assertCount(2, $movs);
        $this->assertEqualsWithDelta(0.0, (float) $movs->sum('monto'), 0.001);
        $this->assertEquals(now()->format('Y-m'), $movs->first(fn ($m) => (float) $m->monto < 0)->periodo_fiscal);

        // Costos: historial con origen cancelacion.
        $this->assertTrue(HistorialCosto::where('articulo_id', $articulo->id)->where('origen', 'cancelacion')->exists());
    }

    // ==================== Nota de crédito (RF-21) ====================

    public function test_nc_parcial_devuelve_stock_reversa_credito_y_no_toca_costos(): void
    {
        // Criterio del spec: NC por 3 de 10 ⇒ stock −3, reversa del crédito en
        // el PERÍODO de la NC con SU desglose, costo_ultimo y PPP intactos.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);

        $compra = $this->borradorFacturaA($articulo, 10, 100, $cuit->id, fechaComprobante: '2026-06-15');
        $this->servicio->confirmarCompra($compra, 1);

        $costoAntes = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();

        $nc = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'compra_origen_id' => $compra->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_A,
            'numero_comprobante_proveedor' => 'NC-0001-777',
            'fecha_comprobante' => '2026-07-05',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 3, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 300, 'importe' => 63]],
        ]);

        $ncConfirmada = $this->servicio->confirmarCompra($nc, 1);

        // Stock: 10 − 3 = 7.
        $this->assertEquals(7.0, $this->stockDe($articulo->id));

        // Fiscal: reversa NEGATIVA con el desglose PROPIO de la NC, período de la NC.
        $reversa = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', $ncConfirmada->id)->first();
        $this->assertEquals(-63.0, (float) $reversa->monto);
        $this->assertEquals('2026-07', $reversa->periodo_fiscal);

        // Costos intactos (una devolución parcial no restaura el costo anterior).
        $costoDespues = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals((float) $costoAntes->costo_ultimo, (float) $costoDespues->costo_ultimo);
        $this->assertEquals((float) $costoAntes->costo_promedio, (float) $costoDespues->costo_promedio);
        $this->assertEquals($compra->id, $costoDespues->compra_ultima_id);
    }

    public function test_cancelar_nc_repone_el_stock_devuelto(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->borrador($articulo, Compra::TIPO_NO_FISCAL, null, 10, 100);
        $this->servicio->confirmarCompra($compra, 1);

        $nc = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'compra_origen_id' => $compra->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 3, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        $this->servicio->confirmarCompra($nc, 1);
        $this->assertEquals(7.0, $this->stockDe($articulo->id));

        $this->servicio->cancelarCompra($nc->fresh(), 1, 'NC mal cargada');

        $this->assertEquals(10.0, $this->stockDe($articulo->id));
    }

    public function test_nc_de_otro_proveedor_es_invalida(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->borrador($articulo, Compra::TIPO_NO_FISCAL, null, 10, 100);
        $this->servicio->confirmarCompra($compra, 1);

        $otroProveedor = Proveedor::create(['nombre' => 'Otro '.uniqid(), 'activo' => true]);

        $nc = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $otroProveedor->id,
            'compra_origen_id' => $compra->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 1, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);

        $this->expectExceptionMessage('mismo proveedor');
        $this->servicio->confirmarCompra($nc, 1);
    }

    // ==================== Advertencias ====================

    public function test_advertencia_factura_a_comprador_no_ri(): void
    {
        // El catálogo de config_test puede no traer todos los códigos (CI).
        $condicionMono = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_MONOTRIBUTO], ['nombre' => 'Monotributo']);
        $condicionRi = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'Responsable Inscripto']);

        $this->assertNotNull($this->servicio->advertenciaComprobanteCuit($condicionMono, Compra::TIPO_FACTURA_A));
        $this->assertNotNull($this->servicio->advertenciaComprobanteCuit($condicionRi, Compra::TIPO_FACTURA_B));
        $this->assertNull($this->servicio->advertenciaComprobanteCuit($condicionRi, Compra::TIPO_FACTURA_A));
        $this->assertNull($this->servicio->advertenciaComprobanteCuit($condicionMono, Compra::TIPO_FACTURA_C));
    }

    /**
     * Matriz emisor (proveedor) × receptor (CUIT comprador) — espejo invertido
     * de la regla de ventas (RI→RI/Mono = A por RG 5003; RI→resto = B;
     * emisor mono/exento = C).
     */
    public function test_sugerir_tipo_comprobante_matriz_emisor_receptor(): void
    {
        // El catálogo de config_test puede no traer todos los códigos.
        $ri = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'Responsable Inscripto']);
        $mono = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_MONOTRIBUTO], ['nombre' => 'Monotributo']);
        $exento = CondicionIva::firstOrCreate(['codigo' => CondicionIva::SUJETO_EXENTO], ['nombre' => 'Sujeto Exento']);
        $cf = CondicionIva::firstOrCreate(['codigo' => CondicionIva::CONSUMIDOR_FINAL], ['nombre' => 'Consumidor Final']);

        // Proveedor RI
        $this->assertSame(Compra::TIPO_FACTURA_A, $this->servicio->sugerirTipoComprobante($ri, $ri));
        $this->assertSame(Compra::TIPO_FACTURA_A, $this->servicio->sugerirTipoComprobante($ri, $mono));
        $this->assertSame(Compra::TIPO_FACTURA_B, $this->servicio->sugerirTipoComprobante($ri, $exento));
        $this->assertSame(Compra::TIPO_FACTURA_B, $this->servicio->sugerirTipoComprobante($ri, $cf));
        $this->assertSame(Compra::TIPO_FACTURA_B, $this->servicio->sugerirTipoComprobante($ri, null));

        // Proveedor monotributista / exento emite C, sea quien sea el receptor
        $this->assertSame(Compra::TIPO_FACTURA_C, $this->servicio->sugerirTipoComprobante($mono, $ri));
        $this->assertSame(Compra::TIPO_FACTURA_C, $this->servicio->sugerirTipoComprobante($exento, $ri));

        // Sin condición del proveedor: B conservador (no asume crédito)
        $this->assertSame(Compra::TIPO_FACTURA_B, $this->servicio->sugerirTipoComprobante(null, $ri));

        // Modo NC: misma letra, tipo nota de crédito
        $this->assertSame(Compra::TIPO_NC_A, $this->servicio->sugerirTipoComprobante($ri, $ri, esNC: true));
        $this->assertSame(Compra::TIPO_NC_C, $this->servicio->sugerirTipoComprobante($mono, $ri, esNC: true));
        $this->assertSame(Compra::TIPO_NC_B, $this->servicio->sugerirTipoComprobante($ri, $cf, esNC: true));
    }

    // ==================== Repricing automático (RF-11, Fase 8) ====================

    public function test_articulo_con_flag_se_repricea_al_confirmar(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $articulo->update([
            'precio_base' => 100,
            'utilidad_porcentaje' => 50,
            'precio_administrado_por_utilidad' => true,
        ]);

        $sinFlag = $this->crearArticuloConStock($this->sucursalId, 0);
        $sinFlag->update(['precio_base' => 100]);

        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);
        $borrador = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => '2026-07-01',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 10, 'factor_conversion' => 1, 'precio_unitario' => 200, 'tipo_iva_id' => $this->tiposIva[5]->id],
            ['articulo_id' => $sinFlag->id, 'cantidad_comprada' => 5, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 2500, 'importe' => 525]],
        ]);

        $this->servicio->confirmarCompra($borrador, 1);

        // Fórmula canónica sobre el costo NUEVO (200), con la alícuota
        // efectiva D21 del entorno (0 si el comercio no computa IVA).
        $alicuota = app(\App\Services\CostoService::class)->alicuotaEfectiva($articulo->fresh(), $this->sucursalId);
        $esperado = round(200 * 1.5 * (1 + $alicuota / 100), 2);

        $this->assertEquals($esperado, (float) $articulo->fresh()->precio_base);
        $this->assertEquals(100.0, (float) $sinFlag->fresh()->precio_base); // sin flag: intacto

        $this->assertCount(1, $this->servicio->ultimoRepricing);
        $this->assertEquals($articulo->id, $this->servicio->ultimoRepricing[0]['articulo_id']);
        $this->assertEquals('global', $this->servicio->ultimoRepricing[0]['alcance']);

        $historial = \App\Models\HistorialPrecio::where('articulo_id', $articulo->id)
            ->where('origen', 'utilidad_automatica')
            ->latest('id')
            ->first();
        $this->assertNotNull($historial);
        $this->assertEquals($esperado, (float) $historial->precio_nuevo);
    }

    // ==================== Corrección (D7 #12, Fase 6) ====================

    public function test_corregir_compra_cancela_y_recrea_atomico(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);
        $compra = $this->servicio->confirmarCompra($this->borradorFacturaA($articulo, 10, 100, $cuit->id), 1);

        $this->assertEquals(10.0, $this->stockDe($articulo->id));

        $neto = 8 * 120;
        $nueva = $this->servicio->corregirCompra($compra, [
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => '2026-07-01',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 8, 'factor_conversion' => 1, 'precio_unitario' => 120, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => $neto, 'importe' => round($neto * 0.21, 2)]],
        ]);

        // Original cancelada con rastro cruzado; la nueva completada.
        $original = $compra->fresh();
        $this->assertEquals(Compra::ESTADO_CANCELADA, $original->estado);
        $this->assertStringContainsString($nueva->numero_comprobante, $original->observaciones);
        $this->assertEquals(Compra::ESTADO_COMPLETADA, $nueva->estado);

        // El stock y el costo vigente quedan los de la corrección.
        $this->assertEquals(8.0, $this->stockDe($articulo->id));
        $this->assertEquals(round($neto * 1.21, 2), (float) $nueva->total);
        $costo = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(120.0, (float) $costo->costo_ultimo);
    }

    public function test_corregir_compra_con_nc_vinculada_esta_bloqueada(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $cuit = $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO);
        $compra = $this->servicio->confirmarCompra($this->borradorFacturaA($articulo, 10, 100, $cuit->id), 1);

        $nc = $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'compra_origen_id' => $compra->id,
            'cuit_id' => $cuit->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_A,
            'fecha_comprobante' => '2026-07-02',
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 2, 'factor_conversion' => 1, 'precio_unitario' => 100, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => 200, 'importe' => 42]],
        ]);
        $this->servicio->confirmarCompra($nc, 1);

        $this->expectExceptionMessage('notas de crédito vinculadas');
        $this->servicio->corregirCompra($compra, ['usuario_id' => 1], [], []);
    }

    public function test_corregir_solo_aplica_a_completadas(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $borrador = $this->borradorFacturaA($articulo, 10, 100);

        $this->expectExceptionMessage('completada');
        $this->servicio->corregirCompra($borrador, ['usuario_id' => 1], [], []);
    }

    // ==================== Helpers ====================

    private function borradorFacturaA($articulo, float $cantidad, float $precio, ?int $cuitId = null, ?string $fechaComprobante = '2026-07-01'): Compra
    {
        $cuitId ??= $this->crearCuit(CondicionIva::RESPONSABLE_INSCRIPTO)->id;

        $neto = $cantidad * $precio;

        return $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuitId,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_FACTURA_A,
            'fecha_comprobante' => $fechaComprobante,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => $cantidad, 'factor_conversion' => 1, 'precio_unitario' => $precio, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ], [
            'ivas' => [['alicuota' => 21, 'base_imponible' => $neto, 'importe' => round($neto * 0.21, 2)]],
        ]);
    }

    private function borrador($articulo, string $tipo, ?int $cuitId, float $cantidad, float $precio, ?string $fechaComprobante = '2026-07-01'): Compra
    {
        return $this->servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'cuit_id' => $cuitId,
            'usuario_id' => 1,
            'tipo_comprobante' => $tipo,
            'fecha_comprobante' => in_array($tipo, Compra::TIPOS_NO_FISCALES, true) ? null : $fechaComprobante,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => $cantidad, 'factor_conversion' => 1, 'precio_unitario' => $precio, 'tipo_iva_id' => $this->tiposIva[5]->id],
        ]);
    }

    private function stockDe(int $articuloId): float
    {
        return (float) Stock::where('articulo_id', $articuloId)
            ->where('sucursal_id', $this->sucursalId)
            ->value('cantidad');
    }

    private function crearCuit(int $codigoCondicion): Cuit
    {
        // firstOrCreate: el catálogo de config_test puede no traer el código (CI).
        $condicion = CondicionIva::firstOrCreate(['codigo' => $codigoCondicion], ['nombre' => 'Condición '.$codigoCondicion]);

        $cuit = Cuit::create([
            'numero_cuit' => '20'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT).'7',
            'razon_social' => 'CUIT Compra Test '.uniqid(),
            'condicion_iva_id' => $condicion->id,
            'activo' => true,
        ]);

        $this->cuitsCreados[] = $cuit->id;

        return $cuit;
    }
}
