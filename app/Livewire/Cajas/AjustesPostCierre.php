<?php

namespace App\Livewire\Cajas;

use App\Models\CierreTurno;
use App\Models\User;
use App\Models\VentaPagoAjuste;
use App\Traits\SucursalAware;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente: Reporte de Ajustes Post-Cierre
 *
 * Lista los cambios de pago aplicados sobre ventas cuyos pagos originales
 * pertenecían a turnos ya cerrados. Los contraasientos van al turno actual,
 * el cierre histórico no se modifica, y cada operación queda en este reporte.
 */
#[Lazy]
class AjustesPostCierre extends Component
{
    use SucursalAware;
    use WithPagination;

    // Filtros
    public string $filtroFechaDesde = '';

    public string $filtroFechaHasta = '';

    public ?int $filtroUsuarioId = null;

    public ?int $filtroTurnoId = null;

    public string $filtroTipoOperacion = '';

    public int $perPage = 15;

    protected $queryString = [
        'filtroFechaDesde' => ['except' => ''],
        'filtroFechaHasta' => ['except' => ''],
        'filtroUsuarioId' => ['except' => null],
        'filtroTurnoId' => ['except' => null],
        'filtroTipoOperacion' => ['except' => ''],
    ];

    public function placeholder(): string
    {
        return view('components.skeleton.page-table')->render();
    }

    public function updated($name): void
    {
        if (in_array($name, ['filtroFechaDesde', 'filtroFechaHasta', 'filtroUsuarioId', 'filtroTurnoId', 'filtroTipoOperacion'])) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->filtroFechaDesde = '';
        $this->filtroFechaHasta = '';
        $this->filtroUsuarioId = null;
        $this->filtroTurnoId = null;
        $this->filtroTipoOperacion = '';
        $this->resetPage();
    }

    public function render()
    {
        $sucursalId = $this->sucursalActual();

        $query = VentaPagoAjuste::with([
            'venta', 'formaPagoAnterior', 'formaPagoNueva', 'ncEmitida', 'usuario',
            'turnoOriginal.detalleCajas', 'turnoOriginal.grupoCierre',
        ])
            ->postCierre()
            ->porSucursal($sucursalId);

        if ($this->filtroFechaDesde) {
            $query->where('created_at', '>=', $this->filtroFechaDesde.' 00:00:00');
        }
        if ($this->filtroFechaHasta) {
            $query->where('created_at', '<=', $this->filtroFechaHasta.' 23:59:59');
        }
        if ($this->filtroUsuarioId) {
            $query->where('usuario_id', $this->filtroUsuarioId);
        }
        if ($this->filtroTurnoId) {
            $query->where('turno_original_id', $this->filtroTurnoId);
        }
        if ($this->filtroTipoOperacion) {
            $query->where('tipo_operacion', $this->filtroTipoOperacion);
        }

        $ajustes = $query->orderByDesc('created_at')->paginate($this->perPage);

        // Datos para filtros
        $usuariosDisponibles = User::whereIn('id', VentaPagoAjuste::postCierre()->porSucursal($sucursalId)->pluck('usuario_id'))
            ->orderBy('name')->get();

        $turnosDisponibles = CierreTurno::whereIn('id', VentaPagoAjuste::postCierre()->porSucursal($sucursalId)->whereNotNull('turno_original_id')->pluck('turno_original_id'))
            ->with(['detalleCajas', 'grupoCierre'])
            ->orderByDesc('fecha_cierre')->get();

        return view('livewire.cajas.ajustes-post-cierre', [
            'ajustes' => $ajustes,
            'usuariosDisponibles' => $usuariosDisponibles,
            'turnosDisponibles' => $turnosDisponibles,
        ]);
    }
}
