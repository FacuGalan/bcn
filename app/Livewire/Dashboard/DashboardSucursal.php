<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Venta;
use App\Models\Compra;
use App\Models\Caja;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire: Dashboard de Sucursal
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Mostrar métricas clave del día (ventas, compras)
 * 2. Estado de cajas
 * 3. Alertas de stock
 * 4. Accesos rápidos a módulos
 * 5. Resumen general de operaciones
 *
 * MÉTRICAS MOSTRADAS:
 * ==================
 * - Ventas del día (cantidad y monto total)
 * - Compras del día (cantidad y monto total)
 * - Estado de cajas (abiertas/cerradas, saldos)
 * - Alertas de stock (bajo mínimo, sin stock)
 * - Últimas operaciones
 *
 * FASE 4 - Sistema Multi-Sucursal (Componentes Livewire)
 *
 * @package App\Livewire\Dashboard
 * @version 1.0.0
 */
class DashboardSucursal extends Component
{
    public $sucursalSeleccionada = null;
    public $fechaSeleccionada = null;

    // Escuchar el evento de cambio de sucursal
    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function mount()
    {
        // IMPORTANTE: Usar la sucursal de la sesión mediante el helper
        $this->sucursalSeleccionada = sucursal_activa() ?? Sucursal::activas()->first()->id ?? 1;
        $this->fechaSeleccionada = now()->format('Y-m-d');
    }

    /**
     * Maneja el evento cuando se cambia la sucursal
     */
    public function handleSucursalChanged($sucursalId, $sucursalNombre = null)
    {
        $this->sucursalSeleccionada = $sucursalId;
        // El render se ejecutará automáticamente con la nueva sucursal
    }

    public function render()
    {
        // Métricas de ventas
        $ventasHoy = Venta::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereDate('fecha', $this->fechaSeleccionada)
            ->where('estado', '!=', 'cancelada')
            ->get();

        $totalVentasHoy = $ventasHoy->sum('total');
        $cantidadVentasHoy = $ventasHoy->count();

        // Métricas de compras
        $comprasHoy = Compra::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereDate('fecha', $this->fechaSeleccionada)
            ->where('estado', '!=', 'cancelada')
            ->get();

        $totalComprasHoy = $comprasHoy->sum('total');
        $cantidadComprasHoy = $comprasHoy->count();

        // Estado de cajas
        $cajas = Caja::porSucursal($this->sucursalSeleccionada)->get();
        $cajasAbiertas = $cajas->filter->estaAbierta()->count();
        $totalEnCajas = $cajas->filter->estaAbierta()->sum('saldo_actual');

        // Alertas de stock
        $stockBajoMinimo = Stock::porSucursal($this->sucursalSeleccionada)->bajoMinimo()->count();
        $stockSinExistencia = Stock::porSucursal($this->sucursalSeleccionada)->where('cantidad', '<=', 0)->count();

        // Últimas ventas
        $ultimasVentas = Venta::with('cliente')
            ->where('sucursal_id', $this->sucursalSeleccionada)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Ventas por forma de pago (hoy)
        $ventasPorFormaPago = Venta::where('sucursal_id', $this->sucursalSeleccionada)
            ->whereDate('fecha', $this->fechaSeleccionada)
            ->where('estado', '!=', 'cancelada')
            ->select('forma_pago', DB::raw('SUM(total) as total'))
            ->groupBy('forma_pago')
            ->get();

        return view('livewire.dashboard.dashboard-sucursal', [
            'totalVentasHoy' => $totalVentasHoy,
            'cantidadVentasHoy' => $cantidadVentasHoy,
            'totalComprasHoy' => $totalComprasHoy,
            'cantidadComprasHoy' => $cantidadComprasHoy,
            'cajasAbiertas' => $cajasAbiertas,
            'totalCajas' => $cajas->count(),
            'totalEnCajas' => $totalEnCajas,
            'stockBajoMinimo' => $stockBajoMinimo,
            'stockSinExistencia' => $stockSinExistencia,
            'ultimasVentas' => $ultimasVentas,
            'ventasPorFormaPago' => $ventasPorFormaPago,
            'sucursal' => Sucursal::find($this->sucursalSeleccionada),
        ]);
    }

    public function updatedFechaSeleccionada()
    {
        // Actualiza métricas cuando cambia la fecha
    }
}
