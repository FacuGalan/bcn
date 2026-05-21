<?php

namespace Tests\Feature\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Livewire\Ventas\Ventas;
use App\Models\User;
use App\Models\Venta;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Smoke tests: garantizan que los componentes Livewire de Ventas montan sin errores.
 * Detecta problemas como imports faltantes en traits, errores de mount, sintaxis Blade, etc.
 */
class SmokeVentasTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->crearFormaPagoEfectivo();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
        ]);

        // Bypass del cache de SucursalService (mismo patrón que SmokePedidosTest).
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

    public function test_ventas_monta(): void
    {
        Livewire::test(Ventas::class)->assertOk();
    }

    public function test_nueva_venta_monta(): void
    {
        Livewire::test(NuevaVenta::class)->assertOk();
    }

    public function test_agregar_por_codigo_inexistente_dispatch_toast(): void
    {
        // Repaso 1 — M2: el scanner antes retornaba en silencio si el código
        // no existía. Ahora dispatchea toast-warning para que el cajero sepa.
        Livewire::test(NuevaVenta::class)
            ->call('agregarPorCodigo', 'CODIGO-INEXISTENTE-XYZ')
            ->assertDispatched('toast-warning');
    }

    public function test_modal_global_invitar_todo_marca_items_sin_persistir(): void
    {
        // Fase 7: botón "Invitar" en la vista principal (al lado de Descuentos)
        // abre un mini-modal con textarea de motivo. Confirmar marca todos los
        // items como cortesía pero NO persiste — la persistencia llega cuando
        // el usuario aprieta procesarVenta.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->assertSet('mostrarModalInvitarTodo', true)
            ->assertSet('motivoInvitacionTotal', '')
            ->set('motivoInvitacionTotal', 'Cortesía gerencia')
            ->call('confirmarInvitarTodo')
            ->assertSet('mostrarModalInvitarTodo', false);

        $items = $componente->get('items');
        $this->assertTrue((bool) $items[0]['es_invitacion']);
        $this->assertSame('Cortesía gerencia', $items[0]['invitacion_motivo']);
        $this->assertEqualsWithDelta(0.0, (float) $items[0]['precio'], 0.01);
        $this->assertSame(0, Venta::count(),
            'El mini-modal global solo marca en memoria, no persiste');
    }

    public function test_modal_global_invitar_todo_rechaza_sin_motivo(): void
    {
        // El motivo es obligatorio (defensa en backend además del @disabled UI).
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', '   ')
            ->call('confirmarInvitarTodo')
            ->assertDispatched('toast-error')
            ->assertSet('mostrarModalInvitarTodo', true);

        $items = $componente->get('items');
        $this->assertFalse((bool) ($items[0]['es_invitacion'] ?? false));
    }

    public function test_desinvitar_todos_revierte_venta_completa(): void
    {
        // Botón "Cortesía" abre modal de confirmación que llama a desinvitarTodos()
        // y restaura los precios originales de todos los items.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', 'Evento VIP')
            ->call('confirmarInvitarTodo');

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

    public function test_confirmar_invitacion_total_persiste_venta_sin_pagos(): void
    {
        // Fase 7 end-to-end: invitar todo desde el mini-modal global + procesarVenta
        // persiste una venta con total_final=0, sin VentaPago, sin movimiento de
        // caja y con las columnas de cortesía completas en cabecera y detalles.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalInvitarTodo')
            ->set('motivoInvitacionTotal', 'Evento corporativo')
            ->call('confirmarInvitarTodo')
            ->call('confirmarInvitacionTotal');

        $venta = Venta::with('detalles')->first();
        $this->assertNotNull($venta);
        $this->assertTrue((bool) $venta->es_invitacion_total);
        $this->assertSame('Evento corporativo', $venta->invitacion_motivo);
        $this->assertNotNull($venta->invitado_por_usuario_id);
        $this->assertNotNull($venta->invitado_at);
        $this->assertEqualsWithDelta(0.0, (float) $venta->total_final, 0.01);
        $this->assertGreaterThan(0, (float) $venta->total_invitado);
        $this->assertSame(0, $venta->pagos()->count(),
            'Venta invitada no debe tener VentaPago');

        $detalle = $venta->detalles->first();
        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertSame('Evento corporativo', $detalle->invitacion_motivo);
        $this->assertGreaterThan(0, (float) $detalle->monto_invitado);
    }
}
