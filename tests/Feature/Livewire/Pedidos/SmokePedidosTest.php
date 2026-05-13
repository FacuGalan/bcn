<?php

namespace Tests\Feature\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoMostrador;
use App\Livewire\Pedidos\PedidosMostrador;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests del módulo Pedidos por Mostrador.
 *
 * Verifica que los componentes Livewire monten sin errores: detecta fallas en
 * mount, sintaxis Blade inválida, variables indefinidas, dependencias rotas.
 */
class SmokePedidosTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

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
}
