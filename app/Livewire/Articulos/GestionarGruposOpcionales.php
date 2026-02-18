<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\ArticuloGrupoOpcionalOpcion;
use App\Models\GrupoOpcional;
use App\Models\Opcional;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Services\OpcionalService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para gestión de grupos opcionales y sus opciones.
 *
 * CRUD global del catálogo de opcionales. Todo es por comercio, no por sucursal.
 * Los grupos y sus opciones están disponibles para todas las sucursales;
 * la asignación a artículos determina el uso.
 */
#[Layout('layouts.app')]
class GestionarGruposOpcionales extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterTipo = 'all';

    // Modal grupo
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $grupoId = null;

    // Formulario grupo
    public string $nombre = '';
    public string $descripcion = '';
    public bool $obligatorio = false;
    public string $tipo = 'seleccionable';
    public int $min_seleccion = 0;
    public ?int $max_seleccion = null;
    public bool $activo = true;
    public int $orden = 0;

    // Opciones del grupo (inline en el modal)
    public array $opciones = [];
    public string $nuevaOpcionNombre = '';
    public string $nuevaOpcionPrecio = '0.00';

    // Eliminar grupo
    public bool $showDeleteModal = false;
    public ?int $grupoAEliminar = null;
    public ?string $nombreGrupoAEliminar = null;

    // Modal receta de opcional
    public bool $showRecetaModal = false;
    public ?int $opcionalRecetaId = null;
    public string $opcionalRecetaNombre = '';

    // Editor de receta (propiedades usadas por el partial _receta-editor)
    public ?int $recetaId = null;
    public array $recetaIngredientes = [];
    public string $busquedaIngrediente = '';
    public array $resultadosBusqueda = [];
    public string $recetaCantidadProducida = '1.000';
    public string $recetaNotas = '';
    public bool $recetaEsOverride = false;
    public ?string $recetaSucursalNombre = null;

    // Modal eliminar receta
    public bool $showDeleteRecetaModal = false;

    // Modal disponibilidad por sucursal
    public bool $showDisponibilidadModal = false;
    public ?int $disponibilidadGrupoId = null;
    public string $disponibilidadGrupoNombre = '';
    public array $disponibilidadSucursales = [];
    public array $disponibilidadOpciones = [];

    // Modal asignación masiva
    public bool $showAsignacionModal = false;
    public ?int $asignacionGrupoId = null;
    public string $asignacionGrupoNombre = '';
    public string $busquedaArticuloAsignacion = '';
    public array $articulosSeleccionados = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    protected function getGrupos()
    {
        $query = GrupoOpcional::withCount(['opcionales' => fn($q) => $q->where('activo', true)]);

        // Mostrar eliminados si se filtra por ellos
        if ($this->filterStatus === 'deleted') {
            $query->onlyTrashed();
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterStatus === 'active') {
            $query->where('activo', true);
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('activo', false);
        }

        if ($this->filterTipo !== 'all') {
            $query->where('tipo', $this->filterTipo);
        }

        return $query->orderBy('orden')->orderBy('nombre')->paginate(10);
    }

    public function create(): void
    {
        $this->resetFormulario();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $grupo = GrupoOpcional::with(['opcionales' => fn($q) => $q->orderBy('orden')])->findOrFail($id);

        $this->grupoId = $grupo->id;
        $this->nombre = $grupo->nombre;
        $this->descripcion = $grupo->descripcion ?? '';
        $this->obligatorio = $grupo->obligatorio;
        $this->tipo = $grupo->tipo;
        $this->min_seleccion = $grupo->min_seleccion;
        $this->max_seleccion = $grupo->max_seleccion;
        $this->activo = $grupo->activo;
        $this->orden = $grupo->orden;

        $this->opciones = $grupo->opcionales->map(fn($op) => [
            'id' => $op->id,
            'nombre' => $op->nombre,
            'descripcion' => $op->descripcion ?? '',
            'precio_extra' => $op->precio_extra,
            'activo' => $op->activo,
            'orden' => $op->orden,
        ])->toArray();

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:500',
            'obligatorio' => 'boolean',
            'tipo' => 'required|in:seleccionable,cuantitativo',
            'min_seleccion' => 'required|integer|min:0',
            'max_seleccion' => 'nullable|integer|min:1',
            'activo' => 'boolean',
            'orden' => 'integer|min:0',
        ]);

        $datos = [
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion ?: null,
            'obligatorio' => $this->obligatorio,
            'tipo' => $this->tipo,
            'min_seleccion' => $this->min_seleccion,
            'max_seleccion' => $this->max_seleccion,
            'activo' => $this->activo,
            'orden' => $this->orden,
        ];

        if ($this->editMode) {
            $grupo = GrupoOpcional::findOrFail($this->grupoId);
            $grupo->update($datos);
            $message = __('Grupo opcional actualizado correctamente');
        } else {
            $grupo = GrupoOpcional::create($datos);
            $message = __('Grupo opcional creado correctamente');
        }

        // Sincronizar opciones
        $this->sincronizarOpciones($grupo);

        $this->dispatch('notify', message: $message, type: 'success');
        $this->showModal = false;
        $this->resetFormulario();
    }

    protected function sincronizarOpciones(GrupoOpcional $grupo): void
    {
        $opcionesIds = [];

        foreach ($this->opciones as $index => $opcionData) {
            if (isset($opcionData['id'])) {
                // Actualizar existente
                $opcion = Opcional::find($opcionData['id']);
                if ($opcion) {
                    $opcion->update([
                        'nombre' => $opcionData['nombre'],
                        'descripcion' => $opcionData['descripcion'] ?: null,
                        'precio_extra' => $opcionData['precio_extra'],
                        'activo' => $opcionData['activo'],
                        'orden' => $opcionData['orden'] ?? $index,
                    ]);
                    $opcionesIds[] = $opcion->id;
                }
            } else {
                // Crear nueva
                $opcion = $grupo->opcionales()->create([
                    'nombre' => $opcionData['nombre'],
                    'descripcion' => $opcionData['descripcion'] ?: null,
                    'precio_extra' => $opcionData['precio_extra'],
                    'activo' => $opcionData['activo'] ?? true,
                    'orden' => $opcionData['orden'] ?? $index,
                ]);
                $opcionesIds[] = $opcion->id;
            }
        }

        // Soft delete de opciones que ya no están en la lista
        $grupo->opcionales()->whereNotIn('id', $opcionesIds)->delete();

        // Propagar opcionales nuevos a artículos que ya tienen este grupo asignado
        // (los eliminados se propagan automáticamente via cascadeOnDelete en la FK)
        $opcionalesActivos = $grupo->opcionales()->where('activo', true)->get();
        if ($opcionalesActivos->isNotEmpty()) {
            $asignaciones = ArticuloGrupoOpcional::where('grupo_opcional_id', $grupo->id)
                ->with('opciones')
                ->get();

            foreach ($asignaciones as $asignacion) {
                $existentes = $asignacion->opciones->pluck('opcional_id');
                foreach ($opcionalesActivos as $opcional) {
                    if (!$existentes->contains($opcional->id)) {
                        $asignacion->opciones()->create([
                            'opcional_id' => $opcional->id,
                            'precio_extra' => $opcional->precio_extra,
                            'orden' => $opcional->orden,
                            'activo' => true,
                            'disponible' => true,
                        ]);
                    }
                }
            }
        }
    }

    public function agregarOpcion(): void
    {
        if (trim($this->nuevaOpcionNombre) === '') {
            return;
        }

        $this->opciones[] = [
            'nombre' => $this->nuevaOpcionNombre,
            'descripcion' => '',
            'precio_extra' => $this->nuevaOpcionPrecio ?: '0.00',
            'activo' => true,
            'orden' => count($this->opciones),
        ];

        $this->nuevaOpcionNombre = '';
        $this->nuevaOpcionPrecio = '0.00';
        $this->dispatch('opcion-agregada');
    }

    public function eliminarOpcion(int $index): void
    {
        unset($this->opciones[$index]);
        $this->opciones = array_values($this->opciones);
    }

    public function moverOpcion(int $index, string $direction): void
    {
        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($newIndex < 0 || $newIndex >= count($this->opciones)) {
            return;
        }

        $temp = $this->opciones[$index];
        $this->opciones[$index] = $this->opciones[$newIndex];
        $this->opciones[$newIndex] = $temp;

        // Actualizar orden
        foreach ($this->opciones as $i => &$op) {
            $op['orden'] = $i;
        }
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->resetFormulario();
    }

    public function toggleStatus(int $id): void
    {
        $grupo = GrupoOpcional::findOrFail($id);
        $grupo->activo = !$grupo->activo;
        $grupo->save();

        $status = $grupo->activo ? __('activado') : __('desactivado');
        $this->dispatch('notify', message: __('Grupo :status correctamente', ['status' => $status]), type: 'success');
    }

    public function confirmarEliminar(int $id): void
    {
        $grupo = GrupoOpcional::find($id);
        if ($grupo) {
            $this->grupoAEliminar = $grupo->id;
            $this->nombreGrupoAEliminar = $grupo->nombre;
            $this->showDeleteModal = true;
        }
    }

    public function cancelarEliminar(): void
    {
        $this->showDeleteModal = false;
        $this->grupoAEliminar = null;
        $this->nombreGrupoAEliminar = null;
    }

    public function eliminar(): void
    {
        if (!$this->grupoAEliminar) {
            return;
        }

        $grupo = GrupoOpcional::find($this->grupoAEliminar);
        if ($grupo) {
            // Soft delete del grupo y sus opcionales
            $grupo->opcionales()->delete();
            $grupo->delete();
            $this->dispatch('notify', message: __('Grupo eliminado correctamente'), type: 'success');
        }

        $this->cancelarEliminar();
    }

    public function restaurar(int $id): void
    {
        $grupo = GrupoOpcional::onlyTrashed()->find($id);
        if ($grupo) {
            $grupo->restore();
            // Restaurar opcionales del grupo
            Opcional::onlyTrashed()
                ->where('grupo_opcional_id', $grupo->id)
                ->restore();
            $this->dispatch('notify', message: __('Grupo restaurado correctamente'), type: 'success');
        }
    }

    // ===== Recetas de Opcionales =====

    /**
     * Busca ingredientes cuando cambia el texto de búsqueda
     */
    public function updatedBusquedaIngrediente(): void
    {
        if (strlen($this->busquedaIngrediente) < 2) {
            $this->resultadosBusqueda = [];
            return;
        }

        $excluirIds = collect($this->recetaIngredientes)->pluck('articulo_id')->toArray();

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

    public function editarRecetaOpcional(int $opcionalId): void
    {
        $opcional = Opcional::findOrFail($opcionalId);

        $this->opcionalRecetaId = $opcional->id;
        $this->opcionalRecetaNombre = $opcional->nombre;
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;

        // Buscar receta default existente
        $receta = Receta::where('recetable_type', 'Opcional')
            ->where('recetable_id', $opcionalId)
            ->whereNull('sucursal_id')
            ->with('ingredientes.articulo')
            ->first();

        if ($receta) {
            $this->recetaId = $receta->id;
            $this->recetaCantidadProducida = (string) $receta->cantidad_producida;
            $this->recetaNotas = $receta->notas ?? '';
            $this->recetaIngredientes = $receta->ingredientes->map(fn($ing) => [
                'articulo_id' => $ing->articulo_id,
                'codigo' => $ing->articulo->codigo ?? '',
                'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
                'unidad_medida' => $ing->articulo->unidad_medida ?? '',
                'cantidad' => (string) $ing->cantidad,
            ])->toArray();
        } else {
            $this->recetaId = null;
            $this->recetaCantidadProducida = '1.000';
            $this->recetaNotas = '';
            $this->recetaIngredientes = [];
        }

        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->showRecetaModal = true;
    }

    public function guardarRecetaOpcional(): void
    {
        if (!$this->opcionalRecetaId) return;

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
            if ($this->recetaId) {
                $receta = Receta::findOrFail($this->recetaId);
                $receta->update([
                    'cantidad_producida' => $this->recetaCantidadProducida,
                    'notas' => $this->recetaNotas ?: null,
                ]);
            } else {
                $receta = Receta::create([
                    'recetable_type' => 'Opcional',
                    'recetable_id' => $this->opcionalRecetaId,
                    'sucursal_id' => null,
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

        $this->dispatch('notify', message: __('Receta guardada correctamente'), type: 'success');
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function confirmarEliminarRecetaOpcional(): void
    {
        if ($this->recetaId) {
            $this->showDeleteRecetaModal = true;
        }
    }

    public function eliminarRecetaOpcional(): void
    {
        if (!$this->recetaId) return;

        $receta = Receta::find($this->recetaId);
        if ($receta) {
            $receta->ingredientes()->delete();
            $receta->delete();
        }

        $this->dispatch('notify', message: __('Receta eliminada correctamente'), type: 'success');
        $this->showDeleteRecetaModal = false;
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarRecetaOpcional(): void
    {
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarEliminarRecetaOpcional(): void
    {
        $this->showDeleteRecetaModal = false;
    }

    protected function resetReceta(): void
    {
        $this->opcionalRecetaId = null;
        $this->opcionalRecetaNombre = '';
        $this->recetaId = null;
        $this->recetaIngredientes = [];
        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->recetaCantidadProducida = '1.000';
        $this->recetaNotas = '';
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;
    }

    // ===== Disponibilidad por Sucursal =====

    public function gestionarDisponibilidad(int $grupoId): void
    {
        $grupo = GrupoOpcional::with(['opcionales' => fn($q) => $q->orderBy('orden')])->findOrFail($grupoId);

        $this->disponibilidadGrupoId = $grupo->id;
        $this->disponibilidadGrupoNombre = $grupo->nombre;
        $this->disponibilidadSucursales = Sucursal::activas()->orderBy('nombre')->get(['id', 'nombre'])->toArray();

        $this->cargarDisponibilidad($grupo);
        $this->showDisponibilidadModal = true;
    }

    protected function cargarDisponibilidad(GrupoOpcional $grupo): void
    {
        $sucursalIds = collect($this->disponibilidadSucursales)->pluck('id')->toArray();

        $this->disponibilidadOpciones = $grupo->opcionales->map(function ($opcional) use ($sucursalIds) {
            $porSucursal = [];

            foreach ($sucursalIds as $sucursalId) {
                $stats = ArticuloGrupoOpcionalOpcion::where('opcional_id', $opcional->id)
                    ->whereHas('articuloGrupoOpcional', fn($q) => $q->where('sucursal_id', $sucursalId))
                    ->selectRaw('COUNT(*) as total, SUM(disponible) as disponibles')
                    ->first();

                $total = (int) ($stats->total ?? 0);
                $disponibles = (int) ($stats->disponibles ?? 0);

                $porSucursal[$sucursalId] = [
                    'disponible' => $total === 0 || $disponibles > 0,
                    'asignado' => $total > 0,
                ];
            }

            return [
                'id' => $opcional->id,
                'nombre' => $opcional->nombre,
                'activo' => $opcional->activo,
                'por_sucursal' => $porSucursal,
            ];
        })->toArray();
    }

    public function toggleDisponibilidad(int $opcionalId, int $sucursalId): void
    {
        $opcionalService = app(OpcionalService::class);

        // Buscar estado actual
        foreach ($this->disponibilidadOpciones as &$opcion) {
            if ($opcion['id'] === $opcionalId) {
                $estadoActual = $opcion['por_sucursal'][$sucursalId]['disponible'] ?? true;

                if ($estadoActual) {
                    $opcionalService->marcarAgotado($opcionalId, $sucursalId);
                } else {
                    $opcionalService->marcarDisponible($opcionalId, $sucursalId);
                }

                $opcion['por_sucursal'][$sucursalId]['disponible'] = !$estadoActual;
                break;
            }
        }
    }

    public function cerrarDisponibilidad(): void
    {
        $this->showDisponibilidadModal = false;
        $this->disponibilidadGrupoId = null;
        $this->disponibilidadGrupoNombre = '';
        $this->disponibilidadOpciones = [];
        $this->disponibilidadSucursales = [];
    }

    // ===== Asignación Masiva =====

    public function gestionarAsignacion(int $grupoId): void
    {
        $grupo = GrupoOpcional::findOrFail($grupoId);

        $this->asignacionGrupoId = $grupo->id;
        $this->asignacionGrupoNombre = $grupo->nombre;
        $this->busquedaArticuloAsignacion = '';
        $this->articulosSeleccionados = [];
        $this->showAsignacionModal = true;
    }

    public function getArticulosParaAsignacionProperty(): array
    {
        if (!$this->asignacionGrupoId) {
            return [];
        }

        $query = Articulo::where('activo', true)
            ->where('es_materia_prima', false);

        if ($this->busquedaArticuloAsignacion && strlen($this->busquedaArticuloAsignacion) >= 2) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->busquedaArticuloAsignacion . '%')
                  ->orWhere('codigo', 'like', '%' . $this->busquedaArticuloAsignacion . '%');
            });
        }

        // Traer artículos con flag de si ya tienen este grupo asignado
        $articulosYaAsignados = ArticuloGrupoOpcional::where('grupo_opcional_id', $this->asignacionGrupoId)
            ->distinct('articulo_id')
            ->pluck('articulo_id')
            ->toArray();

        return $query->orderBy('nombre')
            ->limit(50)
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn($a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'ya_asignado' => in_array($a->id, $articulosYaAsignados),
            ])
            ->toArray();
    }

    public function toggleArticuloSeleccionado(int $articuloId): void
    {
        if (in_array($articuloId, $this->articulosSeleccionados)) {
            $this->articulosSeleccionados = array_values(array_diff($this->articulosSeleccionados, [$articuloId]));
        } else {
            $this->articulosSeleccionados[] = $articuloId;
        }
    }

    public function asignarMasivo(): void
    {
        if (empty($this->articulosSeleccionados) || !$this->asignacionGrupoId) {
            return;
        }

        $opcionalService = app(OpcionalService::class);
        $count = 0;

        foreach ($this->articulosSeleccionados as $articuloId) {
            $result = $opcionalService->asignarGrupoAArticulo($articuloId, $this->asignacionGrupoId);
            if ($result > 0) {
                $count++;
            }
        }

        $this->dispatch('notify', message: __('Grupo asignado a :count artículos', ['count' => $count]), type: 'success');
        $this->articulosSeleccionados = [];
        // Recargar lista para reflejar los ya asignados
        $this->busquedaArticuloAsignacion = $this->busquedaArticuloAsignacion;
    }

    public function cerrarAsignacion(): void
    {
        $this->showAsignacionModal = false;
        $this->asignacionGrupoId = null;
        $this->asignacionGrupoNombre = '';
        $this->busquedaArticuloAsignacion = '';
        $this->articulosSeleccionados = [];
    }

    protected function resetFormulario(): void
    {
        $this->reset([
            'grupoId', 'nombre', 'descripcion', 'obligatorio', 'tipo',
            'min_seleccion', 'max_seleccion', 'activo', 'orden',
            'opciones', 'nuevaOpcionNombre', 'nuevaOpcionPrecio',
        ]);
        $this->activo = true;
        $this->tipo = 'seleccionable';
        $this->nuevaOpcionPrecio = '0.00';
    }

    public function render()
    {
        return view('livewire.articulos.gestionar-grupos-opcionales', [
            'grupos' => $this->getGrupos(),
        ]);
    }
}
