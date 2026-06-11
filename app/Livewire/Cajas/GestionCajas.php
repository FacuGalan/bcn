<?php

namespace App\Livewire\Cajas;

use App\Models\Caja;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Services\CajaService;
use App\Services\IntegracionesPago\SincronizacionMercadoPagoService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Cajas
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Listar cajas de la sucursal
 * 2. Abrir y cerrar cajas
 * 3. Realizar arqueos de caja
 * 4. Registrar ingresos/egresos manuales
 * 5. Ver movimientos de caja
 * 6. Ver resumen de cajas
 *
 * PROPIEDADES:
 * ===========
 *
 * @property Collection $cajas - Lista de cajas
 * @property bool $showAbrirModal - Modal para abrir caja
 * @property bool $showCerrarModal - Modal para cerrar caja
 * @property bool $showMovimientoModal - Modal para registrar movimiento
 * @property bool $showMovimientosModal - Modal para ver movimientos
 *
 * DEPENDENCIAS:
 * ============
 * - CajaService: Para operaciones de caja
 * - Models: Caja, MovimientoCaja, Sucursal
 *
 * FASE 4 - Sistema Multi-Sucursal (Componentes Livewire)
 *
 * @version 1.0.0
 */
#[Lazy]
class GestionCajas extends Component
{
    use WithPagination;

    public $sucursalSeleccionada = null;

    // Modales
    public $showAbrirModal = false;

    public $showCerrarModal = false;

    public $showMovimientoModal = false;

    public $showMovimientosModal = false;

    public $showArqueoModal = false;

    // Propiedades apertura
    public $cajaAbrirId = null;

    public $saldoInicial = 0;

    // Propiedades cierre
    public $cajaCerrarId = null;

    public $arqueo = null;

    // Propiedades movimiento manual
    public $cajaMovimientoId = null;

    public $tipoMovimiento = 'ingreso';

    public $montoMovimiento = 0;

    public $conceptoMovimiento = '';

    public $formaPagoMovimiento = 'efectivo';

    public $referenciaMovimiento = '';

    public $observacionesMovimiento = '';

    // Ver movimientos
    public $cajaMovimientosId = null;

    // Ver terminal Point (posnet)
    public $showTerminalModal = false;

    public $terminalCajaId = null;

    public $terminalPointInfo = null;

    protected $cajaService;

    public function boot(CajaService $cajaService)
    {
        $this->cajaService = $cajaService;
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="5" :rows="6" />
        HTML;
    }

    public function mount()
    {
        $this->sucursalSeleccionada = Sucursal::activas()->first()->id ?? 1;
    }

    public function render()
    {
        $cajas = Caja::with(['movimientos' => function ($q) {
            $q->latest()->limit(5);
        }])
            ->porSucursal($this->sucursalSeleccionada)
            ->get();

        $movimientos = $this->cajaMovimientosId
            ? MovimientoCaja::with('usuario')
                ->porCaja($this->cajaMovimientosId)
                ->orderBy('created_at', 'desc')
                ->paginate(20)
            : collect();

        return view('livewire.cajas.gestion-cajas', [
            'cajas' => $cajas,
            'movimientos' => $movimientos,
            'cajaAbrir' => $this->cajaAbrirId ? Caja::find($this->cajaAbrirId) : null,
            'cajaCerrar' => $this->cajaCerrarId ? Caja::find($this->cajaCerrarId) : null,
            'cajaMovimiento' => $this->cajaMovimientoId ? Caja::find($this->cajaMovimientoId) : null,
        ]);
    }

    /**
     * Muestra la terminal Point (posnet) asignada a la caja. Best-effort: si hay
     * credenciales Point configuradas para la sucursal, trae el modo de operación
     * del dispositivo desde MP; si no, muestra al menos el terminal_id asignado.
     */
    public function verTerminalPoint($cajaId): void
    {
        $caja = Caja::find($cajaId);
        if (! $caja || empty($caja->mp_point_terminal_id)) {
            return;
        }

        $this->terminalCajaId = $cajaId;
        $this->terminalPointInfo = [
            'terminal_id' => $caja->mp_point_terminal_id,
            'operating_mode' => null,
            'consultado' => false,
        ];

        try {
            $point = IntegracionPago::porCodigo(IntegracionPago::CODIGO_MERCADOPAGO_POINT)->first();
            $config = $point
                ? IntegracionPagoSucursal::where('integracion_pago_id', $point->id)
                    ->where('sucursal_id', $caja->sucursal_id)
                    ->where('activo', true)
                    ->first()
                : null;

            if ($config && $config->estaConfigurada()) {
                $device = collect(SincronizacionMercadoPagoService::listarTerminales($config))
                    ->firstWhere('id', $caja->mp_point_terminal_id);
                $this->terminalPointInfo['operating_mode'] = $device['operating_mode'] ?? null;
                $this->terminalPointInfo['consultado'] = true;
            }
        } catch (\Throwable $e) {
            // MP no respondió: igual mostramos el terminal_id asignado.
        }

        $this->showTerminalModal = true;
    }

    public function cerrarTerminalModal(): void
    {
        $this->showTerminalModal = false;
        $this->terminalCajaId = null;
        $this->terminalPointInfo = null;
    }

    public function abrirModalApertura($cajaId)
    {
        $this->cajaAbrirId = $cajaId;
        $this->saldoInicial = 0;
        $this->showAbrirModal = true;
    }

    public function cancelarApertura(): void
    {
        $this->showAbrirModal = false;
        $this->resetAperturaForm();
    }

    public function procesarApertura()
    {
        try {
            if ($this->saldoInicial < 0) {
                $this->dispatch('toast-error', message: __('El saldo inicial no puede ser negativo'));

                return;
            }

            $this->cajaService->abrirCaja($this->cajaAbrirId, $this->saldoInicial, Auth::id());
            $this->dispatch('toast-success', message: __('Caja abierta exitosamente'));
            $this->showAbrirModal = false;
            $this->resetAperturaForm();

        } catch (Exception $e) {
            Log::error('Error al abrir caja', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error').': '.$e->getMessage());
        }
    }

    protected function resetAperturaForm()
    {
        $this->cajaAbrirId = null;
        $this->saldoInicial = 0;
    }

    public function abrirModalCierre($cajaId)
    {
        try {
            $this->cajaCerrarId = $cajaId;
            $this->arqueo = $this->cajaService->realizarArqueo($cajaId);
            $this->showCerrarModal = true;
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: __('Error al realizar arqueo').': '.$e->getMessage());
        }
    }

    public function cancelarCierre(): void
    {
        $this->showCerrarModal = false;
        $this->cajaCerrarId = null;
        $this->arqueo = null;
    }

    public function procesarCierre()
    {
        try {
            $resultado = $this->cajaService->cerrarCaja($this->cajaCerrarId, Auth::id());
            $this->dispatch('toast-success', message: __('Caja cerrada exitosamente'));
            $this->showCerrarModal = false;
            $this->cajaCerrarId = null;
            $this->arqueo = null;

        } catch (Exception $e) {
            Log::error('Error al cerrar caja', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error').': '.$e->getMessage());
        }
    }

    public function abrirModalMovimiento($cajaId)
    {
        $this->cajaMovimientoId = $cajaId;
        $this->tipoMovimiento = 'ingreso';
        $this->montoMovimiento = 0;
        $this->conceptoMovimiento = '';
        $this->formaPagoMovimiento = 'efectivo';
        $this->referenciaMovimiento = '';
        $this->observacionesMovimiento = '';
        $this->showMovimientoModal = true;
    }

    public function cancelarMovimiento(): void
    {
        $this->showMovimientoModal = false;
        $this->resetMovimientoForm();
    }

    public function procesarMovimiento()
    {
        try {
            if ($this->montoMovimiento <= 0) {
                $this->dispatch('toast-error', message: __('El monto debe ser mayor a cero'));

                return;
            }

            if (empty($this->conceptoMovimiento)) {
                $this->dispatch('toast-error', message: __('Debe ingresar un concepto'));

                return;
            }

            if ($this->tipoMovimiento === 'ingreso') {
                $this->cajaService->registrarIngreso(
                    $this->cajaMovimientoId,
                    $this->montoMovimiento,
                    $this->conceptoMovimiento,
                    $this->formaPagoMovimiento,
                    Auth::id(),
                    $this->referenciaMovimiento,
                    $this->observacionesMovimiento
                );
            } else {
                $this->cajaService->registrarEgreso(
                    $this->cajaMovimientoId,
                    $this->montoMovimiento,
                    $this->conceptoMovimiento,
                    $this->formaPagoMovimiento,
                    Auth::id(),
                    $this->referenciaMovimiento,
                    $this->observacionesMovimiento
                );
            }

            $this->dispatch('toast-success', message: ucfirst($this->tipoMovimiento).' registrado exitosamente');
            $this->showMovimientoModal = false;
            $this->resetMovimientoForm();

        } catch (Exception $e) {
            Log::error('Error al registrar movimiento', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: 'Error: '.$e->getMessage());
        }
    }

    protected function resetMovimientoForm()
    {
        $this->cajaMovimientoId = null;
        $this->tipoMovimiento = 'ingreso';
        $this->montoMovimiento = 0;
        $this->conceptoMovimiento = '';
        $this->formaPagoMovimiento = 'efectivo';
        $this->referenciaMovimiento = '';
        $this->observacionesMovimiento = '';
    }

    public function verMovimientos($cajaId)
    {
        $this->cajaMovimientosId = $cajaId;
        $this->showMovimientosModal = true;
    }

    public function cerrarMovimientos()
    {
        $this->showMovimientosModal = false;
        $this->cajaMovimientosId = null;
    }
}
