<?php

namespace Tests\Feature\Livewire\Pedidos;

use App\Livewire\Pedidos\ConfiguracionDelivery;
use App\Models\Tienda;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Switch maestro del apartado Tienda Online en ConfiguracionDelivery
 * (RF-T11): el PADRE es el único escritor de `tiendas.habilitada` —
 * prender el switch crea la tienda (despublicada), guardar publica,
 * apagar + guardar despublica. Sin permiso `func.tienda.config` el
 * switch no crea ni publica.
 */
class ConfiguracionDeliveryTiendaTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Reset del cache estático de SucursalService (mismo patrón que
        // SmokeConfiguracionTest; ver memoria bypass-sucursal-service-cache).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, null);
        }

        Livewire::withoutLazyLoading();

        // La tabla config.tiendas persiste entre corridas: estado inicial limpio.
        Tienda::where('comercio_id', $this->comercio->id)->delete();
    }

    protected function tearDown(): void
    {
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function tienda(): ?Tienda
    {
        return Tienda::where('comercio_id', $this->comercio->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();
    }

    public function test_toggle_crea_tienda_despublicada_y_despliega(): void
    {
        $component = Livewire::test(ConfiguracionDelivery::class)
            ->assertSet('tiendaExiste', false)
            ->assertSet('tiendaPublicada', false)
            ->call('toggleTiendaOnline')
            ->assertOk()
            ->assertSet('tiendaExiste', true)
            ->assertSet('tiendaPublicada', true);

        $tienda = $this->tienda();
        $this->assertNotNull($tienda);
        $this->assertFalse((bool) $tienda->habilitada, 'El toggle crea la tienda DESPUBLICADA; publica recién el guardado');
        $this->assertNotSame('', $tienda->slug);

        $component->assertSet('tiendaPublicadaPersistida', false);
    }

    public function test_guardar_con_switch_prendido_publica(): void
    {
        Livewire::test(ConfiguracionDelivery::class)
            ->call('toggleTiendaOnline')
            ->call('guardarConfig')
            ->assertOk()
            ->assertSet('tiendaPublicadaPersistida', true);

        $this->assertTrue((bool) $this->tienda()->habilitada);
    }

    public function test_apagar_switch_y_guardar_despublica(): void
    {
        Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'slug' => 'tienda-switch-test',
            'habilitada' => true,
        ]);

        Livewire::test(ConfiguracionDelivery::class)
            ->assertSet('tiendaExiste', true)
            ->assertSet('tiendaPublicada', true)
            ->call('toggleTiendaOnline')
            ->assertSet('tiendaPublicada', false)
            ->call('guardarConfig')
            ->assertOk();

        $this->assertFalse((bool) $this->tienda()->habilitada);
    }

    public function test_sin_permiso_no_crea_ni_publica(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ConfiguracionDelivery::class)
            ->call('toggleTiendaOnline')
            ->assertSet('tiendaExiste', false)
            ->assertSet('tiendaPublicada', false);

        $this->assertNull($this->tienda(), 'Sin func.tienda.config el switch no crea la tienda');
    }

    public function test_guardar_sin_permiso_tienda_no_toca_habilitada(): void
    {
        Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'slug' => 'tienda-perm-test',
            'habilitada' => true,
        ]);

        $this->actingAs(User::factory()->create());

        // Sin permiso el toggle no cambia nada y aunque la prop difiriera,
        // guardarConfig no escribe habilitada (doble defensa server-side).
        // guardarConfig igual corta antes por falta de permiso delivery.
        Livewire::test(ConfiguracionDelivery::class)
            ->set('tiendaPublicada', false)
            ->call('guardarConfig');

        $this->assertTrue((bool) $this->tienda()->habilitada);
    }
}
