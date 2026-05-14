<?php

namespace Tests\Feature\Livewire\Pedidos;

use App\Events\Broadcasting\PedidoMostradorBroadcast;
use App\Livewire\Pedidos\NuevoPedidoMostrador;
use App\Livewire\Pedidos\PedidosMostrador;
use App\Models\PedidoMostrador;
use App\Models\User;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Smoke tests del módulo Pedidos por Mostrador.
 *
 * Verifica que los componentes Livewire monten sin errores: detecta fallas en
 * mount, sintaxis Blade inválida, variables indefinidas, dependencias rotas.
 */
class SmokePedidosTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Forzar caches de SucursalService a "acceso total" para tests.
        // En produccion, SucursalService::getSucursalesDisponibles() consulta
        // model_has_roles (Spatie). En tests es complicado configurar Spatie
        // multi-tenant correctamente — mas simple bypassear el caché directo.
        // Valor especial 0 en sucursalIdsCache = acceso a todas las sucursales.
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        $p = $ref->getProperty('sucursalIdsCache');
        $p->setAccessible(true);
        $p->setValue(null, [0]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_pedidos_mostrador_monta(): void
    {
        Livewire::test(PedidosMostrador::class)->assertOk();
    }

    public function test_nuevo_pedido_mostrador_monta(): void
    {
        Livewire::test(NuevoPedidoMostrador::class)->assertOk();
    }

    public function test_pedidos_mostrador_abre_modal_alta(): void
    {
        Livewire::test(PedidosMostrador::class)
            ->call('abrirModalNuevoPedido')
            ->assertSet('modalNuevoPedidoAbierto', true)
            ->assertSet('pedidoIdEnEdicion', null);
    }

    public function test_guardar_borrador_crea_pedido_sin_numero_ni_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class);

        // Simular agregar artículo al carrito (como hace el wire:click en la UI).
        $componente->call('seleccionarArticulo', $articulo->id);

        $componente->call('guardarBorrador');

        $pedidos = PedidoMostrador::all();
        $this->assertCount(1, $pedidos, 'Debe haberse creado un pedido');
        $this->assertEquals(PedidoMostrador::ESTADO_BORRADOR, $pedidos->first()->estado_pedido);
        $this->assertNull($pedidos->first()->numero, 'Borrador no asigna número');

        // El stock no debe haberse descontado.
        $stock = \App\Models\Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();
        $this->assertEquals(50.0, (float) $stock->cantidad, 'Borrador no descuenta stock');
    }

    // ==================== ACCIONES RAPIDAS + BROADCAST ====================

    /**
     * Helper: crea un pedido CONFIRMADO listo para testear acciones.
     */
    protected function crearPedidoConfirmado(): PedidoMostrador
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $service = app(PedidoMostradorService::class);

        return $service->crearPedido([
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id() ?? 1,
            'fecha' => now(),
            'es_borrador' => false,
            'subtotal' => 1000,
            'descuento' => 0,
            'ajuste_forma_pago' => 0,
            'total_final' => 1000,
            'total_cobrado' => 0,
            'estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
        ], [
            [
                'articulo_id' => $articulo->id,
                'tipo' => 'articulo',
                'cantidad' => 1,
                'precio_unitario' => 1000,
                'precio_sin_iva' => 826.45,
                'tipo_iva_id' => $articulo->tipo_iva_id,
                'iva_porcentaje' => 21,
                'iva_monto' => 173.55,
                'descuento' => 0,
                'subtotal' => 1000,
                'total' => 1000,
            ],
        ]);
    }

    public function test_entregar_rapido_cambia_estado_a_entregado(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_entregar_rapido_rechaza_si_la_transicion_no_es_legal(): void
    {
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pedido' => PedidoMostrador::ESTADO_CANCELADO]);

        Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        $this->assertSame(PedidoMostrador::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido);
    }

    public function test_cobrar_rapido_sin_planificados_abre_modal_de_cobro(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        Livewire::test(PedidosMostrador::class)
            ->call('cobrarRapido', $pedido->id)
            ->assertSet('showCobrarModal', true)
            ->assertSet('pedidoCobrarId', $pedido->id);
    }

    public function test_pedido_mostrador_broadcast_se_dispatcha_al_cambiar_estado(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        Event::fake([PedidoMostradorBroadcast::class]);

        app(PedidoMostradorService::class)->cambiarEstado($pedido->fresh(), PedidoMostrador::ESTADO_ENTREGADO);

        Event::assertDispatched(
            PedidoMostradorBroadcast::class,
            fn (PedidoMostradorBroadcast $e) => $e->pedidoId === $pedido->id
                && $e->tipo === PedidoMostradorBroadcast::TIPO_ESTADO_CAMBIADO
        );
    }

    public function test_listener_echo_filtra_por_sucursal_distinta(): void
    {
        $componente = Livewire::test(PedidosMostrador::class);
        $otraSucursalId = $this->sucursalId + 999;

        $componente->call('onPedidoBroadcast', [
            'pedidoId' => 12345,
            'sucursalId' => $otraSucursalId,
            'tipo' => PedidoMostradorBroadcast::TIPO_CREADO,
            'at' => now()->toIso8601String(),
        ]);

        // Como la sucursal no coincide, no debe incrementar nuevosCount.
        $componente->assertSet('nuevosCount', 0);
    }

    public function test_listener_echo_incrementa_nuevos_count_si_pedido_es_creado(): void
    {
        // Mount captura snapshot (vacio porque no hay pedidos).
        $componente = Livewire::test(PedidosMostrador::class);

        $componente->call('onPedidoBroadcast', [
            'pedidoId' => 12345,
            'sucursalId' => $this->sucursalId,
            'tipo' => PedidoMostradorBroadcast::TIPO_CREADO,
            'at' => now()->toIso8601String(),
        ]);

        $componente->assertSet('nuevosCount', 1);
    }

    public function test_marcar_todos_vistos_resetea_contador(): void
    {
        $componente = Livewire::test(PedidosMostrador::class)
            ->set('nuevosCount', 3);

        $componente->call('marcarTodosVistos')
            ->assertSet('nuevosCount', 0);
    }
}
