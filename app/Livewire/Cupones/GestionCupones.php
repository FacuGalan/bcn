<?php

namespace App\Livewire\Cupones;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\FormaPago;
use App\Services\CuponService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
#[Layout('layouts.app')]
class GestionCupones extends Component
{
    use WithPagination;

    // ==================== FILTROS ====================
    public string $searchCupon = '';

    public string $filtroTipo = '';

    public string $filtroEstado = '';

    public bool $showFilters = false;

    // ==================== MODAL CREAR ====================
    public bool $showCrearModal = false;

    public string $tipoCupon = 'promocional';

    public string $codigo = '';

    public string $descripcion = '';

    public string $modoDescuento = 'porcentaje';

    public string $valorDescuento = '';

    public string $aplicaA = 'total';

    public int $usoMaximo = 1;

    public string $fechaVencimiento = '';

    // Cupón desde puntos
    public string $searchClienteCupon = '';

    public ?int $clienteCuponId = null;

    public int $puntosConsumidos = 0;

    // Artículos específicos
    public string $searchArticulo = '';

    public array $articulosSeleccionados = [];

    // Formas de pago válidas
    public array $formasPagoSeleccionadas = [];

    public array $formasPagoDisponibles = [];

    // ==================== MODAL HISTORIAL ====================
    public bool $showHistorialModal = false;

    public string $filtroHistorialCupon = '';

    public string $filtroHistorialDesde = '';

    public string $filtroHistorialHasta = '';

    // ==================== MODALES ====================
    public bool $showEditarModal = false;

    public ?int $cuponEditarId = null;

    public bool $editActivo = true;

    public string $editDescripcion = '';

    public string $editFechaVencimiento = '';

    public int $editUsoMaximo = 1;

    public array $editFormasPagoSeleccionadas = [];

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="3" :columns="6" :rows="8" />
        HTML;
    }

    public function mount()
    {
        $this->generarNuevoCodigo();
        $this->cargarFormasPagoDisponibles();
    }

    private function cargarFormasPagoDisponibles(): void
    {
        $this->formasPagoDisponibles = FormaPago::where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($fp) => ['id' => $fp->id, 'nombre' => $fp->nombre])
            ->toArray();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function abrirCrearModal(): void
    {
        $this->resetFormularioCrear();
        $this->showCrearModal = true;
    }

    public function cerrarCrearModal(): void
    {
        $this->showCrearModal = false;
    }

    public function abrirHistorialModal(): void
    {
        $this->showHistorialModal = true;
    }

    public function cerrarHistorialModal(): void
    {
        $this->showHistorialModal = false;
    }

    // ==================== LISTADO ====================

    public function getCuponesProperty()
    {
        $query = Cupon::with(['cliente', 'creadoPor'])
            ->withCount('usos');

        if ($this->searchCupon) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', "%{$this->searchCupon}%")
                    ->orWhere('descripcion', 'like', "%{$this->searchCupon}%");
            });
        }

        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->filtroEstado === 'activo') {
            $query->vigentes();
        } elseif ($this->filtroEstado === 'inactivo') {
            $query->where('activo', false);
        } elseif ($this->filtroEstado === 'vencido') {
            $query->where('activo', true)
                ->whereNotNull('fecha_vencimiento')
                ->where('fecha_vencimiento', '<', now()->toDateString());
        } elseif ($this->filtroEstado === 'agotado') {
            $query->where('activo', true)
                ->where('uso_maximo', '>', 0)
                ->whereColumn('uso_actual', '>=', 'uso_maximo');
        }

        return $query->orderByDesc('created_at')->paginate(15, pageName: 'cuponesPage');
    }

    public function toggleCuponActivo(int $cuponId): void
    {
        $cupon = Cupon::find($cuponId);
        if ($cupon) {
            $cupon->update(['activo' => ! $cupon->activo]);
            $this->dispatch('toast-success', message: $cupon->activo ? __('Cupón activado') : __('Cupón desactivado'));
        }
    }

    public function editarCupon(int $cuponId): void
    {
        $cupon = Cupon::find($cuponId);
        if (! $cupon) {
            return;
        }

        $this->cuponEditarId = $cuponId;
        $this->editActivo = $cupon->activo;
        $this->editDescripcion = $cupon->descripcion ?? '';
        $this->editFechaVencimiento = $cupon->fecha_vencimiento?->format('Y-m-d') ?? '';
        $this->editUsoMaximo = $cupon->uso_maximo;
        $this->editFormasPagoSeleccionadas = $cupon->formasPago()->pluck('formas_pago.id')->map(fn ($id) => (string) $id)->toArray();
        $this->showEditarModal = true;
    }

    public function guardarEdicion(): void
    {
        $cupon = Cupon::find($this->cuponEditarId);
        if (! $cupon) {
            return;
        }

        $this->validate([
            'editUsoMaximo' => 'required|integer|min:0',
        ]);

        $cupon->update([
            'activo' => $this->editActivo,
            'descripcion' => $this->editDescripcion ?: null,
            'fecha_vencimiento' => $this->editFechaVencimiento ?: null,
            'uso_maximo' => $this->editUsoMaximo,
        ]);

        // Sincronizar formas de pago
        $fpIds = array_map('intval', $this->editFormasPagoSeleccionadas);
        $cupon->formasPago()->sync($fpIds);

        $this->showEditarModal = false;
        $this->dispatch('toast-success', message: __('Cupón actualizado'));
    }

    public function cancelarEdicion(): void
    {
        $this->showEditarModal = false;
        $this->cuponEditarId = null;
    }

    // ==================== TAB CREAR ====================

    public function generarNuevoCodigo(): void
    {
        $this->codigo = app(CuponService::class)->generarCodigo();
    }

    public function getResultadosBusquedaClienteCuponProperty()
    {
        if (strlen($this->searchClienteCupon) < 2) {
            return collect();
        }

        return Cliente::where(function ($q) {
            $q->where('nombre', 'like', "%{$this->searchClienteCupon}%")
                ->orWhere('cuit', 'like', "%{$this->searchClienteCupon}%")
                ->orWhere('telefono', 'like', "%{$this->searchClienteCupon}%");
        })
            ->where('activo', true)
            ->limit(10)
            ->get(['id', 'nombre', 'cuit', 'puntos_saldo_cache']);
    }

    public function seleccionarClienteCupon(int $clienteId): void
    {
        $this->clienteCuponId = $clienteId;
        $this->searchClienteCupon = '';
    }

    public function limpiarClienteCupon(): void
    {
        $this->clienteCuponId = null;
        $this->searchClienteCupon = '';
        $this->puntosConsumidos = 0;
    }

    public function getClienteCuponProperty()
    {
        if (! $this->clienteCuponId) {
            return null;
        }

        return Cliente::find($this->clienteCuponId);
    }

    public function getResultadosBusquedaArticuloProperty()
    {
        if (strlen($this->searchArticulo) < 2) {
            return collect();
        }

        $busqueda = $this->searchArticulo;

        return Articulo::where(function ($q) use ($busqueda) {
            $q->where('nombre', 'like', "%{$busqueda}%")
                ->orWhere('codigo', 'like', "%{$busqueda}%")
                ->orWhere('codigo_barras', 'like', "%{$busqueda}%");
        })
            ->where('activo', true)
            ->limit(10)
            ->get(['id', 'nombre', 'codigo', 'precio_base']);
    }

    /**
     * Agrega el primer artículo de los resultados (para Enter en buscador)
     */
    public function agregarPrimerArticuloCupon(): void
    {
        $resultados = $this->resultadosBusquedaArticulo;
        if ($resultados->isNotEmpty()) {
            $this->agregarArticulo($resultados->first()->id);
        }
    }

    public function agregarArticulo(int $articuloId): void
    {
        if (isset($this->articulosSeleccionados[$articuloId])) {
            return;
        }

        $articulo = Articulo::find($articuloId, ['id', 'nombre', 'codigo', 'precio_base']);
        if ($articulo) {
            $this->articulosSeleccionados[$articuloId] = [
                'id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
            ];
        }
        $this->searchArticulo = '';
    }

    public function quitarArticulo(int $articuloId): void
    {
        unset($this->articulosSeleccionados[$articuloId]);
    }

    public function crearCupon(): void
    {
        $rules = [
            'codigo' => 'required|string|max:50',
            'modoDescuento' => 'required|in:porcentaje,monto_fijo',
            'valorDescuento' => 'required|numeric|min:0.01',
            'aplicaA' => 'required|in:total,articulos',
            'usoMaximo' => 'required|integer|min:0',
        ];

        if ($this->modoDescuento === 'porcentaje') {
            $rules['valorDescuento'] = 'required|numeric|min:0.01|max:100';
        }

        if ($this->tipoCupon === 'puntos') {
            $rules['clienteCuponId'] = 'required|integer';
            $rules['puntosConsumidos'] = 'required|integer|min:1';
        }

        $this->validate($rules);

        try {
            $cuponService = app(CuponService::class);

            // Armar articulo_cantidades desde articulosSeleccionados
            $articuloCantidades = [];
            foreach ($this->articulosSeleccionados as $artId => $art) {
                $cantidad = isset($art['cantidad']) && $art['cantidad'] !== '' ? (int) $art['cantidad'] : null;
                $articuloCantidades[$artId] = $cantidad;
            }

            $data = [
                'codigo' => $this->codigo,
                'descripcion' => $this->descripcion ?: null,
                'modo_descuento' => $this->modoDescuento,
                'valor_descuento' => $this->valorDescuento,
                'aplica_a' => $this->aplicaA,
                'uso_maximo' => $this->usoMaximo,
                'fecha_vencimiento' => $this->fechaVencimiento ?: null,
                'articulo_cantidades' => $articuloCantidades,
                'forma_pago_ids' => array_map('intval', $this->formasPagoSeleccionadas),
                'puntos_consumidos' => $this->puntosConsumidos,
                'sucursal_id' => sucursal_activa(),
            ];

            if ($this->tipoCupon === 'puntos') {
                $cuponService->crearCuponDesdePuntos($this->clienteCuponId, $data, Auth::id());
            } else {
                $cuponService->crearCuponPromocional($data, Auth::id());
            }

            $this->dispatch('toast-success', message: __('Cupón creado correctamente'));
            $this->resetFormularioCrear();
            $this->showCrearModal = false;
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function resetFormularioCrear(): void
    {
        $this->tipoCupon = 'promocional';
        $this->descripcion = '';
        $this->modoDescuento = 'porcentaje';
        $this->valorDescuento = '';
        $this->aplicaA = 'total';
        $this->usoMaximo = 1;
        $this->fechaVencimiento = '';
        $this->clienteCuponId = null;
        $this->searchClienteCupon = '';
        $this->puntosConsumidos = 0;
        $this->articulosSeleccionados = [];
        $this->searchArticulo = '';
        $this->formasPagoSeleccionadas = [];
        $this->generarNuevoCodigo();
    }

    // ==================== HISTORIAL ====================

    public function getHistorialUsosProperty()
    {
        $query = CuponUso::with(['cupon', 'venta', 'cliente', 'sucursal']);

        if ($this->filtroHistorialCupon) {
            $query->whereHas('cupon', function ($q) {
                $q->where('codigo', 'like', "%{$this->filtroHistorialCupon}%");
            });
        }

        if ($this->filtroHistorialDesde) {
            $query->whereDate('fecha', '>=', $this->filtroHistorialDesde);
        }

        if ($this->filtroHistorialHasta) {
            $query->whereDate('fecha', '<=', $this->filtroHistorialHasta);
        }

        return $query->orderByDesc('fecha')->paginate(15, pageName: 'historialPage');
    }

    // ==================== LIFECYCLE ====================

    public function updatingSearchCupon(): void
    {
        $this->resetPage('cuponesPage');
    }

    public function updatingFiltroTipo(): void
    {
        $this->resetPage('cuponesPage');
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage('cuponesPage');
    }

    public function updatingSearchClienteCupon(): void
    {
        $this->clienteCuponId = null;
    }

    public function render()
    {
        return view('livewire.cupones.gestion-cupones', [
            'cupones' => $this->cupones,
            'historialUsos' => $this->historialUsos,
        ]);
    }
}
