<?php

namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Services\CategoriaImportExportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class CategoriaImportExportServiceTest extends TestCase
{
    use WithTenant;

    protected CategoriaImportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new CategoriaImportExportService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_generar_plantilla_vacia_crea_archivo_xlsx_con_headers(): void
    {
        $ruta = $this->service->generarPlantilla();

        $this->assertFileExists($ruta);
        $this->assertStringEndsWith('.xlsx', $ruta);

        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals(__('ID'), $sheet->getCell('A1')->getValue());
        $this->assertEquals(__('Nombre'), $sheet->getCell('B1')->getValue());
        $this->assertEquals(__('Prefijo'), $sheet->getCell('C1')->getValue());
        $this->assertEmpty($sheet->getCell('A2')->getValue());

        @unlink($ruta);
    }

    public function test_generar_plantilla_con_datos_incluye_categorias_existentes(): void
    {
        $cat = Categoria::create(['nombre' => 'Bebidas', 'prefijo' => 'BEB', 'color' => '#000000', 'activo' => true]);

        $ruta = $this->service->generarPlantilla(conDatos: true);
        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals($cat->id, (int) $sheet->getCell('A2')->getValue());
        $this->assertEquals('Bebidas', $sheet->getCell('B2')->getValue());
        $this->assertEquals('BEB', $sheet->getCell('C2')->getValue());

        @unlink($ruta);
    }

    public function test_dry_run_cuenta_cambios_pero_no_persiste(): void
    {
        $cat = Categoria::create(['nombre' => 'Bebidas', 'prefijo' => 'BEB', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            [(string) $cat->id, 'Bebidas Frías', 'BEBF'],
            ['', 'Nueva Cat', 'NUE'],
        ]);

        $resultado = $this->service->importar($archivo, dryRun: true);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(1, $resultado['actualizadas']);

        // La BD no debe haber cambiado
        $cat->refresh();
        $this->assertSame('Bebidas', $cat->nombre);
        $this->assertSame('BEB', $cat->prefijo);
        $this->assertNull(Categoria::where('nombre', 'Nueva Cat')->first());
    }

    public function test_importa_fila_sin_id_como_categoria_nueva(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', 'Bebidas', 'beb'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertEmpty($resultado['errores']);

        $categoria = Categoria::where('nombre', 'Bebidas')->first();
        $this->assertNotNull($categoria);
        $this->assertSame('BEB', $categoria->prefijo);
    }

    public function test_sin_id_actualiza_prefijo_cuando_nombre_ya_existe(): void
    {
        Categoria::create(['nombre' => 'Alimentos', 'prefijo' => 'ALI', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', 'Alimentos', 'FOOD'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(1, $resultado['actualizadas']);
        $this->assertSame('FOOD', Categoria::where('nombre', 'Alimentos')->first()->prefijo);
    }

    public function test_con_id_permite_renombrar_categoria_existente(): void
    {
        $cat = Categoria::create(['nombre' => 'Gaseosas', 'prefijo' => 'GAS', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            [(string) $cat->id, 'Bebidas sin alcohol', 'BSA'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(1, $resultado['actualizadas']);
        $this->assertSame(0, $resultado['sin_cambios']);

        $cat->refresh();
        $this->assertSame('Bebidas sin alcohol', $cat->nombre);
        $this->assertSame('BSA', $cat->prefijo);
    }

    public function test_con_id_y_datos_iguales_cuenta_como_sin_cambios(): void
    {
        $cat = Categoria::create(['nombre' => 'Bebidas', 'prefijo' => 'BEB', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            [(string) $cat->id, 'Bebidas', 'BEB'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertSame(1, $resultado['sin_cambios']);
        $this->assertEmpty($resultado['errores']);
    }

    public function test_sin_id_con_prefijo_identico_cuenta_como_sin_cambios(): void
    {
        Categoria::create(['nombre' => 'Alimentos', 'prefijo' => 'ALI', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', 'Alimentos', 'ALI'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertSame(1, $resultado['sin_cambios']);
    }

    public function test_con_id_inexistente_reporta_error(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['99999', 'Fantasma', 'FTM'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_con_id_no_numerico_reporta_error(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['abc', 'Algo', 'ALG'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_con_id_rechaza_nombre_que_ya_pertenece_a_otra_categoria(): void
    {
        $cat1 = Categoria::create(['nombre' => 'A', 'color' => '#000000', 'activo' => true]);
        Categoria::create(['nombre' => 'B', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            [(string) $cat1->id, 'B', 'XX'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame('A', $cat1->fresh()->nombre);
    }

    public function test_reporta_fila_sin_nombre_como_error_y_sigue(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', '', 'XX'],
            ['', 'Válida', 'VAL'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertStringContainsString('2', $resultado['errores'][0]);
    }

    public function test_reporta_prefijo_mayor_a_10_caracteres(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', 'Categoría X', 'ABCDEFGHIJK'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_ignora_filas_completamente_vacias(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), __('Nombre'), __('Prefijo')],
            ['', 'Uno', 'UNO'],
            ['', '', ''],
            ['', 'Dos', 'DOS'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(2, $resultado['creadas']);
        $this->assertEmpty($resultado['errores']);
    }

    public function test_retorna_error_si_falta_columna_nombre(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('ID'), 'Otra columna', __('Prefijo')],
            ['', 'Algo', 'AAA'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertNotEmpty($resultado['errores']);
    }

    private function crearArchivoXlsx(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($filas as $r => $fila) {
            foreach ($fila as $c => $valor) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1).($r + 1);
                $sheet->setCellValue($coord, $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'test_cat_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, basename($ruta), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
