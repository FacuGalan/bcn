<?php

namespace App\Livewire\Pedidos;

use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Services\Pedidos\PedidoMostradorService;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista de Pedidos por Mostrador para una sucursal.
 *
 * Acciones soportadas: cambiar estado, cobrar pagos pendientes (materializar
 * pagos planificados o agregar pagos nuevos), convertir en venta, cancelar,
 * reimprimir comanda/precuenta. El alta y la edición del carrito viven en
 * un componente aparte (NuevoPedidoMostrador, PR2.C.2).
 */
#[Layout('layouts.app')]
#[Lazy]
class PedidosMostrador extends Component
{
    use SucursalAware, WithPagination;

    // ==================== FILTROS ====================

    public string $search = '';

    public string $filterEstadoPedido = 'activos';

    public string $filterEstadoPago = 'all';

    public ?string $filterFechaDesde = null;

    public ?string $filterFechaHasta = null;

    public bool $showFilters = false;

    /** Controla el desplegable de borradores arriba de la lista. */
    public bool $mostrarBorradores = false;

    // ==================== MODAL: DETALLE ====================

    public bool $showDetalleModal = false;

    public ?int $pedidoDetalleId = null;

    // ==================== MODAL: CAMBIAR ESTADO ====================

    public bool $showCambiarEstadoModal = false;

    public ?int $pedidoEstadoId = null;

    public string $nuevoEstado = '';

    public string $observacionEstado = '';

    public array $transicionesDisponibles = [];

    // ==================== MODAL: CANCELAR ====================

    public bool $showCancelarModal = false;

    public ?int $pedidoCancelarId = null;

    public string $motivoCancelacion = '';

    public array $cancelarPedidoInfo = [];

    // ==================== MODAL: COBRAR PENDIENTE ====================

    public bool $showCobrarModal = false;

    public ?int $pedidoCobrarId = null;

    public array $cobrarPedidoInfo = [];

    public array $cobrarPagosPlanificados = [];

    // ==================== MODAL: CONFIRMAR CONVERSIÓN ====================

    public bool $showConvertirModal = false;

    public ?int $pedidoConvertirId = null;

    public array $convertirPedidoInfo = [];

    // ==================== MODAL FULL-SCREEN: ALTA/EDICIÓN ====================

    /** Si está en true se renderiza <livewire:nuevo-pedido-mostrador>. */
    public bool $modalNuevoPedidoAbierto = false;

    /** ID del pedido a editar (null = alta nueva). */
    public ?int $pedidoIdEnEdicion = null;

    /** Counter para forzar remount del sub-componente al reabrir el modal. */
    public int $modalNuevoPedidoKey = 0;

    protected PedidoMostradorService $service;

    public function boot(PedidoMostradorService $service): void
    {
        $this->service = $service;
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="4" :columns="7" :rows="8" />
        HTML;
    }

    public function mount(): void
    {
        $this->filterFechaDesde = now()->subDays(7)->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
    }

    /**
     * Limpia estado modal-side al cambiar sucursal (además del default del trait).
     */
    protected function onSucursalChanged($sucursalId, $sucursalNombre): void
    {
        $this->showDetalleModal = false;
        $this->showCambiarEstadoModal = false;
        $this->showCancelarModal = false;
        $this->showCobrarModal = false;
        $this->showConvertirModal = false;
        $this->modalNuevoPedidoAbierto = false;
        $this->pedidoIdEnEdicion = null;
        $this->resetCambiarEstadoState();
        $this->resetCancelarState();
        $this->resetCobrarState();
        $this->resetConvertirState();
    }

    // ==================== MODAL ALTA/EDICIÓN ====================

    public function abrirModalNuevoPedido(): void
    {
        $this->pedidoIdEnEdicion = null;
        $this->modalNuevoPedidoKey++;
        $this->modalNuevoPedidoAbierto = true;
    }

    public function abrirModalEditarPedido(int $pedidoId): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (! in_array($pedido->estado_pedido, [PedidoMostrador::ESTADO_BORRADOR, PedidoMostrador::ESTADO_CONFIRMADO], true)) {
            $this->dispatch('toast-error', message: __("El pedido en estado ':estado' no se puede editar", ['estado' => $pedido->estado_pedido]));

            return;
        }

        // Pedidos con cobros materializados se gestionan desde "Cobrar pendiente".
        // Borradores siempre se pueden continuar.
        if ($pedido->estado_pedido !== PedidoMostrador::ESTADO_BORRADOR
            && $pedido->estado_pago !== PedidoMostrador::ESTADO_PAGO_PENDIENTE) {
            $this->dispatch('toast-error', message: __('No se puede editar un pedido con cobros registrados. Gestioná los pagos desde la lista.'));

            return;
        }

        $this->pedidoIdEnEdicion = $pedidoId;
        $this->modalNuevoPedidoKey++;
        $this->modalNuevoPedidoAbierto = true;
        // Cerrar el modal de detalle si estaba abierto.
        $this->showDetalleModal = false;
        $this->pedidoDetalleId = null;
    }

    /**
     * Listener para el evento `cerrar-modal-pedido` despachado por
     * NuevoPedidoMostrador (cancelar sin guardar).
     */
    #[\Livewire\Attributes\On('cerrar-modal-pedido')]
    public function cerrarModalNuevoPedido(): void
    {
        $this->modalNuevoPedidoAbierto = false;
        $this->pedidoIdEnEdicion = null;
    }

    /**
     * Listener para el evento `pedido-guardado` despachado por
     * NuevoPedidoMostrador (alta/edición exitosa). Cierra el modal y refresca
     * la lista — la query del render() se ejecuta de nuevo automáticamente.
     */
    #[\Livewire\Attributes\On('pedido-guardado')]
    public function tras_guardar_pedido(): void
    {
        $this->modalNuevoPedidoAbierto = false;
        $this->pedidoIdEnEdicion = null;
        $this->resetPage();
    }

    // ==================== FILTROS / QUERY ====================

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEstadoPedido(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEstadoPago(): void
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

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterEstadoPedido = 'activos';
        $this->filterEstadoPago = 'all';
        $this->filterFechaDesde = now()->subDays(7)->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
        $this->resetPage();
    }

    protected function obtenerPedidos()
    {
        $query = PedidoMostrador::with([
            'cliente:id,nombre,telefono',
            'sucursal:id,nombre',
            'caja:id,nombre',
            'pagos' => fn ($q) => $q->whereIn('estado', [
                PedidoMostradorPago::ESTADO_ACTIVO,
                PedidoMostradorPago::ESTADO_PLANIFICADO,
            ]),
        ])
            ->where('sucursal_id', $this->sucursalActual());

        // Estado del pedido. Los BORRADORES nunca aparecen en la tabla
        // principal — viven en su propio desplegable arriba (obtenerBorradores).
        if ($this->filterEstadoPedido === 'activos') {
            $query->activos()->where('estado_pedido', '!=', PedidoMostrador::ESTADO_BORRADOR);
        } elseif ($this->filterEstadoPedido === 'borrador') {
            // Caso edge: si el usuario eligió "borrador" en el filtro, lo
            // dejamos pasar (puede querer auditarlos).
            $query->where('estado_pedido', PedidoMostrador::ESTADO_BORRADOR);
        } elseif ($this->filterEstadoPedido !== 'all') {
            $query->where('estado_pedido', $this->filterEstadoPedido);
        } else {
            $query->where('estado_pedido', '!=', PedidoMostrador::ESTADO_BORRADOR);
        }

        // Estado del pago
        if ($this->filterEstadoPago !== 'all') {
            $query->where('estado_pago', $this->filterEstadoPago);
        }

        // Rango de fechas
        if ($this->filterFechaDesde) {
            $query->whereDate('fecha', '>=', $this->filterFechaDesde);
        }
        if ($this->filterFechaHasta) {
            $query->whereDate('fecha', '<=', $this->filterFechaHasta);
        }

        // Búsqueda libre
        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->orWhere('numero', $term);
                }
                $q->orWhere('identificador', 'like', "%{$term}%")
                    ->orWhere('numero_beeper', 'like', "%{$term}%")
                    ->orWhere('nombre_cliente_temporal', 'like', "%{$term}%")
                    ->orWhere('telefono_cliente_temporal', 'like', "%{$term}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$term}%"));
            });
        }

        return $query->orderByDesc('fecha')->orderByDesc('id')->paginate(15);
    }

    /**
     * Borradores de la sucursal: pedidos pre-cargados sin número ni stock,
     * que el usuario quiere retomar después. Se listan en un desplegable
     * separado encima de la tabla principal.
     */
    protected function obtenerBorradores()
    {
        return PedidoMostrador::with(['cliente:id,nombre,telefono'])
            ->where('sucursal_id', $this->sucursalActual())
            ->where('estado_pedido', PedidoMostrador::ESTADO_BORRADOR)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    public function toggleBorradores(): void
    {
        $this->mostrarBorradores = ! $this->mostrarBorradores;
    }

    // ==================== DETALLE ====================

    public function verDetalle(int $pedidoId): void
    {
        $this->pedidoDetalleId = $pedidoId;
        $this->showDetalleModal = true;
    }

    public function cerrarDetalle(): void
    {
        $this->showDetalleModal = false;
        $this->pedidoDetalleId = null;
    }

    // ==================== CAMBIAR ESTADO ====================

    public function abrirCambiarEstado(int $pedidoId): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $transiciones = PedidoMostrador::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];

        // Excluir CANCELADO acá (tiene su propio modal con motivo) y FACTURADO
        // (solo se llega vía convertirEnVenta).
        $transiciones = array_values(array_filter($transiciones, fn ($e) => ! in_array($e, [
            PedidoMostrador::ESTADO_CANCELADO,
            PedidoMostrador::ESTADO_FACTURADO,
        ], true)));

        if (empty($transiciones)) {
            $this->dispatch('toast-error', message: __('No hay transiciones disponibles desde este estado'));

            return;
        }

        $this->pedidoEstadoId = $pedido->id;
        $this->nuevoEstado = $transiciones[0];
        $this->observacionEstado = '';
        $this->transicionesDisponibles = $transiciones;
        $this->showCambiarEstadoModal = true;
    }

    public function cancelarCambiarEstado(): void
    {
        $this->showCambiarEstadoModal = false;
        $this->resetCambiarEstadoState();
    }

    public function confirmarCambiarEstado(): void
    {
        $pedido = PedidoMostrador::find($this->pedidoEstadoId);
        if (! $pedido) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        try {
            $this->service->cambiarEstado($pedido, $this->nuevoEstado, $this->observacionEstado ?: null);
            $this->dispatch('toast-success', message: __('Estado actualizado'));
            $this->showCambiarEstadoModal = false;
            $this->resetCambiarEstadoState();
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de pedido', [
                'pedido_id' => $this->pedidoEstadoId,
                'nuevo_estado' => $this->nuevoEstado,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    protected function resetCambiarEstadoState(): void
    {
        $this->pedidoEstadoId = null;
        $this->nuevoEstado = '';
        $this->observacionEstado = '';
        $this->transicionesDisponibles = [];
    }

    // ==================== CANCELAR ====================

    public function abrirCancelar(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cancelar pedidos'));

            return;
        }

        $pedido = PedidoMostrador::with('cliente:id,nombre')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoMostrador::ESTADO_CANCELADO, PedidoMostrador::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no se puede cancelar'));

            return;
        }

        $this->pedidoCancelarId = $pedido->id;
        $this->motivoCancelacion = '';
        $this->cancelarPedidoInfo = [
            'numero' => $pedido->numero,
            'identificador' => $pedido->identificador,
            'cliente' => $pedido->nombre_cliente_final ?? __('Sin cliente'),
            'total' => (float) $pedido->total_final,
            'tiene_pagos_activos' => $pedido->pagos()->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)->exists(),
        ];
        $this->showCancelarModal = true;
    }

    public function cancelarCancelar(): void
    {
        $this->showCancelarModal = false;
        $this->resetCancelarState();
    }

    public function ejecutarCancelar(): void
    {
        $this->validate([
            'motivoCancelacion' => 'required|string|min:5',
        ], [
            'motivoCancelacion.required' => __('Ingresá un motivo de cancelación'),
            'motivoCancelacion.min' => __('El motivo debe tener al menos 5 caracteres'),
        ]);

        $pedido = PedidoMostrador::find($this->pedidoCancelarId);
        if (! $pedido) {
            return;
        }

        try {
            $this->service->cancelarPedido($pedido, trim($this->motivoCancelacion));
            $this->dispatch('toast-success', message: __('Pedido cancelado'));
            $this->showCancelarModal = false;
            $this->resetCancelarState();
        } catch (Exception $e) {
            Log::error('Error al cancelar pedido', [
                'pedido_id' => $this->pedidoCancelarId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    protected function resetCancelarState(): void
    {
        $this->pedidoCancelarId = null;
        $this->motivoCancelacion = '';
        $this->cancelarPedidoInfo = [];
    }

    // ==================== COBRAR PENDIENTE ====================

    public function abrirCobrar(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cobrar pedidos'));

            return;
        }

        $pedido = PedidoMostrador::with(['pagos.formaPago:id,nombre'])
            ->find($pedidoId);

        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoMostrador::ESTADO_CANCELADO, PedidoMostrador::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no acepta pagos'));

            return;
        }

        $planificados = $pedido->pagos
            ->where('estado', PedidoMostradorPago::ESTADO_PLANIFICADO)
            ->map(fn ($p) => [
                'id' => $p->id,
                'forma_pago' => $p->formaPago?->nombre ?? __('Sin especificar'),
                'monto_final' => (float) $p->monto_final,
                'cuotas' => $p->cuotas,
                'referencia' => $p->referencia,
            ])
            ->values()
            ->toArray();

        $this->pedidoCobrarId = $pedido->id;
        $this->cobrarPedidoInfo = [
            'numero' => $pedido->numero,
            'identificador' => $pedido->identificador,
            'total' => (float) $pedido->total_final,
            'total_cobrado' => (float) $pedido->total_cobrado,
            'total_planificado' => (float) $pedido->total_planificado,
            'pendiente' => max(0, (float) $pedido->total_final - (float) $pedido->total_cobrado),
        ];
        $this->cobrarPagosPlanificados = $planificados;
        $this->showCobrarModal = true;
    }

    public function cerrarCobrar(): void
    {
        $this->showCobrarModal = false;
        $this->resetCobrarState();
    }

    public function confirmarPagoPlanificado(int $pagoId): void
    {
        $pago = PedidoMostradorPago::find($pagoId);
        if (! $pago || $pago->pedido_mostrador_id !== $this->pedidoCobrarId) {
            $this->dispatch('toast-error', message: __('Pago no encontrado'));

            return;
        }

        try {
            $this->service->confirmarPagoPlanificado($pago);
            $this->dispatch('toast-success', message: __('Pago confirmado'));
            $this->abrirCobrar($this->pedidoCobrarId); // Re-cargar modal con estado fresco
        } catch (Exception $e) {
            Log::error('Error al confirmar pago planificado', [
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function eliminarPagoPlanificado(int $pagoId): void
    {
        $pago = PedidoMostradorPago::find($pagoId);
        if (! $pago || $pago->pedido_mostrador_id !== $this->pedidoCobrarId) {
            $this->dispatch('toast-error', message: __('Pago no encontrado'));

            return;
        }

        try {
            $this->service->eliminarPagoPlanificado($pago);
            $this->dispatch('toast-success', message: __('Pago planificado eliminado'));
            $this->abrirCobrar($this->pedidoCobrarId);
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    protected function resetCobrarState(): void
    {
        $this->pedidoCobrarId = null;
        $this->cobrarPedidoInfo = [];
        $this->cobrarPagosPlanificados = [];
    }

    // ==================== CONVERTIR EN VENTA ====================

    public function abrirConvertir(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para convertir pedidos en venta'));

            return;
        }

        $pedido = PedidoMostrador::with('cliente:id,nombre')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [
            PedidoMostrador::ESTADO_CANCELADO,
            PedidoMostrador::ESTADO_FACTURADO,
            PedidoMostrador::ESTADO_BORRADOR,
        ], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no se puede convertir en venta'));

            return;
        }

        $this->pedidoConvertirId = $pedido->id;
        $this->convertirPedidoInfo = [
            'numero' => $pedido->numero,
            'identificador' => $pedido->identificador,
            'cliente' => $pedido->nombre_cliente_final ?? __('Sin cliente'),
            'total' => (float) $pedido->total_final,
            'total_cobrado' => (float) $pedido->total_cobrado,
            'total_planificado' => (float) $pedido->total_planificado,
            'pendiente' => max(0, (float) $pedido->total_final - (float) $pedido->total_cobrado - (float) $pedido->total_planificado),
            'tiene_pagos_planificados' => $pedido->total_planificado > 0.005,
        ];
        $this->showConvertirModal = true;
    }

    public function cancelarConvertir(): void
    {
        $this->showConvertirModal = false;
        $this->resetConvertirState();
    }

    public function ejecutarConvertir(): void
    {
        $pedido = PedidoMostrador::find($this->pedidoConvertirId);
        if (! $pedido) {
            return;
        }

        try {
            $venta = $this->service->convertirEnVenta($pedido);
            $this->dispatch('toast-success', message: __('Pedido convertido en venta #:id', ['id' => $venta->id]));
            $this->showConvertirModal = false;
            $this->resetConvertirState();
        } catch (Exception $e) {
            Log::error('Error al convertir pedido en venta', [
                'pedido_id' => $this->pedidoConvertirId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    protected function resetConvertirState(): void
    {
        $this->pedidoConvertirId = null;
        $this->convertirPedidoInfo = [];
    }

    // ==================== IMPRESIÓN ====================

    public function reimprimirComanda(int $pedidoId): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        try {
            $payload = $this->service->imprimirComanda($pedido);
            $this->dispatch('imprimir-comanda', payload: $payload);
            $this->dispatch('toast-info', message: __('Enviando comanda a impresión...'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function reimprimirPrecuenta(int $pedidoId): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        try {
            $payload = $this->service->imprimirPrecuenta($pedido);
            $this->dispatch('imprimir-precuenta', payload: $payload);
            $this->dispatch('toast-info', message: __('Enviando precuenta a impresión...'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== RENDER ====================

    public function render()
    {
        $pedidos = $this->obtenerPedidos();
        $borradores = $this->obtenerBorradores();

        $pedidoDetalle = $this->pedidoDetalleId
            ? PedidoMostrador::with([
                'cliente:id,nombre,telefono',
                'sucursal:id,nombre',
                'caja:id,nombre',
                'venta:id,numero',
                'detalles.articulo:id,nombre',
                'detalles.opcionales',
                'pagos.formaPago:id,nombre',
            ])->find($this->pedidoDetalleId)
            : null;

        return view('livewire.pedidos.pedidos-mostrador', [
            'pedidos' => $pedidos,
            'borradores' => $borradores,
            'pedidoDetalle' => $pedidoDetalle,
            'estadosPedido' => PedidoMostrador::ESTADOS,
            'estadosPago' => PedidoMostrador::ESTADOS_PAGO,
        ]);
    }
}
