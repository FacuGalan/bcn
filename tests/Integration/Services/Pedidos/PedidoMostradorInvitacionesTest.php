<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\PedidoMostrador;
use App\Services\Pedidos\PedidoMostradorService;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Invitaciones (cortesías) — Fase 4: integración en NuevoPedidoMostrador.
 *
 * Verifica que `crearPedido()` y `actualizarPedido()` persistan las columnas
 * de invitación (cabecera + detalle) y que `recalcularEstadoPago()` trate los
 * pedidos sin saldo a cobrar (total_final<=0, todo invitado) como pagados.
 */
class PedidoMostradorInvitacionesTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_pedido_con_item_invitado_persiste_columnas_de_detalle(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $detalleInvitado = array_merge(
            $this->detalleDe($articulo, cantidad: 2, precioUnitario: 0),
            [
                'es_invitacion' => true,
                'invitacion_motivo' => 'Cliente VIP',
                'invitado_por_usuario_id' => 1,
                'invitado_at' => now(),
                'monto_invitado' => 1000.00, // 2 * 500 (precio original)
                'precio_unitario_original' => 500.00,
                'subtotal' => 0,
                'total' => 0,
            ],
        );

        $pedido = $this->service->crearPedido(
            data: array_merge(
                $this->datosBaseDelPedido(total: 0),
                [
                    'es_invitacion_total' => true,
                    'invitacion_motivo' => 'Cliente VIP',
                    'invitado_por_usuario_id' => 1,
                    'invitado_at' => now(),
                    'total_invitado' => 1000.00,
                ],
            ),
            detalles: [$detalleInvitado],
            esBorrador: false,
        );

        $detalle = $pedido->fresh('detalles')->detalles->first();

        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertSame('Cliente VIP', $detalle->invitacion_motivo);
        $this->assertSame(1, (int) $detalle->invitado_por_usuario_id);
        $this->assertNotNull($detalle->invitado_at);
        $this->assertEqualsWithDelta(1000.00, (float) $detalle->monto_invitado, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $detalle->precio_unitario_original, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $detalle->precio_unitario, 0.01);
    }

    public function test_pedido_completamente_invitado_queda_pagado_sin_pagos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $pedido = $this->service->crearPedido(
            data: array_merge(
                $this->datosBaseDelPedido(total: 0),
                [
                    'es_invitacion_total' => true,
                    'invitacion_motivo' => 'Cortesía cumpleaños',
                    'invitado_por_usuario_id' => 1,
                    'invitado_at' => now(),
                    'total_invitado' => 800.00,
                ],
            ),
            detalles: [
                array_merge(
                    $this->detalleDe($articulo, cantidad: 2, precioUnitario: 0),
                    [
                        'es_invitacion' => true,
                        'invitacion_motivo' => 'Cortesía cumpleaños',
                        'invitado_por_usuario_id' => 1,
                        'invitado_at' => now(),
                        'monto_invitado' => 800.00,
                        'precio_unitario_original' => 400.00,
                        'subtotal' => 0,
                        'total' => 0,
                    ],
                ),
            ],
            esBorrador: false,
        );

        $pedido->refresh();

        $this->assertTrue((bool) $pedido->es_invitacion_total);
        $this->assertSame('Cortesía cumpleaños', $pedido->invitacion_motivo);
        $this->assertEqualsWithDelta(800.00, (float) $pedido->total_invitado, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $pedido->total_final, 0.01);
        $this->assertSame(
            PedidoMostrador::ESTADO_PAGO_PAGADO,
            $pedido->estado_pago,
            'Pedido totalmente invitado debe nacer con estado_pago=pagado'
        );
        $this->assertSame(0, $pedido->pagos()->count(), 'No debe haber pagos persistidos');
    }

    public function test_pedido_con_invitacion_parcial_solo_cobra_items_no_invitados(): void
    {
        $articuloA = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $articuloB = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        // Pedido: 1 item normal $500 + 1 item invitado (regalado, valor original $300).
        $pedido = $this->service->crearPedido(
            data: array_merge(
                $this->datosBaseDelPedido(total: 500),
                [
                    'es_invitacion_total' => false,
                    'total_invitado' => 300.00,
                ],
            ),
            detalles: [
                $this->detalleDe($articuloA, cantidad: 1, precioUnitario: 500),
                array_merge(
                    $this->detalleDe($articuloB, cantidad: 1, precioUnitario: 0),
                    [
                        'es_invitacion' => true,
                        'invitacion_motivo' => 'Item de cortesía',
                        'invitado_por_usuario_id' => 1,
                        'invitado_at' => now(),
                        'monto_invitado' => 300.00,
                        'precio_unitario_original' => 300.00,
                        'subtotal' => 0,
                        'total' => 0,
                    ],
                ),
            ],
            esBorrador: false,
        );

        $pedido->refresh();

        $this->assertFalse((bool) $pedido->es_invitacion_total);
        $this->assertEqualsWithDelta(300.00, (float) $pedido->total_invitado, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $pedido->total_final, 0.01,
            'total_final debe reflejar solo el item no invitado');
        $this->assertSame(
            PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            $pedido->estado_pago,
            'Hay saldo a cobrar ($500) — estado_pago debe ser pendiente'
        );

        $detalles = $pedido->detalles()->orderBy('id')->get();
        $this->assertFalse((bool) $detalles[0]->es_invitacion, 'Primer detalle no invitado');
        $this->assertTrue((bool) $detalles[1]->es_invitacion, 'Segundo detalle invitado');
        $this->assertEqualsWithDelta(300.00, (float) $detalles[1]->monto_invitado, 0.01);
    }

    public function test_actualizar_pedido_persiste_columnas_de_invitacion_en_cabecera(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        // Pedido borrador normal.
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 500),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 500)],
            esBorrador: true,
        );

        // Actualizar a invitación total.
        $this->service->actualizarPedido(
            $pedido,
            data: array_merge(
                $this->datosBaseDelPedido(total: 0),
                [
                    'es_invitacion_total' => true,
                    'invitacion_motivo' => 'Promo grand opening',
                    'invitado_por_usuario_id' => 1,
                    'invitado_at' => now(),
                    'total_invitado' => 500.00,
                    'total_final' => 0,
                ],
            ),
            detalles: [
                array_merge(
                    $this->detalleDe($articulo, cantidad: 1, precioUnitario: 0),
                    [
                        'es_invitacion' => true,
                        'invitacion_motivo' => 'Promo grand opening',
                        'invitado_por_usuario_id' => 1,
                        'invitado_at' => now(),
                        'monto_invitado' => 500.00,
                        'precio_unitario_original' => 500.00,
                        'subtotal' => 0,
                        'total' => 0,
                    ],
                ),
            ],
        );

        $pedido->refresh();

        $this->assertTrue((bool) $pedido->es_invitacion_total);
        $this->assertSame('Promo grand opening', $pedido->invitacion_motivo);
        $this->assertEqualsWithDelta(500.00, (float) $pedido->total_invitado, 0.01);

        $detalle = $pedido->detalles->first();
        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertEqualsWithDelta(500.00, (float) $detalle->monto_invitado, 0.01);

        // recalcularEstadoPago en actualizarPedido detecta total_final<=0 y
        // marca pagado aun sin pagos.
        $this->assertSame(
            PedidoMostrador::ESTADO_PAGO_PAGADO,
            $pedido->estado_pago,
            'Tras pasar a invitación total, el pedido debe quedar en estado_pago=pagado'
        );
    }
}
