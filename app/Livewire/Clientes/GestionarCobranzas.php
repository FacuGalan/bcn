<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FormaPago;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\CobroService;
use App\Traits\SucursalAware;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para gestión de cobranzas
 *
 * Permite registrar cobros de clientes con cuenta corriente,
 * ver cuenta corriente, y generar reportes de antigüedad.
 */
#[Layout('layouts.app')]
class GestionarCobranzas extends Component
{
    use WithPagination;
    use SucursalAware;

    // ==================== Propiedades de Filtros ====================
    public string $search = '';
    public string $filterEstado = 'con_deuda'; // all, con_deuda, sin_deuda
    public string $filterAntiguedad = 'all'; // all, 0_30, 31_60, 61_90, 90_mas
    public bool $showFilters = false;

    // ==================== Modal de Cobro ====================
    public bool $showCobroModal = false;
    public ?int $clienteIdCobro = null;
    public ?Cliente $clienteCobro = null;
    public array $ventasPendientes = [];
    public array $ventasSeleccionadas = [];
    public string $modoSeleccion = 'fifo'; // fifo, manual
    public float $montoACobrar = 0;
    public float $interesTotal = 0;
    public float $descuentoAplicado = 0;
    public string $observaciones = '';

    // ==================== Desglose de Pagos ====================
    public array $desglosePagos = [];
    public array $formasPagoSucursal = [];
    public float $montoPendienteDesglose = 0;
    public array $nuevoPago = [
        'forma_pago_id' => '',
        'monto' => '',
        'cuotas' => 1,
        'cuota_id' => null,
        'aplicar_ajuste' => true, // Por defecto aplica el ajuste de la forma de pago
    ];
    public float $totalAjustesFP = 0; // Total de ajustes de formas de pago

    // ==================== Selector de Cuotas ====================
    public bool $cuotasSelectorAbierto = false;
    public ?int $cuotaSeleccionadaId = null;
    public array $cuotasFormaPagoDisponibles = [];
    public bool $formaPagoPermiteCuotas = false;

    // ==================== Saldo a Favor y Anticipos ====================
    public float $saldoFavorDisponible = 0;
    public float $saldoFavorAUsar = 0; // Cuánto del saldo a favor se usará para pagar
    public bool $esAnticipo = false;
    public float $montoExcedente = 0; // Monto que irá a saldo a favor
    public float $saldoDeudorSucursal = 0; // Saldo deudor del cliente en la sucursal actual

    // ==================== Modal Cuenta Corriente ====================
    public bool $showCuentaCorrienteModal = false;
    public ?int $clienteIdCC = null;
    public ?Cliente $clienteCC = null;
    public array $movimientosCC = [];
    public float $saldoDeudorSucursalCC = 0; // Saldo deudor en sucursal para modal CC

    // ==================== Modal Reporte Antigüedad ====================
    public bool $showReporteAntiguedad = false;
    public array $reporteAntiguedad = [];

    // ==================== Servicio ====================
    protected CobroService $cobroService;

    public function boot(CobroService $cobroService): void
    {
        $this->cobroService = $cobroService;
    }

    /**
     * Reglas de validación para el cobro
     */
    protected function rules(): array
    {
        $rules = [
            'montoACobrar' => 'required|numeric|min:0.01',
        ];

        // Solo requerir desglosePagos si no hay saldo a favor aplicado
        if ($this->saldoFavorAUsar <= 0) {
            $rules['desglosePagos'] = 'required|array|min:1';
        }

        // Para cobros normales, requerir ventas seleccionadas
        if (!$this->esAnticipo) {
            $rules['ventasSeleccionadas'] = 'required|array|min:1';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'montoACobrar.required' => __('Ingrese el monto a cobrar'),
            'montoACobrar.min' => __('El monto debe ser mayor a 0'),
            'desglosePagos.required' => __('Agregue al menos una forma de pago'),
            'desglosePagos.min' => __('Agregue al menos una forma de pago'),
            'ventasSeleccionadas.required' => __('Seleccione al menos una venta'),
            'ventasSeleccionadas.min' => __('Seleccione al menos una venta'),
        ];
    }

    // ==================== Hooks de Actualización ====================

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEstado(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAntiguedad(): void
    {
        $this->resetPage();
    }

    public function updatedMontoACobrar($value): void
    {
        $this->montoACobrar = (float) $value;

        if (!$this->esAnticipo && $this->modoSeleccion === 'fifo') {
            $this->calcularDistribucionFIFO();
        }

        $this->recalcularMontoPendiente();
    }

    public function updatedDescuentoAplicado($value): void
    {
        $this->descuentoAplicado = (float) $value;

        // Limpiar formas de pago cuando se modifica el descuento
        $this->desglosePagos = [];
        $this->totalAjustesFP = 0;
        $this->recalcularMontoPendiente();
    }

    /**
     * Hook cuando cambia la forma de pago seleccionada (dot notation para array anidado)
     */
    public function updatedNuevoPago($value, $key): void
    {
        if ($key === 'forma_pago_id') {
            $this->cargarCuotasParaFormaPago($value);
        } elseif ($key === 'monto') {
            $this->onMontoChanged($value);
        }
    }

    /**
     * Carga las cuotas disponibles para una forma de pago (llamado desde vista o hook)
     */
    public function cargarCuotasParaFormaPago($formaPagoId = null): void
    {
        $this->cuotaSeleccionadaId = null;
        $this->cuotasSelectorAbierto = false;
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;
        $this->nuevoPago['cuotas'] = 1;

        $fpId = $formaPagoId ?? $this->nuevoPago['forma_pago_id'] ?? null;

        if (!$fpId) {
            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $fpId);
        if (!$fp) {
            return;
        }

        // Verificar si la forma de pago permite cuotas
        if ($fp['permite_cuotas'] && !empty($fp['cuotas'])) {
            $this->formaPagoPermiteCuotas = true;

            // Calcular el monto base para las cuotas
            $montoBase = !empty($this->nuevoPago['monto']) ? (float) $this->nuevoPago['monto'] : $this->montoPendienteDesglose;

            // Usar un monto mínimo para mostrar las cuotas (aunque sea con valores de ejemplo)
            if ($montoBase <= 0) {
                $montoBase = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado;
            }

            if ($montoBase > 0) {
                $this->cuotasFormaPagoDisponibles = collect($fp['cuotas'])
                    ->filter(fn($cuota) => ($cuota['cantidad'] ?? 0) > 0)
                    ->map(function ($cuota) use ($montoBase) {
                        $cantidad = (int) $cuota['cantidad'];
                        $recargo = (float) ($cuota['recargo'] ?? 0);
                        $totalConRecargo = round($montoBase * (1 + $recargo / 100), 2);
                        $valorCuota = round($totalConRecargo / $cantidad, 2);

                        return [
                            'id' => $cuota['id'] ?? $cantidad,
                            'cantidad_cuotas' => $cantidad,
                            'recargo_porcentaje' => $recargo,
                            'total_con_recargo' => $totalConRecargo,
                            'valor_cuota' => $valorCuota,
                        ];
                    })->values()->toArray();
            }
        }
    }

    /**
     * Procesa el cambio de monto
     */
    protected function onMontoChanged($value): void
    {
        // Recalcular cuotas si la forma de pago permite cuotas
        if ($this->formaPagoPermiteCuotas && $this->nuevoPago['forma_pago_id']) {
            $this->cargarCuotasParaFormaPago($this->nuevoPago['forma_pago_id']);
        }
    }

    /**
     * Toggle del selector de cuotas
     */
    public function toggleCuotasSelector(): void
    {
        $this->cuotasSelectorAbierto = !$this->cuotasSelectorAbierto;
    }

    /**
     * Hook cuando se selecciona una cuota
     */
    public function updatedCuotaSeleccionadaId($value): void
    {
        $this->cuotasSelectorAbierto = false;

        if (!$value) {
            $this->nuevoPago['cuotas'] = 1;
            $this->nuevoPago['cuota_id'] = null;
        } else {
            // Buscar la cuota seleccionada para obtener la cantidad correcta
            $cuotaSeleccionada = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $value);
            if ($cuotaSeleccionada) {
                $this->nuevoPago['cuotas'] = $cuotaSeleccionada['cantidad_cuotas'];
                $this->nuevoPago['cuota_id'] = (int) $value;
            } else {
                $this->nuevoPago['cuotas'] = 1;
                $this->nuevoPago['cuota_id'] = null;
            }
        }
    }

    /**
     * Recalcula el monto pendiente del desglose
     */
    protected function recalcularMontoPendiente(): void
    {
        $totalDeuda = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado;
        $totalPagado = collect($this->desglosePagos)->sum('monto_para_deuda') + $this->saldoFavorAUsar;
        $this->totalAjustesFP = collect($this->desglosePagos)->sum('monto_ajuste');
        $this->montoPendienteDesglose = round($totalDeuda - $totalPagado, 2);
    }

    /**
     * Aplica saldo a favor del cliente para pagar la deuda
     */
    public function aplicarSaldoFavor(?float $monto = null): void
    {
        if ($this->saldoFavorDisponible <= 0) {
            $this->dispatch('toast-error', message: __('No hay saldo a favor disponible'));
            return;
        }

        if ($this->esAnticipo) {
            $this->dispatch('toast-error', message: __('No se puede usar saldo a favor en un anticipo'));
            return;
        }

        // Calcular deuda pendiente (sin considerar saldo a favor actual para permitir recálculo)
        $totalDeuda = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado;
        $totalPagadoConFormas = collect($this->desglosePagos)->sum('monto_para_deuda');
        $deudaPendiente = max(0, $totalDeuda - $totalPagadoConFormas);

        if ($deudaPendiente <= 0) {
            $this->dispatch('toast-error', message: __('No hay deuda pendiente para aplicar'));
            return;
        }

        // Calcular el monto a aplicar (no puede exceder el disponible ni la deuda pendiente)
        $montoAAplicar = $monto ?? min($this->saldoFavorDisponible, $deudaPendiente);
        $montoAAplicar = min($montoAAplicar, $this->saldoFavorDisponible, $deudaPendiente);

        if ($montoAAplicar <= 0) {
            return;
        }

        $this->saldoFavorAUsar = round($montoAAplicar, 2);
        $this->recalcularMontoPendiente();

        $this->dispatch('toast-success', message: __('Saldo a favor aplicado: $') . number_format($montoAAplicar, 2, ',', '.'));
    }

    /**
     * Quita el saldo a favor aplicado
     */
    public function quitarSaldoFavor(): void
    {
        $this->saldoFavorAUsar = 0;
        $this->recalcularMontoPendiente();
    }

    // ==================== Filtros ====================

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene los clientes con cuenta corriente
     * Usa el nuevo sistema de movimientos_cuenta_corriente para calcular saldos
     */
    protected function getClientes()
    {
        $sucursalId = session('sucursal_id');

        $query = Cliente::where('tiene_cuenta_corriente', true)
            ->where('activo', true);

        // Búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                    ->orWhere('razon_social', 'like', '%' . $this->search . '%')
                    ->orWhere('cuit', 'like', '%' . $this->search . '%')
                    ->orWhere('telefono', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de estado de deuda - usa subquery con movimientos_cuenta_corriente
        if ($this->filterEstado === 'con_deuda') {
            $query->whereExists(function ($q) use ($sucursalId) {
                $q->select(DB::raw(1))
                    ->from('movimientos_cuenta_corriente')
                    ->whereColumn('movimientos_cuenta_corriente.cliente_id', 'clientes.id')
                    ->where('movimientos_cuenta_corriente.sucursal_id', $sucursalId)
                    ->where('movimientos_cuenta_corriente.estado', 'activo')
                    ->groupBy('movimientos_cuenta_corriente.cliente_id')
                    ->havingRaw('COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) > 0');
            });
        } elseif ($this->filterEstado === 'sin_deuda') {
            $query->whereNotExists(function ($q) use ($sucursalId) {
                $q->select(DB::raw(1))
                    ->from('movimientos_cuenta_corriente')
                    ->whereColumn('movimientos_cuenta_corriente.cliente_id', 'clientes.id')
                    ->where('movimientos_cuenta_corriente.sucursal_id', $sucursalId)
                    ->where('movimientos_cuenta_corriente.estado', 'activo')
                    ->groupBy('movimientos_cuenta_corriente.cliente_id')
                    ->havingRaw('COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) > 0');
            });
        }

        // Filtro de antigüedad - usa venta_pagos con saldo pendiente
        if ($this->filterAntiguedad !== 'all') {
            $query->whereHas('ventas', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId)
                    ->whereHas('pagos', function ($pq) {
                        $pq->where('es_cuenta_corriente', true)
                            ->where('saldo_pendiente', '>', 0)
                            ->where('estado', 'activo');
                    });

                $hoy = now()->startOfDay();
                switch ($this->filterAntiguedad) {
                    case '0_30':
                        $q->where('fecha', '>=', $hoy->copy()->subDays(30));
                        break;
                    case '31_60':
                        $q->whereBetween('fecha', [$hoy->copy()->subDays(60), $hoy->copy()->subDays(31)]);
                        break;
                    case '61_90':
                        $q->whereBetween('fecha', [$hoy->copy()->subDays(90), $hoy->copy()->subDays(61)]);
                        break;
                    case '90_mas':
                        $q->where('fecha', '<', $hoy->copy()->subDays(90));
                        break;
                }
            });
        }

        // Obtener clientes paginados
        $clientes = $query->orderBy('nombre')->paginate(15);

        // Agregar el saldo calculado a cada cliente
        $clienteIds = $clientes->pluck('id')->toArray();
        if (!empty($clienteIds)) {
            $saldos = MovimientoCuentaCorriente::select('cliente_id')
                ->selectRaw('COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor')
                ->where('sucursal_id', $sucursalId)
                ->where('estado', 'activo')
                ->whereIn('cliente_id', $clienteIds)
                ->groupBy('cliente_id')
                ->pluck('saldo_deudor', 'cliente_id');

            foreach ($clientes as $cliente) {
                $cliente->saldo_deudor_sucursal = (float) ($saldos[$cliente->id] ?? 0);
            }
        }

        return $clientes;
    }

    // ==================== Modal de Cobro ====================

    /**
     * Abre el modal de cobro para un cliente
     */
    public function abrirModalCobro(int $clienteId): void
    {
        $this->resetCobroForm();

        $this->clienteIdCobro = $clienteId;
        $this->clienteCobro = Cliente::find($clienteId);
        $sucursalId = session('sucursal_id');

        // Cargar ventas pendientes (ahora incluye venta_pago_id)
        $ventas = $this->cobroService->obtenerVentasPendientesFIFO($clienteId, $sucursalId);

        $this->ventasPendientes = $ventas->map(function ($venta) {
            $interesMora = $this->cobroService->calcularInteresMora($venta);
            $diasMora = $venta->fecha_vencimiento && now()->gt($venta->fecha_vencimiento)
                ? now()->diffInDays($venta->fecha_vencimiento)
                : 0;

            return [
                'id' => $venta->id,
                'venta_pago_id' => $venta->venta_pago_id ?? null,
                'numero' => $venta->numero,
                'fecha' => $venta->fecha->format('d/m/Y'),
                'fecha_vencimiento' => $venta->fecha_vencimiento?->format('d/m/Y'),
                'total' => (float) ($venta->monto_original ?? $venta->total_final),
                'saldo_pendiente' => (float) $venta->saldo_pendiente_cache,
                'interes_mora' => $interesMora,
                'dias_mora' => $diasMora,
                'descripcion_comprobantes' => $venta->descripcion_comprobantes ?? '',
                'seleccionada' => false,
                'monto_a_aplicar' => 0,
                'interes_a_aplicar' => 0,
            ];
        })->toArray();

        // Cargar formas de pago de la sucursal
        $this->cargarFormasPago();

        // Cargar saldo a favor disponible usando el nuevo sistema
        $this->saldoFavorDisponible = (float) \App\Models\MovimientoCuentaCorriente::calcularSaldoFavor($clienteId);
        $this->esAnticipo = false;

        // Calcular saldo deudor de la sucursal (suma de ventas pendientes cargadas)
        $this->saldoDeudorSucursal = collect($this->ventasPendientes)->sum('saldo_pendiente');

        $this->showCobroModal = true;
    }

    /**
     * Abre el modal para registrar un anticipo (cobro sin aplicación a ventas)
     */
    public function abrirModalAnticipo(int $clienteId): void
    {
        $this->resetCobroForm();

        $this->clienteIdCobro = $clienteId;
        $this->clienteCobro = Cliente::find($clienteId);
        $this->ventasPendientes = [];
        $this->ventasSeleccionadas = [];
        $this->esAnticipo = true;
        $this->modoSeleccion = 'manual'; // En anticipos no hay FIFO

        // Cargar formas de pago de la sucursal
        $this->cargarFormasPago();

        // En anticipos no se usa saldo a favor como pago
        $this->saldoFavorDisponible = 0;

        $this->showCobroModal = true;
    }

    /**
     * Carga las formas de pago disponibles en la sucursal
     */
    protected function cargarFormasPago(): void
    {
        $sucursalId = session('sucursal_id');

        $formasPago = FormaPago::whereHas('sucursales', function ($q) use ($sucursalId) {
            $q->where('sucursal_id', $sucursalId)
                ->where('activo', true);
        })
            ->with(['sucursales' => function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }, 'conceptoPago', 'cuotas'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $this->formasPagoSucursal = $formasPago->map(function ($fp) {
            $pivotData = $fp->sucursales->first()?->pivot;

            return [
                'id' => $fp->id,
                'nombre' => $fp->nombre,
                'codigo' => $fp->codigo,
                'concepto_pago_id' => $fp->concepto_pago_id,
                'ajuste_porcentaje' => (float) ($pivotData?->ajuste_porcentaje ?? $fp->ajuste_porcentaje ?? 0),
                'permite_vuelto' => $fp->permite_vuelto ?? false,
                'permite_cuotas' => $fp->permite_cuotas ?? false,
                'afecta_caja' => $fp->afecta_caja ?? false,
                'cuotas' => $fp->cuotas->filter(fn($c) => $c->activo)->map(fn($c) => [
                    'id' => $c->id,
                    'cantidad' => $c->cantidad_cuotas,
                    'recargo' => (float) $c->recargo_porcentaje,
                ])->values()->toArray(),
            ];
        })->toArray();
    }

    /**
     * Cierra el modal de cobro
     */
    public function cerrarModalCobro(): void
    {
        $this->showCobroModal = false;
        $this->resetCobroForm();
    }

    /**
     * Resetea el formulario de cobro
     */
    protected function resetCobroForm(): void
    {
        $this->clienteIdCobro = null;
        $this->clienteCobro = null;
        $this->ventasPendientes = [];
        $this->ventasSeleccionadas = [];
        $this->modoSeleccion = 'fifo';
        $this->montoACobrar = 0;
        $this->interesTotal = 0;
        $this->descuentoAplicado = 0;
        $this->observaciones = '';
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = 0;
        $this->nuevoPago = ['forma_pago_id' => '', 'monto' => '', 'cuotas' => 1, 'cuota_id' => null, 'aplicar_ajuste' => true];
        $this->saldoFavorDisponible = 0;
        $this->saldoFavorAUsar = 0;
        $this->esAnticipo = false;
        $this->montoExcedente = 0;
        $this->saldoDeudorSucursal = 0;
        $this->resetValidation();
    }

    /**
     * Alterna el modo de selección FIFO/Manual
     */
    public function toggleModoSeleccion(): void
    {
        $this->modoSeleccion = $this->modoSeleccion === 'fifo' ? 'manual' : 'fifo';

        if ($this->modoSeleccion === 'fifo' && $this->montoACobrar > 0) {
            $this->calcularDistribucionFIFO();
        } else {
            // Resetear selección manual
            foreach ($this->ventasPendientes as $key => $venta) {
                $this->ventasPendientes[$key]['seleccionada'] = false;
                $this->ventasPendientes[$key]['monto_a_aplicar'] = 0;
                $this->ventasPendientes[$key]['interes_a_aplicar'] = 0;
            }
            $this->ventasSeleccionadas = [];
            $this->interesTotal = 0;
        }
    }

    /**
     * Calcula la distribución FIFO del monto
     */
    public function calcularDistribucionFIFO(): void
    {
        if ($this->montoACobrar <= 0) {
            return;
        }

        $ventas = collect($this->ventasPendientes)->map(function ($v) {
            return (object) [
                'id' => $v['id'],
                'venta_pago_id' => $v['venta_pago_id'] ?? null,
                'numero' => $v['numero'],
                'fecha' => $v['fecha'],
                'saldo_pendiente_cache' => $v['saldo_pendiente'],
                'fecha_vencimiento' => $v['fecha_vencimiento'],
                'cliente' => $this->clienteCobro,
            ];
        });

        $distribucion = $this->cobroService->distribuirMontoFIFO($this->montoACobrar, $ventas);

        // Actualizar ventas pendientes con la distribución
        $this->interesTotal = 0;
        $this->ventasSeleccionadas = [];

        foreach ($this->ventasPendientes as $key => $venta) {
            $dist = collect($distribucion)->firstWhere('venta_id', $venta['id']);

            if ($dist && $dist['monto_aplicado'] > 0) {
                $this->ventasPendientes[$key]['seleccionada'] = true;
                $this->ventasPendientes[$key]['monto_a_aplicar'] = $dist['monto_aplicado'];
                $this->ventasPendientes[$key]['interes_a_aplicar'] = $dist['interes_aplicado'];
                $this->interesTotal += $dist['interes_aplicado'];

                $this->ventasSeleccionadas[] = [
                    'venta_id' => $venta['id'],
                    'venta_pago_id' => $venta['venta_pago_id'] ?? $dist['venta_pago_id'] ?? null,
                    'monto_aplicado' => $dist['monto_aplicado'],
                    'interes_aplicado' => $dist['interes_aplicado'],
                ];
            } else {
                $this->ventasPendientes[$key]['seleccionada'] = false;
                $this->ventasPendientes[$key]['monto_a_aplicar'] = 0;
                $this->ventasPendientes[$key]['interes_a_aplicar'] = 0;
            }
        }

        // Actualizar monto pendiente de desglose
        $this->montoPendienteDesglose = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado - collect($this->desglosePagos)->sum('monto_base') - $this->saldoFavorAUsar;
    }

    /**
     * Selecciona/deselecciona una venta manualmente
     */
    public function toggleVentaSeleccion(int $index): void
    {
        if ($this->modoSeleccion !== 'manual') {
            return;
        }

        $this->ventasPendientes[$index]['seleccionada'] = !$this->ventasPendientes[$index]['seleccionada'];

        if ($this->ventasPendientes[$index]['seleccionada']) {
            // Al seleccionar, aplicar el saldo completo
            $this->ventasPendientes[$index]['monto_a_aplicar'] = $this->ventasPendientes[$index]['saldo_pendiente'];
            $this->ventasPendientes[$index]['interes_a_aplicar'] = $this->ventasPendientes[$index]['interes_mora'];
        } else {
            $this->ventasPendientes[$index]['monto_a_aplicar'] = 0;
            $this->ventasPendientes[$index]['interes_a_aplicar'] = 0;
        }

        $this->recalcularSeleccionManual();
    }

    /**
     * Actualiza el monto a aplicar de una venta manual
     */
    public function actualizarMontoVenta(int $index, $monto): void
    {
        if ($this->modoSeleccion !== 'manual') {
            return;
        }

        $monto = (float) $monto;
        $saldoPendiente = $this->ventasPendientes[$index]['saldo_pendiente'];
        $interesMora = $this->ventasPendientes[$index]['interes_mora'];

        $this->ventasPendientes[$index]['monto_a_aplicar'] = min($monto, $saldoPendiente);
        $this->ventasPendientes[$index]['seleccionada'] = $monto > 0;

        // Calcular interés proporcional
        if ($saldoPendiente > 0 && $monto > 0) {
            $this->ventasPendientes[$index]['interes_a_aplicar'] = round($interesMora * ($monto / $saldoPendiente), 2);
        } else {
            $this->ventasPendientes[$index]['interes_a_aplicar'] = 0;
        }

        $this->recalcularSeleccionManual();
    }

    /**
     * Recalcula totales en modo manual
     */
    protected function recalcularSeleccionManual(): void
    {
        $this->ventasSeleccionadas = [];
        $this->montoACobrar = 0;
        $this->interesTotal = 0;

        foreach ($this->ventasPendientes as $venta) {
            if ($venta['seleccionada'] && $venta['monto_a_aplicar'] > 0) {
                $this->ventasSeleccionadas[] = [
                    'venta_id' => $venta['id'],
                    'venta_pago_id' => $venta['venta_pago_id'] ?? null,
                    'monto_aplicado' => $venta['monto_a_aplicar'],
                    'interes_aplicado' => $venta['interes_a_aplicar'],
                ];
                $this->montoACobrar += $venta['monto_a_aplicar'];
                $this->interesTotal += $venta['interes_a_aplicar'];
            }
        }

        $this->montoPendienteDesglose = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado - collect($this->desglosePagos)->sum('monto_base') - $this->saldoFavorAUsar;
    }

    // ==================== Desglose de Pagos ====================

    /**
     * Agrega un pago al desglose
     */
    public function agregarAlDesglose(): void
    {
        if (!$this->nuevoPago['forma_pago_id']) {
            $this->dispatch('toast-error', message: __('Seleccione una forma de pago'));
            return;
        }

        $monto = $this->nuevoPago['monto'];
        if ($monto === null || $monto === '' || (float) $monto <= 0) {
            // En anticipos, si no hay monto específico, usar el monto del anticipo
            // En cobros, usar el monto pendiente
            $monto = $this->esAnticipo ? $this->montoACobrar : $this->montoPendienteDesglose;
        }
        $monto = (float) $monto;

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese un monto válido'));
            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);

        if (!$fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));
            return;
        }

        // En ANTICIPOS: No aplicar ajustes de forma de pago
        // El monto que paga = monto que va a saldo a favor
        if ($this->esAnticipo) {
            $this->desglosePagos[] = [
                'forma_pago_id' => $fp['id'],
                'nombre' => $fp['nombre'],
                'concepto_pago_id' => $fp['concepto_pago_id'],
                'monto_base' => $monto,
                'ajuste_porcentaje' => 0,
                'ajuste_original' => $fp['ajuste_porcentaje'],
                'monto_ajuste' => 0,
                'monto_recargo_cuotas' => 0,
                'monto_final' => $monto, // Sin ajustes
                'cuotas' => 1,
                'recargo_cuotas' => 0,
                'monto_recibido' => $fp['permite_vuelto'] ? $monto : null,
                'vuelto' => 0,
                'permite_vuelto' => $fp['permite_vuelto'],
                'permite_cuotas' => false, // Sin cuotas en anticipos
                'cuotas_disponibles' => [],
                'afecta_caja' => $fp['afecta_caja'],
                'es_excedente' => false,
            ];

            $this->totalAjustesFP = 0;
            $this->montoPendienteDesglose = round($this->montoACobrar - collect($this->desglosePagos)->sum('monto_base'), 2);
            $this->resetNuevoPago();
            return;
        }

        // En COBROS: Comportamiento híbrido según si hay excedente o no
        $aplicarAjuste = $this->nuevoPago['aplicar_ajuste'] ?? true;
        $ajusteOriginal = $fp['ajuste_porcentaje'];
        $ajuste = $aplicarAjuste ? $ajusteOriginal : 0;

        // Factor de ajuste: 0.9 para 10% descuento, 1.1 para 10% recargo
        $factorAjuste = 1 + ($ajuste / 100);
        $deudaPendiente = max(0, $this->montoPendienteDesglose);

        // Determinar si hay excedente (monto ingresado > deuda pendiente)
        $hayExcedente = $monto > $deudaPendiente;

        if ($hayExcedente) {
            // CASO CON EXCEDENTE: El monto es lo que el cliente ENTREGA
            // Ejemplo: Deuda $100, entrega $2000 con 10% dto → $90 cubren deuda, $1910 excedente
            $deudaCubierta = $deudaPendiente;
            $montoPagadoParaDeuda = round($deudaCubierta * $factorAjuste, 2);
            $montoExcedente = round($monto - $montoPagadoParaDeuda, 2);
            $montoFinalBase = $monto; // Lo que el cliente entrega
        } else {
            // CASO SIN EXCEDENTE: El monto es el valor nominal de deuda a cubrir
            // Ejemplo: Deuda $100, pone $100 con 10% dto → cubre $100, paga $90
            $deudaCubierta = $monto;
            $montoPagadoParaDeuda = round($deudaCubierta * $factorAjuste, 2);
            $montoExcedente = 0;
            $montoFinalBase = $montoPagadoParaDeuda; // Lo que realmente paga
        }

        // El ajuste en pesos (para mostrar)
        $montoAjuste = round($montoPagadoParaDeuda - $deudaCubierta, 2);

        // Calcular cuotas si aplica (solo sobre la parte de deuda)
        $cuotas = (int) ($this->nuevoPago['cuotas'] ?? 1);
        $cuotaId = $this->nuevoPago['cuota_id'] ?? null;
        $recargoCuotas = 0;
        $montoRecargoCuotas = 0;

        if ($cuotas > 1 && $fp['permite_cuotas'] && $deudaCubierta > 0) {
            $cuotaConfig = null;
            if ($cuotaId) {
                $cuotaConfig = collect($fp['cuotas'])->firstWhere('id', $cuotaId);
            }
            if (!$cuotaConfig) {
                $cuotaConfig = collect($fp['cuotas'])->firstWhere('cantidad', $cuotas);
            }

            if ($cuotaConfig) {
                $recargoCuotas = (float) $cuotaConfig['recargo'];
                $montoRecargoCuotas = round($montoPagadoParaDeuda * ($recargoCuotas / 100), 2);
            }
        }

        // Monto final
        $montoFinal = round($montoFinalBase + $montoRecargoCuotas, 2);

        $this->desglosePagos[] = [
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'concepto_pago_id' => $fp['concepto_pago_id'],
            'monto_base' => $monto, // Lo que el cliente ENTREGA
            'monto_para_deuda' => $deudaCubierta, // Deuda nominal cubierta (para tracking)
            'monto_pagado_para_deuda' => $montoPagadoParaDeuda, // Dinero usado para la deuda
            'monto_excedente' => $montoExcedente, // Lo que sobra → saldo a favor
            'ajuste_porcentaje' => $ajuste,
            'ajuste_original' => $ajusteOriginal,
            'monto_ajuste' => $montoAjuste,
            'monto_recargo_cuotas' => $montoRecargoCuotas,
            'monto_final' => $montoFinal, // Lo que el cliente paga en total
            'cuotas' => $cuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $fp['permite_vuelto'] ? $montoFinal : null,
            'vuelto' => 0,
            'permite_vuelto' => $fp['permite_vuelto'],
            'permite_cuotas' => $fp['permite_cuotas'],
            'cuotas_disponibles' => $fp['cuotas'],
            'afecta_caja' => $fp['afecta_caja'],
        ];

        // Recalcular totales
        $this->recalcularTotalesDesglose();
        $this->resetNuevoPago();
    }

    /**
     * Recalcula los totales del desglose considerando ajustes proporcionales
     *
     * Comportamiento híbrido:
     * - Si monto_base <= deuda restante: monto_base es valor nominal de deuda a cubrir
     * - Si monto_base > deuda restante: monto_base es lo que el cliente ENTREGA
     */
    protected function recalcularTotalesDesglose(): void
    {
        $totalDeuda = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado;

        // Recalcular cada pago con la proporción correcta
        $deudaRestante = $totalDeuda;

        foreach ($this->desglosePagos as $index => $pago) {
            $montoBase = $pago['monto_base'];
            $ajuste = $pago['ajuste_porcentaje'];

            // Factor de ajuste: 0.9 para 10% descuento, 1.1 para 10% recargo
            $factorAjuste = 1 + ($ajuste / 100);

            // Determinar si hay excedente
            $hayExcedente = $montoBase > $deudaRestante;

            if ($hayExcedente) {
                // CASO CON EXCEDENTE: monto_base es lo que el cliente ENTREGA
                $deudaCubierta = max(0, $deudaRestante);
                $montoPagadoParaDeuda = round($deudaCubierta * $factorAjuste, 2);
                $montoExcedente = round($montoBase - $montoPagadoParaDeuda, 2);
                $montoFinalBase = $montoBase;
            } else {
                // CASO SIN EXCEDENTE: monto_base es valor nominal de deuda
                $deudaCubierta = $montoBase;
                $montoPagadoParaDeuda = round($deudaCubierta * $factorAjuste, 2);
                $montoExcedente = 0;
                $montoFinalBase = $montoPagadoParaDeuda;
            }

            // Ajuste en pesos (para mostrar)
            $montoAjuste = round($montoPagadoParaDeuda - $deudaCubierta, 2);

            // Actualizar deuda restante
            $deudaRestante -= $deudaCubierta;

            // Recalcular recargo cuotas
            $montoRecargoCuotas = 0;
            if (($pago['cuotas'] ?? 1) > 1 && ($pago['recargo_cuotas'] ?? 0) > 0) {
                $montoRecargoCuotas = round($montoPagadoParaDeuda * ($pago['recargo_cuotas'] / 100), 2);
            }

            // Monto final
            $montoFinal = round($montoFinalBase + $montoRecargoCuotas, 2);

            $this->desglosePagos[$index]['monto_para_deuda'] = $deudaCubierta;
            $this->desglosePagos[$index]['monto_pagado_para_deuda'] = $montoPagadoParaDeuda;
            $this->desglosePagos[$index]['monto_excedente'] = $montoExcedente;
            $this->desglosePagos[$index]['monto_ajuste'] = $montoAjuste;
            $this->desglosePagos[$index]['monto_recargo_cuotas'] = $montoRecargoCuotas;
            $this->desglosePagos[$index]['monto_final'] = $montoFinal;
        }

        // Calcular totales
        $this->totalAjustesFP = collect($this->desglosePagos)->sum(function ($pago) {
            return ($pago['monto_ajuste'] ?? 0) + ($pago['monto_recargo_cuotas'] ?? 0);
        });

        $this->montoExcedente = collect($this->desglosePagos)->sum('monto_excedente');

        // Considerar saldo a favor aplicado en el pendiente
        $totalPagado = collect($this->desglosePagos)->sum('monto_para_deuda') + $this->saldoFavorAUsar;
        $this->montoPendienteDesglose = round($totalDeuda - $totalPagado, 2);
    }

    /**
     * Elimina un pago del desglose
     */
    public function quitarDelDesglose(int $index): void
    {
        if (!isset($this->desglosePagos[$index])) {
            return;
        }

        unset($this->desglosePagos[$index]);
        $this->desglosePagos = array_values($this->desglosePagos);

        // Recalcular totales con la lógica de proporcionalidad
        if ($this->esAnticipo) {
            $this->totalAjustesFP = 0;
            $this->montoPendienteDesglose = round($this->montoACobrar - collect($this->desglosePagos)->sum('monto_base'), 2);
        } else {
            $this->recalcularTotalesDesglose();
        }
    }

    /**
     * Asigna el monto pendiente al nuevo pago y lo agrega si hay forma de pago seleccionada
     */
    public function asignarMontoPendiente(): void
    {
        $this->nuevoPago['monto'] = $this->montoPendienteDesglose;

        // Si ya hay una forma de pago seleccionada, agregar directamente
        if ($this->nuevoPago['forma_pago_id']) {
            $this->agregarAlDesglose();
        }
    }

    /**
     * Resetea el formulario de nuevo pago
     */
    protected function resetNuevoPago(): void
    {
        $this->nuevoPago = [
            'forma_pago_id' => '',
            'monto' => '',
            'cuotas' => 1,
            'cuota_id' => null,
            'aplicar_ajuste' => true,
        ];
        $this->cuotaSeleccionadaId = null;
        $this->cuotasSelectorAbierto = false;
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;
    }

    // ==================== Procesar Cobro ====================

    /**
     * Procesa el cobro
     */
    public function procesarCobro(): void
    {
        // Validaciones básicas
        if (!$this->esAnticipo && empty($this->ventasSeleccionadas)) {
            $this->dispatch('toast-error', message: __('Seleccione al menos una venta'));
            return;
        }

        // Validar que haya al menos una forma de pago O saldo a favor aplicado
        if (empty($this->desglosePagos) && $this->saldoFavorAUsar <= 0) {
            $this->dispatch('toast-error', message: __('Agregue al menos una forma de pago o aplique saldo a favor'));
            return;
        }

        if ($this->montoPendienteDesglose > 0.01) {
            $this->dispatch('toast-error', message: __('Falta completar el monto del cobro'));
            return;
        }

        // Para anticipos, el monto debe ser mayor a 0
        if ($this->esAnticipo && $this->montoACobrar <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese el monto del anticipo'));
            return;
        }

        try {
            $data = [
                'sucursal_id' => session('sucursal_id'),
                'cliente_id' => $this->clienteIdCobro,
                'caja_id' => session('caja_id'),
                'observaciones' => $this->observaciones ?: null,
                'descuento_aplicado' => $this->descuentoAplicado,
                'saldo_favor_usado' => $this->saldoFavorAUsar,
            ];

            if ($this->esAnticipo) {
                $cobro = $this->cobroService->registrarAnticipo(
                    $data,
                    $this->desglosePagos
                );
                $this->dispatch('toast-success', message: __('Anticipo registrado correctamente'));
            } else {
                $cobro = $this->cobroService->registrarCobro(
                    $data,
                    $this->ventasSeleccionadas,
                    $this->desglosePagos
                );
                $this->dispatch('toast-success', message: __('Cobro registrado correctamente'));
            }

            // Disparar evento para impresión
            $this->dispatch('cobro-registrado', cobroId: $cobro->id);

            $this->cerrarModalCobro();

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: __('Error al registrar cobro: ') . $e->getMessage());
        }
    }

    // ==================== Modal Cuenta Corriente ====================

    /**
     * Muestra el modal de cuenta corriente
     */
    public function verCuentaCorriente(int $clienteId): void
    {
        $this->clienteIdCC = $clienteId;
        $this->clienteCC = Cliente::find($clienteId);

        $sucursalId = session('sucursal_id');

        // Usar el nuevo sistema de cuenta corriente unificada
        $movimientos = $this->cobroService->obtenerMovimientosCuentaCorriente($clienteId, $sucursalId);

        // Los movimientos ya vienen con saldo calculado y en orden de más reciente a más antiguo
        $this->movimientosCC = $movimientos->map(function ($mov) {
            return [
                'id' => $mov['id'],
                'tipo' => $mov['tipo'],
                'fecha' => $mov['fecha'],
                'hora' => $mov['hora'] ?? null,
                'descripcion' => $mov['concepto'],
                'descripcion_comprobantes' => $mov['descripcion_comprobantes'] ?? null,
                'debe' => $mov['debe'],
                'haber' => $mov['haber'],
                'saldo' => $mov['saldo_deudor'],
                'saldo_favor' => $mov['saldo_favor'] ?? 0,
                'venta_id' => $mov['venta_id'] ?? null,
                'venta_numero' => $mov['venta_numero'] ?? null,
                'cobro_id' => $mov['cobro_id'] ?? null,
                'cobro_numero' => $mov['cobro_numero'] ?? null,
                'es_anulacion' => $mov['es_anulacion'] ?? false,
                'movimiento_anulado_id' => $mov['movimiento_anulado_id'] ?? null,
                'anulado' => !empty($mov['anulado_por_movimiento_id']),
            ];
        })->toArray();

        // Obtener saldos usando el nuevo sistema
        $saldos = MovimientoCuentaCorriente::obtenerSaldos($clienteId, $sucursalId);
        $this->saldoDeudorSucursalCC = (float) $saldos['saldo_deudor'];

        // Actualizar también el saldo a favor desde el nuevo sistema
        if ($this->clienteCC) {
            $this->clienteCC->saldo_a_favor_cache = (float) $saldos['saldo_favor'];
        }

        $this->showCuentaCorrienteModal = true;
    }

    /**
     * Cierra el modal de cuenta corriente
     */
    public function cerrarCuentaCorriente(): void
    {
        $this->showCuentaCorrienteModal = false;
        $this->clienteIdCC = null;
        $this->clienteCC = null;
        $this->movimientosCC = [];
        $this->saldoDeudorSucursalCC = 0;
    }

    /**
     * Abre modal de cobro desde cuenta corriente (cierra CC primero)
     */
    public function abrirCobroDesdeCuentaCorriente(): void
    {
        $clienteId = $this->clienteIdCC;
        $this->cerrarCuentaCorriente();
        $this->abrirModalCobro($clienteId);
    }

    /**
     * Abre modal de anticipo desde cuenta corriente (cierra CC primero)
     */
    public function abrirAnticipoDesdeCuentaCorriente(): void
    {
        $clienteId = $this->clienteIdCC;
        $this->cerrarCuentaCorriente();
        $this->abrirModalAnticipo($clienteId);
    }

    /**
     * Anula un cobro/recibo creando contraasientos
     */
    public function anularCobro(int $cobroId): void
    {
        try {
            $cobro = Cobro::find($cobroId);

            if (!$cobro) {
                $this->dispatch('toast-error', message: __('Cobro no encontrado'));
                return;
            }

            if ($cobro->estado === 'anulado') {
                $this->dispatch('toast-error', message: __('Este cobro ya está anulado'));
                return;
            }

            // Anular el cobro usando el servicio
            $this->cobroService->anularCobro($cobroId, 'Anulación desde cuenta corriente');

            $this->dispatch('toast-success', message: __('Recibo anulado correctamente'));

            // Refrescar la cuenta corriente
            if ($this->clienteIdCC) {
                $this->verCuentaCorriente($this->clienteIdCC);
            }

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: __('Error al anular recibo: ') . $e->getMessage());
        }
    }

    // ==================== Cambio de Sucursal ====================

    /**
     * Hook llamado cuando cambia la sucursal (desde SucursalAware trait)
     * Cierra modales abiertos y refresca la lista
     */
    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        // Cerrar modales abiertos
        if ($this->showCobroModal) {
            $this->cerrarModalCobro();
        }

        if ($this->showCuentaCorrienteModal) {
            $this->cerrarCuentaCorriente();
        }

        if ($this->showReporteAntiguedad) {
            $this->cerrarReporteAntiguedad();
        }

        // El trait ya resetea la paginación automáticamente
        // La vista se actualizará con los datos de la nueva sucursal
    }

    // ==================== Reporte de Antigüedad ====================

    /**
     * Genera y muestra el reporte de antigüedad
     */
    public function generarReporteAntiguedad(): void
    {
        $sucursalId = session('sucursal_id');
        $this->reporteAntiguedad = $this->cobroService->generarReporteAntiguedad($sucursalId);
        $this->showReporteAntiguedad = true;
    }

    /**
     * Cierra el modal de reporte de antigüedad
     */
    public function cerrarReporteAntiguedad(): void
    {
        $this->showReporteAntiguedad = false;
        $this->reporteAntiguedad = [];
    }

    // ==================== Impresión ====================

    /**
     * Dispara el evento para imprimir un recibo
     */
    public function imprimirRecibo(int $cobroId): void
    {
        $this->dispatch('imprimir-recibo-cobro', cobroId: $cobroId);
    }

    // ==================== Render ====================

    public function render()
    {
        return view('livewire.clientes.gestionar-cobranzas', [
            'clientes' => $this->getClientes(),
        ]);
    }
}
