<?php

namespace Tests\Integration\Services;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests de reproducibilidad de la venta. Garantiza que con sólo los datos
 * persistidos en `ventas` y `ventas_detalle` se puede reconstruir EXACTAMENTE
 * el cálculo que se mostró en pantalla al cobrar.
 *
 * Reglas verificadas:
 *   1. ventas_detalle.descuento_promocion_especial guarda la atribución por
 *      item del descuento por promos especiales (NxM/Combo/Menú).
 *   2. ventas_detalle.total = subtotal − descuento_promocion − descuento_promocion_especial − descuento_cupon.
 *   3. ventas.puntos_usados_monto guarda en pesos lo que canjeó el cliente como pago.
 *   4. INVARIANTE: sum(ventas_detalle.total) − descuento_general_monto (si monto_fijo) − puntos_usados_monto = ventas.total.
 */
class VentaReproducibilidadTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected VentaService $ventaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->ventaService = app(VentaService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Configura el modo de control de stock de la sucursal de test.
     */
    private function setControlStock(string $modo): void
    {
        DB::connection('pymes_tenant')->table('sucursales')
            ->where('id', $this->sucursalId)
            ->update(['control_stock_venta' => $modo]);
    }

    /**
     * Persistencia básica: el campo descuento_promocion_especial queda guardado
     * con el valor que viene en el detalle.
     */
    public function test_persiste_descuento_promocion_especial(): void
    {
        $this->setControlStock('no_controla');
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 1000,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1000,
            'total' => 700,           // 1000 − 300 (promo especial)
            'total_final' => 700,
        ]);
        $detalles = [$this->detalleVentaBase($articulo->id, [
            'precio_unitario' => 1000,
            'descuento_promocion_especial' => 300,
            'tiene_promocion' => true,
        ])];

        $venta = $this->ventaService->crearVenta($data, $detalles);
        $detalle = VentaDetalle::where('venta_id', $venta->id)->first();

        $this->assertEquals('300.00', $detalle->descuento_promocion_especial);
    }

    /**
     * Persistencia de puntos_usados_monto en cabecera.
     */
    public function test_persiste_puntos_usados_monto_en_venta(): void
    {
        $this->setControlStock('no_controla');
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 1000,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1000,
            'total' => 800,
            'total_final' => 800,
            'puntos_usados' => 200,             // 200 puntos canjeados
            'puntos_usados_monto' => 200.00,    // Equivalen a $200 al canjearse
        ]);
        $detalles = [$this->detalleVentaBase($articulo->id)];

        $venta = $this->ventaService->crearVenta($data, $detalles);

        $this->assertEquals(200, $venta->puntos_usados);
        $this->assertEquals('200.00', $venta->puntos_usados_monto);
    }

    /**
     * Cálculo correcto del total del item incluyendo TODOS los descuentos:
     *   total = subtotal − promo común − promo especial − cupón.
     */
    public function test_total_item_resta_todos_los_descuentos(): void
    {
        $this->setControlStock('no_controla');
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 1000,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1000,
            'total' => 400,
            'total_final' => 400,
        ]);
        // 1000 (subtotal) − 50 (promo común) − 300 (promo especial) − 250 (cupón) = 400
        $detalles = [$this->detalleVentaBase($articulo->id, [
            'precio_unitario' => 1000,
            'descuento_promocion' => 50,
            'descuento_promocion_especial' => 300,
            'descuento_cupon' => 250,
            'tiene_promocion' => true,
        ])];

        $venta = $this->ventaService->crearVenta($data, $detalles);
        $detalle = VentaDetalle::where('venta_id', $venta->id)->first();

        $this->assertEquals('1000.00', $detalle->subtotal);
        $this->assertEquals('50.00', $detalle->descuento_promocion);
        $this->assertEquals('300.00', $detalle->descuento_promocion_especial);
        $this->assertEquals('250.00', $detalle->descuento_cupon);
        $this->assertEquals(
            '400.00',
            $detalle->total,
            'Total del item debe restar TODOS los descuentos'
        );
    }

    /**
     * INVARIANTE de reproducibilidad:
     *   Σ ventas_detalle.total − descuento_general_monto (si es monto_fijo) − puntos_usados_monto
     *   debe ser EXACTAMENTE igual a ventas.total.
     *
     * Esto garantiza que con los datos persistidos en BD se puede reconstruir el cálculo
     * sin necesidad de volver a correr la lógica de promos.
     */
    public function test_invariante_suma_items_mas_ajustes_cabecera_igual_total_venta(): void
    {
        $this->setControlStock('no_controla');
        $art1 = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 500,
        ]);
        $art2 = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 1000,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        // Escenario: 2 items.
        //   item 1: 500 − 25 (promo común) − 0 (promo especial) − 0 (cupón) = 475
        //   item 2: 1000 − 0 − 200 (promo especial) − 100 (cupón) = 700
        //   Σ items.total = 475 + 700 = 1175
        //   ventas.descuento_general_monto = 100 (monto_fijo)
        //   ventas.puntos_usados_monto = 75
        //   ventas.total esperado = 1175 − 100 − 75 = 1000
        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1500,
            'total' => 1000,
            'total_final' => 1000,
            'descuento_general_tipo' => 'monto_fijo',
            'descuento_general_valor' => 100,
            'descuento_general_monto' => 100,
            'puntos_usados' => 75,
            'puntos_usados_monto' => 75.00,
        ]);
        $detalles = [
            $this->detalleVentaBase($art1->id, [
                'precio_unitario' => 500,
                'descuento_promocion' => 25,
                'tiene_promocion' => true,
            ]),
            $this->detalleVentaBase($art2->id, [
                'precio_unitario' => 1000,
                'descuento_promocion_especial' => 200,
                'descuento_cupon' => 100,
                'tiene_promocion' => true,
            ]),
        ];

        $venta = $this->ventaService->crearVenta($data, $detalles);

        // Reconstruir desde BD
        $sumaItems = (float) VentaDetalle::where('venta_id', $venta->id)->sum('total');
        $descGralMonto = (float) $venta->descuento_general_monto;
        $puntosMonto = (float) $venta->puntos_usados_monto;

        $totalReconstruido = $sumaItems - $descGralMonto - $puntosMonto;

        $this->assertEquals(1175.00, $sumaItems, 'Σ items.total = 475 + 700 = 1175');
        $this->assertEquals(
            (float) $venta->total,
            $totalReconstruido,
            "INVARIANTE roto: Σ items.total ($sumaItems) − desc_gral ($descGralMonto) − puntos_monto ($puntosMonto) = $totalReconstruido, pero ventas.total = {$venta->total}"
        );
    }

    /**
     * Caso exacto de la captura del usuario (venta #327 reconstruida con
     * los valores aproximados que vimos): hamburguesa con desc gral %, cupón,
     * ajuste manual, promo común. Verifica que toda la trazabilidad queda en BD.
     */
    public function test_caso_realista_hamburguesa_con_todos_los_descuentos(): void
    {
        $this->setControlStock('no_controla');
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        // Setup: hamburguesa $6875 → ajuste manual −20% → precio_unitario $5500
        // Cupón 50% sobre precio_unitario $5500 = $2750
        // Promo "5% OFF" → en items con cupón ya se excluye (PR #57), pero el cajero
        // aplica un ajuste manual extra: simulamos que decidió mantener una promo
        // común sobre el item por $100 manual ─ no es realista pero ejercita el cálculo.
        // Total esperado del item: 5500 − 100 (promo común) − 0 (promo especial) − 2750 (cupón) = 2650

        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 5500,
            'total' => 2650,
            'total_final' => 2650,
            'cupon_id' => null,
            'monto_cupon' => 2750,
        ]);
        $detalles = [$this->detalleVentaBase($hamburguesa->id, [
            'precio_unitario' => 5500,
            'precio_lista' => 6875,
            'descuento_promocion' => 100,
            'descuento_cupon' => 2750,
            'ajuste_manual_tipo' => 'porcentaje',
            'ajuste_manual_valor' => 20,
            'ajuste_manual_origen' => 'manual',
            'precio_sin_ajuste_manual' => 6875,
            'tiene_promocion' => true,
        ])];

        $venta = $this->ventaService->crearVenta($data, $detalles);
        $detalle = VentaDetalle::where('venta_id', $venta->id)->first();

        // Trazabilidad completa para reconstruir:
        $this->assertEquals('6875.00', $detalle->precio_lista, 'Precio lista original');
        $this->assertEquals('5500.00', $detalle->precio_unitario, 'Precio post ajuste manual');
        $this->assertEquals('20.00', $detalle->ajuste_manual_valor);
        $this->assertEquals('porcentaje', $detalle->ajuste_manual_tipo);
        $this->assertEquals('manual', $detalle->ajuste_manual_origen);
        $this->assertEquals('6875.00', $detalle->precio_sin_ajuste_manual);
        $this->assertEquals('100.00', $detalle->descuento_promocion);
        $this->assertEquals('0.00', $detalle->descuento_promocion_especial);
        $this->assertEquals('2750.00', $detalle->descuento_cupon);
        $this->assertEquals('5500.00', $detalle->subtotal);
        $this->assertEquals('2650.00', $detalle->total, '5500 − 100 − 0 − 2750 = 2650');

        // Reconstrucción: todos los valores que vimos en pantalla están persistidos.
        // Un reporte/auditoría puede recalcular sin ambigüedad.
    }

    /**
     * PR C — articulos_canjeados_monto en cabecera se persiste correctamente.
     */
    public function test_persiste_articulos_canjeados_monto_en_venta(): void
    {
        $this->setControlStock('no_controla');
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'precio_base' => 1000,
        ]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1000,
            'total' => 700,
            'total_final' => 700,
            'articulos_canjeados_monto' => 300.00,
        ]);
        $detalles = [$this->detalleVentaBase($articulo->id)];

        $venta = $this->ventaService->crearVenta($data, $detalles);

        $this->assertEquals('300.00', $venta->articulos_canjeados_monto);
    }

    /**
     * PR C — invariante completo: Σ items.total − todos_los_descuentos_cabecera = ventas.total.
     * Cubre canje monto + canje artículos + desc gral monto fijo + cupón al total + promos.
     */
    public function test_invariante_completo_con_todos_los_descuentos(): void
    {
        $this->setControlStock('no_controla');
        $art1 = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', ['precio_base' => 500]);
        $art2 = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', ['precio_base' => 1000]);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        // Escenario:
        //   item 1: 500 − 25 (promo común) = 475 (pagado con puntos)
        //   item 2: 1000 − 100 (promo especial) = 900
        //   Σ items.total = 475 + 900 = 1375
        //   ventas.descuento_general_monto = 50 (monto_fijo)
        //   ventas.puntos_usados_monto = 100 (canje monto adicional)
        //   ventas.articulos_canjeados_monto = 475 (item 1 pagado con puntos)
        //   ventas.monto_cupon = 50 (cupón al total — aplica_a='total')
        //   ventas.total esperado = 1375 − 50 − 100 − 475 − 50 = 700
        $data = $this->datosVentaBase([
            'caja_id' => $caja->id,
            '_usar_totales_proporcionados' => true,
            'subtotal' => 1500,
            'total' => 700,
            'total_final' => 700,
            'descuento_general_tipo' => 'monto_fijo',
            'descuento_general_valor' => 50,
            'descuento_general_monto' => 50,
            'puntos_usados' => 100,
            'puntos_usados_monto' => 100.00,
            'articulos_canjeados_monto' => 475.00,
            'monto_cupon' => 50,
        ]);
        $detalles = [
            $this->detalleVentaBase($art1->id, [
                'precio_unitario' => 500,
                'descuento_promocion' => 25,
                'tiene_promocion' => true,
                'pagado_con_puntos' => true,
                'puntos_usados' => 50,
            ]),
            $this->detalleVentaBase($art2->id, [
                'precio_unitario' => 1000,
                'descuento_promocion_especial' => 100,
                'tiene_promocion' => true,
            ]),
        ];

        $venta = $this->ventaService->crearVenta($data, $detalles);

        $sumaItems = (float) VentaDetalle::where('venta_id', $venta->id)->sum('total');
        $reconstruido = $sumaItems
            - (float) $venta->descuento_general_monto
            - (float) $venta->puntos_usados_monto
            - (float) $venta->articulos_canjeados_monto
            - (float) $venta->monto_cupon;

        $this->assertEquals(1375.00, $sumaItems, 'Σ items.total = 475 + 900 = 1375');
        $this->assertEquals(
            (float) $venta->total,
            $reconstruido,
            "Invariante completo roto: $sumaItems − desc_gral − puntos_monto − articulos_canj − cupon = $reconstruido, pero ventas.total = {$venta->total}"
        );
    }

    /**
     * PR C — la FP con solo_sistema=true no aparece en CatalogoCache::formasPago().
     * Crea una FP de prueba con solo_sistema=true y verifica el filtro.
     */
    public function test_formapago_solo_sistema_no_aparece_en_selector(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        // Crear concepto de prueba + FP con solo_sistema=true
        $conceptoId = DB::connection('pymes_tenant')->table('conceptos_pago')->insertGetId([
            'codigo' => 'test_solo_sistema_'.uniqid(),
            'nombre' => 'Test Solo Sistema',
            'permite_cuotas' => false,
            'permite_vuelto' => false,
            'activo' => true,
            'orden' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $codigoFp = 'TEST_SOLO_SIS_'.strtoupper(substr(uniqid(), -4));
        DB::connection('pymes_tenant')->table('formas_pago')->insert([
            'nombre' => 'FP Solo Sistema Test',
            'codigo' => $codigoFp,
            'concepto_pago_id' => $conceptoId,
            'concepto' => 'otro',
            'permite_cuotas' => false,
            'es_mixta' => false,
            'activo' => true,
            'solo_sistema' => true,
            'orden' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $formasPago = \App\Services\CatalogoCache::formasPago();
        $codigos = $formasPago->pluck('codigo')->toArray();

        $this->assertNotContains(
            $codigoFp,
            $codigos,
            'Una FP con solo_sistema=true NO debe aparecer en el selector'
        );

        // Verificación cruzada: una FP normal (Efectivo) sí aparece
        $this->assertContains('EFEC', $codigos, 'Efectivo (solo_sistema=false) sí debe aparecer');
    }
}
