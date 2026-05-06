<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CajaSelector;
use App\Livewire\ComercioSelector;
use App\Livewire\Componentes\SimuladorVenta;
use App\Livewire\Compras\Compras;
use App\Livewire\Cupones\GestionCupones;
use App\Livewire\Puntos\ProgramaPuntos;
use App\Livewire\SucursalSelector;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests de componentes globales y selectores.
 * No requieren caja activa. Algunos no requieren sucursal pero la dejamos
 * activa por consistencia (no rompe).
 */
class SmokeGlobalesTest extends TestCase
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

    public function test_caja_selector_monta(): void
    {
        Livewire::test(CajaSelector::class)->assertOk();
    }

    public function test_sucursal_selector_monta(): void
    {
        Livewire::test(SucursalSelector::class)->assertOk();
    }

    public function test_comercio_selector_monta(): void
    {
        Livewire::test(ComercioSelector::class)->assertOk();
    }

    public function test_simulador_venta_monta(): void
    {
        Livewire::test(SimuladorVenta::class)->assertOk();
    }

    public function test_compras_monta(): void
    {
        Livewire::test(Compras::class)->assertOk();
    }

    public function test_gestion_cupones_monta(): void
    {
        Livewire::test(GestionCupones::class)->assertOk();
    }

    public function test_programa_puntos_monta(): void
    {
        Livewire::test(ProgramaPuntos::class)->assertOk();
    }
}
