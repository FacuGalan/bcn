<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaPromocion;
use App\Models\Compra;
use App\Models\Cobro;
use App\Models\Caja;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\ComprobanteFiscal;
use App\Models\FormaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire: Dashboard de Sucursal
 *
 * Dashboard completo con métricas de:
 * - Ventas (día, semana, mes)
 * - Formas de pago usadas
 * - Comprobantes fiscales emitidos
 * - Promociones aplicadas
 * - Cobros de cuenta corriente
 * - Estado de cajas
 * - Alertas de stock
 */
class DashboardSucursal extends Component
{
    public $sucursalSeleccionada = null;
    public $periodoSeleccionado = 'hoy'; // hoy, semana, mes

    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function mount()
    {
        $this->sucursalSeleccionada = sucursal_activa() ?? Sucursal::activas()->first()->id ?? 1;
    }

    public function handleSucursalChanged($sucursalId, $sucursalNombre = null)
    {
        $this->sucursalSeleccionada = $sucursalId;
    }

    public function cambiarPeriodo($periodo)
    {
        $this->periodoSeleccionado = $periodo;
    }

    private function getRangoFechas(): array
    {
        $hoy = Carbon::today();

        switch ($this->periodoSeleccionado) {
            case 'semana':
                return [
                    'desde' => $hoy->copy()->startOfWeek(),
                    'hasta' => $hoy->copy()->endOfDay(),
                    'label' => __('Esta semana'),
                ];
            case 'mes':
                return [
                    'desde' => $hoy->copy()->startOfMonth(),
                    'hasta' => $hoy->copy()->endOfDay(),
                    'label' => __('Este mes'),
                ];
            default: // hoy
                return [
                    'desde' => $hoy->copy()->startOfDay(),
                    'hasta' => $hoy->copy()->endOfDay(),
                    'label' => __('Hoy'),
                ];
        }
    }

    private function getMetricasVentas($rango): array
    {
        $ventas = Venta::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->get();

        $ventasCompletadas = $ventas->where('estado', '!=', 'cancelada');
        $ventasCanceladas = $ventas->where('estado', 'cancelada');
        $ventasCtaCte = $ventasCompletadas->where('es_cuenta_corriente', true);

        // Comparación con período anterior
        $diasPeriodo = $rango['desde']->diffInDays($rango['hasta']) + 1;
        $rangoAnterior = [
            'desde' => $rango['desde']->copy()->subDays($diasPeriodo),
            'hasta' => $rango['desde']->copy()->subDay()->endOfDay(),
        ];

        $ventasAnterior = Venta::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha', [$rangoAnterior['desde'], $rangoAnterior['hasta']])
            ->where('estado', '!=', 'cancelada')
            ->sum('total_final');

        $totalActual = $ventasCompletadas->sum('total_final');
        $variacion = $ventasAnterior > 0
            ? round((($totalActual - $ventasAnterior) / $ventasAnterior) * 100, 1)
            : ($totalActual > 0 ? 100 : 0);

        return [
            'total' => $totalActual,
            'cantidad' => $ventasCompletadas->count(),
            'canceladas' => $ventasCanceladas->count(),
            'ticket_promedio' => $ventasCompletadas->count() > 0
                ? round($totalActual / $ventasCompletadas->count(), 2)
                : 0,
            'cta_cte_total' => $ventasCtaCte->sum('total_final'),
            'cta_cte_cantidad' => $ventasCtaCte->count(),
            'saldo_pendiente' => $ventasCtaCte->sum('saldo_pendiente_cache'),
            'descuentos_aplicados' => $ventasCompletadas->sum('descuento'),
            'ajustes_forma_pago' => $ventasCompletadas->sum('ajuste_forma_pago'),
            'variacion' => $variacion,
            'periodo_anterior' => $ventasAnterior,
        ];
    }

    private function getVentasPorFormaPago($rango): array
    {
        $pagos = VentaPago::whereHas('venta', function($q) use ($rango) {
                $q->where('sucursal_id', $this->sucursalSeleccionada)
                    ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
                    ->where('estado', '!=', 'cancelada');
            })
            ->with('formaPago')
            ->get();

        $porFormaPago = $pagos->groupBy('forma_pago_id')->map(function($grupo) {
            $formaPago = $grupo->first()->formaPago;
            return [
                'nombre' => $formaPago->nombre ?? __('Sin especificar'),
                'total' => $grupo->sum('monto_final'),
                'cantidad' => $grupo->count(),
                'facturado' => $grupo->where('comprobante_fiscal_id', '!=', null)->sum('monto_final'),
                'no_facturado' => $grupo->where('comprobante_fiscal_id', null)->sum('monto_final'),
            ];
        })->sortByDesc('total')->values()->toArray();

        $totalPagos = $pagos->sum('monto_final');

        return [
            'detalle' => $porFormaPago,
            'total' => $totalPagos,
            'facturado_total' => $pagos->whereNotNull('comprobante_fiscal_id')->sum('monto_final'),
            'no_facturado_total' => $pagos->whereNull('comprobante_fiscal_id')->sum('monto_final'),
        ];
    }

    private function getComprobantesFiscales($rango): array
    {
        $comprobantes = ComprobanteFiscal::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha_emision', [$rango['desde'], $rango['hasta']])
            ->where('estado', 'autorizado')
            ->get();

        $facturas = $comprobantes->filter(fn($c) => $c->esFactura());
        $notasCredito = $comprobantes->filter(fn($c) => $c->esNotaCredito());

        // Agrupar por tipo
        $porTipo = $comprobantes->groupBy('tipo')->map(function($grupo) {
            return [
                'tipo' => $grupo->first()->tipo_legible,
                'cantidad' => $grupo->count(),
                'total' => $grupo->sum('total'),
                'neto' => $grupo->sum('neto_gravado'),
                'iva' => $grupo->sum('iva_total'),
            ];
        })->values()->toArray();

        return [
            'facturas_cantidad' => $facturas->count(),
            'facturas_total' => $facturas->sum('total'),
            'nc_cantidad' => $notasCredito->count(),
            'nc_total' => $notasCredito->sum('total'),
            'neto_total' => $facturas->sum('neto_gravado') - $notasCredito->sum('neto_gravado'),
            'iva_total' => $facturas->sum('iva_total') - $notasCredito->sum('iva_total'),
            'balance' => $facturas->sum('total') - $notasCredito->sum('total'),
            'por_tipo' => $porTipo,
        ];
    }

    private function getPromocionesAplicadas($rango): array
    {
        $promociones = VentaPromocion::whereHas('venta', function($q) use ($rango) {
                $q->where('sucursal_id', $this->sucursalSeleccionada)
                    ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
                    ->where('estado', '!=', 'cancelada');
            })
            ->get();

        $porTipo = $promociones->groupBy('tipo_promocion')->map(function($grupo, $tipo) {
            return [
                'tipo' => $tipo === 'promocion_especial' ? __('Promociones Especiales') : __('Promociones'),
                'cantidad' => $grupo->count(),
                'descuento_total' => $grupo->sum('descuento_aplicado'),
            ];
        })->values()->toArray();

        // Top promociones por uso
        $topPromociones = $promociones->groupBy('descripcion_promocion')
            ->map(function($grupo, $nombre) {
                return [
                    'nombre' => $nombre ?: __('Sin nombre'),
                    'veces_usada' => $grupo->count(),
                    'descuento_total' => $grupo->sum('descuento_aplicado'),
                ];
            })
            ->sortByDesc('veces_usada')
            ->take(5)
            ->values()
            ->toArray();

        return [
            'total_descuentos' => $promociones->sum('descuento_aplicado'),
            'cantidad_aplicaciones' => $promociones->count(),
            'por_tipo' => $porTipo,
            'top_promociones' => $topPromociones,
        ];
    }

    private function getCobros($rango): array
    {
        $cobros = Cobro::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->where('estado', 'activo')
            ->get();

        return [
            'cantidad' => $cobros->count(),
            'total_cobrado' => $cobros->sum('monto_cobrado'),
            'intereses' => $cobros->sum('interes_aplicado'),
            'descuentos' => $cobros->sum('descuento_aplicado'),
            'aplicado_a_deuda' => $cobros->sum('monto_aplicado_a_deuda'),
            'saldo_a_favor' => $cobros->sum('monto_a_favor'),
        ];
    }

    private function getCompras($rango): array
    {
        $compras = Compra::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->where('estado', '!=', 'cancelada')
            ->get();

        return [
            'cantidad' => $compras->count(),
            'total' => $compras->sum('total'),
        ];
    }

    private function getEstadoCajas(): array
    {
        $cajas = Caja::porSucursal($this->sucursalSeleccionada)->get();
        $cajasAbiertas = $cajas->filter->estaAbierta();

        return [
            'total' => $cajas->count(),
            'abiertas' => $cajasAbiertas->count(),
            'cerradas' => $cajas->count() - $cajasAbiertas->count(),
            'saldo_total' => $cajasAbiertas->sum('saldo_actual'),
            'detalle' => $cajas->map(function($caja) {
                return [
                    'nombre' => $caja->nombre,
                    'estado' => $caja->estaAbierta() ? 'abierta' : 'cerrada',
                    'saldo' => $caja->estaAbierta() ? $caja->saldo_actual : 0,
                ];
            })->toArray(),
        ];
    }

    private function getAlertasStock(): array
    {
        $stockBajoMinimo = Stock::porSucursal($this->sucursalSeleccionada)
            ->bajoMinimo()
            ->with('articulo')
            ->limit(10)
            ->get();

        $stockSinExistencia = Stock::porSucursal($this->sucursalSeleccionada)
            ->where('cantidad', '<=', 0)
            ->with('articulo')
            ->limit(10)
            ->get();

        return [
            'bajo_minimo_count' => Stock::porSucursal($this->sucursalSeleccionada)->bajoMinimo()->count(),
            'sin_existencia_count' => Stock::porSucursal($this->sucursalSeleccionada)->where('cantidad', '<=', 0)->count(),
            'bajo_minimo' => $stockBajoMinimo->map(function($s) {
                return [
                    'articulo' => $s->articulo->nombre ?? 'N/A',
                    'cantidad' => $s->cantidad,
                    'minimo' => $s->stock_minimo,
                ];
            })->toArray(),
            'sin_existencia' => $stockSinExistencia->map(function($s) {
                return [
                    'articulo' => $s->articulo->nombre ?? 'N/A',
                ];
            })->toArray(),
        ];
    }

    private function getUltimasVentas(): array
    {
        return Venta::with(['cliente', 'pagos.formaPago'])
            ->where('sucursal_id', $this->sucursalSeleccionada)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($venta) {
                return [
                    'id' => $venta->id,
                    'numero' => $venta->numero,
                    'cliente' => $venta->cliente->nombre ?? __('Consumidor Final'),
                    'total' => $venta->total_final ?? $venta->total,
                    'estado' => $venta->estado,
                    'hora' => $venta->created_at->format('H:i'),
                    'fecha' => $venta->created_at->format('d/m'),
                    'forma_pago' => $venta->pagos->first()?->formaPago?->nombre ?? '-',
                    'es_cta_cte' => $venta->es_cuenta_corriente,
                ];
            })
            ->toArray();
    }

    private function getVentasPorHora($rango): array
    {
        if ($this->periodoSeleccionado !== 'hoy') {
            return [];
        }

        $ventas = Venta::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->groupBy(function($venta) {
                return $venta->created_at->format('H');
            })
            ->map(function($grupo, $hora) {
                return [
                    'hora' => $hora . ':00',
                    'cantidad' => $grupo->count(),
                    'total' => $grupo->sum('total_final'),
                ];
            })
            ->sortKeys()
            ->values()
            ->toArray();

        return $ventas;
    }

    public function render()
    {
        $rango = $this->getRangoFechas();
        $sucursal = Sucursal::find($this->sucursalSeleccionada);

        return view('livewire.dashboard.dashboard-sucursal', [
            'sucursal' => $sucursal,
            'periodoLabel' => $rango['label'],
            'metricas' => $this->getMetricasVentas($rango),
            'formasPago' => $this->getVentasPorFormaPago($rango),
            'fiscal' => $this->getComprobantesFiscales($rango),
            'promociones' => $this->getPromocionesAplicadas($rango),
            'cobros' => $this->getCobros($rango),
            'compras' => $this->getCompras($rango),
            'cajas' => $this->getEstadoCajas(),
            'alertasStock' => $this->getAlertasStock(),
            'ultimasVentas' => $this->getUltimasVentas(),
            'ventasPorHora' => $this->getVentasPorHora($rango),
        ]);
    }
}
