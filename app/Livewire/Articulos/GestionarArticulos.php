<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\GrupoOpcional;
use App\Models\HistorialPrecio;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\OpcionalService;
use App\Traits\SucursalAware;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire para gestión de artículos (sucursal-aware)
 *
 * Muestra artículos activos en la sucursal activa con stock y precio efectivo.
 */
#[Layout('layouts.app')]
#[Lazy]
class GestionarArticulos extends Component
{
    use SucursalAware, WithPagination;

    // Propiedades de filtros
    public string $search = '';

    public string $filterTipo = 'all'; // all, articulo, materia_prima

    public array $categoriasSeleccionadas = [];

    public array $etiquetasSeleccionadasFiltro = [];

    public string $busquedaCategoriaFiltro = '';

    public string $busquedaEtiquetaFiltro = '';

    public bool $showFilters = false;

    // Propiedades del modal
    public bool $showModal = false;

    public bool $editMode = false;

    public ?int $articuloId = null;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;

    public ?int $articuloAEliminar = null;

    public ?string $nombreArticuloAEliminar = null;

    // Modal de opcionales
    public bool $showOpcionalesModal = false;

    public ?int $opcionalesArticuloId = null;

    public string $opcionalesArticuloNombre = '';

    public array $gruposAsignados = [];

    public bool $mostrandoAgregarGrupo = false;

    public string $busquedaGrupo = '';

    // Submodal confirmar desasignación
    public bool $showDesasignarModal = false;

    public ?int $grupoADesasignar = null;

    public ?string $nombreGrupoADesasignar = null;

    // Modal de receta
    public bool $showRecetaModal = false;

    public ?int $recetaArticuloId = null;

    public string $recetaArticuloNombre = '';

    public ?int $recetaId = null;

    public array $recetaIngredientes = [];

    public string $busquedaIngrediente = '';

    public array $resultadosBusqueda = [];

    public string $recetaCantidadProducida = '1.000';

    public string $recetaNotas = '';

    public bool $recetaEsOverride = false;

    public ?string $recetaSucursalNombre = null;

    // Modal de historial de precios
    public bool $showHistorialModal = false;

    public ?int $historialArticuloId = null;

    // Submodal confirmar eliminar receta
    public bool $showDeleteRecetaModal = false;

    // Propiedades del formulario
    public string $codigo = '';

    public string $codigo_barras = '';

    public string $nombre = '';

    public string $descripcion = '';

    public ?int $categoria_id = null;

    public string $unidad_medida = 'unidad';

    public bool $es_materia_prima = false;

    public ?int $tipo_iva_id = null;

    public bool $precio_iva_incluido = true;

    public ?float $precio_base = null;

    public bool $activo = true;

    public string $modo_stock = 'ninguno';

    public ?float $precio_sucursal = null;

    public bool $vendible = true;

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

    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de categorías y resetea la paginación
     */
    public function updatingCategoriasSeleccionadas(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de etiquetas y resetea la paginación
     */
    public function updatingEtiquetasSeleccionadasFiltro(): void
    {
        $this->resetPage();
    }

    /**
     * Hook: cuando cambia la categoría, proponer código automático si tiene prefijo
     */
    public function updatedCategoriaId($value): void
    {
        if (! $value) {
            return;
        }

        $categoria = Categoria::find($value);
        if (! $categoria || ! $categoria->prefijo) {
            return;
        }

        // Solo proponer si el código está vacío o ya es un código autogenerado de alguna categoría
        $debeProponerCodigo = empty($this->codigo);

        if (! $debeProponerCodigo) {
            // Verificar si el código actual matchea patrón de alguna categoría con prefijo
            $prefijos = Categoria::whereNotNull('prefijo')->pluck('prefijo')->toArray();
            foreach ($prefijos as $pref) {
                if (preg_match('/^'.preg_quote($pref, '/').'\d+$/', $this->codigo)) {
                    $debeProponerCodigo = true;
                    break;
                }
            }
        }

        if ($debeProponerCodigo) {
            $this->codigo = $this->calcularSiguienteCodigo($categoria->prefijo);
        }
    }

    /**
     * Calcula el siguiente código disponible para un prefijo dado
     */
    protected function calcularSiguienteCodigo(string $prefijo): string
    {
        $prefijo = strtoupper(trim($prefijo));

        // Buscar artículos cuyo código empiece con el prefijo
        $ultimoNumero = Articulo::where('codigo', 'LIKE', $prefijo.'%')
            ->get(['codigo'])
            ->map(function ($articulo) use ($prefijo) {
                $sufijo = substr($articulo->codigo, strlen($prefijo));

                return ctype_digit($sufijo) ? (int) $sufijo : 0;
            })
            ->max() ?? 0;

        return $prefijo.str_pad($ultimoNumero + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Alterna la visibilidad de los filtros
     */
    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    /**
     * Hook: al cambiar de sucursal, resetear estado
     */
    protected function onSucursalChanged($sucursalId, $sucursalNombre): void
    {
        $this->resetPage();
        $this->showModal = false;
        $this->showOpcionalesModal = false;
        $this->showRecetaModal = false;
        $this->showDeleteModal = false;
        $this->showHistorialModal = false;
    }

    /**
     * Obtiene los artículos activos en la sucursal activa
     */
    protected function getArticulos()
    {
        $sucursalId = sucursal_activa();

        if (! $sucursalId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        // Subqueries para datos de sucursal (deben usar la conexión tenant para el prefijo)
        $tenantDb = DB::connection('pymes_tenant');

        $precioSucursal = $tenantDb->table('articulos_sucursales')
            ->select('precio_base')
            ->whereColumn('articulo_id', 'articulos.id')
            ->where('sucursal_id', $sucursalId)
            ->limit(1);

        $modoStockSucursal = $tenantDb->table('articulos_sucursales')
            ->select('modo_stock')
            ->whereColumn('articulo_id', 'articulos.id')
            ->where('sucursal_id', $sucursalId)
            ->limit(1);

        $stockCantidad = $tenantDb->table('stock')
            ->select('cantidad')
            ->whereColumn('articulo_id', 'articulos.id')
            ->where('sucursal_id', $sucursalId)
            ->limit(1);

        $query = Articulo::query()
            ->select('articulos.*')
            ->selectSub($precioSucursal, 'precio_sucursal')
            ->selectSub($modoStockSucursal, 'modo_stock_sucursal')
            ->selectSub($stockCantidad, 'stock_cantidad')
            ->with(['categoriaModel', 'tipoIva'])
            // Solo artículos activos en la sucursal activa
            ->whereExists(function ($sub) use ($sucursalId) {
                $sub->select(DB::raw(1))
                    ->from('articulos_sucursales')
                    ->whereColumn('articulos_sucursales.articulo_id', 'articulos.id')
                    ->where('articulos_sucursales.sucursal_id', $sucursalId)
                    ->where('articulos_sucursales.activo', true);
            })
            ->where('articulos.activo', true)
            // Conteos por sucursal
            ->withCount(['gruposOpcionales as grupos_opcionales_count' => fn ($q) => $q->where('sucursal_id', $sucursalId)])
            ->withCount(['recetas as tiene_receta_override' => fn ($q) => $q->where('sucursal_id', $sucursalId)->where('activo', true)])
            ->withCount(['recetas as tiene_receta_default' => fn ($q) => $q->whereNull('sucursal_id')->where('activo', true)])
            ->withCount(['recetas as receta_anulada' => fn ($q) => $q->where('sucursal_id', $sucursalId)->where('activo', false)]);

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('articulos.codigo', 'like', '%'.$this->search.'%')
                    ->orWhere('articulos.nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('articulos.descripcion', 'like', '%'.$this->search.'%');
            });
        }

        // Filtro de tipo
        if ($this->filterTipo !== 'all') {
            $query->where('articulos.es_materia_prima', $this->filterTipo === 'materia_prima');
        }

        // Filtro de categorías
        if (! empty($this->categoriasSeleccionadas)) {
            $query->whereIn('articulos.categoria_id', $this->categoriasSeleccionadas);
        }

        // Filtro de etiquetas
        if (! empty($this->etiquetasSeleccionadasFiltro)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasSeleccionadasFiltro);
            });
        }

        return $query->orderBy('articulos.nombre')->paginate(10);
    }

    /**
     * Abre el modal para crear un nuevo artículo
     */
    public function create(): void
    {
        $this->resetFormularioArticulo();
        $this->editMode = false;
        $this->activo = true;
        $this->precio_iva_incluido = true;
        $this->unidad_medida = 'unidad';
        $this->precio_base = null;
        $this->modo_stock = 'ninguno';
        $this->precio_sucursal = null;
        $this->vendible = true;

        // En creación: solo la sucursal activa preseleccionada (las demás se crean inactivas)
        $sucursalActiva = sucursal_activa();
        $this->sucursales_seleccionadas = $sucursalActiva ? [$sucursalActiva] : [];

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
        $this->codigo_barras = $articulo->codigo_barras ?? '';
        $this->nombre = $articulo->nombre;
        $this->descripcion = $articulo->descripcion ?? '';
        $this->categoria_id = $articulo->categoria_id;
        $this->unidad_medida = $articulo->unidad_medida ?? 'unidad';
        $this->es_materia_prima = $articulo->es_materia_prima ?? false;
        $this->tipo_iva_id = $articulo->tipo_iva_id;
        $this->precio_iva_incluido = $articulo->precio_iva_incluido ?? true;
        $this->precio_base = $articulo->precio_base;
        $this->activo = $articulo->activo ?? true;

        // Cargar datos de la sucursal activa
        $sucursalId = sucursal_activa();
        $configSucursal = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        $this->modo_stock = $configSucursal?->modo_stock ?? 'ninguno';
        $this->precio_sucursal = $configSucursal?->precio_base;
        $this->vendible = (bool) ($configSucursal?->vendible ?? true);

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
            'codigo' => 'required|string|max:50|unique:pymes_tenant.articulos,codigo,'.$this->articuloId,
            'codigo_barras' => 'nullable|string|max:50',
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:1000',
            'categoria_id' => 'nullable|exists:pymes_tenant.categorias,id',
            'unidad_medida' => 'required|string|max:50',
            'es_materia_prima' => 'boolean',
            'tipo_iva_id' => 'required|exists:pymes_tenant.tipos_iva,id',
            'precio_iva_incluido' => 'boolean',
            'precio_base' => 'required|numeric|min:0',
            'activo' => 'boolean',
            'modo_stock' => 'required|in:ninguno,unitario,receta',
            'precio_sucursal' => 'nullable|numeric|min:0',
            'vendible' => 'boolean',
        ];

        $this->validate($rules);

        $datos = [
            'codigo' => $this->codigo,
            'codigo_barras' => $this->codigo_barras ?: null,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion ?: null,
            'categoria_id' => $this->categoria_id,
            'unidad_medida' => $this->unidad_medida,
            'es_materia_prima' => $this->es_materia_prima,
            'tipo_iva_id' => $this->tipo_iva_id,
            'precio_iva_incluido' => $this->precio_iva_incluido,
            'precio_base' => $this->precio_base,
            'activo' => $this->activo,
        ];

        if ($this->editMode) {
            // Actualizar artículo existente
            $articulo = Articulo::findOrFail($this->articuloId);
            $precioAnterior = (float) $articulo->precio_base;
            $articulo->update($datos);

            if ((float) $this->precio_base !== $precioAnterior) {
                HistorialPrecio::registrar([
                    'articulo_id' => $articulo->id,
                    'precio_anterior' => $precioAnterior,
                    'precio_nuevo' => $this->precio_base,
                    'origen' => 'articulo_editar',
                ]);
            }

            $message = __('Artículo actualizado correctamente');
        } else {
            // Crear nuevo artículo
            $articulo = Articulo::create($datos);

            HistorialPrecio::registrar([
                'articulo_id' => $articulo->id,
                'precio_anterior' => 0,
                'precio_nuevo' => $this->precio_base,
                'origen' => 'articulo_crear',
            ]);

            $message = __('Artículo creado correctamente');
        }

        // Sincronizar sucursales
        $todasSucursales = Sucursal::pluck('id')->toArray();
        $sucursalActiva = sucursal_activa();

        if ($this->editMode) {
            // En edición: actualizar config de la sucursal activa
            if ($sucursalActiva) {
                // Registrar historial si cambió el precio de sucursal
                $precioSucursalAnterior = DB::connection('pymes_tenant')->table('articulos_sucursales')
                    ->where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $sucursalActiva)
                    ->value('precio_base');

                DB::connection('pymes_tenant')->table('articulos_sucursales')
                    ->where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $sucursalActiva)
                    ->update([
                        'precio_base' => $this->precio_sucursal,
                        'modo_stock' => $this->modo_stock,
                        'vendible' => $this->vendible,
                    ]);

                if ((float) $precioSucursalAnterior !== (float) $this->precio_sucursal) {
                    HistorialPrecio::registrar([
                        'articulo_id' => $articulo->id,
                        'sucursal_id' => $sucursalActiva,
                        'precio_anterior' => $precioSucursalAnterior ?? $articulo->precio_base,
                        'precio_nuevo' => $this->precio_sucursal ?? $articulo->precio_base,
                        'origen' => 'override_sucursal',
                    ]);
                }

                // Auto-crear stock si cambió a modo que lo requiere
                if ($this->modo_stock !== 'ninguno') {
                    Stock::firstOrCreate(
                        ['articulo_id' => $articulo->id, 'sucursal_id' => $sucursalActiva],
                        ['cantidad' => 0, 'ultima_actualizacion' => now()]
                    );
                }
            }

            // Crear registros en sucursales nuevas (si se agregaron sucursales al comercio)
            $sucursalesExistentes = $articulo->sucursales()->pluck('sucursal_id')->toArray();
            $sucursalesNuevas = array_diff($todasSucursales, $sucursalesExistentes);
            foreach ($sucursalesNuevas as $sucursalId) {
                $articulo->sucursales()->attach($sucursalId, [
                    'activo' => false,
                    'modo_stock' => 'ninguno',
                ]);
            }
        } else {
            // En creación: activar solo en la sucursal activa, inactivo en las demás
            $syncDataCompleto = [];
            foreach ($todasSucursales as $sucursalId) {
                $esActiva = $sucursalId == $sucursalActiva;
                $syncDataCompleto[$sucursalId] = [
                    'activo' => $esActiva,
                    'modo_stock' => $esActiva ? $this->modo_stock : 'ninguno',
                    'vendible' => $esActiva ? $this->vendible : true,
                    'precio_base' => $esActiva ? $this->precio_sucursal : null,
                ];
            }
            $articulo->sucursales()->sync($syncDataCompleto);

            // Auto-crear fila en stock solo para sucursal activa
            if ($this->modo_stock !== 'ninguno' && $sucursalActiva) {
                Stock::firstOrCreate(
                    ['articulo_id' => $articulo->id, 'sucursal_id' => $sucursalActiva],
                    ['cantidad' => 0, 'ultima_actualizacion' => now()]
                );
            }
        }

        // Sincronizar etiquetas
        $articulo->etiquetas()->sync($this->etiquetas_seleccionadas);

        $this->js("window.notify('".addslashes($message)."', 'success')");
        $this->showModal = false;
        $this->resetFormularioArticulo();
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->resetFormularioArticulo();
    }

    protected function resetFormularioArticulo(): void
    {
        $this->reset([
            'codigo', 'codigo_barras', 'nombre', 'descripcion', 'categoria_id',
            'unidad_medida', 'es_materia_prima', 'tipo_iva_id',
            'precio_iva_incluido', 'precio_base', 'activo', 'articuloId',
            'sucursales_seleccionadas', 'etiquetas_seleccionadas', 'busquedaEtiqueta',
            'modo_stock', 'precio_sucursal', 'vendible',
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
     * Desactiva un artículo en la sucursal activa
     */
    public function desactivarEnSucursal(int $articuloId): void
    {
        $sucursalId = sucursal_activa();
        if (! $sucursalId) {
            return;
        }

        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->update(['activo' => false]);

        $this->js("window.notify('".addslashes(__('Artículo desactivado en esta sucursal'))."', 'success')");
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
        if (! $this->articuloAEliminar) {
            return;
        }

        $articulo = Articulo::find($this->articuloAEliminar);
        if ($articulo) {
            $articulo->delete(); // Soft delete
            $this->js("window.notify('".__('Artículo eliminado correctamente')."', 'success')");
        }

        $this->cancelarEliminar();
    }

    // ===== Opcionales Modal =====

    public function gestionarOpcionales(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $this->opcionalesArticuloId = $articulo->id;
        $this->opcionalesArticuloNombre = $articulo->nombre;

        $this->cargarGruposAsignados();
        $this->showOpcionalesModal = true;
    }

    protected function cargarGruposAsignados(): void
    {
        if (! $this->opcionalesArticuloId) {
            return;
        }

        $sucursalId = sucursal_activa();

        $asignaciones = ArticuloGrupoOpcional::with([
            'grupoOpcional',
            'opciones.opcional' => fn ($q) => $q->orderBy('orden'),
        ])
            ->where('articulo_id', $this->opcionalesArticuloId)
            ->where('sucursal_id', $sucursalId)
            ->orderBy('orden')
            ->get();

        $this->gruposAsignados = $asignaciones->map(function ($asig) {
            return [
                'id' => $asig->id,
                'grupo_id' => $asig->grupo_opcional_id,
                'nombre' => $asig->grupoOpcional->nombre,
                'tipo' => $asig->grupoOpcional->tipo,
                'obligatorio' => $asig->grupoOpcional->obligatorio,
                'activo' => $asig->activo,
                'orden' => $asig->orden,
                'opciones' => $asig->opciones->map(fn ($op) => [
                    'opcion_id' => $op->id,
                    'opcional_id' => $op->opcional_id,
                    'nombre' => $op->opcional?->nombre ?? __('Opción eliminada'),
                    'precio_extra' => (string) $op->precio_extra,
                    'activo' => $op->activo,
                    'disponible' => $op->disponible,
                ])->toArray(),
            ];
        })->toArray();
    }

    public function actualizarOpcion(int $grupoIndex, int $opcionIndex, string $campo, $valor): void
    {
        if (! isset($this->gruposAsignados[$grupoIndex]['opciones'][$opcionIndex])) {
            return;
        }

        $opcionId = $this->gruposAsignados[$grupoIndex]['opciones'][$opcionIndex]['opcion_id'];

        $opcion = \App\Models\ArticuloGrupoOpcionalOpcion::find($opcionId);
        if (! $opcion) {
            return;
        }

        if ($campo === 'activo' || $campo === 'disponible') {
            $opcion->update([$campo => ! $opcion->$campo]);
            $this->gruposAsignados[$grupoIndex]['opciones'][$opcionIndex][$campo] = ! $this->gruposAsignados[$grupoIndex]['opciones'][$opcionIndex][$campo];
        } elseif ($campo === 'precio_extra') {
            $opcion->update(['precio_extra' => max(0, (float) $valor)]);
            $this->gruposAsignados[$grupoIndex]['opciones'][$opcionIndex]['precio_extra'] = (string) max(0, (float) $valor);
        }
    }

    public function abrirAgregarGrupo(): void
    {
        $this->busquedaGrupo = '';
        $this->mostrandoAgregarGrupo = true;
    }

    public function cancelarAgregarGrupo(): void
    {
        $this->mostrandoAgregarGrupo = false;
        $this->busquedaGrupo = '';
    }

    public function getGruposDisponiblesProperty(): array
    {
        $gruposYaAsignados = collect($this->gruposAsignados)->pluck('grupo_id')->toArray();

        $query = GrupoOpcional::where('activo', true)
            ->whereNotIn('id', $gruposYaAsignados)
            ->withCount(['opcionales' => fn ($q) => $q->where('activo', true)]);

        if ($this->busquedaGrupo) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->busquedaGrupo.'%')
                    ->orWhere('descripcion', 'like', '%'.$this->busquedaGrupo.'%');
            });
        }

        return $query->orderBy('nombre')->limit(20)->get()->map(fn ($g) => [
            'id' => $g->id,
            'nombre' => $g->nombre,
            'tipo' => $g->tipo,
            'obligatorio' => $g->obligatorio,
            'opcionales_count' => $g->opcionales_count,
        ])->toArray();
    }

    public function asignarGrupo(int $grupoId): void
    {
        if (! $this->opcionalesArticuloId) {
            return;
        }

        $service = app(OpcionalService::class);
        $count = $service->asignarGrupoAArticulo($this->opcionalesArticuloId, $grupoId);

        $grupo = GrupoOpcional::find($grupoId);
        $nombre = $grupo ? $grupo->nombre : '';

        $this->js("window.notify('".addslashes(__('Grupo ":nombre" asignado en :count sucursales', ['nombre' => $nombre, 'count' => $count]))."', 'success')");

        $this->mostrandoAgregarGrupo = false;
        $this->cargarGruposAsignados();
    }

    public function moverGrupoArriba(int $index): void
    {
        if ($index <= 0 || ! $this->opcionalesArticuloId) {
            return;
        }

        $grupoActual = $this->gruposAsignados[$index];
        $grupoAnterior = $this->gruposAsignados[$index - 1];

        $this->intercambiarOrden($grupoActual['grupo_id'], $grupoAnterior['grupo_id']);
        $this->cargarGruposAsignados();
    }

    public function moverGrupoAbajo(int $index): void
    {
        if ($index >= count($this->gruposAsignados) - 1 || ! $this->opcionalesArticuloId) {
            return;
        }

        $grupoActual = $this->gruposAsignados[$index];
        $grupoSiguiente = $this->gruposAsignados[$index + 1];

        $this->intercambiarOrden($grupoActual['grupo_id'], $grupoSiguiente['grupo_id']);
        $this->cargarGruposAsignados();
    }

    protected function intercambiarOrden(int $grupoIdA, int $grupoIdB): void
    {
        $sucursalId = sucursal_activa();
        $asignaciones = ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
            ->where('sucursal_id', $sucursalId)
            ->orderBy('orden')
            ->get();

        foreach ($asignaciones as $i => $asig) {
            if ($asig->orden !== $i) {
                ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
                    ->where('grupo_opcional_id', $asig->grupo_opcional_id)
                    ->update(['orden' => $i]);
            }
        }

        $ordenA = ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
            ->where('grupo_opcional_id', $grupoIdA)
            ->where('sucursal_id', $sucursalId)
            ->value('orden');

        $ordenB = ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
            ->where('grupo_opcional_id', $grupoIdB)
            ->where('sucursal_id', $sucursalId)
            ->value('orden');

        ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
            ->where('grupo_opcional_id', $grupoIdA)
            ->update(['orden' => $ordenB]);

        ArticuloGrupoOpcional::where('articulo_id', $this->opcionalesArticuloId)
            ->where('grupo_opcional_id', $grupoIdB)
            ->update(['orden' => $ordenA]);
    }

    public function confirmarDesasignar(int $grupoId, string $nombre): void
    {
        $this->grupoADesasignar = $grupoId;
        $this->nombreGrupoADesasignar = $nombre;
        $this->showDesasignarModal = true;
    }

    public function desasignarGrupo(): void
    {
        if (! $this->opcionalesArticuloId || ! $this->grupoADesasignar) {
            return;
        }

        $service = app(OpcionalService::class);
        $service->desasignarGrupoDeArticulo($this->opcionalesArticuloId, $this->grupoADesasignar);

        $this->js("window.notify('".addslashes(__('Grupo desasignado correctamente'))."', 'success')");
        $this->showDesasignarModal = false;
        $this->grupoADesasignar = null;
        $this->nombreGrupoADesasignar = null;
        $this->cargarGruposAsignados();
    }

    public function cancelarDesasignar(): void
    {
        $this->showDesasignarModal = false;
        $this->grupoADesasignar = null;
        $this->nombreGrupoADesasignar = null;
    }

    public function cancelarOpcionales(): void
    {
        $this->showOpcionalesModal = false;
        $this->mostrandoAgregarGrupo = false;
        $this->opcionalesArticuloId = null;
        $this->opcionalesArticuloNombre = '';
        $this->gruposAsignados = [];
    }

    // ===== Receta Modal =====

    public function editarReceta(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $sucursalId = sucursal_activa();

        $this->recetaArticuloId = $articulo->id;
        $this->recetaArticuloNombre = $articulo->nombre;
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;

        // Buscar override de sucursal primero
        $receta = Receta::where('recetable_type', 'Articulo')
            ->where('recetable_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->with('ingredientes.articulo')
            ->first();

        if ($receta) {
            // Override existe (activo o anulado)
            $this->recetaId = $receta->id;
            $this->recetaCantidadProducida = (string) $receta->cantidad_producida;
            $this->recetaNotas = $receta->notas ?? '';
            $this->recetaIngredientes = $receta->activo ? $receta->ingredientes->map(fn ($ing) => [
                'articulo_id' => $ing->articulo_id,
                'codigo' => $ing->articulo->codigo ?? '',
                'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
                'unidad_medida' => $ing->articulo->unidad_medida ?? '',
                'cantidad' => (string) $ing->cantidad,
            ])->toArray() : [];
        } else {
            // Sin override: buscar default y copiar ingredientes para edición
            $recetaDefault = Receta::where('recetable_type', 'Articulo')
                ->where('recetable_id', $articuloId)
                ->whereNull('sucursal_id')
                ->where('activo', true)
                ->with('ingredientes.articulo')
                ->first();

            $this->recetaId = null;
            $this->recetaCantidadProducida = $recetaDefault ? (string) $recetaDefault->cantidad_producida : '1.000';
            $this->recetaNotas = $recetaDefault->notas ?? '';
            $this->recetaIngredientes = $recetaDefault ? $recetaDefault->ingredientes->map(fn ($ing) => [
                'articulo_id' => $ing->articulo_id,
                'codigo' => $ing->articulo->codigo ?? '',
                'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
                'unidad_medida' => $ing->articulo->unidad_medida ?? '',
                'cantidad' => (string) $ing->cantidad,
            ])->toArray() : [];
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
        if ($this->recetaArticuloId) {
            $excluirIds[] = $this->recetaArticuloId;
        }

        $this->resultadosBusqueda = Articulo::where('activo', true)
            ->whereNotIn('id', $excluirIds)
            ->where(function ($q) {
                $q->where('codigo', 'like', '%'.$this->busquedaIngrediente.'%')
                    ->orWhere('nombre', 'like', '%'.$this->busquedaIngrediente.'%');
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad_medida'])
            ->map(fn ($a) => [
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
        if (! $articulo) {
            return;
        }

        foreach ($this->recetaIngredientes as $ing) {
            if ($ing['articulo_id'] == $articuloId) {
                return;
            }
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

    public function guardarReceta(): void
    {
        if (! $this->recetaArticuloId) {
            return;
        }

        if (empty($this->recetaIngredientes)) {
            $this->js("window.notify('".addslashes(__('La receta debe tener al menos un ingrediente'))."', 'error')");

            return;
        }

        foreach ($this->recetaIngredientes as $ing) {
            if (! isset($ing['cantidad']) || (float) $ing['cantidad'] <= 0) {
                $this->js("window.notify('".addslashes(__('Todas las cantidades deben ser mayores a 0'))."', 'error')");

                return;
            }
        }

        $sucursalId = sucursal_activa();

        DB::connection('pymes_tenant')->transaction(function () use ($sucursalId) {
            if ($this->recetaId) {
                $receta = Receta::findOrFail($this->recetaId);
                $receta->update([
                    'cantidad_producida' => $this->recetaCantidadProducida,
                    'notas' => $this->recetaNotas ?: null,
                    'activo' => true,
                ]);
            } else {
                $receta = Receta::create([
                    'recetable_type' => 'Articulo',
                    'recetable_id' => $this->recetaArticuloId,
                    'sucursal_id' => $sucursalId,
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

        $this->js("window.notify('".addslashes(__('Receta guardada correctamente'))."', 'success')");
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function confirmarEliminarReceta(): void
    {
        if ($this->recetaId) {
            $this->showDeleteRecetaModal = true;
        }
    }

    public function eliminarReceta(): void
    {
        if (! $this->recetaId) {
            return;
        }

        $receta = Receta::find($this->recetaId);
        if ($receta && $receta->sucursal_id) {
            // Solo eliminar overrides de sucursal, nunca el default
            $receta->ingredientes()->delete();
            $receta->delete();
        }

        $this->js("window.notify('".addslashes(__('Receta eliminada correctamente'))."', 'success')");
        $this->showDeleteRecetaModal = false;
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    /**
     * Anula la receta en esta sucursal (crea override inactivo)
     */
    public function anularReceta(): void
    {
        if (! $this->recetaArticuloId) {
            return;
        }

        $sucursalId = sucursal_activa();

        if ($this->recetaId) {
            Receta::where('id', $this->recetaId)->update(['activo' => false]);
        } else {
            Receta::create([
                'recetable_type' => 'Articulo',
                'recetable_id' => $this->recetaArticuloId,
                'sucursal_id' => $sucursalId,
                'cantidad_producida' => 1,
                'activo' => false,
            ]);
        }

        $this->js("window.notify('".addslashes(__('Receta anulada en esta sucursal'))."', 'success')");
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
        $this->recetaArticuloId = null;
        $this->recetaArticuloNombre = '';
        $this->recetaId = null;
        $this->recetaIngredientes = [];
        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->recetaCantidadProducida = '1.000';
        $this->recetaNotas = '';
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;
    }

    // ===== Historial de Precios Modal =====

    public function verHistorial(int $articuloId): void
    {
        $this->historialArticuloId = $articuloId;
        $this->showHistorialModal = true;
    }

    public function cerrarHistorial(): void
    {
        $this->showHistorialModal = false;
        $this->historialArticuloId = null;
    }

    public function getHistorial(): array
    {
        if (! $this->historialArticuloId) {
            return [];
        }

        $registros = HistorialPrecio::where('articulo_id', $this->historialArticuloId)
            ->with('sucursal')
            ->latest()
            ->take(50)
            ->get();

        // Obtener nombres de usuario desde config (cross-connection)
        $userIds = $registros->pluck('usuario_id')->filter()->unique()->values()->toArray();
        $usuarios = [];
        if (! empty($userIds)) {
            $usuarios = DB::connection('config')->table('users')
                ->whereIn('id', $userIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        return $registros->map(function ($r) use ($usuarios) {
            return [
                'fecha' => $r->created_at->format('d/m/Y H:i'),
                'usuario' => $usuarios[$r->usuario_id] ?? '-',
                'precio_anterior' => $r->precio_anterior,
                'precio_nuevo' => $r->precio_nuevo,
                'origen' => $r->origen,
                'sucursal' => $r->sucursal?->nombre,
                'detalle' => $r->detalle,
                'porcentaje_cambio' => $r->porcentaje_cambio,
            ];
        })->toArray();
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="3" :columns="6" :rows="8" />
        HTML;
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        // Datos del modal: solo cuando está abierto
        $categorias = collect();
        $tiposIva = collect();
        $gruposEtiquetas = collect();

        if ($this->showModal) {
            $categorias = CatalogoCache::categorias();
            $tiposIva = CatalogoCache::tiposIva();

            $busquedaModal = $this->busquedaEtiqueta;
            $gruposEtiquetasQuery = GrupoEtiqueta::where('activo', true);

            if ($busquedaModal) {
                $gruposEtiquetasQuery->where(function ($query) use ($busquedaModal) {
                    $query->where('nombre', 'like', '%'.$busquedaModal.'%')
                        ->orWhereHas('etiquetas', function ($q) use ($busquedaModal) {
                            $q->where('activo', true)
                                ->where('nombre', 'like', '%'.$busquedaModal.'%');
                        });
                });
            }

            $gruposEtiquetas = $gruposEtiquetasQuery->orderBy('orden')->orderBy('nombre')->get();

            foreach ($gruposEtiquetas as $grupo) {
                $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

                if ($busquedaModal && ! str_contains(strtolower($grupo->nombre), strtolower($busquedaModal))) {
                    $etiquetasQuery->where('nombre', 'like', '%'.$busquedaModal.'%');
                }

                $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
            }
        }

        // Filtros avanzados: solo cuando están expandidos
        $categoriasFiltro = collect();
        $gruposEtiquetasFiltro = collect();

        if ($this->showFilters) {
            $categoriasFiltroQuery = Categoria::where('activo', true);
            if ($this->busquedaCategoriaFiltro) {
                $categoriasFiltroQuery->where('nombre', 'like', '%'.$this->busquedaCategoriaFiltro.'%');
            }
            $categoriasFiltro = $categoriasFiltroQuery->orderBy('nombre')->get();

            $busquedaFiltro = $this->busquedaEtiquetaFiltro;
            $gruposEtiquetasFiltroQuery = GrupoEtiqueta::where('activo', true);

            if ($busquedaFiltro) {
                $gruposEtiquetasFiltroQuery->where(function ($query) use ($busquedaFiltro) {
                    $query->where('nombre', 'like', '%'.$busquedaFiltro.'%')
                        ->orWhereHas('etiquetas', function ($q) use ($busquedaFiltro) {
                            $q->where('activo', true)
                                ->where('nombre', 'like', '%'.$busquedaFiltro.'%');
                        });
                });
            }

            $gruposEtiquetasFiltro = $gruposEtiquetasFiltroQuery->orderBy('orden')->orderBy('nombre')->get();

            foreach ($gruposEtiquetasFiltro as $grupo) {
                $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

                if ($busquedaFiltro && ! str_contains(strtolower($grupo->nombre), strtolower($busquedaFiltro))) {
                    $etiquetasQuery->where('nombre', 'like', '%'.$busquedaFiltro.'%');
                }

                $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
            }
        }

        return view('livewire.articulos.gestionar-articulos', [
            'articulos' => $this->getArticulos(),
            'categorias' => $categorias,
            'categoriasFiltro' => $categoriasFiltro,
            'tiposIva' => $tiposIva,
            'gruposEtiquetas' => $gruposEtiquetas,
            'gruposEtiquetasFiltro' => $gruposEtiquetasFiltro,
        ]);
    }
}
