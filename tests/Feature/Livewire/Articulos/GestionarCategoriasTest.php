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

    public function test_descargar_plantilla_retorna_archivo_xlsx(): void
    {
        Livewire::withoutLazyLoading();

        $response = Livewire::test(GestionarCategorias::class)
            ->call('descargarPlantilla');

        $response->assertFileDownloaded('plantilla_categorias.xlsx');
    }

    public function test_importar_sin_archivo_falla_validacion(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(GestionarCategorias::class)
            ->call('openImportModal')
            ->call('importarCategorias')
            ->assertHasErrors(['archivoImportacion' => 'required']);
    }

    public function test_existing_categoria_gets_prefix_updated_via_service(): void
    {
        Categoria::create(['nombre' => 'Test Cat', 'prefijo' => 'OLD', 'color' => '#000000', 'activo' => true]);

        $this->assertSame('OLD', Categoria::where('nombre', 'Test Cat')->first()->prefijo);
    }
}
