<?php

namespace Tests\Feature\Livewire\Tesoreria;

use App\Livewire\Tesoreria\GestionTesoreria;
use App\Livewire\Tesoreria\ReportesTesoreria;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

class SmokeTesoreriaTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create();
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gestion_tesoreria_monta(): void
    {
        Livewire::test(GestionTesoreria::class)->assertOk();
    }

    public function test_reportes_tesoreria_monta(): void
    {
        Livewire::test(ReportesTesoreria::class)->assertOk();
    }
}
