<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\DeliverySalida;
use App\Models\DeliverySalidaPedido;
use App\Models\IntegracionPago;
use App\Models\MovimientoCaja;
use App\Models\PedidoDelivery;
use App\Models\Repartidor;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\Pedidos\RepartidorService;
use Exception;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Revisión integral 2026-07-08: consistencia de pedidos EN SALIDA frente a
 * acciones que no pasan por la vuelta (cancelar, convertir, entregar por API,
 * volver a listo) + caja de contexto para pedidos sin caja (tienda/API).
 * Sin estos guards la salida quedaba imposible de cerrar y el efectivo de la
 * calle terminaba contabilizado en caja.
 */
class PedidoDeliverySalidaConsistenciaTest extends TestCase
{
    use WithCaja, WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected RepartidorService $repartidorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->cajaId = $this->crearCajaAbierta($this->sucursalId, ['saldo_actual' => 50000])->id;
        session(['caja_id' => $this->cajaId]);
        $this->habilitarDelivery();
        $this->service = new PedidoDeliveryService;
        $this->repartidorService = new RepartidorService($this->service);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== HELPERS ====================

    private function crearRepartidorHabilitado(): Repartidor
    {
        $repartidor = Repartidor::create([
            'nombre' => 'Carlos Moto',
            'tipo' => 'propio',
            'activo' => true,
        ]);
        $repartidor->sucursales()->attach($this->sucursalId);

        return $repartidor;
    }

    /**
     * Pedido delivery despachado (en_camino) dentro de una salida real.
     */
    private function pedidoEnLaCalle(Repartidor $repartidor, float $total = 1000, ?int $cajaId = null): array
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: $total, cajaId: $cajaId ?? $this->cajaId);
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_LISTO);

        $salida = $this->repartidorService->despacharPedido($pedido->fresh());

        return [$pedido->fresh(), $salida->fresh()];
    }

    // ==================== A1: CANCELAR EN LA CALLE ====================

    public function test_cancelar_pedido_en_camino_lo_desvincula_y_la_vuelta_sigue_registrable(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        [$cancelado, $salida] = $this->pedidoEnLaCalle($repartidor);
        // Segundo pedido en el mismo viaje (se suma a la salida en curso).
        [$entregable, $salida] = $this->pedidoEnLaCalle($repartidor);

        $this->service->cancelarPedido($cancelado, 'Cliente se arrepintió');

        $cancelado->refresh();
        $this->assertNull($cancelado->salida_id, 'El cancelado debe soltar la salida');
        $this->assertSame(
            DeliverySalidaPedido::RESULTADO_NO_ENTREGADO,
            $salida->salidaPedidos()->where('pedido_id', $cancelado->id)->value('resultado'),
            'El intento queda como no_entregado en el pivot (append-only)',
        );

        // La vuelta se registra SOLO con el pedido que sigue pendiente.
        $this->repartidorService->registrarVuelta($salida->fresh(), [
            $entregable->id => ['resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO, 'cobros' => []],
        ], cajaConversionId: $this->cajaId);

        $this->assertSame(DeliverySalida::ESTADO_FINALIZADA, $salida->fresh()->estado);
        $this->assertSame(PedidoDelivery::ESTADO_ENTREGADO, $entregable->fresh()->estado_pedido);
    }

    // ==================== A2: CONVERTIR EN LA CALLE ====================

    public function test_convertir_en_venta_un_pedido_en_reparto_esta_bloqueado(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        [$pedido] = $this->pedidoEnLaCalle($repartidor);

        // Pago planificado que cubriría el total (cobro contra entrega).
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->crearFormaPagoEfectivo()['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('registrá la vuelta');

        $this->service->convertirEnVenta($pedido->fresh());
    }

    public function test_convertir_un_pedido_de_salida_armandose_lo_desvincula_y_convierte(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);

        // Salida ARMANDO (no registrada): el pedido nunca salió a la calle.
        $salida = $this->repartidorService->crearSalida(
            sucursalId: $this->sucursalId,
            repartidorId: $repartidor->id,
            pedidoIds: [$pedido->id],
        );

        $this->service->agregarPago($pedido->fresh(), [
            'forma_pago_id' => $this->crearFormaPagoEfectivo()['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $pedido->refresh();
        $this->assertNull($pedido->salida_id);
        $this->assertSame(0, $salida->salidaPedidos()->where('pedido_id', $pedido->id)->count(), 'El pivot de una salida armando se borra');
        $this->assertNotNull($venta->id);
    }

    // ==================== A3: ENTREGAR SIN VUELTA (API) ====================

    public function test_cambiar_estado_a_entregado_con_salida_en_camino_exige_la_vuelta(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        [$pedido] = $this->pedidoEnLaCalle($repartidor);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('registrá la vuelta');

        // Camino de la API (PATCH estado=entregado): cambiarEstado directo.
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_ENTREGADO);
    }

    // ==================== M1: VOLVER A LISTO DESDE LA CALLE ====================

    public function test_volver_a_listo_desde_la_calle_desvincula_y_permite_redespachar(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        [$pedido, $salida] = $this->pedidoEnLaCalle($repartidor);

        // Drag/modal: en_camino → listo sin pasar por la vuelta.
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO, 'Volvió sin entregar');

        $pedido->refresh();
        $this->assertNull($pedido->salida_id);
        $this->assertSame(
            DeliverySalidaPedido::RESULTADO_NO_ENTREGADO,
            $salida->salidaPedidos()->where('pedido_id', $pedido->id)->value('resultado'),
        );

        // Re-despacho con OTRO repartidor: antes fallaba con "ya está en otra salida".
        $otro = Repartidor::create(['nombre' => 'Ana Bici', 'tipo' => 'propio', 'activo' => true]);
        $otro->sucursales()->attach($this->sucursalId);
        $this->service->asignarRepartidor($pedido->fresh(), $otro->id);

        $nueva = $this->repartidorService->despacharPedido($pedido->fresh());

        $this->assertSame(DeliverySalida::ESTADO_EN_CAMINO, $nueva->estado);
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->fresh()->estado_pedido);
    }

    // ==================== A4: CAJA DE CONTEXTO ====================

    public function test_confirmar_pago_planificado_de_pedido_sin_caja_usa_la_caja_de_contexto(): void
    {
        // Pedido "de tienda": sin caja propia.
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: null);
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fp->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $pago = $this->service->confirmarPagoPlanificado($pago, [], ['caja_id' => $this->cajaId]);

        $this->assertSame($this->cajaId, (int) $pedido->fresh()->caja_id, 'El pedido adopta la caja de quien cobra');
        $this->assertNotNull($pago->movimiento_caja_id, 'El cobro genera MovimientoCaja');
        $this->assertSame(
            MovimientoCaja::TIPO_INGRESO,
            MovimientoCaja::find($pago->movimiento_caja_id)->tipo,
        );
    }

    public function test_agregar_pago_activo_que_afecta_caja_sin_ninguna_caja_es_rechazado(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No hay caja para registrar el cobro');

        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->crearFormaPagoEfectivo()['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);
    }

    // ==================== M3: ASAP LIMPIA HORA PACTADA ====================

    public function test_editar_pedido_a_lo_antes_posible_limpia_la_hora_pactada_previa(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $pedido->update(['hora_pactada_at' => now()->addMinutes(45), 'lo_antes_posible' => false]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);
        $this->service->actualizarPedido($pedido->fresh(), $this->datosBaseDelivery(
            total: 1000,
            cajaId: $this->cajaId,
            overrides: ['lo_antes_posible' => true, 'hora_pactada_at' => null],
        ), [$this->detalleDeliveryDe($articulo, cantidad: 1, precioUnitario: 1000)]);

        $pedido->refresh();
        $this->assertTrue((bool) $pedido->lo_antes_posible);
        $this->assertNull($pedido->hora_pactada_at, 'ASAP y hora pactada son excluyentes');
    }

    // ==================== M4: FP INTEGRADA EN LA VUELTA ====================

    public function test_vuelta_con_pago_planificado_de_fp_integrada_es_rechazada(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        [$pedido, $salida] = $this->pedidoEnLaCalle($repartidor);

        $integracion = IntegracionPago::firstOrCreate(
            ['codigo' => 'mercadopago_qr'],
            [
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico'],
                'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
                'activo' => true,
                'orden' => 1,
            ],
        );
        // FP NO-efectivo (QR): el efectivo va al fondo y nunca llega al guard.
        $conceptoQr = \App\Models\ConceptoPago::firstOrCreate(
            ['codigo' => 'QR'],
            ['nombre' => 'QR', 'permite_cuotas' => false, 'permite_vuelto' => false, 'activo' => true, 'orden' => 9],
        );
        $fpQr = \App\Models\FormaPago::create([
            'nombre' => 'MP QR',
            'codigo' => 'mp-qr-'.uniqid(),
            'concepto' => 'transferencia',
            'concepto_pago_id' => $conceptoQr->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);
        $fpQr->integraciones()->attach($integracion->id, ['es_principal' => true]);

        $pago = $this->service->agregarPago($pedido->fresh(), [
            'forma_pago_id' => $fpQr->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('integración');

        $this->repartidorService->registrarVuelta($salida->fresh(), [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id]],
            ],
        ], cajaConversionId: $this->cajaId);
    }
}
