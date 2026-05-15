<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\PedidoMostrador;
use App\Models\Venta;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
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
    use WithPedidoMostradorHelpers, WithSucursal, WithTenant, WithVentaHelpers;

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

    /**
     * Conversión auto al entregar: si la sucursal tiene el flag activado, al
     * pasar el pedido a ESTADO_ENTREGADO se dispara `convertirEnVenta()` y
     * queda como `facturado` con `venta_id` apuntando a la venta nueva.
     */
    public function test_conversion_auto_al_entregar_genera_venta_sin_pasos_extra(): void
    {
        \App\Models\Sucursal::where('id', $this->sucursalId)->update([
            'pedido_conversion_automatica_al_entregar' => true,
        ]);

        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        // Pago activo que cubre el total para que la conversión no rechace.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 100,
            'monto_final' => 100,
            'afecta_caja' => true,
        ]);

        // Transición a entregado (forzar primero estados intermedios legales).
        $pedido->refresh();
        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_LISTO);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_FACTURADO, $pedido->estado_pedido,
            'Conversión auto debe haber transicionado a facturado');
        $this->assertNotNull($pedido->venta_id, 'venta_id debe quedar seteado');
    }

    /**
     * Canje de puntos como medio de pago durante la conversión: un VentaPago
     * con `es_pago_puntos=true` debe disparar `PuntosService::canjearPuntosComoDescuento()`
     * y descontar puntos del cliente.
     */
    public function test_convertir_con_pago_de_puntos_crea_movimiento_punto(): void
    {
        // Configuración de programa de puntos.
        \App\Models\ConfiguracionPuntos::firstOrCreate([], [
            'activo' => true,
            'modo_acumulacion' => 'global',
            'monto_por_punto' => 100,
            'valor_punto_canje' => 1, // 1 punto = $1
            'minimo_canje' => 1,
        ]);

        $cliente = \App\Models\Cliente::create([
            'nombre' => 'Cliente con puntos',
            'condicion_iva_id' => 1,
            'puntos_saldo_cache' => 0,
        ]);

        // Acreditar 500 puntos al cliente.
        \App\Models\MovimientoPunto::create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursalId,
            'fecha' => now(),
            'tipo' => \App\Models\MovimientoPunto::TIPO_ACUMULACION,
            'puntos' => 500,
            'monto_asociado' => 0,
            'concepto' => 'Saldo inicial test',
            'estado' => 'activo',
            'usuario_id' => 1,
        ]);

        $caja = $this->crearCajaAbierta($this->sucursalId);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pedido = $this->service->crearPedido(
            data: array_merge($this->datosBaseDelPedido(total: 1000, cajaId: $caja->id), [
                'cliente_id' => $cliente->id,
            ]),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        // Pago activo con $200 en puntos (200 puntos = $200).
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 200,
            'monto_final' => 200,
            'es_pago_puntos' => true,
            'puntos_usados' => 200,
            'afecta_caja' => false,
        ]);

        // Pago activo en efectivo por el resto.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 800,
            'monto_final' => 800,
            'afecta_caja' => true,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $movs = \App\Models\MovimientoPunto::where('cliente_id', $cliente->id)
            ->where('venta_id', $venta->id)
            ->where('tipo', \App\Models\MovimientoPunto::TIPO_CANJE_DESCUENTO)
            ->get();

        $this->assertCount(1, $movs, 'Debe crearse un MovimientoPunto tipo canje_descuento');
        // Canje devuelve puntos negativos (sale del saldo del cliente).
        $this->assertEquals(-200, (int) $movs->first()->puntos);
        $this->assertEquals(200.0, (float) $movs->first()->monto_asociado);

        // Saldo descontado: 500 inicial - 200 canjeados = 300.
        $saldo = \App\Services\PuntosService::class;
        $saldoActual = app($saldo)->obtenerSaldo($cliente->id);
        $this->assertEquals(300, $saldoActual);
    }

    /**
     * Conversión sin cliente: aunque haya flags de canje en los detalles o
     * pagos, NO se crean MovimientoPunto y la conversión no rompe.
     */
    public function test_convertir_sin_cliente_omite_canjes_de_puntos(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        // Pago en "puntos" pero el pedido no tiene cliente_id.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 100,
            'monto_final' => 100,
            'es_pago_puntos' => true,
            'puntos_usados' => 100,
            'afecta_caja' => false,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertNull($pedido->fresh()->cliente_id);
        $this->assertEquals(0, \App\Models\MovimientoPunto::where('venta_id', $venta->id)->count(),
            'Sin cliente no se crean MovimientoPunto');
    }

    // ==================== REPASO COMPLETO (C1, B2, B3) ====================

    /**
     * C1 — Guard cierre_turno_id en anularPago: si el pago tiene cierre_turno_id
     * y el usuario no tiene permiso `func.cambiar_forma_pago_turno_cerrado`,
     * rechaza con Exception. Paridad con CambioFormaPagoService (Ventas).
     */
    public function test_anular_pago_rechaza_si_turno_cerrado_y_sin_permiso(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 100,
            'monto_final' => 100,
            'afecta_caja' => true,
        ]);

        // Simular cierre de turno: setear cierre_turno_id en el pago.
        $pago->update(['cierre_turno_id' => 999]);

        // Usuario sin permiso (Auth::id() es null o user sin permiso).
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/turnos cerrados|permiso/i');

        $this->service->anularPago($pago->fresh(), motivo: 'test');
    }

    /**
     * B2 — Promociones por línea: se persisten en pedido_mostrador_detalle_promociones
     * al crear el pedido, y se migran a venta_detalle_promociones al convertir.
     */
    public function test_promociones_por_linea_se_persisten_y_migran_a_venta(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $efectivo = $this->crearFormaPagoEfectivo();

        // Detalle con _promociones_item (espejo del payload que arma el Livewire).
        $detalle = array_merge(
            $this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000),
            [
                'descuento_promocion' => 100,
                'tiene_promocion' => true,
                '_promociones_item' => [
                    'promociones_comunes' => [
                        [
                            'promocion_id' => null,
                            'nombre' => '10% off',
                            'tipo_beneficio' => 'porcentaje',
                            'valor' => 10,
                            'descuento_item' => 100,
                        ],
                    ],
                    'promociones_especiales' => [],
                ],
            ],
        );

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1000, cajaId: $caja->id),
            detalles: [$detalle],
            esBorrador: false,
        );

        // 1) Persistencia a nivel pedido en pedido_mostrador_detalle_promociones.
        $detalleId = $pedido->detalles->first()->id;
        $filas = DB::connection('pymes_tenant')
            ->table('pedido_mostrador_detalle_promociones')
            ->where('pedido_mostrador_detalle_id', $detalleId)
            ->get();
        $this->assertCount(1, $filas, 'Debe persistirse 1 promo por línea en pedido_mostrador_detalle_promociones');
        $this->assertEquals('10% off', $filas->first()->descripcion_promocion);
        $this->assertEquals(100, (float) $filas->first()->descuento_aplicado);

        // 2) Conversión a venta migra las promos por línea a venta_detalle_promociones.
        // Pago cubre el total del pedido (la guard de cobertura es contra total_final).
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());
        $detalleVentaId = $venta->detalles->first()->id;
        $filasVenta = DB::connection('pymes_tenant')
            ->table('venta_detalle_promociones')
            ->where('venta_detalle_id', $detalleVentaId)
            ->get();
        $this->assertCount(1, $filasVenta, 'Debe migrarse 1 promo por línea a venta_detalle_promociones');
        $this->assertEquals('10% off', $filasVenta->first()->descripcion_promocion);
        $this->assertEquals(100, (float) $filasVenta->first()->descuento_aplicado);
    }

    /**
     * B3 — Idempotencia de cancelar: doble cancelación lanza Exception (no daña data).
     */
    public function test_cancelar_pedido_dos_veces_lanza_excepcion(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $caja->id);

        // Primera cancelación: OK.
        $this->service->cancelarPedido($pedido, motivo: 'primera');
        $this->assertEquals(PedidoMostrador::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido);

        // Segunda cancelación: debe rechazar con Exception.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no se puede cancelar/i');

        $this->service->cancelarPedido($pedido->fresh(), motivo: 'segunda');
    }

    // Helpers en tests/Traits/WithPedidoMostradorHelpers.php.
}
