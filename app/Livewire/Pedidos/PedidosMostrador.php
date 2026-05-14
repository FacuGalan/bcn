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

    // ==================== TIEMPO REAL ====================

    /**
     * IDs de pedidos que el usuario ya vio (snapshot al montar). Si llega un
     * pedido nuevo cuyo ID no esta en este array, se incrementa el contador
     * `nuevosCount` para mostrar un badge "X nuevos" en la UI.
     *
     * @var array<int, int>
     */
    public array $idsVistos = [];

    /** Cantidad de pedidos nuevos entrados via broadcast desde que se monto la pagina. */
    public int $nuevosCount = 0;

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

        $this->snapshotIdsVistos();
    }

    /**
     * Captura los IDs de pedidos visibles ahora (excluyendo borradores). Se
     * usa como baseline para detectar pedidos "nuevos" que entran via
     * broadcast.
     */
    protected function snapshotIdsVistos(): void
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            $this->idsVistos = [];

            return;
        }

        $this->idsVistos = PedidoMostrador::where('sucursal_id', $sucursalId)
            ->where('estado_pedido', '!=', PedidoMostrador::ESTADO_BORRADOR)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Listeners dinamicos. El nombre del canal lleva el comercio_id del user
     * activo, asi que se resuelve via TenantService. Sin contexto tenant, no
     * se subscribe a ningun canal.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $comercioId = app(\App\Services\TenantService::class)->getComercioId();
        if ($comercioId === null) {
            return [];
        }

        return [
            "echo-private:comercios.{$comercioId}.pedidos-mostrador,.PedidoMostradorBroadcast" => 'onPedidoBroadcast',
        ];
    }

    /**
     * Handler del evento broadcast PedidoMostradorBroadcast.
     *
     * Filtra por sucursal: solo procesa eventos de la sucursal actual. Si el
     * tipo es "creado" y el pedidoId no estaba en el snapshot, incrementa el
     * contador de nuevos. Cualquier tipo dispara re-render (Livewire lo hace
     * automaticamente al terminar el metodo).
     *
     * @param  array{pedidoId?: int, sucursalId?: int, tipo?: string, at?: string}  $event
     */
    public function onPedidoBroadcast(array $event): void
    {
        $pedidoId = (int) ($event['pedidoId'] ?? 0);
        $sucursalEvt = (int) ($event['sucursalId'] ?? 0);
        $tipo = (string) ($event['tipo'] ?? '');

        if ($pedidoId === 0 || $sucursalEvt !== (int) $this->sucursalActual()) {
            return;
        }

        if ($tipo === \App\Events\Broadcasting\PedidoMostradorBroadcast::TIPO_CREADO
            && ! in_array($pedidoId, $this->idsVistos, true)) {
            $this->idsVistos[] = $pedidoId;
            $this->nuevosCount++;
        }
        // Otros tipos: el render automatico de Livewire trae la data fresca.
    }

    /**
     * El usuario hizo click en "Ver X nuevos" — resetea el contador y
     * actualiza el snapshot al estado actual.
     */
    public function marcarTodosVistos(): void
    {
        $this->nuevosCount = 0;
        $this->snapshotIdsVistos();
        $this->resetPage();
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
                $q->orWhere('numero_beeper', 'like', "%{$term}%")
                    ->orWhere('nombre_cliente_temporal', 'like', "%{$term}%")
                    ->orWhere('telefono_cliente_temporal', 'like', "%{$term}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('telefono', 'like', "%{$term}%"));
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

    // ==================== KANBAN ====================

    /**
     * 4 estados que se muestran como columnas Kanban. Cancelados/Facturados
     * quedan fuera (terminales o solo visibles en vista Lista).
     */
    public const ESTADOS_KANBAN = [
        PedidoMostrador::ESTADO_CONFIRMADO,
        PedidoMostrador::ESTADO_EN_PREPARACION,
        PedidoMostrador::ESTADO_LISTO,
        PedidoMostrador::ESTADO_ENTREGADO,
    ];

    /**
     * Pedidos para la vista Kanban, agrupados por estado_pedido. Mismos
     * filtros que la lista (cliente, fecha, pago) pero SIN paginacion —
     * limitado a 200 para que Sortable no se ponga pesado.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function obtenerPedidosKanban(): array
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            $vacio = [];
            foreach (self::ESTADOS_KANBAN as $estado) {
                $vacio[$estado] = collect();
            }

            return $vacio;
        }

        $query = PedidoMostrador::with([
            'cliente:id,nombre,telefono',
            'pagos' => fn ($q) => $q->whereIn('estado', [
                PedidoMostradorPago::ESTADO_ACTIVO,
                PedidoMostradorPago::ESTADO_PLANIFICADO,
            ]),
        ])
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado_pedido', self::ESTADOS_KANBAN);

        if ($this->filterEstadoPago !== 'all') {
            $query->where('estado_pago', $this->filterEstadoPago);
        }
        if ($this->filterFechaDesde) {
            $query->whereDate('fecha', '>=', $this->filterFechaDesde);
        }
        if ($this->filterFechaHasta) {
            $query->whereDate('fecha', '<=', $this->filterFechaHasta);
        }
        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->orWhere('numero', $term);
                }
                $q->orWhere('numero_beeper', 'like', "%{$term}%")
                    ->orWhere('nombre_cliente_temporal', 'like', "%{$term}%")
                    ->orWhere('telefono_cliente_temporal', 'like', "%{$term}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('telefono', 'like', "%{$term}%"));
            });
        }

        $pedidos = $query->orderByDesc('fecha')->orderByDesc('id')->limit(200)->get();

        // Inicializar cada columna con una Collection FRESCA. array_fill_keys con
        // collect() comparte la misma referencia entre todas las keys (bug subtle).
        $agrupados = [];
        foreach (self::ESTADOS_KANBAN as $estado) {
            $agrupados[$estado] = collect();
        }

        foreach ($pedidos as $pedido) {
            if (isset($agrupados[$pedido->estado_pedido])) {
                $agrupados[$pedido->estado_pedido]->push($pedido);
            }
        }

        return $agrupados;
    }

    /**
     * Cambia el estado de un pedido al soltar una card en otra columna del
     * Kanban. Valida que la transicion sea legal segun TRANSICIONES_PERMITIDAS.
     * Si no lo es, dispara toast-error y el frontend revierte visualmente la
     * card a su columna original (Sortable lo hace solo si lanzamos refresh).
     */
    public function cambiarEstadoDrag(int $pedidoId, string $nuevoEstado): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));
            $this->dispatch('kanban-revertir');

            return;
        }

        if (! in_array($nuevoEstado, self::ESTADOS_KANBAN, true)) {
            $this->dispatch('toast-error', message: __('Estado destino inválido'));
            $this->dispatch('kanban-revertir');

            return;
        }

        $transiciones = PedidoMostrador::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];
        if (! in_array($nuevoEstado, $transiciones, true)) {
            $this->dispatch('toast-error', message: __('Transición :de → :a no permitida', [
                'de' => $pedido->estado_pedido,
                'a' => $nuevoEstado,
            ]));
            $this->dispatch('kanban-revertir');

            return;
        }

        try {
            $this->service->cambiarEstado($pedido, $nuevoEstado);
            $this->dispatch('toast-success', message: __('Estado actualizado'));
        } catch (Exception $e) {
            Log::error('Error al cambiar estado via drag&drop', [
                'pedido_id' => $pedidoId,
                'nuevo_estado' => $nuevoEstado,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
            $this->dispatch('kanban-revertir');
        }
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

    // ==================== ACCIONES RAPIDAS ====================

    /**
     * Marca un pedido como ENTREGADO sin abrir el modal de cambio de estado.
     * Valida que la transicion sea legal (CONFIRMADO/EN_PREPARACION/LISTO ->
     * ENTREGADO) antes de invocar el service. Si la sucursal tiene
     * `pedido_conversion_automatica_al_entregar=true`, el service se encarga
     * de convertir en venta como efecto secundario.
     */
    public function entregarRapido(int $pedidoId): void
    {
        $pedido = PedidoMostrador::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $transiciones = PedidoMostrador::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];
        if (! in_array(PedidoMostrador::ESTADO_ENTREGADO, $transiciones, true)) {
            $this->dispatch('toast-error', message: __("No se puede marcar como entregado desde el estado ':estado'", ['estado' => $pedido->estado_pedido]));

            return;
        }

        try {
            $this->service->cambiarEstado($pedido, PedidoMostrador::ESTADO_ENTREGADO);
            $this->dispatch('toast-success', message: __('Pedido entregado'));
        } catch (Exception $e) {
            Log::error('Error al entregar pedido rapido', [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Cobra un pedido pendiente "rapido":
     * - Si hay pagos planificados → los confirma TODOS sin preguntar
     *   (auto-cobro segun el desglose que el operario ya armo al armar el pedido).
     * - Si NO hay planificados → abre el modal estandar de "Cobrar pendiente"
     *   para que el operario defina la forma de pago.
     */
    public function cobrarRapido(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cobrar pedidos'));

            return;
        }

        $pedido = PedidoMostrador::with('pagos')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoMostrador::ESTADO_CANCELADO, PedidoMostrador::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no acepta pagos'));

            return;
        }

        $planificados = $pedido->pagos->where('estado', PedidoMostradorPago::ESTADO_PLANIFICADO);

        if ($planificados->isEmpty()) {
            $this->abrirCobrar($pedidoId);

            return;
        }

        try {
            foreach ($planificados as $pago) {
                $this->service->confirmarPagoPlanificado($pago);
            }
            $this->dispatch('toast-success', message: __(':n pago(s) confirmados', ['n' => $planificados->count()]));
        } catch (Exception $e) {
            Log::error('Error al cobrar rapido', [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
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
        $pedidosKanban = $this->obtenerPedidosKanban();

        // Mapa de transiciones permitidas SOLO entre estados del Kanban (cancelar/facturar
        // quedan fuera). El frontend usa este array para validar onMove de SortableJS.
        $transicionesKanban = [];
        foreach (self::ESTADOS_KANBAN as $estado) {
            $transicionesKanban[$estado] = array_values(array_intersect(
                PedidoMostrador::TRANSICIONES_PERMITIDAS[$estado] ?? [],
                self::ESTADOS_KANBAN,
            ));
        }

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
            'pedidosKanban' => $pedidosKanban,
            'transicionesKanban' => $transicionesKanban,
            'estadosKanban' => self::ESTADOS_KANBAN,
            'pedidoDetalle' => $pedidoDetalle,
            'estadosPedido' => PedidoMostrador::ESTADOS,
            'estadosPago' => PedidoMostrador::ESTADOS_PAGO,
        ]);
    }
}
