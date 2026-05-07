<?php

namespace Tests\Feature\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Livewire\Ventas\Ventas;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests: garantizan que los componentes Livewire de Ventas montan sin errores.
 * Detecta problemas como imports faltantes en traits, errores de mount, sintaxis Blade, etc.
 */
class SmokeVentasTest extends TestCase
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

    public function test_ventas_monta(): void
    {
        Livewire::test(Ventas::class)->assertOk();
    }

    public function test_nueva_venta_monta(): void
    {
        Livewire::test(NuevaVenta::class)->assertOk();
    }
}
