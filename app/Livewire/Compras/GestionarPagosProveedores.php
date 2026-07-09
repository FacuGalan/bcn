<?php

namespace App\Livewire\Compras;

use App\Models\Caja;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use App\Services\CuentaCorrienteProveedorService;
use App\Services\PagoProveedorService;
use App\Traits\CajaAware;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pagos a proveedores (RF-19, espejo de GestionarCobranzas): deuda por
 * proveedor en la SUCURSAL ACTIVA (D19), pago con aplicación FIFO/manual a
 * compras pendientes, desglose de formas de pago con ORIGEN de fondos (D14),
 * anticipos, estado de cuenta y anulación de órdenes de pago (D16).
 *
 * El origen default es la caja activa; con `func.compras.pagar_avanzado` se
 * habilita otra caja, efectivo de Tesorería o cuenta de empresa por renglón.
 */
#[Layout('layouts.app')]
#[Lazy]
class GestionarPagosProveedores extends Component
{
    use CajaAware, WithPagination;

    public string $search = '';

    // Modal de pago
    public bool $showPagoModal = false;

    public ?int $proveedorPagoId = null;

    public array $comprasPendientes = [];

    /** @var array<int, string> montos a aplicar por compra_id */
    public array $montosAplicar = [];

    public string $montoADistribuir = '';

    public string $saldoFavorUsado = '';

    public float $saldoFavorDisponible = 0;

    /** @var array renglones del desglose: {forma_pago_id, monto, origen, caja_id, cuenta_empresa_id} */
    public array $pagos = [];

    public bool $esAnticipo = false;

    public string $observaciones = '';

    // Modal estado de cuenta / OPs
    public bool $showExtractoModal = false;

    public ?int $proveedorExtractoId = null;

    public array $extracto = [];

    public array $saldosExtracto = [];

    // Modal anular OP
    public bool $showAnularModal = false;

    public ?int $pagoAAnularId = null;

    public string $motivoAnulacion = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function onSucursalChanged(): void
    {
        $this->resetPage();
        $this->cerrarModales();
    }

    public function onCajaChanged(): void
    {
        // El default de los renglones de caja es la caja activa: reset limpio.
        $this->cerrarModales();
    }

    private function cerrarModales(): void
    {
        $this->showPagoModal = false;
        $this->showExtractoModal = false;
        $this->showAnularModal = false;
    }

    // ==================== Modal de pago ====================

    public function abrirPago(int $proveedorId, bool $anticipo = false): void
    {
        $this->reset(['montosAplicar', 'montoADistribuir', 'saldoFavorUsado', 'observaciones']);

        $this->proveedorPagoId = $proveedorId;
        $this->esAnticipo = $anticipo;

        $ccService = app(CuentaCorrienteProveedorService::class);
        $this->comprasPendientes = $anticipo
            ? []
            : $ccService->obtenerComprasPendientes($proveedorId, (int) session('sucursal_id'))->toArray();
        $this->saldoFavorDisponible = MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($proveedorId);

        $this->pagos = [$this->renglonPagoVacio()];

        $this->showPagoModal = true;
    }

    public function distribuirFifo(): void
    {
        $monto = (float) str_replace(',', '.', $this->montoADistribuir);

        if ($monto <= 0) {
            return;
        }

        $distribucion = app(PagoProveedorService::class)
            ->distribuirMontoFIFO($monto, collect($this->comprasPendientes));

        $this->montosAplicar = [];
        foreach ($distribucion as $item) {
            $this->montosAplicar[$item['compra_id']] = (string) $item['monto_aplicado'];
        }

        // Precargar el desglose con el total a pagar (menos saldo a favor usado).
        $usado = min((float) str_replace(',', '.', $this->saldoFavorUsado ?: '0'), $monto);
        $this->pagos[0]['monto'] = (string) round($monto - $usado, 2);
    }

    public function agregarRenglonPago(): void
    {
        $this->pagos[] = $this->renglonPagoVacio();
    }

    public function quitarRenglonPago(int $index): void
    {
        unset($this->pagos[$index]);
        $this->pagos = array_values($this->pagos);
    }

    public function confirmarPago(): void
    {
        try {
            $comprasAAplicar = [];

            foreach ($this->montosAplicar as $compraId => $monto) {
                $monto = (float) str_replace(',', '.', (string) $monto);

                if ($monto > 0) {
                    $comprasAAplicar[] = ['compra_id' => (int) $compraId, 'monto_aplicado' => $monto];
                }
            }

            if (! $this->esAnticipo && empty($comprasAAplicar)) {
                throw new Exception(__('Indicá cuánto aplicar a cada compra (o usá "Distribuir")'));
            }

            $pagos = collect($this->pagos)
                ->map(function ($p) {
                    $origen = $p['origen'] ?? 'caja';

                    // Sin permiso avanzado, todo sale de la caja activa (D14).
                    if (! $this->puedePagarAvanzado()) {
                        $origen = 'caja';
                        $p['caja_id'] = $this->cajaActual();
                    }

                    return [
                        'forma_pago_id' => (int) ($p['forma_pago_id'] ?? 0),
                        'monto' => (float) str_replace(',', '.', (string) ($p['monto'] ?? 0)),
                        'origen' => $origen,
                        'caja_id' => $p['caja_id'] ?: $this->cajaActual(),
                        'cuenta_empresa_id' => $p['cuenta_empresa_id'] ?: null,
                    ];
                })
                ->filter(fn ($p) => $p['monto'] > 0 && $p['forma_pago_id'] > 0)
                ->values()
                ->all();

            app(PagoProveedorService::class)->registrarPago([
                'sucursal_id' => (int) session('sucursal_id'),
                'proveedor_id' => $this->proveedorPagoId,
                'usuario_id' => auth()->id(),
                'caja_id' => $this->cajaActual(),
                'saldo_favor_usado' => (float) str_replace(',', '.', $this->saldoFavorUsado ?: '0'),
                'observaciones' => $this->observaciones ?: null,
            ], $comprasAAplicar, $pagos);

            $this->showPagoModal = false;
            $this->dispatch('notify', type: 'success', message: $this->esAnticipo
                ? __('Anticipo registrado correctamente')
                : __('Pago registrado correctamente'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ==================== Estado de cuenta + OPs ====================

    public function verExtracto(int $proveedorId): void
    {
        $this->proveedorExtractoId = $proveedorId;

        $ccService = app(CuentaCorrienteProveedorService::class);
        $sucursalId = (int) session('sucursal_id');
        $this->extracto = $ccService->obtenerExtractoResumido($proveedorId, $sucursalId)->toArray();
        $this->saldosExtracto = $ccService->obtenerSaldos($proveedorId, $sucursalId);

        $this->showExtractoModal = true;
    }

    public function abrirAnular(int $pagoProveedorId): void
    {
        $this->pagoAAnularId = $pagoProveedorId;
        $this->motivoAnulacion = '';
        $this->showAnularModal = true;
    }

    public function confirmarAnular(): void
    {
        $this->validate(['motivoAnulacion' => 'required|string|max:255']);

        try {
            app(PagoProveedorService::class)->anularPago($this->pagoAAnularId, $this->motivoAnulacion, (int) auth()->id());

            $this->showAnularModal = false;

            if ($this->proveedorExtractoId) {
                $this->verExtracto($this->proveedorExtractoId);
            }

            $this->dispatch('notify', type: 'success', message: __('Orden de pago anulada'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ==================== Helpers ====================

    public function puedePagarAvanzado(): bool
    {
        return (bool) auth()->user()?->hasPermissionTo('func.compras.pagar_avanzado');
    }

    private function renglonPagoVacio(): array
    {
        return [
            'forma_pago_id' => null,
            'monto' => '',
            'origen' => 'caja',
            'caja_id' => $this->cajaActual(),
            'cuenta_empresa_id' => null,
        ];
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="1" :columns="5" :rows="8" />
        HTML;
    }

    public function render()
    {
        $sucursalId = (int) session('sucursal_id');

        // Proveedores con deuda en la sucursal (por compras con saldo) o con
        // cta cte habilitada — la operatoria es POR SUCURSAL ACTIVA (D19).
        $query = Proveedor::query()
            ->where('activo', true)
            ->where(function ($q) use ($sucursalId) {
                $q->where('tiene_cuenta_corriente', true)
                    ->orWhereHas('compras', fn ($c) => $c->where('sucursal_id', $sucursalId)->where('saldo_pendiente', '>', 0));
            });

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('razon_social', 'like', '%'.$this->search.'%')
                    ->orWhere('cuit', 'like', '%'.$this->search.'%');
            });
        }

        $proveedores = $query->orderBy('nombre')->paginate(15);

        // Saldo de la sucursal activa por proveedor (ledger, on-the-fly).
        $saldos = [];
        foreach ($proveedores as $proveedor) {
            $saldos[$proveedor->id] = MovimientoCuentaCorrienteProveedor::obtenerSaldos($proveedor->id, $sucursalId);
        }

        return view('livewire.compras.gestionar-pagos-proveedores', [
            'proveedores' => $proveedores,
            'saldos' => $saldos,
            'formasPago' => FormaPago::where('activo', true)
                ->where('es_mixta', false)
                ->where('solo_sistema', false)
                ->whereNot('codigo', 'cta_cte')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'cajasDisponibles' => Caja::porSucursal($sucursalId)->abiertas()->get(['id', 'nombre']),
            'cuentasEmpresa' => CuentaEmpresa::where('activo', true)->get(['id', 'nombre']),
            'proveedorExtracto' => $this->proveedorExtractoId ? Proveedor::find($this->proveedorExtractoId) : null,
            'opsProveedor' => $this->proveedorExtractoId
                ? PagoProveedor::where('proveedor_id', $this->proveedorExtractoId)
                    ->where('sucursal_id', $sucursalId)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get()
                : collect(),
            'proveedorPago' => $this->proveedorPagoId ? Proveedor::find($this->proveedorPagoId) : null,
        ]);
    }
}
