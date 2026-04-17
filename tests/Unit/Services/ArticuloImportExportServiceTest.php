<?php

namespace Tests\Unit\Services;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\HistorialPrecio;
use App\Models\User;
use App\Services\ArticuloImportExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class ArticuloImportExportServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected ArticuloImportExportService $service;

    protected Categoria $categoria;

    protected int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $this->categoria = Categoria::create([
            'nombre' => 'Bebidas',
            'prefijo' => 'BEB',
            'color' => '#000000',
            'activo' => true,
        ]);

        $this->service = new ArticuloImportExportService;
        $this->usuarioId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== EXPORT ====================

    public function test_genera_plantilla_vacia_con_headers(): void
    {
        $ruta = $this->service->generarPlantilla(false);

        $this->assertFileExists($ruta);
        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getSheetByName(__('Artículos'));

        $this->assertEquals(__('ID'), $sheet->getCell('A1')->getValue());
        $this->assertEquals(__('Código'), $sheet->getCell('B1')->getValue());
        $this->assertEquals(__('Nombre'), $sheet->getCell('D1')->getValue());
        $this->assertEquals(__('Categoría'), $sheet->getCell('F1')->getValue());
        $this->assertEquals(__('Precio'), $sheet->getCell('O1')->getValue());
        // Sin columna "Eliminado" en plantilla vacía
        $this->assertEmpty($sheet->getCell('P1')->getValue());

        @unlink($ruta);
    }

    public function test_genera_plantilla_con_datos_incluye_articulos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'nombre' => 'Coca-Cola',
            'codigo' => 'BEB0001',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 500,
        ]);

        $ruta = $this->service->generarPlantilla(true, $this->sucursalId);
        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getSheetByName(__('Artículos'));

        $this->assertEquals($articulo->id, (int) $sheet->getCell('A2')->getValue());
        $this->assertEquals('BEB0001', $sheet->getCell('B2')->getValue());
        $this->assertEquals('Coca-Cola', $sheet->getCell('D2')->getValue());
        $this->assertEquals('Bebidas', $sheet->getCell('F2')->getValue());
        $this->assertEquals(500.0, (float) $sheet->getCell('O2')->getValue());
        $this->assertEquals(__('Eliminado'), $sheet->getCell('P1')->getValue());

        @unlink($ruta);
    }

    public function test_plantilla_con_datos_marca_soft_deleted(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'nombre' => 'Fanta',
            'codigo' => 'BEB0002',
            'categoria_id' => $this->categoria->id,
        ]);
        $articulo->delete();

        $ruta = $this->service->generarPlantilla(true, $this->sucursalId);
        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getSheetByName(__('Artículos'));

        $this->assertEquals(__('Sí'), $sheet->getCell('P2')->getValue());

        @unlink($ruta);
    }

    // ==================== IMPORT — sin ID ====================

    public function test_importa_fila_nueva_crea_articulo_y_pivot(): void
    {
        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            ['', '', '', 'Sprite', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 200],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertEmpty($resultado['errores']);

        $articulo = Articulo::where('nombre', 'Sprite')->first();
        $this->assertNotNull($articulo);
        $this->assertSame('BEB0001', $articulo->codigo); // autogenerado con prefijo

        $pivot = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(200.0, (float) $pivot->precio_base);
    }

    public function test_importa_fila_nueva_sin_categoria_falla(): void
    {
        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            ['', '', '', 'Sin cat', '', '', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_importa_fila_nueva_con_codigo_manual(): void
    {
        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            ['', 'MI-001', '', 'Custom', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertNotNull(Articulo::where('codigo', 'MI-001')->first());
    }

    public function test_codigo_duplicado_en_fila_nueva_falla(): void
    {
        $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'DUP-1',
            'nombre' => 'Existente',
            'categoria_id' => $this->categoria->id,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            ['', 'DUP-1', '', 'Nuevo', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    // ==================== IMPORT — con ID ====================

    public function test_con_id_y_mismos_datos_cuenta_como_sin_cambios(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'nombre' => 'Agua',
            'codigo' => 'BEB0010',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0010', '', 'Agua', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertSame(1, $resultado['sin_cambios']);
        $this->assertSame(0, HistorialPrecio::where('articulo_id', $art->id)->count());
    }

    public function test_con_id_cambia_nombre_actualiza(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'nombre' => 'Agua',
            'codigo' => 'BEB0010',
            'categoria_id' => $this->categoria->id,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0010', '', 'Agua mineral', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 1000],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(1, $resultado['actualizadas']);
        $this->assertSame('Agua mineral', $art->fresh()->nombre);
    }

    // ==================== LÓGICA DE PRECIO (RF-06) ====================

    public function test_precio_igual_al_base_genera_override_null_sin_historial(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0020',
            'nombre' => 'X',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);
        // Setear override a 120 manualmente
        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)
            ->update(['precio_base' => 120]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0020', '', 'X', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $override = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)->value('precio_base');
        $this->assertNull($override);

        $historial = HistorialPrecio::where('articulo_id', $art->id)->first();
        $this->assertNotNull($historial);
        $this->assertSame('importacion', $historial->origen);
        $this->assertEquals(120.0, (float) $historial->precio_anterior);
        $this->assertEquals(100.0, (float) $historial->precio_nuevo);
    }

    public function test_precio_distinto_al_base_setea_override_y_registra_historial(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0030',
            'nombre' => 'Y',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0030', '', 'Y', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 150],
        ]);

        $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $override = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)->value('precio_base');
        $this->assertEquals(150.0, (float) $override);

        $h = HistorialPrecio::where('articulo_id', $art->id)->first();
        $this->assertNotNull($h);
        $this->assertSame('importacion', $h->origen);
        $this->assertEquals(100.0, (float) $h->precio_anterior);
        $this->assertEquals(150.0, (float) $h->precio_nuevo);
    }

    public function test_precio_igual_al_efectivo_actual_no_cambia_nada(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0040',
            'nombre' => 'Z',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);
        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)
            ->update(['precio_base' => 150]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0040', '', 'Z', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 150],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(1, $resultado['sin_cambios']);
        $this->assertSame(0, HistorialPrecio::where('articulo_id', $art->id)->count());
    }

    public function test_precio_vacio_con_override_previo_restaura_al_base(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0050',
            'nombre' => 'W',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);
        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)
            ->update(['precio_base' => 150]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0050', '', 'W', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', ''],
        ]);

        $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $override = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $art->id)->where('sucursal_id', $this->sucursalId)->value('precio_base');
        $this->assertNull($override);
        $this->assertSame(1, HistorialPrecio::where('articulo_id', $art->id)->count());
    }

    // ==================== SOFT-DELETE ====================

    public function test_soft_deleted_con_id_se_ignora_con_error(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0060',
            'nombre' => 'Eliminado',
            'categoria_id' => $this->categoria->id,
        ]);
        $art->delete();

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0060', '', 'Eliminado edit', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame('Eliminado', $art->fresh()->nombre); // no cambió
    }

    // ==================== DRY-RUN ====================

    public function test_dry_run_no_persiste_cambios_ni_historial(): void
    {
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0070',
            'nombre' => 'Dry',
            'categoria_id' => $this->categoria->id,
            'precio_base' => 100,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0070', '', 'Dry Modificado', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 300],
            ['', '', '', 'Nuevo via dry', '', 'Bebidas', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 50],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId, dryRun: true);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(1, $resultado['actualizadas']);
        $this->assertSame('Dry', $art->fresh()->nombre); // no se modificó
        $this->assertNull(Articulo::where('nombre', 'Nuevo via dry')->first());
        $this->assertSame(0, HistorialPrecio::where('articulo_id', $art->id)->count());
    }

    // ==================== CATEGORÍA/TIPO IVA ====================

    public function test_categoria_invalida_reporta_error(): void
    {
        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            ['', '', '', 'X', '', 'Categoría Inexistente', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $resultado = $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_cambio_de_categoria_con_prefijo_distinto_regenera_codigo(): void
    {
        $otraCat = Categoria::create([
            'nombre' => 'Snacks',
            'prefijo' => 'SNK',
            'color' => '#000000',
            'activo' => true,
        ]);
        $art = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno', [
            'codigo' => 'BEB0099',
            'nombre' => 'Galleta',
            'categoria_id' => $this->categoria->id,
        ]);

        $archivo = $this->crearArchivoXlsx([
            $this->filaHeaders(),
            [(string) $art->id, 'BEB0099', '', 'Galleta', '', 'Snacks', 'unidad', 'IVA 21%', 'Sí', 'No', 'No', 'Sí', 'Sí', 'ninguno', 100],
        ]);

        $this->service->importar($archivo, $this->sucursalId, $this->usuarioId);

        $art->refresh();
        $this->assertSame($otraCat->id, $art->categoria_id);
        $this->assertStringStartsWith('SNK', $art->codigo);
    }

    // ==================== HELPERS ====================

    private function filaHeaders(): array
    {
        return [
            __('ID'),
            __('Código'),
            __('Código de barras'),
            __('Nombre'),
            __('Descripción'),
            __('Categoría'),
            __('Unidad'),
            __('Tipo IVA'),
            __('Precio IVA incluido'),
            __('Materia prima'),
            __('Pesable'),
            __('Activo'),
            __('Vendible'),
            __('Modo stock'),
            __('Precio'),
        ];
    }

    private function crearArchivoXlsx(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Artículos'));

        foreach ($filas as $r => $fila) {
            foreach ($fila as $c => $valor) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1).($r + 1);
                $sheet->setCellValue($coord, $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'test_art_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, basename($ruta), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
