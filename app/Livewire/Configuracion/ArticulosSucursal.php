<?php

namespace App\Livewire\Configuracion;

use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\ArticuloGrupoOpcionalOpcion;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Traits\SucursalAware;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para configurar artículos por sucursal.
 *
 * Tabla principal: activo, modo_stock, vendible por artículo.
 * Modal de configuración detallada: opcionales (precios, activo, disponible)
 * y receta override por sucursal.
 */
#[Layout('layouts.app')]
class ArticulosSucursal extends Component
{
    use WithPagination;
    use SucursalAware;

    // Búsqueda y filtros
    public string $search = '';
    public array $categoriasSeleccionadas = [];
    public array $etiquetasSeleccionadasFiltro = [];
    public string $busquedaCategoriaFiltro = '';
    public string $busquedaEtiquetaFiltro = '';
    public string $filterTipo = 'all';
    public bool $showFilters = false;

    // Estado de artículos en la sucursal: [articulo_id => ['activo','modo_stock','vendible']]
    public array $articulosConfig = [];

    // Modal de configuración detallada
    public bool $showConfigModal = false;
    public ?int $configArticuloId = null;
    public string $configArticuloNombre = '';
    public array $configGrupos = [];

    // Sub-modal receta override
    public bool $showRecetaModal = false;
    public ?int $recetaId = null;
    public bool $recetaEsOverride = false;
    public ?string $recetaSucursalNombre = null;
    public array $recetaIngredientes = [];
    public string $busquedaIngrediente = '';
    public array $resultadosBusqueda = [];
    public string $recetaCantidadProducida = '1.000';
    public string $recetaNotas = '';

    // Sub-modal confirmar eliminar receta override
    public bool $showDeleteRecetaModal = false;

    public function mount(): void
    {
        $this->loadArticulosConfig();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoriasSeleccionadas(): void
    {
        $this->resetPage();
    }

    public function updatingEtiquetasSeleccionadasFiltro(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->reset('articulosConfig');
        $this->loadArticulosConfig();
        $this->showConfigModal = false;
        $this->showRecetaModal = false;
        $this->showDeleteRecetaModal = false;
    }

    /**
     * Carga la configuración de todos los artículos para la sucursal seleccionada.
     */
    protected function loadArticulosConfig(): void
    {
        if (!sucursal_activa()) {
            $this->articulosConfig = [];
            return;
        }

        $this->articulosConfig = [];

        // Obtener todas las relaciones existentes para esta sucursal de una sola vez
        $pivots = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('sucursal_id', sucursal_activa())
            ->get(['articulo_id', 'activo', 'modo_stock', 'vendible'])
            ->keyBy('articulo_id');

        $todosArticulosIds = Articulo::pluck('id');

        foreach ($todosArticulosIds as $articuloId) {
            $pivot = $pivots->get($articuloId);
            if ($pivot) {
                $this->articulosConfig[$articuloId] = [
                    'activo' => (bool) $pivot->activo,
                    'modo_stock' => $pivot->modo_stock ?? 'ninguno',
                    'vendible' => (bool) ($pivot->vendible ?? true),
                ];
            } else {
                // Sin relación = defaults
                $this->articulosConfig[$articuloId] = [
                    'activo' => true,
                    'modo_stock' => 'ninguno',
                    'vendible' => true,
                ];
            }
        }
    }

    /**
     * Alterna el estado activo de un artículo
     */
    public function toggleArticulo(int $articuloId): void
    {
        if (!sucursal_activa()) return;

        $config = $this->articulosConfig[$articuloId] ?? ['activo' => true, 'modo_stock' => 'ninguno', 'vendible' => true];
        $nuevoEstado = !$config['activo'];

        $this->guardarPivot($articuloId, ['activo' => $nuevoEstado]);
        $this->articulosConfig[$articuloId]['activo'] = $nuevoEstado;
    }

    /**
     * Cambia el modo_stock de un artículo
     */
    public function cambiarModoStock(int $articuloId, string $modo): void
    {
        if (!sucursal_activa()) return;
        if (!in_array($modo, ['ninguno', 'unitario', 'receta'])) return;

        $this->guardarPivot($articuloId, ['modo_stock' => $modo]);
        $this->articulosConfig[$articuloId]['modo_stock'] = $modo;

        // Auto-crear fila en stock para que aparezca en inventario
        if ($modo !== 'ninguno') {
            Stock::firstOrCreate(
                ['articulo_id' => $articuloId, 'sucursal_id' => sucursal_activa()],
                ['cantidad' => 0, 'ultima_actualizacion' => now()]
            );
        }
    }

    /**
     * Alterna el estado vendible de un artículo
     */
    public function toggleVendible(int $articuloId): void
    {
        if (!sucursal_activa()) return;

        $config = $this->articulosConfig[$articuloId] ?? ['activo' => true, 'modo_stock' => 'ninguno', 'vendible' => true];
        $nuevoEstado = !$config['vendible'];

        $this->guardarPivot($articuloId, ['vendible' => $nuevoEstado]);
        $this->articulosConfig[$articuloId]['vendible'] = $nuevoEstado;
    }

    /**
     * Guarda/crea la fila pivot del artículo-sucursal
     */
    protected function guardarPivot(int $articuloId, array $datos): void
    {
        $exists = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', sucursal_activa())
            ->exists();

        if ($exists) {
            DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->where('articulo_id', $articuloId)
                ->where('sucursal_id', sucursal_activa())
                ->update(array_merge($datos, ['updated_at' => now()]));
        } else {
            DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->insert(array_merge([
                    'articulo_id' => $articuloId,
                    'sucursal_id' => sucursal_activa(),
                    'activo' => true,
                    'modo_stock' => 'ninguno',
                    'vendible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $datos));
        }
    }

    public function selectAll(): void
    {
        if (!sucursal_activa()) return;

        DB::connection('pymes_tenant')->transaction(function () {
            $todosIds = Articulo::pluck('id');
            foreach ($todosIds as $articuloId) {
                $this->guardarPivot($articuloId, ['activo' => true]);
            }
        });

        $this->loadArticulosConfig();
        $this->dispatch('notify', message: __('Todos los artículos activados'), type: 'success');
    }

    public function deselectAll(): void
    {
        if (!sucursal_activa()) return;

        DB::connection('pymes_tenant')->transaction(function () {
            $todosIds = Articulo::pluck('id');
            foreach ($todosIds as $articuloId) {
                $this->guardarPivot($articuloId, ['activo' => false]);
            }
        });

        $this->loadArticulosConfig();
        $this->dispatch('notify', message: __('Todos los artículos desactivados'), type: 'success');
    }

    // ===== Modal de Configuración Detallada =====

    public function abrirConfiguracion(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $this->configArticuloId = $articulo->id;
        $this->configArticuloNombre = $articulo->nombre;

        $this->cargarGruposConfig();
        $this->showConfigModal = true;
    }

    protected function cargarGruposConfig(): void
    {
        if (!$this->configArticuloId || !sucursal_activa()) return;

        $asignaciones = ArticuloGrupoOpcional::with([
                'grupoOpcional',
                'opciones.opcional',
            ])
            ->where('articulo_id', $this->configArticuloId)
            ->where('sucursal_id', sucursal_activa())
            ->orderBy('orden')
            ->get();

        $this->configGrupos = $asignaciones->map(function ($asig) {
            return [
                'asignacion_id' => $asig->id,
                'grupo_id' => $asig->grupo_opcional_id,
                'nombre' => $asig->grupoOpcional->nombre,
                'tipo' => $asig->grupoOpcional->tipo,
                'activo' => $asig->activo,
                'opciones' => $asig->opciones->map(fn($op) => [
                    'opcion_id' => $op->id,
                    'opcional_id' => $op->opcional_id,
                    'nombre' => $op->opcional->nombre,
                    'precio_extra' => (string) $op->precio_extra,
                    'activo' => $op->activo,
                    'disponible' => $op->disponible,
                ])->toArray(),
            ];
        })->toArray();
    }

    public function toggleGrupoActivo(int $asignacionId): void
    {
        $asig = ArticuloGrupoOpcional::find($asignacionId);
        if (!$asig) return;

        $asig->activo = !$asig->activo;
        $asig->save();
        $this->cargarGruposConfig();
    }

    public function toggleOpcionActivo(int $opcionId): void
    {
        $opcion = ArticuloGrupoOpcionalOpcion::find($opcionId);
        if (!$opcion) return;

        $opcion->activo = !$opcion->activo;
        $opcion->save();
        $this->cargarGruposConfig();
    }

    public function toggleOpcionDisponible(int $opcionId): void
    {
        $opcion = ArticuloGrupoOpcionalOpcion::find($opcionId);
        if (!$opcion) return;

        $opcion->disponible = !$opcion->disponible;
        $opcion->save();
        $this->cargarGruposConfig();
    }

    public function actualizarPrecioOpcion(int $opcionId, string $precio): void
    {
        $opcion = ArticuloGrupoOpcionalOpcion::find($opcionId);
        if (!$opcion) return;

        $opcion->precio_extra = max(0, (float) $precio);
        $opcion->save();
    }

    public function restablecerDefaults(int $asignacionId): void
    {
        $asig = ArticuloGrupoOpcional::find($asignacionId);
        if (!$asig) return;

        $asig->restablecerDefaults();
        $this->cargarGruposConfig();
        $this->dispatch('notify', message: __('Valores restablecidos correctamente'), type: 'success');
    }

    public function cerrarConfiguracion(): void
    {
        $this->showConfigModal = false;
        $this->configArticuloId = null;
        $this->configArticuloNombre = '';
        $this->configGrupos = [];
    }

    // ===== Receta Override por Sucursal =====

    public function abrirRecetaOverride(): void
    {
        if (!$this->configArticuloId || !sucursal_activa()) return;

        $sucursal = Sucursal::find(sucursal_activa());
        $this->recetaSucursalNombre = $sucursal?->nombre;

        // Buscar override primero, luego default
        $override = Receta::where('recetable_type', 'Articulo')
            ->where('recetable_id', $this->configArticuloId)
            ->where('sucursal_id', sucursal_activa())
            ->with('ingredientes.articulo')
            ->first();

        if ($override) {
            $this->recetaEsOverride = true;
            $this->recetaId = $override->id;
            $this->recetaCantidadProducida = (string) $override->cantidad_producida;
            $this->recetaNotas = $override->notas ?? '';
            $this->recetaIngredientes = $override->ingredientes->map(fn($ing) => [
                'articulo_id' => $ing->articulo_id,
                'codigo' => $ing->articulo->codigo ?? '',
                'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
                'unidad_medida' => $ing->articulo->unidad_medida ?? '',
                'cantidad' => (string) $ing->cantidad,
            ])->toArray();
        } else {
            // Buscar default
            $default = Receta::where('recetable_type', 'Articulo')
                ->where('recetable_id', $this->configArticuloId)
                ->whereNull('sucursal_id')
                ->with('ingredientes.articulo')
                ->first();

            $this->recetaEsOverride = false;
            $this->recetaId = null;

            if ($default) {
                $this->recetaCantidadProducida = (string) $default->cantidad_producida;
                $this->recetaNotas = $default->notas ?? '';
                $this->recetaIngredientes = $default->ingredientes->map(fn($ing) => [
                    'articulo_id' => $ing->articulo_id,
                    'codigo' => $ing->articulo->codigo ?? '',
                    'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
                    'unidad_medida' => $ing->articulo->unidad_medida ?? '',
                    'cantidad' => (string) $ing->cantidad,
                ])->toArray();
            } else {
                $this->recetaCantidadProducida = '1.000';
                $this->recetaNotas = '';
                $this->recetaIngredientes = [];
            }
        }

        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->showRecetaModal = true;
    }

    public function updatedBusquedaIngrediente(): void
    {
        if (strlen($this->busquedaIngrediente) < 2) {
            $this->resultadosBusqueda = [];
            return;
        }

        $excluirIds = collect($this->recetaIngredientes)->pluck('articulo_id')->toArray();
        if ($this->configArticuloId) {
            $excluirIds[] = $this->configArticuloId;
        }

        $this->resultadosBusqueda = Articulo::where('activo', true)
            ->whereNotIn('id', $excluirIds)
            ->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->busquedaIngrediente . '%')
                  ->orWhere('nombre', 'like', '%' . $this->busquedaIngrediente . '%');
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad_medida'])
            ->map(fn($a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'unidad_medida' => $a->unidad_medida,
            ])
            ->toArray();
    }

    public function agregarPrimerIngrediente(): void
    {
        if (count($this->resultadosBusqueda) > 0) {
            $this->agregarIngrediente($this->resultadosBusqueda[0]['id']);
        }
    }

    public function agregarIngrediente(int $articuloId): void
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        foreach ($this->recetaIngredientes as $ing) {
            if ($ing['articulo_id'] == $articuloId) return;
        }

        $this->recetaIngredientes[] = [
            'articulo_id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'unidad_medida' => $articulo->unidad_medida,
            'cantidad' => '1.000',
        ];

        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
    }

    public function eliminarIngrediente(int $index): void
    {
        unset($this->recetaIngredientes[$index]);
        $this->recetaIngredientes = array_values($this->recetaIngredientes);
    }

    /**
     * Guarda la receta como override para esta sucursal.
     * Si ya era un override, lo actualiza. Si era default, crea uno nuevo.
     */
    public function guardarRecetaOverride(): void
    {
        if (!$this->configArticuloId || !sucursal_activa()) return;

        if (empty($this->recetaIngredientes)) {
            $this->dispatch('notify', message: __('La receta debe tener al menos un ingrediente'), type: 'error');
            return;
        }

        foreach ($this->recetaIngredientes as $ing) {
            if (!isset($ing['cantidad']) || (float) $ing['cantidad'] <= 0) {
                $this->dispatch('notify', message: __('Todas las cantidades deben ser mayores a 0'), type: 'error');
                return;
            }
        }

        DB::connection('pymes_tenant')->transaction(function () {
            if ($this->recetaId && $this->recetaEsOverride) {
                // Actualizar override existente
                $receta = Receta::findOrFail($this->recetaId);
                $receta->update([
                    'cantidad_producida' => $this->recetaCantidadProducida,
                    'notas' => $this->recetaNotas ?: null,
                ]);
            } else {
                // Crear nuevo override
                $receta = Receta::create([
                    'recetable_type' => 'Articulo',
                    'recetable_id' => $this->configArticuloId,
                    'sucursal_id' => sucursal_activa(),
                    'cantidad_producida' => $this->recetaCantidadProducida,
                    'notas' => $this->recetaNotas ?: null,
                    'activo' => true,
                ]);
            }

            $receta->ingredientes()->delete();
            foreach ($this->recetaIngredientes as $ing) {
                $receta->ingredientes()->create([
                    'articulo_id' => $ing['articulo_id'],
                    'cantidad' => $ing['cantidad'],
                ]);
            }
        });

        $label = $this->recetaEsOverride ? __('Receta override actualizada') : __('Receta override creada para esta sucursal');
        $this->dispatch('notify', message: $label, type: 'success');
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function confirmarEliminarRecetaOverride(): void
    {
        if ($this->recetaId && $this->recetaEsOverride) {
            $this->showDeleteRecetaModal = true;
        }
    }

    public function eliminarRecetaOverride(): void
    {
        if (!$this->recetaId) return;

        $receta = Receta::find($this->recetaId);
        if ($receta) {
            $receta->ingredientes()->delete();
            $receta->delete();
        }

        $this->dispatch('notify', message: __('Override eliminado. Se usará la receta default.'), type: 'success');
        $this->showDeleteRecetaModal = false;
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarReceta(): void
    {
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarEliminarReceta(): void
    {
        $this->showDeleteRecetaModal = false;
    }

    protected function resetReceta(): void
    {
        $this->recetaId = null;
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;
        $this->recetaIngredientes = [];
        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->recetaCantidadProducida = '1.000';
        $this->recetaNotas = '';
    }

    // ===== Render =====

    protected function getArticulos()
    {
        $sucursalId = sucursal_activa();

        $query = Articulo::with(['categoriaModel']);

        // Eager load stock de la sucursal activa
        if ($sucursalId) {
            $query->with(['stocks' => function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }]);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->search . '%')
                  ->orWhere('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->categoriasSeleccionadas)) {
            $query->whereIn('categoria_id', $this->categoriasSeleccionadas);
        }

        if (!empty($this->etiquetasSeleccionadasFiltro)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasSeleccionadasFiltro);
            });
        }

        if ($this->filterTipo !== 'all') {
            $query->where('es_materia_prima', $this->filterTipo === 'materia_prima');
        }

        return $query->orderBy('nombre')->paginate(15);
    }

    public function render()
    {
        // Categorías para el panel de filtros (con búsqueda)
        $categoriasFiltroQuery = Categoria::where('activo', true);
        if ($this->busquedaCategoriaFiltro) {
            $categoriasFiltroQuery->where('nombre', 'like', '%' . $this->busquedaCategoriaFiltro . '%');
        }
        $categoriasFiltro = $categoriasFiltroQuery->orderBy('nombre')->get();

        // Grupos de etiquetas para el panel de filtros (con búsqueda)
        $busquedaFiltro = $this->busquedaEtiquetaFiltro;
        $gruposEtiquetasFiltroQuery = GrupoEtiqueta::where('activo', true);

        if ($busquedaFiltro) {
            $gruposEtiquetasFiltroQuery->where(function ($query) use ($busquedaFiltro) {
                $query->where('nombre', 'like', '%' . $busquedaFiltro . '%')
                      ->orWhereHas('etiquetas', function ($q) use ($busquedaFiltro) {
                          $q->where('activo', true)
                            ->where('nombre', 'like', '%' . $busquedaFiltro . '%');
                      });
            });
        }

        $gruposEtiquetasFiltro = $gruposEtiquetasFiltroQuery->orderBy('orden')->orderBy('nombre')->get();

        foreach ($gruposEtiquetasFiltro as $grupo) {
            $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

            if ($busquedaFiltro && !str_contains(strtolower($grupo->nombre), strtolower($busquedaFiltro))) {
                $etiquetasQuery->where('nombre', 'like', '%' . $busquedaFiltro . '%');
            }

            $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
        }

        return view('livewire.configuracion.articulos-sucursal', [
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'categoriasFiltro' => $categoriasFiltro,
            'gruposEtiquetasFiltro' => $gruposEtiquetasFiltro,
            'articulos' => $this->getArticulos(),
        ]);
    }
}
