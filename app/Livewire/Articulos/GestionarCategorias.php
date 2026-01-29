<?php

namespace App\Livewire\Articulos;

use App\Models\Categoria;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para gestión de categorías
 *
 * Permite crear, editar, listar y gestionar el estado de las categorías de artículos.
 *
 * @package App\Livewire\Articulos
 */
#[Layout('layouts.app')]
class GestionarCategorias extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public string $search = '';
    public string $filterStatus = 'all'; // all, active, inactive
    public bool $showFilters = false;

    // Propiedades del modal
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $categoriaId = null;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;
    public ?int $categoriaAEliminar = null;
    public ?string $nombreCategoriaAEliminar = null;

    // Propiedades del formulario
    public string $nombre = '';
    public string $color = '#3B82F6'; // Color azul por defecto
    public string $icono = '';
    public bool $activo = true;

    /**
     * Actualiza la búsqueda y resetea la paginación
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de estado y resetea la paginación
     */
    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Alterna la visibilidad de los filtros
     */
    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene las categorías con filtros aplicados
     */
    protected function getCategorias()
    {
        $query = Categoria::query();

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de estado
        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        return $query->orderBy('nombre')->paginate(10);
    }

    /**
     * Abre el modal para crear una nueva categoría
     */
    public function create(): void
    {
        $this->reset(['nombre', 'color', 'icono', 'activo', 'categoriaId']);
        $this->editMode = false;
        $this->activo = true;
        $this->color = '#3B82F6';
        $this->showModal = true;
    }

    /**
     * Abre el modal para editar una categoría existente
     */
    public function edit(int $categoriaId): void
    {
        $categoria = Categoria::findOrFail($categoriaId);

        $this->categoriaId = $categoria->id;
        $this->nombre = $categoria->nombre;
        $this->color = $categoria->color;
        $this->icono = $categoria->icono ?? '';
        $this->activo = $categoria->activo;

        $this->editMode = true;
        $this->showModal = true;
    }

    /**
     * Guarda la categoría (crear o actualizar)
     */
    public function save(): void
    {
        $rules = [
            'nombre' => 'required|string|max:100|unique:pymes_tenant.categorias,nombre,' . $this->categoriaId,
            'color' => 'required|string|max:7',
            'icono' => 'nullable|string|max:50',
            'activo' => 'boolean',
        ];

        $this->validate($rules);

        if ($this->editMode) {
            // Actualizar categoría existente
            $categoria = Categoria::findOrFail($this->categoriaId);
            $categoria->nombre = $this->nombre;
            $categoria->color = $this->color;
            $categoria->icono = $this->icono ?: null;
            $categoria->activo = $this->activo;
            $categoria->save();

            $message = 'Categoría actualizada correctamente';
        } else {
            // Crear nueva categoría
            Categoria::create([
                'nombre' => $this->nombre,
                'color' => $this->color,
                'icono' => $this->icono ?: null,
                'activo' => $this->activo,
            ]);

            $message = 'Categoría creada correctamente';
        }

        $this->dispatch('notify', message: $message, type: 'success');
        $this->showModal = false;
        $this->reset(['nombre', 'color', 'icono', 'activo', 'categoriaId']);
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->reset(['nombre', 'color', 'icono', 'activo', 'categoriaId']);
    }

    /**
     * Cambia el estado activo/inactivo de una categoría
     */
    public function toggleStatus(int $categoriaId): void
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $categoria->activo = !$categoria->activo;
        $categoria->save();

        $status = $categoria->activo ? 'activada' : 'desactivada';
        $this->dispatch('notify', message: "Categoría {$status} correctamente", type: 'success');
    }

    /**
     * Abre el modal de confirmación de eliminación
     */
    public function confirmarEliminar(int $categoriaId): void
    {
        $categoria = Categoria::find($categoriaId);
        if ($categoria) {
            $this->categoriaAEliminar = $categoria->id;
            $this->nombreCategoriaAEliminar = $categoria->nombre;
            $this->showDeleteModal = true;
        }
    }

    /**
     * Cierra el modal de confirmación
     */
    public function cancelarEliminar(): void
    {
        $this->showDeleteModal = false;
        $this->categoriaAEliminar = null;
        $this->nombreCategoriaAEliminar = null;
    }

    /**
     * Ejecuta la eliminación después de confirmar
     */
    public function eliminar(): void
    {
        if (!$this->categoriaAEliminar) {
            return;
        }

        $categoria = Categoria::find($this->categoriaAEliminar);
        if ($categoria) {
            $categoria->delete(); // Soft delete
            $this->js("window.notify('Categoría eliminada correctamente', 'success')");
        }

        $this->cancelarEliminar();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        return view('livewire.articulos.gestionar-categorias', [
            'categorias' => $this->getCategorias(),
        ]);
    }
}
