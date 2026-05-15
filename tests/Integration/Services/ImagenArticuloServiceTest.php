<?php

namespace Tests\Integration\Services;

use App\Services\ImagenArticuloService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests de seguridad y comportamiento de ImagenArticuloService.
 *
 * Cubre las defensas críticas: rechazo de archivos no-imagen, rechazo de
 * SVG (vector XSS), rechazo de polyglots con extensión engañosa, rechazo
 * de tamaño excesivo, reemplazo idempotente que borra el archivo anterior.
 */
class ImagenArticuloServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected ImagenArticuloService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        // Usar disco fake para no escribir en storage real durante tests.
        Storage::fake('public');

        $this->service = new ImagenArticuloService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_actualizar_acepta_jpg_valido_y_persiste_path(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        // UploadedFile::fake()->image() crea un JPG real (no un mock).
        $file = UploadedFile::fake()->image('producto.jpg', width: 1200, height: 1200);

        $path = $this->service->actualizar($articulo, $file);

        $this->assertStringEndsWith('.webp', $path, 'La imagen guardada debe ser WebP');
        Storage::disk('public')->assertExists($path);
        $this->assertEquals($path, $articulo->fresh()->imagen_path);
    }

    public function test_actualizar_acepta_png_y_lo_convierte_a_webp(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $file = UploadedFile::fake()->image('producto.png', 500, 500);

        $path = $this->service->actualizar($articulo, $file);

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_rechaza_svg_aunque_la_extension_diga_imagen(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        // SVG con script embebido (vector XSS clásico) renombrado a .jpg.
        $svgConScript = <<<'SVG'
<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg">
    <script>alert('XSS')</script>
</svg>
SVG;
        $file = UploadedFile::fake()->createWithContent('malicioso.jpg', $svgConScript);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/no permitido|inválida/i');

        $this->service->actualizar($articulo, $file);
    }

    public function test_rechaza_archivo_no_imagen_con_extension_engañosa(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        // Archivo de texto plano (PHP/HTML) con extensión .jpg.
        $contenidoPhp = "<?php echo 'pwn'; ?>";
        $file = UploadedFile::fake()->createWithContent('shell.jpg', $contenidoPhp);

        $this->expectException(Exception::class);

        $this->service->actualizar($articulo, $file);
    }

    public function test_rechaza_archivo_que_supera_tamaño_maximo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $maxBytes = ImagenArticuloService::MAX_SIZE_BYTES;
        // Imagen "real" pero pesada (UploadedFile::fake()->image acepta size en KB).
        $file = UploadedFile::fake()->image('grande.jpg')->size(($maxBytes / 1024) + 100);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/tamaño máximo/i');

        $this->service->actualizar($articulo, $file);
    }

    public function test_subir_nueva_imagen_borra_la_anterior(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $primera = UploadedFile::fake()->image('primera.jpg', 400, 400);
        $pathAnterior = $this->service->actualizar($articulo, $primera);
        Storage::disk('public')->assertExists($pathAnterior);

        $segunda = UploadedFile::fake()->image('segunda.jpg', 400, 400);
        $pathNueva = $this->service->actualizar($articulo->fresh(), $segunda);

        $this->assertNotEquals($pathAnterior, $pathNueva);
        Storage::disk('public')->assertMissing($pathAnterior);
        Storage::disk('public')->assertExists($pathNueva);
    }

    public function test_eliminar_borra_archivo_y_limpia_path(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $file = UploadedFile::fake()->image('producto.jpg', 400, 400);
        $path = $this->service->actualizar($articulo, $file);

        $this->service->eliminar($articulo->fresh());

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($articulo->fresh()->imagen_path);
    }

    public function test_eliminar_sin_imagen_no_falla(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $this->assertNull($articulo->imagen_path);

        // No debe lanzar excepción aunque no haya nada que borrar.
        $this->service->eliminar($articulo);

        $this->assertNull($articulo->fresh()->imagen_path);
    }

    public function test_path_está_scopeado_por_comercio(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $file = UploadedFile::fake()->image('producto.jpg', 400, 400);

        $path = $this->service->actualizar($articulo, $file);

        $this->assertStringStartsWith('articulos/', $path,
            'El path debe vivir bajo articulos/{comercio_id}/...');
        $this->assertMatchesRegularExpression('#^articulos/\d+/[a-f0-9-]+\.webp$#', $path,
            'El nombre del archivo debe ser un UUID (no el nombre original del usuario)');
    }
}
