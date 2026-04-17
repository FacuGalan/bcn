<?php

namespace App\Services;

use App\Models\Categoria;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CategoriaImportExportService
{
    /**
     * Genera un archivo .xlsx de plantilla para importar categorías.
     * Devuelve la ruta temporal del archivo generado.
     */
    public function generarPlantilla(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Categorías'));

        $sheet->setCellValue('A1', __('Nombre'));
        $sheet->setCellValue('B1', __('Prefijo'));

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ];
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

        $sheet->setCellValue('A2', __('Ej: Bebidas'));
        $sheet->setCellValue('B2', 'BEB');
        $sheet->setCellValue('A3', __('Ej: Alimentos'));
        $sheet->setCellValue('B3', 'ALI');

        $sheet->getStyle('A2:B3')->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->freezePane('A2');

        $sheet->getComment('A1')->getText()->createText(
            __('Obligatorio. Nombre único de la categoría (máx. 100 caracteres).')
        );
        $sheet->getComment('B1')->getText()->createText(
            __('Opcional. Prefijo para códigos automáticos (máx. 10 caracteres, se convierte a MAYÚSCULAS).')
        );

        $ruta = tempnam(sys_get_temp_dir(), 'plantilla_categorias_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($ruta);

        return $ruta;
    }

    /**
     * Importa categorías desde un archivo .xlsx / .xls / .csv.
     * Best-effort: crea las válidas, actualiza prefijo si el nombre ya existe,
     * y reporta los errores fila a fila sin abortar el resto.
     *
     * @return array{creadas:int, actualizadas:int, errores:array<int, string>}
     */
    public function importar(UploadedFile $archivo): array
    {
        $resultado = [
            'creadas' => 0,
            'actualizadas' => 0,
            'errores' => [],
        ];

        try {
            $reader = IOFactory::createReaderForFile($archivo->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $filas = $sheet->toArray(null, true, true, false);
        } catch (Exception $e) {
            Log::error('Error al leer archivo de categorías', ['error' => $e->getMessage()]);
            $resultado['errores'][] = __('No se pudo leer el archivo: :error', ['error' => $e->getMessage()]);

            return $resultado;
        }

        if (count($filas) < 2) {
            $resultado['errores'][] = __('El archivo está vacío o no tiene filas de datos');

            return $resultado;
        }

        $header = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $filas[0]);
        $idxNombre = array_search(mb_strtolower(__('Nombre')), $header, true);
        $idxPrefijo = array_search(mb_strtolower(__('Prefijo')), $header, true);

        if ($idxNombre === false) {
            $resultado['errores'][] = __('La columna :col es obligatoria en la primera fila', ['col' => __('Nombre')]);

            return $resultado;
        }

        for ($i = 1; $i < count($filas); $i++) {
            $numeroFila = $i + 1;
            $fila = $filas[$i];

            $nombre = trim((string) ($fila[$idxNombre] ?? ''));
            $prefijoRaw = $idxPrefijo !== false ? trim((string) ($fila[$idxPrefijo] ?? '')) : '';

            if ($nombre === '' && $prefijoRaw === '') {
                continue;
            }

            if ($nombre === '') {
                $resultado['errores'][] = __('Fila :fila: el nombre es obligatorio', ['fila' => $numeroFila]);

                continue;
            }

            if (mb_strlen($nombre) > 100) {
                $resultado['errores'][] = __('Fila :fila: el nombre supera 100 caracteres', ['fila' => $numeroFila]);

                continue;
            }

            $prefijo = $prefijoRaw !== '' ? mb_strtoupper($prefijoRaw) : null;
            if ($prefijo !== null && mb_strlen($prefijo) > 10) {
                $resultado['errores'][] = __('Fila :fila: el prefijo supera 10 caracteres', ['fila' => $numeroFila]);

                continue;
            }

            try {
                $existente = Categoria::where('nombre', $nombre)->first();

                if ($existente) {
                    $existente->prefijo = $prefijo;
                    $existente->save();
                    $resultado['actualizadas']++;
                } else {
                    Categoria::create([
                        'nombre' => $nombre,
                        'prefijo' => $prefijo,
                        'color' => '#3B82F6',
                        'activo' => true,
                    ]);
                    $resultado['creadas']++;
                }
            } catch (Exception $e) {
                Log::error('Error importando categoría', [
                    'fila' => $numeroFila,
                    'nombre' => $nombre,
                    'error' => $e->getMessage(),
                ]);
                $resultado['errores'][] = __('Fila :fila: :error', [
                    'fila' => $numeroFila,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($resultado['creadas'] > 0 || $resultado['actualizadas'] > 0) {
            CatalogoCache::clear();
        }

        return $resultado;
    }
}
