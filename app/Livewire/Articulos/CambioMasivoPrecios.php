<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\ArticuloCosto;
use App\Models\CambioPrecioProgramado;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\HistorialPrecio;
use App\Services\CostoService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Lazy]
class CambioMasivoPrecios extends Component
{
    use WithPagination;

    // Paso actual del wizard
    public int $paso = 1;

    // Filtros
    public array $categoriasSeleccionadas = [];

    public array $etiquetasSeleccionadas = [];

    public string $busquedaCategoria = '';

    public string $busquedaEtiqueta = '';

    public string $busquedaArticuloPreview = '';

    // Configuración del ajuste
    public string $tipoAjuste = 'descuento'; // descuento, recargo

    public string $tipoValor = 'porcentual'; // porcentual, fijo

    public ?float $valorAjuste = null;

    // RF-B12: vocabulario unificado con PrecioService::aplicarRedondeo
    public string $tipoRedondeo = 'ninguno'; // ninguno, entero, decena, centena

    // Bloque C (hardening-circuito-precios): sobre qué aplica el ajuste.
    // 'precio' (comportamiento original) | 'costo' | 'ambos' (mismo % a los dos).
    // Los modos que tocan costo requieren func.costos.editar (RF-C1).
    public string $objetivoCambio = 'precio';

    // Sub-opción del modo 'costo' (RF-C2): tras actualizar el costo,
    // 'no' = solo costo | 'automatico' = repricea los artículos opt-in
    // (precio_administrado_por_utilidad) con la fórmula del sugerido.
    public string $actualizarPrecioTrasCosto = 'no';

    // Preview de artículos
    public array $articulosPreview = [];

    public array $preciosEditados = [];

    // Para mostrar totales
    public int $totalArticulos = 0;

    public float $totalPrecioViejo = 0;

    public float $totalPrecioNuevo = 0;

    // Modal de confirmación
    public bool $showConfirmModal = false;

    // Modal para agregar artículo
    public bool $showModalAgregarArticulo = false;

    public string $busquedaArticuloAgregar = '';

    // Alcance del cambio: siempre sucursal actual (global → futuro Manager)
    public string $alcancePrecio = 'sucursal_actual';

    // Filtro tipo de artículo
    public string $filtroTipoArticulo = 'todos'; // 'todos', 'articulos', 'materia_prima'

    // Programación
    public string $modoAplicacion = 'ahora'; // 'ahora' o 'programar'

    public ?string $fechaProgramada = null;

    public ?string $horaProgramada = null;

    public bool $showProgramados = false;

    // Confirmación de cancelación de cambio programado
    public bool $showCancelProgramadoModal = false;

    public ?int $programadoACancelar = null;

    public ?string $programadoACancelarDesc = null;

    // Filtros de cambios programados
    public string $filtroProgramadosEstado = 'pendiente';

    public ?string $filtroProgramadosFechaDesde = null;

    public ?string $filtroProgramadosFechaHasta = null;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="3" :columns="5" :rows="8" />
        HTML;
    }

    public function mount()
    {
        // Inicializar
    }

    public function updatedCategoriasSeleccionadas()
    {
        if ($this->paso === 2) {
            $this->procesarPreview();
        }
    }

    public function updatedEtiquetasSeleccionadas()
    {
        if ($this->paso === 2) {
            $this->procesarPreview();
        }
    }

    public function updatedFiltroTipoArticulo()
    {
        if ($this->paso === 2) {
            $this->procesarPreview();
        }
    }

    // ==================== Bloque C: objetivo del masivo (RF-C1) ====================

    public function puedeEditarCostos(): bool
    {
        return (bool) auth()->user()?->hasPermissionTo('func.costos.editar');
    }

    public function tocaCosto(): bool
    {
        return in_array($this->objetivoCambio, ['costo', 'ambos'], true);
    }

    public function tocaPrecio(): bool
    {
        return $this->objetivoCambio !== 'costo'
            || $this->actualizarPrecioTrasCosto === 'automatico';
    }

    public function updatedObjetivoCambio(): void
    {
        // Defensa server-side del gate (RF-C1): sin permiso, solo precio.
        if ($this->tocaCosto() && ! $this->puedeEditarCostos()) {
            $this->objetivoCambio = 'precio';
            $this->js("window.notify('".__('No tenés permiso para editar costos')."', 'error')");

            return;
        }

        $this->actualizarPrecioTrasCosto = 'no';

        // Programar es solo para precio de venta (RF-C1).
        if ($this->objetivoCambio !== 'precio') {
            $this->modoAplicacion = 'ahora';
        }

        if ($this->paso === 2) {
            $this->preciosEditados = [];
            $this->procesarPreview();
        }
    }

    /**
     * Avanza al siguiente paso
     */
    public function siguientePaso()
    {
        if ($this->paso === 1) {
            // Validar que haya un ajuste válido
            if ($this->valorAjuste === null || $this->valorAjuste <= 0) {
                $this->js("window.notify('".__('Ingresa un valor de ajuste válido')."', 'error')");

                return;
            }

            // RF-C1: los modos que tocan costo requieren func.costos.editar.
            if ($this->tocaCosto() && ! $this->puedeEditarCostos()) {
                $this->objetivoCambio = 'precio';
                $this->js("window.notify('".__('No tenés permiso para editar costos')."', 'error')");

                return;
            }

            // Validar que haya sucursal activa
            if (! sucursal_activa()) {
                $this->js("window.notify('".__('Selecciona una sucursal primero')."', 'error')");

                return;
            }

            $this->procesarPreview();
            $this->paso = 2;
        }
    }

    /**
     * Vuelve al paso anterior
     */
    public function pasoAnterior()
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    /**
     * Vuelve al listado de artículos
     */
    public function volver()
    {
        return redirect()->route('articulos.gestionar');
    }

    /**
     * Obtiene la query base de artículos filtrados
     */
    protected function getArticulosQuery()
    {
        $query = Articulo::with(['categoriaModel', 'etiquetas.grupo', 'tipoIva'])
            ->where('activo', true);

        // Filtro tipo de artículo
        if ($this->filtroTipoArticulo === 'articulos') {
            $query->where('es_materia_prima', false);
        } elseif ($this->filtroTipoArticulo === 'materia_prima') {
            $query->where('es_materia_prima', true);
        }

        // Filtro de categorías
        if (! empty($this->categoriasSeleccionadas)) {
            $query->whereIn('categoria_id', $this->categoriasSeleccionadas);
        }

        // Filtro de etiquetas
        if (! empty($this->etiquetasSeleccionadas)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasSeleccionadas);
            });
        }

        return $query;
    }

    /**
     * Obtiene los artículos del preview filtrados por búsqueda
     */
    public function getArticulosPreviewFiltrados(): array
    {
        if (empty($this->busquedaArticuloPreview)) {
            return $this->articulosPreview;
        }

        $busqueda = strtolower($this->busquedaArticuloPreview);

        return array_filter($this->articulosPreview, function ($articulo) use ($busqueda) {
            return str_contains(strtolower($articulo['codigo']), $busqueda)
                || str_contains(strtolower($articulo['nombre']), $busqueda);
        });
    }

    /**
     * Procesa la vista previa de los cambios
     */
    public function procesarPreview()
    {
        $articulos = $this->getArticulosQuery()->get();

        $this->articulosPreview = [];
        $this->totalArticulos = 0;
        $this->totalPrecioViejo = 0;
        $this->totalPrecioNuevo = 0;

        // Siempre usar precio efectivo de la sucursal activa
        $sucursalId = sucursal_activa();

        // RF-C2: costos en bloque (fila sucursal con fallback consolidada) y
        // gate de IVA UNA sola vez para el margen del preview.
        $costosSucursal = [];
        $costosConsolidados = [];
        $computaIva = false;

        if ($this->tocaCosto()) {
            $ids = $articulos->pluck('id');
            $costosSucursal = ArticuloCosto::whereIn('articulo_id', $ids)
                ->where('sucursal_id', $sucursalId)
                ->pluck('costo_ultimo', 'articulo_id')
                ->all();
            $costosConsolidados = ArticuloCosto::whereIn('articulo_id', $ids)
                ->whereNull('sucursal_id')
                ->pluck('costo_ultimo', 'articulo_id')
                ->all();
            $computaIva = app(CostoService::class)->comercioComputaIva($sucursalId ?: null);
        }

        foreach ($articulos as $articulo) {
            $precioViejo = $sucursalId
                ? $articulo->obtenerPrecioBaseEfectivo($sucursalId)
                : (float) $articulo->precio_base;
            $precioNuevo = $this->objetivoCambio === 'costo'
                ? $precioViejo
                : $this->calcularNuevoPrecio($precioViejo);

            // Si ya hay un precio editado manualmente, usarlo
            if ($this->objetivoCambio !== 'costo' && isset($this->preciosEditados[$articulo->id])) {
                $precioNuevo = (float) $this->preciosEditados[$articulo->id];
            }

            $fila = [
                'id' => $articulo->id,
                'codigo' => $articulo->codigo,
                'nombre' => $articulo->nombre,
                'categoria' => $articulo->categoriaModel?->nombre ?? __('Sin categoría'),
                'categoria_color' => $articulo->categoriaModel?->color ?? '#6B7280',
                'precio_viejo' => $precioViejo,
                'precio_nuevo' => $precioNuevo,
                'diferencia' => $precioNuevo - $precioViejo,
                'diferencia_porcentaje' => $precioViejo > 0 ? round((($precioNuevo - $precioViejo) / $precioViejo) * 100, 2) : 0,
            ];

            if ($this->tocaCosto()) {
                $base = $costosSucursal[$articulo->id] ?? $costosConsolidados[$articulo->id] ?? null;
                $costoViejo = $base !== null ? (float) $base : null;
                $costoNuevo = $costoViejo !== null ? $this->calcularNuevoCosto($costoViejo) : null;

                // Margen resultante (si hay precio): misma división que la venta.
                $alicuota = $computaIva ? (float) ($articulo->tipoIva?->porcentaje ?? 0) : 0.0;
                $neto = $precioNuevo / (1 + $alicuota / 100);

                $fila['costo_viejo'] = $costoViejo;
                $fila['costo_nuevo'] = $costoNuevo;
                // Sin costo base no hay sobre qué aplicar el %: se saltea (RF-C2).
                $fila['sin_costo'] = $costoViejo === null;
                $fila['margen_nuevo'] = ($costoNuevo !== null && $costoNuevo > 0 && $precioNuevo > 0)
                    ? round(($neto - $costoNuevo) / $costoNuevo * 100, 1)
                    : null;
            }

            $this->articulosPreview[$articulo->id] = $fila;

            $this->totalArticulos++;
            $this->totalPrecioViejo += $precioViejo;
            $this->totalPrecioNuevo += $precioNuevo;
        }
    }

    /**
     * Calcula el nuevo precio según la configuración
     */
    protected function calcularNuevoPrecio(float $precioActual): float
    {
        $nuevoPrecio = $precioActual;

        if ($this->tipoValor === 'porcentual') {
            $porcentaje = $this->tipoAjuste === 'descuento'
                ? -$this->valorAjuste
                : $this->valorAjuste;

            $nuevoPrecio = $precioActual * (1 + ($porcentaje / 100));
        } else {
            // Fijo
            $nuevoPrecio = $this->tipoAjuste === 'descuento'
                ? $precioActual - $this->valorAjuste
                : $precioActual + $this->valorAjuste;
        }

        // Asegurar que no sea negativo
        $nuevoPrecio = max(0, $nuevoPrecio);

        // Aplicar redondeo
        return $this->aplicarRedondeo($nuevoPrecio);
    }

    /**
     * Aplica el redondeo según la configuración (lógica en PrecioService,
     * compartida con CostoService::precioSugerido).
     */
    protected function aplicarRedondeo(float $precio): float
    {
        return app(\App\Services\PrecioService::class)->aplicarRedondeo($precio, $this->tipoRedondeo);
    }

    /**
     * RF-C2: el mismo ajuste aplicado al costo último. SIN el redondeo de
     * precios (redondear un costo a decena/centena lo distorsiona): 4
     * decimales, como el resto de la cadena de costos.
     */
    protected function calcularNuevoCosto(float $costoActual): float
    {
        if ($this->tipoValor === 'porcentual') {
            $porcentaje = $this->tipoAjuste === 'descuento' ? -$this->valorAjuste : $this->valorAjuste;
            $nuevo = $costoActual * (1 + ($porcentaje / 100));
        } else {
            $nuevo = $this->tipoAjuste === 'descuento'
                ? $costoActual - $this->valorAjuste
                : $costoActual + $this->valorAjuste;
        }

        return round(max(0, $nuevo), 4);
    }

    /**
     * Actualiza un precio editado manualmente
     */
    public function actualizarPrecioManual(int $articuloId, $nuevoPrecio)
    {
        $nuevoPrecio = (float) str_replace(',', '.', $nuevoPrecio);

        if ($nuevoPrecio < 0) {
            $nuevoPrecio = 0;
        }

        $this->preciosEditados[$articuloId] = $nuevoPrecio;

        if (isset($this->articulosPreview[$articuloId])) {
            $precioViejo = $this->articulosPreview[$articuloId]['precio_viejo'];
            $this->articulosPreview[$articuloId]['precio_nuevo'] = $nuevoPrecio;
            $this->articulosPreview[$articuloId]['diferencia'] = $nuevoPrecio - $precioViejo;
            $this->articulosPreview[$articuloId]['diferencia_porcentaje'] = $precioViejo > 0
                ? round((($nuevoPrecio - $precioViejo) / $precioViejo) * 100, 2)
                : 0;
        }

        // Recalcular totales
        $this->totalPrecioNuevo = array_sum(array_column($this->articulosPreview, 'precio_nuevo'));
    }

    /**
     * Recalcula todos los precios según la configuración actual
     */
    public function recalcular()
    {
        $this->preciosEditados = [];
        $this->procesarPreview();
        $this->js("window.notify('".__('Precios recalculados')."', 'success')");
    }

    /**
     * Muestra el modal de confirmación
     */
    public function confirmarCambios()
    {
        if (empty($this->articulosPreview)) {
            $this->js("window.notify('".__('No hay artículos para actualizar')."', 'error')");

            return;
        }

        $this->showConfirmModal = true;
    }

    /**
     * Cierra el modal de confirmación
     */
    public function cancelarConfirmacion()
    {
        $this->showConfirmModal = false;
    }

    /**
     * Aplica los cambios de precios
     */
    public function aplicarCambios()
    {
        if (empty($this->articulosPreview)) {
            $this->js("window.notify('".__('No hay artículos para actualizar')."', 'error')");

            return;
        }

        // RF-C1: defensa final del gate de costos.
        if ($this->tocaCosto() && ! $this->puedeEditarCostos()) {
            $this->js("window.notify('".__('No tenés permiso para editar costos')."', 'error')");

            return;
        }

        try {
            DB::connection('pymes_tenant')->beginTransaction();

            $articulosActualizados = 0;
            $costosActualizados = 0;
            $repriceados = 0;
            $listasActualizadas = 0;

            // Construir detalle del cambio masivo
            $tipoAjusteLabel = $this->tipoAjuste === 'recargo' ? __('Recargo') : __('Descuento');
            $tipoValorLabel = $this->tipoValor === 'porcentual' ? '%' : '$';
            $redondeoLabel = ! in_array($this->tipoRedondeo, ['ninguno', 'sin_redondeo'], true) ? ', '.__('redondeo').' '.$this->tipoRedondeo : '';
            $detalleMasivo = "{$tipoAjusteLabel} {$this->valorAjuste}{$tipoValorLabel}{$redondeoLabel}";

            $sucursalId = sucursal_activa();

            // === Costos (RF-C2/C3): costo último de la sucursal activa vía la
            // puerta única (CostoService::actualizarManual, origen 'masivo').
            $costosAplicadosIds = [];

            if ($this->tocaCosto()) {
                $costoService = app(CostoService::class);
                $articulos = Articulo::whereIn('id', array_keys($this->articulosPreview))->get()->keyBy('id');

                foreach ($this->articulosPreview as $articuloData) {
                    // Sin costo base no hay sobre qué aplicar el % (RF-C2).
                    if (! empty($articuloData['sin_costo']) || ($articuloData['costo_nuevo'] ?? null) === null) {
                        continue;
                    }

                    $articulo = $articulos->get($articuloData['id']);

                    if ($articulo === null) {
                        continue;
                    }

                    $costoService->actualizarManual(
                        $articulo,
                        $sucursalId,
                        'ultimo',
                        (float) $articuloData['costo_nuevo'],
                        (int) auth()->id(),
                        origen: 'masivo',
                    );

                    $costosAplicadosIds[] = $articulo->id;
                    $costosActualizados++;
                }
            }

            // === Precio de venta: override de la sucursal activa (modo
            // 'precio' y 'ambos'; en 'costo' solo vía repricing automático).
            if ($this->objetivoCambio !== 'costo') {
                foreach ($this->articulosPreview as $articuloData) {
                    $precioNuevo = (float) $articuloData['precio_nuevo'];

                    $exists = DB::connection('pymes_tenant')
                        ->table('articulos_sucursales')
                        ->where('articulo_id', $articuloData['id'])
                        ->where('sucursal_id', $sucursalId)
                        ->exists();

                    if ($exists) {
                        DB::connection('pymes_tenant')
                            ->table('articulos_sucursales')
                            ->where('articulo_id', $articuloData['id'])
                            ->where('sucursal_id', $sucursalId)
                            ->update(['precio_base' => $precioNuevo, 'updated_at' => now()]);
                    } else {
                        DB::connection('pymes_tenant')
                            ->table('articulos_sucursales')
                            ->insert([
                                'articulo_id' => $articuloData['id'],
                                'sucursal_id' => $sucursalId,
                                'activo' => true,
                                'modo_stock' => 'ninguno',
                                'vendible' => true,
                                'precio_base' => $precioNuevo,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    HistorialPrecio::registrar([
                        'articulo_id' => $articuloData['id'],
                        'sucursal_id' => $sucursalId,
                        'precio_anterior' => (float) $articuloData['precio_viejo'],
                        'precio_nuevo' => $precioNuevo,
                        'origen' => 'masivo_sucursal',
                        'porcentaje_cambio' => $this->tipoValor === 'porcentual' ? (float) $this->valorAjuste * ($this->tipoAjuste === 'descuento' ? -1 : 1) : null,
                        'detalle' => $detalleMasivo,
                    ]);

                    $articulosActualizados++;
                }
            }

            // === Sub-opción del modo costo (RF-C2): repricing automático de
            // los opt-in con la fórmula compartida (RF-C4).
            if ($this->objetivoCambio === 'costo' && $this->actualizarPrecioTrasCosto === 'automatico' && $costosAplicadosIds !== []) {
                $repriceados = count(app(CostoService::class)->repricearArticulos(
                    $costosAplicadosIds,
                    (int) $sucursalId,
                    (int) auth()->id(),
                    __('cambio masivo de costos'),
                ));
            }

            DB::connection('pymes_tenant')->commit();

            $partes = [];
            if ($articulosActualizados > 0) {
                $partes[] = __('Se actualizaron :count artículos', ['count' => $articulosActualizados]);
            }
            if ($costosActualizados > 0) {
                $partes[] = __('Se actualizaron :count costos', ['count' => $costosActualizados]);
            }
            if ($repriceados > 0) {
                $partes[] = __(':count precios repriceados por utilidad', ['count' => $repriceados]);
            }
            if ($listasActualizadas > 0) {
                $partes[] = __('y :count precios en listas', ['count' => $listasActualizadas]);
            }

            $mensaje = $partes === [] ? __('No hubo cambios para aplicar') : implode('. ', $partes);

            $this->js("window.notify('".addslashes($mensaje)."', 'success')");
            $this->showConfirmModal = false;

            return redirect()->route('articulos.gestionar');

        } catch (\Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            \Illuminate\Support\Facades\Log::error('CambioMasivoPrecios::aplicarCambios', [
                'objetivo' => $this->objetivoCambio,
                'error' => $e->getMessage(),
            ]);
            $this->js("window.notify('".__('Error al aplicar cambios').': '.addslashes($e->getMessage())."', 'error')");
        }
    }

    /**
     * Quita un artículo del preview
     */
    public function quitarArticulo(int $articuloId)
    {
        if (isset($this->articulosPreview[$articuloId])) {
            $this->totalPrecioViejo -= $this->articulosPreview[$articuloId]['precio_viejo'];
            $this->totalPrecioNuevo -= $this->articulosPreview[$articuloId]['precio_nuevo'];
            $this->totalArticulos--;

            unset($this->articulosPreview[$articuloId]);
            unset($this->preciosEditados[$articuloId]);
        }
    }

    /**
     * Abre el modal para agregar artículo
     */
    public function abrirModalAgregarArticulo()
    {
        $this->busquedaArticuloAgregar = '';
        $this->showModalAgregarArticulo = true;
    }

    /**
     * Cierra el modal para agregar artículo
     */
    public function cerrarModalAgregarArticulo()
    {
        $this->showModalAgregarArticulo = false;
        $this->busquedaArticuloAgregar = '';
    }

    /**
     * Obtiene artículos para agregar (excluye los que ya están en la lista)
     */
    public function getArticulosParaAgregarProperty()
    {
        if (strlen($this->busquedaArticuloAgregar) < 2) {
            return collect();
        }

        $idsExistentes = array_keys($this->articulosPreview);

        return Articulo::with('categoriaModel')
            ->where('activo', true)
            ->whereNotIn('id', $idsExistentes)
            ->where(function ($query) {
                $query->where('codigo', 'like', '%'.$this->busquedaArticuloAgregar.'%')
                    ->orWhere('nombre', 'like', '%'.$this->busquedaArticuloAgregar.'%');
            })
            ->limit(10)
            ->get();
    }

    /**
     * Calcula el precio preview para mostrar en el modal
     */
    public function calcularPrecioPreview(float $precioActual): float
    {
        return $this->calcularNuevoPrecio($precioActual);
    }

    /**
     * Agrega un artículo manualmente a la lista
     */
    public function agregarArticuloManual(int $articuloId)
    {
        // Verificar que no esté ya en la lista
        if (isset($this->articulosPreview[$articuloId])) {
            $this->js("window.notify('".__('Este artículo ya está en la lista')."', 'warning')");

            return;
        }

        $articulo = Articulo::with('categoriaModel')->find($articuloId);

        if (! $articulo) {
            $this->js("window.notify('".__('Artículo no encontrado')."', 'error')");

            return;
        }

        $sucursalId = sucursal_activa();

        $precioViejo = $sucursalId
            ? $articulo->obtenerPrecioBaseEfectivo($sucursalId)
            : (float) $articulo->precio_base;
        $precioNuevo = $this->objetivoCambio === 'costo'
            ? $precioViejo
            : $this->calcularNuevoPrecio($precioViejo);

        $fila = [
            'id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'categoria' => $articulo->categoriaModel?->nombre ?? __('Sin categoría'),
            'categoria_color' => $articulo->categoriaModel?->color ?? '#6B7280',
            'precio_viejo' => $precioViejo,
            'precio_nuevo' => $precioNuevo,
            'diferencia' => $precioNuevo - $precioViejo,
            'diferencia_porcentaje' => $precioViejo > 0 ? round((($precioNuevo - $precioViejo) / $precioViejo) * 100, 2) : 0,
        ];

        if ($this->tocaCosto()) {
            $base = ArticuloCosto::where('articulo_id', $articulo->id)
                ->where(fn ($q) => $q->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id'))
                ->orderByRaw('sucursal_id IS NULL') // primero la fila de la sucursal
                ->value('costo_ultimo');
            $costoViejo = $base !== null ? (float) $base : null;
            $costoNuevo = $costoViejo !== null ? $this->calcularNuevoCosto($costoViejo) : null;

            $computaIva = app(CostoService::class)->comercioComputaIva($sucursalId ?: null);
            $alicuota = $computaIva ? (float) ($articulo->tipoIva?->porcentaje ?? 0) : 0.0;
            $neto = $precioNuevo / (1 + $alicuota / 100);

            $fila['costo_viejo'] = $costoViejo;
            $fila['costo_nuevo'] = $costoNuevo;
            $fila['sin_costo'] = $costoViejo === null;
            $fila['margen_nuevo'] = ($costoNuevo !== null && $costoNuevo > 0 && $precioNuevo > 0)
                ? round(($neto - $costoNuevo) / $costoNuevo * 100, 1)
                : null;
        }

        $this->articulosPreview[$articulo->id] = $fila;

        $this->totalArticulos++;
        $this->totalPrecioViejo += $precioViejo;
        $this->totalPrecioNuevo += $precioNuevo;

        $this->busquedaArticuloAgregar = '';
        $this->js("window.notify('".__('Artículo agregado a la lista')."', 'success')");
    }

    /**
     * Programa un cambio de precios para ejecutarse en una fecha/hora futura.
     */
    public function programarCambios()
    {
        // RF-C1: la programación sigue siendo SOLO de precios de venta (el
        // circuito de programados no conoce costos).
        if ($this->objetivoCambio !== 'precio') {
            $this->js("window.notify('".__('Programar solo está disponible para el precio de venta')."', 'error')");

            return;
        }

        if (empty($this->articulosPreview)) {
            $this->js("window.notify('".__('No hay artículos para actualizar')."', 'error')");

            return;
        }

        if (! $this->fechaProgramada || ! $this->horaProgramada) {
            $this->js("window.notify('".__('Selecciona fecha y hora para programar')."', 'error')");

            return;
        }

        $fechaHora = \Carbon\Carbon::parse("{$this->fechaProgramada} {$this->horaProgramada}");

        if ($fechaHora->isPast()) {
            $this->js("window.notify('".__('La fecha programada debe ser futura')."', 'error')");

            return;
        }

        try {
            CambioPrecioProgramado::create([
                'usuario_id' => auth()->id(),
                'fecha_programada' => $fechaHora,
                'estado' => 'pendiente',
                'alcance_precio' => 'sucursal_actual',
                'sucursal_id' => sucursal_activa(),
                'tipo_ajuste' => $this->tipoAjuste,
                'tipo_valor' => $this->tipoValor,
                'valor_ajuste' => $this->valorAjuste,
                'tipo_redondeo' => $this->tipoRedondeo,
                'total_articulos' => count($this->articulosPreview),
                'articulos_data' => array_values($this->articulosPreview),
            ]);

            $this->showConfirmModal = false;
            $this->modoAplicacion = 'ahora';
            $this->fechaProgramada = null;
            $this->horaProgramada = null;

            $this->js("window.notify('".addslashes(__('Cambio de precios programado correctamente para :fecha', ['fecha' => $fechaHora->format('d/m/Y H:i')]))."', 'success')");

            return redirect()->route('articulos.cambio-masivo-precios');
        } catch (\Exception $e) {
            $this->js("window.notify('".__('Error al programar cambio').': '.addslashes($e->getMessage())."', 'error')");
        }
    }

    /**
     * Cancela un cambio programado pendiente.
     */
    public function confirmCancelProgramado(int $id): void
    {
        $cambio = CambioPrecioProgramado::where('id', $id)
            ->where('estado', 'pendiente')
            ->first();

        if (! $cambio) {
            $this->js("window.notify('".__('Cambio programado no encontrado o ya procesado')."', 'error')");

            return;
        }

        $this->programadoACancelar = $cambio->id;
        $this->programadoACancelarDesc = $cambio->descripcion_ajuste.' — '.$cambio->total_articulos.' '.__('artículos').' — '.$cambio->fecha_programada->format('d/m/Y H:i');
        $this->showCancelProgramadoModal = true;
    }

    public function cancelarCambioProgramado(): void
    {
        if (! $this->programadoACancelar) {
            return;
        }

        $cambio = CambioPrecioProgramado::where('id', $this->programadoACancelar)
            ->where('estado', 'pendiente')
            ->first();

        if ($cambio) {
            $cambio->update(['estado' => 'cancelado']);
            $this->js("window.notify('".__('Cambio programado cancelado')."', 'success')");
        }

        $this->closeCancelProgramadoModal();
    }

    public function closeCancelProgramadoModal(): void
    {
        $this->showCancelProgramadoModal = false;
        $this->programadoACancelar = null;
        $this->programadoACancelarDesc = null;
    }

    /**
     * Obtiene los cambios programados filtrados.
     */
    public function getCambiosProgramadosProperty()
    {
        $query = CambioPrecioProgramado::orderByDesc('created_at');

        if ($this->filtroProgramadosEstado !== 'todos') {
            $query->where('estado', $this->filtroProgramadosEstado);
        }

        if ($this->filtroProgramadosFechaDesde) {
            $query->whereDate('fecha_programada', '>=', $this->filtroProgramadosFechaDesde);
        }

        if ($this->filtroProgramadosFechaHasta) {
            $query->whereDate('fecha_programada', '<=', $this->filtroProgramadosFechaHasta);
        }

        return $query->limit(20)->get();
    }

    /**
     * Cuenta de cambios pendientes para el badge.
     */
    public function getPendientesCountProperty(): int
    {
        return CambioPrecioProgramado::where('estado', 'pendiente')->count();
    }

    public function toggleProgramados()
    {
        $this->showProgramados = ! $this->showProgramados;
    }

    public function render()
    {
        // Categorías con filtrado
        $categoriasQuery = Categoria::where('activo', true);
        if ($this->busquedaCategoria) {
            $categoriasQuery->where('nombre', 'like', '%'.$this->busquedaCategoria.'%');
        }
        $categorias = $categoriasQuery->orderBy('nombre')->get();

        // Grupos de etiquetas con filtrado igual que en GestionarArticulos
        $busqueda = $this->busquedaEtiqueta;

        $gruposEtiquetasQuery = GrupoEtiqueta::where('activo', true);

        if ($busqueda) {
            $gruposEtiquetasQuery->where(function ($query) use ($busqueda) {
                $query->where('nombre', 'like', '%'.$busqueda.'%')
                    ->orWhereHas('etiquetas', function ($q) use ($busqueda) {
                        $q->where('activo', true)
                            ->where('nombre', 'like', '%'.$busqueda.'%');
                    });
            });
        }

        $gruposEtiquetas = $gruposEtiquetasQuery->orderBy('orden')->orderBy('nombre')->get();

        // Cargar etiquetas filtradas para cada grupo
        foreach ($gruposEtiquetas as $grupo) {
            $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

            // Si hay búsqueda y el grupo NO coincide, filtrar etiquetas
            if ($busqueda && ! str_contains(strtolower($grupo->nombre), strtolower($busqueda))) {
                $etiquetasQuery->where('nombre', 'like', '%'.$busqueda.'%');
            }

            $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
        }

        return view('livewire.articulos.cambio-masivo-precios', [
            'categorias' => $categorias,
            'gruposEtiquetas' => $gruposEtiquetas,
            'articulosPreviewFiltrados' => $this->getArticulosPreviewFiltrados(),
            'articulosParaAgregar' => $this->getArticulosParaAgregarProperty(),
        ]);
    }
}
