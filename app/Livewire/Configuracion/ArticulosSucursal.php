<?php

namespace App\Livewire\Configuracion;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para configurar artículos por sucursal
 *
 * Permite seleccionar una sucursal y configurar qué artículos están activos en ella.
 *
 * @package App\Livewire\Configuracion
 */
#[Layout('layouts.app')]
class ArticulosSucursal extends Component
{
    use WithPagination;

    // Sucursal seleccionada
    public ?int $sucursal_id = null;

    // Búsqueda
    public string $search = '';

    // Filtro de categoría
    public string $filterCategory = 'all';

    // Artículos activos en esta sucursal (solo lectura, siempre desde BD)
    public array $articulos_activos = [];

    /**
     * Inicializa el componente
     */
    public function mount(): void
    {
        // Seleccionar la primera sucursal por defecto
        $firstSucursal = Sucursal::orderBy('nombre')->first();
        if ($firstSucursal) {
            $this->sucursal_id = $firstSucursal->id;
            $this->loadArticulos();
        }
    }

    /**
     * Actualiza la búsqueda y resetea la paginación
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de categoría y resetea la paginación
     */
    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    /**
     * Cuando cambia la sucursal, recargar los artículos desde la BD
     */
    public function updatedSucursalId(): void
    {
        // Forzar reset completo del array
        $this->reset('articulos_activos');

        // Cargar el estado fresco desde la BD
        $this->loadArticulos();

        // Resetear paginación
        $this->resetPage();

        // Forzar re-render completo del componente
        $this->dispatch('$refresh');
    }

    /**
     * Carga los artículos activos para la sucursal seleccionada (SIEMPRE desde BD)
     */
    protected function loadArticulos(): void
    {
        if (!$this->sucursal_id) {
            $this->articulos_activos = [];
            return;
        }

        // Limpiar el array antes de cargar
        $this->articulos_activos = [];

        // Obtener TODOS los artículos sin relaciones
        $todosArticulos = Articulo::all();

        foreach ($todosArticulos as $articulo) {
            // Verificar si tiene relación con esta sucursal - QUERY FRESCA cada vez
            $relacionSucursal = $articulo->sucursales()
                ->where('sucursal_id', $this->sucursal_id)
                ->first();

            if ($relacionSucursal) {
                // Si tiene relación, usar el valor de activo del pivot
                if ($relacionSucursal->pivot->activo) {
                    $this->articulos_activos[] = $articulo->id;
                }
            } else {
                // Si NO tiene relación, está activo por defecto
                $this->articulos_activos[] = $articulo->id;
            }
        }
    }

    /**
     * Alterna el estado de un artículo y guarda inmediatamente en la BD
     */
    public function toggleArticulo(int $articuloId): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $articulo = Articulo::find($articuloId);
        if (!$articulo) {
            return;
        }

        // Verificar el estado actual
        $estaActivo = in_array($articuloId, $this->articulos_activos);
        $nuevoEstado = !$estaActivo;

        // Verificar si ya existe la relación
        $exists = $articulo->sucursales()->where('sucursal_id', $this->sucursal_id)->exists();

        if ($exists) {
            // Actualizar el estado en la BD
            $articulo->sucursales()->updateExistingPivot($this->sucursal_id, [
                'activo' => $nuevoEstado
            ]);
        } else {
            // Crear la relación
            $articulo->sucursales()->attach($this->sucursal_id, [
                'activo' => $nuevoEstado
            ]);
        }

        // Recargar el estado desde la BD
        $this->loadArticulos();
    }

    /**
     * Activa todos los artículos para la sucursal actual
     */
    public function selectAll(): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $todosArticulos = Articulo::all();

        foreach ($todosArticulos as $articulo) {
            $exists = $articulo->sucursales()->where('sucursal_id', $this->sucursal_id)->exists();

            if ($exists) {
                $articulo->sucursales()->updateExistingPivot($this->sucursal_id, ['activo' => true]);
            } else {
                $articulo->sucursales()->attach($this->sucursal_id, ['activo' => true]);
            }
        }

        $this->loadArticulos();
        $this->dispatch('notify', message: __('Todos los artículos activados'), type: 'success');
    }

    /**
     * Desactiva todos los artículos para la sucursal actual
     */
    public function deselectAll(): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $todosArticulos = Articulo::all();

        foreach ($todosArticulos as $articulo) {
            $exists = $articulo->sucursales()->where('sucursal_id', $this->sucursal_id)->exists();

            if ($exists) {
                $articulo->sucursales()->updateExistingPivot($this->sucursal_id, ['activo' => false]);
            } else {
                $articulo->sucursales()->attach($this->sucursal_id, ['activo' => false]);
            }
        }

        $this->loadArticulos();
        $this->dispatch('notify', message: __('Todos los artículos desactivados'), type: 'success');
    }

    /**
     * Obtiene los artículos con filtros aplicados
     */
    protected function getArticulos()
    {
        $query = Articulo::with(['categoriaModel', 'tipoIva']);

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->search . '%')
                  ->orWhere('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->search . '%')
                  ->orWhere('marca', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de categoría
        if ($this->filterCategory !== 'all') {
            if ($this->filterCategory === 'none') {
                $query->whereNull('categoria_id');
            } else {
                $query->where('categoria_id', $this->filterCategory);
            }
        }

        return $query->orderBy('nombre')->paginate(15);
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get();
        $articulos = $this->getArticulos();

        return view('livewire.configuracion.articulos-sucursal', [
            'sucursales' => $sucursales,
            'categorias' => $categorias,
            'articulos' => $articulos,
        ]);
    }
}
