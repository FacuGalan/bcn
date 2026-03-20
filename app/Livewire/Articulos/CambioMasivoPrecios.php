<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\CambioPrecioProgramado;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\HistorialPrecio;
use App\Models\ListaPrecioArticulo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
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

    public string $tipoRedondeo = 'sin_redondeo'; // sin_redondeo, entero, decena, centena

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

    // Alcance del cambio
    public string $alcancePrecio = 'global'; // 'global' o 'sucursal_actual'

    // Programación
    public string $modoAplicacion = 'ahora'; // 'ahora' o 'programar'

    public ?string $fechaProgramada = null;

    public ?string $horaProgramada = null;

    public bool $showProgramados = false;

    // Filtros de cambios programados
    public string $filtroProgramadosEstado = 'pendiente';

    public ?string $filtroProgramadosFechaDesde = null;

    public ?string $filtroProgramadosFechaHasta = null;

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

            // Validar que haya sucursal activa si el alcance es sucursal actual
            if ($this->alcancePrecio === 'sucursal_actual' && ! sucursal_activa()) {
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
        $query = Articulo::with(['categoriaModel', 'etiquetas.grupo'])
            ->where('activo', true);

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

        // Si es sucursal actual, usar precio efectivo de esa sucursal
        $sucursalId = ($this->alcancePrecio === 'sucursal_actual') ? sucursal_activa() : null;

        foreach ($articulos as $articulo) {
            $precioViejo = $sucursalId
                ? $articulo->obtenerPrecioBaseEfectivo($sucursalId)
                : (float) $articulo->precio_base;
            $precioNuevo = $this->calcularNuevoPrecio($precioViejo);

            // Si ya hay un precio editado manualmente, usarlo
            if (isset($this->preciosEditados[$articulo->id])) {
                $precioNuevo = (float) $this->preciosEditados[$articulo->id];
            }

            $this->articulosPreview[$articulo->id] = [
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
     * Aplica el redondeo según la configuración
     */
    protected function aplicarRedondeo(float $precio): float
    {
        return match ($this->tipoRedondeo) {
            'entero' => round($precio),
            'decena' => round($precio / 10) * 10,
            'centena' => round($precio / 100) * 100,
            default => round($precio, 2),
        };
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

        try {
            DB::connection('pymes_tenant')->beginTransaction();

            $articulosActualizados = 0;
            $listasActualizadas = 0;

            // Construir detalle del cambio masivo
            $tipoAjusteLabel = $this->tipoAjuste === 'recargo' ? __('Recargo') : __('Descuento');
            $tipoValorLabel = $this->tipoValor === 'porcentual' ? '%' : '$';
            $redondeoLabel = $this->tipoRedondeo !== 'sin_redondeo' ? ', '.__('redondeo').' '.$this->tipoRedondeo : '';
            $detalleMasivo = "{$tipoAjusteLabel} {$this->valorAjuste}{$tipoValorLabel}{$redondeoLabel}";

            if ($this->alcancePrecio === 'sucursal_actual' && sucursal_activa()) {
                // === Cambio sucursal actual: guardar override en articulos_sucursales ===
                $sucursalId = sucursal_activa();

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
            } else {
                // === Cambio global: actualizar articulos.precio_base + eliminar overrides ===
                $articuloIds = array_keys($this->articulosPreview);

                foreach ($this->articulosPreview as $articuloData) {
                    $articulo = Articulo::find($articuloData['id']);

                    if (! $articulo) {
                        continue;
                    }

                    $precioViejo = (float) $articulo->precio_base;
                    $precioNuevo = (float) $articuloData['precio_nuevo'];

                    // Calcular el porcentaje de cambio para aplicar a listas con precio fijo
                    $porcentajeCambio = $precioViejo > 0
                        ? (($precioNuevo - $precioViejo) / $precioViejo) * 100
                        : 0;

                    // Actualizar precio_base del artículo
                    $articulo->precio_base = $precioNuevo;
                    $articulo->save();
                    $articulosActualizados++;

                    HistorialPrecio::registrar([
                        'articulo_id' => $articulo->id,
                        'precio_anterior' => $precioViejo,
                        'precio_nuevo' => $precioNuevo,
                        'origen' => 'masivo_global',
                        'porcentaje_cambio' => $this->tipoValor === 'porcentual' ? (float) $this->valorAjuste * ($this->tipoAjuste === 'descuento' ? -1 : 1) : null,
                        'detalle' => $detalleMasivo,
                    ]);

                    // Actualizar precios fijos en lista_precio_articulos
                    $registrosLista = ListaPrecioArticulo::where('articulo_id', $articulo->id)
                        ->whereNotNull('precio_fijo')
                        ->get();

                    foreach ($registrosLista as $registro) {
                        $precioFijoViejo = (float) $registro->precio_fijo;
                        $precioFijoNuevo = $precioFijoViejo * (1 + ($porcentajeCambio / 100));
                        $precioFijoNuevo = $this->aplicarRedondeo($precioFijoNuevo);

                        $registro->precio_fijo = $precioFijoNuevo;
                        $registro->precio_base_original = $precioNuevo;
                        $registro->save();
                        $listasActualizadas++;
                    }
                }

                // Registrar historial para overrides que serán eliminados
                $overridesExistentes = DB::connection('pymes_tenant')
                    ->table('articulos_sucursales')
                    ->whereIn('articulo_id', $articuloIds)
                    ->whereNotNull('precio_base')
                    ->get(['articulo_id', 'sucursal_id', 'precio_base']);

                foreach ($overridesExistentes as $override) {
                    $precioGenericoNuevo = (float) ($this->articulosPreview[$override->articulo_id]['precio_nuevo'] ?? 0);
                    HistorialPrecio::registrar([
                        'articulo_id' => $override->articulo_id,
                        'sucursal_id' => $override->sucursal_id,
                        'precio_anterior' => (float) $override->precio_base,
                        'precio_nuevo' => $precioGenericoNuevo,
                        'origen' => 'masivo_global',
                        'detalle' => __('Override eliminado por cambio masivo global'),
                    ]);
                }

                // Eliminar overrides de precio_base en TODAS las sucursales para artículos afectados
                DB::connection('pymes_tenant')
                    ->table('articulos_sucursales')
                    ->whereIn('articulo_id', $articuloIds)
                    ->whereNotNull('precio_base')
                    ->update(['precio_base' => null, 'updated_at' => now()]);
            }

            DB::connection('pymes_tenant')->commit();

            $mensaje = __('Se actualizaron :count artículos', ['count' => $articulosActualizados]);
            if ($listasActualizadas > 0) {
                $mensaje .= ' '.__('y :count precios en listas', ['count' => $listasActualizadas]);
            }

            $this->js("window.notify('".addslashes($mensaje)."', 'success')");
            $this->showConfirmModal = false;

            return redirect()->route('articulos.gestionar');

        } catch (\Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
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

        $sucursalId = ($this->alcancePrecio === 'sucursal_actual') ? sucursal_activa() : null;

        $precioViejo = $sucursalId
            ? $articulo->obtenerPrecioBaseEfectivo($sucursalId)
            : (float) $articulo->precio_base;
        $precioNuevo = $this->calcularNuevoPrecio($precioViejo);

        $this->articulosPreview[$articulo->id] = [
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
                'alcance_precio' => $this->alcancePrecio,
                'sucursal_id' => $this->alcancePrecio === 'sucursal_actual' ? sucursal_activa() : null,
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
    public function cancelarCambioProgramado(int $id)
    {
        $cambio = CambioPrecioProgramado::where('id', $id)
            ->where('estado', 'pendiente')
            ->first();

        if (! $cambio) {
            $this->js("window.notify('".__('Cambio programado no encontrado o ya procesado')."', 'error')");

            return;
        }

        $cambio->update(['estado' => 'cancelado']);
        $this->js("window.notify('".__('Cambio programado cancelado')."', 'success')");
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
