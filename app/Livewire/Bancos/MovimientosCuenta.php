<?php

namespace App\Livewire\Bancos;

use App\Models\CuentaEmpresa;
use App\Models\ConceptoMovimientoCuenta;
use App\Models\MovimientoCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Traits\SucursalAware;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts.app')]
class MovimientosCuenta extends Component
{
    use WithPagination, SucursalAware;

    // Filtros
    public ?int $cuentaSeleccionada = null;
    public string $filtroTipo = '';
    public ?int $filtroConcepto = null;
    public string $filtroEstado = '';
    public ?string $fechaDesde = null;
    public ?string $fechaHasta = null;

    // Modal nuevo movimiento
    public bool $showNuevoMovimiento = false;
    public string $nuevoTipo = 'ingreso';
    public ?float $nuevoMonto = null;
    public ?int $nuevoConceptoId = null;
    public string $nuevoDescripcion = '';
    public ?string $nuevoObservaciones = null;

    // Modal anular
    public bool $showAnularModal = false;
    public ?int $anularMovimientoId = null;
    public string $motivoAnulacion = '';

    public function mount()
    {
        // Si viene cuenta en query string
        $cuentaId = request()->query('cuenta');
        if ($cuentaId) {
            $this->cuentaSeleccionada = (int) $cuentaId;
        }
    }

    public function updatedCuentaSeleccionada()
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo()
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado()
    {
        $this->resetPage();
    }

    public function abrirNuevoMovimiento()
    {
        if (!$this->cuentaSeleccionada) {
            $this->dispatch('toast-error', message: __('Seleccione una cuenta primero'));
            return;
        }
        $this->reset(['nuevoTipo', 'nuevoMonto', 'nuevoConceptoId', 'nuevoDescripcion', 'nuevoObservaciones']);
        $this->nuevoTipo = 'ingreso';
        $this->showNuevoMovimiento = true;
    }

    public function guardarMovimiento()
    {
        $this->validate([
            'nuevoTipo' => 'required|in:ingreso,egreso',
            'nuevoMonto' => 'required|numeric|min:0.01',
            'nuevoDescripcion' => 'required|string|max:255',
            'nuevoConceptoId' => 'nullable|exists:pymes_tenant.conceptos_movimiento_cuenta,id',
        ]);

        try {
            CuentaEmpresaService::registrarMovimientoManual(
                $this->cuentaSeleccionada,
                $this->nuevoTipo,
                $this->nuevoMonto,
                $this->nuevoConceptoId,
                $this->nuevoDescripcion,
                Auth::id(),
                sucursal_activa(),
                $this->nuevoObservaciones
            );

            $this->showNuevoMovimiento = false;
            $this->dispatch('toast-success', message: __('Movimiento registrado correctamente'));
        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function confirmarAnular(int $id)
    {
        $this->anularMovimientoId = $id;
        $this->motivoAnulacion = '';
        $this->showAnularModal = true;
    }

    public function anularMovimiento()
    {
        $this->validate([
            'motivoAnulacion' => 'required|string|max:255',
        ]);

        try {
            CuentaEmpresaService::revertirMovimiento(
                $this->anularMovimientoId,
                $this->motivoAnulacion,
                Auth::id()
            );

            $this->showAnularModal = false;
            $this->dispatch('toast-success', message: __('Movimiento anulado correctamente'));
        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function getCuentasProperty()
    {
        $sucursalId = sucursal_activa();
        return CuentaEmpresaService::getCuentasDisponibles($sucursalId ?? 0);
    }

    public function getConceptosProperty()
    {
        return ConceptoMovimientoCuenta::activos()->orderBy('orden')->get();
    }

    public function getConceptosManualesProperty()
    {
        $query = ConceptoMovimientoCuenta::activos();
        if ($this->nuevoTipo === 'ingreso') {
            $query->deIngreso();
        } else {
            $query->deEgreso();
        }
        return $query->orderBy('orden')->get();
    }

    public function render()
    {
        $movimientos = collect();
        $cuentaActual = null;

        if ($this->cuentaSeleccionada) {
            $cuentaActual = CuentaEmpresa::with('moneda')->find($this->cuentaSeleccionada);

            $query = MovimientoCuentaEmpresa::porCuenta($this->cuentaSeleccionada)
                ->with(['conceptoMovimiento', 'usuario', 'sucursal', 'movimientoAnulacion', 'movimientoAnulado']);

            if ($this->filtroTipo) {
                $query->where('tipo', $this->filtroTipo);
            }

            if ($this->filtroConcepto) {
                $query->porConcepto($this->filtroConcepto);
            }

            if ($this->filtroEstado) {
                $query->where('estado', $this->filtroEstado);
            }

            if ($this->fechaDesde) {
                $query->where('created_at', '>=', $this->fechaDesde . ' 00:00:00');
            }

            if ($this->fechaHasta) {
                $query->where('created_at', '<=', $this->fechaHasta . ' 23:59:59');
            }

            $movimientos = $query->orderByDesc('created_at')->paginate(20);
        }

        return view('livewire.bancos.movimientos-cuenta', [
            'movimientos' => $movimientos,
            'cuentaActual' => $cuentaActual,
        ]);
    }
}
