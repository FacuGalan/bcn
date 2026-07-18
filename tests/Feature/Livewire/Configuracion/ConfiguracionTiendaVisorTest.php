<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionTienda;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Visor en vivo de la tienda (RF-T12): con la tienda publicada PERSISTIDA el
 * render embebe el iframe real con ?preview=1; despublicada cae al mock. El
 * canal de imágenes/guardado hacia el visor son eventos Livewire.
 */
class ConfiguracionTiendaVisorTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected Tienda $tienda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, null);
        }

        Livewire::withoutLazyLoading();
        Storage::fake('public');

        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tienda = Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'slug' => 'tienda-visor-test',
            'habilitada' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_publicada_persistida_embebe_el_iframe_real_con_preview(): void
    {
        Livewire::test(ConfiguracionTienda::class, ['publicadaPersistida' => true])
            ->assertOk()
            ->assertSee('?preview=1', false)
            ->assertSee(config('tienda.url').'/tienda/tienda-visor-test', false)
            ->assertSee(__('Tu tienda en vivo'));
    }

    public function test_despublicada_muestra_el_mock_de_fallback(): void
    {
        Livewire::test(ConfiguracionTienda::class, ['publicadaPersistida' => false])
            ->assertOk()
            ->assertDontSee('?preview=1', false)
            ->assertSee(__('Vista previa (simulación)'));
    }

    public function test_subir_logo_emite_el_evento_de_imagenes_del_visor(): void
    {
        Livewire::test(ConfiguracionTienda::class, ['publicadaPersistida' => true])
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 300, 300))
            ->assertDispatched('tienda-preview-imagenes');
    }

    public function test_eliminar_logo_emite_el_evento_de_imagenes_del_visor(): void
    {
        app(\App\Services\ImagenTiendaService::class)
            ->actualizarLogo($this->tienda, UploadedFile::fake()->image('logo.png', 300, 300));

        Livewire::test(ConfiguracionTienda::class, ['publicadaPersistida' => true])
            ->call('eliminarLogo')
            ->assertDispatched('tienda-preview-imagenes');
    }

    public function test_guardar_emite_tienda_guardada_para_recargar_el_iframe(): void
    {
        Livewire::test(ConfiguracionTienda::class, ['publicadaPersistida' => true])
            ->call('guardarTienda')
            ->assertHasNoErrors()
            ->assertDispatched('tienda-guardada');
    }
}
