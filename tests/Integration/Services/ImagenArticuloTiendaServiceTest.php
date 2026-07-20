<?php

namespace Tests\Integration\Services;

use App\Services\ImagenArticuloTiendaService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests de la galería de fotos de tienda por artículo (RF-T14).
 *
 * El pipeline de seguridad (SVG, polyglots, tamaño) se hereda de
 * ImagenArticuloService y ya está cubierto por ImagenArticuloServiceTest;
 * acá se cubre lo propio de la galería: alta con orden incremental, tope
 * máximo, borrado, reordenamiento defensivo y saneo de badges.
 */
class ImagenArticuloTiendaServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected ImagenArticuloTiendaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        Storage::fake('public');

        $this->service = new ImagenArticuloTiendaService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_agregar_persiste_webp_en_subcarpeta_tienda_con_orden_incremental(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $primera = $this->service->agregar($articulo, UploadedFile::fake()->image('a.jpg', 600, 400));
        $segunda = $this->service->agregar($articulo, UploadedFile::fake()->image('b.png', 600, 400));

        $this->assertMatchesRegularExpression('#^articulos/\d+/tienda/[a-f0-9-]+\.webp$#', $primera->path);
        Storage::disk('public')->assertExists($primera->path);
        Storage::disk('public')->assertExists($segunda->path);
        $this->assertSame(1, $primera->orden);
        $this->assertSame(2, $segunda->orden);
        $this->assertCount(2, $articulo->fresh()->imagenesTienda);
    }

    public function test_agregar_rechaza_la_sexta_foto(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        foreach (range(1, ImagenArticuloTiendaService::MAX_IMAGENES) as $i) {
            $this->service->agregar($articulo, UploadedFile::fake()->image("f{$i}.jpg", 300, 300));
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/máximo/i');

        $this->service->agregar($articulo, UploadedFile::fake()->image('extra.jpg', 300, 300));
    }

    public function test_quitar_borra_archivo_y_registro(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $imagen = $this->service->agregar($articulo, UploadedFile::fake()->image('a.jpg', 300, 300));

        $this->service->quitar($imagen);

        Storage::disk('public')->assertMissing($imagen->path);
        $this->assertCount(0, $articulo->fresh()->imagenesTienda);
    }

    public function test_reordenar_renumera_e_ignora_ids_ajenos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $otro = $this->crearArticuloConStock($this->sucursalId);

        $a = $this->service->agregar($articulo, UploadedFile::fake()->image('a.jpg', 300, 300));
        $b = $this->service->agregar($articulo, UploadedFile::fake()->image('b.jpg', 300, 300));
        $ajena = $this->service->agregar($otro, UploadedFile::fake()->image('c.jpg', 300, 300));

        // El payload trae un ID ajeno intercalado (cliente manipulado).
        $this->service->reordenar($articulo, [$b->id, $ajena->id, $a->id]);

        $ordenado = $articulo->fresh()->imagenesTienda->pluck('id')->all();
        $this->assertSame([$b->id, $a->id], $ordenado);
        $this->assertSame(1, $ajena->fresh()->orden, 'La imagen de otro artículo no debe tocarse');
    }

    public function test_borrar_articulo_cascadea_la_galeria(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $imagen = $this->service->agregar($articulo, UploadedFile::fake()->image('a.jpg', 300, 300));

        // forceDelete para gatillar el ON DELETE CASCADE real (softdelete no borra la fila).
        $articulo->forceDelete();

        $this->assertDatabaseMissing('articulo_imagenes_tienda', ['id' => $imagen->id], 'pymes_tenant');
    }

    public function test_badges_tienda_sanea_tipos_invalidos_custom_vacio_y_exceso(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $articulo->update(['badges_tienda' => [
            ['tipo' => 'sin_tacc'],
            ['tipo' => 'inventado'],                    // fuera del catálogo → se descarta
            ['tipo' => 'custom', 'texto' => '  '],      // custom vacío → se descarta
            ['tipo' => 'custom', 'texto' => 'De la nona'],
            'basura',                                    // entrada no-array → se descarta
            ['tipo' => 'vegano'],
            ['tipo' => 'picante'],
            ['tipo' => 'nuevo'],                        // 5º válido → excede el máximo de 4
        ]]);

        $badges = $articulo->fresh()->badgesTienda();

        $this->assertSame([
            ['tipo' => 'sin_tacc', 'texto' => null],
            ['tipo' => 'custom', 'texto' => 'De la nona'],
            ['tipo' => 'vegano', 'texto' => null],
            ['tipo' => 'picante', 'texto' => null],
        ], $badges);
    }

    public function test_badges_tienda_null_devuelve_array_vacio(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $this->assertSame([], $articulo->badgesTienda());
    }
}
