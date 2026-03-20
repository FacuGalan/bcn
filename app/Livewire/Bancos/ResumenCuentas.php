<?php

namespace App\Livewire\Bancos;

use App\Models\Moneda;
use App\Models\MovimientoCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use App\Traits\SucursalAware;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ResumenCuentas extends Component
{
    use SucursalAware;

    public bool $showConciliacionModal = false;

    public function cancelConciliacion()
    {
        $this->showConciliacionModal = false;
    }

    public function render()
    {
        $sucursalId = sucursal_activa();

        $cuentas = CuentaEmpresaService::getCuentasDisponibles($sucursalId ?? 0);

        // Totales por moneda
        $totalesPorMoneda = $cuentas->groupBy('moneda_id')->map(function ($grupo) {
            $moneda = $grupo->first()->moneda;

            return [
                'moneda' => $moneda,
                'total' => $grupo->sum('saldo_actual'),
                'cantidad_cuentas' => $grupo->count(),
            ];
        });

        // Últimos 10 movimientos globales (incluyendo anulados para dar contexto)
        $ultimosMovimientos = MovimientoCuentaEmpresa::query()
            ->whereIn('cuenta_empresa_id', $cuentas->pluck('id'))
            ->with(['cuentaEmpresa', 'usuario', 'movimientoAnulacion', 'movimientoAnulado'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.bancos.resumen-cuentas', [
            'cuentas' => $cuentas,
            'totalesPorMoneda' => $totalesPorMoneda,
            'ultimosMovimientos' => $ultimosMovimientos,
        ]);
    }
}
