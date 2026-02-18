<?php

namespace App\Livewire\Stock;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\StockService;
use App\Models\Stock;
use App\Models\Articulo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class StockInventario extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filterAlerta = 'all';
    public string $filterModoStock = 'all';
    public string $filterTipo = 'all';
    public bool $showFilters = false;

    // Modales
    public bool $showAjusteModal = false;
    public bool $showInventarioModal = false;
    public bool $showUmbralesModal = false;

    // Ajuste
    public $stockAjusteId = null;
    public $cantidadAjuste = 0;
    public string $motivoAjuste = '';

    // Inventario físico
    public $stockInventarioId = null;
    public $cantidadFisica = 0;
    public string $observacionesInventario = '';

    // Umbrales
    public $stockUmbralesId = null;
    public $cantidadMinima = null;
    public $cantidadMaxima = null;

    protected $stockService;

    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function boot(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        $this->showAjusteModal = false;
        $this->showInventarioModal = false;
        $this->showUmbralesModal = false;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAlerta(): void
    {
        $this->resetPage();
    }

    public function updatingFilterModoStock(): void
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

    public function render()
    {
        $sucursalId = sucursal_activa();
        $stocks = $this->obtenerStocks($sucursalId);
        $alertasBajoMinimo = Stock::porSucursal($sucursalId)->bajoMinimo()->count();
        $articulosSinStock = Stock::porSucursal($sucursalId)->where('cantidad', '<=', 0)->count();
        $totalArticulos = Stock::porSucursal($sucursalId)->count();

        return view('livewire.stock.stock-inventario', [
            'stocks' => $stocks,
            'alertasBajoMinimo' => $alertasBajoMinimo,
            'articulosSinStock' => $articulosSinStock,
            'totalArticulos' => $totalArticulos,
            'stockAjuste' => $this->stockAjusteId ? Stock::with('articulo')->find($this->stockAjusteId) : null,
            'stockInventario' => $this->stockInventarioId ? Stock::with('articulo')->find($this->stockInventarioId) : null,
            'stockUmbrales' => $this->stockUmbralesId ? Stock::with('articulo')->find($this->stockUmbralesId) : null,
        ]);
    }

    protected function obtenerStocks($sucursalId)
    {
        $query = Stock::with(['articulo'])
                     ->porSucursal($sucursalId);

        if ($this->search) {
            $query->whereHas('articulo', function ($q) {
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('codigo', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterAlerta === 'bajo_minimo') {
            $query->bajoMinimo();
        } elseif ($this->filterAlerta === 'sin_stock') {
            $query->where('cantidad', '<=', 0);
        }

        if ($this->filterModoStock !== 'all') {
            $modo = $this->filterModoStock;
            $query->whereExists(function ($sub) use ($sucursalId, $modo) {
                $sub->select(DB::raw(1))
                    ->from('articulos_sucursales')
                    ->whereColumn('articulos_sucursales.articulo_id', 'stock.articulo_id')
                    ->where('articulos_sucursales.sucursal_id', $sucursalId)
                    ->where('articulos_sucursales.modo_stock', $modo);
            });
        }

        if ($this->filterTipo !== 'all') {
            $esMp = $this->filterTipo === 'materia_prima';
            $query->whereHas('articulo', function ($q) use ($esMp) {
                $q->where('es_materia_prima', $esMp);
            });
        }

        return $query->orderBy('cantidad', 'asc')->paginate(20);
    }

    public function abrirModalAjuste($stockId)
    {
        $this->stockAjusteId = $stockId;
        $this->cantidadAjuste = 0;
        $this->motivoAjuste = '';
        $this->showAjusteModal = true;
    }

    public function procesarAjuste()
    {
        try {
            if ($this->cantidadAjuste == 0) {
                $this->dispatch('toast-error', message: __('La cantidad de ajuste debe ser diferente de cero'));
                return;
            }

            $this->stockService->ajustarStock(
                $this->stockAjusteId,
                $this->cantidadAjuste,
                Auth::id(),
                $this->motivoAjuste
            );

            $this->dispatch('toast-success', message: __('Ajuste de stock realizado exitosamente'));
            $this->showAjusteModal = false;
            $this->resetAjusteForm();

        } catch (Exception $e) {
            Log::error('Error al ajustar stock', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    protected function resetAjusteForm()
    {
        $this->stockAjusteId = null;
        $this->cantidadAjuste = 0;
        $this->motivoAjuste = '';
    }

    public function abrirModalInventario($stockId)
    {
        $stock = Stock::findOrFail($stockId);
        $this->stockInventarioId = $stockId;
        $this->cantidadFisica = $stock->cantidad;
        $this->observacionesInventario = '';
        $this->showInventarioModal = true;
    }

    public function procesarInventario()
    {
        try {
            $this->stockService->registrarInventarioFisico(
                $this->stockInventarioId,
                $this->cantidadFisica,
                Auth::id(),
                $this->observacionesInventario
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
        $this->stockInventarioId = null;
        $this->cantidadFisica = 0;
        $this->observacionesInventario = '';
    }

    public function abrirModalUmbrales($stockId)
    {
        $stock = Stock::findOrFail($stockId);
        $this->stockUmbralesId = $stockId;
        $this->cantidadMinima = $stock->cantidad_minima;
        $this->cantidadMaxima = $stock->cantidad_maxima;
        $this->showUmbralesModal = true;
    }

    public function actualizarUmbrales()
    {
        try {
            $this->stockService->actualizarUmbrales(
                $this->stockUmbralesId,
                $this->cantidadMinima,
                $this->cantidadMaxima
            );

            $this->dispatch('toast-success', message: __('Umbrales actualizados exitosamente'));
            $this->showUmbralesModal = false;
            $this->resetUmbralesForm();

        } catch (Exception $e) {
            Log::error('Error al actualizar umbrales', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error: :message', ['message' => $e->getMessage()]));
        }
    }

    protected function resetUmbralesForm()
    {
        $this->stockUmbralesId = null;
        $this->cantidadMinima = null;
        $this->cantidadMaxima = null;
    }

    public function cerrarModal($modal)
    {
        $this->{$modal} = false;
    }
}
