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

    public function test_render_item_invitado_muestra_badge_y_precio_tachado(): void
    {
        // Fase 5 (UI inline): tras invitar un item, la vista del carrito debe
        // mostrar el badge "Invitado" y el motivo en la columna del articulo.
        // Smoke visual: detecta regresiones del partial _detalle-items.blade.php.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirInvitarItem', 0)
            ->set('invitarItemMotivo', 'Cortesía VIP')
            ->call('confirmarInvitarItem');

        $componente->assertSee('Invitado')
            ->assertSee('Cortesía VIP')
            ->assertSee('Cortesía'); // Columna Promo cambia a "Cortesía" para invitados

        // El item del carrito debe tener es_invitacion=true tras confirmar.
        $items = $componente->get('items');
        $this->assertTrue((bool) $items[0]['es_invitacion']);
        $this->assertEqualsWithDelta(0.0, (float) $items[0]['precio'], 0.01);
    }

    public function test_abrir_invitar_item_setea_estado_del_modal(): void
    {
        // Fase 5: smoke del flujo del mini-modal. abrirInvitarItem deja
        // mostrarModalInvitarItem=true e invitarItemIndex apuntando al item.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirInvitarItem', 0)
            ->assertSet('mostrarModalInvitarItem', true)
            ->assertSet('invitarItemIndex', 0)
            ->assertSet('invitarItemMotivo', '')
            ->call('cerrarModalInvitarItem')
            ->assertSet('mostrarModalInvitarItem', false)
            ->assertSet('invitarItemIndex', null);
    }

    public function test_confirmar_invitacion_total_persiste_pedido_sin_pagos(): void
    {
        // Fase 6: ciclo completo desde el modal de cobro. Activar switch +
        // ingresar motivo + click "Confirmar invitación" debe persistir el
        // pedido con todos los items invitados y estado_pago=pagado.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('nombreClienteTemporal', 'Juan Test')
            ->set('telefonoClienteTemporal', '12345')
            ->call('confirmarPedido') // Abre el modal en modo cobro
            ->assertSet('mostrarModalPago', true)
            ->assertSet('modalPagoEnModoCobro', true)
            ->call('toggleInvitarTodo')
            ->assertSet('invitarTodo', true)
            ->set('motivoInvitacionTotal', 'Cliente VIP — evento')
            ->call('confirmarInvitacionTotal');

        $componente->assertDispatched('pedido-guardado');

        $pedido = PedidoMostrador::with('detalles')->first();
        $this->assertNotNull($pedido);
        $this->assertTrue((bool) $pedido->es_invitacion_total);
        $this->assertSame('Cliente VIP — evento', $pedido->invitacion_motivo);
        $this->assertNotNull($pedido->invitado_por_usuario_id);
        $this->assertNotNull($pedido->invitado_at);
        $this->assertEqualsWithDelta(0.0, (float) $pedido->total_final, 0.01);
        $this->assertGreaterThan(0, (float) $pedido->total_invitado);
        $this->assertSame(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        $this->assertSame(0, $pedido->pagos()->count(), 'No debe haber pagos');

        $detalle = $pedido->detalles->first();
        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertSame('Cliente VIP — evento', $detalle->invitacion_motivo);
    }

    public function test_confirmar_invitacion_total_rechaza_sin_motivo(): void
    {
        // Fase 6: el motivo es obligatorio. Sin motivo, el botón está disabled
        // en la UI pero defendemos también en backend (vía trait).
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('nombreClienteTemporal', 'Juan Test')
            ->set('telefonoClienteTemporal', '12345')
            ->call('confirmarPedido')
            ->call('toggleInvitarTodo')
            ->set('motivoInvitacionTotal', '   ') // solo whitespace
            ->call('confirmarInvitacionTotal')
            ->assertDispatched('toast-error');

        $this->assertSame(0, PedidoMostrador::count(),
            'No debe persistirse pedido si el motivo está vacío');
    }

    public function test_toggle_invitar_todo_apaga_resetea_motivo(): void
    {
        // Fase 6: apagar el switch borra el motivo (evita persistir basura si
        // el usuario decide finalmente no invitar).
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('nombreClienteTemporal', 'Juan Test')
            ->set('telefonoClienteTemporal', '12345')
            ->call('confirmarPedido')
            ->call('toggleInvitarTodo')
            ->set('motivoInvitacionTotal', 'Algo')
            ->call('toggleInvitarTodo')
            ->assertSet('invitarTodo', false)
            ->assertSet('motivoInvitacionTotal', '');
    }

    public function test_modal_global_invitar_todo_marca_items_sin_persistir(): void
    {
        // Botón "Invitar" en la vista principal (al lado de Descuentos): abre
        // un mini-modal con textarea de motivo. Confirmar marca todos los items
        // como cortesía pero NO persiste — la persistencia llega cuando el
        // usuario aprieta cualquiera de los botones de confirmar el pedido.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->assertSet('mostrarModalInvitarTodo', true)
            ->assertSet('motivoInvitacionTotal', '') // se resetea al abrir
            ->set('motivoInvitacionTotal', 'Cortesía gerencia')
            ->call('confirmarInvitarTodo')
            ->assertSet('mostrarModalInvitarTodo', false); // se cierra al OK

        // Items marcados pero NADA persistido aún.
        $items = $componente->get('items');
        $this->assertTrue((bool) $items[0]['es_invitacion']);
        $this->assertSame('Cortesía gerencia', $items[0]['invitacion_motivo']);
        $this->assertEqualsWithDelta(0.0, (float) $items[0]['precio'], 0.01);
        $this->assertSame(0, PedidoMostrador::count(),
            'El mini-modal global solo marca en memoria, no persiste');
    }

    public function test_modal_global_invitar_todo_rechaza_sin_motivo(): void
    {
        // El motivo es obligatorio (defensa en backend además del @disabled UI).
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', '   ') // solo whitespace
            ->call('confirmarInvitarTodo')
            ->assertDispatched('toast-error')
            ->assertSet('mostrarModalInvitarTodo', true); // no se cierra si falló

        $items = $componente->get('items');
        $this->assertFalse((bool) ($items[0]['es_invitacion'] ?? false));
    }

    public function test_desinvitar_todos_revierte_pedido_completo(): void
    {
        // Cuando el pedido está totalmente invitado, el botón "Cortesía" abre
        // un modal de confirmación que llama a desinvitarTodos() y restaura
        // los precios originales de todos los items.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarArticulo', $articulo->id) // 2do item (mismo articulo, otra linea)
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', 'Evento VIP')
            ->call('confirmarInvitarTodo');

        // Sanity check: ambos items invitados antes de revertir.
        $items = $componente->get('items');
        $this->assertGreaterThanOrEqual(1, count($items));
        foreach ($items as $item) {
            $this->assertTrue((bool) $item['es_invitacion']);
        }

        $precioOriginal = (float) $items[0]['precio_unitario_original'];

        $componente->call('abrirModalDesinvitarTodo')
            ->assertSet('mostrarModalDesinvitarTodo', true)
            ->call('desinvitarTodos')
            ->assertSet('mostrarModalDesinvitarTodo', false)
            ->assertSet('motivoInvitacionTotal', '')
            ->assertSet('invitarTodo', false);

        $items = $componente->get('items');
        foreach ($items as $item) {
            $this->assertFalse((bool) $item['es_invitacion']);
            $this->assertNull($item['precio_unitario_original']);
        }
        $this->assertEqualsWithDelta($precioOriginal, (float) $items[0]['precio'], 0.01);
    }

    public function test_confirmar_sin_cobrar_persiste_pedido_invitado_via_modal_global(): void
    {
        // Flow end-to-end: invitar todo desde el mini-modal global y después
        // apretar "Confirmar sin cobrar" en la vista principal. El pedido se
        // persiste con total_final=0 y estado_pago=pagado.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('nombreClienteTemporal', 'Juan Test')
            ->set('telefonoClienteTemporal', '12345')
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', 'Evento')
            ->call('confirmarInvitarTodo')
            ->call('confirmarSinCobrar')
            ->assertDispatched('pedido-guardado');

        $pedido = PedidoMostrador::with('detalles')->first();
        $this->assertNotNull($pedido);
        $this->assertTrue((bool) $pedido->es_invitacion_total);
        $this->assertSame('Evento', $pedido->invitacion_motivo);
        $this->assertEqualsWithDelta(0.0, (float) $pedido->total_final, 0.01);
        $this->assertSame(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        $this->assertSame(0, $pedido->pagos()->count());
    }

    public function test_desinvitar_item_restaura_precio_y_recalcula(): void
    {
        // Fase 5: invitar y luego desinvitar via los modales debe restaurar
        // el precio original e ir limpiando el flag es_invitacion.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirInvitarItem', 0)
            ->set('invitarItemMotivo', 'Test')
            ->call('confirmarInvitarItem');

        $precioOriginal = (float) $componente->get('items')[0]['precio_unitario_original'];
        $this->assertGreaterThan(0, $precioOriginal, 'Snapshot del precio original debe estar guardado');

        $componente->call('abrirDesinvitarItem', 0)
            ->assertSet('mostrarModalDesinvitarItem', true)
            ->assertSet('desinvitarItemIndex', 0)
            ->call('confirmarDesinvitarItem');

        $items = $componente->get('items');
        $this->assertFalse((bool) $items[0]['es_invitacion']);
        $this->assertEqualsWithDelta($precioOriginal, (float) $items[0]['precio'], 0.01,
            'Precio debe restaurarse al snapshot precio_unitario_original');
        $this->assertNull($items[0]['precio_unitario_original']);
    }

    public function test_guardar_borrador_con_item_invitado_persiste_columnas_de_cortesia(): void
    {
        // Fase 4 (invitaciones): el trait WithInvitaciones esta compuesto en
        // NuevoPedidoMostrador. Cubre que el flujo Livewire → construirDataPedido
        // / construirDetallesPedido propaga las columnas de invitacion al
        // service, y que el pedido persistido refleja la cortesia.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoMostrador::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirInvitarItem', 0)
            ->set('invitarItemMotivo', 'Cliente VIP')
            ->call('confirmarInvitarItem')
            ->call('guardarBorrador');

        $pedido = PedidoMostrador::with('detalles')->first();
        $this->assertNotNull($pedido, 'Debe haberse creado un pedido borrador');

        // Como es el unico item del carrito y esta invitado, el pedido entero
        // queda marcado como invitacion total (computed esInvitacionTotal=true
        // del trait).
        $this->assertTrue((bool) $pedido->es_invitacion_total,
            'Pedido con todos los items invitados debe tener es_invitacion_total=true');
        $this->assertGreaterThan(0, (float) $pedido->total_invitado,
            'total_invitado debe reflejar la suma de monto_invitado de items');
        $this->assertEqualsWithDelta(0.0, (float) $pedido->total_final, 0.01,
            'total_final del pedido cortesia debe ser 0');

        $detalle = $pedido->detalles->first();
        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertSame('Cliente VIP', $detalle->invitacion_motivo);
        $this->assertNotNull($detalle->invitado_por_usuario_id);
        $this->assertNotNull($detalle->invitado_at);
        $this->assertGreaterThan(0, (float) $detalle->monto_invitado);
        $this->assertNotNull($detalle->precio_unitario_original);
        $this->assertEqualsWithDelta(0.0, (float) $detalle->precio_unitario, 0.01,
            'precio_unitario del item invitado se persiste en 0');
    }

    public function test_editar_pedido_invitado_rehidrata_estado_del_trait(): void
    {
        // Fase 4: al editar un pedido con cortesia persistida, los items del
        // carrito deben re-cargar las columnas de invitacion para que la UI
        // pueda renderizar el badge y permitir des-invitar.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $service = app(PedidoMostradorService::class);

        $pedido = $service->crearPedido(
            data: [
                'sucursal_id' => $this->sucursalId,
                'usuario_id' => auth()->id() ?? 1,
                'fecha' => now(),
                'subtotal' => 0,
                'iva' => 0,
                'descuento' => 0,
                'total' => 0,
                'ajuste_forma_pago' => 0,
                'total_final' => 0,
                'es_invitacion_total' => true,
                'invitacion_motivo' => 'Cortesía gerencia',
                'invitado_por_usuario_id' => auth()->id() ?? 1,
                'invitado_at' => now(),
                'total_invitado' => 500.0,
            ],
            detalles: [
                [
                    'articulo_id' => $articulo->id,
                    'tipo_iva_id' => $articulo->tipo_iva_id,
                    'cantidad' => 1,
                    'precio_unitario' => 0,
                    'precio_sin_iva' => 0,
                    'precio_lista' => 500,
                    'subtotal' => 0,
                    'iva_porcentaje' => 21,
                    'iva_monto' => 0,
                    'total' => 0,
                    'es_invitacion' => true,
                    'invitacion_motivo' => 'Cortesía gerencia',
                    'invitado_por_usuario_id' => auth()->id() ?? 1,
                    'invitado_at' => now(),
                    'monto_invitado' => 500.0,
                    'precio_unitario_original' => 500.0,
                ],
            ],
            esBorrador: false,
        );

        $componente = Livewire::test(NuevoPedidoMostrador::class, ['pedidoId' => $pedido->id]);

        $items = $componente->get('items');
        $this->assertNotEmpty($items, 'Debe haber al menos un item rehidratado');
        $this->assertTrue((bool) $items[0]['es_invitacion'],
            'Item rehidratado debe mantener flag es_invitacion');
        $this->assertSame('Cortesía gerencia', $items[0]['invitacion_motivo']);
        $this->assertEqualsWithDelta(500.0, (float) $items[0]['monto_invitado'], 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $items[0]['precio_unitario_original'], 0.01);

        $this->assertSame('Cortesía gerencia', $componente->get('motivoInvitacionTotal'),
            'motivoInvitacionTotal debe rehidratarse desde la cabecera persistida');
        $this->assertEqualsWithDelta(500.0, (float) $componente->get('totalInvitado'), 0.01,
            'totalInvitado del trait debe reflejar lo persistido');
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
        // El gate de cobro exige pedido 100% cubierto para entregar: simulamos
        // el cobro completo agregando un pago activo por el total.
        $efectivo = $this->crearFormaPagoEfectivo();
        app(PedidoMostradorService::class)->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => (float) $pedido->total_final,
            'monto_final' => (float) $pedido->total_final,
            'cuotas' => 1,
        ]);

        Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_entregar_rapido_no_intercepta_si_planificados_cubren_total(): void
    {
        // Pedido con planificados al 100% pero cobrado=0: el gate no debe
        // intervenir porque convertirEnVenta materializa los planificados.
        // Caso real: operario armó el pedido con desglose pero el cliente
        // todavía no pagó; cuando entrega, la materialización los confirma.
        $pedido = $this->crearPedidoConfirmado();
        $efectivo = $this->crearFormaPagoEfectivo();

        app(PedidoMostradorService::class)->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => (float) $pedido->total_final,
            'monto_final' => (float) $pedido->total_final,
            'cuotas' => 1,
            'planificado' => true,
        ]);

        Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        // Transitó sin abrir cobro rapido — los planificados cubren el saldo.
        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_entregar_rapido_intercepta_y_abre_cobro_si_pedido_no_cobrado(): void
    {
        $pedido = $this->crearPedidoConfirmado();
        // estado_pago = pendiente por default → el gate debe interceptar.

        $componente = Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        // No transicionó.
        $this->assertSame(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->fresh()->estado_pedido);
        // Acción quedó pendiente y modal de cobro rápido se abrió.
        $componente->assertSet('accionPendiente', 'entregar')
            ->assertSet('accionPendientePedidoId', $pedido->id)
            ->assertSet('pedidoCobroRapidoId', $pedido->id);
    }

    public function test_entregar_rapido_rechaza_si_la_transicion_no_es_legal(): void
    {
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pedido' => PedidoMostrador::ESTADO_CANCELADO]);

        Livewire::test(PedidosMostrador::class)
            ->call('entregarRapido', $pedido->id);

        $this->assertSame(PedidoMostrador::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido);
    }

    public function test_cobrar_rapido_sin_planificados_abre_cobro_rapido_para_pedido_editable(): void
    {
        // Pedido CONFIRMADO con estado_pago=pendiente: cumple regla de
        // pedidoEsEditable() → cobrarRapido abre el MODAL de cobro rapido
        // (sub-componente NuevoPedidoMostrador en modoCobroRapido=true) en
        // lugar del editor full-screen.
        $pedido = $this->crearPedidoConfirmado();

        Livewire::test(PedidosMostrador::class)
            ->call('cobrarRapido', $pedido->id)
            ->assertSet('pedidoCobroRapidoId', $pedido->id)
            ->assertSet('modalNuevoPedidoAbierto', false);
    }

    public function test_cobrar_rapido_funciona_en_pedido_en_preparacion_sin_planificados(): void
    {
        // Regresión: con comanda automática los pedidos pasan a EN_PREPARACION
        // apenas confirmados. El botón "Cobrar" debe abrir directo el modal
        // de cobro rápido — no caer al flujo viejo que rechazaba con dos toasts.
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pedido' => PedidoMostrador::ESTADO_EN_PREPARACION]);

        Livewire::test(PedidosMostrador::class)
            ->call('cobrarRapido', $pedido->id)
            ->assertSet('pedidoCobroRapidoId', $pedido->id)
            ->assertNotDispatched('toast-error');
    }

    public function test_abrir_modal_editar_acepta_estados_en_preparacion_y_listo_sin_cobros(): void
    {
        // Regresion: la lista permitia abrir el editor solo si el pedido
        // estaba en borrador/confirmado. Pero la regla real es "editable
        // mientras no haya cobros materializados": en preparacion o listo
        // el operario debe poder agregar items hasta que el cliente pague.
        foreach ([PedidoMostrador::ESTADO_EN_PREPARACION, PedidoMostrador::ESTADO_LISTO] as $estado) {
            $pedido = $this->crearPedidoConfirmado();
            $pedido->update(['estado_pedido' => $estado]);

            Livewire::test(PedidosMostrador::class)
                ->call('abrirModalEditarPedido', $pedido->id)
                ->assertSet('pedidoIdEnEdicion', $pedido->id)
                ->assertSet('modalNuevoPedidoAbierto', true)
                ->assertNotDispatched('toast-error');
        }
    }

    public function test_nuevo_pedido_mostrador_cobro_rapido_acepta_estado_en_preparacion(): void
    {
        // El sub-componente en modoCobroRapido debe hidratarse sin error sobre
        // pedidos en EN_PREPARACION / LISTO. Antes rechazaba con "no se puede
        // editar" porque reusaba el guard de edición del carrito.
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pedido' => PedidoMostrador::ESTADO_LISTO]);

        $componente = Livewire::test(NuevoPedidoMostrador::class, [
            'pedidoId' => $pedido->id,
            'modoCobroRapido' => true,
        ])->assertOk();

        $componente->assertSet('modoCobroRapido', true)
            ->assertSet('mostrarModalPago', true)
            ->assertNotDispatched('toast-error')
            ->assertNotDispatched('cerrar-cobro-rapido');
    }

    public function test_nuevo_pedido_mostrador_modo_cobro_rapido_monta_sobre_pedido_confirmado(): void
    {
        // Smoke test del modo cobro rapido: el componente debe montar sin
        // errores y dejar el modal de desglose abierto con monto pendiente
        // igual al saldo del pedido.
        $pedido = $this->crearPedidoConfirmado();

        $componente = Livewire::test(NuevoPedidoMostrador::class, [
            'pedidoId' => $pedido->id,
            'modoCobroRapido' => true,
        ])->assertOk();

        $componente->assertSet('modoCobroRapido', true)
            ->assertSet('mostrarModalPago', true)
            ->assertSet('modalPagoEnModoCobro', true);

        $this->assertEqualsWithDelta(
            (float) $pedido->total_final,
            (float) $componente->get('montoPendienteDesglose'),
            0.01,
            'montoPendienteDesglose debe arrancar igual al saldo (no hay cobrado ni planificado)'
        );
    }

    public function test_cobro_rapido_con_una_fp_persiste_pago_activo_con_esa_fp(): void
    {
        // Caso "feature extra" del flujo: el desglose con UNA sola FP que
        // cubre el total debe crear UN pago con esa FP individual (no con
        // la FP mixta). Esto valida implicitamente: PedidoMostrador no
        // tiene forma_pago_id propio — los pagos llevan la suya por fila.
        $pedido = $this->crearPedidoConfirmado();
        $efectivo = $this->crearFormaPagoEfectivo();

        $componente = Livewire::test(NuevoPedidoMostrador::class, [
            'pedidoId' => $pedido->id,
            'modoCobroRapido' => true,
        ]);

        $componente->set('nuevoPago.forma_pago_id', $efectivo['formaPago']->id)
            ->set('nuevoPago.monto', (float) $pedido->total_final)
            ->call('agregarAlDesglose');

        $this->assertCount(1, $componente->get('desglosePagos'),
            'El desglose debe tener 1 pago tras agregarAlDesglose');

        $componente->call('confirmarPago')
            ->assertDispatched('cobro-rapido-completado');

        $pagos = $pedido->pagos()->get();
        $this->assertCount(1, $pagos, 'Debe haberse persistido 1 pago en el pedido');
        $this->assertSame('activo', $pagos->first()->estado,
            'El pago debe quedar ACTIVO (no planificado)');
        $this->assertSame($efectivo['formaPago']->id, (int) $pagos->first()->forma_pago_id,
            'El pago debe llevar la FP individual (no la FP mixta)');

        $pedido->refresh();
        $this->assertSame(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago,
            'Tras cobrar el total, estado_pago debe pasar a PAGADO');
    }

    public function test_cobro_rapido_desde_modal_parcial_dispara_abrir_cobro_rapido(): void
    {
        // Cuando el pedido esta parcialmente cobrado (no editable), el
        // modal "Cobrar pendiente" se abre con el boton "Definir pagos".
        // Verificamos que ese boton invoca abrirCobroRapido y deja
        // pedidoCobroRapidoId seteado + cierra el modal parcial.
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pago' => PedidoMostrador::ESTADO_PAGO_PARCIAL]);

        Livewire::test(PedidosMostrador::class)
            ->call('abrirCobrar', $pedido->id)
            ->assertSet('showCobrarModal', true)
            ->call('abrirCobroRapido', $pedido->id)
            ->assertSet('pedidoCobroRapidoId', $pedido->id)
            ->assertSet('showCobrarModal', false);
    }

    public function test_cobrar_rapido_sin_planificados_abre_cobro_rapido_aunque_haya_parcial(): void
    {
        // Pedido con cobro parcial previo: cobrarRapido sigue abriendo el
        // modal de desglose (NuevoPedidoMostrador en modoCobroRapido) para
        // cobrar lo que falta. Antes caía al modal estándar; ahora el cobro
        // rápido tolera cobros materializados previos.
        $pedido = $this->crearPedidoConfirmado();
        $pedido->update(['estado_pago' => PedidoMostrador::ESTADO_PAGO_PARCIAL]);

        Livewire::test(PedidosMostrador::class)
            ->call('cobrarRapido', $pedido->id)
            ->assertSet('pedidoCobroRapidoId', $pedido->id)
            ->assertSet('showCobrarModal', false)
            ->assertSet('modalNuevoPedidoAbierto', false);
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
        // El drag a ENTREGADO también pasa por el gate de cobro: cobrar primero.
        $efectivo = $this->crearFormaPagoEfectivo();
        app(PedidoMostradorService::class)->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => (float) $pedido->total_final,
            'monto_final' => (float) $pedido->total_final,
            'cuotas' => 1,
        ]);

        Livewire::test(PedidosMostrador::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoMostrador::ESTADO_ENTREGADO);

        $this->assertSame(PedidoMostrador::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_cambiar_estado_drag_a_entregado_intercepta_si_no_cobrado(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        $componente = Livewire::test(PedidosMostrador::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoMostrador::ESTADO_ENTREGADO);

        // No transicionó, se revirtió el drag y se abrió el cobro.
        $this->assertSame(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->fresh()->estado_pedido);
        $componente->assertDispatched('kanban-revertir')
            ->assertSet('accionPendiente', 'entregar')
            ->assertSet('pedidoCobroRapidoId', $pedido->id);
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

    public function test_reimprimir_comanda_avanza_a_en_preparacion_si_confirmado(): void
    {
        $pedido = $this->crearPedidoConfirmado();
        $this->assertSame(PedidoMostrador::ESTADO_CONFIRMADO, $pedido->fresh()->estado_pedido);

        Livewire::test(PedidosMostrador::class)
            ->call('reimprimirComanda', $pedido->id);

        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->fresh()->estado_pedido);
    }

    public function test_confirmar_pedido_con_comanda_automatica_avanza_a_en_preparacion(): void
    {
        // Opt-in al modo de impresión automática (default productivo).
        \DB::connection('pymes_tenant')->table('sucursales')
            ->where('id', $this->sucursalId)
            ->update(['imprime_comanda_automatico' => true]);

        $pedido = $this->crearPedidoConfirmado();

        $this->assertSame(PedidoMostrador::ESTADO_EN_PREPARACION, $pedido->fresh()->estado_pedido);
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

    // ==================== ORDEN PERSISTIDO KANBAN ====================

    public function test_pedido_nuevo_recibe_orden_kanban_igual_al_id(): void
    {
        $pedido = $this->crearPedidoConfirmado();

        $this->assertSame((int) $pedido->id, (int) $pedido->fresh()->orden_kanban,
            'El hook booted::created debe setear orden_kanban = id para pedidos nuevos');
    }

    public function test_reordenar_columna_asigna_valores_decrecientes_desde_max(): void
    {
        $p1 = $this->crearPedidoConfirmado();
        $p2 = $this->crearPedidoConfirmado();
        $p3 = $this->crearPedidoConfirmado();

        $service = app(PedidoMostradorService::class);

        // Reordenar visualmente: p3 arriba, p1 medio, p2 abajo.
        $service->reordenarColumna(
            sucursalId: $this->sucursalId,
            cajaId: null,
            estado: PedidoMostrador::ESTADO_CONFIRMADO,
            idsOrdenados: [$p3->id, $p1->id, $p2->id],
        );

        $p1->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertGreaterThan($p1->orden_kanban, $p3->orden_kanban,
            'p3 debe tener orden_kanban mayor que p1 (aparece arriba)');
        $this->assertGreaterThan($p2->orden_kanban, $p1->orden_kanban,
            'p1 debe tener orden_kanban mayor que p2');
    }

    public function test_cambiar_estado_resetea_orden_kanban_al_id(): void
    {
        $p1 = $this->crearPedidoConfirmado();
        $p2 = $this->crearPedidoConfirmado();

        $service = app(PedidoMostradorService::class);

        // Reordenar para que p1 quede arriba con orden_kanban alto.
        $service->reordenarColumna(
            sucursalId: $this->sucursalId,
            cajaId: null,
            estado: PedidoMostrador::ESTADO_CONFIRMADO,
            idsOrdenados: [$p1->id, $p2->id],
        );
        $p1->refresh();
        $ordenManual = (int) $p1->orden_kanban;
        $this->assertGreaterThan((int) $p1->id, $ordenManual,
            'Sanity: tras reordenar, orden_kanban deberia ser > id');

        // Cambiar de estado debe resetear el orden al id (default).
        $service->cambiarEstado($p1, PedidoMostrador::ESTADO_LISTO);
        $p1->refresh();

        $this->assertSame((int) $p1->id, (int) $p1->orden_kanban,
            'Al cambiar de columna, orden_kanban debe resetearse a id');
    }

    public function test_reordenar_columna_descarta_ids_de_otra_columna(): void
    {
        $pConfirmado = $this->crearPedidoConfirmado();
        $pListo = $this->crearPedidoConfirmado();
        $pListo->update(['estado_pedido' => PedidoMostrador::ESTADO_LISTO]);

        $service = app(PedidoMostradorService::class);
        $ordenInicialListo = (int) $pListo->orden_kanban;

        // Incluir un id que pertenece a otra columna — debe ser ignorado.
        $service->reordenarColumna(
            sucursalId: $this->sucursalId,
            cajaId: null,
            estado: PedidoMostrador::ESTADO_CONFIRMADO,
            idsOrdenados: [$pConfirmado->id, $pListo->id],
        );

        $this->assertSame($ordenInicialListo, (int) $pListo->fresh()->orden_kanban,
            'Un pedido de otra columna no debe afectarse por reordenarColumna');
    }

    public function test_reordenar_columna_rechaza_estado_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(PedidoMostradorService::class);
        $service->reordenarColumna(
            sucursalId: $this->sucursalId,
            cajaId: null,
            estado: PedidoMostrador::ESTADO_CANCELADO, // no es del Kanban
            idsOrdenados: [1, 2, 3],
        );
    }

    public function test_kanban_query_ordena_por_orden_kanban_desc(): void
    {
        $p1 = $this->crearPedidoConfirmado();
        $p2 = $this->crearPedidoConfirmado();
        $p3 = $this->crearPedidoConfirmado();

        $service = app(PedidoMostradorService::class);

        // Reordenar: p1 primero, p3 ultimo.
        $service->reordenarColumna(
            sucursalId: $this->sucursalId,
            cajaId: null,
            estado: PedidoMostrador::ESTADO_CONFIRMADO,
            idsOrdenados: [$p1->id, $p2->id, $p3->id],
        );

        $componente = Livewire::test(PedidosMostrador::class);
        $kanban = $componente->viewData('pedidosKanban');

        $idsEnColumna = $kanban[PedidoMostrador::ESTADO_CONFIRMADO]->pluck('id')->all();
        $this->assertEquals(
            [$p1->id, $p2->id, $p3->id],
            $idsEnColumna,
            'La query del Kanban debe respetar el orden_kanban DESC (p1 arriba, p3 abajo)'
        );
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
