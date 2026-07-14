<?php

namespace Tests\Unit\Services;

use App\Models\ArticuloCosto;
use App\Models\ArticuloProveedor;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\HistorialCosto;
use App\Models\Proveedor;
use App\Models\Stock;
use App\Services\CostoService;
use Exception;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class CostoServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected CostoService $servicio;

    protected Proveedor $proveedor;

    /** @var array<int> IDs de cuits creados por el test (limpieza selectiva) */
    protected array $cuitsCreados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->servicio = new CostoService;
        $this->proveedor = Proveedor::create(['nombre' => 'Proveedor Test '.uniqid(), 'activo' => true]);
    }

    protected function tearDown(): void
    {
        // cuits no está en el DELETE selectivo de WithTenant: limpieza SOLO de
        // los creados acá (un DELETE global chocaría con FKs de residuos de
        // otras suites, ej. cuentas_empresa → cuits).
        if ($this->cuitsCreados !== []) {
            DB::connection('pymes_tenant')->table('cuit_sucursal')->whereIn('cuit_id', $this->cuitsCreados)->delete();
            DB::connection('pymes_tenant')->table('cuits')->whereIn('id', $this->cuitsCreados)->delete();
        }
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== costoComputableRenglon ====================

    public function test_cascada_de_descuentos_es_multiplicativa(): void
    {
        // Criterio del spec: 1000 con 10+5+3 ⇒ 829,35 (cascada, no suma).
        $costo = $this->servicio->costoComputableRenglon([
            'precio_unitario' => 1000,
            'descuentos' => [10, 5, 3],
            'cantidad_comprada' => 1,
            'factor_conversion' => 1,
        ]);

        $this->assertEquals(829.35, $costo);
    }

    public function test_descuento_global_y_conceptos_ajustan_el_costo(): void
    {
        // 10 unidades a $100 = $1000; −$50 de descuento global +$30 de flete
        // que computa ⇒ $980 / 10 = $98 por unidad.
        $costo = $this->servicio->costoComputableRenglon([
            'precio_unitario' => 100,
            'descuentos' => [],
            'cantidad_comprada' => 10,
            'factor_conversion' => 1,
            'descuento_global_monto' => 50,
            'conceptos_costo_monto' => 30,
        ]);

        $this->assertEquals(98.0, $costo);
    }

    public function test_factor_de_conversion_lleva_a_unidad_de_stock(): void
    {
        // Criterio del spec: 2 bultos x12 @ $1200/bulto ⇒ costo unitario $100.
        $costo = $this->servicio->costoComputableRenglon([
            'precio_unitario' => 1200,
            'descuentos' => [],
            'cantidad_comprada' => 2,
            'factor_conversion' => 12,
        ]);

        $this->assertEquals(100.0, $costo);
    }

    public function test_renglon_sin_cantidad_es_invalido(): void
    {
        $this->expectException(Exception::class);

        $this->servicio->costoComputableRenglon([
            'precio_unitario' => 100,
            'cantidad_comprada' => 0,
            'factor_conversion' => 1,
        ]);
    }

    // ==================== prorratearPorImporte ====================

    public function test_prorrateo_por_importe_proporcional(): void
    {
        $asignados = $this->servicio->prorratearPorImporte([600.0, 400.0], 100.0);

        $this->assertEquals([60.0, 40.0], $asignados);
    }

    public function test_prorrateo_preserva_la_suma_exacta(): void
    {
        $asignados = $this->servicio->prorratearPorImporte([100.0, 100.0, 100.0], 100.0);

        $this->assertEqualsWithDelta(100.0, array_sum($asignados), 0.00001);
    }

    // ==================== registrarDesdeCompra ====================

    public function test_compra_confirmada_actualiza_costos_proveedor_e_historial(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0, 'codigo_proveedor' => 'PROV-123'],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        // Fila de la sucursal + consolidada (criterio del spec).
        $filaSucursal = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $filaConsolidada = ArticuloCosto::where('articulo_id', $articulo->id)->whereNull('sucursal_id')->first();

        $this->assertEquals(130.0, (float) $filaSucursal->costo_ultimo);
        $this->assertEquals(130.0, (float) $filaConsolidada->costo_ultimo);
        $this->assertEquals($this->proveedor->id, $filaSucursal->proveedor_ultimo_id);
        $this->assertEquals($compra->id, $filaSucursal->compra_ultima_id);

        // Upsert de articulo_proveedor con código y costo del proveedor.
        $vinculo = ArticuloProveedor::where('articulo_id', $articulo->id)->where('proveedor_id', $this->proveedor->id)->first();
        $this->assertNotNull($vinculo);
        $this->assertEquals('PROV-123', $vinculo->codigo_proveedor);
        $this->assertEquals(130.0, (float) $vinculo->costo_ultimo);

        // Historial por fila (sucursal + consolidada).
        $this->assertEquals(2, HistorialCosto::where('compra_id', $compra->id)->where('origen', 'compra')->count());
    }

    public function test_ppp_pondera_stock_previo_con_la_compra(): void
    {
        // Criterio del spec: stock 10 @ $100 + compra 5 @ $130 ⇒ PPP $110.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 15); // stock YA incluye la compra
        ArticuloCosto::create(['articulo_id' => $articulo->id, 'sucursal_id' => $this->sucursalId, 'costo_promedio' => 100, 'costo_ultimo' => 100]);
        ArticuloCosto::create(['articulo_id' => $articulo->id, 'sucursal_id' => null, 'costo_promedio' => 100, 'costo_ultimo' => 100]);

        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(110.0, (float) $fila->costo_promedio);
        $this->assertEquals(130.0, (float) $fila->costo_ultimo);
    }

    public function test_ppp_null_la_primera_compra_lo_fija(): void
    {
        // Criterio del spec: catálogo preexistente con stock pero sin costo —
        // el stock previo sin costo NO pondera.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 105); // 100 previas + 5 de la compra
        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(130.0, (float) $fila->costo_promedio);
    }

    public function test_ppp_con_stock_previo_negativo_toma_el_costo_nuevo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 3); // previo = 3 − 5 = −2
        ArticuloCosto::create(['articulo_id' => $articulo->id, 'sucursal_id' => $this->sucursalId, 'costo_promedio' => 100, 'costo_ultimo' => 100]);

        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(130.0, (float) $fila->costo_promedio);
    }

    public function test_registrar_desde_compra_es_idempotente(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);
        $ppp = (float) ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->value('costo_promedio');

        $this->servicio->registrarDesdeCompra($compra, 1);

        $this->assertEquals(2, HistorialCosto::where('compra_id', $compra->id)->count());
        $this->assertEquals($ppp, (float) ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->value('costo_promedio'));
    }

    public function test_dos_renglones_del_mismo_articulo_ponderan_dentro_del_comprobante(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 6, 'costo' => 100.0],
            ['articulo' => $articulo, 'cantidad' => 4, 'costo' => 150.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        // (6×100 + 4×150) / 10 = 120
        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(120.0, (float) $fila->costo_ultimo);
    }

    public function test_consolidado_pondera_stock_de_todas_las_sucursales(): void
    {
        $sucursalB = $this->crearSucursalAdicional('Sucursal B Costos');
        $articulo = $this->crearArticuloConStock($this->sucursalId, 15); // incluye compra de 5
        $this->setStock($articulo->id, $sucursalB, 20); // stock en otra sucursal

        ArticuloCosto::create(['articulo_id' => $articulo->id, 'sucursal_id' => null, 'costo_promedio' => 100, 'costo_ultimo' => 100]);

        $compra = $this->crearCompraConDetalles([
            ['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0],
        ]);

        $this->servicio->registrarDesdeCompra($compra, 1);

        // Consolidado: previo 30 (35 − 5) @ 100 + 5 @ 130 = 3650 / 35 ≈ 104.2857
        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->whereNull('sucursal_id')->first();
        $this->assertEqualsWithDelta(104.2857, (float) $fila->costo_promedio, 0.001);
    }

    // ==================== revertirCostoUltimoSiCorresponde ====================

    public function test_cancelar_la_compra_que_fijo_el_ultimo_restaura_el_anterior(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $compraA = $this->crearCompraConDetalles([['articulo' => $articulo, 'cantidad' => 5, 'costo' => 100.0]]);
        $this->servicio->registrarDesdeCompra($compraA, 1);

        $this->setStock($articulo->id, $this->sucursalId, 10);
        $compraB = $this->crearCompraConDetalles([['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0]]);
        $this->servicio->registrarDesdeCompra($compraB, 1);

        $this->servicio->revertirCostoUltimoSiCorresponde($compraB, 1);

        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(100.0, (float) $fila->costo_ultimo);
        $this->assertEquals($compraA->id, $fila->compra_ultima_id);

        // Historial origen cancelacion (criterio del spec) y PPP intacto.
        $this->assertTrue(
            HistorialCosto::where('articulo_id', $articulo->id)->where('origen', 'cancelacion')->exists()
        );
    }

    public function test_cancelar_una_compra_que_no_es_la_ultima_no_toca_el_costo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $compraA = $this->crearCompraConDetalles([['articulo' => $articulo, 'cantidad' => 5, 'costo' => 100.0]]);
        $this->servicio->registrarDesdeCompra($compraA, 1);

        $this->setStock($articulo->id, $this->sucursalId, 10);
        $compraB = $this->crearCompraConDetalles([['articulo' => $articulo, 'cantidad' => 5, 'costo' => 130.0]]);
        $this->servicio->registrarDesdeCompra($compraB, 1);

        $this->servicio->revertirCostoUltimoSiCorresponde($compraA, 1);

        $fila = ArticuloCosto::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(130.0, (float) $fila->costo_ultimo);
    }

    // ==================== actualizarManual ====================

    public function test_actualizar_reposicion_manual_registra_historial(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $fila = $this->servicio->actualizarManual($articulo, $this->sucursalId, 'reposicion', 150.0, 1);

        $this->assertEquals(150.0, (float) $fila->costo_reposicion);
        $this->assertTrue(
            HistorialCosto::where('articulo_id', $articulo->id)->where('tipo_costo', 'reposicion')->where('origen', 'manual')->exists()
        );
    }

    public function test_borrar_reposicion_vuelve_al_fallback(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $this->servicio->actualizarManual($articulo, $this->sucursalId, 'ultimo', 100.0, 1);
        $this->servicio->actualizarManual($articulo, $this->sucursalId, 'reposicion', 150.0, 1);

        $fila = $this->servicio->actualizarManual($articulo, $this->sucursalId, 'reposicion', null, 1);

        $this->assertNull($fila->costo_reposicion);
        $this->assertEquals(100.0, $fila->costoReposicionEfectivo());
    }

    // ==================== utilidadObjetivo (cascada RF-08) ====================

    public function test_cascada_de_utilidad_articulo_pisa_categoria_pisa_comercio(): void
    {
        // Criterio del spec: artículo 50 % pisa categoría 40 % pisa comercio 30 %.
        $categoria = Categoria::create(['nombre' => 'Cat Costos '.uniqid(), 'utilidad_porcentaje' => 40, 'activo' => true]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['categoria_id' => $categoria->id]);

        $this->assertEquals(40.0, $this->servicio->utilidadObjetivo($articulo));

        $articulo->update(['utilidad_porcentaje' => 50]);
        $this->assertEquals(50.0, $this->servicio->utilidadObjetivo($articulo->fresh()));

        $articulo->update(['utilidad_porcentaje' => null]);
        $categoria->update(['utilidad_porcentaje' => null]);
        $this->assertEquals(30.0, $this->servicio->utilidadObjetivo($articulo->fresh()));
    }

    // ==================== alicuotaEfectiva (D21) ====================

    public function test_alicuota_efectiva_comercio_ri_usa_la_del_articulo(): void
    {
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $this->assertEquals(21.0, $this->servicio->alicuotaEfectiva($articulo, $this->sucursalId));
    }

    public function test_alicuota_efectiva_comercio_monotributo_es_cero(): void
    {
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_MONOTRIBUTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $this->assertEquals(0.0, $this->servicio->alicuotaEfectiva($articulo, $this->sucursalId));
    }

    /**
     * RF-A3 (hardening-circuito-precios): el flag precio_iva_incluido quedó
     * deprecado (el precio es SIEMPRE final con IVA); un residuo en false no
     * cambia la alícuota efectiva.
     */
    public function test_alicuota_efectiva_ignora_flag_neto_deprecado(): void
    {
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['precio_iva_incluido' => false]);

        $this->assertEquals(21.0, $this->servicio->alicuotaEfectiva($articulo, $this->sucursalId));
    }

    public function test_alicuota_efectiva_sin_cuit_es_cero(): void
    {
        // La BD de test puede tener cuits residuales de otras suites (no están
        // en el DELETE selectivo): se desactivan durante el test y se restauran.
        $activosPrevios = DB::connection('pymes_tenant')->table('cuits')->where('activo', true)->pluck('id');
        DB::connection('pymes_tenant')->table('cuits')->whereIn('id', $activosPrevios)->update(['activo' => false]);

        try {
            $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

            $this->assertEquals(0.0, $this->servicio->alicuotaEfectiva($articulo, $this->sucursalId));
        } finally {
            DB::connection('pymes_tenant')->table('cuits')->whereIn('id', $activosPrevios)->update(['activo' => true]);
        }
    }

    // ==================== margenReal / precioSugerido ====================

    public function test_precio_sugerido_formula_canonica_ri(): void
    {
        // Criterio del spec: costo neto $100, utilidad 40 %, IVA 21 % ⇒ $169,40.
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['utilidad_porcentaje' => 40]);
        $this->fijarCostoUltimo($articulo->id, 100.0);

        $this->assertEquals(169.40, $this->servicio->precioSugerido($articulo, $this->sucursalId));
    }

    public function test_precio_sugerido_comercio_no_ri_sin_factor_iva(): void
    {
        // D21: para un monotributista el sugerido NO agrega IVA.
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_MONOTRIBUTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['utilidad_porcentaje' => 40]);
        $this->fijarCostoUltimo($articulo->id, 100.0);

        $this->assertEquals(140.0, $this->servicio->precioSugerido($articulo, $this->sucursalId));
    }

    public function test_precio_sugerido_con_redondeo(): void
    {
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['utilidad_porcentaje' => 40]);
        $this->fijarCostoUltimo($articulo->id, 100.0);

        $this->assertEquals(170.0, $this->servicio->precioSugerido($articulo, $this->sucursalId, null, 'decena'));
        $this->assertEquals(200.0, $this->servicio->precioSugerido($articulo, $this->sucursalId, null, 'centena'));
    }

    public function test_margen_real_reproduce_la_utilidad_pre_redondeo(): void
    {
        // Criterio del spec: el margen inverso reproduce la utilidad.
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', [
            'precio_base' => 169.40,
            'utilidad_porcentaje' => 40,
        ]);
        $this->fijarCostoUltimo($articulo->id, 100.0);

        $margen = $this->servicio->margenReal($articulo, $this->sucursalId);

        $this->assertEquals(100.0, $margen['costo_rector']);
        $this->assertEquals(140.0, $margen['neto_venta']);
        $this->assertEquals(40.0, $margen['margen_real']);
        $this->assertEquals(40.0, $margen['utilidad_objetivo']);
    }

    public function test_margen_real_sin_costo_es_null(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        $this->assertNull($this->servicio->margenReal($articulo, $this->sucursalId));
    }

    public function test_margen_usa_fallback_a_consolidado(): void
    {
        // Criterio RF-09: sin fila de la sucursal ⇒ usa la consolidada.
        $this->crearCuitParaSucursal(CondicionIva::RESPONSABLE_INSCRIPTO);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['precio_base' => 169.40, 'utilidad_porcentaje' => 40]);
        ArticuloCosto::create(['articulo_id' => $articulo->id, 'sucursal_id' => null, 'costo_ultimo' => 100.0]);

        $margen = $this->servicio->margenReal($articulo, $this->sucursalId);

        $this->assertNotNull($margen);
        $this->assertEquals(100.0, $margen['costo_rector']);
    }

    // ==================== Helpers ====================

    /**
     * Compra COMPLETADA con detalles ya resueltos (costo computable incluido)
     * — el pipeline de confirmación completo llega en Fase 4; acá se testea
     * el contrato de CostoService.
     */
    private function crearCompraConDetalles(array $items): Compra
    {
        $compra = Compra::create([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedor->id,
            'usuario_id' => 1,
            'numero_comprobante' => 'C-'.uniqid(),
            'fecha' => now()->toDateString(),
            'tipo_comprobante' => 'factura_a',
            'forma_pago' => 'efectivo',
            'estado' => 'completada',
        ]);

        foreach ($items as $item) {
            CompraDetalle::create([
                'compra_id' => $compra->id,
                'articulo_id' => $item['articulo']->id,
                'cantidad' => $item['cantidad'],
                'cantidad_comprada' => $item['cantidad'],
                'factor_conversion' => $item['factor'] ?? 1,
                'codigo_proveedor_usado' => $item['codigo_proveedor'] ?? null,
                'precio_unitario' => $item['costo'],
                'costo_unitario_computable' => $item['costo'],
                'subtotal' => $item['costo'] * $item['cantidad'],
            ]);
        }

        return $compra->fresh(['detalles']);
    }

    private function setStock(int $articuloId, int $sucursalId, float $cantidad): void
    {
        Stock::updateOrCreate(
            ['articulo_id' => $articuloId, 'sucursal_id' => $sucursalId],
            ['cantidad' => $cantidad],
        );
    }

    private function fijarCostoUltimo(int $articuloId, float $costo): void
    {
        ArticuloCosto::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $this->sucursalId,
            'costo_ultimo' => $costo,
        ]);
    }

    private function crearCuitParaSucursal(int $codigoCondicion): Cuit
    {
        // firstOrCreate: el catálogo de config_test puede no traer el código (CI).
        $condicion = CondicionIva::firstOrCreate(['codigo' => $codigoCondicion], ['nombre' => 'Condición '.$codigoCondicion]);

        $cuit = Cuit::create([
            'numero_cuit' => '20'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT).'3',
            'razon_social' => 'CUIT Test '.uniqid(),
            'condicion_iva_id' => $condicion->id,
            'activo' => true,
        ]);

        DB::connection('pymes_tenant')->table('cuit_sucursal')->insert([
            'cuit_id' => $cuit->id,
            'sucursal_id' => $this->sucursalId,
            'es_principal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cuitsCreados[] = $cuit->id;

        return $cuit;
    }
}
