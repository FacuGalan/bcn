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
use App\Livewire\Concerns\Carrito\WithPagosDesglose;
use App\Livewire\Concerns\Carrito\WithPuntos;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
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

    public bool $showPuntoVentaModal = false;

    public ?int $puntoVentaSeleccionadoId = null;

    public array $puntosVentaDisponibles = [];

    public bool $puedeSeleccionarPuntoVenta = false;

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
        $this->cargarFormasPagoSucursal();
        $this->cargarCatalogoTactil();
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

    /**
     * Carga el snapshot del catálogo táctil de la sucursal: categorías activas
     * con sus artículos básicos. Pensado para que Alpine renderice la grilla
     * sin viajes a Livewire al cambiar de categoría (cambio 100% local).
     *
     * Estructura: [{ id, nombre, color, articulos: [{ id, nombre, precio, codigo, es_pesable }] }]
     */
    protected function cargarCatalogoTactil(): void
    {
        if (! $this->sucursalId) {
            $this->catalogoTactil = [];

            return;
        }

        $categorias = \App\Models\Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color']);

        // Precio base del artículo como referencia visual. Al hacer click,
        // seleccionarArticulo aplica precios según la lista del pedido.
        $articulos = \App\Models\Articulo::query()
            ->where('activo', true)
            ->whereNotNull('categoria_id')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'categoria_id', 'precio_base', 'pesable']);

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
                'articulos' => $arts->map(fn ($a) => [
                    'id' => (int) $a->id,
                    'nombre' => $a->nombre,
                    'codigo' => $a->codigo,
                    'precio' => (float) ($a->precio_base ?? 0),
                    'es_pesable' => (bool) $a->pesable,
                ])->values()->toArray(),
            ];
        })->filter()->values()->toArray();
    }

    public function togglePanelTactil(): void
    {
        $this->panelTactilAbierto = ! $this->panelTactilAbierto;
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
        $this->cargarFormasPagoSucursal();
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

    // ==================== STUBS FISCALES ====================
    // Estos métodos los llama WithPagosDesglose. En NuevaVenta cargan
    // configuración fiscal y disparan eventos de impresión. En el pedido
    // por mostrador no aplican porque NO emite comprobante fiscal en este
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
            'pagos.formaPago:id,nombre,codigo,es_mixta,permite_cuotas',
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

        // No permitir editar pedidos que ya tienen cobros materializados
        // (estado_pago = parcial/pagado). Esos se gestionan desde la lista
        // ("Cobrar pendiente") para no romper la trazabilidad de caja.
        // Borradores siempre se pueden continuar.
        if ($pedido->estado_pedido !== PedidoMostrador::ESTADO_BORRADOR
            && $pedido->estado_pago !== PedidoMostrador::ESTADO_PAGO_PENDIENTE) {
            $this->dispatch('toast-error', message: __('No se puede editar un pedido con cobros registrados. Gestioná los pagos desde la lista.'));
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
    protected function hidratarDesglosePagosGuardados(PedidoMostrador $pedido): void
    {
        $pagos = $pedido->pagos->filter(fn ($p) => in_array($p->estado, ['activo', 'planificado'], true));
        $this->cuentaPagosOriginales = $pagos->count();
        if ($this->cuentaPagosOriginales === 0) {
            return;
        }

        $this->desglosePagos = $pagos->map(fn ($p) => [
            'forma_pago_id' => $p->forma_pago_id,
            'nombre' => $p->formaPago?->nombre ?? '',
            'codigo' => $p->formaPago?->codigo ?? '',
            'monto_base' => (float) $p->monto_base,
            'ajuste_porcentaje' => (float) $p->ajuste_porcentaje,
            'monto_ajuste' => (float) $p->monto_ajuste,
            'monto_final' => (float) $p->monto_final,
            'cuotas' => $p->cuotas ? (int) $p->cuotas : 1,
            'recargo_cuotas_porcentaje' => $p->recargo_cuotas_porcentaje !== null ? (float) $p->recargo_cuotas_porcentaje : 0,
            'recargo_cuotas_monto' => $p->recargo_cuotas_monto !== null ? (float) $p->recargo_cuotas_monto : 0,
            'monto_cuota' => $p->monto_cuota !== null ? (float) $p->monto_cuota : null,
            'monto_recibido' => $p->monto_recibido !== null ? (float) $p->monto_recibido : (float) $p->monto_final,
            'vuelto' => (float) $p->vuelto,
            'referencia' => $p->referencia,
            'observaciones' => $p->observaciones,
            'es_cuenta_corriente' => (bool) $p->es_cuenta_corriente,
            'es_pago_puntos' => (bool) $p->es_pago_puntos,
            'puntos_usados' => (int) $p->puntos_usados,
            'afecta_caja' => (bool) $p->afecta_caja,
            'factura_fiscal' => false,
            'planificado' => $p->estado === 'planificado',
            'estado_persistido' => $p->estado,
        ])->values()->toArray();

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
        }
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
        if ($this->sucursalUsaBeepers && empty(trim($this->numeroBeeper ?? ''))) {
            $this->dispatch('toast-error', message: __('El número de beeper es obligatorio'));

            return false;
        }
        if (! $this->clienteSeleccionado) {
            $nombreTemp = trim($this->nombreClienteTemporal ?? '');
            $telTemp = trim($this->telefonoClienteTemporal ?? '');
            if ($nombreTemp === '' || $telTemp === '') {
                $this->dispatch('toast-error', message: __('Seleccioná un cliente o ingresá nombre y teléfono temporales'));

                return false;
            }
        }
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

        // Si hay desglose cargado, debe estar COMPLETO (cubrir el total). Un
        // desglose parcial guardado como planificado da información incorrecta
        // (el pedido quedaría con total = monto del desglose). El usuario debe
        // completar el desglose o quitar todos los pagos si solo quiere
        // confirmar sin cobrar.
        if (! empty($this->desglosePagos) && ! $this->desgloseCompleto()) {
            $this->dispatch('toast-error', message: __('El desglose de pagos es parcial. Completá el desglose hasta cubrir el total o quitá los pagos para confirmar sin cobrar.'));

            return;
        }

        try {
            $data = $this->construirDataPedido();
            $detalles = $this->construirDetallesPedido();

            if ($this->modoEdicion && $this->pedidoId) {
                $pedido = PedidoMostrador::find($this->pedidoId);
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
            if ($pedido->estado_pedido === PedidoMostrador::ESTADO_BORRADOR) {
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
     * PedidoMostradorService::agregarPago. Si `$planificadoForzado` viene en
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
            'total' => $totalBase,
            'ajuste_forma_pago' => $ajusteForma,
            'total_final' => $totalFinal,
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

            $precioUnitario = (float) $item['precio'];
            $ivaPorc = (float) ($item['iva_porcentaje'] ?? 0);
            $precioIvaIncluido = (bool) ($item['precio_iva_incluido'] ?? true);
            $precioSinIva = ($precioIvaIncluido && $ivaPorc > 0)
                ? round($precioUnitario / (1 + $ivaPorc / 100), 2)
                : $precioUnitario;

            $detalles[] = [
                'articulo_id' => $item['articulo_id'] ?? null,
                'es_concepto' => (bool) ($item['es_concepto'] ?? false),
                'concepto_descripcion' => $item['concepto_descripcion'] ?? null,
                'concepto_categoria_id' => $item['concepto_categoria_id'] ?? $item['categoria_id'] ?? null,
                'tipo_iva_id' => null,
                'lista_precio_id' => $this->listaPrecioId,
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
        $ajustePorcentaje = $fp['ajuste_porcentaje'] ?? 0;
        $montoAjuste = round($totalBase * ($ajustePorcentaje / 100), 2) + 0;
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
                $recargoCuotasMonto = round($totalConAjuste * ($recargoCuotasPorcentaje / 100), 2);
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

    // ==================== OVERRIDE: PROCESAMIENTO TERMINAL ====================
    // WithPagosDesglose::procesarVentaConDesglose crea Venta + VentaPago vía
    // VentaService. Acá lo reemplazamos para crear PedidoMostrador +
    // PedidoMostradorPago via PedidoMostradorService. Cada pago del desglose
    // hereda su flag `planificado` para que se persista con estado=planificado
    // (sin tocar caja) o estado=activo (con MovimientoCaja).

    /**
     * Override del método terminal de WithPagosDesglose. Persiste el pedido
     * y agrega los pagos del desglose. Honra el flag `planificado` por pago.
     */
    protected function procesarVentaConDesglose(): void
    {
        try {
            if (empty($this->items) || empty($this->desglosePagos)) {
                $this->dispatch('toast-error', message: __('No hay pagos en el desglose'));

                return;
            }

            // Validaciones que sí o sí deben estar al persistir (mismas que
            // confirmarPedido pero a prueba de cualquier ruta de entrada).
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

            // Validar caja abierta si algún pago la requiere (no CC).
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            $requiereCaja = collect($this->desglosePagos)->contains(function ($pago) {
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
                $pedido = PedidoMostrador::find($this->pedidoId);
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
            if (! ($this->modoEdicion && $this->cuentaPagosOriginales > 0)) {
                foreach ($this->desglosePagos as $pago) {
                    $this->pedidoService->agregarPago($pedido, $this->normalizarPagoDelDesglose($pago));
                }
            }

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
}
