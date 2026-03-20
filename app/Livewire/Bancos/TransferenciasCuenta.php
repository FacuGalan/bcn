<?php

namespace App\Livewire\Bancos;

use App\Models\CuentaEmpresa;
use App\Models\TransferenciaCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use App\Traits\SucursalAware;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TransferenciasCuenta extends Component
{
    use SucursalAware, WithPagination;

    // Formulario
    public ?int $cuentaOrigenId = null;

    public ?int $cuentaDestinoId = null;

    public ?float $monto = null;

    public string $concepto = '';

    public function transferir()
    {
        $this->validate([
            'cuentaOrigenId' => 'required|exists:pymes_tenant.cuentas_empresa,id',
            'cuentaDestinoId' => 'required|exists:pymes_tenant.cuentas_empresa,id|different:cuentaOrigenId',
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:255',
        ], [
            'cuentaDestinoId.different' => __('La cuenta destino debe ser diferente a la cuenta origen'),
        ]);

        try {
            CuentaEmpresaService::transferirEntreCuentas(
                $this->cuentaOrigenId,
                $this->cuentaDestinoId,
                $this->monto,
                $this->concepto,
                Auth::id()
            );

            $this->reset(['cuentaOrigenId', 'cuentaDestinoId', 'monto', 'concepto']);
            $this->dispatch('toast-success', message: __('Transferencia realizada correctamente'));
        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function getCuentasProperty()
    {
        $sucursalId = sucursal_activa();

        return CuentaEmpresaService::getCuentasDisponibles($sucursalId ?? 0);
    }

    public function getCuentasDestinoProperty()
    {
        if (! $this->cuentaOrigenId) {
            return collect();
        }

        $origen = CuentaEmpresa::find($this->cuentaOrigenId);
        if (! $origen) {
            return collect();
        }

        // Filtrar por misma moneda y diferente cuenta
        return $this->cuentas->filter(function ($cuenta) use ($origen) {
            return $cuenta->id !== $origen->id && $cuenta->moneda_id === $origen->moneda_id;
        })->values();
    }

    public function render()
    {
        $transferencias = TransferenciaCuentaEmpresa::with([
            'cuentaOrigen.moneda', 'cuentaDestino.moneda', 'usuario',
        ])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.bancos.transferencias-cuenta', [
            'transferencias' => $transferencias,
        ]);
    }
}
