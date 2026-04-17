<?php

namespace App\Livewire\Articulos;

use App\Models\Categoria;
use App\Services\CatalogoCache;
use App\Services\CategoriaImportExportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Componente Livewire para gestión de categorías
 *
 * Permite crear, editar, listar y gestionar el estado de las categorías de artículos.
 */
#[Layout('layouts.app')]
#[Lazy]
class GestionarCategorias extends Component
{
    use WithFileUploads;
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

    // Modal de importación
    public bool $showImportModal = false;

    public $archivoImportacion = null;

    public array $importacionResultado = [];

    public bool $importacionProcesada = false;

    // Modal de selección de plantilla
    public bool $showPlantillaModal = false;

    // Propiedades del formulario
    public string $nombre = '';

    public string $prefijo = '';

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
        $this->showFilters = ! $this->showFilters;
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
                $q->where('nombre', 'like', '%'.$this->search.'%');
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
        $this->reset(['nombre', 'prefijo', 'color', 'icono', 'activo', 'categoriaId']);
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
        $this->prefijo = $categoria->prefijo ?? '';
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
            'nombre' => 'required|string|max:100|unique:pymes_tenant.categorias,nombre,'.$this->categoriaId,
            'prefijo' => 'nullable|string|max:10',
            'color' => 'required|string|max:7',
            'icono' => 'nullable|string|max:50',
            'activo' => 'boolean',
        ];

        $this->validate($rules);

        $prefijoLimpio = $this->prefijo ? strtoupper(trim($this->prefijo)) : null;

        if ($this->editMode) {
            // Actualizar categoría existente
            $categoria = Categoria::findOrFail($this->categoriaId);
            $categoria->nombre = $this->nombre;
            $categoria->prefijo = $prefijoLimpio;
            $categoria->color = $this->color;
            $categoria->icono = $this->icono ?: null;
            $categoria->activo = $this->activo;
            $categoria->save();

            $message = __('Categoría actualizada correctamente');
        } else {
            // Crear nueva categoría
            Categoria::create([
                'nombre' => $this->nombre,
                'prefijo' => $prefijoLimpio,
                'color' => $this->color,
                'icono' => $this->icono ?: null,
                'activo' => $this->activo,
            ]);

            $message = __('Categoría creada correctamente');
        }

        CatalogoCache::clear();

        $this->dispatch('notify', message: $message, type: 'success');
        $this->showModal = false;
        $this->reset(['nombre', 'prefijo', 'color', 'icono', 'activo', 'categoriaId']);
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->reset(['nombre', 'prefijo', 'color', 'icono', 'activo', 'categoriaId']);
    }

    /**
     * Cambia el estado activo/inactivo de una categoría
     */
    public function toggleStatus(int $categoriaId): void
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $categoria->activo = ! $categoria->activo;
        $categoria->save();

        $status = $categoria->activo ? __('activada') : __('desactivada');
        $this->dispatch('notify', message: __('Categoría :status correctamente', ['status' => $status]), type: 'success');
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
        if (! $this->categoriaAEliminar) {
            return;
        }

        $categoria = Categoria::find($this->categoriaAEliminar);
        if ($categoria) {
            $categoria->delete(); // Soft delete
            CatalogoCache::clear();
            $this->js("window.notify('".__('Categoría eliminada correctamente')."', 'success')");
        }

        $this->cancelarEliminar();
    }

    /**
     * Abre el modal para elegir el tipo de plantilla a descargar
     */
    public function openPlantillaModal(): void
    {
        $this->showPlantillaModal = true;
    }

    /**
     * Cierra el modal de selección de plantilla
     */
    public function closePlantillaModal(): void
    {
        $this->showPlantillaModal = false;
    }

    /**
     * Descarga la plantilla Excel para importar categorías.
     * Si $conDatos es true, prellena con las categorías actuales.
     */
    public function descargarPlantilla(CategoriaImportExportService $service, bool $conDatos = false)
    {
        $ruta = $service->generarPlantilla($conDatos);
        $this->showPlantillaModal = false;

        $nombre = $conDatos ? 'categorias_'.date('Y-m-d_H-i-s').'.xlsx' : 'plantilla_categorias.xlsx';

        return response()->download($ruta, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Abre el modal de importación
     */
    public function openImportModal(): void
    {
        $this->archivoImportacion = null;
        $this->importacionResultado = [];
        $this->importacionProcesada = false;
        $this->showImportModal = true;
    }

    /**
     * Cierra el modal de importación
     */
    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->archivoImportacion = null;
        $this->importacionResultado = [];
        $this->importacionProcesada = false;
    }

    /**
     * Importa categorías desde un archivo Excel
     */
    public function importarCategorias(CategoriaImportExportService $service): void
    {
        $this->validate([
            'archivoImportacion' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'archivoImportacion.required' => __('Debe seleccionar un archivo'),
            'archivoImportacion.mimes' => __('El archivo debe ser Excel (.xlsx) o CSV'),
            'archivoImportacion.max' => __('El archivo no debe superar 5MB'),
        ]);

        $this->importacionResultado = $service->importar($this->archivoImportacion);
        $this->importacionProcesada = true;

        $procesadas = $this->importacionResultado['creadas']
            + $this->importacionResultado['actualizadas']
            + ($this->importacionResultado['sin_cambios'] ?? 0);

        if ($procesadas > 0) {
            $this->dispatch('notify', type: 'success', message: __(':count categorías procesadas correctamente', ['count' => $procesadas]));
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="1" :columns="4" :rows="8" />
        HTML;
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
