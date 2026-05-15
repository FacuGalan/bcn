<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionEmpresa;
use App\Models\Sucursal;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Verifica que el modal de configuración de sucursal persista los flags de
 * Pedidos Mostrador (conversión auto, beepers, comanda automática) que antes
 * solo eran editables por DB.
 */
class ConfigSucursalFlagsTest extends TestCase
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

        // Bypass cache SucursalService (mismo patrón que SmokePedidosTest).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        $p = $ref->getProperty('sucursalIdsCache');
        $p->setAccessible(true);
        $p->setValue(null, [0]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_abrir_modal_carga_flags_pedidos_mostrador_desde_sucursal(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'pedido_conversion_automatica_al_entregar' => true,
            'usa_beepers' => true,
            'imprime_comanda_automatico' => false,
        ]);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirConfigSucursal', $this->sucursalId)
            ->assertSet('configPedidoConversionAutomaticaAlEntregar', true)
            ->assertSet('configUsaBeepers', true)
            ->assertSet('configImprimeComandaAutomatico', false)
            ->assertSet('mostrarModalConfigSucursal', true);
    }

    public function test_guardar_modal_persiste_los_3_flags(): void
    {
        // Arrancar con los 3 en false en BD.
        Sucursal::where('id', $this->sucursalId)->update([
            'pedido_conversion_automatica_al_entregar' => false,
            'usa_beepers' => false,
            'imprime_comanda_automatico' => false,
        ]);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirConfigSucursal', $this->sucursalId)
            ->set('configPedidoConversionAutomaticaAlEntregar', true)
            ->set('configUsaBeepers', true)
            ->set('configImprimeComandaAutomatico', true)
            ->call('guardarConfigSucursal');

        $sucursal = Sucursal::find($this->sucursalId);
        $this->assertTrue((bool) $sucursal->pedido_conversion_automatica_al_entregar);
        $this->assertTrue((bool) $sucursal->usa_beepers);
        $this->assertTrue((bool) $sucursal->imprime_comanda_automatico);
    }
}
