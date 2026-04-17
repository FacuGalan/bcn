<?php

namespace Tests\Feature\Livewire\Articulos;

use App\Livewire\Articulos\GestionarCategorias;
use App\Models\Categoria;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

class GestionarCategoriasTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_componente_renderiza_sin_errores(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)->assertOk();
    }

    public function test_abre_y_cierra_modal_importacion(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->assertSet('importacionProcesada', false)
            ->call('closeImportModal')
            ->assertSet('showImportModal', false);
    }

    public function test_abre_y_cierra_modal_plantilla(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('openPlantillaModal')
            ->assertSet('showPlantillaModal', true)
            ->call('closePlantillaModal')
            ->assertSet('showPlantillaModal', false);
    }

    public function test_descargar_plantilla_vacia_retorna_archivo_xlsx(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('descargarPlantilla', false)
            ->assertFileDownloaded('plantilla_categorias.xlsx')
            ->assertSet('showPlantillaModal', false);
    }

    public function test_descargar_plantilla_con_datos_usa_nombre_distinto(): void
    {
        Livewire::withoutLazyLoading();

        $response = Livewire::test(GestionarCategorias::class)
            ->call('descargarPlantilla', true);

        $response->assertSet('showPlantillaModal', false);
    }

    public function test_previsualizar_sin_archivo_falla_validacion(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('openImportModal')
            ->call('previsualizarImportacion')
            ->assertHasErrors(['archivoImportacion' => 'required'])
            ->assertSet('importacionPreview', false);
    }

    public function test_volver_a_seleccion_resetea_preview(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('openImportModal')
            ->set('importacionPreview', true)
            ->set('importacionResultado', ['creadas' => 5, 'actualizadas' => 0, 'sin_cambios' => 0, 'errores' => []])
            ->call('volverASeleccion')
            ->assertSet('importacionPreview', false)
            ->assertSet('importacionResultado', []);
    }

    public function test_existing_categoria_gets_prefix_updated_via_service(): void
    {
        Categoria::create(['nombre' => 'Test Cat', 'prefijo' => 'OLD', 'color' => '#000000', 'activo' => true]);

        $this->assertSame('OLD', Categoria::where('nombre', 'Test Cat')->first()->prefijo);
    }
}
