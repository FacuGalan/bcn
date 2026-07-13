<?php

namespace Tests\Feature\Livewire\Articulos;

use App\Livewire\Articulos\AsignarEtiquetas;
use App\Livewire\Articulos\AsignarOpcionales;
use App\Livewire\Articulos\CambioMasivoPrecios;
use App\Livewire\Articulos\GestionarArticulos;
use App\Livewire\Articulos\GestionarEtiquetas;
use App\Livewire\Articulos\GestionarGruposOpcionales;
use App\Livewire\Articulos\GestionarRecetas;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Smoke tests: cada componente debe montar sin error.
 *
 * Detecta: errores de mount(), syntax errors en Blade, variables no definidas,
 * dependencias rotas (services no resolvibles), etc. NO testea logica de UI.
 *
 * Componentes ya cubiertos por otros tests:
 * - GestionarArticulos: GestionarArticulosImportTest
 * - GestionarCategorias: GestionarCategoriasTest
 */
class SmokeArticulosTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create();
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_asignar_etiquetas_monta(): void
    {
        Livewire::test(AsignarEtiquetas::class)->assertOk();
    }

    public function test_asignar_opcionales_monta(): void
    {
        Livewire::test(AsignarOpcionales::class)->assertOk();
    }

    public function test_cambio_masivo_precios_monta(): void
    {
        Livewire::test(CambioMasivoPrecios::class)->assertOk();
    }

    public function test_gestionar_etiquetas_monta(): void
    {
        Livewire::test(GestionarEtiquetas::class)->assertOk();
    }

    public function test_gestionar_grupos_opcionales_monta(): void
    {
        Livewire::test(GestionarGruposOpcionales::class)->assertOk();
    }

    public function test_gestionar_recetas_monta(): void
    {
        Livewire::test(GestionarRecetas::class)->assertOk();
    }

    /**
     * Fase 7 (spec compras-costos): el modal de edición renderiza con la
     * sección de costos/utilidad y el historial de costos abre sin error.
     */
    public function test_gestionar_articulos_edit_con_costos_monta(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        Livewire::test(GestionarArticulos::class)
            ->call('edit', $articulo->id)
            ->assertSet('showModal', true)
            ->call('verHistorialCostos', $articulo->id)
            ->assertOk();
    }
}
