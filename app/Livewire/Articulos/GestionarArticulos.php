<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\Sucursal;
use App\Models\TipoIva;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para gestión de artículos
 *
 * Permite crear, editar, listar y gestionar el estado de los artículos.
 *
 * @package App\Livewire\Articulos
 */
#[Layout('layouts.app')]
class GestionarArticulos extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public string $search = '';
    public string $filterStatus = 'all'; // all, active, inactive
    public string $filterSucursal = 'all';
    public string $filterCategory = 'all';
    public bool $showFilters = false;

    // Propiedades del modal
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $articuloId = null;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;
    public ?int $articuloAEliminar = null;
    public ?string $nombreArticuloAEliminar = null;

    // Propiedades del formulario
    public string $codigo = '';
    public string $nombre = '';
    public string $descripcion = '';
    public ?int $categoria_id = null;
    public string $unidad_medida = 'unidad';
    public bool $es_servicio = false;
    public bool $controla_stock = true;
    public ?int $tipo_iva_id = null;
    public bool $precio_iva_incluido = true;
    public ?float $precio_base = null;
    public bool $activo = true;

    // Sucursales
    public array $sucursales_seleccionadas = [];

    // Etiquetas
    public array $etiquetas_seleccionadas = [];
    public string $busquedaEtiqueta = '';

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
     * Actualiza el filtro de sucursal y resetea la paginación
     */
    public function updatingFilterSucursal(): void
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
     * Alterna la visibilidad de los filtros
     */
    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene los artículos con filtros aplicados
     */
    protected function getArticulos()
    {
        $query = Articulo::with(['categoriaModel', 'tipoIva', 'sucursales' => function($query) {
            $query->wherePivot('activo', true);
        }]);

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->search . '%')
                  ->orWhere('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de estado
        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        // Filtro de sucursal
        if ($this->filterSucursal !== 'all') {
            $query->whereHas('sucursales', function($q) {
                $q->where('sucursal_id', $this->filterSucursal)
                  ->where('articulo_sucursal.activo', true);
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

        return $query->orderBy('nombre')->paginate(10);
    }

    /**
     * Abre el modal para crear un nuevo artículo
     */
    public function create(): void
    {
        $this->reset([
            'codigo', 'nombre', 'descripcion', 'categoria_id',
            'unidad_medida', 'es_servicio', 'controla_stock', 'tipo_iva_id',
            'precio_iva_incluido', 'precio_base', 'activo', 'articuloId',
            'etiquetas_seleccionadas', 'busquedaEtiqueta'
        ]);
        $this->editMode = false;
        $this->activo = true;
        $this->controla_stock = true;
        $this->precio_iva_incluido = true;
        $this->unidad_medida = 'unidad';
        $this->precio_base = null;

        // Seleccionar todas las sucursales por defecto
        $this->sucursales_seleccionadas = Sucursal::pluck('id')->toArray();

        $this->showModal = true;
    }

    /**
     * Abre el modal para editar un artículo existente
     */
    public function edit(int $articuloId): void
    {
        $articulo = Articulo::with(['sucursales', 'etiquetas'])->findOrFail($articuloId);

        $this->articuloId = $articulo->id;
        $this->codigo = $articulo->codigo;
        $this->nombre = $articulo->nombre;
        $this->descripcion = $articulo->descripcion ?? '';
        $this->categoria_id = $articulo->categoria_id;
        $this->unidad_medida = $articulo->unidad_medida ?? 'unidad';
        $this->es_servicio = $articulo->es_servicio ?? false;
        $this->controla_stock = $articulo->controla_stock ?? true;
        $this->tipo_iva_id = $articulo->tipo_iva_id;
        $this->precio_iva_incluido = $articulo->precio_iva_incluido ?? true;
        $this->precio_base = $articulo->precio_base;
        $this->activo = $articulo->activo ?? true;

        // Cargar sucursales donde está activo
        $this->sucursales_seleccionadas = $articulo->sucursales()
            ->wherePivot('activo', true)
            ->pluck('sucursal_id')
            ->toArray();

        // Cargar etiquetas del artículo
        $this->etiquetas_seleccionadas = $articulo->etiquetas()->pluck('etiquetas.id')->toArray();
        $this->busquedaEtiqueta = '';

        $this->editMode = true;
        $this->showModal = true;
    }

    /**
     * Guarda el artículo (crear o actualizar)
     */
    public function save(): void
    {
        $rules = [
            'codigo' => 'required|string|max:50|unique:pymes_tenant.articulos,codigo,' . $this->articuloId,
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:1000',
            'categoria_id' => 'nullable|exists:pymes_tenant.categorias,id',
            'unidad_medida' => 'required|string|max:50',
            'es_servicio' => 'boolean',
            'controla_stock' => 'boolean',
            'tipo_iva_id' => 'required|exists:pymes_tenant.tipos_iva,id',
            'precio_iva_incluido' => 'boolean',
            'precio_base' => 'required|numeric|min:0',
            'activo' => 'boolean',
        ];

        $this->validate($rules);

        $datos = [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion ?: null,
            'categoria_id' => $this->categoria_id,
            'unidad_medida' => $this->unidad_medida,
            'es_servicio' => $this->es_servicio,
            'controla_stock' => $this->controla_stock,
            'tipo_iva_id' => $this->tipo_iva_id,
            'precio_iva_incluido' => $this->precio_iva_incluido,
            'precio_base' => $this->precio_base,
            'activo' => $this->activo,
        ];

        if ($this->editMode) {
            // Actualizar artículo existente
            $articulo = Articulo::findOrFail($this->articuloId);
            $articulo->update($datos);

            $message = 'Artículo actualizado correctamente';
        } else {
            // Crear nuevo artículo
            $articulo = Articulo::create($datos);

            $message = 'Artículo creado correctamente';
        }

        // Sincronizar sucursales
        $syncData = [];
        foreach ($this->sucursales_seleccionadas as $sucursalId) {
            $syncData[$sucursalId] = ['activo' => true];
        }

        // Primero marcar todas como inactivas, luego activar las seleccionadas
        $todasSucursales = Sucursal::pluck('id')->toArray();
        $syncDataCompleto = [];
        foreach ($todasSucursales as $sucursalId) {
            $syncDataCompleto[$sucursalId] = [
                'activo' => in_array($sucursalId, $this->sucursales_seleccionadas)
            ];
        }

        $articulo->sucursales()->sync($syncDataCompleto);

        // Sincronizar etiquetas
        $articulo->etiquetas()->sync($this->etiquetas_seleccionadas);

        $this->js("window.notify('$message', 'success')");
        $this->showModal = false;
        $this->reset([
            'codigo', 'nombre', 'descripcion', 'categoria_id',
            'unidad_medida', 'es_servicio', 'controla_stock', 'tipo_iva_id',
            'precio_iva_incluido', 'precio_base', 'activo', 'articuloId',
            'sucursales_seleccionadas', 'etiquetas_seleccionadas', 'busquedaEtiqueta'
        ]);
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->reset([
            'codigo', 'nombre', 'descripcion', 'categoria_id',
            'unidad_medida', 'es_servicio', 'controla_stock', 'tipo_iva_id',
            'precio_iva_incluido', 'precio_base', 'activo', 'articuloId',
            'sucursales_seleccionadas', 'etiquetas_seleccionadas', 'busquedaEtiqueta'
        ]);
    }

    /**
     * Toggle selección de etiqueta
     */
    public function toggleEtiqueta(int $etiquetaId): void
    {
        if (in_array($etiquetaId, $this->etiquetas_seleccionadas)) {
            $this->etiquetas_seleccionadas = array_values(array_diff($this->etiquetas_seleccionadas, [$etiquetaId]));
        } else {
            $this->etiquetas_seleccionadas[] = $etiquetaId;
        }
    }

    /**
     * Cambia el estado activo/inactivo de un artículo
     */
    public function toggleStatus(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $articulo->activo = !$articulo->activo;
        $articulo->save();

        $status = $articulo->activo ? 'activado' : 'desactivado';
        $this->js("window.notify('Artículo {$status} correctamente', 'success')");
    }

    /**
     * Abre el modal de confirmación de eliminación
     */
    public function confirmarEliminar(int $articuloId): void
    {
        $articulo = Articulo::find($articuloId);
        if ($articulo) {
            $this->articuloAEliminar = $articulo->id;
            $this->nombreArticuloAEliminar = $articulo->nombre;
            $this->showDeleteModal = true;
        }
    }

    /**
     * Cierra el modal de confirmación
     */
    public function cancelarEliminar(): void
    {
        $this->showDeleteModal = false;
        $this->articuloAEliminar = null;
        $this->nombreArticuloAEliminar = null;
    }

    /**
     * Ejecuta la eliminación después de confirmar
     */
    public function eliminar(): void
    {
        if (!$this->articuloAEliminar) {
            return;
        }

        $articulo = Articulo::find($this->articuloAEliminar);
        if ($articulo) {
            $articulo->delete(); // Soft delete
            $this->js("window.notify('Artículo eliminado correctamente', 'success')");
        }

        $this->cancelarEliminar();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get();
        $tiposIva = TipoIva::orderBy('porcentaje')->get();

        // Obtener sucursales del usuario actual
        $user = auth()->user();
        if ($user && method_exists($user, 'sucursales')) {
            $sucursalesUsuario = $user->sucursales()->orderBy('nombre')->get();
        } else {
            $sucursalesUsuario = Sucursal::orderBy('nombre')->get();
        }

        // Todas las sucursales para el modal de edición
        $sucursales = Sucursal::orderBy('nombre')->get();

        // Grupos de etiquetas con sus etiquetas activas (filtradas por búsqueda)
        $busqueda = $this->busquedaEtiqueta;

        $gruposEtiquetasQuery = GrupoEtiqueta::where('activo', true);

        // Si hay búsqueda, filtrar grupos que coincidan o que tengan etiquetas que coincidan
        if ($busqueda) {
            $gruposEtiquetasQuery->where(function ($query) use ($busqueda) {
                $query->where('nombre', 'like', '%' . $busqueda . '%')
                      ->orWhereHas('etiquetas', function ($q) use ($busqueda) {
                          $q->where('activo', true)
                            ->where('nombre', 'like', '%' . $busqueda . '%');
                      });
            });
        }

        $gruposEtiquetas = $gruposEtiquetasQuery->orderBy('orden')->orderBy('nombre')->get();

        // Cargar etiquetas filtradas para cada grupo
        foreach ($gruposEtiquetas as $grupo) {
            $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

            // Si hay búsqueda y el grupo NO coincide con la búsqueda, filtrar etiquetas
            // Si el grupo SÍ coincide, mostrar todas sus etiquetas
            if ($busqueda && !str_contains(strtolower($grupo->nombre), strtolower($busqueda))) {
                $etiquetasQuery->where('nombre', 'like', '%' . $busqueda . '%');
            }

            $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
        }

        return view('livewire.articulos.gestionar-articulos', [
            'articulos' => $this->getArticulos(),
            'categorias' => $categorias,
            'tiposIva' => $tiposIva,
            'sucursales' => $sucursales,
            'sucursalesUsuario' => $sucursalesUsuario,
            'gruposEtiquetas' => $gruposEtiquetas,
        ]);
    }
}
