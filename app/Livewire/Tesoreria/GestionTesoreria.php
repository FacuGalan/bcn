<?php

namespace App\Livewire\Tesoreria;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Tesoreria;
use App\Models\MovimientoTesoreria;
use App\Models\ProvisionFondo;
use App\Models\RendicionFondo;
use App\Models\DepositoBancario;
use App\Models\ArqueoTesoreria;
use App\Models\CuentaBancaria;
use App\Models\Caja;
use App\Models\CierreTurno;
use App\Services\TesoreriaService;
use App\Services\SucursalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Componente Livewire: Gestión de Tesorería
 *
 * Funcionalidades:
 * - Ver saldo actual de tesorería
 * - Listar movimientos con filtros
 * - Provisionar fondo a caja
 * - Recibir rendición de caja
 * - Registrar depósito bancario
 * - Realizar arqueo
 */
class GestionTesoreria extends Component
{
    use WithPagination;

    // Tesorería activa
    public ?Tesoreria $tesoreria = null;

    // Filtros de movimientos
    public string $filtroTipo = '';
    public string $filtroFechaDesde = '';
    public string $filtroFechaHasta = '';
    public string $filtroConcepto = '';

    // Modal de provisión
    public bool $showProvisionModal = false;
    public ?int $cajaProvisionId = null;
    public float $montoProvision = 0;
    public string $observacionesProvision = '';

    // Modal de rendición
    public bool $showRendicionModal = false;
    public array $rendicionesPendientes = [];
    public ?int $rendicionSeleccionada = null;

    // Modal de depósito
    public bool $showDepositoModal = false;
    public ?int $cuentaBancariaId = null;
    public float $montoDeposito = 0;
    public string $fechaDeposito = '';
    public string $numeroComprobante = '';
    public string $observacionesDeposito = '';

    // Modal de arqueo
    public bool $showArqueoModal = false;
    public float $saldoContado = 0;
    public string $observacionesArqueo = '';

    // Datos cargados
    public array $estadisticasHoy = [];

    // Vista activa (tabs)
    public string $vistaActiva = 'movimientos'; // movimientos, cajas, arqueos, depositos

    // Modal de detalle de arqueo
    public bool $showArqueoDetalleModal = false;
    public ?ArqueoTesoreria $arqueoDetalle = null;

    // Modal de rechazo de rendición
    public bool $showRechazoModal = false;
    public ?int $rendicionARechazar = null;
    public string $motivoRechazo = '';

    protected $listeners = ['tesoreria-actualizada' => 'cargarDatos'];

    public function mount(): void
    {
        $this->filtroFechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');
        $this->fechaDeposito = now()->format('Y-m-d');

        $this->cargarDatos();
    }

    public function cargarDatos(): void
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return;
        }

        // Obtener o crear tesorería
        $this->tesoreria = TesoreriaService::obtenerOCrear($sucursalId);

        // Cargar estadísticas del día
        $this->estadisticasHoy = TesoreriaService::estadisticasHoy($this->tesoreria);

        // Cargar rendiciones pendientes con flag de revertibilidad
        $rendiciones = RendicionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->pendientes()
            ->with(['caja', 'usuarioEntrega', 'cierreTurno.detalleCajas', 'cierreTurno.grupoCierre'])
            ->orderBy('fecha', 'desc')
            ->get();

        $this->rendicionesPendientes = $rendiciones->map(function ($rendicion) {
            $data = $rendicion->toArray();
            $data['puede_revertir'] = $this->evaluarRevertibilidad($rendicion);
            return $data;
        })->toArray();
    }

    /**
     * Obtiene los movimientos paginados con filtros
     */
    public function getMovimientosProperty()
    {
        if (!$this->tesoreria) {
            return collect();
        }

        $query = MovimientoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->with('usuario');

        // Filtro por tipo
        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        // Filtro por fecha
        if ($this->filtroFechaDesde) {
            $query->whereDate('created_at', '>=', $this->filtroFechaDesde);
        }
        if ($this->filtroFechaHasta) {
            $query->whereDate('created_at', '<=', $this->filtroFechaHasta);
        }

        // Filtro por concepto
        if ($this->filtroConcepto) {
            $query->where('concepto', 'like', "%{$this->filtroConcepto}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Obtiene las cajas disponibles para provisión
     */
    public function getCajasDisponiblesProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene las cuentas bancarias disponibles
     */
    public function getCuentasBancariasProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        return CuentaBancaria::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('banco')
            ->get();
    }

    /**
     * Obtiene el estado de todas las cajas de la sucursal
     */
    public function getEstadoCajasProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function ($caja) {
                // Obtener último movimiento con usuario
                $ultimoMov = $caja->movimientos()->with('usuario:id,name')->latest()->first();

                return [
                    'id' => $caja->id,
                    'nombre' => $caja->nombre,
                    'numero' => $caja->numero_formateado ?? $caja->numero,
                    'saldo_actual' => $caja->saldo_actual,
                    'estado' => $caja->estado,
                    'esta_abierta' => $caja->estaAbierta(),
                    'ultimo_usuario' => $ultimoMov?->usuario?->name,
                    'ultimo_movimiento' => $ultimoMov?->created_at,
                ];
            });
    }

    /**
     * Obtiene los depósitos pendientes de confirmar
     */
    public function getDepositosPendientesProperty()
    {
        if (!$this->tesoreria) {
            return collect();
        }

        return DepositoBancario::where('tesoreria_id', $this->tesoreria->id)
            ->where('estado', DepositoBancario::ESTADO_PENDIENTE)
            ->with(['cuentaBancaria', 'usuario'])
            ->orderBy('fecha_deposito', 'desc')
            ->get();
    }

    /**
     * Obtiene el historial de arqueos
     */
    public function getArqueosProperty()
    {
        if (!$this->tesoreria) {
            return collect();
        }

        return ArqueoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->with(['usuario:id,name', 'supervisor:id,name'])
            ->orderBy('fecha', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Obtiene el historial de provisiones recientes
     */
    public function getProvisionesRecientesProperty()
    {
        if (!$this->tesoreria) {
            return collect();
        }

        return ProvisionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->with(['caja:id,nombre', 'usuarioEntrega:id,name'])
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Obtiene el historial de rendiciones recientes
     */
    public function getRendicionesRecientesProperty()
    {
        if (!$this->tesoreria) {
            return collect();
        }

        return RendicionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->with(['caja:id,nombre', 'usuarioEntrega:id,name'])
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();
    }

    // ==================== PROVISIÓN DE FONDO ====================

    public function abrirModalProvision(): void
    {
        $this->reset(['cajaProvisionId', 'montoProvision', 'observacionesProvision']);
        $this->showProvisionModal = true;
    }

    public function procesarProvision(): void
    {
        $this->validate([
            'cajaProvisionId' => 'required|exists:pymes_tenant.cajas,id',
            'montoProvision' => 'required|numeric|min:0.01',
        ], [
            'cajaProvisionId.required' => __('Seleccione una caja'),
            'montoProvision.required' => __('Ingrese el monto'),
            'montoProvision.min' => __('El monto debe ser mayor a 0'),
        ]);

        try {
            $caja = Caja::find($this->cajaProvisionId);

            $provision = TesoreriaService::provisionarFondo(
                $this->tesoreria,
                $caja,
                $this->montoProvision,
                auth()->id(),
                $this->observacionesProvision ?: null
            );

            $this->showProvisionModal = false;
            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Provision de :monto realizada a :caja', ['monto' => '$' . $this->montoProvision, 'caja' => $caja->nombre]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== CONFIRMACIÓN DE RENDICIÓN ====================

    public function abrirModalRendicion(): void
    {
        $this->cargarDatos();
        $this->rendicionSeleccionada = null;
        $this->showRendicionModal = true;
    }

    public function confirmarRendicion(int $rendicionId): void
    {
        try {
            $rendicion = RendicionFondo::find($rendicionId);

            if (!$rendicion) {
                throw new \Exception(__('Rendicion no encontrada'));
            }

            TesoreriaService::confirmarRendicion($rendicion, auth()->id());

            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Rendicion de caja :caja confirmada', ['caja' => $rendicion->caja->nombre]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== RECHAZO Y REVERSIÓN ====================

    public function abrirModalRechazo(int $rendicionId): void
    {
        $this->rendicionARechazar = $rendicionId;
        $this->motivoRechazo = '';
        $this->showRechazoModal = true;
    }

    public function rechazarYRevertirCierre(): void
    {
        try {
            $rendicion = RendicionFondo::find($this->rendicionARechazar);

            if (!$rendicion) {
                throw new \Exception(__('Rendicion no encontrada'));
            }

            TesoreriaService::rechazarYRevertirCierre(
                $rendicion,
                auth()->id(),
                $this->motivoRechazo ?: null
            );

            $this->showRechazoModal = false;
            $this->rendicionARechazar = null;
            $this->motivoRechazo = '';
            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Cierre de turno rechazado y revertido correctamente'));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Evalúa si una rendición pendiente puede ser revertida.
     * Condiciones: cierre no revertido, es el último cierre de esa caja/grupo,
     * y (para grupo sin fondo común) todas las rendiciones del cierre están pendientes.
     */
    protected function evaluarRevertibilidad(RendicionFondo $rendicion): bool
    {
        $cierre = $rendicion->cierreTurno;
        if (!$cierre || $cierre->estaRevertido()) {
            return false;
        }

        // Verificar que las cajas del cierre no estén abiertas con un nuevo turno
        foreach ($cierre->detalleCajas as $detalleCaja) {
            $caja = Caja::find($detalleCaja->caja_id);
            if ($caja && $caja->estado === 'abierta') {
                return false;
            }
        }

        // Verificar que sea el último cierre para esa caja/grupo
        if ($cierre->esIndividual()) {
            $detalleCaja = $cierre->detalleCajas->first();
            if (!$detalleCaja) {
                return false;
            }
            $ultimoCierre = CierreTurno::noRevertidos()
                ->whereHas('detalleCajas', fn($q) => $q->where('caja_id', $detalleCaja->caja_id))
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (!$ultimoCierre || $ultimoCierre->id !== $cierre->id) {
                return false;
            }
        } else {
            $ultimoCierreGrupo = CierreTurno::noRevertidos()
                ->where('grupo_cierre_id', $cierre->grupo_cierre_id)
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (!$ultimoCierreGrupo || $ultimoCierreGrupo->id !== $cierre->id) {
                return false;
            }

            // Para grupo sin fondo común: verificar que todas las rendiciones estén pendientes
            $esGrupalConFondoComun = $cierre->grupoCierre && $cierre->grupoCierre->usaFondoComun();
            if (!$esGrupalConFondoComun) {
                $tieneNoPendientes = RendicionFondo::where('cierre_turno_id', $cierre->id)
                    ->where('estado', '!=', RendicionFondo::ESTADO_PENDIENTE)
                    ->exists();
                if ($tieneNoPendientes) {
                    return false;
                }
            }
        }

        return true;
    }

    // ==================== DEPÓSITO BANCARIO ====================

    public function abrirModalDeposito(): void
    {
        $this->reset(['cuentaBancariaId', 'montoDeposito', 'numeroComprobante', 'observacionesDeposito']);
        $this->fechaDeposito = now()->format('Y-m-d');
        $this->showDepositoModal = true;
    }

    public function procesarDeposito(): void
    {
        $this->validate([
            'cuentaBancariaId' => 'required|exists:pymes_tenant.cuentas_bancarias,id',
            'montoDeposito' => 'required|numeric|min:0.01',
            'fechaDeposito' => 'required|date',
        ], [
            'cuentaBancariaId.required' => __('Seleccione una cuenta bancaria'),
            'montoDeposito.required' => __('Ingrese el monto'),
            'montoDeposito.min' => __('El monto debe ser mayor a 0'),
            'fechaDeposito.required' => __('Ingrese la fecha del deposito'),
        ]);

        try {
            $cuenta = CuentaBancaria::find($this->cuentaBancariaId);

            $deposito = TesoreriaService::registrarDeposito(
                $this->tesoreria,
                $cuenta,
                $this->montoDeposito,
                Carbon::parse($this->fechaDeposito),
                auth()->id(),
                $this->numeroComprobante ?: null,
                $this->observacionesDeposito ?: null
            );

            $this->showDepositoModal = false;
            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Deposito de :monto registrado en :banco', ['monto' => '$' . $this->montoDeposito, 'banco' => $cuenta->banco]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== ARQUEO ====================

    public function abrirModalArqueo(): void
    {
        $this->reset(['saldoContado', 'observacionesArqueo']);
        $this->saldoContado = $this->tesoreria->saldo_actual; // Pre-cargar saldo actual
        $this->showArqueoModal = true;
    }

    public function procesarArqueo(): void
    {
        $this->validate([
            'saldoContado' => 'required|numeric|min:0',
        ], [
            'saldoContado.required' => __('Ingrese el saldo contado'),
            'saldoContado.min' => __('El saldo no puede ser negativo'),
        ]);

        try {
            $arqueo = TesoreriaService::realizarArqueo(
                $this->tesoreria,
                $this->saldoContado,
                auth()->id(),
                $this->observacionesArqueo ?: null
            );

            $this->showArqueoModal = false;
            $this->cargarDatos();

            $diferencia = $arqueo->diferencia;
            if ($diferencia == 0) {
                $this->dispatch('toast-success', message: __('Arqueo realizado - Caja cuadrada'));
            } elseif ($diferencia > 0) {
                $this->dispatch('toast-success', message: __('Arqueo realizado - Sobrante:') . " \$" . number_format($diferencia, 2));
            } else {
                $this->dispatch('toast-error', message: __('Arqueo realizado - Faltante:') . " \$" . number_format(abs($diferencia), 2));
            }

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== DEPÓSITOS PENDIENTES ====================

    public function confirmarDepositoBancario(int $depositoId): void
    {
        try {
            $deposito = DepositoBancario::find($depositoId);

            if (!$deposito) {
                throw new \Exception(__('Deposito no encontrado'));
            }

            TesoreriaService::confirmarDeposito($deposito);

            $this->dispatch('toast-success', message: __('Deposito de :monto confirmado', ['monto' => '$' . $deposito->monto]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cancelarDepositoBancario(int $depositoId): void
    {
        try {
            $deposito = DepositoBancario::find($depositoId);

            if (!$deposito) {
                throw new \Exception(__('Deposito no encontrado'));
            }

            $deposito->cancelar();
            $this->cargarDatos();

            $this->dispatch('toast-info', message: __('Deposito cancelado - Monto devuelto a tesoreria'));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== ARQUEOS ====================

    public function verDetalleArqueo(int $arqueoId): void
    {
        $this->arqueoDetalle = ArqueoTesoreria::with(['usuario:id,name', 'supervisor:id,name'])
            ->find($arqueoId);
        $this->showArqueoDetalleModal = true;
    }

    public function cerrarDetalleArqueo(): void
    {
        $this->showArqueoDetalleModal = false;
        $this->arqueoDetalle = null;
    }

    public function aprobarArqueo(int $arqueoId, bool $aplicarAjuste = false): void
    {
        try {
            $arqueo = ArqueoTesoreria::find($arqueoId);

            if (!$arqueo) {
                throw new \Exception(__('Arqueo no encontrado'));
            }

            TesoreriaService::aprobarArqueo($arqueo, auth()->id(), $aplicarAjuste);

            $this->showArqueoDetalleModal = false;
            $this->arqueoDetalle = null;

            $mensaje = $aplicarAjuste && $arqueo->diferencia != 0
                ? __('Arqueo aprobado y ajuste aplicado')
                : __('Arqueo aprobado');

            $this->dispatch('toast-success', message: $mensaje);

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== FILTROS ====================

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroTipo', 'filtroConcepto']);
        $this->filtroFechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatedFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroFechaHasta(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroConcepto(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.tesoreria.gestion-tesoreria', [
            'movimientos' => $this->movimientos,
            'cajasDisponibles' => $this->cajasDisponibles,
            'cuentasBancarias' => $this->cuentasBancarias,
            'estadoCajas' => $this->estadoCajas,
            'depositosPendientes' => $this->depositosPendientes,
            'arqueos' => $this->arqueos,
            'provisionesRecientes' => $this->provisionesRecientes,
            'rendicionesRecientes' => $this->rendicionesRecientes,
        ]);
    }
}
