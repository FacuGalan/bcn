<?php

namespace App\Livewire\Cajas;

use App\Models\Caja;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\MovimientoTesoreria;
use App\Models\Tesoreria;
use App\Models\TipoCambio;
use App\Models\TransferenciaEfectivo;
use App\Services\CajaService;
use App\Services\SucursalService;
use App\Traits\CajaAware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

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
    use CajaAware;

    // Tab activo
    public string $tabActivo = 'transferencia';

    // Cajas disponibles
    public $cajasDisponibles = [];

    public $cajaActualId = null;

    public $cajaActual = null;

    // Tesorería
    public $tesoreria = null;

    public $tesoreriaActiva = false;

    // Multi-moneda
    public $monedasDisponibles = [];

    public $saldosMonedasCaja = [];

    // Formulario de Transferencia
    public $transferencia = [
        'caja_destino_id' => null,
        'monto' => null,
        'motivo' => '',
        'moneda_id' => null,
    ];

    // Formulario de Ingreso
    public $ingreso = [
        'monto' => null,
        'motivo' => '',
        'origen' => 'tesoreria',
        'moneda_id' => null,
    ];

    // Formulario de Egreso
    public $egreso = [
        'monto' => null,
        'motivo' => '',
        'destino' => 'tesoreria',
        'moneda_id' => null,
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

    /**
     * Hook llamado cuando cambia la sucursal (desde SucursalAware trait)
     */
    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->showConfirmModal = false;
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

        // Monedas extranjeras activas
        $this->monedasDisponibles = Moneda::activas()
            ->where('es_principal', false)
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'simbolo'])
            ->toArray();

        // Saldos en monedas extranjeras de la caja actual
        $this->cargarSaldosMonedasCaja();

        // Cargar historial
        $this->cargarHistorial();
    }

    public function handleCajaChanged($cajaId = null)
    {
        $this->cajaActualId = $cajaId;
        $this->cargarDatos();
    }

    /**
     * Carga los saldos acumulados por moneda extranjera en la caja actual (turno abierto)
     */
    protected function cargarSaldosMonedasCaja(): void
    {
        $this->saldosMonedasCaja = [];

        if (! $this->cajaActualId || empty($this->monedasDisponibles)) {
            return;
        }

        $movimientos = MovimientoCaja::where('caja_id', $this->cajaActualId)
            ->whereNull('cierre_turno_id')
            ->whereNotNull('moneda_id')
            ->selectRaw('moneda_id, tipo, SUM(monto_moneda_original) as total')
            ->groupBy('moneda_id', 'tipo')
            ->get();

        $saldos = [];
        foreach ($movimientos as $mov) {
            if (! isset($saldos[$mov->moneda_id])) {
                $saldos[$mov->moneda_id] = 0;
            }
            if ($mov->tipo === 'ingreso') {
                $saldos[$mov->moneda_id] += (float) $mov->total;
            } else {
                $saldos[$mov->moneda_id] -= (float) $mov->total;
            }
        }

        foreach ($this->monedasDisponibles as $moneda) {
            $saldo = $saldos[$moneda['id']] ?? 0;
            if ($saldo != 0) {
                $this->saldosMonedasCaja[$moneda['id']] = [
                    'codigo' => $moneda['codigo'],
                    'simbolo' => $moneda['simbolo'],
                    'saldo' => round($saldo, 2),
                ];
            }
        }
    }

    /**
     * Obtiene el saldo de una moneda específica en la caja (turno abierto)
     */
    protected function getSaldoMonedaCaja(int $cajaId, int $monedaId): float
    {
        $ingresos = (float) MovimientoCaja::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('moneda_id', $monedaId)
            ->where('tipo', 'ingreso')
            ->sum('monto_moneda_original');

        $egresos = (float) MovimientoCaja::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('moneda_id', $monedaId)
            ->where('tipo', 'egreso')
            ->sum('monto_moneda_original');

        return round($ingresos - $egresos, 2);
    }

    /**
     * Obtiene la cotización y equivalente ARS para una moneda extranjera.
     * Retorna [montoARS, tipoCambioId, tasa] o null si no hay cotización.
     */
    protected function obtenerEquivalenteARS(float $montoOriginal, int $monedaId): ?array
    {
        $monedaPrincipal = Moneda::obtenerPrincipal();
        if (! $monedaPrincipal) {
            return null;
        }

        $tasa = TipoCambio::obtenerTasaVenta($monedaId, $monedaPrincipal->id);
        if (! $tasa || $tasa <= 0) {
            return null;
        }

        $tcRecord = TipoCambio::ultimaTasa($monedaId, $monedaPrincipal->id);

        return [
            'monto_ars' => round($montoOriginal * $tasa, 2),
            'tipo_cambio_id' => $tcRecord?->id,
            'tasa' => $tasa,
        ];
    }

    protected function cargarHistorial()
    {
        if (! $this->cajaActualId) {
            $this->movimientosRecientes = [];
            $this->transferenciasRecientes = [];

            return;
        }

        // Últimos 10 movimientos manuales de la caja
        $this->movimientosRecientes = MovimientoCaja::where('caja_id', $this->cajaActualId)
            ->whereIn('referencia_tipo', ['ajuste', 'retiro', 'ingreso_manual', 'egreso_manual', 'transferencia'])
            ->with(['usuario', 'moneda'])
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
                    'moneda_simbolo' => $mov->moneda?->simbolo,
                    'monto_moneda_original' => $mov->monto_moneda_original,
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
            'moneda_id' => null,
        ];
        $this->ingreso = [
            'monto' => null,
            'motivo' => '',
            'origen' => 'tesoreria',
            'moneda_id' => null,
        ];
        $this->egreso = [
            'monto' => null,
            'motivo' => '',
            'destino' => 'tesoreria',
            'moneda_id' => null,
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

        $monedaId = $this->transferencia['moneda_id'] ? (int) $this->transferencia['moneda_id'] : null;
        $monto = (float) $this->transferencia['monto'];

        if ($monedaId) {
            // Validar saldo en moneda extranjera
            $saldoMoneda = $this->getSaldoMonedaCaja($this->cajaActualId, $monedaId);
            if ($saldoMoneda < $monto) {
                $this->addError('transferencia.monto', __('Saldo insuficiente en la moneda seleccionada'));

                return;
            }
        } else {
            if (! $this->cajaActual || $this->cajaActual->saldo_actual < $monto) {
                $this->addError('transferencia.monto', __('Saldo insuficiente en la caja'));

                return;
            }
        }

        $cajaDestino = $this->cajasDisponibles->firstWhere('id', $this->transferencia['caja_destino_id']);
        $monedaInfo = $monedaId ? collect($this->monedasDisponibles)->firstWhere('id', $monedaId) : null;

        // Calcular equivalente ARS si es moneda extranjera
        $equivalenteARS = null;
        if ($monedaId) {
            $equivalenteARS = $this->obtenerEquivalenteARS($monto, $monedaId);
            if (! $equivalenteARS) {
                $this->addError('transferencia.monto', __('No hay cotización disponible para esta moneda'));

                return;
            }
        }

        $this->accionPendiente = 'transferencia';
        $this->datosPendientes = [
            'caja_origen' => $this->cajaActual->nombre,
            'caja_destino' => $cajaDestino->nombre,
            'monto' => $monto,
            'motivo' => $this->transferencia['motivo'],
            'moneda_simbolo' => $monedaInfo ? $monedaInfo['simbolo'] : '$',
            'moneda_nombre' => $monedaInfo ? $monedaInfo['nombre'] : null,
            'equivalente_ars' => $equivalenteARS ? $equivalenteARS['monto_ars'] : null,
            'cotizacion' => $equivalenteARS ? $equivalenteARS['tasa'] : null,
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
            $monedaId = $this->transferencia['moneda_id'] ? (int) $this->transferencia['moneda_id'] : null;
            $cajaDestino = Caja::find($this->transferencia['caja_destino_id']);
            $esMonedaExtranjera = $monedaId !== null;

            // Calcular equivalente ARS para moneda extranjera
            $montoARS = $monto;
            $tipoCambioId = null;
            if ($esMonedaExtranjera) {
                $equiv = $this->obtenerEquivalenteARS($monto, $monedaId);
                if (! $equiv) {
                    throw new \Exception(__('No hay cotización disponible para esta moneda'));
                }
                $montoARS = $equiv['monto_ars'];
                $tipoCambioId = $equiv['tipo_cambio_id'];
            }

            // Crear registro de transferencia
            $transferencia = TransferenciaEfectivo::create([
                'caja_origen_id' => $this->cajaActualId,
                'caja_destino_id' => $this->transferencia['caja_destino_id'],
                'monto' => $montoARS,
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
                'monto' => $montoARS,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
                'moneda_id' => $monedaId,
                'monto_moneda_original' => $esMonedaExtranjera ? $monto : null,
                'tipo_cambio_id' => $tipoCambioId,
            ]);

            // Ingreso en caja destino
            MovimientoCaja::create([
                'caja_id' => $cajaDestino->id,
                'tipo' => 'ingreso',
                'concepto' => "Transferencia desde {$this->cajaActual->nombre}: {$motivo}",
                'monto' => $montoARS,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
                'moneda_id' => $monedaId,
                'monto_moneda_original' => $esMonedaExtranjera ? $monto : null,
                'tipo_cambio_id' => $tipoCambioId,
            ]);

            // Actualizar saldo_actual (ARS equivalente siempre)
            $this->cajaActual->saldo_actual -= $montoARS;
            $this->cajaActual->save();

            $cajaDestino->saldo_actual += $montoARS;
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
            $this->dispatch('toast-error', message: __('Error').': '.$e->getMessage());
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

        $monedaId = $this->ingreso['moneda_id'] ? (int) $this->ingreso['moneda_id'] : null;
        $monto = (float) $this->ingreso['monto'];

        // Si viene de tesorería, validar saldo
        if ($this->ingreso['origen'] === 'tesoreria') {
            if (! $this->tesoreriaActiva) {
                $this->addError('ingreso.origen', __('No hay tesorería activa en esta sucursal'));

                return;
            }
            if ($monedaId) {
                if (! $this->tesoreria->tieneSaldoSuficienteMoneda($monto, $monedaId)) {
                    $this->addError('ingreso.monto', __('Saldo insuficiente en la moneda seleccionada'));

                    return;
                }
            } else {
                if ($this->tesoreria->saldo_actual < $monto) {
                    $this->addError('ingreso.monto', __('Saldo insuficiente en tesorería'));

                    return;
                }
            }
        }

        $monedaInfo = $monedaId ? collect($this->monedasDisponibles)->firstWhere('id', $monedaId) : null;

        // Calcular equivalente ARS si es moneda extranjera
        $equivalenteARS = null;
        if ($monedaId) {
            $equivalenteARS = $this->obtenerEquivalenteARS($monto, $monedaId);
            if (! $equivalenteARS) {
                $this->addError('ingreso.monto', __('No hay cotización disponible para esta moneda'));

                return;
            }
        }

        $this->accionPendiente = 'ingreso';
        $this->datosPendientes = [
            'caja' => $this->cajaActual->nombre,
            'monto' => $monto,
            'motivo' => $this->ingreso['motivo'],
            'origen' => $this->ingreso['origen'] === 'tesoreria' ? __('Tesorería') : __('Otro origen'),
            'moneda_simbolo' => $monedaInfo ? $monedaInfo['simbolo'] : '$',
            'moneda_nombre' => $monedaInfo ? $monedaInfo['nombre'] : null,
            'equivalente_ars' => $equivalenteARS ? $equivalenteARS['monto_ars'] : null,
            'cotizacion' => $equivalenteARS ? $equivalenteARS['tasa'] : null,
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
            $monedaId = $this->ingreso['moneda_id'] ? (int) $this->ingreso['moneda_id'] : null;
            $esMonedaExtranjera = $monedaId !== null;

            // Calcular equivalente ARS para moneda extranjera
            $montoARS = $monto;
            $tipoCambioId = null;
            if ($esMonedaExtranjera) {
                $equiv = $this->obtenerEquivalenteARS($monto, $monedaId);
                if (! $equiv) {
                    throw new \Exception(__('No hay cotización disponible para esta moneda'));
                }
                $montoARS = $equiv['monto_ars'];
                $tipoCambioId = $equiv['tipo_cambio_id'];
            }

            // Registrar movimiento en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $this->cajaActualId,
                'tipo' => 'ingreso',
                'concepto' => $motivo.($esDesdeTesoreria ? ' (desde tesorería)' : ''),
                'monto' => $montoARS,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'ingreso_manual',
                'referencia_id' => null,
                'moneda_id' => $monedaId,
                'monto_moneda_original' => $esMonedaExtranjera ? $monto : null,
                'tipo_cambio_id' => $tipoCambioId,
            ]);

            // Actualizar saldo_actual (ARS equivalente siempre)
            $this->cajaActual->saldo_actual += $montoARS;
            $this->cajaActual->save();

            // Si viene de tesorería, registrar el egreso
            if ($esDesdeTesoreria && $this->tesoreria) {
                if ($esMonedaExtranjera) {
                    $this->tesoreria->egresoMonedaExtranjera(
                        $monto,
                        "Ingreso manual a caja {$this->cajaActual->nombre}: {$motivo}",
                        $usuarioId,
                        $monedaId,
                        'ingreso_manual_caja',
                        $movimientoCaja->id
                    );
                } else {
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
            $this->dispatch('toast-error', message: __('Error').': '.$e->getMessage());
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

        $monedaId = $this->egreso['moneda_id'] ? (int) $this->egreso['moneda_id'] : null;
        $monto = (float) $this->egreso['monto'];

        // Validar saldo
        if ($monedaId) {
            $saldoMoneda = $this->getSaldoMonedaCaja($this->cajaActualId, $monedaId);
            if ($saldoMoneda < $monto) {
                $this->addError('egreso.monto', __('Saldo insuficiente en la moneda seleccionada'));

                return;
            }
        } else {
            if (! $this->cajaActual || $this->cajaActual->saldo_actual < $monto) {
                $this->addError('egreso.monto', __('Saldo insuficiente en la caja'));

                return;
            }
        }

        // Si va a tesorería, validar que exista
        if ($this->egreso['destino'] === 'tesoreria' && ! $this->tesoreriaActiva) {
            $this->addError('egreso.destino', __('No hay tesorería activa en esta sucursal'));

            return;
        }

        $monedaInfo = $monedaId ? collect($this->monedasDisponibles)->firstWhere('id', $monedaId) : null;

        // Calcular equivalente ARS si es moneda extranjera
        $equivalenteARS = null;
        if ($monedaId) {
            $equivalenteARS = $this->obtenerEquivalenteARS($monto, $monedaId);
            if (! $equivalenteARS) {
                $this->addError('egreso.monto', __('No hay cotización disponible para esta moneda'));

                return;
            }
        }

        $this->accionPendiente = 'egreso';
        $this->datosPendientes = [
            'caja' => $this->cajaActual->nombre,
            'monto' => $monto,
            'motivo' => $this->egreso['motivo'],
            'destino' => $this->egreso['destino'] === 'tesoreria' ? __('Tesorería') : __('Otro destino'),
            'moneda_simbolo' => $monedaInfo ? $monedaInfo['simbolo'] : '$',
            'moneda_nombre' => $monedaInfo ? $monedaInfo['nombre'] : null,
            'equivalente_ars' => $equivalenteARS ? $equivalenteARS['monto_ars'] : null,
            'cotizacion' => $equivalenteARS ? $equivalenteARS['tasa'] : null,
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
            $monedaId = $this->egreso['moneda_id'] ? (int) $this->egreso['moneda_id'] : null;
            $esMonedaExtranjera = $monedaId !== null;

            // Calcular equivalente ARS para moneda extranjera
            $montoARS = $monto;
            $tipoCambioId = null;
            if ($esMonedaExtranjera) {
                $equiv = $this->obtenerEquivalenteARS($monto, $monedaId);
                if (! $equiv) {
                    throw new \Exception(__('No hay cotización disponible para esta moneda'));
                }
                $montoARS = $equiv['monto_ars'];
                $tipoCambioId = $equiv['tipo_cambio_id'];
            }

            // Registrar movimiento en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $this->cajaActualId,
                'tipo' => 'egreso',
                'concepto' => $motivo.($esHaciaTesoreria ? ' (a tesorería)' : ''),
                'monto' => $montoARS,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'egreso_manual',
                'referencia_id' => null,
                'moneda_id' => $monedaId,
                'monto_moneda_original' => $esMonedaExtranjera ? $monto : null,
                'tipo_cambio_id' => $tipoCambioId,
            ]);

            // Actualizar saldo_actual (ARS equivalente siempre)
            $this->cajaActual->saldo_actual -= $montoARS;
            $this->cajaActual->save();

            // Si va a tesorería, registrar el ingreso
            if ($esHaciaTesoreria && $this->tesoreria) {
                if ($esMonedaExtranjera) {
                    $this->tesoreria->ingresoMonedaExtranjera(
                        $monto,
                        "Egreso manual desde caja {$this->cajaActual->nombre}: {$motivo}",
                        $usuarioId,
                        $monedaId,
                        'egreso_manual_caja',
                        $movimientoCaja->id
                    );
                } else {
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
            $this->dispatch('toast-error', message: __('Error').': '.$e->getMessage());
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
