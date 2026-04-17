<?php

namespace Tests\Feature\Livewire\Articulos;

use App\Livewire\Articulos\GestionarArticulos;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class GestionarArticulosImportTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

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

        Livewire::test(GestionarArticulos::class)->assertOk();
    }

    public function test_abre_y_cierra_modal_plantilla(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarArticulos::class)
            ->call('openPlantillaModal')
            ->assertSet('showPlantillaModal', true)
            ->call('closePlantillaModal')
            ->assertSet('showPlantillaModal', false);
    }

    public function test_abre_y_cierra_modal_importacion(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarArticulos::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->assertSet('importacionPreview', false)
            ->assertSet('importacionProcesada', false)
            ->call('closeImportModal')
            ->assertSet('showImportModal', false);
    }

    public function test_descargar_plantilla_vacia_retorna_xlsx(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarArticulos::class)
            ->call('descargarPlantilla', false)
            ->assertFileDownloaded('plantilla_articulos.xlsx')
            ->assertSet('showPlantillaModal', false);
    }

    public function test_previsualizar_sin_archivo_falla_validacion(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarArticulos::class)
            ->call('openImportModal')
            ->call('previsualizarImportacion')
            ->assertHasErrors(['archivoImportacion' => 'required']);
    }

    public function test_volver_a_seleccion_resetea_preview(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarArticulos::class)
            ->call('openImportModal')
            ->set('importacionPreview', true)
            ->set('importacionResultado', ['creadas' => 2, 'actualizadas' => 0, 'sin_cambios' => 0, 'errores' => []])
            ->call('volverASeleccion')
            ->assertSet('importacionPreview', false)
            ->assertSet('importacionResultado', []);
    }
}
