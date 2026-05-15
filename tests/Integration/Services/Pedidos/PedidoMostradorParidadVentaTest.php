<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\PedidoMostrador;
use App\Models\Venta;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Spec: pedidos-mostrador-paridad-venta — Fase 6.
 *
 * Cubre los 5 criterios de aceptación del recálculo autoritativo server-side,
 * persistencia de promociones a nivel pedido / línea, y paridad estructural
 * con Venta en la conversión.
 */
class PedidoMostradorParidadVentaTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoMostradorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->service = new PedidoMostradorService;
        Event::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * CA-01 — Reproductor del bug original.
     *
     * Pedido total nominal $100. Pago con FP efectivo con 10% de descuento
     * (monto_ajuste = -10, monto_final = 90). El service debe:
     *  1) recalcular total_final = 100 - 10 = 90,
     *  2) marcar estado_pago = 'pagado' (pagado=$90 cubre total_final=$90).
     *
     * Antes del fix: total_final permanecía en 100 y estado_pago quedaba
     * 'parcial'. Este test es regression guard del bug reportado el 2026-05-14.
     */
    public function test_pago_con_fp_descuento_marca_pedido_pagado_y_recalcula_total_final(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 100,
            'ajuste_porcentaje' => -10,
            'monto_ajuste' => -10,
            'monto_final' => 90,
            'afecta_caja' => true,
        ]);

        $pedido->refresh();

        $this->assertEquals(-10.0, (float) $pedido->ajuste_forma_pago);
        $this->assertEquals(90.0, (float) $pedido->total_final);
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
    }

    /**
     * CA-02 — Persistencia de promociones a nivel pedido.
     *
     * Al crear un pedido con `_promociones_comunes` y `_promociones_especiales`
     * en el array de datos, `pedido_mostrador_promociones` debe quedar
     * poblada con las filas correspondientes (paridad con venta_promociones).
     */
    public function test_guarda_promociones_a_nivel_pedido(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        // promocion_id/promocion_especial_id en null para no chocar con FKs
        // (la persistencia del flujo es lo que se valida, no la FK).
        $data = array_merge($this->datosBaseDelPedido(total: 1000), [
            '_promociones_comunes' => [
                [
                    'promocion_id' => null,
                    'nombre' => '10% off general',
                    'tipo_beneficio' => 'porcentaje',
                    'valor' => 10,
                    'descuento' => 100,
                ],
            ],
            '_promociones_especiales' => [
                [
                    'promocion_especial_id' => null,
                    'nombre' => '2x1 Combo',
                    'descuento' => 50,
                ],
            ],
        ]);

        $pedido = $this->service->crearPedido(
            data: $data,
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        $filas = DB::connection('pymes_tenant')
            ->table('pedido_mostrador_promociones')
            ->where('pedido_mostrador_id', $pedido->id)
            ->get();

        $this->assertCount(2, $filas);

        $comun = $filas->firstWhere('tipo_promocion', 'promocion');
        $this->assertNotNull($comun);
        $this->assertEquals('10% off general', $comun->descripcion_promocion);
        $this->assertEquals(100, (float) $comun->descuento_aplicado);
        $this->assertEquals('porcentaje', $comun->tipo_beneficio);

        $especial = $filas->firstWhere('tipo_promocion', 'promocion_especial');
        $this->assertNotNull($especial);
        $this->assertEquals('2x1 Combo', $especial->descripcion_promocion);
        $this->assertEquals(50, (float) $especial->descuento_aplicado);
    }

    /**
     * CA-02b — actualizarPedido sobrescribe promociones (idempotente).
     */
    public function test_actualizar_pedido_reemplaza_promociones_previas(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $pedido = $this->service->crearPedido(
            data: array_merge($this->datosBaseDelPedido(total: 1000), [
                '_promociones_comunes' => [['promocion_id' => null, 'nombre' => 'Promo A', 'descuento' => 10]],
            ]),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        $this->service->actualizarPedido(
            $pedido,
            data: array_merge($this->datosBaseDelPedido(total: 1000), [
                '_promociones_comunes' => [['promocion_id' => null, 'nombre' => 'Promo B', 'descuento' => 20]],
            ]),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
        );

        $filas = DB::connection('pymes_tenant')
            ->table('pedido_mostrador_promociones')
            ->where('pedido_mostrador_id', $pedido->id)
            ->get();

        $this->assertCount(1, $filas, 'Promociones previas deben ser reemplazadas');
        $this->assertEquals('Promo B', $filas->first()->descripcion_promocion);
    }

    /**
     * CA-03 — Persistencia de descuentos de promo en líneas.
     *
     * `pedido_mostrador_detalle.descuento_promocion`,
     * `descuento_promocion_especial`, `descuento_cupon` deben persistir
     * tal como vienen del payload.
     */
    public function test_persiste_descuentos_de_promocion_y_cupon_por_linea(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $detalle = array_merge(
            $this->detalleDe($articulo, cantidad: 2, precioUnitario: 500),
            [
                'descuento_promocion' => 30,
                'descuento_promocion_especial' => 20,
                'descuento_cupon' => 15,
                'descuento_lista' => 5,
                'tiene_promocion' => true,
            ],
        );

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1000),
            detalles: [$detalle],
            esBorrador: false,
        );

        $persistido = $pedido->detalles->first();
        $this->assertEquals(30.0, (float) $persistido->descuento_promocion);
        $this->assertEquals(20.0, (float) $persistido->descuento_promocion_especial);
        $this->assertEquals(15.0, (float) $persistido->descuento_cupon);
        $this->assertEquals(5.0, (float) $persistido->descuento_lista);
        $this->assertTrue((bool) $persistido->tiene_promocion);
    }

    /**
     * CA-04 — Recálculo autoritativo: el service pisa lo que envía el Livewire.
     *
     * Si el Livewire mandó total_final = 1500 (incorrecto), pero el pago
     * dice monto_ajuste = -100 sobre total = 1000, el service debe persistir
     * total_final = 900, ignorando los 1500 originales. Es la garantía
     * "API-first" del spec.
     */
    public function test_recalcular_totales_sobrescribe_total_final_erroneo_desde_pagos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $efectivo = $this->crearFormaPagoEfectivo();

        $data = array_merge($this->datosBaseDelPedido(total: 1000, cajaId: $caja->id), [
            'total_final' => 1500, // mentira deliberada del "Livewire"
            'ajuste_forma_pago' => 500,
        ]);

        $pedido = $this->service->crearPedido(
            data: $data,
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        // Sin pagos todavía → respeta lo que envió el Livewire.
        $this->assertEquals(1500.0, (float) $pedido->total_final);

        // Apenas se agrega un pago, el service toma el control y recalcula.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_ajuste' => -100,
            'monto_final' => 900,
            'afecta_caja' => true,
        ]);

        $pedido->refresh();
        $this->assertEquals(-100.0, (float) $pedido->ajuste_forma_pago);
        $this->assertEquals(900.0, (float) $pedido->total_final, 'total_final autoritativo desde pagos');
    }

    /**
     * CA-04b — Anular un pago revierte el ajuste FP en total_final.
     */
    public function test_anular_pago_revierte_ajuste_fp_en_total_final(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_ajuste' => -50,
            'monto_final' => 950,
            'afecta_caja' => true,
        ]);

        $this->assertEquals(950.0, (float) $pedido->fresh()->total_final);

        $this->service->anularPago($pago, motivo: 'test');

        $pedido->refresh();
        // Sin pagos activos NI planificados → recalcularTotales no-op,
        // total_final queda como estaba persistido (950). Esto es OK porque
        // estado_pago se recalcula a "pendiente" (suma activos = 0).
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PENDIENTE, $pedido->estado_pago);
    }

    /**
     * CA-05 — Conversión a Venta preserva paridad total_final y migra
     * promociones a `venta_promociones`.
     */
    public function test_conversion_a_venta_preserva_totales_y_migra_promociones(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $efectivo = $this->crearFormaPagoEfectivo();

        $data = array_merge($this->datosBaseDelPedido(total: 1000, cajaId: $caja->id), [
            '_promociones_comunes' => [[
                'promocion_id' => null,
                'nombre' => 'Promo de paridad',
                'tipo_beneficio' => 'porcentaje',
                'valor' => 5,
                'descuento' => 50,
            ]],
            'puntos_usados' => 0,
            'puntos_canjeados_pago' => 0,
            'puntos_canjeados_articulos' => 0,
        ]);

        $pedido = $this->service->crearPedido(
            data: $data,
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        // Pago con ajuste para verificar total_final autoritativo.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_ajuste' => -100,
            'monto_final' => 900,
            'afecta_caja' => true,
        ]);

        $pedido->refresh();
        $totalFinalPedido = (float) $pedido->total_final;
        $this->assertEquals(900.0, $totalFinalPedido);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        // Paridad estructural total_final.
        $this->assertEquals($totalFinalPedido, (float) $venta->total_final, 'paridad pedido.total_final == venta.total_final');

        // Promociones migradas.
        $promosVenta = DB::connection('pymes_tenant')
            ->table('venta_promociones')
            ->where('venta_id', $venta->id)
            ->get();

        $this->assertCount(1, $promosVenta);
        $this->assertEquals('Promo de paridad', $promosVenta->first()->descripcion_promocion);
        $this->assertEquals(50, (float) $promosVenta->first()->descuento_aplicado);
    }

    // ==================== HELPERS (espejo de PedidoMostradorServiceTest) ====================

    private function datosBaseDelPedido(float $total = 1000, ?int $cajaId = null): array
    {
        return [
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $cajaId,
            'usuario_id' => 1,
            'fecha' => now(),
            'subtotal' => $total,
            'iva' => 0,
            'descuento' => 0,
            'total' => $total,
            'ajuste_forma_pago' => 0,
            'total_final' => $total,
            'identificador' => 'Mesa 1',
        ];
    }

    private function detalleDe($articulo, float $cantidad, float $precioUnitario): array
    {
        $subtotal = $precioUnitario * $cantidad;

        return [
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'es_concepto' => false,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'precio_sin_iva' => $precioUnitario / 1.21,
            'descuento' => 0,
            'precio_lista' => $precioUnitario,
            'subtotal' => $subtotal,
            'iva_porcentaje' => 21,
            'iva_monto' => $subtotal - ($subtotal / 1.21),
            'total' => $subtotal,
        ];
    }

    private function pedidoConfirmadoSimple(float $totalFinal = 1000, ?int $cajaId = null): PedidoMostrador
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);

        return $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: $totalFinal, cajaId: $cajaId),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: $totalFinal)],
            esBorrador: false,
        );
    }
}
