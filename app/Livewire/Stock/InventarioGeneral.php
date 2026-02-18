<?php

namespace App\Livewire\Stock;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\GrupoEtiqueta;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Exception;

#[Layout('layouts.app')]
class InventarioGeneral extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public array $categoriasSeleccionadas = [];
    public array $etiquetasSeleccionadas = [];
    public string $busquedaCategoria = '';
    public string $busquedaEtiqueta = '';
    public bool $showFilters = false;

    // Datos de inventario
    public array $cantidadesFisicas = [];
    public string $observacionesGlobal = '';

    // Modales
    public bool $showConfirmModal = false;
    public bool $showResultModal = false;
    public array $resultado = [];

    protected $stockService;

    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function boot(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        $this->cantidadesFisicas = [];
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoriasSeleccionadas(): void
    {
        $this->resetPage();
    }

    public function updatingEtiquetasSeleccionadas(): void
    {
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Actualiza una cantidad física desde el input (wire:change)
     */
    public function actualizarCantidad(int $articuloId, $valor)
    {
        if ($valor === '' || $valor === null) {
            unset($this->cantidadesFisicas[$articuloId]);
        } else {
            $this->cantidadesFisicas[$articuloId] = (float) str_replace(',', '.', $valor);
        }
    }

    /**
     * Limpia todas las cantidades ingresadas
     */
    public function limpiarTodo()
    {
        $this->cantidadesFisicas = [];
        $this->observacionesGlobal = '';
    }

    /**
     * Obtiene el conteo de artículos con cantidades ingresadas
     */
    public function getConteoIngresadosProperty(): int
    {
        return count($this->cantidadesFisicas);
    }

    /**
     * Muestra el modal de confirmación
     */
    public function confirmarProcesar()
    {
        if (empty($this->cantidadesFisicas)) {
            $this->dispatch('toast-error', message: __('No hay artículos con conteo ingresado'));
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
     * Procesa el inventario general en una sola transacción
     */
    public function procesarInventario()
    {
        if (empty($this->cantidadesFisicas)) {
            $this->dispatch('toast-error', message: __('No hay artículos con conteo ingresado'));
            return;
        }

        $sucursalId = sucursal_activa();
        $usuarioId = Auth::id();

        $procesados = 0;
        $sobrantes = 0;
        $faltantes = 0;
        $sinDiferencia = 0;
        $errores = [];

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            foreach ($this->cantidadesFisicas as $articuloId => $cantidadFisica) {
                // Obtener o crear el stock para este artículo en la sucursal activa
                $stock = Stock::firstOrCreate(
                    [
                        'articulo_id' => $articuloId,
                        'sucursal_id' => $sucursalId,
                    ],
                    [
                        'cantidad' => 0,
                        'cantidad_minima' => null,
                        'cantidad_maxima' => null,
                        'ultima_actualizacion' => now(),
                    ]
                );

                $resultado = $this->stockService->registrarInventarioFisicoInterno(
                    $stock->id,
                    $cantidadFisica,
                    $usuarioId,
                    $this->observacionesGlobal ?: null
                );

                $procesados++;

                if ($resultado['tipo_diferencia'] === 'sobrante') {
                    $sobrantes++;
                } elseif ($resultado['tipo_diferencia'] === 'faltante') {
                    $faltantes++;
                } else {
                    $sinDiferencia++;
                }
            }

            DB::connection('pymes_tenant')->commit();

            $this->resultado = [
                'procesados' => $procesados,
                'sobrantes' => $sobrantes,
                'faltantes' => $faltantes,
                'sin_diferencia' => $sinDiferencia,
            ];

            Log::info('Inventario general procesado', [
                'sucursal_id' => $sucursalId,
                'usuario_id' => $usuarioId,
                'procesados' => $procesados,
                'sobrantes' => $sobrantes,
                'faltantes' => $faltantes,
                'sin_diferencia' => $sinDiferencia,
            ]);

            $this->showConfirmModal = false;
            $this->showResultModal = true;
            $this->cantidadesFisicas = [];
            $this->observacionesGlobal = '';

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al procesar inventario general', [
                'error' => $e->getMessage(),
                'sucursal_id' => $sucursalId,
            ]);
            $this->showConfirmModal = false;
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Cierra el modal de resultado y vuelve al inventario de stock
     */
    public function cerrarResultado()
    {
        return redirect()->route('stock.index');
    }

    /**
     * Volver al listado de stock
     */
    public function volver()
    {
        return redirect()->route('stock.index');
    }

    public function render()
    {
        $sucursalId = sucursal_activa();

        // Query de artículos que controlan stock en esta sucursal
        $query = Articulo::where('activo', true)
            ->conStockEnSucursal($sucursalId);

        // Filtro de categorías
        if (!empty($this->categoriasSeleccionadas)) {
            $query->whereIn('categoria_id', $this->categoriasSeleccionadas);
        }

        // Filtro de etiquetas
        if (!empty($this->etiquetasSeleccionadas)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasSeleccionadas);
            });
        }

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('codigo', 'like', "%{$this->search}%");
            });
        }

        $articulos = $query->with(['categoriaModel'])
            ->orderBy('nombre')
            ->paginate(50);

        // Cargar stock actual para cada artículo
        $articuloIds = $articulos->pluck('id')->toArray();
        $stocksPorArticulo = Stock::where('sucursal_id', $sucursalId)
            ->whereIn('articulo_id', $articuloIds)
            ->pluck('cantidad', 'articulo_id')
            ->toArray();

        // Categorías con filtrado
        $categoriasQuery = Categoria::where('activo', true);
        if ($this->busquedaCategoria) {
            $categoriasQuery->where('nombre', 'like', '%' . $this->busquedaCategoria . '%');
        }
        $categorias = $categoriasQuery->orderBy('nombre')->get();

        // Grupos de etiquetas con filtrado
        $busqueda = $this->busquedaEtiqueta;
        $gruposEtiquetasQuery = GrupoEtiqueta::where('activo', true);

        if ($busqueda) {
            $gruposEtiquetasQuery->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', '%' . $busqueda . '%')
                  ->orWhereHas('etiquetas', function ($eq) use ($busqueda) {
                      $eq->where('activo', true)
                        ->where('nombre', 'like', '%' . $busqueda . '%');
                  });
            });
        }

        $gruposEtiquetas = $gruposEtiquetasQuery->orderBy('orden')->orderBy('nombre')->get();

        foreach ($gruposEtiquetas as $grupo) {
            $etiquetasQuery = $grupo->etiquetas()->where('activo', true);

            if ($busqueda && !str_contains(strtolower($grupo->nombre), strtolower($busqueda))) {
                $etiquetasQuery->where('nombre', 'like', '%' . $busqueda . '%');
            }

            $grupo->setRelation('etiquetas', $etiquetasQuery->orderBy('orden')->orderBy('nombre')->get());
        }

        return view('livewire.stock.inventario-general', [
            'articulos' => $articulos,
            'stocksPorArticulo' => $stocksPorArticulo,
            'categorias' => $categorias,
            'gruposEtiquetas' => $gruposEtiquetas,
        ]);
    }
}
