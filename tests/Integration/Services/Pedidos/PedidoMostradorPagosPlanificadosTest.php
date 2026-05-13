<?php

namespace Tests\Integration\Services\Pedidos;

use App\Events\PedidoMostrador\PedidoEstadoPagoCambiado;
use App\Models\MovimientoCaja;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Services\Pedidos\PedidoMostradorService;
use Exception;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR2.B.1: tests de pagos planificados.
 *
 * Cubre:
 * - agregarPago(planificado=true) NO crea MovimientoCaja ni cambia estado_pago.
 * - confirmarPagoPlanificado materializa (crea MovimientoCaja, marca activo,
 *   recalcula estado_pago).
 * - eliminarPagoPlanificado borra directo, falla en activo.
 * - convertirEnVenta materializa planificados antes de migrar.
 * - cancelarPedido borra planificados directo (sin contraasiento).
 * - Accessor total_planificado / total_cobrado.
 */
class PedidoMostradorPagosPlanificadosTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_agregar_pago_planificado_no_crea_movimiento_caja_ni_cambia_estado_pago(): void
    {
        Event::fake([PedidoEstadoPagoCambiado::class]);

        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmado(total: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $saldoAntes = (float) $caja->fresh()->saldo_actual;

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        $this->assertEquals(PedidoMostradorPago::ESTADO_PLANIFICADO, $pago->estado);
        $this->assertNull($pago->movimiento_caja_id);
        $this->assertEquals($saldoAntes, (float) $caja->fresh()->saldo_actual, 'Saldo de caja no debe cambiar');
        $this->assertEquals(0, MovimientoCaja::where('referencia_id', $pedido->id)->count());

        $this->assertEquals(
            PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            $pedido->fresh()->estado_pago,
            'estado_pago no debe cambiar con planificados'
        );

        Event::assertNotDispatched(PedidoEstadoPagoCambiado::class);
    }

    public function test_confirmar_pago_planificado_lo_materializa(): void
    {
        Event::fake([PedidoEstadoPagoCambiado::class]);

        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmado(total: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        $saldoAntes = (float) $caja->fresh()->saldo_actual;

        $confirmado = $this->service->confirmarPagoPlanificado($pago, [
            'monto_recibido' => 1200,
            'vuelto' => 200,
        ]);

        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $confirmado->estado);
        $this->assertNotNull($confirmado->movimiento_caja_id);
        $this->assertEquals(1200.0, (float) $confirmado->monto_recibido);
        $this->assertEquals(200.0, (float) $confirmado->vuelto);

        $movCaja = MovimientoCaja::find($confirmado->movimiento_caja_id);
        $this->assertEquals(MovimientoCaja::REF_PEDIDO_MOSTRADOR, $movCaja->referencia_tipo);
        $this->assertEquals(1000.0, (float) $movCaja->monto);

        $this->assertEquals($saldoAntes + 1000.0, (float) $caja->fresh()->saldo_actual);
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->fresh()->estado_pago);

        Event::assertDispatched(PedidoEstadoPagoCambiado::class);
    }

    public function test_confirmar_pago_no_planificado_lanza_excepcion(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmado(total: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
            // planificado=false implícito
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/planificado/i');

        $this->service->confirmarPagoPlanificado($pago);
    }

    public function test_eliminar_pago_planificado_borra_directo(): void
    {
        $pedido = $this->pedidoConfirmado(total: 500);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'planificado' => true,
        ]);

        $pagoId = $pago->id;
        $this->service->eliminarPagoPlanificado($pago);

        $this->assertNull(PedidoMostradorPago::find($pagoId));
    }

    public function test_eliminar_pago_activo_lanza_excepcion(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmado(total: 500, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/planificado/i');

        $this->service->eliminarPagoPlanificado($pago);
    }

    public function test_convertir_en_venta_materializa_pagos_planificados(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1000, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        $efectivo = $this->crearFormaPagoEfectivo();
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        $saldoAntes = (float) $caja->fresh()->saldo_actual;

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        // El pago planificado se materializó: ahora hay MovimientoCaja por el monto.
        $this->assertEquals($saldoAntes + 1000.0, (float) $caja->fresh()->saldo_actual);

        $pago = PedidoMostradorPago::where('pedido_mostrador_id', $pedido->id)->first();
        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $pago->estado);
        $this->assertNotNull($pago->movimiento_caja_id);
        $this->assertNotNull($pago->venta_pago_id, 'Pago debe quedar migrado a VentaPago');

        // El MovimientoCaja se re-asoció a la venta (no quedó como pedido_mostrador).
        $movCaja = MovimientoCaja::find($pago->movimiento_caja_id);
        $this->assertEquals(MovimientoCaja::REF_VENTA, $movCaja->referencia_tipo);
        $this->assertEquals($venta->id, $movCaja->referencia_id);
    }

    public function test_cancelar_pedido_borra_planificados_sin_contraasiento(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 500, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 500)],
            esBorrador: false,
        );

        $efectivo = $this->crearFormaPagoEfectivo();
        $pagoPlanif = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        $movCajaCount = MovimientoCaja::where('referencia_id', $pedido->id)->count();

        $this->service->cancelarPedido($pedido, motivo: 'cambio de planes');

        // El pago planificado fue borrado, no anulado.
        $this->assertNull(PedidoMostradorPago::find($pagoPlanif->id));

        // No se generaron MovimientoCaja por el pago planificado (no había qué anular).
        $this->assertEquals(
            $movCajaCount,
            MovimientoCaja::where('referencia_id', $pedido->id)->count()
        );
    }

    public function test_accessors_total_planificado_y_total_cobrado(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmado(total: 1500, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        // 500 cobrado real
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
        ]);

        // 700 planificado
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 700,
            'monto_final' => 700,
            'planificado' => true,
        ]);

        $pedido = $pedido->fresh();
        $this->assertEquals(500.0, $pedido->total_cobrado);
        $this->assertEquals(700.0, $pedido->total_planificado);
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PARCIAL, $pedido->estado_pago, '500/1500 = parcial');
    }

    public function test_convertir_sin_pagos_lanza_excepcion_con_detalle_del_faltante(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1500, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1500)],
            esBorrador: false,
        );

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/1500|faltan/i');

        $this->service->convertirEnVenta($pedido->fresh());
    }

    public function test_convertir_con_planificados_que_cubren_el_total_funciona(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 800, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 800)],
            esBorrador: false,
        );

        $efectivo = $this->crearFormaPagoEfectivo();
        // 300 cobrado + 500 planificado = 800 total cubierto.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 300,
            'monto_final' => 300,
            'afecta_caja' => true,
        ]);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'planificado' => true,
        ]);

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertNotNull($venta->id);
        $this->assertEquals(800.0, (float) $venta->total_final);
    }

    public function test_pago_planificado_no_requiere_caja_id(): void
    {
        // Pedido sin caja (canal totem, app externa, etc): puede tener pagos
        // planificados que se materializan después cuando entra a una caja.
        $pedido = $this->pedidoConfirmado(total: 800, cajaId: null);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 800,
            'monto_final' => 800,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        $this->assertEquals(PedidoMostradorPago::ESTADO_PLANIFICADO, $pago->estado);
        $this->assertNull($pago->movimiento_caja_id);
    }

    // ==================== HELPERS ====================

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

    private function pedidoConfirmado(float $total = 1000, ?int $cajaId = null): PedidoMostrador
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);

        return $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: $total, cajaId: $cajaId),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: $total)],
            esBorrador: false,
        );
    }
}
