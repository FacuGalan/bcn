<?php

namespace Tests\Feature\Livewire\Clientes;

use App\Livewire\Clientes\GestionarClientes;
use App\Livewire\Clientes\GestionarCobranzas;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

class SmokeClientesTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();

        $user = User::factory()->create();
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
        ]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gestionar_clientes_monta(): void
    {
        Livewire::test(GestionarClientes::class)->assertOk();
    }

    public function test_gestionar_cobranzas_monta(): void
    {
        Livewire::test(GestionarCobranzas::class)->assertOk();
    }
}
