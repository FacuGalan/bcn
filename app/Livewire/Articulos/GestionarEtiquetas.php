<?php

namespace App\Livewire\Articulos;

use App\Models\Etiqueta;
use App\Models\GrupoEtiqueta;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para gestión de grupos de etiquetas y etiquetas
 *
 * Permite crear, editar, listar y gestionar grupos de etiquetas con sus etiquetas hijas.
 * Estructura de acordeón donde cada grupo muestra sus etiquetas.
 */
#[Layout('layouts.app')]
class GestionarEtiquetas extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public string $search = '';
    public string $filterStatus = 'all';
    public bool $showFilters = false;

    // Control de acordeón - grupos expandidos
    public array $gruposExpandidos = [];

    // Modal de grupo
    public bool $showGrupoModal = false;
    public bool $editModeGrupo = false;
    public ?int $grupoId = null;

    // Propiedades del formulario de grupo
    public string $grupoNombre = '';
    public string $grupoCodigo = '';
    public string $grupoDescripcion = '';
    public string $grupoColor = '#3B82F6';
    public bool $grupoActivo = true;

    // Modal de etiqueta
    public bool $showEtiquetaModal = false;
    public bool $editModeEtiqueta = false;
    public ?int $etiquetaId = null;
    public ?int $etiquetaGrupoId = null;

    // Propiedades del formulario de etiqueta
    public string $etiquetaNombre = '';
    public string $etiquetaCodigo = '';
    public ?string $etiquetaColor = null;
    public bool $etiquetaActivo = true;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;
    public string $deleteType = ''; // 'grupo' o 'etiqueta'
    public ?int $itemAEliminar = null;
    public ?string $nombreItemAEliminar = null;

    public function mount()
    {
        // Expandir todos los grupos por defecto
        $grupos = GrupoEtiqueta::pluck('id')->toArray();
        $this->gruposExpandidos = $grupos;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Alterna la expansión de un grupo
     */
    public function toggleGrupo(int $grupoId): void
    {
        if (in_array($grupoId, $this->gruposExpandidos)) {
            $this->gruposExpandidos = array_diff($this->gruposExpandidos, [$grupoId]);
        } else {
            $this->gruposExpandidos[] = $grupoId;
        }
    }

    /**
     * Expande todos los grupos
     */
    public function expandirTodos(): void
    {
        $this->gruposExpandidos = GrupoEtiqueta::pluck('id')->toArray();
    }

    /**
     * Colapsa todos los grupos
     */
    public function colapsarTodos(): void
    {
        $this->gruposExpandidos = [];
    }

    /**
     * Obtiene los grupos de etiquetas con filtros aplicados
     */
    protected function getGrupos()
    {
        $query = GrupoEtiqueta::with(['etiquetas' => function ($query) {
            $query->orderBy('orden')->orderBy('nombre');
        }])->withCount('etiquetas');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('codigo', 'like', '%' . $this->search . '%')
                  ->orWhereHas('etiquetas', function ($eq) {
                      $eq->where('nombre', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        return $query->orderBy('orden')->orderBy('nombre')->get();
    }

    // ==================== Métodos de Grupo ====================

    /**
     * Abre el modal para crear un nuevo grupo
     */
    public function createGrupo(): void
    {
        $this->resetGrupoForm();
        $this->editModeGrupo = false;
        $this->showGrupoModal = true;
    }

    /**
     * Abre el modal para editar un grupo
     */
    public function editGrupo(int $grupoId): void
    {
        $grupo = GrupoEtiqueta::findOrFail($grupoId);

        $this->grupoId = $grupo->id;
        $this->grupoNombre = $grupo->nombre;
        $this->grupoCodigo = $grupo->codigo ?? '';
        $this->grupoDescripcion = $grupo->descripcion ?? '';
        $this->grupoColor = $grupo->color;
        $this->grupoActivo = $grupo->activo;

        $this->editModeGrupo = true;
        $this->showGrupoModal = true;
    }

    /**
     * Guarda el grupo
     */
    public function saveGrupo(): void
    {
        $rules = [
            'grupoNombre' => 'required|string|max:100|unique:pymes_tenant.grupos_etiquetas,nombre,' . $this->grupoId,
            'grupoCodigo' => 'nullable|string|max:50|unique:pymes_tenant.grupos_etiquetas,codigo,' . $this->grupoId,
            'grupoDescripcion' => 'nullable|string|max:500',
            'grupoColor' => 'required|string|max:7',
            'grupoActivo' => 'boolean',
        ];

        $this->validate($rules);

        $datos = [
            'nombre' => $this->grupoNombre,
            'codigo' => $this->grupoCodigo ?: null,
            'descripcion' => $this->grupoDescripcion ?: null,
            'color' => $this->grupoColor,
            'activo' => $this->grupoActivo,
        ];

        if ($this->editModeGrupo) {
            $grupo = GrupoEtiqueta::findOrFail($this->grupoId);
            $grupo->update($datos);
            $message = __('Grupo actualizado correctamente');
        } else {
            $maxOrden = GrupoEtiqueta::max('orden') ?? 0;
            $datos['orden'] = $maxOrden + 1;
            $grupo = GrupoEtiqueta::create($datos);
            // Expandir el nuevo grupo
            $this->gruposExpandidos[] = $grupo->id;
            $message = __('Grupo creado correctamente');
        }

        $this->js("window.notify('" . addslashes($message) . "', 'success')");
        $this->showGrupoModal = false;
        $this->resetGrupoForm();
    }

    /**
     * Cancela la edición de grupo
     */
    public function cancelGrupo(): void
    {
        $this->showGrupoModal = false;
        $this->resetGrupoForm();
    }

    /**
     * Resetea el formulario de grupo
     */
    protected function resetGrupoForm(): void
    {
        $this->grupoId = null;
        $this->grupoNombre = '';
        $this->grupoCodigo = '';
        $this->grupoDescripcion = '';
        $this->grupoColor = '#3B82F6';
        $this->grupoActivo = true;
    }

    /**
     * Cambia el estado de un grupo
     */
    public function toggleGrupoStatus(int $grupoId): void
    {
        $grupo = GrupoEtiqueta::findOrFail($grupoId);
        $grupo->activo = !$grupo->activo;
        $grupo->save();

        $status = $grupo->activo ? __('activado') : __('desactivado');
        $this->js("window.notify('" . __('Grupo :status correctamente', ['status' => $status]) . "', 'success')");
    }

    // ==================== Métodos de Etiqueta ====================

    /**
     * Abre el modal para crear una nueva etiqueta
     */
    public function createEtiqueta(int $grupoId): void
    {
        $this->resetEtiquetaForm();
        $this->etiquetaGrupoId = $grupoId;
        $this->editModeEtiqueta = false;
        $this->showEtiquetaModal = true;
    }

    /**
     * Abre el modal para editar una etiqueta
     */
    public function editEtiqueta(int $etiquetaId): void
    {
        $etiqueta = Etiqueta::findOrFail($etiquetaId);

        $this->etiquetaId = $etiqueta->id;
        $this->etiquetaGrupoId = $etiqueta->grupo_etiqueta_id;
        $this->etiquetaNombre = $etiqueta->nombre;
        $this->etiquetaCodigo = $etiqueta->codigo ?? '';
        $this->etiquetaColor = $etiqueta->color;
        $this->etiquetaActivo = $etiqueta->activo;

        $this->editModeEtiqueta = true;
        $this->showEtiquetaModal = true;
    }

    /**
     * Guarda la etiqueta
     */
    public function saveEtiqueta(): void
    {
        $rules = [
            'etiquetaNombre' => 'required|string|max:100',
            'etiquetaCodigo' => 'nullable|string|max:50',
            'etiquetaColor' => 'nullable|string|max:7',
            'etiquetaActivo' => 'boolean',
            'etiquetaGrupoId' => 'required|exists:pymes_tenant.grupos_etiquetas,id',
        ];

        $this->validate($rules);

        // Verificar unicidad de código dentro del grupo
        $existeQuery = Etiqueta::where('grupo_etiqueta_id', $this->etiquetaGrupoId)
            ->where('codigo', $this->etiquetaCodigo);

        if ($this->etiquetaId) {
            $existeQuery->where('id', '!=', $this->etiquetaId);
        }

        if ($this->etiquetaCodigo && $existeQuery->exists()) {
            $this->addError('etiquetaCodigo', __('Ya existe una etiqueta con este código en el grupo.'));
            return;
        }

        $datos = [
            'grupo_etiqueta_id' => $this->etiquetaGrupoId,
            'nombre' => $this->etiquetaNombre,
            'codigo' => $this->etiquetaCodigo ?: null,
            'color' => $this->etiquetaColor ?: null,
            'activo' => $this->etiquetaActivo,
        ];

        if ($this->editModeEtiqueta) {
            $etiqueta = Etiqueta::findOrFail($this->etiquetaId);
            $etiqueta->update($datos);
            $message = __('Etiqueta actualizada correctamente');
        } else {
            $maxOrden = Etiqueta::where('grupo_etiqueta_id', $this->etiquetaGrupoId)->max('orden') ?? 0;
            $datos['orden'] = $maxOrden + 1;
            Etiqueta::create($datos);
            $message = __('Etiqueta creada correctamente');
        }

        $this->js("window.notify('" . addslashes($message) . "', 'success')");
        $this->showEtiquetaModal = false;
        $this->resetEtiquetaForm();
    }

    /**
     * Cancela la edición de etiqueta
     */
    public function cancelEtiqueta(): void
    {
        $this->showEtiquetaModal = false;
        $this->resetEtiquetaForm();
    }

    /**
     * Resetea el formulario de etiqueta
     */
    protected function resetEtiquetaForm(): void
    {
        $this->etiquetaId = null;
        $this->etiquetaGrupoId = null;
        $this->etiquetaNombre = '';
        $this->etiquetaCodigo = '';
        $this->etiquetaColor = null;
        $this->etiquetaActivo = true;
    }

    /**
     * Cambia el estado de una etiqueta
     */
    public function toggleEtiquetaStatus(int $etiquetaId): void
    {
        $etiqueta = Etiqueta::findOrFail($etiquetaId);
        $etiqueta->activo = !$etiqueta->activo;
        $etiqueta->save();

        $status = $etiqueta->activo ? __('activada') : __('desactivada');
        $this->js("window.notify('" . __('Etiqueta :status correctamente', ['status' => $status]) . "', 'success')");
    }

    // ==================== Métodos de Eliminación ====================

    /**
     * Confirmar eliminación de un grupo
     */
    public function confirmarEliminarGrupo(int $grupoId): void
    {
        $grupo = GrupoEtiqueta::find($grupoId);
        if ($grupo) {
            $this->deleteType = 'grupo';
            $this->itemAEliminar = $grupo->id;
            $this->nombreItemAEliminar = $grupo->nombre;
            $this->showDeleteModal = true;
        }
    }

    /**
     * Confirmar eliminación de una etiqueta
     */
    public function confirmarEliminarEtiqueta(int $etiquetaId): void
    {
        $etiqueta = Etiqueta::find($etiquetaId);
        if ($etiqueta) {
            $this->deleteType = 'etiqueta';
            $this->itemAEliminar = $etiqueta->id;
            $this->nombreItemAEliminar = $etiqueta->nombre;
            $this->showDeleteModal = true;
        }
    }

    /**
     * Cancela la eliminación
     */
    public function cancelarEliminar(): void
    {
        $this->showDeleteModal = false;
        $this->deleteType = '';
        $this->itemAEliminar = null;
        $this->nombreItemAEliminar = null;
    }

    /**
     * Ejecuta la eliminación
     */
    public function eliminar(): void
    {
        if (!$this->itemAEliminar) {
            return;
        }

        if ($this->deleteType === 'grupo') {
            $grupo = GrupoEtiqueta::find($this->itemAEliminar);
            if ($grupo) {
                // Eliminar etiquetas del grupo primero
                $grupo->etiquetas()->delete();
                $grupo->delete();
                $this->js("window.notify('" . __('Grupo eliminado correctamente') . "', 'success')");
            }
        } elseif ($this->deleteType === 'etiqueta') {
            $etiqueta = Etiqueta::find($this->itemAEliminar);
            if ($etiqueta) {
                $etiqueta->delete();
                $this->js("window.notify('" . __('Etiqueta eliminada correctamente') . "', 'success')");
            }
        }

        $this->cancelarEliminar();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        return view('livewire.articulos.gestionar-etiquetas', [
            'grupos' => $this->getGrupos(),
        ]);
    }
}
