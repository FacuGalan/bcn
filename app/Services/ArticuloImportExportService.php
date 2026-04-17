<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\HistorialPrecio;
use App\Models\TipoIva;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ArticuloImportExportService
{
    private const COL_ID = 'A';

    private const COL_CODIGO = 'B';

    private const COL_CODIGO_BARRAS = 'C';

    private const COL_NOMBRE = 'D';

    private const COL_DESCRIPCION = 'E';

    private const COL_CATEGORIA = 'F';

    private const COL_UNIDAD = 'G';

    private const COL_TIPO_IVA = 'H';

    private const COL_PRECIO_IVA_INCLUIDO = 'I';

    private const COL_MATERIA_PRIMA = 'J';

    private const COL_PESABLE = 'K';

    private const COL_ACTIVO = 'L';

    private const COL_VENDIBLE = 'M';

    private const COL_MODO_STOCK = 'N';

    private const COL_PRECIO = 'O';

    private const COL_ELIMINADO = 'P';

    private const LISTA_SI_NO = '"Sí,No"';

    private const LISTA_MODO_STOCK = '"ninguno,unitario,receta"';

    /**
     * Genera un archivo .xlsx de plantilla para importar artículos.
     *
     * @param  bool  $conDatos  Si true, prellena con los artículos actuales (requiere $sucursalId).
     * @param  int|null  $sucursalId  Sucursal activa desde la que se exporta el precio y campos del pivot.
     */
    public function generarPlantilla(bool $conDatos = false, ?int $sucursalId = null): string
    {
        if ($conDatos && $sucursalId === null) {
            throw new \InvalidArgumentException('Se requiere sucursalId para exportar con datos');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Artículos'));

        $this->escribirHeaders($sheet, $conDatos);
        $this->construirHojaDatos($spreadsheet);

        if ($conDatos) {
            $ultimaFila = $this->llenarDatosExistentes($sheet, $sucursalId);
        } else {
            $ultimaFila = $this->llenarFilaEjemplo($sheet);
        }

        $this->aplicarEstilosGenerales($sheet, $ultimaFila, $conDatos);
        $this->aplicarDataValidations($sheet, $ultimaFila);
        $this->aplicarComentarios($sheet);

        $ruta = tempnam(sys_get_temp_dir(), 'plantilla_articulos_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return $ruta;
    }

    private function escribirHeaders(Worksheet $sheet, bool $conDatos): void
    {
        $sheet->setCellValue(self::COL_ID.'1', __('ID'));
        $sheet->setCellValue(self::COL_CODIGO.'1', __('Código'));
        $sheet->setCellValue(self::COL_CODIGO_BARRAS.'1', __('Código de barras'));
        $sheet->setCellValue(self::COL_NOMBRE.'1', __('Nombre'));
        $sheet->setCellValue(self::COL_DESCRIPCION.'1', __('Descripción'));
        $sheet->setCellValue(self::COL_CATEGORIA.'1', __('Categoría'));
        $sheet->setCellValue(self::COL_UNIDAD.'1', __('Unidad'));
        $sheet->setCellValue(self::COL_TIPO_IVA.'1', __('Tipo IVA'));
        $sheet->setCellValue(self::COL_PRECIO_IVA_INCLUIDO.'1', __('Precio IVA incluido'));
        $sheet->setCellValue(self::COL_MATERIA_PRIMA.'1', __('Materia prima'));
        $sheet->setCellValue(self::COL_PESABLE.'1', __('Pesable'));
        $sheet->setCellValue(self::COL_ACTIVO.'1', __('Activo'));
        $sheet->setCellValue(self::COL_VENDIBLE.'1', __('Vendible'));
        $sheet->setCellValue(self::COL_MODO_STOCK.'1', __('Modo stock'));
        $sheet->setCellValue(self::COL_PRECIO.'1', __('Precio'));

        if ($conDatos) {
            $sheet->setCellValue(self::COL_ELIMINADO.'1', __('Eliminado'));
        }

        $ultimaColumna = $conDatos ? self::COL_ELIMINADO : self::COL_PRECIO;
        $headerRange = self::COL_ID."1:{$ultimaColumna}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);
    }

    /**
     * Construye una hoja oculta "_datos" con los valores válidos para dropdowns
     * (categorías activas y tipos de IVA activos). Se referencia desde DataValidation.
     */
    private function construirHojaDatos(Spreadsheet $spreadsheet): void
    {
        $datosSheet = $spreadsheet->createSheet();
        $datosSheet->setTitle('_datos');

        $categorias = Categoria::where('activo', true)->orderBy('nombre')->pluck('nombre');
        $tiposIva = TipoIva::where('activo', true)->orderBy('porcentaje')->pluck('nombre');

        $row = 1;
        foreach ($categorias as $nombre) {
            $datosSheet->setCellValue("A{$row}", $nombre);
            $row++;
        }
        $row = 1;
        foreach ($tiposIva as $nombre) {
            $datosSheet->setCellValue("B{$row}", $nombre);
            $row++;
        }

        $datosSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Llena la plantilla con los artículos existentes de la sucursal dada.
     * Devuelve el número de la última fila con datos.
     */
    private function llenarDatosExistentes(Worksheet $sheet, int $sucursalId): int
    {
        $articulos = Articulo::withTrashed()
            ->with(['tipoIva', 'categoriaModel'])
            ->orderBy('codigo')
            ->get();

        $overrides = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('sucursal_id', $sucursalId)
            ->get()
            ->keyBy('articulo_id');

        $row = 2;
        foreach ($articulos as $articulo) {
            $pivot = $overrides->get($articulo->id);
            $precioEfectivo = ($pivot?->precio_base !== null) ? (float) $pivot->precio_base : (float) $articulo->precio_base;

            $sheet->setCellValue(self::COL_ID."{$row}", $articulo->id);
            $sheet->setCellValueExplicit(self::COL_CODIGO."{$row}", $articulo->codigo, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit(self::COL_CODIGO_BARRAS."{$row}", $articulo->codigo_barras ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue(self::COL_NOMBRE."{$row}", $articulo->nombre);
            $sheet->setCellValue(self::COL_DESCRIPCION."{$row}", $articulo->descripcion ?? '');
            $sheet->setCellValue(self::COL_CATEGORIA."{$row}", $articulo->categoriaModel?->nombre ?? '');
            $sheet->setCellValue(self::COL_UNIDAD."{$row}", $articulo->unidad_medida ?? 'unidad');
            $sheet->setCellValue(self::COL_TIPO_IVA."{$row}", $articulo->tipoIva?->nombre ?? '');
            $sheet->setCellValue(self::COL_PRECIO_IVA_INCLUIDO."{$row}", $articulo->precio_iva_incluido ? __('Sí') : __('No'));
            $sheet->setCellValue(self::COL_MATERIA_PRIMA."{$row}", $articulo->es_materia_prima ? __('Sí') : __('No'));
            $sheet->setCellValue(self::COL_PESABLE."{$row}", $articulo->pesable ? __('Sí') : __('No'));
            $sheet->setCellValue(self::COL_ACTIVO."{$row}", ($pivot?->activo ?? true) ? __('Sí') : __('No'));
            $sheet->setCellValue(self::COL_VENDIBLE."{$row}", ($pivot?->vendible ?? true) ? __('Sí') : __('No'));
            $sheet->setCellValue(self::COL_MODO_STOCK."{$row}", $pivot?->modo_stock ?? 'ninguno');
            $sheet->setCellValue(self::COL_PRECIO."{$row}", $precioEfectivo);

            $eliminado = $articulo->trashed();
            $sheet->setCellValue(self::COL_ELIMINADO."{$row}", $eliminado ? __('Sí') : '');

            if ($eliminado) {
                $sheet->getStyle(self::COL_ID."{$row}:".self::COL_ELIMINADO."{$row}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FEE2E2');
            }

            $row++;
        }

        return max($row - 1, 2);
    }

    private function llenarFilaEjemplo(Worksheet $sheet): int
    {
        $sheet->setCellValue(self::COL_ID.'2', '');
        $sheet->setCellValue(self::COL_CODIGO.'2', '');
        $sheet->setCellValue(self::COL_NOMBRE.'2', __('Ej: Coca-Cola 500ml'));
        $sheet->setCellValue(self::COL_UNIDAD.'2', 'unidad');
        $sheet->setCellValue(self::COL_PRECIO_IVA_INCLUIDO.'2', __('Sí'));
        $sheet->setCellValue(self::COL_MATERIA_PRIMA.'2', __('No'));
        $sheet->setCellValue(self::COL_PESABLE.'2', __('No'));
        $sheet->setCellValue(self::COL_ACTIVO.'2', __('Sí'));
        $sheet->setCellValue(self::COL_VENDIBLE.'2', __('Sí'));
        $sheet->setCellValue(self::COL_MODO_STOCK.'2', 'ninguno');
        $sheet->setCellValue(self::COL_PRECIO.'2', 0);

        $sheet->getStyle('D2:D2')->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

        return 2;
    }

    private function aplicarEstilosGenerales(Worksheet $sheet, int $ultimaFila, bool $conDatos): void
    {
        // Columna ID en gris (se mantiene gestionada por el sistema)
        $sheet->getStyle(self::COL_ID.'2:'.self::COL_ID."{$ultimaFila}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('F3F4F6');
        $sheet->getStyle(self::COL_ID.'2:'.self::COL_ID."{$ultimaFila}")
            ->getFont()->getColor()->setRGB('9CA3AF');

        // Formato TEXT en Código y Código de barras para evitar que Excel los
        // interprete como número (notación científica en códigos de barras > 11 dígitos,
        // pérdida de ceros a la izquierda en códigos autogenerados como "000001").
        $filasFormato = max($ultimaFila + 500, 1000);
        foreach ([self::COL_CODIGO, self::COL_CODIGO_BARRAS] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$filasFormato}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Anchos
        $anchos = [
            self::COL_ID => 10,
            self::COL_CODIGO => 14,
            self::COL_CODIGO_BARRAS => 16,
            self::COL_NOMBRE => 32,
            self::COL_DESCRIPCION => 30,
            self::COL_CATEGORIA => 18,
            self::COL_UNIDAD => 10,
            self::COL_TIPO_IVA => 14,
            self::COL_PRECIO_IVA_INCLUIDO => 12,
            self::COL_MATERIA_PRIMA => 12,
            self::COL_PESABLE => 10,
            self::COL_ACTIVO => 10,
            self::COL_VENDIBLE => 10,
            self::COL_MODO_STOCK => 12,
            self::COL_PRECIO => 12,
        ];
        if ($conDatos) {
            $anchos[self::COL_ELIMINADO] = 11;
        }
        foreach ($anchos as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('B2');
    }

    private function aplicarDataValidations(Worksheet $sheet, int $ultimaFila): void
    {
        $maxFila = max($ultimaFila + 100, 200);

        // Categoría (desde hoja _datos columna A)
        $this->aplicarValidacionRango($sheet, self::COL_CATEGORIA, $maxFila, '_datos!$A$1:$A$1000');
        // Tipo IVA (desde hoja _datos columna B)
        $this->aplicarValidacionRango($sheet, self::COL_TIPO_IVA, $maxFila, '_datos!$B$1:$B$1000');

        // Listas inline
        foreach ([self::COL_PRECIO_IVA_INCLUIDO, self::COL_MATERIA_PRIMA, self::COL_PESABLE, self::COL_ACTIVO, self::COL_VENDIBLE] as $col) {
            $this->aplicarValidacionInline($sheet, $col, $maxFila, self::LISTA_SI_NO);
        }

        $this->aplicarValidacionInline($sheet, self::COL_MODO_STOCK, $maxFila, self::LISTA_MODO_STOCK);
    }

    private function aplicarValidacionRango(Worksheet $sheet, string $col, int $maxFila, string $formula): void
    {
        for ($fila = 2; $fila <= $maxFila; $fila++) {
            $validation = $sheet->getCell("{$col}{$fila}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula);
        }
    }

    private function aplicarValidacionInline(Worksheet $sheet, string $col, int $maxFila, string $lista): void
    {
        for ($fila = 2; $fila <= $maxFila; $fila++) {
            $validation = $sheet->getCell("{$col}{$fila}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($lista);
        }
    }

    private function aplicarComentarios(Worksheet $sheet): void
    {
        $sheet->getComment(self::COL_ID.'1')->getText()->createText(
            __('No modificar. El sistema usa este ID para identificar el artículo al importar (permite renombrar sin duplicar).')
        );
        $sheet->getComment(self::COL_CODIGO.'1')->getText()->createText(
            __('Opcional al crear: si queda vacío, se autogenera usando el prefijo de la categoría.')
        );
        $sheet->getComment(self::COL_NOMBRE.'1')->getText()->createText(
            __('Obligatorio. Nombre del artículo.')
        );
        $sheet->getComment(self::COL_CATEGORIA.'1')->getText()->createText(
            __('Obligatorio al crear. Elegí una categoría del desplegable.')
        );
        $sheet->getComment(self::COL_TIPO_IVA.'1')->getText()->createText(
            __('Obligatorio al crear. Elegí un tipo de IVA del desplegable.')
        );
        $sheet->getComment(self::COL_PRECIO.'1')->getText()->createText(
            __('Precio de la sucursal activa. Si coincide con el precio base genérico del artículo, no se registra un override.')
        );
    }

    // ================================================================================
    // IMPORT
    // ================================================================================

    private const ORIGEN_IMPORTACION = 'importacion';

    /**
     * Importa artículos desde un archivo .xlsx / .xls / .csv.
     * Best-effort: procesa fila a fila, reporta errores sin abortar el resto.
     *
     * Comportamiento por fila:
     * - Con ID: busca el Articulo y actualiza campos base + pivot de la sucursal. Permite rename.
     * - Sin ID: crea Articulo + pivot para la sucursal.
     * - Soft-deleted: se ignoran con error (no se restauran por import).
     *
     * @param  bool  $dryRun  Si true, valida y cuenta pero no persiste (preview).
     * @return array{creadas:int, actualizadas:int, sin_cambios:int, errores:array<int, string>}
     */
    public function importar(UploadedFile $archivo, int $sucursalId, int $usuarioId, bool $dryRun = false): array
    {
        $resultado = [
            'creadas' => 0,
            'actualizadas' => 0,
            'sin_cambios' => 0,
            'errores' => [],
        ];

        $nombreArchivo = $archivo->getClientOriginalName() ?: basename($archivo->getRealPath());

        try {
            $reader = IOFactory::createReaderForFile($archivo->getRealPath());
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([__('Artículos'), 'Artículos', 'Articulos']);
            $spreadsheet = $reader->load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $filas = $sheet->toArray(null, true, true, false);
        } catch (Exception $e) {
            Log::error('Error al leer archivo de artículos', ['error' => $e->getMessage()]);
            $resultado['errores'][] = __('No se pudo leer el archivo: :error', ['error' => $e->getMessage()]);

            return $resultado;
        }

        if (count($filas) < 2) {
            $resultado['errores'][] = __('El archivo está vacío o no tiene filas de datos');

            return $resultado;
        }

        $headerMap = $this->mapearHeaders($filas[0]);
        if (isset($headerMap['_error'])) {
            $resultado['errores'][] = $headerMap['_error'];

            return $resultado;
        }

        // Pre-cargas para minimizar queries
        $categoriasIndex = Categoria::all()->keyBy(fn ($c) => mb_strtolower(trim($c->nombre)));
        $tiposIvaIndex = TipoIva::all()->keyBy(fn ($t) => mb_strtolower(trim($t->nombre)));

        $cambioHuboAlgo = false;

        for ($i = 1; $i < count($filas); $i++) {
            $numeroFila = $i + 1;
            $fila = $filas[$i];

            if ($this->filaVacia($fila)) {
                continue;
            }

            try {
                $this->procesarFila(
                    fila: $fila,
                    numeroFila: $numeroFila,
                    headerMap: $headerMap,
                    categoriasIndex: $categoriasIndex,
                    tiposIvaIndex: $tiposIvaIndex,
                    sucursalId: $sucursalId,
                    usuarioId: $usuarioId,
                    nombreArchivo: $nombreArchivo,
                    dryRun: $dryRun,
                    resultado: $resultado,
                );
            } catch (Exception $e) {
                Log::error('Error importando artículo', [
                    'fila' => $numeroFila,
                    'error' => $e->getMessage(),
                ]);
                $resultado['errores'][] = __('Fila :fila: :error', [
                    'fila' => $numeroFila,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $dryRun && ($resultado['creadas'] > 0 || $resultado['actualizadas'] > 0)) {
            CatalogoCache::clear();
        }

        return $resultado;
    }

    /**
     * Mapea la fila de headers a índices de columna (insensible a acentos/mayúsculas).
     *
     * @return array<string, int>|array{_error: string}
     */
    private function mapearHeaders(array $fila): array
    {
        $normalizar = fn ($s) => mb_strtolower(trim((string) $s));

        $headers = array_map($normalizar, $fila);

        $required = [
            'id' => __('ID'),
            'codigo' => __('Código'),
            'codigo_barras' => __('Código de barras'),
            'nombre' => __('Nombre'),
            'descripcion' => __('Descripción'),
            'categoria' => __('Categoría'),
            'unidad' => __('Unidad'),
            'tipo_iva' => __('Tipo IVA'),
            'precio_iva_incluido' => __('Precio IVA incluido'),
            'materia_prima' => __('Materia prima'),
            'pesable' => __('Pesable'),
            'activo' => __('Activo'),
            'vendible' => __('Vendible'),
            'modo_stock' => __('Modo stock'),
            'precio' => __('Precio'),
        ];

        $map = [];
        foreach ($required as $key => $label) {
            $idx = array_search($normalizar($label), $headers, true);
            if ($idx === false && $key === 'nombre') {
                return ['_error' => __('La columna :col es obligatoria en la primera fila', ['col' => $label])];
            }
            $map[$key] = $idx !== false ? $idx : -1;
        }

        return $map;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Procesa una fila individual. Lanza excepción en error recuperable; caller la captura.
     */
    private function procesarFila(
        array $fila,
        int $numeroFila,
        array $headerMap,
        $categoriasIndex,
        $tiposIvaIndex,
        int $sucursalId,
        int $usuarioId,
        string $nombreArchivo,
        bool $dryRun,
        array &$resultado,
    ): void {
        $get = fn (string $key) => $headerMap[$key] >= 0 ? trim((string) ($fila[$headerMap[$key]] ?? '')) : '';

        $idRaw = $get('id');
        $nombre = $get('nombre');
        $codigoManual = $get('codigo');
        $categoriaNombre = $get('categoria');
        $tipoIvaNombre = $get('tipo_iva');

        if ($nombre === '') {
            $resultado['errores'][] = __('Fila :fila: el nombre es obligatorio', ['fila' => $numeroFila]);

            return;
        }

        if (mb_strlen($nombre) > 191) {
            $resultado['errores'][] = __('Fila :fila: el nombre supera 191 caracteres', ['fila' => $numeroFila]);

            return;
        }

        // Resolver categoría (puede ser null al update si la columna viene vacía)
        $categoria = null;
        if ($categoriaNombre !== '') {
            $categoria = $categoriasIndex->get(mb_strtolower($categoriaNombre));
            if (! $categoria) {
                $resultado['errores'][] = __('Fila :fila: categoría ":categoria" no encontrada', [
                    'fila' => $numeroFila,
                    'categoria' => $categoriaNombre,
                ]);

                return;
            }
        }

        // Resolver tipo de IVA (puede ser null al update si la columna viene vacía)
        $tipoIva = null;
        if ($tipoIvaNombre !== '') {
            $tipoIva = $tiposIvaIndex->get(mb_strtolower($tipoIvaNombre));
            if (! $tipoIva) {
                $resultado['errores'][] = __('Fila :fila: tipo de IVA ":tipo" no encontrado', [
                    'fila' => $numeroFila,
                    'tipo' => $tipoIvaNombre,
                ]);

                return;
            }
        }

        $precioRaw = $get('precio');
        $precioRecibido = $precioRaw === '' ? null : (float) str_replace(',', '.', $precioRaw);

        $datos = [
            'codigo_barras' => $get('codigo_barras') ?: null,
            'nombre' => $nombre,
            'descripcion' => $get('descripcion') ?: null,
            'unidad_medida' => $get('unidad') ?: 'unidad',
            'precio_iva_incluido' => $this->parseSiNo($get('precio_iva_incluido'), true),
            'es_materia_prima' => $this->parseSiNo($get('materia_prima'), false),
            'pesable' => $this->parseSiNo($get('pesable'), false),
        ];
        $pivotDatos = [
            'activo' => $this->parseSiNo($get('activo'), true),
            'vendible' => $this->parseSiNo($get('vendible'), true),
            'modo_stock' => $this->parseModoStock($get('modo_stock')) ?? 'ninguno',
        ];

        if ($idRaw !== '') {
            $this->procesarFilaConId(
                idRaw: $idRaw,
                numeroFila: $numeroFila,
                codigoManual: $codigoManual,
                categoria: $categoria,
                tipoIva: $tipoIva,
                datos: $datos,
                pivotDatos: $pivotDatos,
                precioRecibido: $precioRecibido,
                sucursalId: $sucursalId,
                usuarioId: $usuarioId,
                nombreArchivo: $nombreArchivo,
                dryRun: $dryRun,
                resultado: $resultado,
            );
        } else {
            $this->procesarFilaSinId(
                numeroFila: $numeroFila,
                codigoManual: $codigoManual,
                categoria: $categoria,
                tipoIva: $tipoIva,
                datos: $datos,
                pivotDatos: $pivotDatos,
                precioRecibido: $precioRecibido,
                sucursalId: $sucursalId,
                usuarioId: $usuarioId,
                nombreArchivo: $nombreArchivo,
                dryRun: $dryRun,
                resultado: $resultado,
            );
        }
    }

    private function procesarFilaConId(
        string $idRaw,
        int $numeroFila,
        string $codigoManual,
        ?Categoria $categoria,
        ?TipoIva $tipoIva,
        array $datos,
        array $pivotDatos,
        ?float $precioRecibido,
        int $sucursalId,
        int $usuarioId,
        string $nombreArchivo,
        bool $dryRun,
        array &$resultado,
    ): void {
        if (! ctype_digit($idRaw)) {
            $resultado['errores'][] = __('Fila :fila: el ID debe ser un número', ['fila' => $numeroFila]);

            return;
        }

        $articulo = Articulo::withTrashed()->find((int) $idRaw);
        if (! $articulo) {
            $resultado['errores'][] = __('Fila :fila: no existe un artículo con ID :id', [
                'fila' => $numeroFila,
                'id' => $idRaw,
            ]);

            return;
        }

        if ($articulo->trashed()) {
            $resultado['errores'][] = __('Fila :fila: el artículo ":nombre" está eliminado, no se modifica', [
                'fila' => $numeroFila,
                'nombre' => $articulo->nombre,
            ]);

            return;
        }

        $callback = function () use (
            $articulo, $codigoManual, $categoria, $tipoIva, $datos, $pivotDatos,
            $precioRecibido, $sucursalId, $usuarioId, $nombreArchivo, $dryRun, &$resultado
        ) {
            $categoriaAnterior = $articulo->categoriaModel;
            $cambioCategoria = $categoria !== null && $categoria->id !== $articulo->categoria_id;

            // Asignar campos base
            foreach ($datos as $k => $v) {
                $articulo->{$k} = $v;
            }
            if ($categoria !== null) {
                $articulo->categoria_id = $categoria->id;
            }
            if ($tipoIva !== null) {
                $articulo->tipo_iva_id = $tipoIva->id;
            }

            // Código: si hay cambio de categoría con prefijo distinto → regenerar (RF-05)
            if ($cambioCategoria && trim((string) $categoriaAnterior?->prefijo) !== trim((string) $categoria?->prefijo)) {
                $articulo->codigo = $this->calcularSiguienteCodigo($categoria?->prefijo);
            } elseif ($codigoManual !== '' && $codigoManual !== $articulo->codigo) {
                $colision = Articulo::where('codigo', $codigoManual)
                    ->where('id', '!=', $articulo->id)
                    ->first();
                if ($colision) {
                    throw new Exception(__('el código ":codigo" ya existe', ['codigo' => $codigoManual]));
                }
                $articulo->codigo = $codigoManual;
            }

            $huboCambioBase = $articulo->isDirty();
            $huboCambioPivot = $this->actualizarPivot($articulo->id, $sucursalId, $pivotDatos, $dryRun);
            $huboCambioPrecio = $this->aplicarCambioPrecio(
                articulo: $articulo,
                precioRecibido: $precioRecibido,
                sucursalId: $sucursalId,
                usuarioId: $usuarioId,
                nombreArchivo: $nombreArchivo,
                dryRun: $dryRun,
            );

            if ($huboCambioBase && ! $dryRun) {
                $articulo->save();
            }

            if ($huboCambioBase || $huboCambioPivot || $huboCambioPrecio) {
                $resultado['actualizadas']++;
            } else {
                $resultado['sin_cambios']++;
            }
        };

        if ($dryRun) {
            $callback();
        } else {
            DB::connection('pymes_tenant')->transaction($callback);
        }
    }

    private function procesarFilaSinId(
        int $numeroFila,
        string $codigoManual,
        ?Categoria $categoria,
        ?TipoIva $tipoIva,
        array $datos,
        array $pivotDatos,
        ?float $precioRecibido,
        int $sucursalId,
        int $usuarioId,
        string $nombreArchivo,
        bool $dryRun,
        array &$resultado,
    ): void {
        if ($categoria === null) {
            $resultado['errores'][] = __('Fila :fila: la categoría es obligatoria al crear', ['fila' => $numeroFila]);

            return;
        }
        if ($tipoIva === null) {
            $resultado['errores'][] = __('Fila :fila: el tipo de IVA es obligatorio al crear', ['fila' => $numeroFila]);

            return;
        }

        // Determinar código
        if ($codigoManual !== '') {
            if (Articulo::withTrashed()->where('codigo', $codigoManual)->exists()) {
                $resultado['errores'][] = __('Fila :fila: el código ":codigo" ya existe', [
                    'fila' => $numeroFila,
                    'codigo' => $codigoManual,
                ]);

                return;
            }
            $codigoFinal = $codigoManual;
        } else {
            $codigoFinal = $this->calcularSiguienteCodigo($categoria->prefijo);
        }

        $callback = function () use (
            $codigoFinal, $categoria, $tipoIva, $datos, $pivotDatos,
            $precioRecibido, $sucursalId, $usuarioId, $nombreArchivo, $dryRun, &$resultado
        ) {
            if ($dryRun) {
                $resultado['creadas']++;

                return;
            }

            $articulo = Articulo::create(array_merge($datos, [
                'codigo' => $codigoFinal,
                'categoria_id' => $categoria->id,
                'tipo_iva_id' => $tipoIva->id,
                'precio_base' => 0,
                'activo' => true,
            ]));

            DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->insert([
                    'articulo_id' => $articulo->id,
                    'sucursal_id' => $sucursalId,
                    'precio_base' => null,
                    'activo' => $pivotDatos['activo'],
                    'vendible' => $pivotDatos['vendible'],
                    'modo_stock' => $pivotDatos['modo_stock'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->aplicarCambioPrecio(
                articulo: $articulo,
                precioRecibido: $precioRecibido,
                sucursalId: $sucursalId,
                usuarioId: $usuarioId,
                nombreArchivo: $nombreArchivo,
                dryRun: false,
            );

            $resultado['creadas']++;
        };

        if ($dryRun) {
            $callback();
        } else {
            DB::connection('pymes_tenant')->transaction($callback);
        }
    }

    /**
     * Actualiza o crea el pivot articulos_sucursales para la sucursal activa.
     * Devuelve true si hubo cambio real.
     */
    private function actualizarPivot(int $articuloId, int $sucursalId, array $pivotDatos, bool $dryRun): bool
    {
        $pivot = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        if (! $pivot) {
            if (! $dryRun) {
                DB::connection('pymes_tenant')
                    ->table('articulos_sucursales')
                    ->insert([
                        'articulo_id' => $articuloId,
                        'sucursal_id' => $sucursalId,
                        'precio_base' => null,
                        'activo' => $pivotDatos['activo'],
                        'vendible' => $pivotDatos['vendible'],
                        'modo_stock' => $pivotDatos['modo_stock'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return true;
        }

        $cambioActivo = (bool) $pivot->activo !== $pivotDatos['activo'];
        $cambioVendible = (bool) $pivot->vendible !== $pivotDatos['vendible'];
        $cambioModoStock = $pivot->modo_stock !== $pivotDatos['modo_stock'];

        if (! $cambioActivo && ! $cambioVendible && ! $cambioModoStock) {
            return false;
        }

        if (! $dryRun) {
            DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->where('id', $pivot->id)
                ->update([
                    'activo' => $pivotDatos['activo'],
                    'vendible' => $pivotDatos['vendible'],
                    'modo_stock' => $pivotDatos['modo_stock'],
                    'updated_at' => now(),
                ]);
        }

        return true;
    }

    /**
     * Aplica la lógica de precio (RF-06). Devuelve true si hubo cambio efectivo.
     *
     * Reglas:
     * - Si precio recibido == Articulo.precio_base (base genérico) → override null, sin historial
     * - Si precio recibido != base y distinto al efectivo actual → override seteado + historial
     * - Si precio recibido == efectivo actual → sin cambio
     */
    private function aplicarCambioPrecio(
        Articulo $articulo,
        ?float $precioRecibido,
        int $sucursalId,
        int $usuarioId,
        string $nombreArchivo,
        bool $dryRun,
    ): bool {
        $precioBase = (float) $articulo->precio_base;
        $pivotId = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $sucursalId)
            ->value('id');

        $override = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $sucursalId)
            ->value('precio_base');

        $override = $override !== null ? (float) $override : null;
        $efectivoAnterior = $override ?? $precioBase;

        $precioFinal = $precioRecibido;
        // Tratar null/vacío como intención de volver al base
        if ($precioFinal === null) {
            $nuevoOverride = null;
        } elseif ($this->precioCoincideConBase($precioFinal, $precioBase)) {
            $nuevoOverride = null;
        } else {
            $nuevoOverride = $precioFinal;
        }

        $cambioOverride = ! $this->overrideIguales($override, $nuevoOverride);
        $efectivoNuevo = $nuevoOverride ?? $precioBase;
        $cambioPrecioEfectivo = abs($efectivoNuevo - $efectivoAnterior) > 0.001;

        if (! $cambioOverride) {
            return false;
        }

        if (! $dryRun && $pivotId !== null) {
            DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->where('id', $pivotId)
                ->update([
                    'precio_base' => $nuevoOverride,
                    'updated_at' => now(),
                ]);
        }

        if ($cambioPrecioEfectivo && ! $dryRun) {
            $porcentaje = $efectivoAnterior > 0
                ? round((($efectivoNuevo - $efectivoAnterior) / $efectivoAnterior) * 100, 2)
                : 0;

            HistorialPrecio::registrar([
                'articulo_id' => $articulo->id,
                'sucursal_id' => $sucursalId,
                'precio_anterior' => $efectivoAnterior,
                'precio_nuevo' => $efectivoNuevo,
                'usuario_id' => $usuarioId,
                'origen' => self::ORIGEN_IMPORTACION,
                'porcentaje_cambio' => $porcentaje,
                'detalle' => __('Importado desde :archivo', ['archivo' => $nombreArchivo]),
            ]);
        }

        return true;
    }

    private function precioCoincideConBase(float $precio, float $base): bool
    {
        return abs($precio - $base) < 0.001;
    }

    private function overrideIguales(?float $a, ?float $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return abs($a - $b) < 0.001;
    }

    private function parseSiNo(string $valor, bool $default): bool
    {
        $v = mb_strtolower(trim($valor));
        if ($v === '') {
            return $default;
        }

        return in_array($v, ['sí', 'si', 'yes', 'y', '1', 'true', 'verdadero'], true);
    }

    private function parseModoStock(string $valor): ?string
    {
        $v = mb_strtolower(trim($valor));
        if (in_array($v, ['ninguno', 'unitario', 'receta'], true)) {
            return $v;
        }

        return null;
    }

    /**
     * Calcula el siguiente código disponible a partir del prefijo de la categoría.
     * Replica la lógica de GestionarArticulos::calcularSiguienteCodigo (protected).
     */
    private function calcularSiguienteCodigo(?string $prefijo): string
    {
        $prefijo = $prefijo !== null ? strtoupper(trim($prefijo)) : '';

        if ($prefijo === '') {
            $ultimoNumero = Articulo::withTrashed()
                ->where('codigo', 'REGEXP', '^[0-9]+$')
                ->get(['codigo'])
                ->map(fn ($a) => (int) $a->codigo)
                ->max() ?? 0;

            return str_pad((string) ($ultimoNumero + 1), 6, '0', STR_PAD_LEFT);
        }

        $ultimoNumero = Articulo::withTrashed()
            ->where('codigo', 'LIKE', $prefijo.'%')
            ->get(['codigo'])
            ->map(function ($articulo) use ($prefijo) {
                $sufijo = substr($articulo->codigo, strlen($prefijo));

                return ctype_digit($sufijo) ? (int) $sufijo : 0;
            })
            ->max() ?? 0;

        return $prefijo.str_pad((string) ($ultimoNumero + 1), 4, '0', STR_PAD_LEFT);
    }
}
