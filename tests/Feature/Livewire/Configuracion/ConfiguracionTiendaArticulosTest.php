<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionTiendaArticulos;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Config de tienda POR ARTÍCULO (RF-T14): guardado inmediato de destacado,
 * badges, galería y orden desde el panel. La seguridad del pipeline de
 * imágenes vive en ImagenArticuloTiendaServiceTest; acá se cubre el
 * componente (permiso, scoping por visibilidad y persistencia).
 */
class ConfiguracionTiendaArticulosTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Storage::fake('public');
        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_toggle_destacado_persiste_al_instante(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $this->assertFalse((bool) $articulo->destacado);

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('toggleDestacado', $articulo->id)
            ->assertDispatched('tienda-catalogo-cambiado');

        $this->assertTrue((bool) $articulo->fresh()->destacado);
    }

    public function test_no_toca_articulos_fuera_de_la_tienda(): void
    {
        $oculto = $this->crearArticuloConStock($this->sucursalId);
        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $oculto->id)->update(['visible_tienda' => false]);

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('toggleDestacado', $oculto->id)
            ->assertNotDispatched('tienda-catalogo-cambiado');

        $this->assertFalse((bool) $oculto->fresh()->destacado, 'Un artículo no visible en tienda no debe poder tocarse desde acá');
    }

    public function test_badges_toggle_y_custom_persisten_con_maximo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $componente = Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('abrirEditor', $articulo->id)
            ->call('toggleBadge', 'sin_tacc')
            ->call('toggleBadge', 'vegano')
            ->call('toggleBadge', 'picante')
            ->set('badgeCustom', 'De la casa');

        $this->assertSame([
            ['tipo' => 'sin_tacc', 'texto' => null],
            ['tipo' => 'vegano', 'texto' => null],
            ['tipo' => 'picante', 'texto' => null],
            ['tipo' => 'custom', 'texto' => 'De la casa'],
        ], $articulo->fresh()->badgesTienda());

        // 5º badge: rechazado (máximo 4), no persiste.
        $componente->call('toggleBadge', 'nuevo');
        $this->assertCount(4, $articulo->fresh()->badgesTienda());

        // Destildar libera el cupo; un tipo inválido se ignora.
        $componente->call('toggleBadge', 'picante')
            ->call('toggleBadge', 'inexistente');
        $tipos = collect($articulo->fresh()->badgesTienda())->pluck('tipo');
        $this->assertFalse($tipos->contains('picante'));
        $this->assertFalse($tipos->contains('inexistente'));
    }

    public function test_galeria_sube_quita_y_reordena(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $componente = Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('abrirEditor', $articulo->id)
            ->set('fotosUpload', [
                UploadedFile::fake()->image('a.jpg', 400, 300),
                UploadedFile::fake()->image('b.jpg', 400, 300),
            ])
            ->assertDispatched('tienda-catalogo-cambiado');

        $fotos = $articulo->fresh()->imagenesTienda;
        $this->assertCount(2, $fotos);
        Storage::disk('public')->assertExists($fotos[0]->path);

        // Reordenar por drag & drop (payload de SortableJS: ids en orden final).
        $componente->call('reordenarFotos', [$fotos[1]->id, $fotos[0]->id]);
        $this->assertSame(
            [$fotos[1]->id, $fotos[0]->id],
            $articulo->fresh()->imagenesTienda->pluck('id')->all(),
        );

        $componente->call('quitarFoto', $fotos[0]->id);
        $this->assertCount(1, $articulo->fresh()->imagenesTienda);
        Storage::disk('public')->assertMissing($fotos[0]->path);
    }

    public function test_reordenar_articulos_renumera_e_ignora_ajenos(): void
    {
        $a = $this->crearArticuloConStock($this->sucursalId);
        $b = $this->crearArticuloConStock($this->sucursalId);
        $oculto = $this->crearArticuloConStock($this->sucursalId);
        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $oculto->id)->update(['visible_tienda' => false]);
        $ordenOculto = (int) $oculto->fresh()->orden;

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('reordenarArticulos', [$b->id, $oculto->id, $a->id]);

        $this->assertSame(10, (int) $b->fresh()->orden);
        $this->assertSame(20, (int) $a->fresh()->orden);
        $this->assertSame($ordenOculto, (int) $oculto->fresh()->orden, 'El no visible se ignora aunque venga en el payload');
    }

    public function test_reordenar_categorias_renumera(): void
    {
        $cat1 = Categoria::create(['nombre' => 'Pizzas RF14', 'activo' => true, 'orden' => 1]);
        $cat2 = Categoria::create(['nombre' => 'Bebidas RF14', 'activo' => true, 'orden' => 2]);

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('reordenarCategorias', [$cat2->id, $cat1->id]);

        $this->assertSame(10, (int) $cat2->fresh()->orden);
        $this->assertSame(20, (int) $cat1->fresh()->orden);
    }

    public function test_sin_permiso_no_escribe(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        // Usuario común sin permisos funcionales (no system admin).
        $this->actingAs(User::factory()->create(['is_system_admin' => false]));

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->call('toggleDestacado', $articulo->id)
            ->assertDispatched('toast-error');

        $this->assertFalse((bool) $articulo->fresh()->destacado);
    }

    public function test_render_agrupa_por_categoria_en_orden_de_tienda(): void
    {
        $cat = Categoria::create(['nombre' => 'Con categoria RF14', 'activo' => true, 'orden' => 1]);
        $conCat = $this->crearArticuloConStock($this->sucursalId);
        $conCat->update(['categoria_id' => $cat->id]);
        $sinCat = $this->crearArticuloConStock($this->sucursalId);

        Livewire::test(ConfiguracionTiendaArticulos::class)
            ->assertOk()
            ->assertSee('Con categoria RF14')
            ->assertSee('Sin categoría')
            ->assertSee($conCat->nombre)
            ->assertSee($sinCat->nombre);
    }
}
