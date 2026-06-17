<?php

namespace Tests\Feature\Livewire\Fiscal;

use App\Livewire\Fiscal\LibrosIva;
use App\Livewire\Fiscal\PosicionFiscal;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests del módulo Fiscal (Fase 7): que los componentes monten y
 * respondan a los cambios de filtro / export sin error.
 */
class SmokeFiscalTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // is_system_admin=true bypasa el check de permisos del mount().
        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function cuit(): Cuit
    {
        $cond = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'RI']);

        return Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Emisor SA', 'condicion_iva_id' => $cond->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
    }

    public function test_posicion_fiscal_monta(): void
    {
        Livewire::test(PosicionFiscal::class)->assertOk();
    }

    public function test_posicion_fiscal_monta_con_cuit_y_periodo(): void
    {
        $this->cuit();

        Livewire::test(PosicionFiscal::class)
            ->assertOk()
            ->assertSet('periodo', now()->format('Y-m'));
    }

    public function test_libros_iva_monta(): void
    {
        Livewire::test(LibrosIva::class)->assertOk();
    }

    public function test_libros_iva_cambia_de_tab(): void
    {
        $this->cuit();

        Livewire::test(LibrosIva::class)
            ->assertSet('tab', 'ventas')
            ->call('setTab', 'compras')
            ->assertSet('tab', 'compras')
            ->call('setTab', 'ventas')
            ->assertSet('tab', 'ventas');
    }
}
