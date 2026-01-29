<?php

namespace App\Livewire\Cajas;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CierreTurno;
use App\Models\CierreTurnoCaja;
use App\Models\Caja;
use App\Models\GrupoCierre;
use App\Services\SucursalService;
use Carbon\Carbon;

/**
 * Componente de Historial de Turnos
 *
 * Muestra el listado de todos los cierres de turno con:
 * - Filtros por fecha, caja, usuario
 * - Detalle completo de cada cierre
 * - Opción de reimpresión
 */
class HistorialTurnos extends Component
{
    use WithPagination;

    // Filtros
    public string $filtroFechaDesde = '';
    public string $filtroFechaHasta = '';
    public ?int $filtroCajaId = null;
    public ?int $filtroUsuarioId = null;
    public string $filtroTipo = ''; // individual, grupo, ''

    // Datos para filtros
    public $cajasDisponibles = [];
    public $usuariosDisponibles = [];

    // Modal de detalle
    public bool $showDetalleModal = false;
    public ?int $cierreSeleccionadoId = null;
    public $cierreDetalle = null;

    protected $queryString = [
        'filtroFechaDesde' => ['except' => ''],
        'filtroFechaHasta' => ['except' => ''],
        'filtroCajaId' => ['except' => null],
        'filtroTipo' => ['except' => ''],
    ];

    public function mount()
    {
        // Por defecto, mostrar último mes
        $this->filtroFechaDesde = now()->subMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');

        $this->cargarDatosFiltros();
    }

    protected function cargarDatosFiltros()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        // Cajas de la sucursal
        $this->cajasDisponibles = Caja::where('sucursal_id', $sucursalId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'numero']);

        // Usuarios que han cerrado turnos
        $this->usuariosDisponibles = CierreTurno::where('sucursal_id', $sucursalId)
            ->with('usuario:id,name')
            ->select('usuario_id')
            ->distinct()
            ->get()
            ->pluck('usuario')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function getCierresProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        $query = CierreTurno::where('sucursal_id', $sucursalId)
            ->with(['usuario:id,name', 'grupoCierre:id,nombre', 'detalleCajas'])
            ->orderBy('fecha_cierre', 'desc');

        // Filtro por fecha
        if ($this->filtroFechaDesde) {
            $query->where('fecha_cierre', '>=', Carbon::parse($this->filtroFechaDesde)->startOfDay());
        }
        if ($this->filtroFechaHasta) {
            $query->where('fecha_cierre', '<=', Carbon::parse($this->filtroFechaHasta)->endOfDay());
        }

        // Filtro por tipo
        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        // Filtro por usuario
        if ($this->filtroUsuarioId) {
            $query->where('usuario_id', $this->filtroUsuarioId);
        }

        // Filtro por caja (busca en detalle de cajas)
        if ($this->filtroCajaId) {
            $query->whereHas('detalleCajas', function ($q) {
                $q->where('caja_id', $this->filtroCajaId);
            });
        }

        return $query->paginate(15);
    }

    public function limpiarFiltros()
    {
        $this->filtroFechaDesde = now()->subMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');
        $this->filtroCajaId = null;
        $this->filtroUsuarioId = null;
        $this->filtroTipo = '';
        $this->resetPage();
    }

    public function verDetalle(int $cierreId)
    {
        $this->cierreSeleccionadoId = $cierreId;

        $this->cierreDetalle = CierreTurno::with([
            'usuario:id,name',
            'grupoCierre:id,nombre,fondo_comun',
            'detalleCajas.caja:id,nombre,numero',
            'movimientos' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'movimientos.usuario:id,name',
            'ventaPagos' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'ventaPagos.formaPago:id,nombre',
            'ventaPagos.conceptoPago:id,nombre',
            'ventaPagos.venta:id,numero',
            'cobroPagos' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'cobroPagos.formaPago:id,nombre',
            'cobroPagos.conceptoPago:id,nombre',
            'cobroPagos.cobro:id,numero',
        ])->find($cierreId);

        $this->showDetalleModal = true;
    }

    public function cerrarDetalle()
    {
        $this->showDetalleModal = false;
        $this->cierreSeleccionadoId = null;
        $this->cierreDetalle = null;
    }

    public function reimprimir(int $cierreId)
    {
        // TODO: Implementar lógica de reimpresión
        $this->dispatch('toast-info', message: 'Función de reimpresión pendiente de implementar');
    }

    /**
     * Obtiene el resumen de totales para el período filtrado
     */
    public function getResumenPeriodoProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        $query = CierreTurno::where('sucursal_id', $sucursalId);

        if ($this->filtroFechaDesde) {
            $query->where('fecha_cierre', '>=', Carbon::parse($this->filtroFechaDesde)->startOfDay());
        }
        if ($this->filtroFechaHasta) {
            $query->where('fecha_cierre', '<=', Carbon::parse($this->filtroFechaHasta)->endOfDay());
        }

        return [
            'total_cierres' => $query->count(),
            'total_ingresos' => $query->sum('total_ingresos'),
            'total_egresos' => $query->sum('total_egresos'),
            'total_diferencia' => $query->sum('total_diferencia'),
            'cierres_con_diferencia' => (clone $query)->where('total_diferencia', '!=', 0)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.cajas.historial-turnos', [
            'cierres' => $this->cierres,
            'resumen' => $this->resumenPeriodo,
        ]);
    }
}
