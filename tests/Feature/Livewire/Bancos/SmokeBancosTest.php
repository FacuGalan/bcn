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

    public function test_gestion_cuentas_persiste_cuit(): void
    {
        $moneda = \App\Models\Moneda::firstOrCreate(
            ['codigo' => 'ARS'],
            ['nombre' => 'Peso', 'simbolo' => '$', 'es_principal' => true, 'decimales' => 2, 'activo' => true, 'orden' => 1]
        );
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );

        Livewire::test(GestionCuentas::class)
            ->call('crear')
            ->set('nombre', 'Cuenta con CUIT')
            ->set('tipo', 'banco')
            ->set('moneda_id', $moneda->id)
            ->set('cuit_id', $cuit->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cuentas_empresa', [
            'nombre' => 'Cuenta con CUIT',
            'cuit_id' => $cuit->id,
        ], 'pymes_tenant');
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
