<?php

namespace App\Livewire\Cajas;

use Livewire\Component;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\MovimientoTesoreria;
use App\Models\Tesoreria;
use App\Models\TransferenciaEfectivo;
use App\Services\CajaService;
use App\Services\SucursalService;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Componente de Movimientos Manuales de Caja
 *
 * Permite realizar:
 * - Transferencias de efectivo entre cajas
 * - Ingresos manuales (con opción de origen tesorería)
 * - Egresos manuales (con opción de destino tesorería)
 */
class MovimientosManuales extends Component
{
    // Tab activo
    public string $tabActivo = 'transferencia';

    // Cajas disponibles
    public $cajasDisponibles = [];
    public $cajaActualId = null;
    public $cajaActual = null;

    // Tesorería
    public $tesoreria = null;
    public $tesoreriaActiva = false;

    // Formulario de Transferencia
    public $transferencia = [
        'caja_destino_id' => null,
        'monto' => null,
        'motivo' => '', // Se guarda en observaciones
    ];

    // Formulario de Ingreso
    public $ingreso = [
        'monto' => null,
        'motivo' => '',
        'origen' => 'tesoreria', // tesoreria, otro
    ];

    // Formulario de Egreso
    public $egreso = [
        'monto' => null,
        'motivo' => '',
        'destino' => 'tesoreria', // tesoreria, otro
    ];

    // Historial de movimientos
    public $movimientosRecientes = [];
    public $transferenciasRecientes = [];

    // Modal de confirmación
    public bool $showConfirmModal = false;
    public string $accionPendiente = '';
    public array $datosPendientes = [];

    protected $listeners = [
        'caja-actualizada' => 'cargarDatos',
        'caja-changed' => 'handleCajaChanged',
    ];

    public function mount()
    {
        $this->cargarDatos();
    }

    public function cargarDatos()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        // Cargar cajas operativas de la sucursal
        $this->cajasDisponibles = Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->where('estado', 'abierta')
            ->orderBy('nombre')
            ->get();

        // Caja actual
        $this->cajaActualId = CajaService::getCajaActiva();
        $this->cajaActual = $this->cajaActualId
            ? $this->cajasDisponibles->firstWhere('id', $this->cajaActualId)
            : null;

        // Tesorería de la sucursal
        $this->tesoreria = Tesoreria::where('sucursal_id', $sucursalId)->first();
        $this->tesoreriaActiva = $this->tesoreria && $this->tesoreria->activo;

        // Cargar historial
        $this->cargarHistorial();
    }

    public function handleCajaChanged($cajaId = null)
    {
        $this->cajaActualId = $cajaId;
        $this->cargarDatos();
    }

    protected function cargarHistorial()
    {
        if (!$this->cajaActualId) {
            $this->movimientosRecientes = [];
            $this->transferenciasRecientes = [];
            return;
        }

        // Últimos 10 movimientos manuales de la caja
        $this->movimientosRecientes = MovimientoCaja::where('caja_id', $this->cajaActualId)
            ->whereIn('referencia_tipo', ['ajuste', 'retiro', 'ingreso_manual', 'egreso_manual', 'transferencia'])
            ->with('usuario')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($mov) {
                return [
                    'id' => $mov->id,
                    'tipo' => $mov->tipo,
                    'concepto' => $mov->concepto,
                    'monto' => $mov->monto,
                    'usuario' => $mov->usuario?->name ?? 'Sistema',
                    'fecha' => $mov->created_at->format('d/m H:i'),
                    'referencia_tipo' => $mov->referencia_tipo,
                ];
            })
            ->toArray();

        // Últimas 5 transferencias
        $this->transferenciasRecientes = TransferenciaEfectivo::where(function ($q) {
                $q->where('caja_origen_id', $this->cajaActualId)
                  ->orWhere('caja_destino_id', $this->cajaActualId);
            })
            ->with(['cajaOrigen', 'cajaDestino', 'usuario'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                $esOrigen = $t->caja_origen_id == $this->cajaActualId;
                return [
                    'id' => $t->id,
                    'tipo' => $esOrigen ? 'salida' : 'entrada',
                    'caja_relacionada' => $esOrigen ? $t->cajaDestino->nombre : $t->cajaOrigen->nombre,
                    'monto' => $t->monto,
                    'motivo' => $t->observaciones,
                    'estado' => $t->estado,
                    'usuario' => $t->usuario?->name ?? 'Sistema',
                    'fecha' => $t->fecha ? $t->fecha->format('d/m H:i') : $t->created_at->format('d/m H:i'),
                ];
            })
            ->toArray();
    }

    public function cambiarTab(string $tab)
    {
        $this->tabActivo = $tab;
        $this->resetFormularios();
    }

    protected function resetFormularios()
    {
        $this->transferencia = [
            'caja_destino_id' => null,
            'monto' => null,
            'motivo' => '',
        ];
        $this->ingreso = [
            'monto' => null,
            'motivo' => '',
            'origen' => 'tesoreria',
        ];
        $this->egreso = [
            'monto' => null,
            'motivo' => '',
            'destino' => 'tesoreria',
        ];
        $this->resetErrorBag();
    }

    // ==================== TRANSFERENCIA ====================

    public function confirmarTransferencia()
    {
        $this->validate([
            'transferencia.caja_destino_id' => 'required|exists:pymes_tenant.cajas,id',
            'transferencia.monto' => 'required|numeric|min:0.01',
            'transferencia.motivo' => 'required|string|max:255',
        ], [
            'transferencia.caja_destino_id.required' => __('Seleccione la caja destino'),
            'transferencia.monto.required' => __('Ingrese el monto'),
            'transferencia.monto.min' => __('El monto debe ser mayor a cero'),
            'transferencia.motivo.required' => __('Ingrese el motivo de la transferencia'),
        ]);

        // Validaciones adicionales
        if ($this->transferencia['caja_destino_id'] == $this->cajaActualId) {
            $this->addError('transferencia.caja_destino_id', __('La caja destino debe ser diferente a la actual'));
            return;
        }

        if (!$this->cajaActual || $this->cajaActual->saldo_actual < $this->transferencia['monto']) {
            $this->addError('transferencia.monto', __('Saldo insuficiente en la caja'));
            return;
        }

        $cajaDestino = $this->cajasDisponibles->firstWhere('id', $this->transferencia['caja_destino_id']);

        $this->accionPendiente = 'transferencia';
        $this->datosPendientes = [
            'caja_origen' => $this->cajaActual->nombre,
            'caja_destino' => $cajaDestino->nombre,
            'monto' => $this->transferencia['monto'],
            'motivo' => $this->transferencia['motivo'],
        ];
        $this->showConfirmModal = true;
    }

    public function ejecutarTransferencia()
    {
        try {
            DB::beginTransaction();

            $usuarioId = auth()->id();
            $monto = (float) $this->transferencia['monto'];
            $motivo = $this->transferencia['motivo'];
            $cajaDestino = Caja::find($this->transferencia['caja_destino_id']);

            // Crear registro de transferencia
            $transferencia = TransferenciaEfectivo::create([
                'caja_origen_id' => $this->cajaActualId,
                'caja_destino_id' => $this->transferencia['caja_destino_id'],
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'fecha' => now(),
                'estado' => 'completada',
                'observaciones' => $motivo,
            ]);

            // Egreso en caja origen
            MovimientoCaja::create([
                'caja_id' => $this->cajaActualId,
                'tipo' => 'egreso',
                'concepto' => "Transferencia a {$cajaDestino->nombre}: {$motivo}",
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
            ]);

            // Actualizar saldo caja origen
            $this->cajaActual->saldo_actual -= $monto;
            $this->cajaActual->save();

            // Ingreso en caja destino
            MovimientoCaja::create([
                'caja_id' => $cajaDestino->id,
                'tipo' => 'ingreso',
                'concepto' => "Transferencia desde {$this->cajaActual->nombre}: {$motivo}",
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
            ]);

            // Actualizar saldo caja destino
            $cajaDestino->saldo_actual += $monto;
            $cajaDestino->save();

            DB::commit();

            CajaService::clearCache();
            $this->showConfirmModal = false;
            $this->resetFormularios();
            $this->cargarDatos();

            $this->dispatch('caja-actualizada', cajaId: $this->cajaActualId, accion: 'transferencia');
            $this->dispatch('toast-success', message: __('Transferencia realizada correctamente'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en transferencia', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error') . ': ' . $e->getMessage());
        }
    }

    // ==================== INGRESO MANUAL ====================

    public function confirmarIngreso()
    {
        $this->validate([
            'ingreso.monto' => 'required|numeric|min:0.01',
            'ingreso.motivo' => 'required|string|max:255',
            'ingreso.origen' => 'required|in:tesoreria,otro',
        ], [
            'ingreso.monto.required' => __('Ingrese el monto'),
            'ingreso.monto.min' => __('El monto debe ser mayor a cero'),
            'ingreso.motivo.required' => __('Ingrese el motivo del ingreso'),
        ]);

        // Si viene de tesorería, validar saldo
        if ($this->ingreso['origen'] === 'tesoreria') {
            if (!$this->tesoreriaActiva) {
                $this->addError('ingreso.origen', __('No hay tesorería activa en esta sucursal'));
                return;
            }
            if ($this->tesoreria->saldo_actual < $this->ingreso['monto']) {
                $this->addError('ingreso.monto', __('Saldo insuficiente en tesorería'));
                return;
            }
        }

        $this->accionPendiente = 'ingreso';
        $this->datosPendientes = [
            'caja' => $this->cajaActual->nombre,
            'monto' => $this->ingreso['monto'],
            'motivo' => $this->ingreso['motivo'],
            'origen' => $this->ingreso['origen'] === 'tesoreria' ? __('Tesorería') : __('Otro origen'),
        ];
        $this->showConfirmModal = true;
    }

    public function ejecutarIngreso()
    {
        try {
            DB::beginTransaction();

            $usuarioId = auth()->id();
            $monto = (float) $this->ingreso['monto'];
            $motivo = $this->ingreso['motivo'];
            $esDesdeTesoreria = $this->ingreso['origen'] === 'tesoreria';

            // Registrar movimiento en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $this->cajaActualId,
                'tipo' => 'ingreso',
                'concepto' => $motivo . ($esDesdeTesoreria ? ' (desde tesorería)' : ''),
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'ingreso_manual',
                'referencia_id' => null,
            ]);

            // Actualizar saldo de caja
            $this->cajaActual->saldo_actual += $monto;
            $this->cajaActual->save();

            // Si viene de tesorería, registrar el egreso
            if ($esDesdeTesoreria && $this->tesoreria) {
                $saldoAnterior = $this->tesoreria->saldo_actual;
                $this->tesoreria->saldo_actual -= $monto;
                $this->tesoreria->save();

                MovimientoTesoreria::create([
                    'tesoreria_id' => $this->tesoreria->id,
                    'tipo' => 'egreso',
                    'concepto' => "Ingreso manual a caja {$this->cajaActual->nombre}: {$motivo}",
                    'monto' => $monto,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => $this->tesoreria->saldo_actual,
                    'usuario_id' => $usuarioId,
                    'referencia_tipo' => 'ingreso_manual_caja',
                    'referencia_id' => $movimientoCaja->id,
                ]);
            }

            DB::commit();

            CajaService::clearCache();
            $this->showConfirmModal = false;
            $this->resetFormularios();
            $this->cargarDatos();

            $this->dispatch('caja-actualizada', cajaId: $this->cajaActualId, accion: 'ingreso_manual');
            $this->dispatch('toast-success', message: __('Ingreso registrado correctamente'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ingreso manual', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error') . ': ' . $e->getMessage());
        }
    }

    // ==================== EGRESO MANUAL ====================

    public function confirmarEgreso()
    {
        $this->validate([
            'egreso.monto' => 'required|numeric|min:0.01',
            'egreso.motivo' => 'required|string|max:255',
            'egreso.destino' => 'required|in:tesoreria,otro',
        ], [
            'egreso.monto.required' => __('Ingrese el monto'),
            'egreso.monto.min' => __('El monto debe ser mayor a cero'),
            'egreso.motivo.required' => __('Ingrese el motivo del egreso'),
        ]);

        // Validar saldo de caja
        if (!$this->cajaActual || $this->cajaActual->saldo_actual < $this->egreso['monto']) {
            $this->addError('egreso.monto', __('Saldo insuficiente en la caja'));
            return;
        }

        // Si va a tesorería, validar que exista
        if ($this->egreso['destino'] === 'tesoreria' && !$this->tesoreriaActiva) {
            $this->addError('egreso.destino', __('No hay tesorería activa en esta sucursal'));
            return;
        }

        $this->accionPendiente = 'egreso';
        $this->datosPendientes = [
            'caja' => $this->cajaActual->nombre,
            'monto' => $this->egreso['monto'],
            'motivo' => $this->egreso['motivo'],
            'destino' => $this->egreso['destino'] === 'tesoreria' ? __('Tesorería') : __('Otro destino'),
        ];
        $this->showConfirmModal = true;
    }

    public function ejecutarEgreso()
    {
        try {
            DB::beginTransaction();

            $usuarioId = auth()->id();
            $monto = (float) $this->egreso['monto'];
            $motivo = $this->egreso['motivo'];
            $esHaciaTesoreria = $this->egreso['destino'] === 'tesoreria';

            // Registrar movimiento en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $this->cajaActualId,
                'tipo' => 'egreso',
                'concepto' => $motivo . ($esHaciaTesoreria ? ' (a tesorería)' : ''),
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'egreso_manual',
                'referencia_id' => null,
            ]);

            // Actualizar saldo de caja
            $this->cajaActual->saldo_actual -= $monto;
            $this->cajaActual->save();

            // Si va a tesorería, registrar el ingreso
            if ($esHaciaTesoreria && $this->tesoreria) {
                $saldoAnterior = $this->tesoreria->saldo_actual;
                $this->tesoreria->saldo_actual += $monto;
                $this->tesoreria->save();

                MovimientoTesoreria::create([
                    'tesoreria_id' => $this->tesoreria->id,
                    'tipo' => 'ingreso',
                    'concepto' => "Egreso manual desde caja {$this->cajaActual->nombre}: {$motivo}",
                    'monto' => $monto,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => $this->tesoreria->saldo_actual,
                    'usuario_id' => $usuarioId,
                    'referencia_tipo' => 'egreso_manual_caja',
                    'referencia_id' => $movimientoCaja->id,
                ]);
            }

            DB::commit();

            CajaService::clearCache();
            $this->showConfirmModal = false;
            $this->resetFormularios();
            $this->cargarDatos();

            $this->dispatch('caja-actualizada', cajaId: $this->cajaActualId, accion: 'egreso_manual');
            $this->dispatch('toast-success', message: __('Egreso registrado correctamente'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en egreso manual', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: __('Error') . ': ' . $e->getMessage());
        }
    }

    // ==================== MODAL ====================

    public function ejecutarAccion()
    {
        match ($this->accionPendiente) {
            'transferencia' => $this->ejecutarTransferencia(),
            'ingreso' => $this->ejecutarIngreso(),
            'egreso' => $this->ejecutarEgreso(),
            default => null,
        };
    }

    public function cancelarAccion()
    {
        $this->showConfirmModal = false;
        $this->accionPendiente = '';
        $this->datosPendientes = [];
    }

    public function render()
    {
        return view('livewire.cajas.movimientos-manuales');
    }
}
