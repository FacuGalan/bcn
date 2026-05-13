<?php

namespace Tests\Feature\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoMostrador;
use App\Livewire\Pedidos\PedidosMostrador;
use App\Models\PedidoMostrador;
use App\Models\User;
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

        $user = User::factory()->create();
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

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
}
