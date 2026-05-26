<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\PedidoMostrador;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Spec: comanda-por-detalle-pedido-mostrador — Fase 2.
 *
 * Cubre CA-01 a CA-09: comportamiento del service `comandarPedido()`,
 * accessor derivado `estado_comanda`, transiciones legales y bypass.
 */
class PedidoMostradorComandaTest extends TestCase
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
     * Crea un pedido confirmado con N detalles distintos para tests que
     * necesitan probar el alcance "nuevos vs todos".
     */
    protected function pedidoConfirmadoConVariosDetalles(int $cantidadDetalles = 2): PedidoMostrador
    {
        $detalles = [];
        for ($i = 0; $i < $cantidadDetalles; $i++) {
            $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);
            $detalles[] = $this->detalleDe($articulo, cantidad: 1, precioUnitario: 500);
        }

        return $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 500 * $cantidadDetalles),
            detalles: $detalles,
            esBorrador: false,
        );
    }

    /** CA-01 */
    public function test_comandar_todos_desde_confirmado_marca_detalles_y_va_a_en_preparacion(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);

        $payload = $this->service->comandarPedido($pedido, PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        $pedido->refresh()->load('detalles');
        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->estado_pedido);
        $this->assertCount(2, $pedido->detalles);
        foreach ($pedido->detalles as $d) {
            $this->assertNotNull($d->comandado_at, 'Cada detalle debe quedar con timestamp');
        }
        $this->assertSame('comanda', $payload['tipo_documento']);
        $this->assertSame($pedido->id, $payload['pedido_id']);
    }

    /** CA-02 */
    public function test_comandar_todos_desde_listo_regresa_a_en_preparacion(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);
        // Avanzar manualmente CONFIRMADO -> EN_PREPARACION -> LISTO (transiciones legales).
        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_EN_PREPARACION);
        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_LISTO);

        $this->service->comandarPedido($pedido->fresh(), PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->fresh()->estado_pedido);
    }

    /** CA-03 */
    public function test_comandar_todos_desde_entregado_regresa_a_en_preparacion(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);
        // CONFIRMADO -> ENTREGADO es transición legal directa.
        $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_ENTREGADO);

        $this->service->comandarPedido($pedido->fresh(), PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->fresh()->estado_pedido);
    }

    /** CA-04 */
    public function test_comandar_nuevos_solo_marca_los_no_comandados(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(3);
        $detalles = $pedido->detalles;

        // Marco los primeros 2 como comandados manualmente, con timestamp viejo.
        $hace1Hora = now()->subHour();
        DB::connection('pymes_tenant')->table('pedidos_mostrador_detalle')
            ->whereIn('id', [$detalles[0]->id, $detalles[1]->id])
            ->update(['comandado_at' => $hace1Hora]);

        $this->service->comandarPedido($pedido->fresh(), PedidoMostradorService::ALCANCE_COMANDA_NUEVOS);

        $pedido->fresh()->load('detalles')->detalles->each(function ($d) use ($detalles, $hace1Hora) {
            if (in_array($d->id, [$detalles[0]->id, $detalles[1]->id], true)) {
                // Los previamente comandados conservan su timestamp original.
                $this->assertEqualsWithDelta($hace1Hora->timestamp, $d->comandado_at->timestamp, 1.0);
            } else {
                // El nuevo recibió timestamp fresco (within last minute).
                $this->assertNotNull($d->comandado_at);
                $this->assertGreaterThan(now()->subMinute()->timestamp, $d->comandado_at->timestamp);
            }
        });
    }

    /** CA-05 — D5 cerrado: reimpresión total refresca timestamps */
    public function test_comandar_todos_en_pedido_ya_100pc_comandado_actualiza_timestamps(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);
        // Comandar todos una vez.
        $this->service->comandarPedido($pedido, PedidoMostradorService::ALCANCE_COMANDA_TODOS);
        $original = $pedido->fresh()->load('detalles')->detalles->pluck('comandado_at', 'id');

        // Avanzar tiempo simulado: forzar timestamp viejo en BD.
        $hace1Hora = now()->subHour();
        DB::connection('pymes_tenant')->table('pedidos_mostrador_detalle')
            ->where('pedido_mostrador_id', $pedido->id)
            ->update(['comandado_at' => $hace1Hora]);

        // Re-comandar todos.
        $this->service->comandarPedido($pedido->fresh(), PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        $pedido->fresh()->load('detalles')->detalles->each(function ($d) use ($hace1Hora) {
            $this->assertGreaterThan(
                $hace1Hora->timestamp,
                $d->comandado_at->timestamp,
                'El timestamp debe haberse actualizado a now()'
            );
        });
    }

    /** CA-06 */
    public function test_confirmar_con_imprime_comanda_automatico_marca_y_avanza(): void
    {
        // Opt-in al modo productivo (WithSucursal lo deja en false por default).
        DB::connection('pymes_tenant')->table('sucursales')
            ->where('id', $this->sucursalId)
            ->update(['imprime_comanda_automatico' => true]);

        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);

        $pedido->refresh()->load('detalles');
        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->estado_pedido);
        foreach ($pedido->detalles as $d) {
            $this->assertNotNull($d->comandado_at);
        }
    }

    /** CA-07 */
    public function test_confirmar_sin_imprime_comanda_automatico_no_marca_ni_avanza(): void
    {
        // WithSucursal ya lo dejó en false. Confirmar el pedido.
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);

        $pedido->refresh()->load('detalles');
        $this->assertSame(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        foreach ($pedido->detalles as $d) {
            $this->assertNull($d->comandado_at, 'Sin auto-comanda, los detalles quedan sin timestamp');
        }
    }

    /** CA-08 */
    public function test_cancelar_preserva_comandado_at(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);
        $this->service->comandarPedido($pedido, PedidoMostradorService::ALCANCE_COMANDA_TODOS);
        $timestamps = $pedido->fresh()->load('detalles')->detalles->pluck('comandado_at', 'id');

        $this->service->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_CANCELADO);

        $pedido->fresh()->load('detalles')->detalles->each(function ($d) use ($timestamps) {
            $this->assertNotNull($d->comandado_at, 'Cancelar no debe limpiar comandado_at');
            $this->assertEqualsWithDelta(
                $timestamps[$d->id]->timestamp,
                $d->comandado_at->timestamp,
                1.0,
                'El timestamp original se preserva tras cancelar'
            );
        });
    }

    /** CA-09 */
    public function test_actualizar_pedido_agregando_detalle_lo_crea_con_comandado_at_null(): void
    {
        // Pedido con 1 detalle, confirmado.
        $articuloOriginal = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: 500),
            detalles: [$this->detalleDe($articuloOriginal, cantidad: 1, precioUnitario: 500)],
            esBorrador: false,
        );

        // Comandar lo existente: el detalle queda con comandado_at != null.
        $this->service->comandarPedido($pedido, PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        // Agregar un item nuevo via actualizarPedido.
        $articuloNuevo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);

        $pedidoActualizado = $this->service->actualizarPedido(
            $pedido->fresh(),
            data: $this->datosBaseDelPedido(total: 1000),
            detalles: [
                $this->detalleDe($articuloOriginal, cantidad: 1, precioUnitario: 500),
                $this->detalleDe($articuloNuevo, cantidad: 1, precioUnitario: 500),
            ],
        );

        $pedidoActualizado->load('detalles');
        $this->assertCount(2, $pedidoActualizado->detalles);

        // `actualizarPedido` borra y recrea detalles. Ambos quedan con
        // `comandado_at=null` (default). El próximo `comandarPedido('nuevos')`
        // los procesaría a los dos como nuevos. Esto es el comportamiento
        // esperado: la edición desmarca, hay que volver a mandar a cocina.
        foreach ($pedidoActualizado->detalles as $d) {
            $this->assertNull($d->comandado_at);
        }
    }

    public function test_accessor_estado_comanda_refleja_los_detalles(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(2);
        $this->assertSame(PedidoMostrador::ESTADO_COMANDA_NO, $pedido->fresh()->estado_comanda);

        // Marco solo el primero.
        DB::connection('pymes_tenant')->table('pedidos_mostrador_detalle')
            ->where('id', $pedido->detalles->first()->id)
            ->update(['comandado_at' => now()]);
        $this->assertSame(PedidoMostrador::ESTADO_COMANDA_PARCIAL, $pedido->fresh()->estado_comanda);

        // Marco todos.
        DB::connection('pymes_tenant')->table('pedidos_mostrador_detalle')
            ->where('pedido_mostrador_id', $pedido->id)
            ->update(['comandado_at' => now()]);
        $this->assertSame(PedidoMostrador::ESTADO_COMANDA_TOTAL, $pedido->fresh()->estado_comanda);
    }

    public function test_comandar_nuevos_sin_items_nuevos_lanza_excepcion(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(1);
        $this->service->comandarPedido($pedido, PedidoMostradorService::ALCANCE_COMANDA_TODOS);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No hay items nuevos para comandar');

        $this->service->comandarPedido($pedido->fresh(), PedidoMostradorService::ALCANCE_COMANDA_NUEVOS);
    }

    public function test_comandar_con_alcance_invalido_lanza_excepcion(): void
    {
        $pedido = $this->pedidoConfirmadoConVariosDetalles(1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Alcance de comanda inválido');

        $this->service->comandarPedido($pedido, 'cualquier_cosa');
    }
}
