<?php

namespace App\Livewire\Stock;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\StockService;
use App\Models\Stock;
use App\Models\Articulo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Componente Livewire: Stock / Inventario
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Listar stock por sucursal con filtros
 * 2. Mostrar alertas de stock bajo mínimo
 * 3. Realizar ajustes manuales de stock
 * 4. Registrar inventario físico
 * 5. Ver stock consolidado de todas las sucursales
 * 6. Actualizar umbrales (stock mínimo y máximo)
 *
 * PROPIEDADES:
 * ===========
 * @property Collection $stocks - Lista de stock filtrada
 * @property string $search - Búsqueda por artículo
 * @property string $filterAlerta - Filtro por alertas (all, bajo_minimo, sin_stock)
 * @property bool $showAjusteModal - Modal de ajuste de stock
 * @property bool $showInventarioModal - Modal de inventario físico
 *
 * DEPENDENCIAS:
 * ============
 * - StockService: Para ajustes y reportes
 * - Models: Stock, Articulo, Sucursal
 *
 * FASE 4 - Sistema Multi-Sucursal (Componentes Livewire)
 *
 * @package App\Livewire\Stock
 * @version 1.0.0
 */
class StockInventario extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public $search = '';
    public $filterAlerta = 'all';
    public $sucursalSeleccionada = null;

    // Propiedades de modales
    public $showAjusteModal = false;
    public $showInventarioModal = false;
    public $showUmbralesModal = false;

    // Propiedades de ajuste
    public $stockAjusteId = null;
    public $cantidadAjuste = 0;
    public $motivoAjuste = '';

    // Propiedades de inventario físico
    public $stockInventarioId = null;
    public $cantidadFisica = 0;
    public $observacionesInventario = '';

    // Propiedades de umbrales
    public $stockUmbralesId = null;
    public $cantidadMinima = null;
    public $cantidadMaxima = null;

    protected $stockService;

    /**
     * Escuchar evento de cambio de sucursal
     */
    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function boot(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount()
    {
        $this->sucursalSeleccionada = sucursal_activa() ?? Sucursal::activas()->first()->id ?? 1;
    }

    /**
     * Maneja el cambio de sucursal
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Actualizar sucursal seleccionada
        $this->sucursalSeleccionada = $sucursalId ?? sucursal_activa();

        // Cerrar modales si están abiertos
        $this->showAjusteModal = false;
        $this->showInventarioModal = false;
        $this->showUmbralesModal = false;

        // El componente se re-renderizará automáticamente con los datos de la nueva sucursal
    }

    public function render()
    {
        $stocks = $this->obtenerStocks();
        $sucursales = Sucursal::activas()->get();
        $alertasBajoMinimo = Stock::porSucursal($this->sucursalSeleccionada)->bajoMinimo()->count();
        $articulosSinStock = Stock::porSucursal($this->sucursalSeleccionada)->where('cantidad', '<=', 0)->count();

        return view('livewire.stock.stock-inventario', [
            'stocks' => $stocks,
            'sucursales' => $sucursales,
            'alertasBajoMinimo' => $alertasBajoMinimo,
            'articulosSinStock' => $articulosSinStock,
            'stockAjuste' => $this->stockAjusteId ? Stock::with('articulo')->find($this->stockAjusteId) : null,
            'stockInventario' => $this->stockInventarioId ? Stock::with('articulo')->find($this->stockInventarioId) : null,
            'stockUmbrales' => $this->stockUmbralesId ? Stock::with('articulo')->find($this->stockUmbralesId) : null,
        ]);
    }

    protected function obtenerStocks()
    {
        $query = Stock::with(['articulo', 'sucursal'])
                     ->porSucursal($this->sucursalSeleccionada);

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

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterAlerta()
    {
        $this->resetPage();
    }

    public function updatedSucursalSeleccionada()
    {
        $this->resetPage();
    }
}
