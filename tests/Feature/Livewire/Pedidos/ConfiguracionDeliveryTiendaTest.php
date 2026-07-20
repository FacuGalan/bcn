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
 * (RF-T11 + RF-T15): el PADRE es el único escritor de `tiendas.habilitada`.
 * Con el auto-guardado (RF-T15) el switch publica/despublica AL INSTANTE
 * (prenderlo sin tienda la crea y publica en el mismo acto). Sin permiso
 * `func.tienda.config` el switch no crea ni publica. También cubre el
 * auto-guardado del resto de la config (updated → persistirConfig).
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

    public function test_toggle_crea_la_tienda_y_la_publica_al_instante(): void
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
        $this->assertTrue((bool) $tienda->habilitada, 'RF-T15: el toggle publica AL INSTANTE, sin pasar por Guardar');
        $this->assertNotSame('', $tienda->slug);

        $component->assertSet('tiendaPublicadaPersistida', true);
    }

    public function test_apagar_el_switch_despublica_al_instante(): void
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
            ->assertSet('tiendaPublicadaPersistida', false);

        $this->assertFalse((bool) $this->tienda()->habilitada, 'RF-T15: despublica sin pasar por Guardar');
    }

    public function test_autoguardado_persiste_cada_cambio_sin_boton(): void
    {
        // Un checkbox: updated() → persistirConfig() directo a la BD.
        Livewire::test(ConfiguracionDelivery::class)
            ->set('takeawayHabilitado', false)
            ->set('modoPromesa', 'automatica')
            ->set('demoraBaseMin', '25');

        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $config = $sucursal->getConfigDelivery();
        $this->assertFalse((bool) $config['takeaway_habilitado']);
        $this->assertSame('automatica', $config['modo_promesa']);
        $this->assertSame(25, (int) $config['demora_base_min']);
    }

    public function test_repeaters_persisten_al_mutar(): void
    {
        $componente = Livewire::test(ConfiguracionDelivery::class)
            ->set('nuevoFeriado', '2026-12-25')
            ->call('agregarFeriado');

        $config = \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery();
        $this->assertContains('2026-12-25', $config['feriados']);

        $componente->call('quitarFeriado', array_search('2026-12-25', $componente->get('feriados'), true));
        $config = \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery();
        $this->assertNotContains('2026-12-25', (array) $config['feriados']);
    }

    public function test_encargos_toggle_precarga_calendario_de_atencion_y_persiste(): void
    {
        // Calendario de atención con datos distintivos: la PRIMERA activación
        // de encargos lo precarga como punto de partida (RF-T16).
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update(['config_delivery' => array_merge(
            is_array($sucursal->config_delivery) ? $sucursal->config_delivery : [],
            [
                'dias_laborales' => [5, 6, 7],
                'horarios_atencion' => [['dias' => [5, 6, 7], 'desde' => '20:00', 'hasta' => '23:00']],
                'feriados' => ['2026-12-25'],
            ],
        )]);

        $componente = Livewire::test(ConfiguracionDelivery::class)
            ->set('aceptaProgramados', true);

        $config = \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery();
        $this->assertTrue((bool) $config['acepta_programados']);
        $this->assertSame([5, 6, 7], array_map('intval', $config['encargos']['dias_laborales']));
        $this->assertSame('20:00', $config['encargos']['horarios'][0]['desde']);
        $this->assertContains('2026-12-25', $config['encargos']['feriados']);
        $this->assertSame(24, (int) $config['encargos']['anticipacion_horas']);
        $this->assertSame(30, (int) $config['encargos']['max_dias_adelante']);

        // Editar el calendario de encargos y apagar/prender: NO re-precarga
        // (la precarga es SOLO la primera vez, cuando el JSON no tenía
        // `encargos`). Misma instancia: dos Livewire::test en un método
        // pierden la sesión de sucursal (quirk del entorno de test).
        $componente->set('encargosDias.5', false)
            ->set('aceptaProgramados', false)
            ->set('aceptaProgramados', true);

        $config = \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery();
        $this->assertSame([6, 7], array_map('intval', $config['encargos']['dias_laborales']), 'La re-activación no debe re-precargar el calendario editado');
    }

    public function test_autoguardado_sin_permiso_no_escribe(): void
    {
        $original = (bool) \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery()['takeaway_habilitado'];

        $this->actingAs(User::factory()->create());

        Livewire::test(ConfiguracionDelivery::class)
            ->set('takeawayHabilitado', ! $original)
            ->assertDispatched('toast-error');

        $this->assertSame(
            $original,
            (bool) \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery()['takeaway_habilitado'],
        );
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

        // Sin permiso el toggle no cambia nada; guardarConfig ya NO escribe
        // habilitada nunca (RF-T15: la persiste solo el toggle) y además
        // corta antes por falta de permiso delivery.
        Livewire::test(ConfiguracionDelivery::class)
            ->set('tiendaPublicada', false)
            ->call('guardarConfig');

        $this->assertTrue((bool) $this->tienda()->habilitada);
    }
}
