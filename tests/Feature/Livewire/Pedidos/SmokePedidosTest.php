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

    public function test_broadcast_dispatcha_evento_frontend_para_destacar_pedido(): void
    {
        $componente = Livewire::test(PedidosMostrador::class);

        $componente->call('onPedidoBroadcast', [
            'pedidoId' => 7777,
            'sucursalId' => $this->sucursalId,
            'tipo' => PedidoMostradorBroadcast::TIPO_ESTADO_CAMBIADO,
            'at' => now()->toIso8601String(),
        ]);

        // El frontend escucha `pedido-destacado` (.window) para resaltar la fila/card
        // del pedido hasta que el usuario interactue. Debe dispatchearse para
        // CUALQUIER tipo de evento broadcast (creado, estado cambiado, etc).
        $componente->assertDispatched('pedido-destacado', pedidoId: 7777);
    }

    public function test_broadcast_no_dispatcha_destacado_si_sucursal_no_coincide(): void
    {
        $componente = Livewire::test(PedidosMostrador::class);

        $componente->call('onPedidoBroadcast', [
            'pedidoId' => 7777,
            'sucursalId' => $this->sucursalId + 999,
            'tipo' => PedidoMostradorBroadcast::TIPO_CREADO,
            'at' => now()->toIso8601String(),
        ]);

        $componente->assertNotDispatched('pedido-destacado');
    }

    public function test_marcar_todos_vistos_resetea_contador(): void
    {
        $componente = Livewire::test(PedidosMostrador::class)
            ->set('nuevosCount', 3);

        $componente->call('marcarTodosVistos')
            ->assertSet('nuevosCount', 0);
    }

    // ==================== KANBAN ====================

    public function test_cambiar_estado_drag_con_transicion_legal_funciona(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        Livewire::test(PedidosMostrador::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoMostrador::ESTADO_ENTREGADO);

        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_cambiar_estado_drag_rechaza_estado_fuera_de_kanban(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        Livewire::test(PedidosMostrador::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoMostrador::ESTADO_CANCELADO)
            ->assertDispatched('toast-error')
            ->assertDispatched('kanban-revertir');

        $this->assertSame(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->fresh()->estado_pedido);
    }

    public function test_cambiar_estado_drag_rechaza_transicion_ilegal(): void
    {
        $pedido = $this->crearPedidoConfirmado();
        // Forzar al estado ENTREGADO para probar una transicion ilegal hacia atras
        $pedido->update(['estado_pedido' => PedidoMostrador::ESTADO_ENTREGADO]);

        Livewire::test(PedidosMostrador::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoMostrador::ESTADO_CONFIRMADO)
            ->assertDispatched('toast-error')
            ->assertDispatched('kanban-revertir');

        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    /**
     * Reproductor: al editar un pedido con desglose mixto persistido, el
     * `ajusteFormaPagoInfo` debe reflejar el ajuste real para que el total
     * visible en el form coincida con `pedido.total_final`.
     */
    public function test_editar_pedido_con_desglose_mixto_hidrata_ajuste_fp(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $efectivo = $this->crearFormaPagoEfectivo();

        $service = app(PedidoMostradorService::class);
        $pedido = $service->crearPedido([
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id() ?? 1,
            'fecha' => now(),
            'subtotal' => 1000,
            'iva' => 0,
            'descuento' => 0,
            'total' => 1000,
            'ajuste_forma_pago' => 0,
            'total_final' => 1000,
        ], [
            [
                'articulo_id' => $articulo->id,
                'cantidad' => 1,
                'precio_unitario' => 1000,
                'precio_sin_iva' => 826.45,
                'subtotal' => 1000,
                'iva_porcentaje' => 21,
                'iva_monto' => 173.55,
                'total' => 1000,
            ],
        ], esBorrador: false);

        // Dos pagos planificados sumando el total con un descuento FP de $50.
        $service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 600,
            'monto_ajuste' => -30,
            'monto_final' => 570,
            'planificado' => true,
            'afecta_caja' => true,
        ]);
        $service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 400,
            'monto_ajuste' => -20,
            'monto_final' => 380,
            'planificado' => true,
            'afecta_caja' => true,
        ]);

        $pedido->refresh();
        $this->assertEqualsWithDelta(950.0, (float) $pedido->total_final, 0.01, 'recalcularTotales debe haber dejado total_final=950');

        // Crear FP "Mixta" para que la hidratación pueda restaurar formaPagoId.
        $fpMixta = \App\Models\FormaPago::create([
            'nombre' => 'Mixto',
            'codigo' => 'mixto',
            'concepto' => 'otro',
            'concepto_pago_id' => $efectivo['concepto']->id,
            'es_mixta' => true,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        $componente = Livewire::test(NuevoPedidoMostrador::class, ['pedidoId' => $pedido->id]);

        $info = $componente->get('ajusteFormaPagoInfo');
        $this->assertTrue((bool) $info['es_mixta'], 'es_mixta debe ser true para desglose multi-pago');
        $this->assertEqualsWithDelta(-50.0, (float) $info['monto'], 0.01, 'monto del ajuste debe reflejar la suma de los ajustes persistidos');
        $this->assertEqualsWithDelta(950.0, (float) $info['total_con_ajuste'], 0.01, 'total_con_ajuste = total + ajuste FP');

        $desglose = $componente->get('desglosePagos');
        $this->assertCount(2, $desglose);

        // formaPagoId debe quedar seteado en la FP Mixta para que el selector la muestre.
        $this->assertEquals($fpMixta->id, (int) $componente->get('formaPagoId'),
            'formaPagoId debe restaurarse a la FP Mixta cuando hay desglose multi-pago');

        // editarDesglose() debe existir y abrir el modal con el desglose cargado.
        $componente->call('editarDesglose')
            ->assertSet('mostrarModalPago', true);
        $this->assertCount(2, $componente->get('desglosePagos'),
            'editarDesglose no debe vaciar desglosePagos');
    }

    /**
     * Smoke del modal Ver pedido: render sin errores con un pedido que incluye
     * promociones, pagos y observaciones (camino completo de paridad con Ver venta).
     */
    public function test_render_modal_ver_pedido_completo(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        // Marcar promo nivel pedido para forzar render de la sección.
        \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('pedido_mostrador_promociones')->insert([
                'pedido_mostrador_id' => $pedido->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => null,
                'promocion_especial_id' => null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => 'Promo test',
                'tipo_beneficio' => 'porcentaje',
                'valor_beneficio' => 10,
                'descuento_aplicado' => 100,
                'monto_minimo_requerido' => null,
                'created_at' => now(),
            ]);

        Livewire::test(PedidosMostrador::class)
            ->call('verDetalle', $pedido->id)
            ->assertOk()
            ->assertSet('showDetalleModal', true)
            ->assertSet('pedidoDetalleId', $pedido->id)
            ->assertSee('Promo test')
            ->assertSee('Promociones aplicadas');
    }

    public function test_render_kanban_agrupa_pedidos_por_estado(): void
    {
        $pedido1 = $this->crearPedidoConfirmado();
        $pedido2 = $this->crearPedidoConfirmado();
        $pedido2->update(['estado_pedido' => PedidoMostrador::ESTADO_LISTO]);

        $componente = Livewire::test(PedidosMostrador::class);
        $kanban = $componente->viewData('pedidosKanban');

        $this->assertTrue(
            $kanban[PedidoMostrador::ESTADO_CONFIRMADO]->pluck('id')->contains($pedido1->id),
            'pedido1 (CONFIRMADO) debe estar en la columna confirmado'
        );
        $this->assertTrue(
            $kanban[PedidoMostrador::ESTADO_LISTO]->pluck('id')->contains($pedido2->id),
            'pedido2 (LISTO) debe estar en la columna listo'
        );
        $this->assertFalse(
            $kanban[PedidoMostrador::ESTADO_CONFIRMADO]->pluck('id')->contains($pedido2->id),
            'pedido2 NO debe aparecer en confirmado tras el update'
        );
    }
}
