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
            ->call('edit', $articulo->id);

        // El sugerido depende de la alícuota efectiva (si otro test dejó un
        // CUIT RI activo, incluye el ×1,21): el assert es contra la cuenta
        // calculada, no un valor fijo (patrón del test de RevisionPrecios).
        $sugerido = (float) $editor->instance()->cuentaSugerida()['sugerido'];
        $this->assertGreaterThanOrEqual(150.0, $sugerido); // 100 × 1,5 como piso

        $editor->call('aplicarPrecioSugerido')->assertOk();

        // Va al campo del modal (RF-B4: en edición SIEMPRE el precio efectivo
        // de la sucursal), no a la BD todavía.
        $this->assertEquals($sugerido, (float) $editor->get('precio_sucursal'));
        $this->assertNull($this->precioSucursalDe($articulo->id));

        $editor->call('save')->assertOk();
        $this->assertEquals($sugerido, (float) $this->precioSucursalDe($articulo->id));
    }

    /**
     * RF-B4 (hardening-circuito-precios): en mono-sucursal, si un masivo creó
     * un override en articulos_sucursales, el ABM muestra y edita ESE precio
     * (el que la venta cobra), no el precio_base muerto.
     */
    public function test_gestionar_articulos_mono_sucursal_edita_precio_efectivo(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', [
            'precio_base' => 100,
        ]);

        // Override como el que deja el cambio masivo de precios.
        \Illuminate\Support\Facades\DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['precio_base' => 150]);

        $editor = Livewire::test(GestionarArticulos::class)
            ->call('edit', $articulo->id);

        // El modal carga el precio EFECTIVO (150, no el base 100).
        $this->assertEquals(150.0, (float) $editor->get('precio_sucursal'));

        $editor->set('precio_sucursal', 180)->call('save')->assertOk();

        // Se actualizó el override (lo que la venta cobra); el base no revive.
        $this->assertEquals(180.0, (float) $this->precioSucursalDe($articulo->id));
        $this->assertEquals(100.0, (float) $articulo->fresh()->precio_base);
    }

    private function precioSucursalDe(int $articuloId): ?float
    {
        $valor = \Illuminate\Support\Facades\DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $this->sucursalId)
            ->value('precio_base');

        return $valor !== null ? (float) $valor : null;
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
