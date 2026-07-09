<?php

namespace Tests\Integration\Services\Pedidos;

use App\Events\PedidoMostrador\PedidoCancelado;
use App\Events\PedidoMostrador\PedidoConvertidoEnVenta;
use App\Events\PedidoMostrador\PedidoCreado;
use App\Events\PedidoMostrador\PedidoEstadoCambiado;
use App\Events\PedidoMostrador\PedidoEstadoPagoCambiado;
use App\Models\MovimientoCaja;
use App\Models\MovimientoStock;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\VentaPago;
use App\Services\Pedidos\PedidoMostradorService;
use Exception;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR2.B (Pedidos por Mostrador): tests del service que orquesta el ciclo de
 * vida del pedido (alta, transiciones, pagos, cancelación, conversión).
 *
 * Cubre los caminos críticos contractuales del service. Las features avanzadas
 * (promos, cupones, opcionales en convertirEnVenta) quedan para PR2.C cuando
 * se integre el modal UI.
 */
class PedidoMostradorServiceTest extends TestCase
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

    // ==================== ALTA ====================

    public function test_crear_pedido_borrador_no_asigna_numero_ni_descuenta_stock(): void
    {
        Event::fake([PedidoCreado::class]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1000),
            detalles: [$this->detalleDe($articulo, cantidad: 2, precioUnitario: 500)],
            esBorrador: true,
        );

        $this->assertEquals(PedidoMostrador::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertNull($pedido->numero);
        $this->assertCount(1, $pedido->detalles);

        $stock = Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(50.0, (float) $stock->cantidad, 'Borrador no debe descontar stock');

        $this->assertEquals(0, MovimientoStock::where('articulo_id', $articulo->id)->count());

        Event::assertNotDispatched(PedidoCreado::class);
    }

    public function test_crear_pedido_confirmado_asigna_numero_y_descuenta_stock(): void
    {
        Event::fake([PedidoCreado::class]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 2000),
            detalles: [$this->detalleDe($articulo, cantidad: 3, precioUnitario: 666.67)],
            esBorrador: false,
        );

        $this->assertEquals(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertEquals(1, $pedido->numero);
        $this->assertNotNull($pedido->confirmado_at);

        $stock = Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(47.0, (float) $stock->cantidad, 'Stock debe descontarse');

        $mov = MovimientoStock::where('articulo_id', $articulo->id)->first();
        $this->assertNotNull($mov);
        $this->assertEquals(MovimientoStock::TIPO_PEDIDO_MOSTRADOR, $mov->tipo);
        $this->assertEquals(MovimientoStock::DOC_PEDIDO_MOSTRADOR_DETALLE, $mov->documento_tipo);

        Event::assertDispatched(PedidoCreado::class);
    }

    // ==================== TRANSICIONES ====================

    public function test_cambiar_estado_valido_actualiza_estado_y_timestamp(): void
    {
        Event::fake([PedidoEstadoCambiado::class]);

        $pedido = $this->pedidoConfirmadoSimple();

        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_EN_PREPARACION);

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->estado_pedido);
        $this->assertNotNull($pedido->en_preparacion_at);

        Event::assertDispatched(PedidoEstadoCambiado::class);
    }

    public function test_cambiar_estado_invalido_lanza_excepcion(): void
    {
        $pedido = $this->pedidoConfirmadoSimple();
        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_ENTREGADO);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Transici/i');

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_BORRADOR);
    }

    // ==================== PAGOS ====================

    public function test_agregar_pago_crea_movimiento_caja_y_actualiza_estado_pago(): void
    {
        Event::fake([PedidoEstadoPagoCambiado::class]);

        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $saldoAntes = (float) $caja->fresh()->saldo_actual;

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 400,
            'monto_final' => 400,
            'afecta_caja' => true,
        ]);

        $this->assertNotNull($pago->movimiento_caja_id);
        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $pago->estado);

        $movCaja = MovimientoCaja::find($pago->movimiento_caja_id);
        $this->assertEquals(MovimientoCaja::REF_PEDIDO_MOSTRADOR, $movCaja->referencia_tipo);
        $this->assertEquals($pedido->id, $movCaja->referencia_id);
        $this->assertEquals(400.0, (float) $movCaja->monto);

        $this->assertEquals($saldoAntes + 400.0, (float) $caja->fresh()->saldo_actual);

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PARCIAL, $pedido->estado_pago);

        Event::assertDispatched(PedidoEstadoPagoCambiado::class);
    }

    public function test_agregar_pago_completo_marca_estado_pagado(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
        ]);

        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->fresh()->estado_pago);
    }

    public function test_anular_pago_genera_contraasiento_y_recalcula_estado_pago(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $caja->id);
        $efectivo = $this->crearFormaPagoEfectivo();

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
        ]);

        $saldoConPago = (float) $caja->fresh()->saldo_actual;

        $this->service->anularPago($pago, motivo: 'error de tipeo');

        $pago->refresh();
        $this->assertEquals(PedidoMostradorPago::ESTADO_ANULADO, $pago->estado);
        $this->assertNotNull($pago->anulado_at);

        $original = MovimientoCaja::find($pago->movimiento_caja_id);
        $this->assertNotNull($original->anulado_por_movimiento_id);

        $contraasiento = MovimientoCaja::find($original->anulado_por_movimiento_id);
        $this->assertEquals(MovimientoCaja::REF_ANULACION_PEDIDO_MOSTRADOR, $contraasiento->referencia_tipo);
        $this->assertEquals(MovimientoCaja::TIPO_EGRESO, $contraasiento->tipo);

        $this->assertEquals($saldoConPago - 1000.0, (float) $caja->fresh()->saldo_actual);

        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PENDIENTE, $pedido->fresh()->estado_pago);
    }

    // ==================== CANCELACION ====================

    public function test_cancelar_pedido_anula_pagos_y_revierte_stock(): void
    {
        Event::fake([PedidoCancelado::class]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 500, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 2, precioUnitario: 250)],
            esBorrador: false,
        );

        $efectivo = $this->crearFormaPagoEfectivo();
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'afecta_caja' => true,
        ]);

        $this->service->cancelarPedido($pedido, motivo: 'cliente no apareció');

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_CANCELADO, $pedido->estado_pedido);
        $this->assertEquals('cliente no apareció', $pedido->motivo_cancelacion);
        $this->assertNotNull($pedido->cancelado_at);

        $stock = Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertEquals(10.0, (float) $stock->cantidad, 'Stock revierte al valor previo');

        $pagosActivos = PedidoMostradorPago::where('pedido_mostrador_id', $pedido->id)
            ->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)
            ->count();
        $this->assertEquals(0, $pagosActivos);

        Event::assertDispatched(PedidoCancelado::class);
    }

    // ==================== CONVERSION A VENTA ====================

    public function test_convertir_en_venta_no_redescuenta_stock_y_reasocia_movimientos(): void
    {
        Event::fake([PedidoConvertidoEnVenta::class]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 1000, cajaId: $caja->id),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 1000)],
            esBorrador: false,
        );

        $efectivo = $this->crearFormaPagoEfectivo();
        $pagoPedido = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
        ]);

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        $stockAntes = (float) Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->value('cantidad');

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertNotNull($venta->id);
        $this->assertEquals(1000.0, (float) $venta->total_final);

        $stockDespues = (float) Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->value('cantidad');
        $this->assertEquals($stockAntes, $stockDespues, 'Convertir no debe re-descontar stock');

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_FACTURADO, $pedido->estado_pedido);
        $this->assertEquals($venta->id, $pedido->venta_id);
        $this->assertNotNull($pedido->convertido_at);

        $movStock = MovimientoStock::where('articulo_id', $articulo->id)->first();
        $this->assertEquals(MovimientoStock::TIPO_VENTA, $movStock->tipo, 'Movimientos de stock se re-asocian a venta');
        $this->assertEquals($venta->id, $movStock->venta_id);

        $movCaja = MovimientoCaja::find($pagoPedido->fresh()->movimiento_caja_id);
        $this->assertEquals(MovimientoCaja::REF_VENTA, $movCaja->referencia_tipo);
        $this->assertEquals($venta->id, $movCaja->referencia_id);

        $ventaPago = VentaPago::where('venta_id', $venta->id)->first();
        $this->assertNotNull($ventaPago, 'Debe existir un VentaPago migrado desde el pedido');
        $this->assertEquals(1000.0, (float) $ventaPago->monto_final);
        $this->assertEquals($ventaPago->id, $pagoPedido->fresh()->venta_pago_id);

        Event::assertDispatched(PedidoConvertidoEnVenta::class);

        // D20 (spec pedidos-delivery): la venta persiste su origen polimórfico
        // también en mostrador (morphMap 'PedidoMostrador').
        $this->assertEquals('PedidoMostrador', $venta->fresh()->origen_type);
        $this->assertEquals($pedido->id, (int) $venta->fresh()->origen_id);
    }

    public function test_convertir_pedido_borrador_lanza_excepcion(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 100),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: 100)],
            esBorrador: true,
        );

        $this->expectException(Exception::class);
        $this->service->convertirEnVenta($pedido);
    }

    // ==================== NUMERACION ====================

    public function test_siguiente_numero_es_atomico_y_secuencial(): void
    {
        $n1 = $this->service->siguienteNumero($this->sucursalId);
        $n2 = $this->service->siguienteNumero($this->sucursalId);
        $n3 = $this->service->siguienteNumero($this->sucursalId);

        $this->assertEquals(1, $n1);
        $this->assertEquals(2, $n2);
        $this->assertEquals(3, $n3);

        $sucursal = Sucursal::find($this->sucursalId);
        $this->assertEquals(3, (int) $sucursal->pedido_mostrador_ultimo_numero);
    }

    public function test_resetear_numeracion_pone_contador_en_cero(): void
    {
        $this->service->siguienteNumero($this->sucursalId);
        $this->service->siguienteNumero($this->sucursalId);

        $this->service->resetearNumeracion($this->sucursalId, usuarioId: 1);

        $sucursal = Sucursal::find($this->sucursalId);
        $this->assertEquals(0, (int) $sucursal->pedido_mostrador_ultimo_numero);

        $this->assertEquals(1, $this->service->siguienteNumero($this->sucursalId));
    }

    // Helpers (datosBaseDelPedido, detalleDe, pedidoConfirmadoSimple) en
    // tests/Traits/WithPedidoMostradorHelpers.php.
}
