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

    public function test_generar_plantilla_crea_archivo_xlsx_con_headers(): void
    {
        $ruta = $this->service->generarPlantilla();

        $this->assertFileExists($ruta);
        $this->assertStringEndsWith('.xlsx', $ruta);

        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals(__('Nombre'), $sheet->getCell('A1')->getValue());
        $this->assertEquals(__('Prefijo'), $sheet->getCell('B1')->getValue());

        @unlink($ruta);
    }

    public function test_importa_fila_valida_como_categoria_nueva(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('Nombre'), __('Prefijo')],
            ['Bebidas', 'beb'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(0, $resultado['actualizadas']);
        $this->assertEmpty($resultado['errores']);

        $categoria = Categoria::where('nombre', 'Bebidas')->first();
        $this->assertNotNull($categoria);
        $this->assertSame('BEB', $categoria->prefijo);
    }

    public function test_actualiza_prefijo_cuando_nombre_ya_existe(): void
    {
        Categoria::create(['nombre' => 'Alimentos', 'prefijo' => 'ALI', 'color' => '#000000', 'activo' => true]);

        $archivo = $this->crearArchivoXlsx([
            [__('Nombre'), __('Prefijo')],
            ['Alimentos', 'FOOD'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(1, $resultado['actualizadas']);
        $this->assertSame('FOOD', Categoria::where('nombre', 'Alimentos')->first()->prefijo);
    }

    public function test_reporta_fila_sin_nombre_como_error_y_sigue(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('Nombre'), __('Prefijo')],
            ['', 'XX'],
            ['Válida', 'VAL'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(1, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertStringContainsString('2', $resultado['errores'][0]);
    }

    public function test_reporta_prefijo_mayor_a_10_caracteres(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('Nombre'), __('Prefijo')],
            ['Categoría X', 'ABCDEFGHIJK'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(0, $resultado['creadas']);
        $this->assertCount(1, $resultado['errores']);
    }

    public function test_ignora_filas_completamente_vacias(): void
    {
        $archivo = $this->crearArchivoXlsx([
            [__('Nombre'), __('Prefijo')],
            ['Uno', 'UNO'],
            ['', ''],
            ['Dos', 'DOS'],
        ]);

        $resultado = $this->service->importar($archivo);

        $this->assertSame(2, $resultado['creadas']);
        $this->assertEmpty($resultado['errores']);
    }

    public function test_retorna_error_si_falta_columna_nombre(): void
    {
        $archivo = $this->crearArchivoXlsx([
            ['Otra columna', __('Prefijo')],
            ['Algo', 'AAA'],
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
