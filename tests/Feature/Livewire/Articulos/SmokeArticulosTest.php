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

    /**
     * "Usar como precio": copia el sugerido al campo del modal (alcance global
     * si no hay override de sucursal) y Guardar lo persiste por el camino normal.
     */
    public function test_gestionar_articulos_aplica_precio_sugerido(): void
    {
        // El sugerido está gateado por func.costos.ver: system admin lo tiene todo.
        $this->actingAs(User::factory()->create(['is_system_admin' => true]));

        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', [
            'precio_base' => 100,
            'utilidad_porcentaje' => 50,
        ]);
        \App\Models\ArticuloCosto::create([
            'articulo_id' => $articulo->id,
            'sucursal_id' => $this->sucursalId,
            'costo_ultimo' => 100,
            'fecha_costo_ultimo' => now(),
        ]);

        $editor = Livewire::test(GestionarArticulos::class)
            ->call('edit', $articulo->id)
            ->call('aplicarPrecioSugerido')
            ->assertOk();

        // Sin CUIT RI en la sucursal la alícuota efectiva es 0:
        // sugerido = 100 × (1 + 50%) = 150 → va al campo, no a la BD todavía.
        $this->assertEquals(150.0, (float) $editor->get('precio_base'));
        $this->assertEquals(100.0, (float) $articulo->fresh()->precio_base);

        $editor->call('save')->assertOk();
        $this->assertEquals(150.0, (float) $articulo->fresh()->precio_base);
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
