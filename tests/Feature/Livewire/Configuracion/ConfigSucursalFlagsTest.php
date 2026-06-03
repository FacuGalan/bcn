<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionEmpresa;
use App\Models\Caja;
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

    private function crearCajaConPantalla(): Caja
    {
        return Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja PC',
            'codigo' => 'CPC',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
            'usa_pantalla_cliente' => true,
        ]);
    }

    public function test_usa_pantalla_cliente_helper_refleja_cajas(): void
    {
        $sucursal = Sucursal::find($this->sucursalId);
        $this->assertFalse($sucursal->usaPantallaCliente());

        $this->crearCajaConPantalla();
        $this->assertTrue(Sucursal::find($this->sucursalId)->usaPantallaCliente());
    }

    public function test_abrir_personalizar_pantalla_carga_defaults_desde_sucursal(): void
    {
        // Sin config guardada → deben llegar los DEFAULTS mergeados.
        Sucursal::where('id', $this->sucursalId)->update(['config_pantalla_cliente' => null]);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirPersonalizarPantalla', $this->sucursalId)
            ->assertSet('mostrarModalPersonalizarPantalla', true)
            ->assertSet('pcSucursalId', $this->sucursalId)
            ->assertSet('pcColorFondo', Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS['color_fondo'])
            ->assertSet('pcAnimacion', Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS['animacion'])
            ->assertSet('pcColorTexto', Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS['color_texto']);
    }

    public function test_abrir_personalizar_pantalla_carga_config_guardada(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_pantalla_cliente' => [
                'mostrar_logo' => false,
                'color_fondo' => '#101010',
                'animacion' => 'respiracion',
                'color_acento' => '#ff8800',
                'mensaje_idle' => 'Acercá tu celu',
            ],
        ]);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirPersonalizarPantalla', $this->sucursalId)
            ->assertSet('pcMostrarLogo', false)
            ->assertSet('pcColorFondo', '#101010')
            ->assertSet('pcAnimacion', 'respiracion')
            ->assertSet('pcColorAcento', '#ff8800')
            ->assertSet('pcMensajeIdle', 'Acercá tu celu')
            // las keys ausentes vienen de los defaults
            ->assertSet('pcTamanoLogo', Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS['tamano_logo']);
    }

    public function test_guardar_personalizar_pantalla_persiste_en_sucursal(): void
    {
        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirPersonalizarPantalla', $this->sucursalId)
            ->set('pcMostrarLogo', false)
            ->set('pcMostrarNombre', true)
            ->set('pcColorFondo', '#123456')
            ->set('pcAnimacion', 'aurora')
            ->set('pcColorAcento', '#abcdef')
            ->set('pcColorTexto', 'auto')
            ->set('pcMensajeIdle', 'Listo para pagar')
            ->set('pcTamanoLogo', 'lg')
            ->call('guardarPersonalizarPantalla')
            ->assertSet('mostrarModalPersonalizarPantalla', false)
            ->assertHasNoErrors();

        $config = Sucursal::find($this->sucursalId)->getConfigPantallaCliente();
        $this->assertFalse($config['mostrar_logo']);
        $this->assertSame('#123456', $config['color_fondo']);
        $this->assertSame('aurora', $config['animacion']);
        $this->assertSame('#abcdef', $config['color_acento']);
        $this->assertSame('lg', $config['tamano_logo']);
        $this->assertSame('Listo para pagar', $config['mensaje_idle']);
    }

    public function test_guardar_personalizar_pantalla_rechaza_color_invalido(): void
    {
        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirPersonalizarPantalla', $this->sucursalId)
            ->set('pcColorFondo', 'no-es-hex')
            ->call('guardarPersonalizarPantalla')
            ->assertHasErrors(['pcColorFondo']);
    }
}
