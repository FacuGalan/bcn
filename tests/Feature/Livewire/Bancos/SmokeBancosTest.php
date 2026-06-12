<?php

namespace Tests\Feature\Livewire\Bancos;

use App\Livewire\Bancos\ConciliacionesCuenta;
use App\Livewire\Bancos\GestionCuentas;
use App\Livewire\Bancos\MovimientosCuenta;
use App\Livewire\Bancos\ResumenCuentas;
use App\Livewire\Bancos\TransferenciasCuenta;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

class SmokeBancosTest extends TestCase
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

    public function test_gestion_cuentas_monta(): void
    {
        Livewire::test(GestionCuentas::class)->assertOk();
    }

    public function test_movimientos_cuenta_monta(): void
    {
        Livewire::test(MovimientosCuenta::class)->assertOk();
    }

    public function test_resumen_cuentas_monta(): void
    {
        Livewire::test(ResumenCuentas::class)->assertOk();
    }

    public function test_transferencias_cuenta_monta(): void
    {
        Livewire::test(TransferenciasCuenta::class)->assertOk();
    }

    public function test_conciliaciones_cuenta_monta(): void
    {
        Livewire::test(ConciliacionesCuenta::class)->assertOk();
    }
}
