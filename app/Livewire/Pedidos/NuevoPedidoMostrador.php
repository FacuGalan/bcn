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
use App\Livewire\Concerns\Carrito\WithOpcionales;
use App\Livewire\Concerns\Carrito\WithPuntos;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\FormaPago;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\ListaPrecio;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorDetalle;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\Pedidos\PedidoMostradorService;
use App\Services\PuntosService;
use App\Traits\CajaAware;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta/edición de Pedido por Mostrador.
 *
 * Es un sub-componente Livewire que se invoca desde `PedidosMostrador` (la
 * Lista) como modal FULL-SCREEN — no tiene ruta dedicada. El padre controla
 * cuándo renderizarlo via `<livewire:nuevo-pedido-mostrador :pedidoId="..." />`
 * y escucha los eventos `cerrar-modal-pedido` y `pedido-guardado` para
 * cerrar/refrescar.
 *
 * Compone los 10 traits del Carrito EXCEPTO `WithPagosDesglose`: la captura
 * de pagos vive en otro modal en PR2.C.2.B. Acá se construye el carrito
 * completo (items + descuentos + cupones + opcionales + puntos + ajustes)
 * y se persiste vía `PedidoMostradorService::crearPedido` / `actualizarPedido`.
 *
 * Reusa la UI 1:1 con NuevaVenta mediante los parciales en
 * `resources/views/livewire/carrito/`.
 *
 * Modos:
 *   - **Alta** (`$pedidoId === null`): carrito vacío, botones "Guardar borrador"
 *     y "Confirmar pedido". El segundo asigna número, descuenta stock y
 *     dispara `PedidoCreado`.
 *   - **Edición** (`$pedidoId` provisto): hidrata items/cliente/etc desde el
 *     pedido. Solo editable si estado en {borrador, confirmado}. El guardado
 *     dispara `actualizarPedido` (revierte/redescuenta stock si era confirmado).
 *
 * Validación de beeper: si `sucursal.usa_beepers = true` y no se ingresó
 * `numero_beeper`, bloquea al confirmar (no al guardar borrador).
 */
class NuevoPedidoMostrador extends Component
{
    use CajaAware;
    use WithArticuloRapido;
    use WithBusquedaArticulos;
    use WithBusquedaClientes;
    use WithCalculoVenta;
    use WithCarritoItems;
    use WithConsultaPrecios;
    use WithCupones;
    use WithDescuentos;
    use WithOpcionales;
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

    public bool $modoEdicion = false;

    /** Estado del pedido en modo edición (para mostrarlo y validar transiciones). */
    public ?string $estadoPedidoActual = null;

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

    // ==================== EDICIÓN DE NOMBRE DE ITEM ====================

    public ?int $editarNombreIndex = null;

    public string $editarNombreValor = '';

    /** Índice del item resaltado tras agregar (efecto highlight temporal). */
    public ?int $itemResaltado = null;

    // ==================== STUBS PARA WithCalculoVenta ====================
    // WithCalculoVenta llama estos métodos al final de calcularVenta() si hay
    // formaPagoId. Como no usamos WithPagosDesglose, son no-op.

    public bool $formaPagoPermiteCuotas = false;

    public ?int $cuotaSeleccionadaId = null;

    public array $infoCuotaSeleccionada = [];

    public array $ajusteFormaPagoInfo = [];

    // ==================== MODAL DE PAGOS (DESGLOSE MIXTO) ====================

    /** Indica si el modal de pago está abierto. */
    public bool $mostrarModalPagoPedido = false;

    /**
     * Desglose de pagos en construcción. Cada item:
     * [
     *   'forma_pago_id' => int,
     *   'forma_pago_nombre' => string,
     *   'monto_base' => float,
     *   'ajuste_porcentaje' => float,
     *   'monto_ajuste' => float,
     *   'monto_final' => float,
     *   'cuotas' => ?int,
     *   'recargo_cuotas_porcentaje' => ?float,
     *   'recargo_cuotas_monto' => ?float,
     *   'monto_cuota' => ?float,
     *   'planificado' => bool,       // true=guardar sin cobrar, false=cobrar ahora
     *   'referencia' => ?string,
     * ]
     */
    public array $desglosePagosPedido = [];

    /** Formulario para agregar un pago al desglose. */
    public array $nuevoPagoPedido = [
        'forma_pago_id' => null,
        'monto_base' => null,
        'cuotas' => 1,
        'recargo_cuotas_porcentaje' => 0,
        'planificado' => false,
        'referencia' => null,
    ];

    /** Formas de pago de la sucursal cacheadas en mount (id, nombre, cuotas, ajuste). */
    public array $formasPagoDisponibles = [];

    // ==================== INYECCIÓN ====================

    protected PedidoMostradorService $pedidoService;

    protected OpcionalService $opcionalService;

    protected CuponService $cuponService;

    protected PuntosService $puntosService;

    public function boot(
        PedidoMostradorService $pedidoService,
        OpcionalService $opcionalService,
        CuponService $cuponService,
        PuntosService $puntosService,
    ): void {
        $this->pedidoService = $pedidoService;
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

    public function mount(?int $pedidoId = null): void
    {
        $this->sucursalId = sucursal_activa() ?? Sucursal::activas()->first()?->id ?? 1;
        $this->cajaSeleccionada = caja_activa();

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->cargarFormasPagoDisponibles();
        $this->listaPrecioId = $this->obtenerIdListaBase();

        $local = collect($this->formasVenta)->firstWhere('codigo', 'local');
        $this->formaVentaId = $local['id'] ?? $this->formasVenta[0]['id'] ?? null;

        $pos = collect($this->canalesVenta)->firstWhere('codigo', 'pos');
        $this->canalVentaId = $pos['id'] ?? $this->canalesVenta[0]['id'] ?? null;

        $this->cargarTopeDescuentoUsuario();

        if ($pedidoId !== null) {
            $this->cargarPedidoParaEditar($pedidoId);
        }
    }

    public function render()
    {
        return view('livewire.pedidos.nuevo-pedido-mostrador', [
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

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->cargarFormasPagoDisponibles();
        $this->listaPrecioId = $this->obtenerIdListaBase();
    }

    #[On('caja-changed')]
    public function handleCajaChanged($cajaId = null, $cajaNombre = null): void
    {
        $this->cajaSeleccionada = $cajaId;
    }

    // ==================== CARGA DE CONTEXTO ====================

    protected function cargarConfiguracionSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->sucursalUsaBeepers = false;
            $this->controlStock = 'permitir';

            return;
        }
        $sucursal = Sucursal::find($this->sucursalId);
        $this->sucursalUsaBeepers = (bool) ($sucursal->usa_beepers ?? false);
        $this->controlStock = $sucursal->control_stock_venta ?? 'permitir';
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

    // ==================== STUBS PARA WithCalculoVenta ====================

    protected function cargarCuotasFormaPago(): void
    {
        // No-op en alta de pedido (sin desglose de pagos en este PR).
    }

    protected function calcularAjusteFormaPago(): void
    {
        // No-op en alta de pedido.
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
        $pedido = PedidoMostrador::with([
            'cliente:id,nombre,telefono,lista_precio_id',
            'detalles.articulo:id,nombre,codigo,categoria_id,precio_iva_incluido,puntos_canje,tipo_iva_id',
            'detalles.opcionales',
        ])->find($pedidoId);

        if (! $pedido) {
            $this->dispatch('toast-error', message: __('Pedido no encontrado'));
            $this->dispatch('cerrar-modal-pedido');

            return;
        }

        if (! in_array($pedido->estado_pedido, [PedidoMostrador::ESTADO_BORRADOR, PedidoMostrador::ESTADO_CONFIRMADO], true)) {
            $this->dispatch('toast-error', message: __("El pedido en estado ':estado' no se puede editar", ['estado' => $pedido->estado_pedido]));
            $this->dispatch('cerrar-modal-pedido');

            return;
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

        $this->items = $pedido->detalles
            ->filter(fn ($d) => ! $d->es_concepto || $d->concepto_descripcion)
            ->map(fn ($d) => $this->detalleAItemCarrito($d))
            ->values()
            ->toArray();

        $this->cargarConfiguracionSucursal();
        $this->cargarListasPrecios();
        $this->calcularVenta();
    }

    protected function detalleAItemCarrito(PedidoMostradorDetalle $detalle): array
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
        if (empty($this->items)) {
            $this->dispatch('toast-error', message: __('El pedido debe tener al menos un artículo'));

            return;
        }

        // Validaciones que se hacen antes de mostrar el modal de pago.
        if ($this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
            $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

            return;
        }

        if (! $this->clienteSeleccionado) {
            $nombreTemp = trim($this->nombreClienteTemporal ?? '');
            $telTemp = trim($this->telefonoClienteTemporal ?? '');
            if ($nombreTemp === '' || $telTemp === '') {
                $this->dispatch('toast-error', message: __('Seleccioná un cliente o ingresá nombre y teléfono temporales'));

                return;
            }
        }

        $this->calcularVenta();
        if (! $this->resultado) {
            $this->dispatch('toast-error', message: __('No se pudo calcular el pedido'));

            return;
        }

        $this->abrirModalPagoPedido();
    }

    protected function guardar(bool $esBorrador): void
    {
        try {
            if (empty($this->items)) {
                $this->dispatch('toast-error', message: __('El pedido debe tener al menos un artículo'));

                return;
            }

            if (! $esBorrador && $this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
                $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

                return;
            }

            if (! $esBorrador && ! $this->clienteSeleccionado) {
                $nombreTemp = trim($this->nombreClienteTemporal ?? '');
                $telTemp = trim($this->telefonoClienteTemporal ?? '');
                if ($nombreTemp === '' || $telTemp === '') {
                    $this->dispatch('toast-error', message: __('Seleccioná un cliente o ingresá nombre y teléfono temporales'));

                    return;
                }
            }

            $this->calcularVenta();
            if (! $this->resultado) {
                $this->dispatch('toast-error', message: __('No se pudo calcular el pedido'));

                return;
            }

            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoMostrador::find($this->pedidoId);
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
            Log::error('Error al guardar pedido por mostrador', [
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

        return [
            'sucursal_id' => $this->sucursalId,
            'cliente_id' => $this->clienteSeleccionado,
            'nombre_cliente_temporal' => $this->clienteSeleccionado ? null : (trim($this->nombreClienteTemporal ?? '') ?: null),
            'telefono_cliente_temporal' => $this->clienteSeleccionado ? null : (trim($this->telefonoClienteTemporal ?? '') ?: null),
            'caja_id' => $this->cajaSeleccionada,
            'canal_venta_id' => $this->canalVentaId,
            'forma_venta_id' => $this->formaVentaId,
            'lista_precio_id' => $this->listaPrecioId,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'identificador' => trim($this->identificador ?? '') ?: null,
            'numero_beeper' => trim($this->numeroBeeper ?? '') ?: null,
            'subtotal' => (float) ($r['subtotal'] ?? 0),
            'iva' => (float) ($r['iva_total'] ?? 0),
            'descuento' => (float) ($r['descuento_total'] ?? 0),
            'total' => (float) ($r['total'] ?? 0),
            'ajuste_forma_pago' => 0,
            'total_final' => (float) ($r['total_final'] ?? $r['total'] ?? 0),
            'descuento_general_tipo' => $this->descuentoGeneralActivo ? $this->descuentoGeneralTipo : null,
            'descuento_general_valor' => $this->descuentoGeneralActivo ? $this->descuentoGeneralValor : null,
            'descuento_general_monto' => $this->descuentoGeneralActivo ? $this->descuentoGeneralMonto : 0,
            'descuento_general_aplicado_por' => $this->descuentoGeneralAplicadoPor,
            'cupon_id' => $this->cuponAplicado['id'] ?? null,
            'cupon_codigo_snapshot' => $this->cuponAplicado['codigo'] ?? null,
            'cupon_descripcion_snapshot' => $this->cuponAplicado['descripcion'] ?? null,
            'monto_cupon' => (float) ($this->cuponMontoDescuento ?? 0),
            'puntos_ganados' => 0,
            'puntos_usados' => (int) ($this->canjePuntosActivo ? $this->canjePuntosUnidades : 0),
            'observaciones' => trim($this->observaciones ?? '') ?: null,
        ];
    }

    protected function construirDetallesPedido(): array
    {
        $detalles = [];
        foreach ($this->items as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $detalles[] = [
                'articulo_id' => $item['articulo_id'] ?? null,
                'es_concepto' => (bool) ($item['es_concepto'] ?? false),
                'concepto_descripcion' => $item['concepto_descripcion'] ?? null,
                'concepto_categoria_id' => $item['concepto_categoria_id'] ?? $item['categoria_id'] ?? null,
                'tipo_iva_id' => null,
                'lista_precio_id' => $this->listaPrecioId,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $item['precio'],
                'precio_sin_iva' => null,
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
                'descuento_promocion' => 0,
                'descuento_promocion_especial' => 0,
                'descuento_cupon' => (float) ($item['descuento_cupon'] ?? 0),
                'descuento_lista' => 0,
                'tiene_promocion' => (bool) ($item['tiene_promocion'] ?? false),
                'total' => (float) $item['precio'] * $cantidad,
                'opcionales' => $item['opcionales'] ?? [],
            ];
        }

        return $detalles;
    }

    // ==================== FORMAS DE PAGO DISPONIBLES ====================

    /**
     * Carga las formas de pago activas en la sucursal con sus cuotas y ajuste.
     * Pensado para el desglose de pagos del pedido — sin lógica fiscal.
     */
    protected function cargarFormasPagoDisponibles(): void
    {
        if (! $this->sucursalId) {
            $this->formasPagoDisponibles = [];

            return;
        }

        $formasPago = FormaPago::with(['cuotas', 'conceptoPago'])
            ->where('activo', true)
            ->where('es_mixta', false)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $this->formasPagoDisponibles = $formasPago->map(function ($fp) {
            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $fp->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            // Excluir las que están inactivas en la sucursal.
            if ($configSucursal && ! $configSucursal->activo) {
                return null;
            }

            // Excluir las marcadas "solo sistema" (canje puntos interno, etc.).
            if ($fp->solo_sistema) {
                return null;
            }

            $ajustePorcentaje = $configSucursal && $configSucursal->ajuste_porcentaje !== null
                ? (float) $configSucursal->ajuste_porcentaje
                : (float) ($fp->ajuste_porcentaje ?? 0);

            $cuotasDisponibles = [];
            if ($fp->permite_cuotas) {
                foreach ($fp->cuotas as $cuota) {
                    $cuotaSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                        ->where('sucursal_id', $this->sucursalId)
                        ->first();
                    $activa = $cuotaSucursal ? $cuotaSucursal->activo : true;
                    if (! $activa) {
                        continue;
                    }
                    $recargo = $cuotaSucursal && $cuotaSucursal->recargo_porcentaje !== null
                        ? (float) $cuotaSucursal->recargo_porcentaje
                        : (float) $cuota->recargo_porcentaje;
                    $cuotasDisponibles[] = [
                        'id' => (int) $cuota->id,
                        'cantidad' => (int) $cuota->cantidad_cuotas,
                        'recargo' => $recargo,
                        'descripcion' => $cuota->descripcion,
                    ];
                }
            }

            return [
                'id' => (int) $fp->id,
                'nombre' => $fp->nombre,
                'codigo' => $fp->codigo,
                'ajuste_porcentaje' => $ajustePorcentaje,
                'permite_cuotas' => (bool) $fp->permite_cuotas,
                'afecta_caja' => (bool) ($fp->afecta_caja ?? true),
                'es_cuenta_corriente' => strtoupper($fp->codigo ?? '') === 'CTA_CTE',
                'cuotas' => $cuotasDisponibles,
            ];
        })->filter()->values()->toArray();
    }

    // ==================== DESGLOSE DE PAGOS ====================

    public function abrirModalPagoPedido(): void
    {
        $this->desglosePagosPedido = [];
        $this->resetNuevoPagoPedido();

        // Si el total es 0 (pedido bonificado), permitir confirmar sin pagos.
        $totalFinal = (float) ($this->resultado['total_final'] ?? $this->resultado['total'] ?? 0);
        if ($totalFinal <= 0.005) {
            $this->mostrarModalPagoPedido = true;

            return;
        }

        // Prellenar monto del primer pago con el pendiente total
        $this->nuevoPagoPedido['monto_base'] = round($totalFinal, 2);
        $this->mostrarModalPagoPedido = true;
    }

    public function cerrarModalPagoPedido(): void
    {
        $this->mostrarModalPagoPedido = false;
        $this->desglosePagosPedido = [];
        $this->resetNuevoPagoPedido();
    }

    protected function resetNuevoPagoPedido(): void
    {
        $this->nuevoPagoPedido = [
            'forma_pago_id' => null,
            'monto_base' => null,
            'cuotas' => 1,
            'recargo_cuotas_porcentaje' => 0,
            'planificado' => false,
            'referencia' => null,
        ];
    }

    public function updatedNuevoPagoPedidoFormaPagoId($value): void
    {
        $this->nuevoPagoPedido['cuotas'] = 1;
        $this->nuevoPagoPedido['recargo_cuotas_porcentaje'] = 0;

        // Prefill monto con el pendiente actual si está vacío
        $pendiente = $this->montoPendientePagoPedido();
        if ($pendiente > 0.005 && empty($this->nuevoPagoPedido['monto_base'])) {
            $this->nuevoPagoPedido['monto_base'] = round($pendiente, 2);
        }
    }

    /**
     * Selecciona un plan de cuotas para el form (id + recargo).
     */
    public function seleccionarCuotasPagoPedido(int $cuotas, float $recargo = 0): void
    {
        $this->nuevoPagoPedido['cuotas'] = max(1, $cuotas);
        $this->nuevoPagoPedido['recargo_cuotas_porcentaje'] = $cuotas > 1 ? $recargo : 0;
    }

    public function togglePlanificadoNuevoPago(): void
    {
        $this->nuevoPagoPedido['planificado'] = ! (bool) ($this->nuevoPagoPedido['planificado'] ?? false);
    }

    public function togglePlanificadoEnDesglose(int $index): void
    {
        if (! isset($this->desglosePagosPedido[$index])) {
            return;
        }
        $this->desglosePagosPedido[$index]['planificado'] = ! (bool) ($this->desglosePagosPedido[$index]['planificado'] ?? false);
    }

    /**
     * Monto pendiente en el desglose actual del pedido (total_final − sum desglose).
     */
    public function montoPendientePagoPedido(): float
    {
        $total = (float) ($this->resultado['total_final'] ?? $this->resultado['total'] ?? 0);
        $cubierto = array_sum(array_map(fn ($p) => (float) ($p['monto_final'] ?? 0), $this->desglosePagosPedido));

        return round($total - $cubierto, 2);
    }

    public function agregarPagoAlDesglosePedido(): void
    {
        $this->validate([
            'nuevoPagoPedido.forma_pago_id' => 'required|integer',
            'nuevoPagoPedido.monto_base' => 'required|numeric|min:0.01',
        ], [], [
            'nuevoPagoPedido.forma_pago_id' => __('Forma de pago'),
            'nuevoPagoPedido.monto_base' => __('Monto'),
        ]);

        $fp = collect($this->formasPagoDisponibles)->firstWhere('id', (int) $this->nuevoPagoPedido['forma_pago_id']);
        if (! $fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));

            return;
        }

        $montoBase = (float) $this->nuevoPagoPedido['monto_base'];
        $cuotas = max(1, (int) ($this->nuevoPagoPedido['cuotas'] ?? 1));
        $recargoCuotasPct = (float) ($this->nuevoPagoPedido['recargo_cuotas_porcentaje'] ?? 0);
        $ajustePct = (float) ($fp['ajuste_porcentaje'] ?? 0);

        // Ajuste por forma de pago (puede ser % positivo recargo o negativo descuento).
        $montoAjuste = round($montoBase * $ajustePct / 100, 2);
        $montoConAjuste = round($montoBase + $montoAjuste, 2);

        // Recargo por cuotas (si aplica).
        $recargoCuotasMonto = 0;
        $montoCuota = null;
        if ($cuotas > 1 && $recargoCuotasPct > 0) {
            $recargoCuotasMonto = round($montoConAjuste * $recargoCuotasPct / 100, 2);
        }
        $montoFinal = round($montoConAjuste + $recargoCuotasMonto, 2);
        if ($cuotas > 1) {
            $montoCuota = round($montoFinal / $cuotas, 2);
        }

        // Validar que no excedamos el pendiente (con tolerancia).
        $pendiente = $this->montoPendientePagoPedido();
        if ($montoFinal > $pendiente + 0.01) {
            $this->dispatch('toast-error', message: __('El monto excede el pendiente'));

            return;
        }

        $this->desglosePagosPedido[] = [
            'forma_pago_id' => (int) $fp['id'],
            'forma_pago_nombre' => $fp['nombre'],
            'forma_pago_codigo' => $fp['codigo'],
            'afecta_caja' => $fp['afecta_caja'],
            'es_cuenta_corriente' => $fp['es_cuenta_corriente'],
            'monto_base' => $montoBase,
            'ajuste_porcentaje' => $ajustePct,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cuotas > 1 ? $cuotas : null,
            'recargo_cuotas_porcentaje' => $cuotas > 1 ? $recargoCuotasPct : null,
            'recargo_cuotas_monto' => $cuotas > 1 ? $recargoCuotasMonto : null,
            'monto_cuota' => $montoCuota,
            'planificado' => (bool) ($this->nuevoPagoPedido['planificado'] ?? false),
            'referencia' => trim($this->nuevoPagoPedido['referencia'] ?? '') ?: null,
        ];

        $this->resetNuevoPagoPedido();

        // Prefill monto con el nuevo pendiente
        $nuevoPendiente = $this->montoPendientePagoPedido();
        if ($nuevoPendiente > 0.005) {
            $this->nuevoPagoPedido['monto_base'] = round($nuevoPendiente, 2);
        }
    }

    public function eliminarPagoDelDesglose(int $index): void
    {
        if (! isset($this->desglosePagosPedido[$index])) {
            return;
        }
        unset($this->desglosePagosPedido[$index]);
        $this->desglosePagosPedido = array_values($this->desglosePagosPedido);

        // Reprefill monto con el pendiente
        $pendiente = $this->montoPendientePagoPedido();
        if ($pendiente > 0.005 && empty($this->nuevoPagoPedido['monto_base'])) {
            $this->nuevoPagoPedido['monto_base'] = round($pendiente, 2);
        }
    }

    /**
     * Confirma el desglose: persiste el pedido y agrega los pagos. Despacha
     * pedido-guardado al padre. Si algo falla, el pedido se cancela para no
     * dejar pedidos huérfanos.
     */
    public function confirmarPagosPedido(): void
    {
        $totalFinal = (float) ($this->resultado['total_final'] ?? $this->resultado['total'] ?? 0);
        $cubierto = array_sum(array_map(fn ($p) => (float) ($p['monto_final'] ?? 0), $this->desglosePagosPedido));

        // Validación: debe cubrir el total (a menos que sea 0).
        if ($totalFinal > 0.005 && abs($cubierto - $totalFinal) > 0.05) {
            $this->dispatch('toast-error', message: __('La suma de pagos debe igualar el total final'));

            return;
        }

        try {
            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            // 1) Crear/actualizar el pedido (confirmado).
            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoMostrador::find($this->pedidoId);
                if (! $pedido) {
                    throw new Exception(__('Pedido no encontrado'));
                }
                $this->pedidoService->actualizarPedido($pedido, $data, $detalles);
            } else {
                $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: false);
            }

            // 2) Agregar los pagos del desglose.
            foreach ($this->desglosePagosPedido as $pago) {
                $this->pedidoService->agregarPago($pedido, [
                    'forma_pago_id' => $pago['forma_pago_id'],
                    'monto_base' => $pago['monto_base'],
                    'ajuste_porcentaje' => $pago['ajuste_porcentaje'] ?? 0,
                    'monto_ajuste' => $pago['monto_ajuste'] ?? 0,
                    'monto_final' => $pago['monto_final'],
                    'cuotas' => $pago['cuotas'] ?? null,
                    'recargo_cuotas_porcentaje' => $pago['recargo_cuotas_porcentaje'] ?? null,
                    'recargo_cuotas_monto' => $pago['recargo_cuotas_monto'] ?? null,
                    'monto_cuota' => $pago['monto_cuota'] ?? null,
                    'referencia' => $pago['referencia'] ?? null,
                    'es_cuenta_corriente' => (bool) ($pago['es_cuenta_corriente'] ?? false),
                    'afecta_caja' => (bool) ($pago['afecta_caja'] ?? true),
                    'planificado' => (bool) ($pago['planificado'] ?? false),
                ]);
            }

            $msg = $this->modoEdicion
                ? __('Pedido actualizado')
                : __('Pedido confirmado #:numero', ['numero' => $pedido->numero]);
            $this->dispatch('toast-success', message: $msg);

            $this->mostrarModalPagoPedido = false;
            $this->dispatch('pedido-guardado');
        } catch (Exception $e) {
            Log::error('Error al confirmar pedido con pagos', [
                'pedido_id' => $this->pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }
}
