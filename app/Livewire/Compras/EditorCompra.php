<?php

namespace App\Livewire\Compras;

use App\Models\Articulo;
use App\Models\ArticuloProveedor;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\CuentaCompra;
use App\Models\CuentaEmpresa;
use App\Models\Cuit;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\TipoIva;
use App\Services\CompraService;
use App\Services\CostoService;
use Exception;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Editor de compra en modal a pantalla completa (Fase 6, sesión UX D7).
 *
 * Sub-componente SIN ruta propia: lo monta condicionalmente el listado
 * `Compras` (patrón NuevoPedidoDelivery — todo el comprobante se simula
 * dentro del modal y se modifica ahí mismo):
 *   - compraId = null  → alta (borrador nuevo)
 *   - compraId (borrador) → edición directa
 *   - ncOrigenId → NC precargada con los renglones de la compra origen
 *   - esNC = true → NC suelta (sin origen)
 *
 * Grilla tipo planilla con navegación por teclado, búsqueda por código
 * propio / código del proveedor / nombre, alta rápida inline, descuentos
 * en cascada como texto "10+5+3", sección fiscal siempre visible con
 * desglose de IVA editable (validación de cuadre NO bloqueante) y pago
 * en modal al confirmar (D14: la caja pertenece al PAGO — el bloque de
 * pago lee la caja activa, el componente no es CajaAware).
 */
class EditorCompra extends Component
{
    // ==================== Identidad / modo ====================

    public ?int $compraId = null;

    public ?int $ncOrigenId = null;

    public bool $esNC = false;

    public int $sucursalId;

    // ==================== Encabezado ====================

    public ?int $proveedorId = null;

    public string $tipoComprobante = Compra::TIPO_FACTURA_A;

    public bool $noFiscal = false;

    public ?int $cuitId = null;

    public string $numeroComprobanteProveedor = '';

    public ?string $fechaComprobante = null;

    public ?string $fechaVencimiento = null;

    public ?int $cuentaCompraId = null;

    public string $descuentoGlobal = '';

    public string $observaciones = '';

    // ==================== Renglones (grilla planilla) ====================

    /** @var array<int, array> ver renglonVacio() */
    public array $renglones = [];

    // ==================== Sección fiscal ====================

    /** @var array<int, array{alicuota: string, base_imponible: string, importe: string}> */
    public array $ivas = [];

    /** true cuando el usuario editó el desglose a mano: se deja de pisar con la sugerencia */
    public bool $fiscalManual = false;

    public string $netoNoGravado = '';

    public string $netoExento = '';

    /** @var array<int, array{tipo: string, descripcion: string, monto: string, tipo_iva_id: ?int, computa_costo: bool}> */
    public array $conceptos = [];

    /** @var array<int, array{impuesto_id: ?int, base_imponible: string, alicuota: string, monto: string, certificado_numero: string}> */
    public array $percepciones = [];

    // ==================== Modal de pago (al confirmar) ====================

    public bool $mostrarModalPago = false;

    /** 'cta_cte' | 'contado' */
    public string $modalidadPago = 'contado';

    public bool $registrarPagoAhora = true;

    /** @var array renglones {forma_pago_id, monto, origen, caja_id, cuenta_empresa_id} */
    public array $pagosIniciales = [];

    public string $saldoFavorUsado = '';

    public float $saldoFavorDisponible = 0;

    // ==================== Modal resumen post-confirmación ====================

    public bool $mostrarModalResumen = false;

    public array $resumen = [];

    /** @var array<int, array{nombre: string, costo: float, margen_real: ?float, objetivo: float}> */
    public array $articulosBajoMargen = [];

    // ==================== Alta rápida de artículo (inline) ====================

    public bool $mostrarModalArticuloRapido = false;

    public ?int $filaAltaRapida = null;

    public string $artRapidoNombre = '';

    public ?int $artRapidoCategoriaId = null;

    public string $artRapidoCodigo = '';

    public ?int $artRapidoTipoIvaId = null;

    public string $artRapidoCodigoProveedor = '';

    public string $artRapidoPrecioVenta = '';

    // ==================== Mount / carga ====================

    public function mount(?int $compraId = null, ?int $ncOrigenId = null, bool $esNC = false): void
    {
        $this->sucursalId = (int) sucursal_activa();
        $this->esNC = $esNC || $ncOrigenId !== null;
        $this->ncOrigenId = $ncOrigenId;
        $this->tipoComprobante = $this->esNC ? Compra::TIPO_NC_A : Compra::TIPO_FACTURA_A;
        $this->fechaComprobante = now()->toDateString();
        $this->cuitId = $this->cuitDefault()?->id;
        $this->renglones = [$this->renglonVacio()];

        if ($ncOrigenId !== null) {
            $this->precargarNcDesdeOrigen($ncOrigenId);
        }

        if ($compraId !== null) {
            $this->cargarCompra($compraId);
        }
    }

    /** CUIT comprador default: el principal de la sucursal (patrón CostoService). */
    protected function cuitDefault(): ?Cuit
    {
        $sucursal = Sucursal::find($this->sucursalId);

        return $sucursal?->cuits()->wherePivot('es_principal', true)->first()
            ?? $sucursal?->cuits()->first()
            ?? Cuit::activos()->first();
    }

    /**
     * Precarga un borrador existente al editor (edición directa, D7 #12).
     */
    protected function cargarCompra(int $compraId): void
    {
        $compra = Compra::with(['detalles.articulo', 'detalles.tipoIva', 'ivas', 'conceptos', 'percepciones'])
            ->findOrFail($compraId);

        $this->compraId = $compra->id;
        $this->esNC = $compra->esNotaCredito();
        $this->ncOrigenId = $compra->compra_origen_id;
        $this->proveedorId = $compra->proveedor_id;
        $this->tipoComprobante = $compra->tipo_comprobante;
        $this->noFiscal = ! $compra->esFiscal();
        $this->cuitId = $compra->cuit_id ?? $this->cuitId;
        $this->numeroComprobanteProveedor = (string) ($compra->numero_comprobante_proveedor ?? '');
        $this->fechaComprobante = $compra->fecha_comprobante?->toDateString();
        $this->fechaVencimiento = $compra->fecha_vencimiento?->toDateString();
        $this->cuentaCompraId = $compra->cuenta_compra_id;
        $this->descuentoGlobal = $compra->descuento_global_porcentaje !== null
            ? rtrim(rtrim(number_format((float) $compra->descuento_global_porcentaje, 2, '.', ''), '0'), '.')
            : '';
        $this->observaciones = (string) ($compra->observaciones ?? '');
        $this->netoNoGravado = (float) $compra->neto_no_gravado > 0 ? (string) $compra->neto_no_gravado : '';
        $this->netoExento = (float) $compra->neto_exento > 0 ? (string) $compra->neto_exento : '';

        $this->renglones = $compra->detalles->map(fn ($d) => [
            'articulo_id' => $d->articulo_id,
            'nombre' => $d->articulo?->nombre ?? __('Artículo eliminado'),
            'codigo' => $d->articulo?->codigo ?? '',
            'busqueda' => '',
            'resultados' => [],
            'codigo_proveedor_usado' => $d->codigo_proveedor_usado,
            'cantidad_comprada' => $this->numAString($d->cantidad_comprada),
            'factor_conversion' => $this->numAString($d->factor_conversion),
            'precio_unitario' => $this->numAString($d->precio_unitario),
            'descuentos_texto' => implode('+', array_map(
                fn ($x) => rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.'),
                (array) $d->descuentos
            )),
            'tipo_iva_id' => $d->tipo_iva_id,
        ])->values()->all() ?: [$this->renglonVacio()];

        $this->ivas = $compra->ivas->map(fn ($iva) => [
            'alicuota' => $this->numAString($iva->alicuota),
            'base_imponible' => $this->numAString($iva->base_imponible),
            'importe' => $this->numAString($iva->importe),
        ])->values()->all();

        // Un desglose persistido se respeta tal cual se cargó.
        $this->fiscalManual = $this->ivas !== [];

        $this->conceptos = $compra->conceptos->map(fn ($c) => [
            'tipo' => $c->tipo,
            'descripcion' => (string) ($c->descripcion ?? ''),
            'monto' => $this->numAString($c->monto),
            'tipo_iva_id' => $c->tipo_iva_id,
            'computa_costo' => (bool) $c->computa_costo,
        ])->values()->all();

        $this->percepciones = $compra->percepciones->map(fn ($p) => [
            'impuesto_id' => $p->impuesto_id,
            'base_imponible' => $this->numAString($p->base_imponible),
            'alicuota' => $this->numAString($p->alicuota),
            'monto' => $this->numAString($p->monto),
            'certificado_numero' => (string) ($p->certificado_numero ?? ''),
        ])->values()->all();
    }

    /**
     * NC desde el detalle de una compra (D7 #9): precarga proveedor, CUIT y
     * renglones con las cantidades compradas como TOPE a devolver; el desglose
     * de IVA proporcional es solo PRECARGA sugerida (RF-21) — el usuario carga
     * el de la NC física.
     */
    protected function precargarNcDesdeOrigen(int $origenId): void
    {
        $origen = Compra::with(['detalles.articulo', 'detalles.tipoIva'])->findOrFail($origenId);

        $this->proveedorId = $origen->proveedor_id;
        $this->cuitId = $origen->cuit_id ?? $this->cuitId;
        $this->cuentaCompraId = $origen->cuenta_compra_id;
        $this->noFiscal = ! $origen->esFiscal();
        $this->tipoComprobante = match ($origen->tipo_comprobante) {
            Compra::TIPO_FACTURA_A, Compra::TIPO_FACTURA_M => Compra::TIPO_NC_A,
            Compra::TIPO_FACTURA_B => Compra::TIPO_NC_B,
            Compra::TIPO_FACTURA_C => Compra::TIPO_NC_C,
            default => Compra::TIPO_NC_NO_FISCAL,
        };

        $this->renglones = $origen->detalles->map(fn ($d) => [
            'articulo_id' => $d->articulo_id,
            'nombre' => $d->articulo?->nombre ?? __('Artículo eliminado'),
            'codigo' => $d->articulo?->codigo ?? '',
            'busqueda' => '',
            'resultados' => [],
            'codigo_proveedor_usado' => $d->codigo_proveedor_usado,
            'cantidad_comprada' => $this->numAString($d->cantidad_comprada),
            'max_cantidad' => (float) $d->cantidad_comprada,
            'factor_conversion' => $this->numAString($d->factor_conversion),
            'precio_unitario' => $this->numAString($d->precio_unitario),
            'descuentos_texto' => implode('+', array_map(
                fn ($x) => rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.'),
                (array) $d->descuentos
            )),
            'tipo_iva_id' => $d->tipo_iva_id,
        ])->values()->all() ?: [$this->renglonVacio()];

        $this->sugerirDesgloseFiscal();
    }

    // ==================== Grilla: renglones ====================

    protected function renglonVacio(): array
    {
        return [
            'articulo_id' => null,
            'nombre' => '',
            'codigo' => '',
            'busqueda' => '',
            'resultados' => [],
            'codigo_proveedor_usado' => null,
            'cantidad_comprada' => '1',
            'factor_conversion' => '1',
            'precio_unitario' => '',
            'descuentos_texto' => '',
            'tipo_iva_id' => null,
        ];
    }

    public function agregarRenglon(): void
    {
        $this->renglones[] = $this->renglonVacio();
    }

    public function quitarRenglon(int $index): void
    {
        unset($this->renglones[$index]);
        $this->renglones = array_values($this->renglones) ?: [$this->renglonVacio()];
        $this->sugerirDesgloseFiscal();
    }

    /**
     * Hook global: búsqueda de artículo por fila + re-sugerencia del desglose
     * fiscal cuando cambian montos (mientras el usuario no lo haya pisado).
     */
    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^renglones\.(\d+)\.busqueda$/', $name, $m)) {
            $this->buscarArticuloFila((int) $m[1]);

            return;
        }

        // El usuario editó el desglose a mano: dejar de pisarlo.
        if (str_starts_with($name, 'ivas.') || in_array($name, ['netoNoGravado', 'netoExento'], true)) {
            $this->fiscalManual = true;

            return;
        }

        $recalculan = preg_match('/^(renglones\.\d+\.(cantidad_comprada|factor_conversion|precio_unitario|descuentos_texto|tipo_iva_id)|conceptos\..+|descuentoGlobal|tipoComprobante|noFiscal)$/', $name);

        if ($recalculan) {
            if ($name === 'noFiscal') {
                $this->tipoComprobante = $this->tiposDisponibles()[0];
            }

            $this->sugerirDesgloseFiscal();
        }

        if ($name === 'proveedorId') {
            $this->seleccionarProveedor((int) $value ?: null);
        }
    }

    /**
     * Busca por código del proveedor seleccionado (exacto), código propio,
     * código de barras y nombre (todas las palabras, patrón del carrito).
     */
    public function buscarArticuloFila(int $fila): void
    {
        if (! isset($this->renglones[$fila])) {
            return;
        }

        $termino = trim((string) $this->renglones[$fila]['busqueda']);

        if (mb_strlen($termino) < 2) {
            $this->renglones[$fila]['resultados'] = [];

            return;
        }

        $resultados = collect();

        // 1. Código del PROVEEDOR seleccionado (match exacto, RF-04). Puede
        //    devolver varios artículos: se listan y el usuario elige.
        if ($this->proveedorId) {
            $resultados = ArticuloProveedor::porCodigo($this->proveedorId, $termino)
                ->with('articulo.tipoIva')
                ->get()
                ->filter(fn ($ap) => $ap->articulo !== null && $ap->articulo->activo)
                ->map(fn ($ap) => $this->resultadoDesdeArticulo($ap->articulo, $ap));
        }

        // 2. Búsqueda propia: cada palabra contra nombre/código/código de barras.
        $query = Articulo::with('tipoIva')->where('activo', true);

        foreach (preg_split('/\s+/', $termino, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $query->where(function ($q) use ($palabra) {
                $q->where('nombre', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$palabra.'%');
            });
        }

        $query->where(function ($q) {
            $q->whereHas('sucursales', function ($subQ) {
                $subQ->where('sucursal_id', $this->sucursalId)
                    ->where('articulos_sucursales.activo', 1);
            })->orWhereDoesntHave('sucursales');
        });

        $propios = $query->orderBy('nombre')->limit(10)->get()
            ->map(fn ($art) => $this->resultadoDesdeArticulo($art));

        $this->renglones[$fila]['resultados'] = $resultados->concat($propios)
            ->unique('id')
            ->take(10)
            ->values()
            ->all();
    }

    protected function resultadoDesdeArticulo(Articulo $articulo, ?ArticuloProveedor $ap = null): array
    {
        return [
            'id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'codigo_proveedor' => $ap?->codigo_proveedor,
            'factor_conversion' => $ap !== null ? (float) $ap->factor_conversion : null,
            'descuentos_habituales' => $ap !== null ? (array) $ap->descuentos_habituales : [],
            'tipo_iva_id' => $articulo->tipo_iva_id,
        ];
    }

    public function seleccionarArticuloFila(int $fila, int $articuloId): void
    {
        if (! isset($this->renglones[$fila])) {
            return;
        }

        $articulo = Articulo::with('tipoIva')->find($articuloId);

        if ($articulo === null) {
            return;
        }

        // Datos del proveedor para ESTE artículo (RF-04): código, factor y
        // descuentos habituales se precargan (editables).
        $ap = $this->proveedorId
            ? ArticuloProveedor::where('articulo_id', $articuloId)
                ->where('proveedor_id', $this->proveedorId)
                ->first()
            : null;

        $this->renglones[$fila] = array_merge($this->renglones[$fila], [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'busqueda' => '',
            'resultados' => [],
            'codigo_proveedor_usado' => $ap?->codigo_proveedor,
            'factor_conversion' => $ap !== null ? $this->numAString($ap->factor_conversion) : $this->renglones[$fila]['factor_conversion'],
            'descuentos_texto' => $ap !== null && (array) $ap->descuentos_habituales !== []
                ? implode('+', array_map(
                    fn ($x) => rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.'),
                    (array) $ap->descuentos_habituales
                ))
                : $this->renglones[$fila]['descuentos_texto'],
            'tipo_iva_id' => $articulo->tipo_iva_id,
        ]);

        $this->sugerirDesgloseFiscal();
    }

    public function cerrarResultadosFila(int $fila): void
    {
        if (isset($this->renglones[$fila])) {
            $this->renglones[$fila]['resultados'] = [];
        }
    }

    /** Vuelve la celda del artículo al modo búsqueda (cambiar el artículo de la fila). */
    public function limpiarArticuloFila(int $fila): void
    {
        if (! isset($this->renglones[$fila])) {
            return;
        }

        $this->renglones[$fila] = array_merge($this->renglones[$fila], [
            'articulo_id' => null,
            'nombre' => '',
            'codigo' => '',
            'busqueda' => '',
            'resultados' => [],
            'codigo_proveedor_usado' => null,
        ]);

        $this->sugerirDesgloseFiscal();
    }

    // ==================== Alta rápida inline (D7 #3) ====================

    public function abrirAltaRapida(int $fila): void
    {
        $this->filaAltaRapida = $fila;

        $busqueda = trim((string) ($this->renglones[$fila]['busqueda'] ?? ''));

        // Heurística: lo tipeado que parece código va al código del proveedor;
        // si no, al nombre.
        $pareceCodigo = $busqueda !== '' && ! str_contains($busqueda, ' ') && preg_match('/\d/', $busqueda);
        $this->artRapidoNombre = $pareceCodigo ? '' : $busqueda;
        $this->artRapidoCodigoProveedor = $pareceCodigo ? $busqueda : '';
        $this->artRapidoCodigo = '';
        $this->artRapidoCategoriaId = null;
        $this->artRapidoPrecioVenta = '';
        $this->artRapidoTipoIvaId = TipoIva::where('porcentaje', 21)->value('id');

        $this->mostrarModalArticuloRapido = true;
    }

    public function cerrarAltaRapida(): void
    {
        $this->mostrarModalArticuloRapido = false;
        $this->filaAltaRapida = null;
    }

    /** Auto-código por prefijo de la categoría (patrón WithArticuloRapido). */
    public function updatedArtRapidoCategoriaId($value): void
    {
        if (! $value) {
            return;
        }

        $categoria = Categoria::find($value);

        if ($categoria && $categoria->prefijo) {
            $ultimoCodigo = Articulo::where('codigo', 'like', $categoria->prefijo.'-%')
                ->orderByRaw('CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED) DESC')
                ->value('codigo');

            $numero = $ultimoCodigo ? ((int) last(explode('-', $ultimoCodigo)) + 1) : 1;

            $this->artRapidoCodigo = $categoria->prefijo.'-'.str_pad($numero, 3, '0', STR_PAD_LEFT);
        }
    }

    public function guardarArticuloRapido(): void
    {
        $this->validate([
            'artRapidoNombre' => 'required|string|min:2|max:200',
            'artRapidoCodigo' => 'required|string|max:50|unique:pymes_tenant.articulos,codigo',
            'artRapidoCategoriaId' => 'nullable|exists:pymes_tenant.categorias,id',
            'artRapidoTipoIvaId' => 'required|exists:pymes_tenant.tipos_iva,id',
        ], [
            'artRapidoNombre.required' => __('El nombre es obligatorio'),
            'artRapidoCodigo.required' => __('El código es obligatorio'),
            'artRapidoCodigo.unique' => __('Ya existe un artículo con este código'),
            'artRapidoTipoIvaId.required' => __('Seleccione un tipo de IVA'),
        ]);

        try {
            $articulo = Articulo::create([
                'nombre' => $this->artRapidoNombre,
                'codigo' => $this->artRapidoCodigo,
                'categoria_id' => $this->artRapidoCategoriaId,
                'unidad_medida' => 'unidad',
                'tipo_iva_id' => $this->artRapidoTipoIvaId,
                'precio_iva_incluido' => true,
                'precio_base' => $this->num($this->artRapidoPrecioVenta),
                'es_materia_prima' => false,
                'activo' => true,
            ]);

            // Disponible en todas las sucursales, activo solo en la actual
            // (patrón WithArticuloRapido).
            $syncData = [];
            foreach (Sucursal::pluck('id') as $sucursalId) {
                $syncData[$sucursalId] = [
                    'activo' => $sucursalId == $this->sucursalId,
                    'modo_stock' => 'ninguno',
                    'vendible' => true,
                    'precio_base' => null,
                ];
            }
            $articulo->sucursales()->sync($syncData);

            // D7 #3: persistir automáticamente el código del proveedor.
            if ($this->proveedorId && $this->artRapidoCodigoProveedor !== '') {
                ArticuloProveedor::create([
                    'articulo_id' => $articulo->id,
                    'proveedor_id' => $this->proveedorId,
                    'codigo_proveedor' => $this->artRapidoCodigoProveedor,
                    'factor_conversion' => 1,
                    'activo' => true,
                ]);
            }

            if ($this->filaAltaRapida !== null) {
                $this->seleccionarArticuloFila($this->filaAltaRapida, $articulo->id);
            }

            $this->cerrarAltaRapida();

            $this->dispatch('notify', type: 'success', message: __('Artículo ":nombre" creado', ['nombre' => $articulo->nombre]));
        } catch (Exception $e) {
            Log::error('Error en alta rápida de artículo (compras): '.$e->getMessage());
            $this->dispatch('notify', type: 'error', message: __('Error al crear el artículo'));
        }
    }

    // ==================== Proveedor ====================

    public function seleccionarProveedor(?int $proveedorId): void
    {
        $this->proveedorId = $proveedorId;

        if ($proveedorId === null) {
            return;
        }

        $proveedor = Proveedor::find($proveedorId);

        if ($proveedor === null) {
            return;
        }

        // RF-22: la cuenta de compra default del proveedor precarga la de la
        // compra (editable). RF-18: dias_pago precarga el vencimiento.
        $this->cuentaCompraId = $this->cuentaCompraId ?? $proveedor->cuenta_compra_id;

        if ($proveedor->dias_pago !== null && ! $this->fechaVencimiento) {
            $this->fechaVencimiento = now()->addDays((int) $proveedor->dias_pago)->toDateString();
        }
    }

    // ==================== Cálculos (públicos para la vista) ====================

    public function num(string|float|int|null $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    protected function numAString(string|float|int|null $valor): string
    {
        $float = (float) $valor;

        return rtrim(rtrim(number_format($float, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** "10+5+3" → [10.0, 5.0, 3.0] (solo % válidos 0 < d < 100). */
    public function parseDescuentos(string $texto): array
    {
        $partes = preg_split('/[+\s]+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($partes)
            ->map(fn ($p) => (float) str_replace(',', '.', $p))
            ->filter(fn ($d) => $d > 0 && $d < 100)
            ->values()
            ->all();
    }

    /** Datos derivados de un renglón: unitario efectivo (por bulto), cantidad stock y subtotal. */
    public function calcularRenglon(array $renglon): array
    {
        $cantidad = $this->num($renglon['cantidad_comprada'] ?? 0);
        $factor = max($this->num($renglon['factor_conversion'] ?? 1), 0.0);
        $unitario = $this->num($renglon['precio_unitario'] ?? 0);

        foreach ($this->parseDescuentos((string) ($renglon['descuentos_texto'] ?? '')) as $descuento) {
            $unitario *= (1 - $descuento / 100);
        }

        return [
            'unitario_efectivo' => round($unitario, 4),
            'cantidad_stock' => round($cantidad * $factor, 3),
            'subtotal' => round($unitario * $cantidad, 2),
        ];
    }

    public function totales(): array
    {
        $subtotal = 0.0;

        foreach ($this->renglones as $renglon) {
            if (! empty($renglon['articulo_id'])) {
                $subtotal += $this->calcularRenglon($renglon)['subtotal'];
            }
        }

        $descuentoGlobal = round($subtotal * $this->num($this->descuentoGlobal) / 100, 2);
        $conceptos = round(collect($this->conceptos)->sum(fn ($c) => $this->num($c['monto'] ?? 0)), 2);
        $iva = $this->esFiscalActual() && $this->discriminaActual()
            ? round(collect($this->ivas)->sum(fn ($i) => $this->num($i['importe'] ?? 0)), 2)
            : 0.0;
        $percepciones = $this->esFiscalActual()
            ? round(collect($this->percepciones)->sum(fn ($p) => $this->num($p['monto'] ?? 0)), 2)
            : 0.0;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento_global' => $descuentoGlobal,
            'conceptos' => $conceptos,
            'iva' => $iva,
            'percepciones' => $percepciones,
            'total' => round($subtotal - $descuentoGlobal + $conceptos + $iva + $percepciones, 2),
        ];
    }

    public function esFiscalActual(): bool
    {
        return ! in_array($this->tipoComprobante, Compra::TIPOS_NO_FISCALES, true);
    }

    public function discriminaActual(): bool
    {
        return in_array($this->tipoComprobante, Compra::TIPOS_DISCRIMINAN_IVA, true);
    }

    /** Tipos de comprobante válidos según modo NC + toggle no fiscal (D15). */
    public function tiposDisponibles(): array
    {
        if ($this->noFiscal) {
            return $this->esNC ? [Compra::TIPO_NC_NO_FISCAL] : [Compra::TIPO_NO_FISCAL];
        }

        return $this->esNC
            ? [Compra::TIPO_NC_A, Compra::TIPO_NC_B, Compra::TIPO_NC_C]
            : [Compra::TIPO_FACTURA_A, Compra::TIPO_FACTURA_B, Compra::TIPO_FACTURA_C, Compra::TIPO_FACTURA_M];
    }

    // ==================== Sugerencia del desglose fiscal (RF-14) ====================

    /**
     * Sugiere compra_ivas + netos desde los renglones (bases con cascada +
     * prorrateo del descuento global) + conceptos gravados. Solo pisa el
     * estado mientras el usuario no lo haya editado a mano.
     */
    public function sugerirDesgloseFiscal(): void
    {
        if ($this->fiscalManual) {
            return;
        }

        [$ivas, $noGravado, $exento] = $this->calcularSugerenciaFiscal();

        $this->ivas = $ivas;
        $this->netoNoGravado = $noGravado > 0 ? (string) $noGravado : '';
        $this->netoExento = $exento > 0 ? (string) $exento : '';
    }

    /** Vuelve a la sugerencia automática (botón "Recalcular"). */
    public function recalcularDesgloseFiscal(): void
    {
        $this->fiscalManual = false;
        $this->sugerirDesgloseFiscal();
    }

    /**
     * @return array{0: array, 1: float, 2: float} [ivas, neto_no_gravado, neto_exento]
     */
    protected function calcularSugerenciaFiscal(): array
    {
        if (! $this->esFiscalActual() || ! $this->discriminaActual()) {
            return [[], 0.0, 0.0];
        }

        $alicuotas = TipoIva::pluck('porcentaje', 'id');

        // Bases por renglón con cascada, luego prorrateo del descuento global
        // por importe (la factura real ya lo trae así, RF-14).
        $factorGlobal = 1 - $this->num($this->descuentoGlobal) / 100;

        $bases = [];
        $exento = 0.0;

        foreach ($this->renglones as $renglon) {
            if (empty($renglon['articulo_id'])) {
                continue;
            }

            $base = $this->calcularRenglon($renglon)['subtotal'] * $factorGlobal;
            $alicuota = round((float) ($alicuotas[$renglon['tipo_iva_id']] ?? 0), 2);

            if ($alicuota > 0) {
                $bases[(string) $alicuota] = ($bases[(string) $alicuota] ?? 0) + $base;
            } else {
                $exento += $base;
            }
        }

        // Conceptos gravados (RF-15): su neto suma a la base de su alícuota;
        // sin tipo_iva ⇒ no gravado.
        $noGravado = 0.0;

        foreach ($this->conceptos as $concepto) {
            $monto = $this->num($concepto['monto'] ?? 0);

            if ($monto <= 0) {
                continue;
            }

            $alicuota = round((float) ($alicuotas[$concepto['tipo_iva_id'] ?? null] ?? 0), 2);

            if ($alicuota > 0) {
                $bases[(string) $alicuota] = ($bases[(string) $alicuota] ?? 0) + $monto;
            } else {
                $noGravado += $monto;
            }
        }

        ksort($bases);

        $ivas = [];
        foreach ($bases as $alicuota => $base) {
            $ivas[] = [
                'alicuota' => (string) $alicuota,
                'base_imponible' => (string) round($base, 2),
                'importe' => (string) round($base * (float) $alicuota / 100, 2),
            ];
        }

        return [$ivas, round($noGravado, 2), round($exento, 2)];
    }

    /**
     * Cuadre RF-14 (NO bloqueante): sugerido vs cargado con tolerancia de
     * ±$1 por alícuota. Devuelve el detalle de las diferencias o [].
     */
    public function diferenciasCuadre(): array
    {
        if (! $this->esFiscalActual() || ! $this->discriminaActual()) {
            return [];
        }

        [$sugeridos] = $this->calcularSugerenciaFiscal();

        $porAlicuota = collect($sugeridos)->keyBy('alicuota');
        $cargados = collect($this->ivas)->keyBy(fn ($i) => (string) round($this->num($i['alicuota']), 2));

        $diferencias = [];

        foreach ($porAlicuota->keys()->merge($cargados->keys())->unique() as $alicuota) {
            $sugerido = $this->num($porAlicuota[$alicuota]['importe'] ?? 0);
            $cargado = $this->num($cargados[$alicuota]['importe'] ?? 0);

            if (abs($sugerido - $cargado) > 1) {
                $diferencias[] = [
                    'alicuota' => (float) $alicuota,
                    'sugerido' => round($sugerido, 2),
                    'cargado' => round($cargado, 2),
                ];
            }
        }

        return $diferencias;
    }

    public function agregarIva(): void
    {
        $this->fiscalManual = true;
        $this->ivas[] = ['alicuota' => '21', 'base_imponible' => '', 'importe' => ''];
    }

    public function quitarIva(int $index): void
    {
        $this->fiscalManual = true;
        unset($this->ivas[$index]);
        $this->ivas = array_values($this->ivas);
    }

    // ==================== Conceptos y percepciones ====================

    public function agregarConcepto(): void
    {
        $this->conceptos[] = [
            'tipo' => 'flete',
            'descripcion' => '',
            'monto' => '',
            'tipo_iva_id' => null,
            'computa_costo' => true,
        ];
    }

    public function quitarConcepto(int $index): void
    {
        unset($this->conceptos[$index]);
        $this->conceptos = array_values($this->conceptos);
        $this->sugerirDesgloseFiscal();
    }

    public function agregarPercepcion(): void
    {
        $this->percepciones[] = [
            'impuesto_id' => null,
            'base_imponible' => '',
            'alicuota' => '',
            'monto' => '',
            'certificado_numero' => '',
        ];
    }

    public function quitarPercepcion(int $index): void
    {
        unset($this->percepciones[$index]);
        $this->percepciones = array_values($this->percepciones);
    }

    /** base × alícuota → monto (comodidad de carga; el monto queda editable). */
    public function calcularMontoPercepcion(int $index): void
    {
        if (! isset($this->percepciones[$index])) {
            return;
        }

        $base = $this->num($this->percepciones[$index]['base_imponible']);
        $alicuota = $this->num($this->percepciones[$index]['alicuota']);

        if ($base > 0 && $alicuota > 0) {
            $this->percepciones[$index]['monto'] = (string) round($base * $alicuota / 100, 2);
        }
    }

    // ==================== Advertencias (no bloqueantes) ====================

    public function advertencias(): array
    {
        $avisos = [];

        // Comprobante × condición IVA del CUIT comprador (RF-06).
        if ($this->cuitId && $this->esFiscalActual()) {
            $condicion = Cuit::with('condicionIva')->find($this->cuitId)?->condicionIva;
            $aviso = app(CompraService::class)->advertenciaComprobanteCuit($condicion, $this->tipoComprobante);

            if ($aviso !== null) {
                $avisos[] = $aviso;
            }
        }

        // Período fiscal viejo (RF-06): la fecha del comprobante rige el crédito.
        if ($this->esFiscalActual() && $this->fechaComprobante
            && $this->fechaComprobante < now()->startOfMonth()->toDateString()) {
            $avisos[] = __('La fecha del comprobante cae en un período fiscal anterior (:periodo): el crédito se computará en ese período — AFIP lo admite, decide el contador', [
                'periodo' => substr($this->fechaComprobante, 0, 7),
            ]);
        }

        // Cuadre del desglose de IVA (RF-14).
        foreach ($this->diferenciasCuadre() as $dif) {
            $avisos[] = __('IVA :alicuota%: el desglose cargado ($:cargado) difiere del calculado por los renglones ($:sugerido)', [
                'alicuota' => rtrim(rtrim(number_format($dif['alicuota'], 2, '.', ''), '0'), '.'),
                'cargado' => number_format($dif['cargado'], 2, ',', '.'),
                'sugerido' => number_format($dif['sugerido'], 2, ',', '.'),
            ]);
        }

        return $avisos;
    }

    public function esDuplicado(): bool
    {
        if (! $this->proveedorId || trim($this->numeroComprobanteProveedor) === '') {
            return false;
        }

        return app(CompraService::class)->esComprobanteDuplicado(
            $this->proveedorId,
            $this->tipoComprobante,
            trim($this->numeroComprobanteProveedor),
            $this->compraId,
        );
    }

    // ==================== Guardar borrador (D7 #7) ====================

    public function guardarBorrador(): void
    {
        try {
            $compra = $this->persistirBorrador();

            $this->compraId = $compra->id;

            $this->dispatch('compra-guardada');
            $this->dispatch('notify', type: 'success', message: __('Borrador guardado'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    protected function persistirBorrador(): Compra
    {
        $this->validarParaGuardar();

        $servicio = app(CompraService::class);
        [$data, $renglones, $extras] = $this->construirPayload();

        if ($this->compraId) {
            $compra = Compra::findOrFail($this->compraId);

            return $servicio->actualizarBorrador($compra, $data, $renglones, $extras);
        }

        return $servicio->crearBorrador($data, $renglones, $extras);
    }

    protected function validarParaGuardar(): void
    {
        if (! $this->proveedorId) {
            throw new Exception(__('Seleccioná un proveedor'));
        }

        $conArticulo = collect($this->renglones)->filter(fn ($r) => ! empty($r['articulo_id']));

        if ($conArticulo->isEmpty()) {
            throw new Exception(__('Cargá al menos un renglón con artículo'));
        }

        foreach ($conArticulo as $renglon) {
            if ($this->num($renglon['cantidad_comprada']) <= 0) {
                throw new Exception(__('El renglón ":nombre" tiene cantidad inválida', ['nombre' => $renglon['nombre']]));
            }

            if ($this->num($renglon['factor_conversion']) <= 0) {
                throw new Exception(__('El renglón ":nombre" tiene factor de conversión inválido', ['nombre' => $renglon['nombre']]));
            }
        }
    }

    /** @return array{0: array, 1: array, 2: array} [$data, $renglones, $extras] */
    protected function construirPayload(): array
    {
        $discrimina = $this->esFiscalActual() && $this->discriminaActual();

        $data = [
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $this->proveedorId,
            'usuario_id' => (int) auth()->id(),
            'tipo_comprobante' => $this->tipoComprobante,
            'compra_origen_id' => $this->ncOrigenId,
            'cuit_id' => $this->esFiscalActual() ? $this->cuitId : null,
            'cuenta_compra_id' => $this->cuentaCompraId,
            'numero_comprobante_proveedor' => trim($this->numeroComprobanteProveedor) ?: null,
            'fecha_comprobante' => $this->esFiscalActual() ? ($this->fechaComprobante ?: null) : $this->fechaComprobante,
            'fecha_vencimiento' => $this->fechaVencimiento ?: null,
            'descuento_global_porcentaje' => $this->num($this->descuentoGlobal) > 0 ? $this->num($this->descuentoGlobal) : null,
            'observaciones' => trim($this->observaciones) ?: null,
            'neto_no_gravado' => $discrimina ? $this->num($this->netoNoGravado) : 0,
            'neto_exento' => $discrimina ? $this->num($this->netoExento) : 0,
        ];

        $renglones = collect($this->renglones)
            ->filter(fn ($r) => ! empty($r['articulo_id']))
            ->map(fn ($r) => [
                'articulo_id' => (int) $r['articulo_id'],
                'cantidad_comprada' => $this->num($r['cantidad_comprada']),
                'factor_conversion' => $this->num($r['factor_conversion']),
                'precio_unitario' => $this->num($r['precio_unitario']),
                'descuentos' => $this->parseDescuentos((string) $r['descuentos_texto']),
                'codigo_proveedor_usado' => $r['codigo_proveedor_usado'] ?: null,
                'tipo_iva_id' => $r['tipo_iva_id'] ?: null,
            ])
            ->values()
            ->all();

        $extras = [
            'ivas' => $discrimina
                ? collect($this->ivas)
                    ->filter(fn ($i) => $this->num($i['importe']) > 0 || $this->num($i['base_imponible']) > 0)
                    ->map(fn ($i) => [
                        'alicuota' => $this->num($i['alicuota']),
                        'base_imponible' => $this->num($i['base_imponible']),
                        'importe' => $this->num($i['importe']),
                    ])
                    ->values()
                    ->all()
                : [],
            'conceptos' => collect($this->conceptos)
                ->filter(fn ($c) => $this->num($c['monto']) > 0)
                ->map(fn ($c) => [
                    'tipo' => $c['tipo'],
                    'descripcion' => trim($c['descripcion']) ?: null,
                    'monto' => $this->num($c['monto']),
                    'tipo_iva_id' => $c['tipo_iva_id'] ?: null,
                    'computa_costo' => (bool) $c['computa_costo'],
                ])
                ->values()
                ->all(),
            'percepciones' => $this->esFiscalActual()
                ? collect($this->percepciones)
                    ->filter(fn ($p) => ! empty($p['impuesto_id']) && $this->num($p['monto']) > 0)
                    ->map(fn ($p) => [
                        'impuesto_id' => (int) $p['impuesto_id'],
                        'base_imponible' => $this->num($p['base_imponible']) ?: null,
                        'alicuota' => $this->num($p['alicuota']) ?: null,
                        'monto' => $this->num($p['monto']),
                        'certificado_numero' => trim($p['certificado_numero']) ?: null,
                    ])
                    ->values()
                    ->all()
                : [],
        ];

        return [$data, $renglones, $extras];
    }

    // ==================== Confirmación + pago (D7 #6) ====================

    public function abrirModalPago(): void
    {
        try {
            $this->validarParaGuardar();

            if ($this->esFiscalActual() && ! $this->fechaComprobante) {
                throw new Exception(__('La fecha del comprobante es obligatoria en compras fiscales (rige el período del crédito)'));
            }

            if ($this->esDuplicado()) {
                throw new Exception(__('Ya existe una compra activa de este proveedor con ese tipo y número de comprobante'));
            }

            $proveedor = Proveedor::find($this->proveedorId);

            // La NC no lleva pago: aplica contra el saldo de la compra origen /
            // saldo a favor (RF-21) — confirmación directa.
            if ($this->esNC) {
                $this->confirmar();

                return;
            }

            $this->modalidadPago = $proveedor?->tiene_cuenta_corriente ? 'cta_cte' : 'contado';
            $this->registrarPagoAhora = $this->modalidadPago === 'contado';
            $this->saldoFavorDisponible = $this->proveedorId
                ? MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($this->proveedorId)
                : 0;
            $this->saldoFavorUsado = '';
            $this->pagosIniciales = [$this->renglonPagoVacio()];

            $this->mostrarModalPago = true;
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cerrarModalPago(): void
    {
        $this->mostrarModalPago = false;
    }

    public function agregarRenglonPago(): void
    {
        $this->pagosIniciales[] = $this->renglonPagoVacio();
    }

    public function quitarRenglonPago(int $index): void
    {
        unset($this->pagosIniciales[$index]);
        $this->pagosIniciales = array_values($this->pagosIniciales) ?: [$this->renglonPagoVacio()];
    }

    protected function renglonPagoVacio(): array
    {
        return [
            'forma_pago_id' => null,
            'monto' => '',
            'origen' => 'caja',
            'caja_id' => caja_activa(),
            'cuenta_empresa_id' => null,
        ];
    }

    public function puedePagarAvanzado(): bool
    {
        return (bool) auth()->user()?->hasPermissionTo('func.compras.pagar_avanzado');
    }

    public function confirmar(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.compras.confirmar')) {
            $this->dispatch('notify', type: 'error', message: __('No tenés permiso para confirmar compras'));

            return;
        }

        try {
            $compra = $this->persistirBorrador();
            $this->compraId = $compra->id;

            $confirmada = app(CompraService::class)->confirmarCompra(
                $compra,
                (int) auth()->id(),
                $this->construirPagoInicial((float) $compra->total),
            );

            $this->mostrarModalPago = false;
            $this->prepararResumen($confirmada);
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    protected function construirPagoInicial(float $total): array
    {
        if ($this->esNC) {
            return [];
        }

        // forma_pago del encabezado según la modalidad elegida en el modal.
        $formaPago = $this->modalidadPago === 'cta_cte' ? 'cta_cte' : 'efectivo';

        $pagos = [];
        $saldoFavor = 0.0;

        if ($this->registrarPagoAhora) {
            $saldoFavor = min($this->num($this->saldoFavorUsado), $this->saldoFavorDisponible);

            $pagos = collect($this->pagosIniciales)
                ->map(function ($p) {
                    $origen = $p['origen'] ?? 'caja';

                    // Sin permiso avanzado, todo sale de la caja activa (D14).
                    if (! $this->puedePagarAvanzado()) {
                        $origen = 'caja';
                        $p['caja_id'] = caja_activa();
                    }

                    return [
                        'forma_pago_id' => (int) ($p['forma_pago_id'] ?? 0),
                        'monto' => $this->num($p['monto'] ?? 0),
                        'origen' => $origen,
                        'caja_id' => $p['caja_id'] ?: caja_activa(),
                        'cuenta_empresa_id' => $p['cuenta_empresa_id'] ?: null,
                    ];
                })
                ->filter(fn ($p) => $p['monto'] > 0 && $p['forma_pago_id'] > 0)
                ->values()
                ->all();

            // Contado: intentar mapear la forma_pago del encabezado a la FP elegida.
            if ($this->modalidadPago === 'contado' && $pagos !== []) {
                $codigo = FormaPago::find($pagos[0]['forma_pago_id'])?->codigo;
                $formaPago = in_array($codigo, ['efectivo', 'debito', 'credito', 'tarjeta', 'transferencia', 'cheque'], true)
                    ? $codigo
                    : 'efectivo';
            }
        }

        // Persistir la forma de pago elegida en el encabezado del borrador.
        if ($this->compraId) {
            Compra::whereKey($this->compraId)->update(['forma_pago' => $formaPago]);
        }

        if (! $this->registrarPagoAhora || ($pagos === [] && $saldoFavor <= 0)) {
            return [];
        }

        return [
            'pagos' => $pagos,
            'saldo_favor_usado' => $saldoFavor,
            'caja_id' => caja_activa(),
        ];
    }

    /** Suma del desglose de pago (para mostrar el restante en el modal). */
    public function fondosCargados(): float
    {
        $pagos = collect($this->pagosIniciales)->sum(fn ($p) => $this->num($p['monto'] ?? 0));

        return round($pagos + min($this->num($this->saldoFavorUsado), $this->saldoFavorDisponible), 2);
    }

    // ==================== Resumen post-confirmación (D7 #8) ====================

    protected function prepararResumen(Compra $confirmada): void
    {
        $this->resumen = [
            'numero' => $confirmada->numero_comprobante,
            'total' => (float) $confirmada->total,
            'saldo_pendiente' => (float) $confirmada->saldo_pendiente,
            'es_nc' => $confirmada->esNotaCredito(),
        ];

        // Artículos cuyo margen real quedó bajo la utilidad objetivo (RF-10:
        // el aviso NO bloquea; la revisión es retomable — pantalla en Fase 8).
        // Costos/márgenes son dato sensible: solo con func.costos.ver (RF-20).
        $this->articulosBajoMargen = [];

        if (! $confirmada->esNotaCredito() && auth()->user()?->hasPermissionTo('func.costos.ver')) {
            $costoService = app(CostoService::class);

            foreach ($confirmada->detalles->pluck('articulo')->filter()->unique('id') as $articulo) {
                $margen = $costoService->margenReal($articulo, $this->sucursalId);

                if ($margen !== null && $margen['margen_real'] < $margen['utilidad_objetivo']) {
                    $this->articulosBajoMargen[] = [
                        'nombre' => $articulo->nombre,
                        'costo' => $margen['costo_rector'],
                        'margen_real' => round($margen['margen_real'], 1),
                        'objetivo' => round($margen['utilidad_objetivo'], 1),
                    ];
                }
            }
        }

        $this->mostrarModalResumen = true;
    }

    public function cerrarResumen(): void
    {
        $this->mostrarModalResumen = false;
        $this->dispatch('compra-guardada');
    }

    // ==================== Cierre ====================

    public function cerrar(): void
    {
        $this->dispatch('cerrar-editor-compra');
    }

    // ==================== Render ====================

    public function render()
    {
        return view('livewire.compras.editor-compra', [
            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'cuit', 'tiene_cuenta_corriente', 'dias_pago', 'cuenta_compra_id']),
            'cuits' => Cuit::activos()->orderBy('razon_social')->get(['id', 'razon_social', 'numero_cuit', 'condicion_iva_id']),
            'cuentasCompra' => CuentaCompra::activas()->get(['id', 'nombre']),
            'tiposIva' => TipoIva::where('activo', true)->orderBy('porcentaje')->get(['id', 'nombre', 'porcentaje']),
            'impuestosPercepcion' => Impuesto::activos()
                ->where('naturaleza_default', 'percepcion')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'prefijo']),
            'formasPago' => FormaPago::where('activo', true)
                ->where('es_mixta', false)
                ->where('solo_sistema', false)
                ->whereNot('codigo', 'cta_cte')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'cajasDisponibles' => Caja::porSucursal($this->sucursalId)->abiertas()->get(['id', 'nombre']),
            'cuentasEmpresa' => CuentaEmpresa::where('activo', true)->get(['id', 'nombre']),
            'proveedorSeleccionado' => $this->proveedorId ? Proveedor::find($this->proveedorId) : null,
            'compraOrigen' => $this->ncOrigenId ? Compra::find($this->ncOrigenId) : null,
            'totales' => $this->totales(),
            'advertencias' => $this->advertencias(),
            'esDuplicado' => $this->esDuplicado(),
        ]);
    }
}
