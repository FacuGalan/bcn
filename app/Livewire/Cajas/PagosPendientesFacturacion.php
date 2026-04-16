<?php

namespace App\Livewire\Cajas;

use App\Models\FormaPago;
use App\Models\VentaPago;
use App\Services\Ventas\CambioFormaPagoService;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente: Reporte de Pagos Pendientes de Facturar
 *
 * Lista los venta_pagos cuya emisión de FC nueva falló después de un cambio
 * de forma de pago (o por cualquier otra razón quedaron en estado
 * pendiente_de_facturar). Permite reintentar la emisión o marcar como error_arca.
 */
#[Lazy]
class PagosPendientesFacturacion extends Component
{
    use SucursalAware;
    use WithPagination;

    public string $filtroFechaDesde = '';

    public string $filtroFechaHasta = '';

    public ?int $filtroFormaPagoId = null;

    public string $filtroEstado = 'pendiente_de_facturar';

    public int $perPage = 15;

    // Modal reintentar
    public bool $showReintentarModal = false;

    public ?int $pagoAReintentarId = null;

    // Modal marcar error
    public bool $showMarcarErrorModal = false;

    public ?int $pagoAMarcarErrorId = null;

    public string $motivoMarcarError = '';

    protected $queryString = [
        'filtroFechaDesde' => ['except' => ''],
        'filtroFechaHasta' => ['except' => ''],
        'filtroFormaPagoId' => ['except' => null],
        'filtroEstado' => ['except' => 'pendiente_de_facturar'],
    ];

    public function placeholder(): string
    {
        return view('components.skeleton.page-table')->render();
    }

    public function updated($name): void
    {
        if (in_array($name, ['filtroFechaDesde', 'filtroFechaHasta', 'filtroFormaPagoId', 'filtroEstado'])) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->filtroFechaDesde = '';
        $this->filtroFechaHasta = '';
        $this->filtroFormaPagoId = null;
        $this->filtroEstado = 'pendiente_de_facturar';
        $this->resetPage();
    }

    public function abrirReintentar(int $pagoId): void
    {
        $this->pagoAReintentarId = $pagoId;
        $this->showReintentarModal = true;
    }

    public function confirmarReintentar(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.reintentar_facturacion')) {
            $this->dispatch('toast', tipo: 'error', mensaje: __('No tenés permiso para reintentar facturación'));

            return;
        }

        $pago = VentaPago::find($this->pagoAReintentarId);
        if (! $pago) {
            $this->dispatch('toast', tipo: 'error', mensaje: __('Pago no encontrado'));
            $this->cerrarReintentarModal();

            return;
        }

        try {
            $service = new CambioFormaPagoService;
            $fc = $service->reintentarFacturacionPago($pago, auth()->id());

            $this->dispatch('toast', tipo: 'exito', mensaje: __('Factura emitida correctamente: :num', ['num' => $fc->numero_formateado ?? $fc->id]));
            $this->cerrarReintentarModal();
        } catch (Exception $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: __('Error al reintentar: :msg', ['msg' => $e->getMessage()]));
            $this->cerrarReintentarModal();
        }
    }

    public function cerrarReintentarModal(): void
    {
        $this->showReintentarModal = false;
        $this->pagoAReintentarId = null;
    }

    public function abrirMarcarError(int $pagoId): void
    {
        $this->pagoAMarcarErrorId = $pagoId;
        $this->motivoMarcarError = '';
        $this->showMarcarErrorModal = true;
    }

    public function confirmarMarcarError(): void
    {
        if (mb_strlen(trim($this->motivoMarcarError)) < 10) {
            $this->dispatch('toast', tipo: 'error', mensaje: __('El motivo debe tener al menos 10 caracteres'));

            return;
        }

        $pago = VentaPago::find($this->pagoAMarcarErrorId);
        if (! $pago) {
            $this->dispatch('toast', tipo: 'error', mensaje: __('Pago no encontrado'));
            $this->cerrarMarcarErrorModal();

            return;
        }

        try {
            $service = new CambioFormaPagoService;
            $service->marcarErrorFacturacion($pago, auth()->id(), $this->motivoMarcarError);

            $this->dispatch('toast', tipo: 'exito', mensaje: __('Pago marcado como error ARCA'));
            $this->cerrarMarcarErrorModal();
        } catch (Exception $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->cerrarMarcarErrorModal();
        }
    }

    public function cerrarMarcarErrorModal(): void
    {
        $this->showMarcarErrorModal = false;
        $this->pagoAMarcarErrorId = null;
        $this->motivoMarcarError = '';
    }

    public function render()
    {
        $sucursalId = $this->sucursalActual();

        $query = VentaPago::with(['venta.cliente', 'formaPago'])
            ->whereHas('venta', fn ($q) => $q->where('sucursal_id', $sucursalId));

        if ($this->filtroEstado === 'pendiente_de_facturar') {
            $query->pendientesDeFacturar();
        } elseif ($this->filtroEstado === 'error_arca') {
            $query->conErrorFacturacion();
        } elseif ($this->filtroEstado === 'todos') {
            $query->whereIn('estado_facturacion', [
                VentaPago::ESTADO_FACT_PENDIENTE,
                VentaPago::ESTADO_FACT_ERROR,
            ]);
        }

        if ($this->filtroFechaDesde) {
            $query->where('created_at', '>=', $this->filtroFechaDesde.' 00:00:00');
        }
        if ($this->filtroFechaHasta) {
            $query->where('created_at', '<=', $this->filtroFechaHasta.' 23:59:59');
        }
        if ($this->filtroFormaPagoId) {
            $query->where('forma_pago_id', $this->filtroFormaPagoId);
        }

        $pagos = $query->orderByDesc('created_at')->paginate($this->perPage);

        $formasPagoDisponibles = FormaPago::whereHas('sucursales', fn ($q) => $q->where('sucursales.id', $sucursalId))
            ->where('factura_fiscal', true)
            ->orderBy('nombre')
            ->get();

        return view('livewire.cajas.pagos-pendientes-facturacion', [
            'pagos' => $pagos,
            'formasPagoDisponibles' => $formasPagoDisponibles,
        ]);
    }
}
