<?php

namespace App\Livewire\Compras;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\HistorialCosto;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\PagoProveedorCompra;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\PagoProveedorService;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de compras (Fase 6, sesión UX D7 — reescritura RF-12).
 *
 * Patrón lista de pedidos: badges de estado, badge de pago = botón pagar,
 * acciones acotadas (Ver / Editar / Cargar NC / Cancelar). La carga/edición
 * abre el sub-componente EditorCompra en modal a pantalla completa (montaje
 * condicional con key incremental, patrón PedidosDelivery).
 *
 * SucursalAware (D14: NO CajaAware — la caja pertenece al pago; el modal de
 * pago rápido lee la caja activa igual que GestionarPagosProveedores).
 */
#[Layout('layouts.app')]
#[Lazy]
class Compras extends Component
{
    use SucursalAware, WithPagination;

    // ==================== Filtros ====================

    public string $search = '';

    public string $filterEstado = 'all';

    public string $filterProveedor = '';

    public string $filterFechaDesde = '';

    public string $filterFechaHasta = '';

    public bool $showFilters = false;

    // ==================== Editor (modal fullscreen) ====================

    public bool $editorAbierto = false;

    public ?int $compraIdEnEdicion = null;

    public ?int $editorNcOrigenId = null;

    public bool $editorEsNC = false;

    public int $editorKey = 0;

    // ==================== Modal detalle ====================

    public bool $showDetalleModal = false;

    public ?int $compraDetalleId = null;

    // ==================== Modal cancelar (D17) ====================

    public bool $showCancelarModal = false;

    public ?int $compraCancelarId = null;

    public string $motivoCancelacion = '';

    public string $manejoPagos = '';

    public bool $cancelarTienePagos = false;

    // ==================== Modal eliminar borrador ====================

    public bool $showEliminarModal = false;

    public ?int $compraEliminarId = null;

    // ==================== Modal pago rápido (badge de pago = botón) ====================

    public bool $showPagoModal = false;

    public ?int $compraPagoId = null;

    public string $montoAPagar = '';

    public string $saldoFavorUsado = '';

    public float $saldoFavorDisponible = 0;

    /** @var array renglones {forma_pago_id, monto, origen, caja_id, cuenta_empresa_id} */
    public array $pagos = [];

    // ==================== Ciclo de vida ====================

    public function mount(): void
    {
        $this->filterFechaDesde = now()->subDays(30)->toDateString();
        $this->filterFechaHasta = now()->toDateString();
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="4" :columns="8" :rows="8" />
        HTML;
    }

    protected function onSucursalChanged($sucursalId, $sucursalNombre): void
    {
        $this->cerrarModales();
        $this->resetPage();
    }

    private function cerrarModales(): void
    {
        $this->editorAbierto = false;
        $this->showDetalleModal = false;
        $this->showCancelarModal = false;
        $this->showEliminarModal = false;
        $this->showPagoModal = false;
    }

    // ==================== Filtros ====================

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEstado(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProveedor(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFechaHasta(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterEstado', 'filterProveedor']);
        $this->filterFechaDesde = now()->subDays(30)->toDateString();
        $this->filterFechaHasta = now()->toDateString();
        $this->resetPage();
    }

    // ==================== Editor (patrón PedidosDelivery) ====================

    public function abrirNuevaCompra(): void
    {
        $this->compraIdEnEdicion = null;
        $this->editorNcOrigenId = null;
        $this->editorEsNC = false;
        $this->editorKey++;
        $this->editorAbierto = true;
    }

    /** Camino secundario D7 #9: NC suelta desde el listado. */
    public function abrirNuevaNC(): void
    {
        $this->compraIdEnEdicion = null;
        $this->editorNcOrigenId = null;
        $this->editorEsNC = true;
        $this->editorKey++;
        $this->editorAbierto = true;
    }

    public function abrirEditarCompra(int $compraId): void
    {
        $compra = Compra::find($compraId);

        if ($compra === null || ! $this->validarAccesoSucursal($compra)) {
            return;
        }

        // Fase 6: edición directa SOLO de borradores. La corrección de una
        // completada (cancelar+recrear atómico, D7 #12) llega con sus
        // decisiones de conflictos.
        if (! $compra->esBorrador()) {
            $this->dispatch('notify', type: 'error', message: __('Una compra completada es inmutable: cancelala y volvé a cargarla'));

            return;
        }

        $this->compraIdEnEdicion = $compraId;
        $this->editorNcOrigenId = null;
        $this->editorEsNC = $compra->esNotaCredito();
        $this->editorKey++;
        $this->editorAbierto = true;
    }

    /** Camino principal D7 #9: NC desde el detalle de la compra. */
    public function abrirNCDesdeCompra(int $compraId): void
    {
        $compra = Compra::find($compraId);

        if ($compra === null || ! $this->validarAccesoSucursal($compra)) {
            return;
        }

        if (! $compra->estaCompletada() || $compra->esNotaCredito()) {
            $this->dispatch('notify', type: 'error', message: __('Solo se puede cargar una NC sobre una compra completada'));

            return;
        }

        $this->showDetalleModal = false;
        $this->compraIdEnEdicion = null;
        $this->editorNcOrigenId = $compraId;
        $this->editorEsNC = true;
        $this->editorKey++;
        $this->editorAbierto = true;
    }

    #[On('cerrar-editor-compra')]
    public function cerrarEditor(): void
    {
        $this->editorAbierto = false;
        $this->compraIdEnEdicion = null;
        $this->editorNcOrigenId = null;
    }

    #[On('compra-guardada')]
    public function trasGuardarCompra(): void
    {
        $this->cerrarEditor();
        $this->resetPage();
    }

    // ==================== Detalle (D7 #11) ====================

    public function verDetalle(int $compraId): void
    {
        $this->compraDetalleId = $compraId;
        $this->showDetalleModal = true;
    }

    public function cerrarDetalle(): void
    {
        $this->showDetalleModal = false;
        $this->compraDetalleId = null;
    }

    // ==================== Cancelar (D17) ====================

    public function abrirCancelar(int $compraId): void
    {
        $compra = Compra::find($compraId);

        if ($compra === null || ! $this->validarAccesoSucursal($compra)) {
            return;
        }

        $this->compraCancelarId = $compraId;
        $this->motivoCancelacion = '';

        // D17: si tiene pagos aplicados el usuario ELIGE cascada o saldo a favor.
        $this->cancelarTienePagos = PagoProveedorCompra::where('compra_id', $compraId)
            ->whereHas('pagoProveedor', fn ($q) => $q->where('estado', 'activo'))
            ->exists();
        $this->manejoPagos = $this->cancelarTienePagos ? 'saldo_favor' : '';

        $this->showCancelarModal = true;
    }

    public function confirmarCancelar(): void
    {
        $this->validate(['motivoCancelacion' => 'required|string|max:255'], [
            'motivoCancelacion.required' => __('Indicá el motivo de la cancelación'),
        ]);

        try {
            $compra = Compra::findOrFail($this->compraCancelarId);

            app(CompraService::class)->cancelarCompra(
                $compra,
                (int) auth()->id(),
                $this->motivoCancelacion,
                $this->cancelarTienePagos ? $this->manejoPagos : null,
            );

            $this->showCancelarModal = false;
            $this->cerrarDetalle();
            $this->dispatch('notify', type: 'success', message: __('Compra cancelada'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ==================== Eliminar borrador ====================

    public function abrirEliminarBorrador(int $compraId): void
    {
        $this->compraEliminarId = $compraId;
        $this->showEliminarModal = true;
    }

    public function confirmarEliminarBorrador(): void
    {
        try {
            $compra = Compra::findOrFail($this->compraEliminarId);

            app(CompraService::class)->eliminarBorrador($compra);

            $this->showEliminarModal = false;
            $this->dispatch('notify', type: 'success', message: __('Borrador eliminado'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ==================== Pago rápido (badge de pago = botón, D7 #10) ====================

    public function abrirPago(int $compraId): void
    {
        $compra = Compra::find($compraId);

        if ($compra === null || ! $this->validarAccesoSucursal($compra) || ! $compra->tieneSaldoPendiente()) {
            return;
        }

        if (! auth()->user()?->hasPermissionTo('func.compras.pagar')) {
            $this->dispatch('notify', type: 'error', message: __('No tenés permiso para registrar pagos'));

            return;
        }

        $this->compraPagoId = $compraId;
        $this->montoAPagar = (string) $compra->saldo_pendiente;
        $this->saldoFavorUsado = '';
        $this->saldoFavorDisponible = MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($compra->proveedor_id);
        $this->pagos = [$this->renglonPagoVacio()];
        $this->pagos[0]['monto'] = (string) $compra->saldo_pendiente;

        $this->showPagoModal = true;
    }

    public function agregarRenglonPago(): void
    {
        $this->pagos[] = $this->renglonPagoVacio();
    }

    public function quitarRenglonPago(int $index): void
    {
        unset($this->pagos[$index]);
        $this->pagos = array_values($this->pagos) ?: [$this->renglonPagoVacio()];
    }

    public function confirmarPago(): void
    {
        try {
            $compra = Compra::findOrFail($this->compraPagoId);

            $monto = (float) str_replace(',', '.', $this->montoAPagar ?: '0');

            if ($monto <= 0) {
                throw new Exception(__('Indicá el monto a pagar'));
            }

            $pagos = collect($this->pagos)
                ->map(function ($p) {
                    $origen = $p['origen'] ?? 'caja';

                    if (! $this->puedePagarAvanzado()) {
                        $origen = 'caja';
                        $p['caja_id'] = caja_activa();
                    }

                    return [
                        'forma_pago_id' => (int) ($p['forma_pago_id'] ?? 0),
                        'monto' => (float) str_replace(',', '.', (string) ($p['monto'] ?? 0)),
                        'origen' => $origen,
                        'caja_id' => $p['caja_id'] ?: caja_activa(),
                        'cuenta_empresa_id' => $p['cuenta_empresa_id'] ?: null,
                    ];
                })
                ->filter(fn ($p) => $p['monto'] > 0 && $p['forma_pago_id'] > 0)
                ->values()
                ->all();

            app(PagoProveedorService::class)->registrarPago([
                'sucursal_id' => (int) session('sucursal_id'),
                'proveedor_id' => $compra->proveedor_id,
                'usuario_id' => auth()->id(),
                'caja_id' => caja_activa(),
                'saldo_favor_usado' => (float) str_replace(',', '.', $this->saldoFavorUsado ?: '0'),
                'observaciones' => __('Pago desde el listado de compras'),
            ], [
                ['compra_id' => $compra->id, 'monto_aplicado' => $monto],
            ], $pagos);

            $this->showPagoModal = false;
            $this->dispatch('notify', type: 'success', message: __('Pago registrado correctamente'));
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

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
            'caja_id' => caja_activa(),
            'cuenta_empresa_id' => null,
        ];
    }

    // ==================== Helpers ====================

    private function validarAccesoSucursal(Compra $compra): bool
    {
        if ((int) $compra->sucursal_id !== (int) $this->sucursalActual()) {
            $this->dispatch('notify', type: 'error', message: __('La compra pertenece a otra sucursal'));

            return false;
        }

        return true;
    }

    // ==================== Render ====================

    public function render()
    {
        $query = Compra::with(['proveedor:id,nombre', 'cuentaCompra:id,nombre'])
            ->where('sucursal_id', $this->sucursalActual());

        if ($this->filterEstado === 'con_saldo') {
            $query->completadas()->where('saldo_pendiente', '>', 0);
        } elseif ($this->filterEstado !== 'all') {
            $query->where('estado', $this->filterEstado);
        }

        if ($this->filterProveedor !== '') {
            $query->where('proveedor_id', (int) $this->filterProveedor);
        }

        if ($this->filterFechaDesde) {
            $query->whereDate('fecha', '>=', $this->filterFechaDesde);
        }

        if ($this->filterFechaHasta) {
            $query->whereDate('fecha', '<=', $this->filterFechaHasta);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('numero_comprobante', 'like', '%'.$this->search.'%')
                    ->orWhere('numero_comprobante_proveedor', 'like', '%'.$this->search.'%')
                    ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', '%'.$this->search.'%'));
            });
        }

        $compras = $query->orderByDesc('id')->paginate(15);

        // Detalle (D7 #11): reconstrucción perfecta de la factura.
        $compraDetalle = null;
        $pagosDetalle = collect();
        $costosDetalle = collect();

        if ($this->showDetalleModal && $this->compraDetalleId) {
            $compraDetalle = Compra::with([
                'proveedor', 'cuit', 'cuentaCompra', 'usuario:id,name', 'sucursal:id,nombre',
                'detalles.articulo:id,nombre,codigo', 'detalles.tipoIva:id,porcentaje',
                'ivas', 'conceptos.tipoIva:id,porcentaje', 'percepciones.impuesto:id,nombre',
                'compraOrigen:id,numero_comprobante',
                'notasCredito' => fn ($q) => $q->where('estado', '!=', Compra::ESTADO_CANCELADA),
            ])->find($this->compraDetalleId);

            if ($compraDetalle !== null) {
                $pagosDetalle = PagoProveedorCompra::with('pagoProveedor:id,numero,fecha,estado,monto_total')
                    ->where('compra_id', $compraDetalle->id)
                    ->get();

                $costosDetalle = HistorialCosto::with('articulo:id,nombre')
                    ->where('compra_id', $compraDetalle->id)
                    ->get();
            }
        }

        return view('livewire.compras.compras', [
            'compras' => $compras,
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'compraDetalle' => $compraDetalle,
            'pagosDetalle' => $pagosDetalle,
            'costosDetalle' => $costosDetalle,
            'compraPago' => $this->compraPagoId ? Compra::with('proveedor:id,nombre')->find($this->compraPagoId) : null,
            'formasPago' => FormaPago::where('activo', true)
                ->where('es_mixta', false)
                ->where('solo_sistema', false)
                ->whereNot('codigo', 'cta_cte')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'cajasDisponibles' => Caja::porSucursal((int) $this->sucursalActual())->abiertas()->get(['id', 'nombre']),
            'cuentasEmpresa' => CuentaEmpresa::where('activo', true)->get(['id', 'nombre']),
        ]);
    }
}
