<?php

namespace App\Livewire\Pedidos;

use App\Livewire\Concerns\Carrito\WithArticuloRapido;
use App\Livewire\Concerns\Carrito\WithBusquedaArticulos;
use App\Livewire\Concerns\Carrito\WithBusquedaClientes;
use App\Livewire\Concerns\Carrito\WithCalculoVenta;
use App\Livewire\Concerns\Carrito\WithCarritoItems;
use App\Livewire\Concerns\Carrito\WithConsultaPrecios;
use App\Livewire\Concerns\Carrito\WithCupones;
use App\Livewire\Concerns\Carrito\WithDescuentos;
use App\Livewire\Concerns\Carrito\WithInvitaciones;
use App\Livewire\Concerns\Carrito\WithOpcionales;
use App\Livewire\Concerns\Carrito\WithPagosDesglose;
use App\Livewire\Concerns\Carrito\WithPuntos;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\ListaPrecio;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryDetalle;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\Pedidos\CotizacionEnvio;
use App\Services\Pedidos\DeliveryEnvioService;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\PuntosService;
use App\Traits\CajaAware;
use App\Traits\ManejaDomicilio;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta/edición de Pedido Delivery / Take-away (espejo de NuevoPedidoMostrador).
 *
 * Deltas propios de delivery:
 * - `tipo` delivery|take_away obligatorio (RF-02); forma de venta AUTOMÁTICA
 *   según tipo (seeds DELIVERY/TAKEAWAY — condiciona listas y promos).
 * - Dirección de entrega georreferenciada (RF-04): modal con ManejaDomicilio
 *   + partial domicilio-form (referencia, sin domTipo, defaults de sucursal);
 *   snapshot en el pedido + actualiza la dirección de ENTREGA del cliente
 *   (D6/D18, nunca la fiscal) salvo "entregar en otra dirección".
 * - Cotización de envío (RF-06) vía DeliveryEnvioService: zona → radio →
 *   fuera de alcance (forzable solo con permiso); costo editable a mano (D7).
 * - El costo de envío se muestra y entra al total de PAGOS, pero se persiste
 *   como campo de encabezado: el SERVICE materializa el renglón-concepto D17
 *   y ajusta los totales por delta (los data-totales van SIN envío).
 *
 * Es un sub-componente Livewire que se invoca desde `PedidosDelivery` (la
 * Lista) como modal FULL-SCREEN — no tiene ruta dedicada. El padre controla
 * cuándo renderizarlo via `<livewire:nuevo-pedido-delivery :pedidoId="..." />`
 * y escucha los eventos `cerrar-modal-pedido` y `pedido-guardado` para
 * cerrar/refrescar.
 *
 * Compone los 10 traits del Carrito EXCEPTO `WithPagosDesglose`: la captura
 * de pagos vive en otro modal en PR2.C.2.B. Acá se construye el carrito
 * completo (items + descuentos + cupones + opcionales + puntos + ajustes)
 * y se persiste vía `PedidoDeliveryService::crearPedido` / `actualizarPedido`.
 *
 * Reusa la UI 1:1 con NuevaVenta mediante los parciales en
 * `resources/views/livewire/carrito/`.
 *
 * Modos:
 *   - **Alta** (`$pedidoId === null`): carrito vacío, botones "Guardar borrador"
 *     y "Confirmar pedido". El segundo asigna número, descuenta stock y
 *     dispara `PedidoCreado`.
 *   - **Edición** (`$pedidoId` provisto): hidrata items/cliente/etc desde el
 *     pedido. Editable mientras el pedido no esté cancelado/facturado y no
 *     tenga cobros materializados (estado_pago = pendiente). El guardado
 *     dispara `actualizarPedido` (revierte/redescuenta stock si era confirmado).
 *
 * Validación de beeper: si `sucursal.usa_beepers = true` y no se ingresó
 * `numero_beeper`, bloquea al confirmar (no al guardar borrador).
 */
class NuevoPedidoDelivery extends Component
{
    /**
     * Nombre temporal por defecto cuando no se elige cliente ni se cargan datos
     * temporales. Es un dato que se persiste (no UI), por eso literal sin __().
     */
    public const NOMBRE_CLIENTE_DEFAULT = 'Consumidor final';

    use CajaAware;
    use ManejaDomicilio;
    use WithArticuloRapido;
    use WithBusquedaArticulos;
    use WithBusquedaClientes;
    use WithCalculoVenta {
        // El cálculo del carrito NO incluye el envío (D17: fuera de la cascada
        // de descuentos); lo envolvemos para sumarlo al resultado final.
        WithCalculoVenta::calcularVenta as calcularVentaCarrito;
    }
    use WithCarritoItems;
    use WithConsultaPrecios;
    use WithCupones;
    use WithDescuentos;
    use WithInvitaciones;
    use WithOpcionales;
    use WithPagosDesglose;
    use WithPuntos;

    // ==================== CONTEXTO ====================

    /** @var int|null ID de pedido en modo edición (null = alta). Lo pasa el padre. */
    public ?int $pedidoId = null;

    public ?int $sucursalId = null;

    public ?int $cajaSeleccionada = null;

    public ?int $listaPrecioId = null;

    public ?int $formaVentaId = null;

    public ?int $canalVentaId = null;

    /**
     * Forma de pago seleccionada — usada por WithCalculoVenta. En este PR
     * queda null porque no incluimos WithPagosDesglose; los stubs
     * cargarCuotasFormaPago() y calcularAjusteFormaPago() están abajo.
     */
    public ?int $formaPagoId = null;

    public ?string $observaciones = null;

    public array $listasPreciosDisponibles = [];

    public ?array $resultado = null;

    // ==================== ESPECÍFICAS DE PEDIDO ====================

    public ?string $identificador = null;

    public ?string $numeroBeeper = null;

    public ?string $nombreClienteTemporal = null;

    public ?string $telefonoClienteTemporal = null;

    public bool $sucursalUsaBeepers = false;

    // ==================== DELIVERY: TIPO (RF-02) ====================

    /** Tipo de pedido: delivery | take_away. Obligatorio al alta. */
    public string $tipo = PedidoDelivery::TIPO_DELIVERY;

    public bool $sucursalUsaDelivery = false;

    public bool $takeawayHabilitado = true;

    public bool $georreferenciarPedidos = false;

    // ==================== DELIVERY: DIRECCIÓN (RF-04) ====================

    public bool $mostrarModalDireccion = false;

    /** Snapshot de la dirección confirmada (lo que se persiste en el pedido). */
    public ?string $direccionEntrega = null;

    public ?string $direccionReferencia = null;

    public ?int $localidadEntregaId = null;

    public ?float $entregaLatitud = null;

    public ?float $entregaLongitud = null;

    /** true ⇒ NO actualizar la dirección de entrega del cliente (solo snapshot). */
    public bool $entregarEnOtraDireccion = false;

    // ==================== DELIVERY: ENVÍO (RF-06/D7) ====================

    /** Costo de envío vigente (cotizado o manual). Input editable (D7). */
    public $costoEnvio = 0;

    public bool $costoEnvioManual = false;

    public ?int $zonaEnvioId = null;

    public ?string $zonaEnvioNombre = null;

    public ?float $distanciaKm = null;

    /** ok | fuera_de_alcance | desconocido (CotizacionEnvio::ALCANCE_*). */
    public string $alcanceEnvio = CotizacionEnvio::ALCANCE_DESCONOCIDO;

    // ==================== PROMESA DE ENTREGA (RF-15 core) ====================

    /** Modo de promesa de la sucursal: manual | automatica (franjas = Fase 8). */
    public string $modoPromesa = 'manual';

    /** Botones de demora (minutos) para el modo manual. */
    public array $botonesDemora = [];

    /** Botón de demora elegido por el operador (modo manual). */
    public ?int $demoraSeleccionadaMin = null;

    /** Franjas de HOY para el modo franjas: [['iso' => 'Y-m-d H:i:s', 'label' => 'H:i'], ...]. */
    public array $franjasDisponibles = [];

    /** Franja elegida (ISO) o 'asap' = Lo antes posible (hora_pactada null). */
    public ?string $franjaSeleccionada = null;

    /** La sucursal ofrece "Lo antes posible" en modo franjas. */
    public bool $aceptaLoAntesPosible = true;

    /** Demora estimada de la última cotización (modo automática, delivery). */
    public ?int $demoraEstimadaMin = null;

    /** Demora base de la config (estimación para take-away en modo automática). */
    public int $demoraBaseMin = 15;

    /** Promesa ya persistida del pedido en edición (se preserva si no se cambia). */
    public ?string $horaPactadaExistente = null;

    public bool $modoEdicion = false;

    /** Estado del pedido en modo edición (para mostrarlo y validar transiciones). */
    public ?string $estadoPedidoActual = null;

    /**
     * Cantidad de pagos (activos+planificados) existentes en el pedido al
     * abrirlo en edición. Si > 0, NO recreamos pagos al guardar — solo se
     * actualiza el resto del pedido. Los pagos se gestionan desde la lista
     * (acción "Cobrar pendiente"). Evita duplicación.
     */
    public int $cuentaPagosOriginales = 0;

    /**
     * Modo "cobro rapido": el componente arranca con el modal de desglose
     * abierto sobre el listado, sin renderizar la UI completa del editor.
     * Se usa para que el operador defina las formas de pago de un pedido
     * confirmado sin entrar al editor full-screen. En este modo, al
     * confirmar el desglose se AGREGAN pagos activos al pedido existente
     * (no se recrea ni se modifica el carrito).
     */
    public bool $modoCobroRapido = false;

    /**
     * Saldo pendiente del pedido en modo cobro rápido (total_final − cobrado −
     * planificado). Lo fija iniciarCobroRapido() y calcularVenta() lo re-aplica
     * como total a cubrir en cada recálculo.
     */
    public ?float $saldoCobroRapido = null;

    /**
     * Diferencia el modo del modal de pago:
     * - false (preview): se abrió por seleccionar FP mixta en el dropdown.
     *   "Confirmar" cierra el modal manteniendo el desglose y vuelve al alta
     *   con los totales actualizados.
     * - true (cobrar): se abrió por click en "Confirmar pedido". "Confirmar"
     *   del modal procesa: persiste el pedido y agrega los pagos.
     */
    public bool $modalPagoEnModoCobro = false;

    /** Modo de control de stock de la sucursal. */
    public string $controlStock = 'permitir';

    // ==================== MODAL CONCEPTO LIBRE ====================

    public bool $mostrarModalConcepto = false;

    public string $conceptoDescripcion = '';

    public ?int $conceptoCategoriaId = null;

    public float $conceptoImporte = 0;

    public array $categoriasDisponibles = [];

    // ==================== MODAL PESABLE ====================

    public bool $mostrarModalPesable = false;

    public ?int $pesableArticuloId = null;

    public float $pesablePrecioUnitario = 0;

    public string $pesableUnidadMedida = 'kg';

    public string $pesableNombreArticulo = '';

    // ==================== MODAL CONFIRM LIMPIAR ====================

    public bool $mostrarConfirmLimpiar = false;

    // ==================== PANEL TÁCTIL (RF-11) ====================

    /** Si el panel táctil está expandido (default true en alta nueva). */
    public bool $panelTactilAbierto = true;

    /**
     * Catálogo táctil (snapshot al mount): categorías de la sucursal con sus
     * artículos básicos (id, nombre, precio_base, código, pesable). Se pasa
     * a Alpine como JSON y el cambio de categoría es 100% local — sin viaje
     * a Livewire. Optimiza la experiencia táctil.
     */
    public array $catalogoTactil = [];

    // ==================== EDICIÓN DE NOMBRE DE ITEM ====================

    public ?int $editarNombreIndex = null;

    public string $editarNombreValor = '';

    /** Índice del item resaltado tras agregar (efecto highlight temporal). */
    public ?int $itemResaltado = null;

    // ==================== STUBS FISCALES (para WithPagosDesglose) ====================
    // WithPagosDesglose espera estas props del componente host (NuevaVenta las
    // declara). Como en el pedido no aplican (sin comprobante fiscal hasta la
    // conversión a venta), declaramos stubs en falsy fijo. La impresión y AFIP
    // tampoco se gatillan: el override de `procesarVentaConDesglose` toma otro
    // camino.

    /** Siempre false: el pedido no emite comprobante fiscal en su flujo. */
    public bool $emitirFacturaFiscal = false;

    /** Siempre false: el pedido nunca dispara facturación automática. */
    public bool $sucursalFacturaAutomatica = false;

    /** Habilita el botón "Confirmar sin cobrar" en el modal de pago. */
    public bool $puedeConfirmarSinCobrar = true;

    public float $montoFacturaFiscal = 0;

    public array $desgloseIvaFiscal = [];

    /** Siempre 0/[]: el pedido no aplica percepciones (no emite comprobante). */
    public float $percepcionMonto = 0;

    public array $percepcionTributos = [];

    public bool $showPuntoVentaModal = false;

    public ?int $puntoVentaSeleccionadoId = null;

    public array $puntosVentaDisponibles = [];

    public bool $puedeSeleccionarPuntoVenta = false;

    // ==================== INYECCIÓN ====================

    protected PedidoDeliveryService $pedidoService;

    protected DeliveryEnvioService $envioService;

    protected OpcionalService $opcionalService;

    protected CuponService $cuponService;

    protected PuntosService $puntosService;

    public function boot(
        PedidoDeliveryService $pedidoService,
        DeliveryEnvioService $envioService,
        OpcionalService $opcionalService,
        CuponService $cuponService,
        PuntosService $puntosService,
    ): void {
        $this->pedidoService = $pedidoService;
        $this->envioService = $envioService;
        $this->opcionalService = $opcionalService;
        $this->cuponService = $cuponService;
        $this->puntosService = $puntosService;
    }

    // ==================== COMPUTED CATÁLOGOS ====================

    #[Computed]
    public function formasVenta(): array
    {
        return CatalogoCache::formasVenta()->toArray();
    }

    #[Computed]
    public function canalesVenta(): array
    {
        return CatalogoCache::canalesVenta()->toArray();
    }

    #[Computed]
    public function formasPago(): array
    {
        return CatalogoCache::formasPago()->toArray();
    }

    // ==================== CICLO DE VIDA ====================

    public function mount(?int $pedidoId = null, bool $modoCobroRapido = false): void
    {
        $this->modoCobroRapido = $modoCobroRapido;
        $this->sucursalId = sucursal_activa() ?? Sucursal::activas()->first()?->id ?? 1;
        $this->cajaSeleccionada = caja_activa();

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->cargarFormasPagoSucursal();
        // En cobro rapido no usamos el panel tactil (solo se muestra el modal).
        if (! $this->modoCobroRapido) {
            $this->cargarCatalogoTactil();
        }
        $this->listaPrecioId = $this->obtenerIdListaBase();

        // Forma de venta AUTOMÁTICA según tipo (seeds DELIVERY/TAKEAWAY): las
        // listas de precios y promociones condicionan por forma de venta.
        $this->aplicarFormaVentaPorTipo();

        $pos = collect($this->canalesVenta)->firstWhere('codigo', 'pos');
        $this->canalVentaId = $pos['id'] ?? $this->canalesVenta[0]['id'] ?? null;

        $this->cargarTopeDescuentoUsuario();

        if ($pedidoId !== null) {
            $this->cargarPedidoParaEditar($pedidoId);
        }

        if ($this->modoCobroRapido) {
            $this->iniciarCobroRapido();
        }
    }

    /**
     * Prepara el modal de desglose para cobrar el saldo pendiente del pedido
     * sin abrir el editor full-screen. El pedido ya esta hidratado por
     * cargarPedidoParaEditar(). Calcula el saldo (total_final - cobrado -
     * planificado), lo asigna como monto a cobrar y abre el modal en modo
     * "cobrar" (no "preview"), de modo que confirmarPago() dispare el
     * procesamiento.
     */
    protected function iniciarCobroRapido(): void
    {
        if (! $this->modoEdicion || ! $this->pedidoId) {
            $this->dispatch('toast-error', message: __('No se puede iniciar el cobro rapido sin un pedido valido'));
            $this->dispatch('cerrar-cobro-rapido');

            return;
        }

        $pedido = PedidoDelivery::find($this->pedidoId);
        if (! $pedido) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));
            $this->dispatch('cerrar-cobro-rapido');

            return;
        }

        $totalFinal = (float) $pedido->total_final;
        $cobrado = (float) $pedido->total_cobrado;
        $planificado = (float) $pedido->total_planificado;
        $saldo = round($totalFinal - $cobrado - $planificado, 2);

        if ($saldo <= 0.01) {
            $this->dispatch('toast-error', message: __('Este pedido no tiene saldo por cobrar'));
            $this->dispatch('cerrar-cobro-rapido');

            return;
        }

        // El modal usa $resultado['total_final'] como base de calculo (cuotas,
        // ajustes, IVA mixto, validacion desgloseCompleto). En cobro rapido
        // sobreescribimos ese total con el saldo pendiente para que toda la
        // logica del trait WithPagosDesglose opere sobre lo que falta cobrar
        // y no sobre el total original del pedido.
        if (! is_array($this->resultado)) {
            $this->resultado = [];
        }
        $this->saldoCobroRapido = $saldo;
        $this->resultado['total_final'] = $saldo;

        // El desglose en cobro rapido empieza vacio: los pagos existentes
        // (cobrados/planificados) no se tocan; agregamos pagos ACTIVOS nuevos
        // por el saldo. cargarPedidoParaEditar() pudo dejar $desglosePagos
        // poblado al hidratar — lo limpiamos.
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = $saldo;
        $this->totalConAjustes = $saldo;
        $this->ajusteFormaPagoInfo = [
            'nombre' => '',
            'porcentaje' => 0,
            'monto' => 0,
            'total_con_ajuste' => $saldo,
            'es_mixta' => false,
            'cuotas' => 1,
            'recargo_cuotas_porcentaje' => 0,
            'recargo_cuotas_monto' => 0,
            'valor_cuota' => 0,
        ];

        $this->modalPagoEnModoCobro = true;
        $this->mostrarModalPago = true;
    }

    /**
     * Carga el snapshot del catálogo táctil de la sucursal: categorías activas
     * con sus artículos básicos. Pensado para que Alpine renderice la grilla
     * sin viajes a Livewire al cambiar de categoría (cambio 100% local).
     *
     * Estructura por categoría: { id, nombre, color, icono, icono_svg, articulos: [...] }
     * Estructura por artículo: { id, nombre, codigo, precio, es_pesable, imagen_url }
     *
     * `icono_svg` viene pre-renderizado como HTML (Alpine no puede resolver
     * componentes Blade en runtime). Se renderiza con `x-html` en la vista.
     * `imagen_url` queda null mientras el upload de imágenes no esté
     * implementado: el fallback visual es el ícono SVG de la categoría.
     */
    protected function cargarCatalogoTactil(): void
    {
        if (! $this->sucursalId) {
            $this->catalogoTactil = [];

            return;
        }

        $categorias = \App\Models\Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color', 'icono']);

        // Precio base del artículo como referencia visual. Al hacer click,
        // seleccionarArticulo aplica precios según la lista del pedido.
        // withCount marca tiene_opcionales sin hidratar las relaciones.
        $articulos = \App\Models\Articulo::query()
            ->where('activo', true)
            ->whereNotNull('categoria_id')
            ->withCount('gruposOpcionales')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'categoria_id', 'precio_base', 'pesable',
                'imagen_path', 'imagen_focal_x', 'imagen_focal_y']);

        $articulosPorCategoria = $articulos->groupBy('categoria_id');

        $this->catalogoTactil = $categorias->map(function ($cat) use ($articulosPorCategoria) {
            $arts = $articulosPorCategoria->get($cat->id, collect());
            if ($arts->isEmpty()) {
                return null;
            }

            return [
                'id' => (int) $cat->id,
                'nombre' => $cat->nombre,
                'color' => $cat->color ?: '#6B7280',
                'icono' => $cat->icono,
                'icono_svg' => $this->renderIconoSvg($cat->icono),
                'articulos' => $arts->map(fn ($a) => [
                    'id' => (int) $a->id,
                    'nombre' => $a->nombre,
                    'codigo' => $a->codigo,
                    'precio' => (float) ($a->precio_base ?? 0),
                    'es_pesable' => (bool) $a->pesable,
                    'tiene_opcionales' => (int) ($a->grupos_opcionales_count ?? 0) > 0,
                    'imagen_url' => $a->imagenUrl(),
                    'imagen_focal' => $a->imagenFocalPosition(),
                ])->values()->toArray(),
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Pre-renderiza el componente Blade del ícono a string HTML para que
     * Alpine lo inyecte vía `x-html` (no puede resolver componentes Blade en
     * runtime). Soporta cualquier ícono que use `<x-dynamic-component>`:
     * heroicons (`heroicon-o-...`), componentes custom del proyecto
     * (`food.hamburguesa`, `icon.tag`, etc.). Devuelve null si el ícono no
     * está seteado o el componente no existe.
     *
     * Se ejecuta una sola vez al cargar el catálogo en mount(), no por
     * render, así que el costo de compilar Blade es aceptable.
     */
    protected function renderIconoSvg(?string $icono): ?string
    {
        if (! $icono) {
            return null;
        }
        try {
            return trim(\Illuminate\Support\Facades\Blade::render(
                '<x-dynamic-component :component="$icono" class="w-6 h-6" />',
                ['icono' => $icono],
            ));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function togglePanelTactil(): void
    {
        $this->panelTactilAbierto = ! $this->panelTactilAbierto;
    }

    public function render()
    {
        return view('livewire.pedidos.nuevo-pedido-delivery', [
            'condicionesIvaCliente' => $this->mostrarModalClienteRapido ? CatalogoCache::condicionesIva() : collect(),
        ]);
    }

    #[On('sucursal-changed')]
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        if ($this->modoEdicion) {
            $this->dispatch('toast-warning', message: __('No se puede cambiar de sucursal mientras se edita un pedido.'));

            return;
        }

        $this->sucursalId = $sucursalId;
        $this->items = [];
        $this->resultado = null;
        $this->identificador = null;
        $this->numeroBeeper = null;
        $this->nombreClienteTemporal = null;
        $this->telefonoClienteTemporal = null;
        $this->observaciones = null;
        $this->resetDireccion();
        $this->resetDomicilio();

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->cargarFormasPagoSucursal();
        $this->listaPrecioId = $this->obtenerIdListaBase();
        $this->aplicarFormaVentaPorTipo();
    }

    #[On('caja-changed')]
    public function handleCajaChanged($cajaId = null, $cajaNombre = null): void
    {
        $this->cajaSeleccionada = $cajaId;
    }

    /**
     * Hook del concern de cobro QR: el pago se canceló o expiró. El trait cerró
     * el modal de espera; reabrimos el de desglose para que el operario reintente
     * o cambie la forma de pago sin perder lo que ya armó. (En NuevaVenta no se
     * reabre nada porque el carrito queda intacto y se reintenta con el botón
     * "Cobrar"; en el pedido el desglose ES el punto de entrada del cobro.)
     */
    protected function alCancelarCobroIntegracion(): void
    {
        if ($this->modalPagoEnModoCobro) {
            $this->mostrarModalPago = true;
        }
    }

    // ==================== CARGA DE CONTEXTO ====================

    protected function cargarConfiguracionSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->sucursalUsaBeepers = false;
            $this->controlStock = 'permitir';
            $this->sucursalUsaDelivery = false;
            $this->takeawayHabilitado = true;
            $this->georreferenciarPedidos = false;

            return;
        }
        $sucursal = Sucursal::find($this->sucursalId);
        $this->sucursalUsaBeepers = (bool) ($sucursal->usa_beepers ?? false);
        $this->controlStock = $sucursal->control_stock_venta ?? 'permitir';

        $this->sucursalUsaDelivery = (bool) ($sucursal->usa_delivery ?? false);
        $config = $this->envioService->configDelivery($sucursal);
        $this->takeawayHabilitado = (bool) ($config['takeaway_habilitado'] ?? true);
        $this->georreferenciarPedidos = (bool) ($config['georreferenciar_pedidos'] ?? false);

        // Promesa de entrega (RF-15): manual (botones), automática (por km) o
        // franjas (horarios fijos + "Lo antes posible"; cupos = Fase 8).
        $modo = (string) ($config['modo_promesa'] ?? 'manual');
        $this->modoPromesa = in_array($modo, ['automatica', 'franjas'], true) ? $modo : 'manual';
        $this->botonesDemora = array_values(array_map('intval', (array) ($config['botones_demora'] ?? [])));
        $this->demoraBaseMin = (int) ($config['demora_base_min'] ?? 15);
        $this->aceptaLoAntesPosible = (bool) ($config['acepta_lo_antes_posible'] ?? true);
        $this->cargarFranjasDisponibles();

        // Si el take-away está deshabilitado y el tipo actual es take_away,
        // volver a delivery (y viceversa cuando la sucursal no usa delivery).
        if ($this->tipo === PedidoDelivery::TIPO_TAKE_AWAY && ! $this->takeawayHabilitado) {
            $this->tipo = PedidoDelivery::TIPO_DELIVERY;
        }
    }

    // ==================== DELIVERY: TIPO (RF-02) ====================

    /**
     * Setea la forma de venta según el tipo (DELIVERY/TAKEAWAY). Fallback a la
     * primera activa si la sucursal todavía no tiene los seeds.
     */
    protected function aplicarFormaVentaPorTipo(): void
    {
        $codigo = $this->tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'TAKEAWAY' : 'DELIVERY';
        $fv = collect($this->formasVenta)
            ->first(fn ($f) => strtoupper((string) ($f['codigo'] ?? '')) === $codigo);

        $this->formaVentaId = $fv['id'] ?? ($this->formasVenta[0]['id'] ?? null);
    }

    /**
     * Cambio de tipo (RF-02): re-aplica forma de venta (y por ende listas y
     * promos), limpia el envío al pasar a take-away y advierte artículos del
     * carrito no disponibles para el nuevo tipo (RF-16: advierte, no bloquea).
     */
    public function updatedTipo(string $value): void
    {
        if (! in_array($value, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            $this->tipo = PedidoDelivery::TIPO_DELIVERY;

            return;
        }

        if ($value === PedidoDelivery::TIPO_TAKE_AWAY && ! $this->takeawayHabilitado) {
            $this->tipo = PedidoDelivery::TIPO_DELIVERY;
            $this->dispatch('toast-error', message: __('El take-away está deshabilitado en esta sucursal'));

            return;
        }

        if ($value === PedidoDelivery::TIPO_TAKE_AWAY) {
            // RF-02: al pasar a take-away se limpia el circuito de envío.
            $this->resetEnvio();
        } elseif ($this->direccionEntrega && $this->entregaLatitud) {
            $this->cotizarEnvio();
        }

        // Las franjas dependen del tipo (cada horario dice a qué tipo sirve):
        // recargar y descartar la elección previa si ya no aplica.
        $this->cargarFranjasDisponibles();
        if ($this->franjaSeleccionada && $this->franjaSeleccionada !== 'asap'
            && ! in_array($this->franjaSeleccionada, array_column($this->franjasDisponibles, 'iso'), true)) {
            $this->franjaSeleccionada = null;
        }

        $this->aplicarFormaVentaPorTipo();
        $this->actualizarPreciosItems();
        $this->calcularVenta();
        $this->advertirArticulosNoDisponibles();
    }

    /**
     * RF-16: advertencia (no bloqueo) por artículos del carrito no disponibles
     * para el tipo actual. El operador manda; la API pública sí bloquea.
     */
    protected function advertirArticulosNoDisponibles(): void
    {
        $ids = array_values(array_filter(array_column($this->items, 'articulo_id')));
        if (empty($ids)) {
            return;
        }

        $nombres = $this->pedidoService->articulosNoDisponibles($ids, $this->tipo);
        if (! empty($nombres)) {
            $this->dispatch('toast-warning', message: __('Ojo: :articulos no está(n) disponible(s) para :tipo', [
                'articulos' => implode(', ', $nombres),
                'tipo' => __(PedidoDelivery::TIPOS[$this->tipo]),
            ]));
        }
    }

    // ==================== DELIVERY: DIRECCIÓN (RF-04) ====================

    /**
     * Abre el modal de dirección. Precarga: dirección de ENTREGA del cliente
     * si existe; si no, la fiscal como texto inicial (sin coordenadas); y
     * provincia/localidad default de la sucursal para carga rápida.
     */
    public function abrirModalDireccion(): void
    {
        $this->resetDomicilio();
        $this->domTipo = 'otro'; // Modo entrega: sin tipo fiscal/comercial (el partial lo oculta).

        // Estado ya confirmado en este pedido → editarlo.
        if ($this->direccionEntrega) {
            $this->setDomicilioDesde([
                'tipo' => 'otro',
                'direccion' => $this->direccionEntrega,
                'referencia' => $this->direccionReferencia,
                'localidad_id' => $this->localidadEntregaId,
                'latitud' => $this->entregaLatitud,
                'longitud' => $this->entregaLongitud,
            ]);
        } elseif ($this->clienteSeleccionado) {
            $cliente = Cliente::find($this->clienteSeleccionado);
            if ($cliente && $cliente->direccion_entrega) {
                $this->setDomicilioDesde([
                    'tipo' => 'otro',
                    'direccion' => $cliente->direccion_entrega,
                    'referencia' => $cliente->direccion_entrega_referencia,
                    'latitud' => $cliente->latitud,
                    'longitud' => $cliente->longitud,
                ]);
            } elseif ($cliente && $cliente->direccion) {
                // Fiscal como texto inicial, SIN coordenadas (D18).
                $this->domDireccion = (string) $cliente->direccion;
            }
        }

        $sucursal = Sucursal::find($this->sucursalId);
        if ($sucursal) {
            $this->domicilioDefaultDesdeSucursal($sucursal);
        }

        $this->mostrarModalDireccion = true;
    }

    public function cerrarModalDireccion(): void
    {
        $this->mostrarModalDireccion = false;
    }

    /**
     * Confirma la dirección del modal: snapshot al estado del pedido y
     * re-cotización del envío. Guardar sin coordenadas está permitido con
     * advertencia explícita (sin geo no hay cálculo automático, RF-04).
     */
    public function confirmarDireccion(): void
    {
        if (trim($this->domDireccion) === '') {
            $this->dispatch('toast-error', message: __('Ingresá la dirección de entrega'));

            return;
        }

        $datos = $this->datosDomicilio();
        $this->direccionEntrega = $datos['direccion'];
        $this->direccionReferencia = $datos['referencia'];
        $this->localidadEntregaId = $datos['localidad_id'];
        $this->entregaLatitud = $datos['latitud'] !== null ? (float) $datos['latitud'] : null;
        $this->entregaLongitud = $datos['longitud'] !== null ? (float) $datos['longitud'] : null;

        $this->mostrarModalDireccion = false;

        if ($this->georreferenciarPedidos && $this->entregaLatitud === null) {
            $this->dispatch('toast-warning', message: __('Dirección sin coordenadas: no hay cálculo automático de envío ni validación de alcance'));
        }

        $this->cotizarEnvio();
    }

    // ==================== DELIVERY: ENVÍO (RF-06/D7) ====================

    /**
     * Cotiza el envío para las coordenadas confirmadas. Respeta el costo
     * manual (D7): la cotización actualiza zona/distancia/alcance, pero solo
     * pisa el costo cuando no fue editado a mano.
     */
    public function cotizarEnvio(): void
    {
        if ($this->tipo !== PedidoDelivery::TIPO_DELIVERY) {
            return;
        }

        $sucursal = Sucursal::find($this->sucursalId);
        if (! $sucursal) {
            return;
        }

        $cotizacion = $this->envioService->cotizar($sucursal, $this->entregaLatitud, $this->entregaLongitud);

        $this->alcanceEnvio = $cotizacion->alcance;
        $this->distanciaKm = $cotizacion->distanciaKm;
        $this->zonaEnvioId = $cotizacion->zona?->id;
        $this->zonaEnvioNombre = $cotizacion->zona?->nombre;
        $this->demoraEstimadaMin = $cotizacion->demoraEstimadaMin;

        if (! $this->costoEnvioManual && $cotizacion->costo !== null) {
            $this->costoEnvio = $cotizacion->costo;
        }

        if ($cotizacion->esFueraDeAlcance()) {
            $this->dispatch('toast-warning', message: __('La dirección está fuera del alcance de entrega (:km km)', [
                'km' => number_format((float) $cotizacion->distanciaKm, 1, ',', '.'),
            ]));
        }

        $this->calcularVenta();
    }

    /** El operador pisó el costo a mano (D7): queda marcado y auditado. */
    public function updatedCostoEnvio(): void
    {
        $this->costoEnvioManual = true;
        $this->calcularVenta();
    }

    /** Vuelve al costo cotizado (descarta el override manual). */
    public function recotizarEnvio(): void
    {
        $this->costoEnvioManual = false;
        $this->cotizarEnvio();
    }

    protected function resetEnvio(): void
    {
        $this->costoEnvio = 0;
        $this->costoEnvioManual = false;
        $this->zonaEnvioId = null;
        $this->zonaEnvioNombre = null;
        $this->distanciaKm = null;
        $this->alcanceEnvio = CotizacionEnvio::ALCANCE_DESCONOCIDO;
        $this->demoraEstimadaMin = null;
    }

    // ==================== PROMESA DE ENTREGA (RF-15 core) ====================

    /**
     * Botón de demora del alta (modo manual). Click sobre el ya elegido lo
     * des-selecciona (pedido sin promesa).
     */
    public function seleccionarDemora(int $min): void
    {
        $this->demoraSeleccionadaMin = $this->demoraSeleccionadaMin === $min ? null : $min;
    }

    /**
     * Franja del alta (modo franjas): ISO del horario elegido o 'asap' para
     * "Lo antes posible". Click sobre la ya elegida la des-selecciona.
     */
    public function seleccionarFranja(string $valor): void
    {
        $this->franjaSeleccionada = $this->franjaSeleccionada === $valor ? null : $valor;
    }

    /**
     * Recarga los horarios de entrega elegibles para el TIPO actual (las
     * franjas configuradas dicen a qué tipo sirven y qué días aplican).
     */
    protected function cargarFranjasDisponibles(): void
    {
        if ($this->modoPromesa !== 'franjas' || ! $this->sucursalId) {
            $this->franjasDisponibles = [];

            return;
        }

        $sucursal = Sucursal::find($this->sucursalId);
        $this->franjasDisponibles = $sucursal
            ? array_map(
                fn ($slot) => ['iso' => $slot->toDateTimeString(), 'label' => $slot->format('H:i')],
                $this->envioService->franjasDisponibles($sucursal, $this->tipo),
            )
            : [];
    }

    /**
     * Promesa estimada para MOSTRAR en el alta (la definitiva se resuelve al
     * persistir, ver resolverHoraPactada). En automática: demora cotizada por
     * km (delivery) o demora base (take-away). En manual: el botón elegido.
     * En franjas: el horario elegido (asap = null, la UI muestra el label).
     */
    public function getHoraPactadaEstimadaProperty(): ?\Carbon\Carbon
    {
        if ($this->modoPromesa === 'franjas') {
            if ($this->franjaSeleccionada && $this->franjaSeleccionada !== 'asap') {
                return \Carbon\Carbon::parse($this->franjaSeleccionada);
            }
            if ($this->franjaSeleccionada === null && $this->modoEdicion && $this->horaPactadaExistente) {
                return \Carbon\Carbon::parse($this->horaPactadaExistente);
            }

            return null;
        }

        if ($this->demoraSeleccionadaMin !== null) {
            return now()->addMinutes($this->demoraSeleccionadaMin);
        }

        if ($this->modoEdicion && $this->horaPactadaExistente) {
            return \Carbon\Carbon::parse($this->horaPactadaExistente);
        }

        if ($this->modoPromesa === 'automatica') {
            $demora = $this->tipo === PedidoDelivery::TIPO_DELIVERY
                ? $this->demoraEstimadaMin
                : $this->demoraBaseMin;

            return $demora !== null ? now()->addMinutes((int) $demora) : null;
        }

        return null;
    }

    /**
     * hora_pactada_at que va al service al persistir:
     * - Franjas: horario elegido; 'asap' → null (Lo antes posible); sin
     *   elección en edición → preserva.
     * - Botón manual elegido → now + demora (alta o edición, pisa lo anterior).
     * - Edición sin cambio → preserva la promesa existente.
     * - Automática → now + demora cotizada (delivery) / demora base (take-away).
     * - Manual sin botón → null (sin promesa).
     */
    protected function resolverHoraPactada(): ?string
    {
        if ($this->modoPromesa === 'franjas') {
            if ($this->franjaSeleccionada === 'asap') {
                return null;
            }
            if ($this->franjaSeleccionada) {
                return $this->franjaSeleccionada;
            }

            return $this->modoEdicion ? $this->horaPactadaExistente : null;
        }

        if ($this->demoraSeleccionadaMin !== null) {
            return now()->addMinutes($this->demoraSeleccionadaMin)->toDateTimeString();
        }

        if ($this->modoEdicion && $this->horaPactadaExistente) {
            return $this->horaPactadaExistente;
        }

        if ($this->modoPromesa === 'automatica') {
            $demora = $this->tipo === PedidoDelivery::TIPO_DELIVERY
                ? $this->demoraEstimadaMin
                : $this->demoraBaseMin;

            return $demora !== null ? now()->addMinutes((int) $demora)->toDateTimeString() : null;
        }

        return null;
    }

    protected function resetDireccion(): void
    {
        $this->direccionEntrega = null;
        $this->direccionReferencia = null;
        $this->localidadEntregaId = null;
        $this->entregaLatitud = null;
        $this->entregaLongitud = null;
        $this->entregarEnOtraDireccion = false;
        $this->resetEnvio();
    }

    // ==================== DELIVERY: CÁLCULO CON ENVÍO ====================

    /**
     * Override del cálculo del carrito: suma el costo de envío al RESULTADO
     * (display + total a cubrir por los pagos). El envío queda FUERA de la
     * cascada de descuentos/promos/puntos (D17) y los data-totales que van al
     * service se persisten SIN envío (construirDataPedido lo descuenta): el
     * service materializa el renglón-concepto y ajusta por delta.
     */
    public function calcularVenta(): void
    {
        $this->calcularVentaCarrito();
        $this->aplicarEnvioAlResultado();

        // En cobro rápido el total a cubrir es el SALDO pendiente del pedido,
        // no el total recalculado del carrito: iniciarCobroRapido() lo setea,
        // pero cualquier recálculo posterior (ej. updatedFormaPagoId, que
        // además resetea el desglose y su pendiente) pisaría el override —
        // lo re-aplicamos acá.
        if ($this->modoCobroRapido && $this->saldoCobroRapido !== null && is_array($this->resultado)) {
            $this->resultado['total_final'] = $this->saldoCobroRapido;
            $desglosado = (float) collect($this->desglosePagos)->sum('monto_base');
            $this->montoPendienteDesglose = max(0, round($this->saldoCobroRapido - $desglosado, 2));
        }

        // El ajuste por forma de pago se calculó DENTRO de calcularVentaCarrito()
        // sobre el total sin envío. Recalcularlo sobre el total definitivo:
        // total_con_ajuste es la base del monto_final de los pagos — si queda
        // sin el envío, todo cobro nace corto y el pedido queda "parcial".
        if ($this->formaPagoId) {
            $this->calcularAjusteFormaPago();
        }
    }

    protected function aplicarEnvioAlResultado(): void
    {
        if (! $this->resultado) {
            return;
        }

        $envio = $this->montoEnvioVigente();
        $this->resultado['costo_envio'] = $envio;

        if ($envio <= 0) {
            return;
        }

        $ivaEnvio = round($envio - $envio / 1.21, 2);
        $this->resultado['subtotal'] = round((float) ($this->resultado['subtotal'] ?? 0) + $envio, 2);
        $this->resultado['iva_total'] = round((float) ($this->resultado['iva_total'] ?? 0) + $ivaEnvio, 2);
        $this->resultado['total'] = round((float) ($this->resultado['total'] ?? 0) + $envio, 2);
        $this->resultado['total_final'] = round((float) ($this->resultado['total_final'] ?? 0) + $envio, 2);
    }

    protected function montoEnvioVigente(): float
    {
        return $this->tipo === PedidoDelivery::TIPO_DELIVERY
            ? round(max(0, (float) $this->costoEnvio), 2)
            : 0.0;
    }

    /**
     * Override del hook del trait WithPagosDesglose: el envío es un valor FIJO
     * — el ajuste % de cada pago del desglose se calcula excluyendo la porción
     * de envío en forma proporcional (cada pago cubre bienes y envío en la
     * misma proporción). En cobro rápido el saldo no se puede descomponer.
     */
    protected function baseAjustePagoDesglose(float $montoBase): float
    {
        if ($this->modoCobroRapido) {
            return $montoBase;
        }

        $total = (float) ($this->resultado['total_final'] ?? 0);
        $envio = $this->montoEnvioVigente();
        if ($total <= 0 || $envio <= 0) {
            return $montoBase;
        }

        return round($montoBase * (($total - $envio) / $total), 2);
    }

    protected function cargarListasPrecios(): void
    {
        if (! $this->sucursalId) {
            $this->listasPreciosDisponibles = [];

            return;
        }

        $this->listasPreciosDisponibles = ListaPrecio::porSucursal($this->sucursalId)
            ->activas()
            ->orderBy('es_lista_base', 'desc')
            ->ordenadoPorPrioridad()
            ->get()
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'nombre' => $l->nombre,
                'es_lista_base' => (bool) $l->es_lista_base,
                'ajuste_porcentaje' => (float) $l->ajuste_porcentaje,
                'descripcion_ajuste' => $l->obtenerDescripcionAjuste(),
                'aplica_promociones' => (bool) $l->aplica_promociones,
                'promociones_alcance' => $l->promociones_alcance,
            ])
            ->toArray();
    }

    protected function obtenerIdListaBase(): ?int
    {
        foreach ($this->listasPreciosDisponibles as $l) {
            if (! empty($l['es_lista_base'])) {
                return (int) $l['id'];
            }
        }

        return $this->listasPreciosDisponibles[0]['id'] ?? null;
    }

    /**
     * Recalcula precios de items cuando cambia la lista. Lo invocan los traits
     * WithBusquedaClientes y WithDescuentos al cambiar contexto.
     */
    protected function actualizarPreciosItems(): void
    {
        foreach ($this->items as $index => $item) {
            $articulo = Articulo::find($item['articulo_id'] ?? 0);
            if (! $articulo) {
                continue;
            }
            $precioInfo = $this->obtenerPrecioConLista($articulo);

            if (($item['ajuste_manual_tipo'] ?? null) !== null) {
                $precioBase = $precioInfo['precio_base'];
                $this->items[$index]['precio_base'] = $precioBase;

                if ($item['ajuste_manual_tipo'] === 'monto') {
                    $this->items[$index]['precio'] = $item['ajuste_manual_valor'];
                } else {
                    $porcentaje = (float) $item['ajuste_manual_valor'];
                    $this->items[$index]['precio'] = round($precioBase - ($precioBase * $porcentaje / 100), 2);
                }
                $this->items[$index]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
                $this->items[$index]['tiene_ajuste'] = true;
            } else {
                $this->items[$index]['precio'] = $precioInfo['precio'];
                $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
                $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
            }
        }
    }

    // ==================== STUBS FISCALES ====================
    // Estos métodos los llama WithPagosDesglose. En NuevaVenta cargan
    // configuración fiscal y disparan eventos de impresión. En el pedido
    // delivery no aplican porque NO emite comprobante fiscal en este
    // flujo (el fiscal se genera al convertir pedido en venta).

    /**
     * Stub: el pedido NO usa configuración fiscal (no factura). Mantenemos
     * los flags en falsy para que WithPagosDesglose no trate de emitir CF.
     */
    protected function cargarConfiguracionFiscalSucursal(): void
    {
        $this->emitirFacturaFiscal = false;
        $this->sucursalFacturaAutomatica = false;
    }

    /** Stub: NuevaVenta actualiza el flag fiscal al cambiar FP. En pedido no aplica. */
    public function actualizarFacturaFiscalSegunFP(): void
    {
        $this->emitirFacturaFiscal = false;
    }

    /** Stub: NuevaVenta dispara impresión via QZ. En pedido se hace por separado. */
    protected function dispararEventoImpresion($venta, $comprobanteFiscal = null): void
    {
        // No-op: la impresión del pedido (comanda / precuenta) se dispara
        // desde la Lista o al convertir en venta.
    }

    // ==================== EDITAR NOMBRE DE ITEM ====================

    public function abrirEditarNombre(int $index): void
    {
        $this->editarNombreIndex = $index;
        $this->editarNombreValor = $this->items[$index]['nombre'] ?? '';
    }

    public function cerrarEditarNombre(): void
    {
        $this->editarNombreIndex = null;
        $this->editarNombreValor = '';
        $this->dispatch('focus-busqueda');
    }

    public function aplicarEditarNombre(): void
    {
        $index = $this->editarNombreIndex;
        $nombre = trim($this->editarNombreValor);
        if ($index === null || ! isset($this->items[$index]) || empty($nombre)) {
            $this->cerrarEditarNombre();

            return;
        }
        $this->items[$index]['nombre'] = $nombre;
        $this->cerrarEditarNombre();
    }

    // ==================== MODAL PESABLE ====================

    public function confirmarPesable(float $cantidad): void
    {
        if (! $this->pesableArticuloId || $cantidad <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese una cantidad válida'));

            return;
        }

        $this->mostrarModalPesable = false;
        $articuloId = $this->pesableArticuloId;
        $this->pesableArticuloId = null;

        $articulo = Articulo::with(['categoriaModel', 'tipoIva'])->find($articuloId);
        if (! $articulo) {
            return;
        }

        $precioInfo = $this->obtenerPrecioConLista($articulo);
        $tipoIva = $articulo->tipoIva;
        $ivaInfo = [
            'codigo' => $tipoIva?->codigo ?? 5,
            'porcentaje' => (float) ($tipoIva?->porcentaje ?? 21),
            'nombre' => $tipoIva?->nombre ?? 'IVA 21%',
        ];

        $this->verificarStockAlAgregar($articulo, $cantidad);

        $this->items[] = [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'categoria_id' => $articulo->categoria_id,
            'categoria_nombre' => $articulo->categoriaModel?->nombre,
            'precio_base' => $precioInfo['precio_base'],
            'precio' => $precioInfo['precio'],
            'tiene_ajuste' => $precioInfo['tiene_ajuste'],
            'cantidad' => $cantidad,
            'iva_codigo' => $ivaInfo['codigo'],
            'iva_porcentaje' => $ivaInfo['porcentaje'],
            'iva_nombre' => $ivaInfo['nombre'],
            'precio_iva_incluido' => $articulo->precio_iva_incluido ?? true,
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'ajuste_manual_origen' => null,
            'ajuste_manual_aplicado_por' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
            'puntos_canje' => $articulo->puntos_canje,
            'pagado_con_puntos' => false,
        ];

        $estaBonificadoPorCupon = $this->cuponAplicado
            && in_array($articulo->id, $this->cuponArticulosBonificados ?? []);

        if ($this->descuentoGeneralActivo
            && $this->descuentoGeneralTipo === 'porcentaje'
            && ! $estaBonificadoPorCupon) {
            $lastIndex = count($this->items) - 1;
            $precioBase = (float) $precioInfo['precio_base'];
            $nuevoPrecio = round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2);
            $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
            $this->items[$lastIndex]['precio'] = $nuevoPrecio;
            $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
            $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
            $this->items[$lastIndex]['ajuste_manual_origen'] = 'descuento_general';
            $this->items[$lastIndex]['ajuste_manual_aplicado_por'] = $this->descuentoGeneralAplicadoPor;
            $this->items[$lastIndex]['tiene_ajuste'] = true;
        }

        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->cantidadAgregar = 1;
        $this->calcularVenta();
        $this->dispatch('scroll-carrito-abajo');
        $this->dispatch('focus-busqueda');
    }

    public function cerrarModalPesable(): void
    {
        $this->mostrarModalPesable = false;
        $this->pesableArticuloId = null;
        $this->dispatch('focus-busqueda');
    }

    // ==================== CONCEPTO LIBRE ====================

    public function abrirModalConcepto(): void
    {
        $this->categoriasDisponibles = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])
            ->toArray();

        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = 0;
        $this->mostrarModalConcepto = true;
    }

    public function cerrarModalConcepto(): void
    {
        $this->mostrarModalConcepto = false;
        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = 0;
        $this->dispatch('focus-busqueda');
    }

    public function agregarConcepto(): void
    {
        if ($this->conceptoImporte <= 0) {
            $this->dispatch('toast-error', message: __('El importe debe ser mayor a cero'));

            return;
        }

        $categoriaNombre = null;
        $ivaInfo = [
            'codigo' => 5,
            'porcentaje' => 21.0,
            'nombre' => 'IVA 21%',
        ];

        if ($this->conceptoCategoriaId) {
            $categoria = Categoria::with('tipoIva')->find($this->conceptoCategoriaId);
            if ($categoria) {
                $categoriaNombre = $categoria->nombre;
                $ivaInfo = $categoria->obtenerInfoIva();
            }
        }

        $descripcion = $this->conceptoDescripcion ?: ($categoriaNombre ?? __('Varios'));

        $this->items[] = [
            'articulo_id' => null,
            'es_concepto' => true,
            'codigo' => 'CONCEPTO',
            'nombre' => $descripcion,
            'categoria_id' => $this->conceptoCategoriaId,
            'categoria_nombre' => $categoriaNombre,
            'precio_base' => (float) $this->conceptoImporte,
            'precio' => (float) $this->conceptoImporte,
            'cantidad' => 1,
            'tiene_ajuste' => false,
            'iva_codigo' => $ivaInfo['codigo'],
            'iva_porcentaje' => $ivaInfo['porcentaje'],
            'iva_nombre' => $ivaInfo['nombre'],
            'precio_iva_incluido' => true,
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'ajuste_manual_origen' => null,
            'ajuste_manual_aplicado_por' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
        ];

        if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'porcentaje') {
            $lastIndex = count($this->items) - 1;
            $precioBase = (float) $this->conceptoImporte;
            $nuevoPrecio = max(0, round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2));
            $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioBase;
            $this->items[$lastIndex]['precio'] = $nuevoPrecio;
            $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
            $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
            $this->items[$lastIndex]['ajuste_manual_origen'] = 'descuento_general';
            $this->items[$lastIndex]['ajuste_manual_aplicado_por'] = $this->descuentoGeneralAplicadoPor;
            $this->items[$lastIndex]['tiene_ajuste'] = true;
        }

        $this->calcularVenta();
        $this->cerrarModalConcepto();
        $this->dispatch('toast-success', message: __('Concepto agregado al detalle'));
    }

    // ==================== LIMPIAR CARRITO ====================

    public function confirmarLimpiarCarrito(): void
    {
        if (empty($this->items)) {
            return;
        }
        $this->mostrarConfirmLimpiar = true;
    }

    public function cancelarLimpiarCarrito(): void
    {
        $this->mostrarConfirmLimpiar = false;
    }

    public function ejecutarLimpiarCarrito(): void
    {
        $this->mostrarConfirmLimpiar = false;
        $this->limpiarCarrito();
    }

    /**
     * Resetea el carrito. Versión adaptada al pedido (sin WithPagosDesglose).
     */
    public function limpiarCarrito($mostrarMensaje = true): void
    {
        $this->items = [];
        $this->resultado = null;

        $this->clienteSeleccionado = null;
        $this->clienteNombre = '';
        $this->busquedaCliente = '';
        $this->clientesResultados = [];

        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->cantidadAgregar = 1;
        $this->mostrarModalArticuloRapido = false;
        $this->mostrarModalBusquedaArticulos = false;
        $this->busquedaArticuloModal = '';
        $this->articulosModalResultados = [];
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = [];

        $this->observaciones = null;
        $this->identificador = null;
        $this->numeroBeeper = null;
        $this->nombreClienteTemporal = null;
        $this->telefonoClienteTemporal = null;

        $this->resetDireccion();
        $this->resetDomicilio();

        $this->descuentoGeneralActivo = false;
        $this->descuentoGeneralValor = 0;
        $this->descuentoGeneralMonto = 0;
        $this->descuentoGeneralAplicadoPor = null;

        $this->cuponAplicado = null;
        $this->cuponCodigoInput = '';
        $this->cuponMontoDescuento = 0;
        $this->cuponArticulosBonificados = [];

        $this->canjePuntosActivo = false;
        $this->canjePuntosMonto = 0;
        $this->canjePuntosUnidades = 0;

        if ($mostrarMensaje) {
            $this->dispatch('toast-success', message: __('Carrito limpiado'));
        }
    }

    // ==================== EDICIÓN ====================

    protected function cargarPedidoParaEditar(int $pedidoId): void
    {
        $pedido = PedidoDelivery::with([
            'cliente:id,nombre,telefono,lista_precio_id',
            'detalles.articulo:id,nombre,codigo,categoria_id,precio_iva_incluido,puntos_canje,tipo_iva_id',
            'detalles.opcionales',
            'pagos.formaPago:id,nombre,codigo,es_mixta,permite_cuotas',
        ])->find($pedidoId);

        if (! $pedido) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));
            $this->dispatch('cerrar-modal-pedido');

            return;
        }

        // Tanto en cobro rápido como en edición normal aceptamos cualquier
        // estado activo. La restricción real de edición vive en el guard de
        // cobros materializados más abajo: si el pedido tiene estado_pago !=
        // pendiente, no se edita. Mientras el cliente no haya pagado, el
        // operario puede ajustar el carrito en cualquier punto del flujo.
        $estadosPermitidos = [
            PedidoDelivery::ESTADO_BORRADOR,
            PedidoDelivery::ESTADO_CONFIRMADO,
            PedidoDelivery::ESTADO_EN_PREPARACION,
            PedidoDelivery::ESTADO_LISTO,
            PedidoDelivery::ESTADO_ENTREGADO,
        ];

        if (! in_array($pedido->estado_pedido, $estadosPermitidos, true)) {
            $msg = $this->modoCobroRapido
                ? __("El pedido en estado ':estado' no acepta cobros", ['estado' => $pedido->estado_pedido])
                : __("El pedido en estado ':estado' no se puede editar", ['estado' => $pedido->estado_pedido]);
            $this->dispatch('toast-error', message: $msg);
            $this->dispatch($this->modoCobroRapido ? 'cerrar-cobro-rapido' : 'cerrar-modal-pedido');

            return;
        }

        // No permitir editar pedidos que ya tienen cobros materializados
        // (estado_pago = parcial/pagado). Esos se gestionan desde la lista
        // ("Cobrar pendiente") para no romper la trazabilidad de caja.
        // Borradores siempre se pueden continuar.
        // Excepción: pedido completamente invitado (cortesía) tiene
        // estado_pago=pagado sin cobros materializados — sigue siendo editable
        // mientras el operario quiera ajustar items/cliente.
        // En modoCobroRapido este guard NO aplica: cobrar lo que falta es
        // justamente el caso de uso, sin importar pagos materializados previos.
        if (! $this->modoCobroRapido) {
            $tieneCobrosMaterializados = $pedido->pagos()
                ->where('estado', \App\Models\PedidoDeliveryPago::ESTADO_ACTIVO)
                ->exists();
            if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR
                && $pedido->estado_pago !== PedidoDelivery::ESTADO_PAGO_PENDIENTE
                && $tieneCobrosMaterializados) {
                $this->dispatch('toast-error', message: __('No se puede editar un pedido con cobros registrados. Gestioná los pagos desde la lista.'));
                $this->dispatch('cerrar-modal-pedido');

                return;
            }
        }

        $this->pedidoId = $pedido->id;
        $this->modoEdicion = true;
        $this->estadoPedidoActual = $pedido->estado_pedido;
        $this->sucursalId = $pedido->sucursal_id;
        $this->cajaSeleccionada = $pedido->caja_id;
        $this->canalVentaId = $pedido->canal_venta_id;
        $this->formaVentaId = $pedido->forma_venta_id;
        $this->listaPrecioId = $pedido->lista_precio_id ?? $this->listaPrecioId;
        $this->observaciones = $pedido->observaciones;
        $this->identificador = $pedido->identificador;
        $this->numeroBeeper = $pedido->numero_beeper;
        $this->nombreClienteTemporal = $pedido->nombre_cliente_temporal;
        $this->telefonoClienteTemporal = $pedido->telefono_cliente_temporal;

        if ($pedido->cliente_id) {
            $this->seleccionarCliente($pedido->cliente_id);
        }

        // Hidratar el estado delivery ANTES de armar items/calcular: tipo,
        // dirección snapshot y envío (el renglón D17 se EXCLUYE del carrito —
        // lo regenera el service desde costo_envio).
        $this->tipo = $pedido->tipo;
        $this->direccionEntrega = $pedido->direccion_entrega;
        $this->direccionReferencia = $pedido->direccion_referencia;
        $this->localidadEntregaId = $pedido->localidad_entrega_id;
        $this->entregaLatitud = $pedido->latitud !== null ? (float) $pedido->latitud : null;
        $this->entregaLongitud = $pedido->longitud !== null ? (float) $pedido->longitud : null;
        $this->costoEnvio = (float) $pedido->costo_envio;
        $this->costoEnvioManual = (bool) $pedido->costo_envio_manual;
        $this->zonaEnvioId = $pedido->zona_id;
        $this->zonaEnvioNombre = $pedido->zona?->nombre;
        $this->distanciaKm = $pedido->distancia_km !== null ? (float) $pedido->distancia_km : null;
        $this->alcanceEnvio = $pedido->fuera_de_alcance
            ? CotizacionEnvio::ALCANCE_FUERA
            : ($this->entregaLatitud !== null ? CotizacionEnvio::ALCANCE_OK : CotizacionEnvio::ALCANCE_DESCONOCIDO);
        $this->horaPactadaExistente = $pedido->hora_pactada_at?->toDateTimeString();

        $this->items = $pedido->detalles
            ->filter(fn ($d) => ! $d->es_costo_envio)
            ->filter(fn ($d) => ! $d->es_concepto || $d->concepto_descripcion)
            ->map(fn ($d) => $this->detalleAItemCarrito($d))
            ->values()
            ->toArray();

        // Rehidratar el estado del trait WithInvitaciones desde la cabecera
        // persistida. `motivoInvitacionTotal` permite re-confirmar el motivo
        // si el operario edita el pedido invitado (raro pero defensible).
        $this->motivoInvitacionTotal = $pedido->es_invitacion_total
            ? ($pedido->invitacion_motivo ?? '')
            : '';
        $this->totalInvitado = (float) $pedido->total_invitado;

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->cargarFormasPagoSucursal();
        $this->calcularVenta();

        // Hidratar el desglose de pagos desde lo persistido. Pagos activos
        // (cobrados) + planificados (sin cobrar todavía). Si hay algo cargado,
        // el modal se prellena con esa configuración cuando el usuario lo abra.
        $this->hidratarDesglosePagosGuardados($pedido);
    }

    /**
     * Carga `$desglosePagos` y los totales auxiliares a partir de los pagos
     * persistidos del pedido. Si hay UN solo pago, también setea `formaPagoId`
     * y `cuotaSeleccionadaId` para que el selector de FP lo refleje.
     */
    protected function hidratarDesglosePagosGuardados(PedidoDelivery $pedido): void
    {
        $pagos = $pedido->pagos->filter(fn ($p) => in_array($p->estado, ['activo', 'planificado'], true));
        $this->cuentaPagosOriginales = $pagos->count();
        if ($this->cuentaPagosOriginales === 0) {
            return;
        }

        // Metadata de cada FP indexada por id (permite_cuotas, cuotas, etc).
        // El modal de desglose espera estas claves al renderizar cada pago.
        $fpsPorId = collect($this->formasPagoSucursal)->keyBy('id');

        $this->desglosePagos = $pagos->map(function ($p) use ($fpsPorId) {
            $fpMeta = $fpsPorId->get((int) $p->forma_pago_id, []);

            return [
                'forma_pago_id' => $p->forma_pago_id,
                'nombre' => $p->formaPago?->nombre ?? ($fpMeta['nombre'] ?? ''),
                'codigo' => $p->formaPago?->codigo ?? ($fpMeta['codigo'] ?? ''),
                'monto_base' => (float) $p->monto_base,
                'ajuste_porcentaje' => (float) $p->ajuste_porcentaje,
                'monto_ajuste' => (float) $p->monto_ajuste,
                'monto_final' => (float) $p->monto_final,
                'cuotas' => $p->cuotas ? (int) $p->cuotas : 1,
                'recargo_cuotas_porcentaje' => $p->recargo_cuotas_porcentaje !== null ? (float) $p->recargo_cuotas_porcentaje : 0,
                'recargo_cuotas_monto' => $p->recargo_cuotas_monto !== null ? (float) $p->recargo_cuotas_monto : 0,
                'recargo_cuotas' => $p->recargo_cuotas_monto !== null ? (float) $p->recargo_cuotas_monto : 0,
                'monto_cuota' => $p->monto_cuota !== null ? (float) $p->monto_cuota : null,
                'monto_recibido' => $p->monto_recibido !== null ? (float) $p->monto_recibido : (float) $p->monto_final,
                'vuelto' => (float) $p->vuelto,
                'referencia' => $p->referencia,
                'observaciones' => $p->observaciones,
                'es_cuenta_corriente' => (bool) $p->es_cuenta_corriente,
                'es_pago_puntos' => (bool) $p->es_pago_puntos,
                'puntos_usados' => (int) $p->puntos_usados,
                'afecta_caja' => (bool) $p->afecta_caja,
                // Metadata de la FP que el modal de desglose espera.
                'permite_cuotas' => (bool) ($fpMeta['permite_cuotas'] ?? false),
                'permite_vuelto' => (bool) ($fpMeta['permite_vuelto'] ?? false),
                'cuotas_disponibles' => $fpMeta['cuotas'] ?? [],
                'factura_fiscal' => (bool) ($fpMeta['factura_fiscal'] ?? false),
                'moneda_id' => $p->moneda_id,
                'es_moneda_extranjera' => false,
                'moneda_info' => $fpMeta['moneda_info'] ?? null,
                'tipo_cambio_tasa' => $p->tipo_cambio_tasa !== null ? (float) $p->tipo_cambio_tasa : null,
                'tipo_cambio_id' => $p->tipo_cambio_id,
                'monto_moneda_original' => $p->monto_moneda_original !== null ? (float) $p->monto_moneda_original : null,
                'planificado' => $p->estado === 'planificado',
                'estado_persistido' => $p->estado,
            ];
        })->values()->toArray();

        $totalDesglose = (float) $pagos->sum('monto_final');
        $this->totalConAjustes = $totalDesglose;
        $this->montoPendienteDesglose = max(0, (float) $pedido->total_final - $totalDesglose);

        // Si hay UN solo pago Y cubre completo el total del pedido, lo tratamos
        // como FP simple — seteamos formaPagoId/cuotaSeleccionadaId para que
        // el selector lo refleje. Si el único pago es PARCIAL (no cubre el
        // total), lo dejamos como desglose para que se vea que está incompleto
        // y el usuario pueda completarlo.
        $totalPedido = (float) $pedido->total_final;
        if ($this->cuentaPagosOriginales === 1 && $totalPedido > 0 && abs($totalDesglose - $totalPedido) <= 0.05) {
            $primerPago = $pagos->first();
            $this->formaPagoId = (int) $primerPago->forma_pago_id;
            $this->cargarCuotasFormaPago();
            $this->calcularAjusteFormaPago();

            return;
        }

        // Desglose mixto (varios pagos) o pago único parcial: el path "FP simple"
        // de arriba no aplica, pero igual hay que reflejar el ajuste FP + recargo
        // cuotas en `ajusteFormaPagoInfo` para que la vista calcule bien el total
        // visible (`total_final + ajusteFormaPagoInfo.monto`).
        $sumaAjuste = (float) $pagos->sum(fn ($p) => (float) $p->monto_ajuste);
        $sumaRecargo = (float) $pagos->sum(fn ($p) => (float) ($p->recargo_cuotas_monto ?? 0));
        $ajusteTotal = round($sumaAjuste + $sumaRecargo, 2);
        $totalBase = (float) ($pedido->total ?? 0);

        // Si hay >1 pago, deducir la FP "Mixta" raíz de la sucursal (espejo
        // de WithPagosDesglose:1787) y setearla como formaPagoId. Sin esto el
        // selector queda en "Seleccionar forma de pago" pese a tener el
        // desglose hidratado.
        if ($this->cuentaPagosOriginales > 1) {
            $formaMixta = FormaPago::where('es_mixta', true)->where('activo', true)->first();
            if ($formaMixta) {
                $this->formaPagoId = (int) $formaMixta->id;
            }
        }

        $this->ajusteFormaPagoInfo = [
            'nombre' => $this->cuentaPagosOriginales > 1
                ? __('Mixto')
                : ($pagos->first()->formaPago?->nombre ?? ''),
            'porcentaje' => 0,
            'monto' => $ajusteTotal,
            'total_con_ajuste' => round($totalBase + $ajusteTotal, 2),
            'es_mixta' => $this->cuentaPagosOriginales > 1,
            'cuotas' => 1,
            'recargo_cuotas_porcentaje' => 0,
            'recargo_cuotas_monto' => round($sumaRecargo, 2),
            'valor_cuota' => 0,
        ];

        // Propagar el ajuste al desglose IVA para que el footer del form
        // refleje el mismo total que ve la lista (incluye recargo/descuento FP).
        $this->actualizarDesgloseIvaConAjusteFormaPago($sumaAjuste, $sumaRecargo);
    }

    protected function detalleAItemCarrito(PedidoDeliveryDetalle $detalle): array
    {
        return [
            'articulo_id' => $detalle->articulo_id,
            'nombre' => $detalle->articulo?->nombre ?? $detalle->concepto_descripcion ?? '—',
            'codigo' => $detalle->articulo?->codigo,
            'categoria_id' => $detalle->articulo?->categoria_id ?? $detalle->concepto_categoria_id,
            'categoria_nombre' => null,
            'precio_base' => (float) $detalle->precio_lista ?: (float) $detalle->precio_unitario,
            'precio' => (float) $detalle->precio_unitario,
            'tiene_ajuste' => $detalle->ajuste_manual_tipo !== null,
            'cantidad' => (float) $detalle->cantidad,
            'iva_codigo' => null,
            'iva_porcentaje' => (float) $detalle->iva_porcentaje,
            'iva_nombre' => null,
            'precio_iva_incluido' => (bool) ($detalle->articulo?->precio_iva_incluido ?? true),
            'ajuste_manual_tipo' => $detalle->ajuste_manual_tipo,
            'ajuste_manual_valor' => $detalle->ajuste_manual_valor !== null ? (float) $detalle->ajuste_manual_valor : null,
            'ajuste_manual_origen' => $detalle->ajuste_manual_origen,
            'ajuste_manual_aplicado_por' => $detalle->ajuste_manual_aplicado_por,
            'precio_sin_ajuste_manual' => $detalle->precio_sin_ajuste_manual !== null ? (float) $detalle->precio_sin_ajuste_manual : null,
            'opcionales' => $detalle->opcionales->map(fn ($o) => [
                'opcional_id' => $o->opcional_id,
                'descripcion' => $o->descripcion,
                'precio' => (float) $o->precio,
                'cantidad' => (float) $o->cantidad,
            ])->toArray(),
            'precio_opcionales' => (float) $detalle->precio_opcionales,
            'puntos_canje' => $detalle->articulo?->puntos_canje,
            'pagado_con_puntos' => (bool) $detalle->pagado_con_puntos,
            'es_concepto' => (bool) $detalle->es_concepto,
            'concepto_descripcion' => $detalle->concepto_descripcion,
            'concepto_categoria_id' => $detalle->concepto_categoria_id,
            // Invitacion (cortesia): rehidratamos para que el trait pueda
            // re-renderizar el badge / strike-through y permita des-invitar
            // si el pedido sigue editable.
            'es_invitacion' => (bool) $detalle->es_invitacion,
            'invitacion_motivo' => $detalle->invitacion_motivo,
            'invitado_por_usuario_id' => $detalle->invitado_por_usuario_id,
            'invitado_at' => $detalle->invitado_at?->toDateTimeString(),
            'monto_invitado' => (float) $detalle->monto_invitado,
            'precio_unitario_original' => $detalle->precio_unitario_original !== null
                ? (float) $detalle->precio_unitario_original
                : null,
            // Comanda por detalle: si null, el item es "nuevo" (no enviado
            // a cocina aún). Lo usa el partial del carrito para mostrar el
            // badge "Nuevo" amber al lado del nombre.
            'comandado_at' => $detalle->comandado_at?->toDateTimeString(),
        ];
    }

    // ==================== CLIENTE TEMPORAL (RF-17) ====================

    public function abrirModalAltaClienteTemporal(): void
    {
        if (empty(trim($this->nombreClienteTemporal ?? '')) || empty(trim($this->telefonoClienteTemporal ?? ''))) {
            $this->dispatch('toast-error', message: __('Nombre y teléfono son obligatorios'));

            return;
        }

        $this->clienteRapidoNombre = $this->nombreClienteTemporal ?? '';
        $this->clienteRapidoTelefono = $this->telefonoClienteTemporal ?? '';
        $this->abrirModalClienteRapido();
    }

    public function limpiarClienteTemporal(): void
    {
        $this->nombreClienteTemporal = null;
        $this->telefonoClienteTemporal = null;
    }

    // ==================== CIERRE / GUARDADO ====================

    /**
     * Cerrar el modal sin guardar. Despacha evento que la Lista escucha.
     */
    public function cerrar(): void
    {
        $this->dispatch('cerrar-modal-pedido');
    }

    public function guardarBorrador(): void
    {
        // Guardar como borrador NO requiere pagos: persiste directamente.
        $this->guardar(esBorrador: true);
    }

    /**
     * Confirmar pedido: si tiene items, abre el modal de pago para que el
     * usuario configure el desglose (cobrar ahora o planificar) antes de
     * persistir. Una vez confirmado en el modal, `confirmarPagosPedido()`
     * crea el pedido y agrega los pagos.
     */
    public function confirmarPedido(): void
    {
        if (! $this->validarAntesDeCobro()) {
            return;
        }

        // Click en "Confirmar pedido" → modal en modo cobrar. Si después se
        // procesa (confirmarPago) se persiste todo.
        $this->modalPagoEnModoCobro = true;

        // Si no hay FP seleccionada, abrir directo el modal mixto.
        if (! $this->formaPagoId) {
            $this->abrirModalDesglose();

            return;
        }

        // Con FP elegida: delegar al flujo del trait. Si la FP es simple,
        // procesa directo (efectivo → modal vuelto, moneda extranjera →
        // modal cotización, débito/crédito → desglose con un solo pago).
        // Si la FP es mixta, abre el modal de desglose.
        $this->iniciarCobro();
    }

    /**
     * Validaciones previas al cobro: items, beeper, cliente, cálculo. Devuelve
     * true si todo OK. Despacha toast-error y devuelve false en caso contrario.
     */
    protected function validarAntesDeCobro(): bool
    {
        if (empty($this->items)) {
            $this->dispatch('toast-error', message: __('El pedido debe tener al menos un artículo'));

            return false;
        }
        if ($this->tipo === PedidoDelivery::TIPO_TAKE_AWAY
            && $this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
            $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

            return false;
        }

        // RF-04: delivery exige dirección de entrega para confirmar (el
        // borrador puede guardarse sin ella).
        if ($this->tipo === PedidoDelivery::TIPO_DELIVERY && empty($this->direccionEntrega)) {
            $this->dispatch('toast-error', message: __('Cargá la dirección de entrega antes de confirmar'));
            $this->abrirModalDireccion();

            return false;
        }

        // RF-06/D7: fuera de alcance solo confirmable con permiso.
        if ($this->tipo === PedidoDelivery::TIPO_DELIVERY
            && $this->alcanceEnvio === CotizacionEnvio::ALCANCE_FUERA
            && ! auth()->user()?->hasPermissionTo('func.pedidos_delivery.forzar_alcance')) {
            $this->dispatch('toast-error', message: __('La dirección está fuera del alcance de entrega y no tenés permiso para forzarla'));

            return false;
        }

        // RF-16: advertir (no bloquear) artículos no disponibles para el tipo.
        $this->advertirArticulosNoDisponibles();

        // Cliente NO es obligatorio: sin cliente ni datos temporales se graba
        // "Consumidor final" (ver construirDataPedido). El teléfono es opcional.
        $this->calcularVenta();
        if (! $this->resultado) {
            $this->dispatch('toast-error', message: __('No se pudo calcular el pedido'));

            return false;
        }

        return true;
    }

    /**
     * Abre el modal de desglose mixto (igual que NuevaVenta::abrirModalDesglose).
     * Carga FPs, resetea estado y muestra el modal del trait WithPagosDesglose.
     */
    public function abrirModalDesglose(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: __('El carrito está vacío'));

            return;
        }

        $this->cargarFormasPagoSucursal();

        $this->desglosePagos = [];
        $this->montoPendienteDesglose = $this->resultado['total_final'] ?? 0;
        $this->totalConAjustes = $this->montoPendienteDesglose;
        $this->resetNuevoPago();
        $this->mostrarModalPago = true;
    }

    /**
     * Abre el modal de desglose con los pagos ya cargados (espejo de
     * NuevaVenta::editarDesglose). Se invoca desde el botón "Editar desglose"
     * en `_resumen-totales.blade.php`. Si no hay desglose, abre como nuevo.
     */
    public function editarDesglose(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: __('El carrito está vacío'));

            return;
        }

        if (empty($this->desglosePagos)) {
            $this->abrirModalDesglose();

            return;
        }

        $this->cargarFormasPagoSucursal();

        $totalDesglosado = collect($this->desglosePagos)->sum('monto_base');
        $this->montoPendienteDesglose = max(0, ($this->resultado['total_final'] ?? 0) - $totalDesglosado);
        $this->totalConAjustes = collect($this->desglosePagos)->sum('monto_final');

        $this->resetNuevoPago();
        $this->mostrarModalPago = true;
    }

    /**
     * Marca/desmarca un pago del desglose como planificado (guardar sin cobrar).
     * Pagos planificados no afectan caja al persistir.
     */
    public function togglePlanificadoEnDesglose(int $index): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }
        $this->desglosePagos[$index]['planificado'] = ! (bool) ($this->desglosePagos[$index]['planificado'] ?? false);
    }

    /**
     * Confirma el pedido SIN agregar pagos. Persiste como CONFIRMADO con
     * número y stock descontado, estado_pago=pendiente. El usuario podrá
     * cobrar después desde la lista ("Cobrar pendiente").
     */
    /**
     * Persiste el pedido CONFIRMADO sin cobrar. Si hay desglose configurado
     * (mixto o simple), los guarda como pagos PLANIFICADOS — no tocan caja,
     * pero quedan registrados para que después se materialicen via "Cobrar
     * pendiente" en la lista. Si NO hay desglose, persiste solo el pedido.
     */
    public function confirmarSinCobrar(): void
    {
        if (! $this->validarAntesDeCobro()) {
            return;
        }

        // Pedido totalmente invitado (total_final<=0): no hay nada que cobrar,
        // el service marca estado_pago=pagado automáticamente. Saltamos la
        // validación del desglose porque no aplica.
        $totalFinal = (float) ($this->resultado['total_final'] ?? 0);
        $esInvitacionCompleta = $this->esInvitacionTotal && $totalFinal <= 0.005;

        // Si hay desglose cargado, debe estar COMPLETO (cubrir el total). Un
        // desglose parcial guardado como planificado da información incorrecta
        // (el pedido quedaría con total = monto del desglose). El usuario debe
        // completar el desglose o quitar todos los pagos si solo quiere
        // confirmar sin cobrar.
        if (! $esInvitacionCompleta && ! empty($this->desglosePagos) && ! $this->desgloseCompleto()) {
            $this->dispatch('toast-error', message: __('El desglose de pagos es parcial. Completá el desglose hasta cubrir el total o quitá los pagos para confirmar sin cobrar.'));

            return;
        }

        try {
            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoDelivery::find($this->pedidoId);
                if (! $pedido) {
                    $this->dispatch('toast-error', message: __('Pedido no encontrado'));

                    return;
                }
                $this->pedidoService->actualizarPedido($pedido, $data, $detalles);
            } else {
                $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: false);
            }

            // Si el pedido quedó en BORRADOR (porque era un borrador en edición),
            // "Confirmar sin cobrar" debe transicionarlo a CONFIRMADO: asignar
            // número, descontar stock, disparar PedidoCreado.
            $pedido = $pedido->fresh();
            if ($pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR) {
                $this->pedidoService->confirmarBorrador($pedido);
                $pedido->refresh();
            }

            // Si hay desglose, persistirlo todo como planificado. En edición
            // con pagos preexistentes NO duplicamos: esos pagos se gestionan
            // desde la lista (acción "Cobrar pendiente").
            $cuentaDesglose = 0;
            if (! ($this->modoEdicion && $this->cuentaPagosOriginales > 0)) {
                $cuentaDesglose = count($this->desglosePagos ?? []);
                if ($cuentaDesglose > 0) {
                    foreach ($this->desglosePagos as $pago) {
                        $this->pedidoService->agregarPago($pedido, $this->normalizarPagoDelDesglose($pago, planificadoForzado: true));
                    }
                }
            }

            $msg = $this->modoEdicion
                ? __('Pedido actualizado sin cobrar')
                : ($cuentaDesglose > 0
                    ? __('Pedido #:numero confirmado con :n pagos planificados', ['numero' => $pedido->numero, 'n' => $cuentaDesglose])
                    : __('Pedido confirmado sin cobrar #:numero', ['numero' => $pedido->numero]));
            $this->dispatch('toast-success', message: $msg);

            $this->mostrarModalPago = false;
            $this->dispatch('pedido-guardado');
        } catch (Exception $e) {
            Log::error('Error al confirmar sin cobrar', [
                'pedido_id' => $this->pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Normaliza un pago del desglose (WithPagosDesglose) al payload que espera
     * PedidoDeliveryService::agregarPago. Si `$planificadoForzado` viene en
     * true, marca el pago como planificado independientemente del flag interno.
     */
    protected function normalizarPagoDelDesglose(array $pago, bool $planificadoForzado = false): array
    {
        $esCC = (bool) ($pago['es_cuenta_corriente'] ?? false);
        if (! $esCC && isset($pago['codigo'])) {
            $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
        }

        return [
            'forma_pago_id' => (int) $pago['forma_pago_id'],
            'monto_base' => (float) ($pago['monto_base'] ?? $pago['monto_final']),
            'ajuste_porcentaje' => (float) ($pago['ajuste_porcentaje'] ?? 0),
            'monto_ajuste' => (float) ($pago['monto_ajuste'] ?? 0),
            'monto_final' => (float) $pago['monto_final'],
            'monto_recibido' => $pago['monto_recibido'] ?? null,
            'vuelto' => (float) ($pago['vuelto'] ?? 0),
            'cuotas' => $pago['cuotas'] ?? null,
            'recargo_cuotas_porcentaje' => $pago['recargo_cuotas_porcentaje'] ?? null,
            'recargo_cuotas_monto' => $pago['recargo_cuotas_monto'] ?? null,
            'monto_cuota' => $pago['monto_cuota'] ?? null,
            'referencia' => $pago['referencia'] ?? null,
            'observaciones' => $pago['observaciones'] ?? null,
            'es_cuenta_corriente' => $esCC,
            'es_pago_puntos' => (bool) ($pago['es_pago_puntos'] ?? false),
            'puntos_usados' => $pago['puntos_usados'] ?? 0,
            'afecta_caja' => (bool) ($pago['afecta_caja'] ?? ! $esCC),
            'moneda_id' => $pago['moneda_id'] ?? null,
            'monto_moneda_original' => $pago['monto_moneda_original'] ?? null,
            'tipo_cambio_id' => $pago['tipo_cambio_id'] ?? null,
            'tipo_cambio_tasa' => $pago['tipo_cambio_tasa'] ?? null,
            'planificado' => $planificadoForzado ? true : (bool) ($pago['planificado'] ?? false),
        ];
    }

    protected function guardar(bool $esBorrador): void
    {
        try {
            if (empty($this->items)) {
                $this->dispatch('toast-error', message: __('El pedido debe tener al menos un artículo'));

                return;
            }

            if (! $esBorrador && $this->tipo === PedidoDelivery::TIPO_TAKE_AWAY
                && $this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
                $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

                return;
            }

            if (! $esBorrador && $this->tipo === PedidoDelivery::TIPO_DELIVERY && empty($this->direccionEntrega)) {
                $this->dispatch('toast-error', message: __('Cargá la dirección de entrega antes de confirmar'));
                $this->abrirModalDireccion();

                return;
            }

            // Cliente NO es obligatorio: sin cliente ni datos temporales se graba
            // "Consumidor final" (ver construirDataPedido). El teléfono es opcional.

            $this->calcularVenta();
            if (! $this->resultado) {
                $this->dispatch('toast-error', message: __('No se pudo calcular el pedido'));

                return;
            }

            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoDelivery::find($this->pedidoId);
                if (! $pedido) {
                    $this->dispatch('toast-error', message: __('Pedido no encontrado'));

                    return;
                }
                $this->pedidoService->actualizarPedido($pedido, $data, $detalles);
                $this->dispatch('toast-success', message: __('Pedido actualizado'));
            } else {
                $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: $esBorrador);
                $msg = $esBorrador
                    ? __('Borrador guardado')
                    : __('Pedido confirmado #:numero', ['numero' => $pedido->numero]);
                $this->dispatch('toast-success', message: $msg);
            }

            // Avisar al padre: cerrar modal y refrescar la lista.
            $this->dispatch('pedido-guardado');
        } catch (Exception $e) {
            Log::error('Error al guardar pedido delivery', [
                'pedido_id' => $this->pedidoId,
                'es_borrador' => $esBorrador,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== CONSTRUCCIÓN DE PAYLOAD ====================

    protected function construirDataPedido(): array
    {
        $r = $this->resultado;
        $totalBase = (float) ($r['total_final'] ?? $r['total'] ?? 0);

        // Calcular el total_final del pedido. El criterio:
        //  - Desglose COMPLETO (suma de monto_base de los pagos cubre el total
        //    real del pedido): usar totalConAjustes (refleja recargos/descuentos
        //    por FP). Aplica tanto a una sola FP como a desgloses mixtos —
        //    antes se exigía es_mixta, pero eso dejaba a las FP simples con
        //    ajuste (ej: efectivo 10% off) sin persistir el ajuste en
        //    total_final, generando estado_pago = parcial errado.
        //  - FP simple con ajuste sin modal abierto: ajusteFormaPagoInfo[total_con_ajuste].
        //  - Sino (sin desglose, o desglose parcial): totalBase. No truncamos
        //    el monto del pedido al monto cargado en un desglose incompleto.
        //
        // desgloseCompleto() del trait valida que Σ monto_base ≈ total_final
        // del cálculo, con tolerancia 0.05 para redondeos.
        $esMixto = (bool) ($this->ajusteFormaPagoInfo['es_mixta'] ?? false);
        $hayDesglose = ! empty($this->desglosePagos);
        $desgloseEstaCompleto = $hayDesglose && $this->desgloseCompleto();

        if ($desgloseEstaCompleto) {
            $totalFinal = (float) $this->totalConAjustes;
        } elseif (! $hayDesglose && ! $esMixto
            && isset($this->ajusteFormaPagoInfo['total_con_ajuste'])
            && (float) $this->ajusteFormaPagoInfo['total_con_ajuste'] > 0) {
            // FP simple seleccionada en el dropdown sin haber abierto el modal
            // de desglose (caso 1 pago directo).
            $totalFinal = (float) $this->ajusteFormaPagoInfo['total_con_ajuste'];
        } else {
            $totalFinal = $totalBase;
        }
        $ajusteForma = round($totalFinal - $totalBase, 2);

        // D17: los data-totales van SIN envío — el service materializa el
        // renglón-concepto desde `costo_envio` y ajusta los totales por delta.
        // El resultado en memoria SÍ lo incluye (aplicarEnvioAlResultado).
        $envio = $this->montoEnvioVigente();
        $ivaEnvio = $envio > 0 ? round($envio - $envio / 1.21, 2) : 0.0;

        return [
            'tipo' => $this->tipo,
            'origen' => PedidoDelivery::ORIGEN_PANEL,
            'sucursal_id' => $this->sucursalId,
            'cliente_id' => $this->clienteSeleccionado,
            'nombre_cliente_temporal' => $this->clienteSeleccionado ? null : (trim($this->nombreClienteTemporal ?? '') ?: self::NOMBRE_CLIENTE_DEFAULT),
            'telefono_cliente_temporal' => $this->clienteSeleccionado ? null : (trim($this->telefonoClienteTemporal ?? '') ?: null),
            'caja_id' => $this->cajaSeleccionada,
            'canal_venta_id' => $this->canalVentaId,
            'forma_venta_id' => $this->formaVentaId,
            'lista_precio_id' => $this->listaPrecioId,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'identificador' => trim($this->identificador ?? '') ?: null,
            'numero_beeper' => trim($this->numeroBeeper ?? '') ?: null,
            // Dirección de entrega (RF-04, snapshot) + envío (RF-06/D7).
            'direccion_entrega' => $this->direccionEntrega,
            'direccion_referencia' => $this->direccionReferencia,
            'localidad_entrega_id' => $this->localidadEntregaId,
            'latitud' => $this->entregaLatitud,
            'longitud' => $this->entregaLongitud,
            'zona_id' => $this->tipo === PedidoDelivery::TIPO_DELIVERY ? $this->zonaEnvioId : null,
            'costo_envio' => $envio,
            'costo_envio_manual' => $this->costoEnvioManual,
            'costo_envio_usuario_id' => $this->costoEnvioManual ? Auth::id() : null,
            'distancia_km' => $this->distanciaKm,
            'fuera_de_alcance' => $this->alcanceEnvio === CotizacionEnvio::ALCANCE_FUERA,
            // Promesa de entrega (RF-15 core): explícita desde el alta; null en
            // manual sin botón (y el service no la autocalcula en ese modo).
            'hora_pactada_at' => $this->resolverHoraPactada(),
            '_actualizar_direccion_cliente' => ! $this->entregarEnOtraDireccion,
            'subtotal' => round((float) ($r['subtotal'] ?? 0) - $envio, 2),
            'iva' => round((float) ($r['iva_total'] ?? 0) - $ivaEnvio, 2),
            'descuento' => (float) ($r['descuento_total'] ?? 0),
            'total' => round($totalBase - $envio, 2),
            'ajuste_forma_pago' => $ajusteForma,
            'total_final' => round($totalFinal - $envio, 2),
            'descuento_general_tipo' => $this->descuentoGeneralActivo ? $this->descuentoGeneralTipo : null,
            'descuento_general_valor' => $this->descuentoGeneralActivo ? $this->descuentoGeneralValor : null,
            'descuento_general_monto' => $this->descuentoGeneralActivo ? $this->descuentoGeneralMonto : 0,
            'descuento_general_aplicado_por' => $this->descuentoGeneralAplicadoPor,
            'cupon_id' => $this->cuponAplicado['id'] ?? null,
            'cupon_codigo_snapshot' => $this->cuponAplicado['codigo'] ?? null,
            'cupon_descripcion_snapshot' => $this->cuponAplicado['descripcion'] ?? null,
            'monto_cupon' => (float) ($this->cuponMontoDescuento ?? 0),
            'puntos_ganados' => 0,
            // Paridad puntos con Venta:
            //   puntos_usados = total de puntos canjeados (monto + artículos),
            //   puntos_canjeados_pago = solo el canje como medio de pago,
            //   puntos_canjeados_articulos = solo los artículos pagados con puntos,
            //   puntos_usados_monto = equivalente en pesos del canje monto,
            //   articulos_canjeados_monto = equivalente en pesos del canje artículo.
            'puntos_usados' => (int) (($this->canjePuntosActivo ? $this->canjePuntosUnidades : 0) + $this->calcularPuntosUsadosEnArticulos()),
            'puntos_canjeados_pago' => (int) ($this->canjePuntosActivo ? $this->canjePuntosUnidades : 0),
            'puntos_canjeados_articulos' => (int) $this->calcularPuntosUsadosEnArticulos(),
            'puntos_usados_monto' => (float) ($r['puntos_usados_monto'] ?? 0),
            'articulos_canjeados_monto' => (float) ($r['articulos_canjeados_monto'] ?? 0),
            // Promociones aplicadas a nivel pedido (espejo de Venta).
            // PedidoDeliveryService::guardarPromocionesPedido las inserta en
            // pedido_delivery_promociones.
            '_promociones_comunes' => $r['promociones_comunes_aplicadas'] ?? [],
            '_promociones_especiales' => $r['promociones_especiales_aplicadas'] ?? [],
            'observaciones' => trim($this->observaciones ?? '') ?: null,
            // Invitacion (cortesia). Cabecera: solo se llena cuando el pedido
            // completo es cortesia. total_invitado se persiste siempre como
            // cache del SUM(detalle.monto_invitado) para evitar joinear en
            // listados y reportes.
            'es_invitacion_total' => $this->esInvitacionTotal,
            'invitacion_motivo' => $this->esInvitacionTotal
                ? (trim($this->motivoInvitacionTotal) ?: null)
                : null,
            'invitado_por_usuario_id' => $this->esInvitacionTotal ? Auth::id() : null,
            'invitado_at' => $this->esInvitacionTotal ? now() : null,
            'total_invitado' => (float) $this->totalInvitado,
        ];
    }

    protected function construirDetallesPedido(): array
    {
        // Trazabilidad de cupón por ítem: WithCupones calcula la distribución.
        $descuentoCuponPorItem = method_exists($this, 'calcularDescuentoCuponPorItem')
            ? $this->calcularDescuentoCuponPorItem()
            : [];

        $detalles = [];
        foreach ($this->items as $index => $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $precioUnitario = (float) $item['precio'];
            $ivaPorc = (float) ($item['iva_porcentaje'] ?? 0);
            $precioIvaIncluido = (bool) ($item['precio_iva_incluido'] ?? true);
            $precioSinIva = ($precioIvaIncluido && $ivaPorc > 0)
                ? round($precioUnitario / (1 + $ivaPorc / 100), 2)
                : $precioUnitario;

            // Atribución de promociones por línea desde $this->resultado['items'][$index]
            // (espejo de WithPagosDesglose::confirmarPago).
            $itemResultado = $this->resultado['items'][$index] ?? [];
            $promocionesComunes = $itemResultado['promociones_comunes'] ?? [];
            $promocionesEspeciales = $itemResultado['promociones_especiales'] ?? [];
            $esConcepto = (bool) ($item['es_concepto'] ?? false);

            $descuentoPromocion = $esConcepto ? 0 : (float) ($itemResultado['descuento_comun'] ?? 0);
            $descuentoPromocionEspecial = 0.0;
            if (! $esConcepto) {
                foreach ($promocionesEspeciales as $promoEsp) {
                    $descuentoPromocionEspecial += (float) ($promoEsp['descuento'] ?? 0);
                }
            }
            $tienePromocion = ! $esConcepto && (! empty($promocionesComunes) || ! empty($promocionesEspeciales));

            $descuentoCupon = (float) ($descuentoCuponPorItem[$index] ?? ($item['descuento_cupon'] ?? 0));

            $detalles[] = [
                'articulo_id' => $item['articulo_id'] ?? null,
                'es_concepto' => $esConcepto,
                'concepto_descripcion' => $item['concepto_descripcion'] ?? null,
                'concepto_categoria_id' => $item['concepto_categoria_id'] ?? $item['categoria_id'] ?? null,
                'tipo_iva_id' => null,
                'lista_precio_id' => $esConcepto ? null : $this->listaPrecioId,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'precio_sin_iva' => $precioSinIva,
                'descuento' => 0,
                'precio_lista' => (float) ($item['precio_base'] ?? $item['precio']),
                'precio_opcionales' => (float) ($item['precio_opcionales'] ?? 0),
                'subtotal' => (float) $item['precio'] * $cantidad,
                'ajuste_manual_tipo' => $item['ajuste_manual_tipo'] ?? null,
                'ajuste_manual_valor' => $item['ajuste_manual_valor'] ?? null,
                'ajuste_manual_origen' => $item['ajuste_manual_origen'] ?? null,
                'ajuste_manual_aplicado_por' => $item['ajuste_manual_aplicado_por'] ?? null,
                'precio_sin_ajuste_manual' => $item['precio_sin_ajuste_manual'] ?? null,
                'pagado_con_puntos' => (bool) ($item['pagado_con_puntos'] ?? false),
                'puntos_usados' => (int) ($item['puntos_usados'] ?? 0),
                'iva_porcentaje' => (float) ($item['iva_porcentaje'] ?? 0),
                'iva_monto' => (float) ($item['iva_monto'] ?? 0),
                'descuento_porcentaje' => 0,
                'descuento_monto' => 0,
                'descuento_promocion' => round($descuentoPromocion, 2),
                'descuento_promocion_especial' => round($descuentoPromocionEspecial, 2),
                'descuento_cupon' => round($descuentoCupon, 2),
                'descuento_lista' => 0,
                'tiene_promocion' => $tienePromocion,
                'total' => (float) $item['precio'] * $cantidad,
                'opcionales' => $item['opcionales'] ?? [],
                // Promociones aplicadas a la línea para que el service las
                // persista en pedido_delivery_detalle_promociones (paridad
                // con VentaService::crearDetalleVenta + guardarPromocionesDetalle).
                '_promociones_item' => $esConcepto ? [] : [
                    'promociones_comunes' => $promocionesComunes,
                    'promociones_especiales' => $promocionesEspeciales,
                ],
                // Invitacion (cortesia) por linea. El trait WithInvitaciones
                // mantiene estas claves en memoria; aca solo las propagamos.
                'es_invitacion' => (bool) ($item['es_invitacion'] ?? false),
                'invitacion_motivo' => $item['invitacion_motivo'] ?? null,
                'invitado_por_usuario_id' => $item['invitado_por_usuario_id'] ?? null,
                'invitado_at' => $item['invitado_at'] ?? null,
                'monto_invitado' => (float) ($item['monto_invitado'] ?? 0),
                'precio_unitario_original' => isset($item['precio_unitario_original'])
                    ? (float) $item['precio_unitario_original']
                    : null,
            ];
        }

        return $detalles;
    }

    // ==================== MÉTODOS DEL SELECTOR DE FP (copiados de NuevaVenta) ====================
    // WithCalculoVenta llama estos métodos al final de cada cálculo si hay
    // formaPagoId. Viven en NuevaVenta (no en trait), así que los duplicamos
    // acá idénticos para mantener el mismo comportamiento de cuotas / ajuste.

    protected function cargarCuotasFormaPago(): void
    {
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;

        if (! $this->formaPagoId) {
            return;
        }

        $formaPago = FormaPago::find($this->formaPagoId);
        if (! $formaPago || ! $formaPago->permite_cuotas || $formaPago->es_mixta) {
            return;
        }

        $this->formaPagoPermiteCuotas = true;

        $cuotas = FormaPagoCuota::where('forma_pago_id', $this->formaPagoId)
            ->where('activo', true)
            ->orderBy('cantidad_cuotas')
            ->get();

        $totalBase = $this->resultado['total_final'] ?? 0;

        foreach ($cuotas as $cuota) {
            $configSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();
            if ($configSucursal && ! $configSucursal->activo) {
                continue;
            }
            $recargoPorcentaje = $cuota->getRecargoParaSucursal($this->sucursalId);
            $recargoMonto = round($totalBase * ($recargoPorcentaje / 100), 2);
            $totalConRecargo = round($totalBase + $recargoMonto, 2);
            $valorCuota = $cuota->cantidad_cuotas > 0 ? round($totalConRecargo / $cuota->cantidad_cuotas, 2) : 0;

            $this->cuotasFormaPagoDisponibles[] = [
                'id' => $cuota->id,
                'cantidad_cuotas' => $cuota->cantidad_cuotas,
                'recargo_porcentaje' => $recargoPorcentaje,
                'recargo_monto' => $recargoMonto,
                'total_con_recargo' => $totalConRecargo,
                'valor_cuota' => $valorCuota,
                'descripcion' => $cuota->descripcion,
            ];
        }
    }

    protected function calcularInfoCuotaSeleccionada(): void
    {
        $this->resetInfoCuotaSeleccionada();
        if (! $this->cuotaSeleccionadaId) {
            return;
        }
        $cuotaInfo = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $this->cuotaSeleccionadaId);
        if (! $cuotaInfo) {
            return;
        }
        $this->infoCuotaSeleccionada = [
            'cantidad_cuotas' => $cuotaInfo['cantidad_cuotas'],
            'recargo_porcentaje' => $cuotaInfo['recargo_porcentaje'],
            'recargo_monto' => $cuotaInfo['recargo_monto'],
            'valor_cuota' => $cuotaInfo['valor_cuota'],
            'total_con_recargo' => $cuotaInfo['total_con_recargo'],
            'descripcion' => $this->formatearDescripcionCuota($cuotaInfo),
        ];
    }

    protected function formatearDescripcionCuota(array $cuotaInfo): string
    {
        $cantCuotas = $cuotaInfo['cantidad_cuotas'];
        $recargo = $cuotaInfo['recargo_porcentaje'];
        if ($cantCuotas === 1) {
            return '1 pago';
        }
        $desc = "{$cantCuotas} cuotas de $".number_format($cuotaInfo['valor_cuota'], 2, ',', '.');
        $desc .= $recargo > 0 ? " (+{$recargo}%)" : ' (sin interés)';

        return $desc;
    }

    protected function resetInfoCuotaSeleccionada(): void
    {
        $this->infoCuotaSeleccionada = [
            'cantidad_cuotas' => 1,
            'recargo_porcentaje' => 0,
            'recargo_monto' => 0,
            'valor_cuota' => 0,
            'total_con_recargo' => 0,
            'descripcion' => '1 pago',
        ];
    }

    protected function calcularAjusteFormaPago(): void
    {
        $this->ajusteFormaPagoInfo = [
            'nombre' => '',
            'porcentaje' => 0,
            'monto' => 0,
            'total_con_ajuste' => 0,
            'es_mixta' => false,
            'cuotas' => 1,
            'recargo_cuotas_porcentaje' => 0,
            'recargo_cuotas_monto' => 0,
            'valor_cuota' => 0,
        ];

        if (! $this->formaPagoId || ! $this->resultado) {
            return;
        }

        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (! $fp) {
            $formaPago = FormaPago::find($this->formaPagoId);
            if (! $formaPago) {
                return;
            }
            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $this->formaPagoId)
                ->where('sucursal_id', $this->sucursalId)
                ->first();
            $ajuste = $configSucursal && $configSucursal->ajuste_porcentaje !== null
                ? $configSucursal->ajuste_porcentaje
                : ($formaPago->ajuste_porcentaje ?? 0);
            $fp = [
                'id' => $formaPago->id,
                'nombre' => $formaPago->nombre,
                'ajuste_porcentaje' => $ajuste,
                'es_mixta' => $formaPago->es_mixta ?? false,
            ];
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        // El envío es un valor FIJO: queda fuera del ajuste por forma de pago
        // y del recargo de cuotas, igual que de descuentos/promos/puntos (D17).
        // Ej: efectivo -10% sobre $1000 de productos + $500 de envío = $1400.
        // En cobro rápido el saldo no se puede descomponer → base completa.
        $baseAjuste = $this->modoCobroRapido
            ? (float) $totalBase
            : max(0, round((float) $totalBase - $this->montoEnvioVigente(), 2));
        $ajustePorcentaje = $fp['ajuste_porcentaje'] ?? 0;
        $montoAjuste = round($baseAjuste * ($ajustePorcentaje / 100), 2) + 0;
        $totalConAjuste = round($totalBase + $montoAjuste, 2) + 0;

        $cantidadCuotas = 1;
        $recargoCuotasPorcentaje = 0;
        $recargoCuotasMonto = 0;
        $valorCuota = $totalConAjuste;

        if ($this->cuotaSeleccionadaId && ! empty($this->cuotasFormaPagoDisponibles)) {
            $cuotaInfo = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $this->cuotaSeleccionadaId);
            if ($cuotaInfo) {
                $cantidadCuotas = $cuotaInfo['cantidad_cuotas'];
                $recargoCuotasPorcentaje = $cuotaInfo['recargo_porcentaje'];
                $recargoCuotasMonto = round(($baseAjuste + $montoAjuste) * ($recargoCuotasPorcentaje / 100), 2);
                $totalConAjuste = round($totalConAjuste + $recargoCuotasMonto, 2);
                $valorCuota = $cantidadCuotas > 0 ? round($totalConAjuste / $cantidadCuotas, 2) : $totalConAjuste;
                $this->infoCuotaSeleccionada = [
                    'cantidad_cuotas' => $cantidadCuotas,
                    'recargo_porcentaje' => $recargoCuotasPorcentaje,
                    'recargo_monto' => $recargoCuotasMonto,
                    'valor_cuota' => $valorCuota,
                    'total_con_recargo' => $totalConAjuste,
                    'descripcion' => $this->formatearDescripcionCuota([
                        'cantidad_cuotas' => $cantidadCuotas,
                        'recargo_porcentaje' => $recargoCuotasPorcentaje,
                        'valor_cuota' => $valorCuota,
                    ]),
                ];
            }
        }

        $this->ajusteFormaPagoInfo = [
            'nombre' => $fp['nombre'],
            'porcentaje' => $ajustePorcentaje,
            'monto' => $montoAjuste,
            'total_con_ajuste' => $totalConAjuste,
            'es_mixta' => $fp['es_mixta'] ?? false,
            'cuotas' => $cantidadCuotas,
            'recargo_cuotas_porcentaje' => $recargoCuotasPorcentaje,
            'recargo_cuotas_monto' => $recargoCuotasMonto,
            'valor_cuota' => $valorCuota,
        ];

        $this->actualizarDesgloseIvaConAjusteFormaPago($montoAjuste, $recargoCuotasMonto);
    }

    protected function actualizarDesgloseIvaConAjusteFormaPago(float $montoAjusteFormaPago, float $montoRecargoCuotas): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            return;
        }
        $desglose = $this->resultado['desglose_iva'];
        $totalNetoBase = $desglose['total_neto'];

        if ($totalNetoBase == 0 || ($montoAjusteFormaPago == 0 && $montoRecargoCuotas == 0)) {
            $this->resultado['desglose_iva']['ajuste_forma_pago'] = 0;
            $this->resultado['desglose_iva']['recargo_cuotas'] = 0;
            $this->resultado['desglose_iva']['total_con_ajuste_fp'] = $desglose['total'];

            return;
        }

        $ajusteTotal = $montoAjusteFormaPago + $montoRecargoCuotas;
        $totalSubtotalBase = array_sum(array_column($desglose['por_alicuota'], 'subtotal'));

        $nuevoPorAlicuota = [];
        foreach ($desglose['por_alicuota'] as $alicuota) {
            $proporcion = $totalSubtotalBase > 0 ? $alicuota['subtotal'] / $totalSubtotalBase : 0;
            $ajusteAlicuotaConIva = $ajusteTotal * $proporcion;
            $ajusteNetoAlicuota = $alicuota['porcentaje'] > 0
                ? $ajusteAlicuotaConIva / (1 + $alicuota['porcentaje'] / 100)
                : $ajusteAlicuotaConIva;
            $nuevoNeto = $alicuota['neto'] + $ajusteNetoAlicuota;
            $nuevoIva = $nuevoNeto * ($alicuota['porcentaje'] / 100);

            $nuevoPorAlicuota[] = [
                'codigo' => $alicuota['codigo'],
                'nombre' => $alicuota['nombre'],
                'porcentaje' => $alicuota['porcentaje'],
                'neto_sin_descuento' => $alicuota['neto_sin_descuento'],
                'iva_sin_descuento' => $alicuota['iva_sin_descuento'],
                'subtotal_sin_descuento' => $alicuota['subtotal_sin_descuento'],
                'neto' => round($alicuota['neto'], 3),
                'iva' => round($alicuota['iva'], 3),
                'subtotal' => round($alicuota['subtotal'], 3),
                'descuento_aplicado' => $alicuota['descuento_aplicado'],
                'neto_con_ajuste_fp' => round($nuevoNeto, 3),
                'iva_con_ajuste_fp' => round($nuevoIva, 3),
                'subtotal_con_ajuste_fp' => round($nuevoNeto + $nuevoIva, 3),
                'ajuste_fp_aplicado' => round($ajusteAlicuotaConIva, 3),
            ];
        }

        $totalNetoConAjuste = array_sum(array_column($nuevoPorAlicuota, 'neto_con_ajuste_fp'));
        $totalIvaConAjuste = array_sum(array_column($nuevoPorAlicuota, 'iva_con_ajuste_fp'));
        $totalConAjuste = array_sum(array_column($nuevoPorAlicuota, 'subtotal_con_ajuste_fp'));

        $this->resultado['desglose_iva'] = [
            'por_alicuota' => $nuevoPorAlicuota,
            'total_neto' => $desglose['total_neto'],
            'total_iva' => $desglose['total_iva'],
            'total' => $desglose['total'],
            'descuento_aplicado' => $desglose['descuento_aplicado'],
            'ajuste_forma_pago' => round($montoAjusteFormaPago, 3),
            'recargo_cuotas' => round($montoRecargoCuotas, 3),
            'total_neto_con_ajuste_fp' => round($totalNetoConAjuste, 3),
            'total_iva_con_ajuste_fp' => round($totalIvaConAjuste, 3),
            'total_con_ajuste_fp' => round($totalConAjuste, 3),
        ];
    }

    // ==================== SELECTOR DE CUOTAS (UI) ====================

    public function toggleCuotasSelector(): void
    {
        $this->cuotasSelectorAbierto = ! $this->cuotasSelectorAbierto;
    }

    // ==================== HOOKS LIVEWIRE FORMA DE PAGO ====================

    public function updatedFormaPagoId($value): void
    {
        // Limpiar desglose previo y reset de cuotas (mismo flow que NuevaVenta).
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = 0;
        $this->totalConAjustes = 0;
        $this->cuotaSeleccionadaId = null;
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;
        $this->resetInfoCuotaSeleccionada();

        $this->cargarCuotasFormaPago();
        $this->actualizarPreciosItems();
        $this->calcularVenta(); // dispara calcularAjusteFormaPago internamente

        // Si la FP elegida es mixta, abrir el modal de desglose automáticamente
        // (igual que NuevaVenta). MODO PREVIEW: al confirmar el modal vuelve
        // al alta con los totales actualizados, no procesa el pedido.
        if (($this->ajusteFormaPagoInfo['es_mixta'] ?? false) && ! empty($this->items)) {
            $this->modalPagoEnModoCobro = false;
            $this->abrirModalDesglose();
        }
    }

    public function updatedCuotaSeleccionadaId($value): void
    {
        $this->calcularInfoCuotaSeleccionada();
        $this->calcularAjusteFormaPago();
    }

    // ==================== OVERRIDE: BOTÓN CONFIRMAR DEL MODAL ====================

    /**
     * Override del "Confirmar" del modal de pago.
     *
     * Distingue dos contextos:
     * - **Preview** (`$modalPagoEnModoCobro=false`): el modal se abrió por
     *   seleccionar una FP mixta en el dropdown. Confirmar acá NO procesa
     *   el pedido — solo cierra el modal manteniendo el desglose. El alta
     *   muestra los totales actualizados y el usuario puede seguir editando.
     *   Comportamiento idéntico al "Confirmar" del modal de NuevaVenta.
     * - **Cobrar** (`$modalPagoEnModoCobro=true`): el modal se abrió por
     *   click en "Confirmar pedido". Confirmar acá procesa: persiste el
     *   pedido y agrega los pagos.
     */
    public function confirmarPago(): void
    {
        if (! $this->desgloseCompleto()) {
            $this->dispatch('toast-error', message: __('Complete el desglose de pagos'));

            return;
        }

        if ($this->modalPagoEnModoCobro) {
            // Enganche compartido del cobro por integración (QR). Este modal
            // procesa directo (no pasa por iniciarCobro/verificarPuntoVentaYProcesar
            // como NuevaVenta), así que invocamos el MISMO punto único del trait
            // para que cualquier lógica de cobro por integración —presente o
            // futura— aplique también acá. Si dispara el QR, esperamos al polling
            // (pollearCobroIntegracion reanuda el flujo al aprobarse).
            if ($this->interceptarCobroPorIntegracion()) {
                return;
            }

            $this->procesarVentaConDesglose();

            return;
        }

        // Preview: cerrar modal manteniendo desglose. Actualizar ajuste de FP
        // para que el resumen del alta refleje el total final con los pagos.
        $totalBase = (float) ($this->resultado['total_final'] ?? 0);
        $montoAjuste = (float) $this->totalConAjustes - $totalBase;
        $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
        $this->ajusteFormaPagoInfo['total_con_ajuste'] = (float) $this->totalConAjustes;

        $this->mostrarModalPago = false;
        $this->dispatch('toast-success', message: __('Desglose guardado. Revisá los totales y confirmá el pedido.'));
    }

    /**
     * Confirma el pedido como invitación total en un solo click desde el modal
     * de cobro. Invita todos los items (vía trait WithInvitaciones) y persiste.
     *
     * El motivo y el permiso los valida `confirmarInvitarTodo()` del trait;
     * si falla, abortamos antes de tocar la persistencia. `procesarVentaConDesglose()`
     * detecta `total_final=0` (todo invitado) y persiste sin desglose ni caja.
     */
    public function confirmarInvitacionTotal(): void
    {
        $this->confirmarInvitarTodo();

        // Si el trait no pudo marcar (sin permiso, motivo vacío), abortamos.
        if (! $this->esInvitacionTotal) {
            return;
        }

        $this->procesarVentaConDesglose();
    }

    // ==================== OVERRIDE: PROCESAMIENTO TERMINAL ====================
    // WithPagosDesglose::procesarVentaConDesglose crea Venta + VentaPago vía
    // VentaService. Acá lo reemplazamos para crear PedidoDelivery +
    // PedidoDeliveryPago via PedidoDeliveryService. Cada pago del desglose
    // hereda su flag `planificado` para que se persista con estado=planificado
    // (sin tocar caja) o estado=activo (con MovimientoCaja).

    /**
     * Override del método terminal de WithPagosDesglose. Persiste el pedido
     * y agrega los pagos del desglose. Honra el flag `planificado` por pago.
     */
    protected function procesarVentaConDesglose(): void
    {
        // Cobro rapido: el pedido ya existe y no debe modificarse — solo
        // agregamos pagos ACTIVOS por el saldo. Branch dedicado para no
        // disparar actualizarPedido() ni revalidar items/cliente.
        if ($this->modoCobroRapido) {
            $this->procesarCobroRapido();

            return;
        }

        try {
            if (empty($this->items)) {
                $this->dispatch('toast-error', message: __('El pedido debe tener al menos un artículo'));

                return;
            }

            // Pedido totalmente invitado: persistimos sin pagos. total_final=0
            // hace que el service marque estado_pago=pagado.
            $totalFinalActual = (float) ($this->resultado['total_final'] ?? 0);
            $esInvitacionCompleta = $this->esInvitacionTotal && $totalFinalActual <= 0.005;

            if (! $esInvitacionCompleta && empty($this->desglosePagos)) {
                $this->dispatch('toast-error', message: __('No hay pagos en el desglose'));

                return;
            }

            // Validaciones que sí o sí deben estar al persistir (mismas que
            // confirmarPedido pero a prueba de cualquier ruta de entrada).
            if ($this->tipo === PedidoDelivery::TIPO_TAKE_AWAY
                && $this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
                $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

                return;
            }
            // Cliente NO es obligatorio: sin cliente ni datos temporales se graba
            // "Consumidor final" (ver construirDataPedido). El teléfono es opcional.

            // Validar caja abierta si algún pago la requiere (no CC).
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            $requiereCaja = ! $esInvitacionCompleta && collect($this->desglosePagos)->contains(function ($pago) {
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }

                // Pagos planificados no requieren caja abierta (no impactan ahora).
                return ! $esCC && ! (bool) ($pago['planificado'] ?? false);
            });

            if ($requiereCaja) {
                if (! $cajaId) {
                    $this->dispatch('toast-error', message: __('Debe seleccionar una caja'));

                    return;
                }
                $caja = \App\Models\Caja::find($cajaId);
                if (! $caja || ! $caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: __('La caja debe estar abierta'));

                    return;
                }
            }

            // Recalcular para asegurar que el resultado refleja el carrito actual.
            $this->calcularVenta();

            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            // Crear/actualizar pedido en estado CONFIRMADO.
            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoDelivery::find($this->pedidoId);
                if (! $pedido) {
                    throw new Exception(__('Pedido no encontrado'));
                }
                $this->pedidoService->actualizarPedido($pedido, $data, $detalles);
            } else {
                $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: false);
            }

            // Agregar cada pago del desglose, honrando el flag planificado.
            // En edición con pagos preexistentes NO duplicamos: esos pagos se
            // gestionan desde la lista (acción "Cobrar pendiente").
            // Si es invitación total: no hay pagos para agregar.
            if (! $esInvitacionCompleta && ! ($this->modoEdicion && $this->cuentaPagosOriginales > 0)) {
                foreach ($this->desglosePagos as $pago) {
                    $this->pedidoService->agregarPago($pedido, $this->normalizarPagoDelDesglose($pago));
                }
            }

            // Asociar el cobro QR confirmado (Fase 5) al pedido recién persistido.
            // No-op si el pedido no se cobró por integración.
            $this->asociarCobroIntegracionAlCobrable($pedido);
            $this->resetCobroIntegracion();

            $msg = $this->modoEdicion
                ? __('Pedido actualizado')
                : __('Pedido confirmado #:numero', ['numero' => $pedido->numero]);
            $this->dispatch('toast-success', message: $msg);

            $this->mostrarModalPago = false;
            $this->dispatch('pedido-guardado');
        } catch (Exception $e) {
            Log::error('Error al procesar pedido con desglose', [
                'pedido_id' => $this->pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Procesa el cobro rapido: solo agrega pagos ACTIVOS al pedido existente
     * por el saldo pendiente. NO modifica items, descuentos ni totales del
     * pedido (esos ya estan persistidos). Cada fila del desglose lleva su
     * propia forma_pago_id — si hay una sola fila que cubre el total, el
     * pago queda con esa FP individual (no con la FP "mixta" del selector).
     */
    protected function procesarCobroRapido(): void
    {
        try {
            if (! $this->pedidoId) {
                $this->dispatch('toast-error', message: __('Pedido no encontrado'));

                return;
            }

            // Invitación total de un pedido existente: no hay nada que cobrar, así
            // que en vez de agregar pagos aplicamos la cortesía al pedido vía
            // actualizarPedido (total_final=0 + recalcularEstadoPago → pagado).
            // Sin esto, el desglose queda vacío y abortaba con "No hay pagos en el
            // desglose".
            $totalFinalActual = (float) ($this->resultado['total_final'] ?? 0);
            $esInvitacionCompleta = $this->esInvitacionTotal && $totalFinalActual <= 0.005;

            if ($esInvitacionCompleta) {
                $pedido = PedidoDelivery::find($this->pedidoId);
                if (! $pedido) {
                    $this->dispatch('toast-error', message: __('Pedido no encontrado'));

                    return;
                }

                // En cobro rápido, iniciarCobroRapido() dejó
                // ajusteFormaPagoInfo.total_con_ajuste = saldo del pedido; sin
                // limpiarlo, construirDataPedido() lo preferiría sobre el total
                // invitado (0) y persistiría total_final con el saldo. La
                // invitación total no cobra nada → reseteamos el estado de cobro.
                $this->desglosePagos = [];
                $this->ajusteFormaPagoInfo['monto'] = 0;
                $this->ajusteFormaPagoInfo['total_con_ajuste'] = 0;

                $this->calcularVenta();
                $this->pedidoService->actualizarPedido(
                    $pedido,
                    $this->construirDataPedido(),
                    $this->construirDetallesPedido(),
                );

                $this->asociarCobroIntegracionAlCobrable($pedido);
                $this->resetCobroIntegracion();

                $this->dispatch('toast-success', message: __('Pedido cobrado'));
                $this->mostrarModalPago = false;
                $this->dispatch('cobro-rapido-completado');

                return;
            }

            if (empty($this->desglosePagos)) {
                $this->dispatch('toast-error', message: __('No hay pagos en el desglose'));

                return;
            }

            // Validar caja abierta para pagos que la afecten (no CC).
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            $requiereCaja = collect($this->desglosePagos)->contains(function ($pago) {
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }

                return ! $esCC;
            });

            if ($requiereCaja) {
                if (! $cajaId) {
                    $this->dispatch('toast-error', message: __('Debe seleccionar una caja'));

                    return;
                }
                $caja = \App\Models\Caja::find($cajaId);
                if (! $caja || ! $caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: __('La caja debe estar abierta'));

                    return;
                }
            }

            $pedido = PedidoDelivery::find($this->pedidoId);
            if (! $pedido) {
                $this->dispatch('toast-error', message: __('Pedido no encontrado'));

                return;
            }

            // Persistir cada pago del desglose como ACTIVO (planificadoForzado=false).
            // El service maneja la transaccion, MovimientoCaja y el recalculo
            // de estado_pago del pedido.
            foreach ($this->desglosePagos as $pago) {
                $this->pedidoService->agregarPago(
                    $pedido,
                    $this->normalizarPagoDelDesglose($pago, planificadoForzado: false),
                );
            }

            // Asociar el cobro QR confirmado (Fase 5) al pedido cobrado.
            $this->asociarCobroIntegracionAlCobrable($pedido);
            $this->resetCobroIntegracion();

            $this->dispatch('toast-success', message: __('Pedido cobrado'));
            $this->mostrarModalPago = false;
            $this->dispatch('cobro-rapido-completado');
        } catch (Exception $e) {
            Log::error('Error al procesar cobro rapido', [
                'pedido_id' => $this->pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Override de WithPagosDesglose::cerrarModalPago. En cobro rapido, cerrar
     * el modal cierra el componente entero (el padre escucha el evento y
     * limpia $pedidoCobroRapidoId). Para el flujo de alta/edicion, replica
     * la logica estandar del trait (PHP no permite invocar "parent::" cuando
     * el metodo viene de un trait).
     */
    public function cerrarModalPago(): void
    {
        if ($this->modoCobroRapido) {
            $this->mostrarModalPago = false;
            $this->dispatch('cerrar-cobro-rapido');

            return;
        }

        if ($this->desgloseCompleto() && ! empty($this->desglosePagos)) {
            $totalBase = $this->resultado['total_final'] ?? 0;
            $totalConAjustes = $this->totalConAjustes;
            $montoAjuste = $totalConAjustes - $totalBase;

            $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
            $this->ajusteFormaPagoInfo['total_con_ajuste'] = $totalConAjustes;
        }

        $this->mostrarModalPago = false;
        if (! $this->desgloseCompleto()) {
            $this->desglosePagos = [];
            $this->montoPendienteDesglose = 0;
            $this->totalConAjustes = 0;
            $this->limpiarDesgloseIvaMixto();
        }
        $this->resetNuevoPago();
    }
}
