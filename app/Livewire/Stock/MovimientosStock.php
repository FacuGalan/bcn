<?php

namespace App\Livewire\Stock;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MovimientoStock;
use App\Models\Stock;
use App\Models\Articulo;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class MovimientosStock extends Component
{
    use WithPagination;

    // Filtros
    public $search = '';
    public $filterTipo = '';
    public $filterFechaDesde = '';
    public $filterFechaHasta = '';
    public $articuloSeleccionado = null;
    public bool $showFilters = false;

    // Modales
    public $showCargaModal = false;
    public $showDescargaModal = false;
    public $showInventarioModal = false;

    // Carga de stock
    public $cargaArticuloId = null;
    public $cargaCantidad = 0;
    public $cargaConcepto = '';
    public $cargaObservaciones = '';
    public $cargaSearchArticulo = '';

    // Descarga de stock
    public $descargaArticuloId = null;
    public $descargaCantidad = 0;
    public $descargaConcepto = '';
    public $descargaObservaciones = '';
    public $descargaSearchArticulo = '';

    // Inventario físico
    public $inventarioArticuloId = null;
    public $inventarioCantidadFisica = 0;
    public $inventarioObservaciones = '';
    public $inventarioSearchArticulo = '';
    public $inventarioStockActual = 0;

    protected $stockService;

    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function boot(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount()
    {
        $this->filterFechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
    }

    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        $this->showCargaModal = false;
        $this->showDescargaModal = false;
        $this->showInventarioModal = false;
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    public function render()
    {
        $sucursalId = sucursal_activa();
        $movimientos = $this->obtenerMovimientos($sucursalId);

        // Resumen del día
        $hoy = now()->toDateString();
        $resumenHoy = MovimientoStock::porSucursal($sucursalId)
            ->activos()
            ->where('fecha', $hoy)
            ->selectRaw('
                COALESCE(SUM(entrada), 0) as total_entradas,
                COALESCE(SUM(salida), 0) as total_salidas,
                COUNT(*) as total_movimientos
            ')
            ->first();

        return view('livewire.stock.movimientos-stock', [
            'movimientos' => $movimientos,
            'totalEntradasHoy' => (float) ($resumenHoy->total_entradas ?? 0),
            'totalSalidasHoy' => (float) ($resumenHoy->total_salidas ?? 0),
            'totalMovimientosHoy' => (int) ($resumenHoy->total_movimientos ?? 0),
            'tiposMovimiento' => $this->getTiposMovimiento(),
        ]);
    }

    protected function obtenerMovimientos($sucursalId)
    {
        $query = MovimientoStock::with(['articulo', 'usuario', 'venta', 'compra', 'transferencia'])
            ->porSucursal($sucursalId)
            ->activos();

        if ($this->search) {
            $query->whereHas('articulo', function ($q) {
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('codigo', 'like', "%{$this->search}%");
            });
        }

        if ($this->articuloSeleccionado) {
            $query->porArticulo($this->articuloSeleccionado);
        }

        if ($this->filterTipo) {
            $query->porTipo($this->filterTipo);
        }

        $query->entreFechas(
            $this->filterFechaDesde ?: null,
            $this->filterFechaHasta ?: null
        );

        return $query->orderBy('id', 'desc')->paginate(20);
    }

    protected function getTiposMovimiento(): array
    {
        return [
            MovimientoStock::TIPO_VENTA => __('Venta'),
            MovimientoStock::TIPO_COMPRA => __('Compra'),
            MovimientoStock::TIPO_AJUSTE_MANUAL => __('Ajuste Manual'),
            MovimientoStock::TIPO_INVENTARIO_FISICO => __('Inventario Físico'),
            MovimientoStock::TIPO_TRANSFERENCIA_SALIDA => __('Transferencia Salida'),
            MovimientoStock::TIPO_TRANSFERENCIA_ENTRADA => __('Transferencia Entrada'),
            MovimientoStock::TIPO_ANULACION_VENTA => __('Anulación Venta'),
            MovimientoStock::TIPO_ANULACION_COMPRA => __('Anulación Compra'),
            MovimientoStock::TIPO_DEVOLUCION => __('Devolución'),
            MovimientoStock::TIPO_CARGA_INICIAL => __('Carga Inicial'),
        ];
    }

    // ==================== Búsqueda de artículos ====================

    public function getArticulosCargaProperty()
    {
        if (strlen($this->cargaSearchArticulo) < 2) return collect();
        return Articulo::activos()->conStock()
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->cargaSearchArticulo}%")
                  ->orWhere('codigo', 'like', "%{$this->cargaSearchArticulo}%");
            })
            ->limit(10)->get();
    }

    public function getArticulosDescargaProperty()
    {
        if (strlen($this->descargaSearchArticulo) < 2) return collect();
        return Articulo::activos()->conStock()
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->descargaSearchArticulo}%")
                  ->orWhere('codigo', 'like', "%{$this->descargaSearchArticulo}%");
            })
            ->limit(10)->get();
    }

    public function getArticulosInventarioProperty()
    {
        if (strlen($this->inventarioSearchArticulo) < 2) return collect();
        return Articulo::activos()->conStock()
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->inventarioSearchArticulo}%")
                  ->orWhere('codigo', 'like', "%{$this->inventarioSearchArticulo}%");
            })
            ->limit(10)->get();
    }

    // ==================== Carga de Stock ====================

    public function abrirModalCarga()
    {
        $this->resetCargaForm();
        $this->showCargaModal = true;
    }

    public function seleccionarArticuloCarga($articuloId)
    {
        $this->cargaArticuloId = $articuloId;
        $articulo = Articulo::find($articuloId);
        $this->cargaSearchArticulo = $articulo ? $articulo->nombre : '';
    }

    public function procesarCarga()
    {
        $this->validate([
            'cargaArticuloId' => 'required|exists:pymes_tenant.articulos,id',
            'cargaCantidad' => 'required|numeric|min:0.01',
            'cargaConcepto' => 'required|string|max:255',
        ], [
            'cargaArticuloId.required' => __('Seleccione un artículo'),
            'cargaCantidad.required' => __('Ingrese la cantidad'),
            'cargaCantidad.min' => __('La cantidad debe ser mayor a 0'),
            'cargaConcepto.required' => __('Ingrese un motivo'),
        ]);

        try {
            $sucursalId = sucursal_activa();
            $stock = Stock::firstOrCreate(
                [
                    'articulo_id' => $this->cargaArticuloId,
                    'sucursal_id' => $sucursalId,
                ],
                [
                    'cantidad' => 0,
                    'ultima_actualizacion' => now(),
                ]
            );

            $this->stockService->ajustarStock(
                $stock->id,
                abs($this->cargaCantidad),
                Auth::id(),
                $this->cargaConcepto
            );

            $this->dispatch('toast-success', message: __('Carga de stock registrada exitosamente'));
            $this->showCargaModal = false;
            $this->resetCargaForm();

        } catch (Exception $e) {
            Log::error('Error al cargar stock', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    protected function resetCargaForm()
    {
        $this->cargaArticuloId = null;
        $this->cargaCantidad = 0;
        $this->cargaConcepto = '';
        $this->cargaObservaciones = '';
        $this->cargaSearchArticulo = '';
    }

    // ==================== Descarga de Stock ====================

    public function abrirModalDescarga()
    {
        $this->resetDescargaForm();
        $this->showDescargaModal = true;
    }

    public function seleccionarArticuloDescarga($articuloId)
    {
        $this->descargaArticuloId = $articuloId;
        $articulo = Articulo::find($articuloId);
        $this->descargaSearchArticulo = $articulo ? $articulo->nombre : '';
    }

    public function procesarDescarga()
    {
        $this->validate([
            'descargaArticuloId' => 'required|exists:pymes_tenant.articulos,id',
            'descargaCantidad' => 'required|numeric|min:0.01',
            'descargaConcepto' => 'required|string|max:255',
        ], [
            'descargaArticuloId.required' => __('Seleccione un artículo'),
            'descargaCantidad.required' => __('Ingrese la cantidad'),
            'descargaCantidad.min' => __('La cantidad debe ser mayor a 0'),
            'descargaConcepto.required' => __('Ingrese un motivo'),
        ]);

        try {
            $sucursalId = sucursal_activa();
            $stock = Stock::where('articulo_id', $this->descargaArticuloId)
                ->where('sucursal_id', $sucursalId)
                ->first();

            if (!$stock) {
                throw new Exception(__('El artículo no tiene stock en esta sucursal'));
            }

            $this->stockService->ajustarStock(
                $stock->id,
                -abs($this->descargaCantidad),
                Auth::id(),
                $this->descargaConcepto
            );

            $this->dispatch('toast-success', message: __('Descarga de stock registrada exitosamente'));
            $this->showDescargaModal = false;
            $this->resetDescargaForm();

        } catch (Exception $e) {
            Log::error('Error al descargar stock', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    protected function resetDescargaForm()
    {
        $this->descargaArticuloId = null;
        $this->descargaCantidad = 0;
        $this->descargaConcepto = '';
        $this->descargaObservaciones = '';
        $this->descargaSearchArticulo = '';
    }

    // ==================== Inventario Físico ====================

    public function abrirModalInventario()
    {
        $this->resetInventarioForm();
        $this->showInventarioModal = true;
    }

    public function seleccionarArticuloInventario($articuloId)
    {
        $this->inventarioArticuloId = $articuloId;
        $articulo = Articulo::find($articuloId);
        $this->inventarioSearchArticulo = $articulo ? $articulo->nombre : '';

        $sucursalId = sucursal_activa();
        $stock = Stock::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        $this->inventarioStockActual = $stock ? (float) $stock->cantidad : 0;
        $this->inventarioCantidadFisica = $this->inventarioStockActual;
    }

    public function procesarInventario()
    {
        $this->validate([
            'inventarioArticuloId' => 'required|exists:pymes_tenant.articulos,id',
            'inventarioCantidadFisica' => 'required|numeric|min:0',
        ], [
            'inventarioArticuloId.required' => __('Seleccione un artículo'),
            'inventarioCantidadFisica.required' => __('Ingrese la cantidad física'),
            'inventarioCantidadFisica.min' => __('La cantidad no puede ser negativa'),
        ]);

        try {
            $sucursalId = sucursal_activa();
            $stock = Stock::firstOrCreate(
                [
                    'articulo_id' => $this->inventarioArticuloId,
                    'sucursal_id' => $sucursalId,
                ],
                [
                    'cantidad' => 0,
                    'ultima_actualizacion' => now(),
                ]
            );

            $this->stockService->registrarInventarioFisico(
                $stock->id,
                $this->inventarioCantidadFisica,
                Auth::id(),
                $this->inventarioObservaciones
            );

            $this->dispatch('toast-success', message: __('Inventario físico registrado exitosamente'));
            $this->showInventarioModal = false;
            $this->resetInventarioForm();

        } catch (Exception $e) {
            Log::error('Error al registrar inventario', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    protected function resetInventarioForm()
    {
        $this->inventarioArticuloId = null;
        $this->inventarioCantidadFisica = 0;
        $this->inventarioObservaciones = '';
        $this->inventarioSearchArticulo = '';
        $this->inventarioStockActual = 0;
    }

    // ==================== Filtro por artículo ====================

    public function filtrarPorArticulo($articuloId)
    {
        $this->articuloSeleccionado = $articuloId;
        $this->resetPage();
    }

    public function limpiarFiltroArticulo()
    {
        $this->articuloSeleccionado = null;
        $this->resetPage();
    }

    // ==================== Paginación ====================

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterTipo()
    {
        $this->resetPage();
    }

    public function updatingFilterFechaDesde()
    {
        $this->resetPage();
    }

    public function updatingFilterFechaHasta()
    {
        $this->resetPage();
    }
}
