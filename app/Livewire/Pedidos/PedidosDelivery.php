<?php

namespace App\Livewire\Pedidos;

use App\Livewire\Concerns\Carrito\WithCobroIntegracion;
use App\Models\Cliente;
use App\Models\DeliverySalida;
use App\Models\DeliveryZona;
use App\Models\FormaPago;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Repartidor;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\Pedidos\RepartidorService;
use App\Traits\CajaAware;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Panel de Pedidos Delivery / Take-away de una sucursal (RF-01, espejo del
 * panel de mostrador).
 *
 * Acciones espejo: cambiar estado, cobrar, convertir en venta, cancelar,
 * comanda/precuenta. Propias de delivery: asignar repartidor, despachar
 * (listo → en_camino SIEMPRE vía salida — RepartidorService), armar salida
 * multi-pedido y registrar la vuelta con cobros contra entrega (D13). El
 * alta/edición del carrito vive en NuevoPedidoDelivery.
 *
 * A diferencia de mostrador, el listado NO filtra por caja: los pedidos
 * delivery son de la sucursal (los de tienda/API ni siquiera tienen caja).
 * La caja activa se usa solo para cobrar/convertir.
 */
#[Layout('layouts.app')]
#[Lazy]
class PedidosDelivery extends Component
{
    use CajaAware, SucursalAware, WithPagination {
        // Ambos traits definen getListeners() — el de CajaAware ya incluye los
        // listeners de sucursal-changed/sucursal-cambiada (porque caja implica
        // sucursal), asi que tomamos ese y descartamos el de SucursalAware. El
        // override de la clase ($this->getListeners()) sigue ganando igual.
        CajaAware::getListeners insteadof SucursalAware;
    }

    // Cobro por QR (mismo concern que usan NuevaVenta/NuevoPedidoDelivery):
    // materializa pagos planificados con forma de pago integrada solo cuando el
    // pago QR se confirma. Única fuente de verdad del cobro por integración.
    use WithCobroIntegracion;

    // ==================== FILTROS ====================

    public string $search = '';

    public string $filterEstadoPedido = 'activos';

    public string $filterEstadoPago = 'all';

    /** Filtros propios de delivery (RF-01): tipo, repartidor, origen, zona. */
    public string $filterTipo = 'all';

    public string $filterRepartidor = 'all';

    public string $filterOrigen = 'all';

    public string $filterZona = 'all';

    public ?string $filterFechaDesde = null;

    public ?string $filterFechaHasta = null;

    public bool $showFilters = false;

    // ==================== ORDENAMIENTO ====================

    public string $sortField = 'fecha';

    public string $sortDirection = 'desc';

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

    /**
     * Pago planificado (PedidoDeliveryPago) que se está cobrando por QR. Se
     * setea al disparar el cobro por integración y se materializa recién cuando
     * el QR se aprueba (alConfirmarCobroIntegracion). Si el QR se cancela/expira,
     * queda planificado y editable.
     */
    public ?int $cobroIntegracionPagoPlanificadoId = null;

    // ==================== MODAL: CONFIRMAR CONVERSIÓN ====================

    public bool $showConvertirModal = false;

    public ?int $pedidoConvertirId = null;

    public array $convertirPedidoInfo = [];

    // ==================== MODAL FULL-SCREEN: ALTA/EDICIÓN ====================

    /** Si está en true se renderiza <livewire:nuevo-pedido-delivery>. */
    public bool $modalNuevoPedidoAbierto = false;

    /** ID del pedido a editar (null = alta nueva). */
    public ?int $pedidoIdEnEdicion = null;

    /** Counter para forzar remount del sub-componente al reabrir el modal. */
    public int $modalNuevoPedidoKey = 0;

    // ==================== COBRO RAPIDO (modal-only) ====================

    /**
     * ID del pedido sobre el que se abrio el modal de cobro rapido. Cuando
     * no es null, la vista renderiza <livewire:nuevo-pedido-delivery> con
     * modoCobroRapido=true: el sub-componente arranca con el modal de
     * desglose abierto (sin la UI del editor full-screen) para definir las
     * formas de pago del saldo pendiente.
     */
    public ?int $pedidoCobroRapidoId = null;

    /** Counter para forzar remount al re-abrir el cobro rapido. */
    public int $cobroRapidoKey = 0;

    // ==================== ACCIÓN PENDIENTE DE COBRO ====================

    /**
     * Cuando se intercepta convertir en venta sobre un pedido no cobrado, se
     * guarda acá la acción para reanudarla después de que el modal de cobro
     * rápido complete. Único valor activo: 'convertir' (entregar dejó de
     * gatearse al pasar a comanda-por-detalle).
     */
    #[\Livewire\Attributes\Locked]
    public ?string $accionPendiente = null;

    /** ID del pedido sobre el que se interceptó la acción pendiente. */
    #[\Livewire\Attributes\Locked]
    public ?int $accionPendientePedidoId = null;

    // ==================== MODAL COMANDAR ====================

    /**
     * Modal "comandar" que pregunta al operario si quiere mandar a cocina
     * solo los items nuevos o todo el pedido. Solo se abre cuando hay mezcla
     * (items con `comandado_at=null` Y items ya comandados). En otros casos
     * `comandarPedido()` ejecuta directo.
     */
    public bool $showComandarModal = false;

    public ?int $pedidoComandarId = null;

    public int $comandarNuevosCount = 0;

    public int $comandarComandadosCount = 0;

    // ==================== MODAL: ASIGNAR REPARTIDOR ====================

    public bool $showRepartidorModal = false;

    public ?int $pedidoRepartidorId = null;

    public string $repartidorSeleccionadoId = '';

    // ==================== MODAL: ARMAR SALIDA (RF-08) ====================

    public bool $showArmarSalidaModal = false;

    public string $salidaRepartidorId = '';

    /** @var array<int, bool> pedido_id => seleccionado */
    public array $salidaPedidosSeleccionados = [];

    // ==================== MODAL: REGISTRAR VUELTA (RF-08/D13) ====================

    public bool $showVueltaModal = false;

    public ?int $vueltaSalidaId = null;

    /** @var array<int, array{resultado: string, motivo: string}> pedido_id => resultado */
    public array $vueltaResultados = [];

    /**
     * Pagos planificados a cobrar en la vuelta, por pedido:
     * pago_id => [cobrar(bool), monto_recibido(string), es_efectivo(bool), ...].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $vueltaCobros = [];

    /** Info de solo-lectura del modal de vuelta (repartidor, fondo, pedidos). */
    public array $vueltaInfo = [];

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

    protected PedidoDeliveryService $service;

    protected RepartidorService $repartidorService;

    public function boot(PedidoDeliveryService $service, RepartidorService $repartidorService): void
    {
        $this->service = $service;
        $this->repartidorService = $repartidorService;
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

        $query = PedidoDelivery::where('sucursal_id', $sucursalId)
            ->where('estado_pedido', '!=', PedidoDelivery::ESTADO_BORRADOR);

        $this->idsVistos = $query->pluck('id')
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
        $listeners = [];
        if ($comercioId !== null) {
            $listeners["echo-private:comercios.{$comercioId}.pedidos-delivery,.PedidoDeliveryBroadcast"] = 'onPedidoBroadcast';
        }

        // Listeners de los traits CajaAware/SucursalAware se agregan manualmente
        // porque este metodo sobreescribe al de los traits. handleCajaChanged y
        // handleSucursalChangedFromCaja viven en CajaAware.
        return array_merge($listeners, [
            'caja-changed' => 'handleCajaChanged',
            'sucursal-changed' => 'handleSucursalChangedFromCaja',
            'sucursal-cambiada' => 'handleSucursalChangedFromCaja',
        ]);
    }

    /**
     * Handler del evento broadcast PedidoDeliveryBroadcast.
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

        if ($tipo === \App\Events\Broadcasting\PedidoDeliveryBroadcast::TIPO_CREADO
            && ! in_array($pedidoId, $this->idsVistos, true)) {
            $this->idsVistos[] = $pedidoId;
            $this->nuevosCount++;
        }

        // Notifica al frontend para resaltar visualmente la fila/card del pedido
        // hasta que el usuario interactue con ella (Alpine local, sin tocar
        // estado server-side). Aplica a TODOS los tipos: creado, estado_cambiado,
        // pago_cambiado, etc. — cualquier cambio en vivo merece destacarse.
        $this->dispatch('pedido-destacado', pedidoId: $pedidoId);

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
        $this->resetEstadoComponente();
        $this->snapshotIdsVistos();
        $this->nuevosCount = 0;
    }

    /**
     * Limpia estado modal-side al cambiar caja (igual que sucursal). Refresca
     * el snapshot porque cambia el universo de pedidos visibles.
     */
    protected function onCajaChanged($cajaId, $cajaNombre): void
    {
        $this->resetEstadoComponente();
        $this->snapshotIdsVistos();
        $this->nuevosCount = 0;
    }

    /**
     * Helper compartido entre cambio de sucursal y cambio de caja: cierra
     * todos los modales abiertos y resetea estado intermedio.
     */
    protected function resetEstadoComponente(): void
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
        $this->cerrarAsignarRepartidor();
        $this->cerrarArmarSalida();
        $this->cerrarVuelta();
        $this->cerrarAceptar();
        $this->cerrarRechazar();
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
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        // Estados terminales (cancelado/facturado): no se editan nunca.
        if (in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_CANCELADO, PedidoDelivery::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __("El pedido en estado ':estado' no se puede editar", ['estado' => $pedido->estado_pedido]));

            return;
        }

        // Mientras el cliente no haya pagado, el operario puede ajustar el
        // carrito en cualquier punto del flujo (en_preparacion, listo, etc).
        // Solo bloqueamos si ya hay cobros materializados: esos pedidos se
        // gestionan desde "Cobrar pendiente" para no romper la trazabilidad
        // de caja. Borradores siempre se pueden continuar.
        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR
            && $pedido->estado_pago !== PedidoDelivery::ESTADO_PAGO_PENDIENTE) {
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
     * NuevoPedidoDelivery (cancelar sin guardar).
     */
    #[\Livewire\Attributes\On('cerrar-modal-pedido')]
    public function cerrarModalNuevoPedido(): void
    {
        $this->modalNuevoPedidoAbierto = false;
        $this->pedidoIdEnEdicion = null;
    }

    /**
     * Listener para el evento `pedido-guardado` despachado por
     * NuevoPedidoDelivery (alta/edición exitosa). Cierra el modal y refresca
     * la lista — la query del render() se ejecuta de nuevo automáticamente.
     */
    #[\Livewire\Attributes\On('pedido-guardado')]
    public function tras_guardar_pedido(): void
    {
        $this->modalNuevoPedidoAbierto = false;
        $this->pedidoIdEnEdicion = null;
        $this->resetPage();
    }

    /**
     * Abre el modal de cobro rapido para un pedido editable sin pagos
     * planificados. Monta <livewire:nuevo-pedido-delivery> con
     * modoCobroRapido=true, que muestra SOLO el modal de desglose
     * superpuesto al listado (no entra al editor full-screen).
     */
    public function abrirCobroRapido(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.cobrar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cobrar pedidos'));

            return;
        }

        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_CANCELADO, PedidoDelivery::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no acepta pagos'));

            return;
        }

        $this->cobroRapidoKey++;
        $this->pedidoCobroRapidoId = $pedidoId;
        // Cerrar el modal "Cobrar pendiente" si estaba abierto (se invoca
        // desde el boton "Definir pagos" del modal parcial).
        $this->showCobrarModal = false;
        // Cerrar el modal de detalle si estaba abierto.
        $this->showDetalleModal = false;
        $this->pedidoDetalleId = null;
    }

    /**
     * Listener para `cobro-rapido-completado` despachado por
     * NuevoPedidoDelivery al confirmar el desglose. Cierra el sub-componente,
     * refresca la lista, y si había una acción pendiente (entregar / convertir)
     * y el pedido quedó 100% cobrado, la reanuda. Cobros parciales NO disparan
     * la acción — el usuario tiene que reintentar cuando termine de cobrar.
     */
    #[\Livewire\Attributes\On('cobro-rapido-completado')]
    public function trasCobroRapidoCompletado(): void
    {
        $this->pedidoCobroRapidoId = null;
        $this->reanudarAccionPendienteSiCobrado();
        $this->resetPage();
    }

    /**
     * Listener para `cerrar-cobro-rapido` despachado por NuevoPedidoDelivery
     * cuando el usuario cierra el modal sin confirmar. Descarta cualquier
     * acción pendiente acumulada.
     */
    #[\Livewire\Attributes\On('cerrar-cobro-rapido')]
    public function trasCerrarCobroRapido(): void
    {
        $this->pedidoCobroRapidoId = null;
        $this->accionPendiente = null;
        $this->accionPendientePedidoId = null;
    }

    /**
     * Devuelve true si el pedido tiene cobertura completa: cobrado + planificado
     * cubre el total. Total cero (pedidos invitación) se considera cubierto.
     * Pedidos con planificados al 100% se consideran cubiertos porque la
     * conversión a venta los materializa — no tiene sentido pedir al operario
     * que vuelva a desglosar lo que ya armó al alta.
     */
    protected function pedidoEstaCobrado(PedidoDelivery $pedido): bool
    {
        $total = (float) $pedido->total_final;
        if ($total <= 0.005) {
            return true;
        }

        $cubierto = (float) $pedido->total_cobrado + (float) $pedido->total_planificado;

        return $cubierto + 0.005 >= $total;
    }

    /**
     * Si el pedido tiene saldo pendiente, guarda la acción solicitada y abre
     * el modal de cobro rápido. Cuando el cobro termine y el pedido quede 100%
     * cobrado, `trasCobroRapidoCompletado` reanuda la acción automáticamente.
     * Retorna true cuando interceptó (el caller debe abortar su flujo).
     */
    protected function gatearPorCobro(PedidoDelivery $pedido, string $accion): bool
    {
        if ($this->pedidoEstaCobrado($pedido)) {
            return false;
        }

        $this->accionPendiente = $accion;
        $this->accionPendientePedidoId = $pedido->id;
        $this->abrirCobroRapido($pedido->id);

        return true;
    }

    /**
     * Si hay una acción pendiente y el pedido quedó 100% cobrado, la ejecuta.
     * Si quedó parcial o cancelado el cobro, descarta la acción silenciosamente.
     */
    protected function reanudarAccionPendienteSiCobrado(): void
    {
        if (! $this->accionPendiente || ! $this->accionPendientePedidoId) {
            return;
        }

        $accion = $this->accionPendiente;
        $pedidoId = $this->accionPendientePedidoId;
        $this->accionPendiente = null;
        $this->accionPendientePedidoId = null;

        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->pedidoEstaCobrado($pedido)) {
            return;
        }

        match ($accion) {
            'convertir' => $this->abrirConvertir($pedidoId),
            default => null,
        };
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

    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRepartidor(): void
    {
        $this->resetPage();
    }

    public function updatingFilterOrigen(): void
    {
        $this->resetPage();
    }

    public function updatingFilterZona(): void
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

    /**
     * Ordenar el listado por columna. Un click ordena ascendente por ese campo;
     * volver a clickear la misma columna alterna a descendente (y viceversa).
     * Campos válidos: numero, cliente, fecha, total_final, estado_pedido, estado_pago.
     */
    public function sortBy(string $field): void
    {
        $permitidos = ['numero', 'cliente', 'fecha', 'total_final', 'estado_pedido', 'estado_pago', 'tipo'];
        if (! in_array($field, $permitidos, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Alterna la dirección del orden actual sin cambiar el campo. Lo usa el
     * control de orden mobile (selector de campo + botón de dirección).
     */
    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function updatingSortField(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterEstadoPedido = 'activos';
        $this->filterEstadoPago = 'all';
        $this->filterTipo = 'all';
        $this->filterRepartidor = 'all';
        $this->filterOrigen = 'all';
        $this->filterZona = 'all';
        $this->filterFechaDesde = now()->subDays(7)->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
        $this->resetPage();
    }

    protected function obtenerPedidos()
    {
        $query = PedidoDelivery::with([
            'cliente:id,nombre,telefono',
            'sucursal:id,nombre',
            'caja:id,nombre',
            'repartidor:id,nombre',
            'zona:id,nombre',
            'pagos' => fn ($q) => $q->whereIn('estado', [
                PedidoDeliveryPago::ESTADO_ACTIVO,
                PedidoDeliveryPago::ESTADO_PLANIFICADO,
            ]),
        ])
            ->where('sucursal_id', $this->sucursalActual());

        $this->aplicarFiltrosDelivery($query);

        // Estado del pedido. Los BORRADORES nunca aparecen en la tabla
        // principal — viven en su propio desplegable arriba (obtenerBorradores).
        if ($this->filterEstadoPedido === 'activos') {
            $query->activos()->where('estado_pedido', '!=', PedidoDelivery::ESTADO_BORRADOR);
        } elseif ($this->filterEstadoPedido === 'borrador') {
            // Caso edge: si el usuario eligió "borrador" en el filtro, lo
            // dejamos pasar (puede querer auditarlos).
            $query->where('estado_pedido', PedidoDelivery::ESTADO_BORRADOR);
        } elseif ($this->filterEstadoPedido !== 'all') {
            $query->where('estado_pedido', $this->filterEstadoPedido);
        } else {
            $query->where('estado_pedido', '!=', PedidoDelivery::ESTADO_BORRADOR);
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
            $this->aplicarBusqueda($query);
        }

        $this->aplicarOrden($query);

        return $query->paginate(15);
    }

    /**
     * Filtros propios de delivery (RF-01): tipo, repartidor, origen y zona.
     * Compartidos entre la lista y el kanban.
     */
    protected function aplicarFiltrosDelivery($query): void
    {
        if ($this->filterTipo !== 'all') {
            $query->where('tipo', $this->filterTipo);
        }
        if ($this->filterRepartidor !== 'all') {
            $this->filterRepartidor === 'sin_asignar'
                ? $query->whereNull('repartidor_id')
                : $query->where('repartidor_id', (int) $this->filterRepartidor);
        }
        if ($this->filterOrigen !== 'all') {
            $query->where('origen', $this->filterOrigen);
        }
        if ($this->filterZona !== 'all') {
            $query->where('zona_id', (int) $this->filterZona);
        }
    }

    /**
     * Búsqueda libre compartida lista/kanban: número, cliente (vinculado o
     * temporal), teléfono y dirección de entrega.
     */
    protected function aplicarBusqueda($query): void
    {
        $term = trim($this->search);
        $query->where(function ($q) use ($term) {
            if (is_numeric($term)) {
                $q->orWhere('numero', $term);
            }
            $q->orWhere('nombre_cliente_temporal', 'like', "%{$term}%")
                ->orWhere('telefono_cliente_temporal', 'like', "%{$term}%")
                ->orWhere('direccion_entrega', 'like', "%{$term}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$term}%")
                    ->orWhere('telefono', 'like', "%{$term}%"));
        });
    }

    /**
     * Aplica el orden dinámico según $sortField / $sortDirection. Los estados se
     * ordenan por su orden lógico (constantes del modelo), no alfabético; el
     * cliente por su nombre efectivo (cliente vinculado o nombre temporal).
     */
    protected function aplicarOrden($query): void
    {
        $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        switch ($this->sortField) {
            case 'numero':
            case 'total_final':
            case 'tipo':
                $query->orderBy($this->sortField, $dir);
                break;

            case 'estado_pedido':
                $orden = $this->fieldOrderSql('estado_pedido', array_keys(PedidoDelivery::ESTADOS));
                $query->orderByRaw("{$orden} {$dir}");
                break;

            case 'estado_pago':
                $orden = $this->fieldOrderSql('estado_pago', array_keys(PedidoDelivery::ESTADOS_PAGO));
                $query->orderByRaw("{$orden} {$dir}");
                break;

            case 'cliente':
                // Nombre efectivo: cliente vinculado o nombre temporal. La tabla
                // clientes lleva prefijo tenant en la conexión, así que lo
                // anteponemos a mano para la subconsulta correlacionada (SQL raw).
                $prefix = DB::connection('pymes_tenant')->getTablePrefix();
                $tabla = $prefix.(new Cliente)->getTable();
                $query->orderByRaw("COALESCE((SELECT nombre FROM {$tabla} WHERE {$tabla}.id = cliente_id), nombre_cliente_temporal) {$dir}");
                break;

            case 'fecha':
            default:
                $query->orderBy('fecha', $dir);
                break;
        }

        $query->orderByDesc('id');
    }

    /**
     * Construye una expresión FIELD(columna, 'v1','v2',...) para ordenar por el
     * orden lógico de un enum. Los valores provienen de constantes del modelo,
     * no de input del usuario, pero los citamos igual de forma segura.
     */
    protected function fieldOrderSql(string $columna, array $valores): string
    {
        $lista = implode(',', array_map(fn ($v) => DB::connection('pymes_tenant')->getPdo()->quote($v), $valores));

        return "FIELD({$columna}, {$lista})";
    }

    /**
     * Borradores de la sucursal: pedidos pre-cargados sin número ni stock,
     * que el usuario quiere retomar después. Se listan en un desplegable
     * separado encima de la tabla principal.
     */
    protected function obtenerBorradores()
    {
        $query = PedidoDelivery::with(['cliente:id,nombre,telefono'])
            ->where('sucursal_id', $this->sucursalActual())
            ->where('estado_pedido', PedidoDelivery::ESTADO_BORRADOR)
            // Los borradores EXTERNOS son "por aceptar" (D14) y tienen su
            // propio strip con aceptar/rechazar — no van en este dropdown.
            ->where('origen', PedidoDelivery::ORIGEN_PANEL);

        return $query->orderByDesc('updated_at')->limit(50)->get();
    }

    // ==================== KANBAN ====================

    /**
     * 5 estados que se muestran como columnas Kanban (RF-03: + en_camino).
     * Cancelados/Facturados quedan fuera (terminales o solo vista Lista).
     */
    public const ESTADOS_KANBAN = [
        PedidoDelivery::ESTADO_CONFIRMADO,
        PedidoDelivery::ESTADO_EN_PREPARACION,
        PedidoDelivery::ESTADO_LISTO,
        PedidoDelivery::ESTADO_EN_CAMINO,
        PedidoDelivery::ESTADO_ENTREGADO,
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

        $query = PedidoDelivery::with([
            'cliente:id,nombre,telefono',
            'repartidor:id,nombre',
            'zona:id,nombre',
            'pagos' => fn ($q) => $q->whereIn('estado', [
                PedidoDeliveryPago::ESTADO_ACTIVO,
                PedidoDeliveryPago::ESTADO_PLANIFICADO,
            ]),
        ])
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado_pedido', self::ESTADOS_KANBAN);

        $this->aplicarFiltrosDelivery($query);

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
            $this->aplicarBusqueda($query);
        }

        // Orden Kanban: prioridad al `orden_kanban` (que el usuario manipula con
        // drag dentro de la misma columna). Tiebreak por id DESC para casos en
        // que dos pedidos compartan orden_kanban (no deberia, pero por las dudas).
        $pedidos = $query->orderByDesc('orden_kanban')->orderByDesc('id')->limit(200)->get();

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
        $pedido = PedidoDelivery::find($pedidoId);
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

        $transiciones = PedidoDelivery::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];
        if (! in_array($nuevoEstado, $transiciones, true)) {
            $this->dispatch('toast-error', message: __('Transición :de → :a no permitida', [
                'de' => $pedido->estado_pedido,
                'a' => $nuevoEstado,
            ]));
            $this->dispatch('kanban-revertir');

            return;
        }

        // Intercepciones delivery (RF-08): despachar SIEMPRE crea la salida
        // (implícita de 1 pedido) y entregar desde la calle pasa por la vuelta
        // (registra cobros contra entrega). El drag es solo el gesto.
        if ($nuevoEstado === PedidoDelivery::ESTADO_EN_CAMINO) {
            $this->dispatch('kanban-revertir'); // El render con el estado real re-ubica la card.
            $this->despachar($pedido->id);

            return;
        }

        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_EN_CAMINO
            && $nuevoEstado === PedidoDelivery::ESTADO_ENTREGADO
            && $pedido->salida_id) {
            $this->dispatch('kanban-revertir');
            $this->abrirVuelta((int) $pedido->salida_id);

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

    /**
     * Persiste el orden manual de una columna Kanban (drag dentro del mismo
     * estado). Lo invoca SortableJS via $wire desde kanban.js. NO dispatchea
     * broadcast: el reordenamiento es preferencia local del operador, otras
     * terminales mantienen su propio orden hasta el proximo refresh.
     *
     * @param  array<int>  $idsOrdenados  IDs de la columna en orden visible (top → bottom).
     */
    public function reordenarColumna(string $estado, array $idsOrdenados): void
    {
        try {
            $this->service->reordenarColumna(
                sucursalId: (int) $this->sucursalActual(),
                cajaId: $this->cajaActual(),
                estado: $estado,
                idsOrdenados: $idsOrdenados,
            );
        } catch (Exception $e) {
            Log::error('Error al reordenar columna Kanban', [
                'estado' => $estado,
                'ids' => $idsOrdenados,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: __('No se pudo guardar el nuevo orden'));
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
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $transiciones = PedidoDelivery::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];

        // Excluir CANCELADO acá (tiene su propio modal con motivo) y FACTURADO
        // (solo se llega vía convertirEnVenta).
        $transiciones = array_values(array_filter($transiciones, fn ($e) => ! in_array($e, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
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
        $pedido = PedidoDelivery::find($this->pedidoEstadoId);
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
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $transiciones = PedidoDelivery::TRANSICIONES_PERMITIDAS[$pedido->estado_pedido] ?? [];
        if (! in_array(PedidoDelivery::ESTADO_ENTREGADO, $transiciones, true)) {
            $this->dispatch('toast-error', message: __("No se puede marcar como entregado desde el estado ':estado'", ['estado' => $pedido->estado_pedido]));

            return;
        }

        // Un pedido en la calle se entrega registrando la VUELTA de su salida
        // (ahí se cargan los cobros contra entrega, D13).
        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_EN_CAMINO && $pedido->salida_id) {
            $this->abrirVuelta((int) $pedido->salida_id);

            return;
        }

        try {
            $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_ENTREGADO);
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
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.cobrar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cobrar pedidos'));

            return;
        }

        $pedido = PedidoDelivery::with('pagos')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_CANCELADO, PedidoDelivery::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no acepta pagos'));

            return;
        }

        $planificados = $pedido->pagos->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO);

        if ($planificados->isEmpty()) {
            // Sin pagos planificados: abrir directo el modal de desglose
            // (NuevoPedidoDelivery en modoCobroRapido=true). El cobro rápido
            // ahora acepta cualquier estado activo del pedido, asi que no
            // bifurcamos por "es editable" — siempre se puede cobrar saldo
            // pendiente independiente del estado_pedido.
            $this->abrirCobroRapido($pedidoId);

            return;
        }

        // Si alguno de los planificados usa una forma de pago con integración
        // (QR), NO los confirmamos en lote: cada pago QR necesita su propio
        // cobro con espera de confirmación. Abrimos "Cobrar pendiente" para
        // confirmarlos de a uno (los integrados disparan el QR).
        $fpIntegradas = FormaPago::whereIn('id', $planificados->pluck('forma_pago_id')->unique())
            ->get()
            ->filter->tieneIntegracion();

        if ($fpIntegradas->isNotEmpty()) {
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

    /**
     * Encapsula la regla de edición: cualquier estado activo (no cancelado ni
     * facturado) y sin cobros materializados (estado_pago=pendiente) se puede
     * editar. La edición es independiente del avance del flujo: mientras el
     * cliente no haya pagado, el operario puede ajustar el carrito.
     */
    public function pedidoEsEditable(PedidoDelivery $pedido): bool
    {
        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
        ], true)) {
            return false;
        }

        return $pedido->estado_pago === PedidoDelivery::ESTADO_PAGO_PENDIENTE;
    }

    // ==================== ACEPTACION DE EXTERNOS (D14/RF-12) ====================

    public bool $showAceptarModal = false;

    public ?int $pedidoAceptarId = null;

    public array $aceptarInfo = [];

    public bool $showRechazarModal = false;

    public ?int $pedidoRechazarId = null;

    public string $motivoRechazo = '';

    /**
     * Pedidos externos "por aceptar": borradores con origen tienda/api (D14,
     * patrón borrador — sin número ni stock, precios cotizados snapshot).
     */
    protected function pedidosPorAceptar()
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            return collect();
        }

        return PedidoDelivery::with(['cliente:id,nombre,telefono'])
            ->where('sucursal_id', $sucursalId)
            ->where('estado_pedido', PedidoDelivery::ESTADO_BORRADOR)
            ->where('origen', '!=', PedidoDelivery::ORIGEN_PANEL)
            ->orderBy('created_at')
            ->get();
    }

    public function abrirAceptar(int $pedidoId): void
    {
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $config = app(\App\Services\Pedidos\DeliveryEnvioService::class)
            ->configDelivery(\App\Models\Sucursal::findOrFail($pedido->sucursal_id));

        $this->pedidoAceptarId = $pedidoId;
        $this->aceptarInfo = [
            'numero' => $pedido->numero_visible,
            'cliente' => $pedido->nombre_cliente_final ?? __('Sin cliente'),
            'tipo' => $pedido->tipo,
            'total' => (float) $pedido->total_final,
            'modo_promesa_manual' => ($config['modo_promesa'] ?? 'manual') === 'manual',
            'botones_demora' => $config['botones_demora'] ?? [0, 10, 15, 20, 30, 45, 60],
        ];
        $this->showAceptarModal = true;
    }

    /**
     * Acepta el pedido externo. En modo promesa manual, `$demoraMin` viene
     * del botón elegido (RF-15); en automática se calcula por distancia.
     */
    public function confirmarAceptar(?int $demoraMin = null): void
    {
        $pedido = PedidoDelivery::find($this->pedidoAceptarId);
        if (! $pedido) {
            return;
        }

        try {
            $this->service->aceptarPedidoExterno($pedido, $demoraMin);
            $this->dispatch('toast-success', message: __('Pedido #:numero aceptado', ['numero' => $pedido->fresh()->numero_visible]));
            $this->cerrarAceptar();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarAceptar(): void
    {
        $this->showAceptarModal = false;
        $this->pedidoAceptarId = null;
        $this->aceptarInfo = [];
    }

    public function abrirRechazar(int $pedidoId): void
    {
        $this->pedidoRechazarId = $pedidoId;
        $this->motivoRechazo = '';
        $this->showRechazarModal = true;
    }

    public function confirmarRechazar(): void
    {
        $this->validate([
            'motivoRechazo' => 'required|string|min:5',
        ], [
            'motivoRechazo.required' => __('Ingresá el motivo del rechazo'),
            'motivoRechazo.min' => __('El motivo debe tener al menos 5 caracteres'),
        ]);

        $pedido = PedidoDelivery::find($this->pedidoRechazarId);
        if (! $pedido) {
            return;
        }

        try {
            $resultado = $this->service->rechazarPedidoExterno($pedido, trim($this->motivoRechazo));

            if (! empty($resultado['a_devolver'])) {
                $this->dispatch('toast-warning', message: __('Pedido rechazado: tenía pago online acreditado — queda A DEVOLVER (devolución manual)'));
            } else {
                $this->dispatch('toast-success', message: __('Pedido rechazado'));
            }
            $this->cerrarRechazar();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarRechazar(): void
    {
        $this->showRechazarModal = false;
        $this->pedidoRechazarId = null;
        $this->motivoRechazo = '';
    }

    // ==================== REPARTIDOR (RF-08) ====================

    /**
     * Repartidores activos habilitados en la sucursal actual (selector de
     * asignación, armar salida y filtro).
     */
    protected function repartidoresDisponibles()
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            return collect();
        }

        return Repartidor::activos()
            ->porSucursal((int) $sucursalId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);
    }

    public function abrirAsignarRepartidor(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para gestionar repartidores'));

            return;
        }

        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
            $this->dispatch('toast-error', message: __('Los pedidos take-away no llevan repartidor'));

            return;
        }

        $this->pedidoRepartidorId = $pedidoId;
        $this->repartidorSeleccionadoId = (string) ($pedido->repartidor_id ?? '');
        $this->showRepartidorModal = true;
    }

    public function confirmarAsignarRepartidor(): void
    {
        $pedido = PedidoDelivery::find($this->pedidoRepartidorId);
        if (! $pedido) {
            return;
        }

        try {
            $this->service->asignarRepartidor(
                $pedido,
                $this->repartidorSeleccionadoId !== '' ? (int) $this->repartidorSeleccionadoId : null,
            );
            $this->dispatch('toast-success', message: $this->repartidorSeleccionadoId !== ''
                ? __('Repartidor asignado')
                : __('Repartidor desasignado'));
            $this->cerrarAsignarRepartidor();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarAsignarRepartidor(): void
    {
        $this->showRepartidorModal = false;
        $this->pedidoRepartidorId = null;
        $this->repartidorSeleccionadoId = '';
    }

    // ==================== DESPACHAR (RF-08: salida implícita) ====================

    /**
     * Pasa un pedido listo → en_camino. Con repartidor asignado crea y
     * registra la salida implícita de 1 pedido (RepartidorService); sin
     * repartidor y con `exigir_repartidor` desactivado, cambia el estado
     * directo (no hay circuito de fondo posible).
     */
    public function despachar(int $pedidoId): void
    {
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_LISTO) {
            $this->dispatch('toast-error', message: __("Solo se despachan pedidos listos (estado actual: ':estado')", ['estado' => $pedido->estado_pedido]));

            return;
        }

        try {
            if ($pedido->repartidor_id) {
                $this->repartidorService->despacharPedido($pedido);
            } else {
                // Sin repartidor: cambiarEstado valida exigir_repartidor y
                // rechaza con mensaje claro si la sucursal lo exige.
                $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_CAMINO);
            }
            $this->dispatch('toast-success', message: __('Pedido despachado'));
        } catch (Exception $e) {
            Log::error('Error al despachar pedido delivery', [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== ARMAR SALIDA (RF-08) ====================

    public function abrirArmarSalida(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para gestionar repartidores'));

            return;
        }

        $this->salidaRepartidorId = '';
        $this->salidaPedidosSeleccionados = [];
        $this->showArmarSalidaModal = true;
    }

    public function cerrarArmarSalida(): void
    {
        $this->showArmarSalidaModal = false;
        $this->salidaRepartidorId = '';
        $this->salidaPedidosSeleccionados = [];
    }

    /**
     * Pedidos candidatos a una salida: delivery, listos y sin salida actual.
     */
    protected function pedidosParaSalida()
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            return collect();
        }

        return PedidoDelivery::with(['cliente:id,nombre', 'repartidor:id,nombre', 'zona:id,nombre'])
            ->where('sucursal_id', $sucursalId)
            ->where('tipo', PedidoDelivery::TIPO_DELIVERY)
            ->where('estado_pedido', PedidoDelivery::ESTADO_LISTO)
            ->whereNull('salida_id')
            ->orderBy('listo_at')
            ->get();
    }

    /**
     * Crea la salida con los pedidos tildados y la registra en el acto
     * (listo → en_camino): el flujo real es "el cadete se va ya". El armado
     * en dos tiempos queda para la API/automatización.
     */
    public function confirmarArmarSalida(): void
    {
        $pedidoIds = array_keys(array_filter($this->salidaPedidosSeleccionados));

        if ($this->salidaRepartidorId === '' || empty($pedidoIds)) {
            $this->dispatch('toast-error', message: __('Elegí un repartidor y al menos un pedido'));

            return;
        }

        try {
            $salida = $this->repartidorService->crearSalida(
                sucursalId: (int) $this->sucursalActual(),
                repartidorId: (int) $this->salidaRepartidorId,
                pedidoIds: array_map('intval', $pedidoIds),
            );
            $this->repartidorService->registrarSalida($salida);

            $this->dispatch('toast-success', message: __('Salida registrada: :n pedido(s) en camino', ['n' => count($pedidoIds)]));
            $this->cerrarArmarSalida();
        } catch (Exception $e) {
            Log::error('Error al armar salida', [
                'repartidor_id' => $this->salidaRepartidorId,
                'pedidos' => $pedidoIds,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== REGISTRAR VUELTA (RF-08/D13) ====================

    /**
     * Salidas en camino de la sucursal (sección "salidas en curso").
     */
    protected function salidasEnCurso()
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            return collect();
        }

        return DeliverySalida::with(['repartidor:id,nombre', 'pedidosActuales:id,salida_id'])
            ->where('sucursal_id', $sucursalId)
            ->where('estado', DeliverySalida::ESTADO_EN_CAMINO)
            ->orderBy('salida_at')
            ->get();
    }

    /**
     * Abre el modal de vuelta de una salida: por pedido precarga resultado
     * "entregado" + sus pagos planificados como cobros a confirmar (los de
     * efectivo van al fondo del repartidor, D13).
     */
    public function abrirVuelta(int $salidaId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para gestionar repartidores'));

            return;
        }

        $salida = DeliverySalida::with([
            'repartidor',
            'pedidosActuales.pagos' => fn ($q) => $q->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO),
            'pedidosActuales.pagos.formaPago.conceptoPago',
            'pedidosActuales.cliente:id,nombre',
        ])->find($salidaId);

        if (! $salida || ! $this->tieneAccesoASucursal($salida->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Salida no encontrada'));

            return;
        }

        if ($salida->estado !== DeliverySalida::ESTADO_EN_CAMINO) {
            $this->dispatch('toast-error', message: __('La salida no está en camino'));

            return;
        }

        $fondo = $salida->repartidor->fondoAbierto((int) $salida->sucursal_id);

        $this->vueltaSalidaId = $salida->id;
        $this->vueltaResultados = [];
        $this->vueltaCobros = [];

        $pedidosInfo = [];
        $hayEfectivo = false;

        foreach ($salida->pedidosActuales as $pedido) {
            $this->vueltaResultados[$pedido->id] = [
                'resultado' => 'entregado',
                'motivo' => '',
            ];

            $pagosInfo = [];
            foreach ($pedido->pagos as $pago) {
                $esEfectivo = strtoupper((string) $pago->formaPago?->conceptoPago?->codigo) === 'EFECTIVO';
                $hayEfectivo = $hayEfectivo || $esEfectivo;

                $this->vueltaCobros[$pago->id] = [
                    'pedido_id' => $pedido->id,
                    'cobrar' => true,
                    'monto_recibido' => (string) $pago->monto_final,
                ];

                $pagosInfo[] = [
                    'id' => $pago->id,
                    'forma_pago' => $pago->formaPago?->nombre ?? __('Sin especificar'),
                    'monto_final' => (float) $pago->monto_final,
                    'es_efectivo' => $esEfectivo,
                ];
            }

            $pedidosInfo[] = [
                'id' => $pedido->id,
                'numero' => $pedido->numero,
                'cliente' => $pedido->nombre_cliente_final ?? __('Sin cliente'),
                'direccion' => $pedido->direccion_entrega,
                'total' => (float) $pedido->total_final,
                'pagos' => $pagosInfo,
            ];
        }

        $this->vueltaInfo = [
            'salida_id' => $salida->id,
            'repartidor' => $salida->repartidor->nombre,
            'salida_at' => $salida->salida_at?->format('d/m H:i'),
            'fondo_abierto' => $fondo !== null,
            'hay_efectivo' => $hayEfectivo,
            'pedidos' => $pedidosInfo,
        ];
        $this->showVueltaModal = true;
    }

    public function cerrarVuelta(): void
    {
        $this->showVueltaModal = false;
        $this->vueltaSalidaId = null;
        $this->vueltaResultados = [];
        $this->vueltaCobros = [];
        $this->vueltaInfo = [];
    }

    public function confirmarVuelta(): void
    {
        $salida = DeliverySalida::find($this->vueltaSalidaId);
        if (! $salida) {
            return;
        }

        // Armar el payload del service: resultado + cobros tildados por pedido.
        $resultados = [];
        foreach ($this->vueltaResultados as $pedidoId => $res) {
            $resultados[(int) $pedidoId] = [
                'resultado' => $res['resultado'],
                'motivo' => trim($res['motivo'] ?? '') ?: null,
                'cobros' => [],
            ];
        }

        foreach ($this->vueltaCobros as $pagoId => $cobro) {
            $pedidoId = (int) ($cobro['pedido_id'] ?? 0);
            if (! ($cobro['cobrar'] ?? false) || ! isset($resultados[$pedidoId])) {
                continue;
            }
            if (($resultados[$pedidoId]['resultado'] ?? '') !== 'entregado') {
                continue; // No entregado: sin cobros (el service lo rechaza).
            }
            $resultados[$pedidoId]['cobros'][] = [
                'pago_id' => (int) $pagoId,
                'monto_recibido' => ($cobro['monto_recibido'] ?? '') !== ''
                    ? (float) $cobro['monto_recibido']
                    : null,
            ];
        }

        try {
            $this->repartidorService->registrarVuelta(
                $salida,
                $resultados,
                cajaConversionId: $this->cajaActual(),
            );
            $this->dispatch('toast-success', message: __('Vuelta registrada'));
            $this->cerrarVuelta();
        } catch (Exception $e) {
            Log::error('Error al registrar vuelta', [
                'salida_id' => $this->vueltaSalidaId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== CANCELAR ====================

    public function abrirCancelar(int $pedidoId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.cancelar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cancelar pedidos'));

            return;
        }

        $pedido = PedidoDelivery::with('cliente:id,nombre')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_CANCELADO, PedidoDelivery::ESTADO_FACTURADO], true)) {
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
            'tiene_pagos_activos' => $pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)->exists(),
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

        $pedido = PedidoDelivery::find($this->pedidoCancelarId);
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
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.cobrar')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para cobrar pedidos'));

            return;
        }

        $pedido = PedidoDelivery::with(['pagos.formaPago:id,nombre'])
            ->find($pedidoId);

        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_CANCELADO, PedidoDelivery::ESTADO_FACTURADO], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no acepta pagos'));

            return;
        }

        $planificados = $pedido->pagos
            ->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)
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
        $pago = PedidoDeliveryPago::find($pagoId);
        if (! $pago || $pago->pedido_delivery_id !== $this->pedidoCobrarId) {
            $this->dispatch('toast-error', message: __('Pago no encontrado'));

            return;
        }

        // Si la forma de pago tiene integración (QR), NO materializamos directo:
        // disparamos el cobro por QR y esperamos. El pago se materializa recién
        // al aprobarse (alConfirmarCobroIntegracion); si sale negativo queda
        // planificado y editable.
        $formaPago = FormaPago::find($pago->forma_pago_id);
        if ($formaPago && $formaPago->tieneIntegracion()) {
            $this->iniciarCobroIntegracionPagoPlanificado($pago);

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

    /**
     * Dispara el cobro por QR de un pago planificado con forma de pago
     * integrada. Cierra el modal "Cobrar pendiente" mientras se muestra el QR
     * (se reabre al confirmar o al cancelar). El pedido aporta sucursal y caja
     * para resolver la integración y el POS.
     */
    protected function iniciarCobroIntegracionPagoPlanificado(PedidoDeliveryPago $pago): void
    {
        $pedido = $pago->pedido()->first();
        if (! $pedido) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $this->cobroIntegracionPagoPlanificadoId = $pago->id;
        $this->showCobrarModal = false;

        $this->iniciarCobroIntegracion([
            'forma_pago_id' => $pago->forma_pago_id,
            'monto' => (float) $pago->monto_final,
            'moneda_id' => $pago->moneda_id,
            'sucursal_id' => $pedido->sucursal_id,
            'caja_id' => $pedido->caja_id ?? caja_activa(),
        ]);

        // Si iniciarCobroIntegracion no abrió el modal (FP sin integración
        // configurada en la sucursal, etc.), reabrimos el "Cobrar pendiente".
        if (! $this->mostrarModalEsperandoPago) {
            $this->cobroIntegracionPagoPlanificadoId = null;
            $this->abrirCobrar($pago->pedido_delivery_id);
        }
    }

    /**
     * Hook del concern: el cobro QR del pago planificado se aprobó. Materializa
     * ese pago (lo pasa a activo, toca caja, recalcula estado_pago) y asocia la
     * transacción QR al pedido. Reabre "Cobrar pendiente" con el estado fresco.
     */
    protected function alConfirmarCobroIntegracion(): void
    {
        $pagoId = $this->cobroIntegracionPagoPlanificadoId;
        $pago = $pagoId ? PedidoDeliveryPago::find($pagoId) : null;

        if (! $pago) {
            $this->resetCobroIntegracion();
            $this->cobroIntegracionPagoPlanificadoId = null;

            return;
        }

        $pedidoId = $pago->pedido_delivery_id;

        try {
            $this->service->confirmarPagoPlanificado($pago);

            $pedido = PedidoDelivery::find($pedidoId);
            if ($pedido) {
                $this->asociarCobroIntegracionAlCobrable($pedido);
            }

            $this->dispatch('toast-success', message: __('Pago confirmado'));
        } catch (Exception $e) {
            Log::error('Error al confirmar pago planificado por QR', [
                'pago_id' => $pagoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        } finally {
            $this->resetCobroIntegracion();
            $this->cobroIntegracionPagoPlanificadoId = null;
        }

        // Reabrir el modal con el estado actualizado para seguir cobrando los
        // pagos planificados restantes (o cerrarlo si ya no quedan).
        $this->abrirCobrar($pedidoId);
    }

    /**
     * Hook del concern de cobro QR: el pago planificado se canceló/expiró, no se
     * materializó nada. Reabrimos "Cobrar pendiente" para reintentar o editar.
     * Solo aplica si había un pago planificado en cobro (el cobro rápido del
     * sub-componente maneja su propio caso por separado).
     */
    protected function alCancelarCobroIntegracion(): void
    {
        if ($this->cobroIntegracionPagoPlanificadoId === null) {
            return;
        }

        $pago = PedidoDeliveryPago::find($this->cobroIntegracionPagoPlanificadoId);
        $this->cobroIntegracionPagoPlanificadoId = null;

        if ($pago) {
            $this->abrirCobrar($pago->pedido_delivery_id);
        }
    }

    public function eliminarPagoPlanificado(int $pagoId): void
    {
        $pago = PedidoDeliveryPago::find($pagoId);
        if (! $pago || $pago->pedido_delivery_id !== $this->pedidoCobrarId) {
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
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.convertir_venta')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para convertir pedidos en venta'));

            return;
        }

        $pedido = PedidoDelivery::with('cliente:id,nombre')->find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
            PedidoDelivery::ESTADO_BORRADOR,
        ], true)) {
            $this->dispatch('toast-error', message: __('Este pedido no se puede convertir en venta'));

            return;
        }

        if ($this->gatearPorCobro($pedido, 'convertir')) {
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
        $pedido = PedidoDelivery::find($this->pedidoConvertirId);
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

    /**
     * Envía un pedido a cocina con flujo decisor:
     * - Si todos los detalles ya están comandados o ninguno lo está, ejecuta
     *   directo `comandarPedido($pedido, 'todos')` sin preguntar (reimpresión
     *   completa o primera comanda).
     * - Si hay mezcla (algunos comandados + algunos nuevos), abre el modal
     *   para que el operario elija "solo nuevos" o "todo el pedido".
     */
    public function comandarPedido(int $pedidoId): void
    {
        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $pedido->loadMissing('detalles');
        $nuevos = $pedido->detalles->whereNull('comandado_at')->count();
        $comandados = $pedido->detalles->whereNotNull('comandado_at')->count();

        // Mezcla -> preguntar.
        if ($nuevos > 0 && $comandados > 0) {
            $this->pedidoComandarId = $pedido->id;
            $this->comandarNuevosCount = $nuevos;
            $this->comandarComandadosCount = $comandados;
            $this->showComandarModal = true;

            return;
        }

        // Sin mezcla -> ejecuta directo todos los detalles.
        $this->ejecutarComandarPedido($pedido, PedidoDeliveryService::ALCANCE_COMANDA_TODOS);
    }

    /**
     * Despacha la acción tras la elección del operario en el modal.
     */
    public function confirmarComandar(string $alcance): void
    {
        if (! in_array($alcance, [PedidoDeliveryService::ALCANCE_COMANDA_TODOS, PedidoDeliveryService::ALCANCE_COMANDA_NUEVOS], true)) {
            $this->dispatch('toast-error', message: __('Alcance inválido'));

            return;
        }

        $pedidoId = $this->pedidoComandarId;
        $this->cerrarComandarModal();

        if (! $pedidoId) {
            return;
        }

        $pedido = PedidoDelivery::find($pedidoId);
        if (! $pedido || ! $this->tieneAccesoASucursal($pedido->sucursal_id)) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));

            return;
        }

        $this->ejecutarComandarPedido($pedido, $alcance);
    }

    public function cerrarComandarModal(): void
    {
        $this->showComandarModal = false;
        $this->pedidoComandarId = null;
        $this->comandarNuevosCount = 0;
        $this->comandarComandadosCount = 0;
    }

    protected function ejecutarComandarPedido(PedidoDelivery $pedido, string $alcance): void
    {
        try {
            $payload = $this->service->comandarPedido($pedido, $alcance);
            $this->dispatch('imprimir-comanda', payload: $payload);
            $this->dispatch('toast-info', message: __('Enviando comanda a impresión...'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function reimprimirPrecuenta(int $pedidoId): void
    {
        $pedido = PedidoDelivery::find($pedidoId);
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
                PedidoDelivery::TRANSICIONES_PERMITIDAS[$estado] ?? [],
                self::ESTADOS_KANBAN,
            ));
        }

        $pedidoDetalle = $this->pedidoDetalleId
            ? PedidoDelivery::with([
                'cliente:id,nombre,telefono',
                'sucursal:id,nombre',
                'caja:id,nombre',
                'repartidor:id,nombre,telefono',
                'zona:id,nombre',
                'venta:id,numero',
                'cupon',
                'detalles.articulo:id,nombre',
                'detalles.opcionales',
                'pagos.formaPago:id,nombre,codigo',
                'promociones',
            ])->find($this->pedidoDetalleId)
            : null;

        return view('livewire.pedidos.pedidos-delivery', [
            'pedidos' => $pedidos,
            'borradores' => $borradores,
            'pedidosKanban' => $pedidosKanban,
            'transicionesKanban' => $transicionesKanban,
            'estadosKanban' => self::ESTADOS_KANBAN,
            'pedidoDetalle' => $pedidoDetalle,
            'estadosPedido' => PedidoDelivery::ESTADOS,
            'estadosPago' => PedidoDelivery::ESTADOS_PAGO,
            'tiposPedido' => PedidoDelivery::TIPOS,
            'origenesPedido' => PedidoDelivery::ORIGENES,
            'repartidores' => $this->repartidoresDisponibles(),
            'zonas' => $this->zonasDisponibles(),
            'salidasEnCurso' => $this->salidasEnCurso(),
            'pedidosParaSalida' => $this->showArmarSalidaModal ? $this->pedidosParaSalida() : collect(),
            'pedidosPorAceptar' => $this->pedidosPorAceptar(),
        ]);
    }

    /**
     * Zonas activas de la sucursal (filtro del panel).
     */
    protected function zonasDisponibles()
    {
        $sucursalId = $this->sucursalActual();
        if ($sucursalId === null) {
            return collect();
        }

        return DeliveryZona::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('orden')
            ->get(['id', 'nombre']);
    }
}
