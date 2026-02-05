<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FormaPago;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\CobroService;
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
        'aplicar_ajuste' => true, // Por defecto aplica el ajuste de la forma de pago
    ];
    public float $totalAjustesFP = 0; // Total de ajustes de formas de pago

    // ==================== Selector de Cuotas ====================
    public bool $cuotasSelectorAbierto = false;
    public ?int $cuotaSeleccionadaId = null;
    public array $cuotasFormaPagoDisponibles = [];
    public bool $formaPagoPermiteCuotas = false;

    // ==================== Modal Cuenta Corriente ====================
    public bool $showCuentaCorrienteModal = false;
    public ?int $clienteIdCC = null;
    public ?Cliente $clienteCC = null;
    public array $movimientosCC = [];

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
        return [
            'montoACobrar' => 'required|numeric|min:0.01',
            'desglosePagos' => 'required|array|min:1',
            'ventasSeleccionadas' => 'required|array|min:1',
        ];
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

        if ($this->modoSeleccion === 'fifo') {
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
        } else {
            $this->nuevoPago['cuotas'] = (int) $value;
        }
    }

    /**
     * Recalcula el monto pendiente del desglose
     */
    protected function recalcularMontoPendiente(): void
    {
        $totalBase = collect($this->desglosePagos)->sum('monto_base');
        $this->totalAjustesFP = collect($this->desglosePagos)->sum('monto_ajuste');
        $this->montoPendienteDesglose = round($this->montoACobrar + $this->interesTotal - $this->descuentoAplicado - $totalBase, 2);
    }

    // ==================== Filtros ====================

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene los clientes con cuenta corriente
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

        // Filtro de estado de deuda
        if ($this->filterEstado === 'con_deuda') {
            $query->where('saldo_deudor_cache', '>', 0);
        } elseif ($this->filterEstado === 'sin_deuda') {
            $query->where('saldo_deudor_cache', '<=', 0);
        }

        // Filtro de antigüedad
        if ($this->filterAntiguedad !== 'all') {
            $query->whereHas('ventas', function ($q) use ($sucursalId) {
                $q->where('es_cuenta_corriente', true)
                    ->where('saldo_pendiente_cache', '>', 0)
                    ->where('estado', 'completada');

                if ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                }

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

        return $query->orderByDesc('saldo_deudor_cache')
            ->orderBy('nombre')
            ->paginate(15);
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

        // Cargar ventas pendientes
        $ventas = $this->cobroService->obtenerVentasPendientesFIFO($clienteId, $sucursalId);

        $this->ventasPendientes = $ventas->map(function ($venta) {
            $interesMora = $this->cobroService->calcularInteresMora($venta);
            $diasMora = $venta->fecha_vencimiento && now()->gt($venta->fecha_vencimiento)
                ? now()->diffInDays($venta->fecha_vencimiento)
                : 0;

            return [
                'id' => $venta->id,
                'numero' => $venta->numero,
                'fecha' => $venta->fecha->format('d/m/Y'),
                'fecha_vencimiento' => $venta->fecha_vencimiento?->format('d/m/Y'),
                'total' => (float) $venta->total_final,
                'saldo_pendiente' => (float) $venta->saldo_pendiente_cache,
                'interes_mora' => $interesMora,
                'dias_mora' => $diasMora,
                'seleccionada' => false,
                'monto_a_aplicar' => 0,
                'interes_a_aplicar' => 0,
            ];
        })->toArray();

        // Cargar formas de pago de la sucursal
        $this->cargarFormasPago();

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
        $this->nuevoPago = ['forma_pago_id' => '', 'monto' => '', 'cuotas' => 1];
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
        $this->montoPendienteDesglose = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado - collect($this->desglosePagos)->sum('monto_base');
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
                    'monto_aplicado' => $venta['monto_a_aplicar'],
                    'interes_aplicado' => $venta['interes_a_aplicar'],
                ];
                $this->montoACobrar += $venta['monto_a_aplicar'];
                $this->interesTotal += $venta['interes_a_aplicar'];
            }
        }

        $this->montoPendienteDesglose = $this->montoACobrar + $this->interesTotal - $this->descuentoAplicado - collect($this->desglosePagos)->sum('monto_base');
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
            $monto = $this->montoPendienteDesglose;
        }
        $monto = (float) $monto;

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: __('No hay monto pendiente para agregar'));
            return;
        }

        if ($monto > $this->montoPendienteDesglose + 0.01) {
            $this->dispatch('toast-error', message: __('El monto excede el pendiente'));
            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        if (!$fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));
            return;
        }

        // Verificar si se aplica el ajuste de la forma de pago
        $aplicarAjuste = $this->nuevoPago['aplicar_ajuste'] ?? true;

        // Calcular ajuste (solo si está habilitado)
        $ajusteOriginal = $fp['ajuste_porcentaje'];
        $ajuste = $aplicarAjuste ? $ajusteOriginal : 0;
        $montoAjuste = round($monto * ($ajuste / 100), 2);
        $montoConAjuste = round($monto + $montoAjuste, 2);

        // Calcular cuotas si aplica
        $cuotas = (int) ($this->nuevoPago['cuotas'] ?? 1);
        $recargoCuotas = 0;
        $montoFinal = $montoConAjuste;

        if ($cuotas > 1 && $fp['permite_cuotas']) {
            $cuotaConfig = collect($fp['cuotas'])->firstWhere('cantidad', $cuotas);
            if ($cuotaConfig) {
                $recargoCuotas = $cuotaConfig['recargo'];
                $montoRecargoCuotas = round($montoConAjuste * ($recargoCuotas / 100), 2);
                $montoFinal = round($montoConAjuste + $montoRecargoCuotas, 2);
            }
        }

        $this->desglosePagos[] = [
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'concepto_pago_id' => $fp['concepto_pago_id'],
            'monto_base' => $monto,
            'ajuste_porcentaje' => $ajuste,
            'ajuste_original' => $ajusteOriginal, // Guardamos el original para mostrar
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
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
        $this->totalAjustesFP = collect($this->desglosePagos)->sum('monto_ajuste');
        $this->montoPendienteDesglose = round($this->montoPendienteDesglose - $monto, 2);
        $this->resetNuevoPago();
    }

    /**
     * Elimina un pago del desglose
     */
    public function quitarDelDesglose(int $index): void
    {
        if (!isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = $this->desglosePagos[$index];
        $this->montoPendienteDesglose = round($this->montoPendienteDesglose + $pago['monto_base'], 2);

        unset($this->desglosePagos[$index]);
        $this->desglosePagos = array_values($this->desglosePagos);

        // Recalcular total de ajustes
        $this->totalAjustesFP = collect($this->desglosePagos)->sum('monto_ajuste');
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
        if (empty($this->ventasSeleccionadas)) {
            $this->dispatch('toast-error', message: __('Seleccione al menos una venta'));
            return;
        }

        if (empty($this->desglosePagos)) {
            $this->dispatch('toast-error', message: __('Agregue al menos una forma de pago'));
            return;
        }

        if ($this->montoPendienteDesglose > 0.01) {
            $this->dispatch('toast-error', message: __('Falta completar el monto del cobro'));
            return;
        }

        try {
            $data = [
                'sucursal_id' => session('sucursal_id'),
                'cliente_id' => $this->clienteIdCobro,
                'caja_id' => session('caja_id'),
                'observaciones' => $this->observaciones ?: null,
                'descuento_aplicado' => $this->descuentoAplicado,
            ];

            $cobro = $this->cobroService->registrarCobro(
                $data,
                $this->ventasSeleccionadas,
                $this->desglosePagos
            );

            $this->dispatch('toast-success', message: __('Cobro registrado correctamente'));

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
        $movimientos = $this->cobroService->obtenerMovimientosCuentaCorriente($clienteId, $sucursalId);

        // Calcular saldo acumulado
        $saldoAcumulado = 0;
        $this->movimientosCC = $movimientos->reverse()->map(function ($mov) use (&$saldoAcumulado) {
            $saldoAcumulado += $mov['debe'] - $mov['haber'];
            $mov['saldo'] = $saldoAcumulado;
            return $mov;
        })->reverse()->values()->toArray();

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
