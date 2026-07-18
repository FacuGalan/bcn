<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionTienda;
use App\Models\Tienda;
use App\Models\User;
use App\Services\ImagenTiendaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Logo y portada de la tienda online (RF-T11): upload vía Livewire con
 * re-encode WebP de ImagenTiendaService, reemplazo y eliminación. La
 * seguridad del service (MIME real, tamaño) se prueba directo contra él.
 */
class ConfiguracionTiendaImagenesTest extends TestCase
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
            'slug' => 'tienda-imagenes-test',
            'habilitada' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_guardar_procesa_logo_y_portada_como_webp(): void
    {
        Livewire::test(ConfiguracionTienda::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 400, 400))
            ->set('portadaUpload', UploadedFile::fake()->image('portada.jpg', 2000, 1200))
            ->call('guardarTienda')
            ->assertHasNoErrors();

        $this->tienda->refresh();
        $this->assertNotNull($this->tienda->logo_path);
        $this->assertNotNull($this->tienda->portada_path);
        $this->assertStringEndsWith('.webp', $this->tienda->logo_path);
        $this->assertStringStartsWith("tiendas/{$this->comercio->id}/", $this->tienda->logo_path);
        Storage::disk('public')->assertExists($this->tienda->logo_path);
        Storage::disk('public')->assertExists($this->tienda->portada_path);
    }

    public function test_reemplazo_borra_la_imagen_anterior(): void
    {
        $service = app(ImagenTiendaService::class);
        $primera = $service->actualizarLogo($this->tienda, UploadedFile::fake()->image('uno.png', 300, 300));
        $segunda = $service->actualizarLogo($this->tienda->fresh(), UploadedFile::fake()->image('dos.png', 300, 300));

        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);
    }

    public function test_eliminar_logo_borra_archivo_y_campo(): void
    {
        $path = app(ImagenTiendaService::class)
            ->actualizarLogo($this->tienda, UploadedFile::fake()->image('logo.png', 300, 300));

        Livewire::test(ConfiguracionTienda::class)
            ->call('eliminarLogo');

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->tienda->fresh()->logo_path);
    }

    public function test_service_rechaza_archivo_no_imagen(): void
    {
        $this->expectExceptionMessage(__('Formato de imagen no permitido. Aceptados: JPG, PNG, WebP.'));

        app(ImagenTiendaService::class)->actualizarLogo(
            $this->tienda,
            UploadedFile::fake()->create('malicioso.svg', 10, 'image/svg+xml'),
        );
    }

    public function test_service_rechaza_archivo_gigante(): void
    {
        $this->expectExceptionMessage(__('La imagen supera el tamaño máximo permitido (:max MB).', ['max' => 5]));

        // 6MB de "imagen": el límite se chequea ANTES del MIME.
        app(ImagenTiendaService::class)->actualizarLogo(
            $this->tienda,
            UploadedFile::fake()->create('grande.png', 6 * 1024, 'image/png'),
        );
    }
}
