<?php

namespace App\Livewire\Ventas;

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
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\CuentaEmpresa;
use App\Models\Cupon;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\ListaPrecio;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\VentaPago;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Services\CajaService;
use App\Services\CatalogoCache;
use App\Services\CuentaEmpresaService;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\PuntosService;
use App\Services\VentaService;
use App\Traits\AperturaTurnoTrait;
use App\Traits\CajaAware;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Componente Livewire: Nueva Venta (POS)
 *
 * Sistema completo de punto de venta con:
 * - Búsqueda de artículos por nombre, código y código de barras
 * - Cálculo de precios según lista de precios
 * - Aplicación de promociones especiales (NxM, Combo, Menú)
 * - Aplicación de promociones comunes (descuentos, etc.)
 * - Selectores de forma de venta, canal de venta, forma de pago y lista de precios
 */
#[Lazy]
class NuevaVenta extends Component
{
    use AperturaTurnoTrait;
    use CajaAware;
    use WithArticuloRapido;
    use WithBusquedaArticulos;
    use WithBusquedaClientes;
    use WithCalculoVenta;
    use WithCarritoItems;
    use WithConsultaPrecios;
    use WithCupones;
    use WithDescuentos;
    use WithInvitaciones;
    use WithOpcionales;
    use WithPagosDesglose;
    use WithPuntos;

    // =========================================
    // PROPIEDADES DEL POS / CARRITO
    // =========================================

    /** @var int|null Índice del item resaltado en el detalle */
    public $itemResaltado = null;

    /** @var bool Modal de concepto visible */
    public $mostrarModalConcepto = false;

    /** @var string Descripción del concepto */
    public $conceptoDescripcion = '';

    /** @var int|null Categoría del concepto */
    public $conceptoCategoriaId = null;

    /** @var float Importe del concepto */
    public $conceptoImporte = 0;

    /** @var array Categorías disponibles para conceptos */
    public $categoriasDisponibles = [];

    /** @var bool Modal de confirmación para limpiar carrito */
    public bool $mostrarConfirmLimpiar = false;

    // =========================================
    // PROPIEDADES DE CONTEXTO DE VENTA
    // =========================================

    /** @var int|null ID de sucursal */
    public $sucursalId = null;

    /** @var int|null ID de forma de venta */
    public $formaVentaId = null;

    /** @var int|null ID de canal de venta */
    public $canalVentaId = null;

    /** @var int|null ID de forma de pago */
    public $formaPagoId = null;

    /** @var int|null ID de lista de precios */
    public $listaPrecioId = null;

    /** @var int|null ID de la caja seleccionada */
    public $cajaSeleccionada = null;

    /** @var array Estado de validación de la caja (operativa, estado, mensaje, caja) */
    public $estadoCaja = [
        'operativa' => false,
        'estado' => 'sin_caja',
        'mensaje' => 'No hay caja seleccionada',
        'caja' => null,
    ];

    /** @var string Tipo de comprobante */
    public $tipoComprobante = 'ticket';

    /** @var string|null Observaciones de la venta */
    public $observaciones = null;

    // =========================================
    // PROPIEDADES DE COLECCIONES
    // =========================================

    /** @var array Listas de precios disponibles */
    public $listasPreciosDisponibles = [];

    /** Formas de venta disponibles (computed: no viaja en payload Livewire) */
    #[Computed]
    public function formasVenta(): array
    {
        return CatalogoCache::formasVenta()->toArray();
    }

    /** Canales de venta disponibles (computed: no viaja en payload Livewire) */
    #[Computed]
    public function canalesVenta(): array
    {
        return CatalogoCache::canalesVenta()->toArray();
    }

    /** Formas de pago disponibles (computed: no viaja en payload Livewire) */
    #[Computed]
    public function formasPago(): array
    {
        return CatalogoCache::formasPago()->toArray();
    }

    // =========================================
    // PROPIEDADES DEL RESULTADO
    // =========================================

    /** @var array|null Resultado del cálculo */
    public $resultado = null;

    /** @var int|null Índice del item con popover de edición de nombre */
    public ?int $editarNombreIndex = null;

    /** @var string Nombre temporal en el popover */
    public string $editarNombreValor = '';

    // =========================================
    // PROPIEDADES MODAL PESABLE
    // =========================================

    public bool $mostrarModalPesable = false;

    public ?int $pesableArticuloId = null;

    public float $pesablePrecioUnitario = 0;

    public string $pesableUnidadMedida = 'kg';

    public string $pesableNombreArticulo = '';

    // =========================================
    // PROPIEDADES DE FACTURACIÓN FISCAL
    // =========================================

    /** @var bool Si se debe emitir factura fiscal (checkbox principal) */
    public $emitirFacturaFiscal = false;

    /** @var bool Si la sucursal actual tiene facturación fiscal automática */
    public $sucursalFacturaAutomatica = false;

    /** @var float Monto total que se facturará fiscalmente */
    public $montoFacturaFiscal = 0;

    /** @var array Desglose de IVA para la factura fiscal (recalculado) */
    public $desgloseIvaFiscal = [];

    // =========================================
    // PROPIEDADES DE SELECCIÓN DE PUNTO DE VENTA FISCAL
    // =========================================

    /** @var bool Mostrar modal de selección de punto de venta */
    public $showPuntoVentaModal = false;

    /** @var int|null ID del punto de venta seleccionado para facturación */
    public $puntoVentaSeleccionadoId = null;

    /** @var array Puntos de venta disponibles para la caja actual */
    public $puntosVentaDisponibles = [];

    /** @var bool Indica si el usuario puede seleccionar punto de venta */
    public $puedeSeleccionarPuntoVenta = false;

    // =========================================
    // INYECCIÓN DE DEPENDENCIAS
    // =========================================

    protected $ventaService;

    protected $opcionalService;

    protected $cuponService;

    protected $puntosService;

    public function boot(VentaService $ventaService, OpcionalService $opcionalService, CuponService $cuponService, PuntosService $puntosService)
    {
        $this->ventaService = $ventaService;
        $this->opcionalService = $opcionalService;
        $this->cuponService = $cuponService;
        $this->puntosService = $puntosService;
    }

    // =========================================
    // CICLO DE VIDA
    // =========================================

    public function placeholder()
    {
        return view('livewire.ventas.nueva-venta-skeleton');
    }

    public function mount()
    {
        $this->sucursalId = sucursal_activa() ?? Sucursal::activas()->first()?->id ?? 1;
        $this->cajaSeleccionada = caja_activa();

        // Validar estado de la caja
        $this->actualizarEstadoCaja();

        // Cargar configuración de facturación fiscal de la sucursal
        $this->cargarConfiguracionFiscalSucursal();

        // Cargar listas de precios
        $this->cargarListasPrecios();

        // Seleccionar lista base por defecto
        $this->listaPrecioId = $this->obtenerIdListaBase();

        // Valores por defecto: primera forma de pago según orden configurado (normalmente Efectivo)
        $this->formaPagoId = $this->formasPago[0]['id'] ?? 1;

        // Valores por defecto: Local (ID 1) para forma de venta
        $local = collect($this->formasVenta)->firstWhere('codigo', 'local');
        $this->formaVentaId = $local['id'] ?? $this->formasVenta[0]['id'] ?? 1;

        // Valores por defecto: POS para canal de venta (no visible en UI pero se usa en cálculos)
        $pos = collect($this->canalesVenta)->firstWhere('codigo', 'pos');
        $this->canalVentaId = $pos['id'] ?? $this->canalesVenta[0]['id'] ?? 1;

        // Establecer factura fiscal según la forma de pago por defecto
        $this->actualizarFacturaFiscalSegunFP();

        // Cargar tope de descuento del usuario (MAX de sus roles)
        $this->cargarTopeDescuentoUsuario();
    }

    public function render()
    {
        return view('livewire.ventas.nueva-venta', [
            'condicionesIvaCliente' => $this->mostrarModalClienteRapido ? CatalogoCache::condicionesIva() : collect(),
        ]);
    }

    // =========================================
    // HANDLERS DE EVENTOS
    // =========================================

    #[On('sucursal-changed')]
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        $this->sucursalId = $sucursalId;
        $this->items = [];
        $this->resultado = null;
        $this->cargarListasPrecios();

        // Recargar configuración de facturación fiscal de la nueva sucursal
        $this->cargarConfiguracionFiscalSucursal();
        $this->actualizarFacturaFiscalSegunFP();

        // Buscar directamente la lista base desde la BD
        $listaBase = ListaPrecio::where('sucursal_id', $sucursalId)
            ->where('es_lista_base', true)
            ->where('activo', true)
            ->first();

        $this->listaPrecioId = $listaBase?->id ?? $this->obtenerIdListaBase();
    }

    #[On('caja-changed')]
    public function handleCajaChanged($cajaId = null, $cajaNombre = null)
    {
        $this->cajaSeleccionada = $cajaId;
        $this->actualizarEstadoCaja();

        if (! empty($this->items)) {
            $this->items = [];
            $this->resultado = null;
            $this->dispatch('toast-info', message: __('Caja cambiada. El carrito ha sido limpiado.'));
        }
    }

    /**
     * Maneja la actualización de estado de cajas (activar, pausar, abrir/cerrar turno)
     * Solo actualiza el estado, no limpia el carrito
     */
    #[On('caja-actualizada')]
    public function handleCajaActualizada($cajaId = null, $accion = null)
    {
        CajaService::clearCache();
        $this->actualizarEstadoCaja();
    }

    public function cambiarCaja($cajaId)
    {
        CajaService::establecerCajaActiva($cajaId);
        CajaService::clearCache();
        $caja = Caja::find($cajaId);
        if ($caja) {
            $this->dispatch('caja-changed', cajaId: $caja->id, cajaNombre: $caja->nombre);
        }
    }

    /**
     * Actualiza el estado de validación de la caja seleccionada
     * Este método determina si la caja está operativa para realizar ventas
     */
    public function actualizarEstadoCaja(): void
    {
        $resultado = CajaService::validarCajaOperativa($this->cajaSeleccionada);

        // Convertir el modelo Caja a array para evitar problemas con Livewire
        $this->estadoCaja = [
            'operativa' => $resultado['operativa'],
            'estado' => $resultado['estado'],
            'mensaje' => $resultado['mensaje'],
            'caja' => $resultado['caja'] ? [
                'id' => $resultado['caja']->id,
                'nombre' => $resultado['caja']->nombre,
                'estado' => $resultado['caja']->estado,
            ] : null,
        ];
    }

    /**
     * Activa una caja que está pausada (tiene turno abierto pero está inactiva)
     */
    public function activarCaja(int $cajaId): void
    {
        try {
            $caja = Caja::find($cajaId);

            if (! $caja) {
                $this->dispatch('toast-error', message: __('Caja no encontrada'));

                return;
            }

            // Verificar que la caja esté cerrada pero tenga movimientos pendientes (pausada)
            if ($caja->estado === 'abierta') {
                $this->dispatch('toast-info', message: __('La caja ya está activa'));
                $this->actualizarEstadoCaja();

                return;
            }

            // Activar la caja (cambiar estado a abierta)
            $caja->update([
                'estado' => 'abierta',
            ]);

            CajaService::clearCache();
            $this->actualizarEstadoCaja();

            // Notificar a otros componentes (CajaSelector, TurnoActual)
            $this->dispatch('caja-actualizada', cajaId: $caja->id, accion: 'activada');

            $this->dispatch('toast-success', message: __('Caja activada correctamente'));

        } catch (\Exception $e) {
            Log::error('Error al activar caja', ['error' => $e->getMessage(), 'caja_id' => $cajaId]);
            $this->dispatch('toast-error', message: __('Error al activar la caja: ').$e->getMessage());
        }
    }

    // =========================================
    // MÉTODOS DE LISTAS DE PRECIOS
    // =========================================

    protected function cargarListasPrecios(): void
    {
        if (! $this->sucursalId) {
            $this->listasPreciosDisponibles = [];

            return;
        }

        $this->listasPreciosDisponibles = ListaPrecio::porSucursal($this->sucursalId)
            ->activas()
            ->orderBy('es_lista_base', 'desc') // Lista base primero
            ->ordenadoPorPrioridad()
            ->get()
            ->map(function ($lista) {
                return [
                    'id' => (int) $lista->id,
                    'nombre' => $lista->nombre,
                    'es_lista_base' => (bool) $lista->es_lista_base,
                    'ajuste_porcentaje' => (float) $lista->ajuste_porcentaje,
                    'descripcion_ajuste' => $lista->obtenerDescripcionAjuste(),
                    'aplica_promociones' => (bool) $lista->aplica_promociones,
                    'promociones_alcance' => $lista->promociones_alcance,
                ];
            })
            ->toArray();
    }

    protected function obtenerIdListaBase(): ?int
    {
        // Primero buscar la lista marcada como base
        foreach ($this->listasPreciosDisponibles as $lista) {
            if (! empty($lista['es_lista_base']) && $lista['es_lista_base'] === true) {
                return (int) $lista['id'];
            }
        }

        // Si no hay lista base marcada, buscar por nombre "Base" o "Lista Base"
        foreach ($this->listasPreciosDisponibles as $lista) {
            $nombreLower = strtolower($lista['nombre'] ?? '');
            if (str_contains($nombreLower, 'base') || str_contains($nombreLower, 'general')) {
                return (int) $lista['id'];
            }
        }

        // Fallback: primera lista disponible
        return $this->listasPreciosDisponibles[0]['id'] ?? null;
    }

    // =========================================
    // EDITAR NOMBRE DE ITEM
    // =========================================

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

    // =========================================
    // MODAL PESABLE
    // =========================================

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

        // Herencia de descuento general % a items nuevos.
        // Excepción: items bonificados por un cupón ya aplicado NO heredan
        // (el cupón tiene prioridad sobre el descuento general).
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

    // =========================================
    // MÉTODOS DE CONCEPTO POR IMPORTE
    // =========================================

    /**
     * Abre el modal para agregar un concepto
     */
    public function abrirModalConcepto()
    {
        $this->categoriasDisponibles = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])
            ->toArray();

        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = null;
        $this->mostrarModalConcepto = true;
    }

    /**
     * Cierra el modal de concepto
     */
    public function cerrarModalConcepto()
    {
        $this->mostrarModalConcepto = false;
        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = 0;
        $this->dispatch('focus-busqueda');
    }

    /**
     * Agrega un concepto al detalle de la venta
     */
    public function agregarConcepto()
    {
        // Validar solo el importe
        if ($this->conceptoImporte <= 0) {
            $this->dispatch('toast-error', message: 'El importe debe ser mayor a cero');

            return;
        }

        // Obtener nombre de categoría y su IVA si se seleccionó
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

        // Determinar descripción: usar la ingresada, o el nombre de categoría, o "Varios"
        $descripcion = $this->conceptoDescripcion;
        if (empty($descripcion)) {
            $descripcion = $categoriaNombre ?? 'Varios';
        }

        // Agregar al carrito como concepto
        $this->items[] = [
            'articulo_id' => null, // No es un artículo
            'es_concepto' => true,
            'codigo' => 'CONCEPTO',
            'nombre' => $descripcion,
            'categoria_id' => $this->conceptoCategoriaId,
            'categoria_nombre' => $categoriaNombre,
            'precio_base' => (float) $this->conceptoImporte,
            'precio' => (float) $this->conceptoImporte,
            'cantidad' => 1,
            'tiene_ajuste' => false,
            // Información de IVA (de la categoría o 21% por defecto)
            'iva_codigo' => $ivaInfo['codigo'],
            'iva_porcentaje' => $ivaInfo['porcentaje'],
            'iva_nombre' => $ivaInfo['nombre'],
            'precio_iva_incluido' => true, // Los conceptos siempre tienen IVA incluido
            // Campos para ajuste manual (necesarios para descuento general)
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'ajuste_manual_origen' => null,
            'ajuste_manual_aplicado_por' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
        ];

        // RF-34: Herencia de descuento general % a conceptos nuevos.
        // Conceptos no tienen articulo_id, por lo que nunca son bonificados por
        // cupones de artículos — siempre heredan el descuento general.
        if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'porcentaje') {
            $lastIndex = count($this->items) - 1;
            $precioBase = (float) $this->conceptoImporte;
            $nuevoPrecio = round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2);
            if ($nuevoPrecio < 0) {
                $nuevoPrecio = 0;
            }
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
        $this->dispatch('toast-success', message: 'Concepto agregado al detalle');
    }

    // =========================================
    // HANDLERS DE CAMBIO DE FILTROS
    // =========================================

    public function updatedListaPrecioId()
    {
        $this->actualizarPreciosItems();
        $this->calcularVenta();
    }

    public function updatedFormaVentaId()
    {
        $this->actualizarPreciosItems();
        $this->calcularVenta();
    }

    public function updatedCanalVentaId()
    {
        $this->actualizarPreciosItems();
        $this->calcularVenta();
    }

    public function updatedFormaPagoId()
    {
        // Limpiar desglose anterior si había uno
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = 0;
        $this->totalConAjustes = 0;

        // Limpiar valores mixtos del desglose de IVA
        $this->limpiarDesgloseIvaMixto();

        // Resetear cuotas
        $this->cuotaSeleccionadaId = null;
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;
        $this->cuotasSelectorAbierto = false;
        $this->resetInfoCuotaSeleccionada();

        // Cargar cuotas disponibles para la forma de pago seleccionada
        $this->cargarCuotasFormaPago();

        $this->actualizarPreciosItems();
        $this->calcularVenta(); // Esto ya llama a calcularAjusteFormaPago()

        // Actualizar el checkbox de factura fiscal según la FP seleccionada
        $this->actualizarFacturaFiscalSegunFP();

        // Si es forma de pago mixta, abrir modal de desglose
        if ($this->ajusteFormaPagoInfo['es_mixta'] && ! empty($this->items)) {
            $this->abrirModalDesglose();
        }
    }

    /**
     * Cuando cambia la cuota seleccionada
     */
    public function updatedCuotaSeleccionadaId()
    {
        $this->calcularInfoCuotaSeleccionada();
        $this->calcularAjusteFormaPago();
        // Cerrar el selector al seleccionar una opción
        $this->cuotasSelectorAbierto = false;
    }

    /**
     * Toggle del selector de cuotas
     */
    public function toggleCuotasSelector(): void
    {
        $this->cuotasSelectorAbierto = ! $this->cuotasSelectorAbierto;
    }

    /**
     * Carga las cuotas disponibles para la forma de pago seleccionada
     */
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

        // Obtener cuotas activas para esta forma de pago
        $cuotas = FormaPagoCuota::where('forma_pago_id', $this->formaPagoId)
            ->where('activo', true)
            ->orderBy('cantidad_cuotas')
            ->get();

        $totalBase = $this->resultado['total_final'] ?? 0;

        foreach ($cuotas as $cuota) {
            // Verificar si está activa en la sucursal
            $configSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            // Si existe config de sucursal y está desactivada, omitir
            if ($configSucursal && ! $configSucursal->activo) {
                continue;
            }

            // Obtener recargo efectivo (sucursal o general)
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

    /**
     * Calcula la información de la cuota seleccionada
     */
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

    /**
     * Formatea la descripción de una cuota
     */
    protected function formatearDescripcionCuota(array $cuotaInfo): string
    {
        $cantCuotas = $cuotaInfo['cantidad_cuotas'];
        $recargo = $cuotaInfo['recargo_porcentaje'];

        if ($cantCuotas === 1) {
            return '1 pago';
        }

        $desc = "{$cantCuotas} cuotas de $".number_format($cuotaInfo['valor_cuota'], 2, ',', '.');

        if ($recargo > 0) {
            $desc .= " (+{$recargo}%)";
        } else {
            $desc .= ' (sin interés)';
        }

        return $desc;
    }

    /**
     * Resetea la información de cuota seleccionada
     */
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

    /**
     * Calcula el ajuste de la forma de pago seleccionada
     * Incluye ajuste de forma de pago + recargo por cuotas si aplica
     */
    protected function calcularAjusteFormaPago(): void
    {
        // Resetear
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

        // Cargar formas de pago si no están cargadas
        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (! $fp) {
            // Intentar cargar desde la base de datos
            $formaPago = FormaPago::find($this->formaPagoId);
            if (! $formaPago) {
                return;
            }

            // Obtener ajuste específico de sucursal o general
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

        // Variables para cuotas
        $cantidadCuotas = 1;
        $recargoCuotasPorcentaje = 0;
        $recargoCuotasMonto = 0;
        $valorCuota = $totalConAjuste;

        // Si hay cuota seleccionada, aplicar recargo de cuotas
        if ($this->cuotaSeleccionadaId && ! empty($this->cuotasFormaPagoDisponibles)) {
            $cuotaInfo = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $this->cuotaSeleccionadaId);

            if ($cuotaInfo) {
                $cantidadCuotas = $cuotaInfo['cantidad_cuotas'];
                $recargoCuotasPorcentaje = $cuotaInfo['recargo_porcentaje'];

                // El recargo de cuotas se aplica sobre el total con ajuste de forma de pago
                $recargoCuotasMonto = round($totalConAjuste * ($recargoCuotasPorcentaje / 100), 2);
                $totalConAjuste = round($totalConAjuste + $recargoCuotasMonto, 2);
                $valorCuota = $cantidadCuotas > 0 ? round($totalConAjuste / $cantidadCuotas, 2) : $totalConAjuste;

                // Actualizar info de cuota seleccionada con valores recalculados
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

        // Recalcular desglose de IVA con el ajuste de forma de pago
        $this->actualizarDesgloseIvaConAjusteFormaPago($montoAjuste, $recargoCuotasMonto);
    }

    /**
     * Actualiza el desglose de IVA considerando el ajuste de forma de pago y recargo por cuotas
     *
     * El ajuste de forma de pago (descuento o recargo) se prorratea proporcionalmente
     * entre las alícuotas de IVA, siguiendo las reglas de AFIP.
     *
     * @param  float  $montoAjusteFormaPago  Monto del ajuste de forma de pago (negativo = descuento)
     * @param  float  $montoRecargoCuotas  Monto del recargo por cuotas (siempre positivo o cero)
     */
    protected function actualizarDesgloseIvaConAjusteFormaPago(float $montoAjusteFormaPago, float $montoRecargoCuotas): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            return;
        }

        $desglose = $this->resultado['desglose_iva'];
        $totalNetoBase = $desglose['total_neto'];

        // Si no hay ajustes ni neto base, no hay nada que hacer
        if ($totalNetoBase == 0 || ($montoAjusteFormaPago == 0 && $montoRecargoCuotas == 0)) {
            // Agregar campos para ajuste de forma de pago (vacíos)
            $this->resultado['desglose_iva']['ajuste_forma_pago'] = 0;
            $this->resultado['desglose_iva']['recargo_cuotas'] = 0;
            $this->resultado['desglose_iva']['total_con_ajuste_fp'] = $desglose['total'];

            return;
        }

        // Combinar ajustes (el ajuste de forma de pago puede ser negativo)
        $ajusteTotal = $montoAjusteFormaPago + $montoRecargoCuotas;

        // Calcular el total actual (subtotal con IVA) para prorratear
        $totalSubtotalBase = array_sum(array_column($desglose['por_alicuota'], 'subtotal'));

        // El ajuste afecta al neto y al IVA proporcionalmente
        // Por cada alícuota, agregamos/quitamos la proporción correspondiente
        // IMPORTANTE: Prorrateamos sobre el subtotal (con IVA), no sobre el neto
        $nuevoPorAlicuota = [];
        foreach ($desglose['por_alicuota'] as $alicuota) {
            // Proporción de esta alícuota sobre el subtotal total (con IVA)
            $proporcion = $totalSubtotalBase > 0 ? $alicuota['subtotal'] / $totalSubtotalBase : 0;

            // Ajuste asignado a esta alícuota (con IVA incluido)
            $ajusteAlicuotaConIva = $ajusteTotal * $proporcion;

            // Convertir el ajuste a neto (el ajuste "incluye" IVA proporcionalmente)
            if ($alicuota['porcentaje'] > 0) {
                $ajusteNetoAlicuota = $ajusteAlicuotaConIva / (1 + $alicuota['porcentaje'] / 100);
            } else {
                $ajusteNetoAlicuota = $ajusteAlicuotaConIva; // Exento o no gravado
            }

            // Nuevo neto después del ajuste
            $nuevoNeto = $alicuota['neto'] + $ajusteNetoAlicuota;

            // Nuevo IVA sobre el nuevo neto
            $nuevoIva = $nuevoNeto * ($alicuota['porcentaje'] / 100);

            $nuevoPorAlicuota[] = [
                'codigo' => $alicuota['codigo'],
                'nombre' => $alicuota['nombre'],
                'porcentaje' => $alicuota['porcentaje'],
                'neto_sin_descuento' => $alicuota['neto_sin_descuento'],
                'iva_sin_descuento' => $alicuota['iva_sin_descuento'],
                'subtotal_sin_descuento' => $alicuota['subtotal_sin_descuento'],
                'neto' => round($alicuota['neto'], 3), // Neto después de promociones (sin ajuste FP)
                'iva' => round($alicuota['iva'], 3), // IVA después de promociones (sin ajuste FP)
                'subtotal' => round($alicuota['subtotal'], 3),
                'descuento_aplicado' => $alicuota['descuento_aplicado'],
                // Nuevos campos con ajuste de forma de pago
                'neto_con_ajuste_fp' => round($nuevoNeto, 3),
                'iva_con_ajuste_fp' => round($nuevoIva, 3),
                'subtotal_con_ajuste_fp' => round($nuevoNeto + $nuevoIva, 3),
                'ajuste_fp_aplicado' => round($ajusteAlicuotaConIva, 3),
            ];
        }

        // Calcular nuevos totales
        $totalNetoConAjuste = array_sum(array_column($nuevoPorAlicuota, 'neto_con_ajuste_fp'));
        $totalIvaConAjuste = array_sum(array_column($nuevoPorAlicuota, 'iva_con_ajuste_fp'));
        $totalConAjuste = array_sum(array_column($nuevoPorAlicuota, 'subtotal_con_ajuste_fp'));

        // Actualizar el desglose
        $this->resultado['desglose_iva'] = [
            'por_alicuota' => $nuevoPorAlicuota,
            'total_neto' => $desglose['total_neto'], // Neto sin ajuste de forma de pago
            'total_iva' => $desglose['total_iva'], // IVA sin ajuste de forma de pago
            'total' => $desglose['total'], // Total sin ajuste de forma de pago
            'descuento_aplicado' => $desglose['descuento_aplicado'], // Descuento de promociones
            // Nuevos campos con ajuste de forma de pago
            'ajuste_forma_pago' => round($montoAjusteFormaPago, 3),
            'recargo_cuotas' => round($montoRecargoCuotas, 3),
            'total_neto_con_ajuste_fp' => round($totalNetoConAjuste, 3),
            'total_iva_con_ajuste_fp' => round($totalIvaConAjuste, 3),
            'total_con_ajuste_fp' => round($totalConAjuste, 3),
        ];
    }

    /**
     * Abre el modal de desglose para formas de pago mixtas
     */
    public function abrirModalDesglose(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: 'El carrito está vacío');

            return;
        }

        // Cargar formas de pago actualizadas
        $this->cargarFormasPagoSucursal();

        // Inicializar desglose vacío para mixtas
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = $this->resultado['total_final'] ?? 0;
        $this->totalConAjustes = $this->montoPendienteDesglose;
        $this->resetNuevoPago();
        $this->mostrarModalPago = true;
    }

    /**
     * Abre el modal para editar un desglose existente
     */
    public function editarDesglose(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: 'El carrito está vacío');

            return;
        }

        if (empty($this->desglosePagos)) {
            // Si no hay desglose, abrir como nuevo
            $this->abrirModalDesglose();

            return;
        }

        // Cargar formas de pago actualizadas
        $this->cargarFormasPagoSucursal();

        // Recalcular monto pendiente basado en el desglose actual
        $totalDesglosado = collect($this->desglosePagos)->sum('monto_base');
        $this->montoPendienteDesglose = max(0, ($this->resultado['total_final'] ?? 0) - $totalDesglosado);

        // Recalcular total con ajustes
        $this->totalConAjustes = collect($this->desglosePagos)->sum('monto_final');

        $this->resetNuevoPago();
        $this->mostrarModalPago = true;
    }

    protected function actualizarPreciosItems(): void
    {
        foreach ($this->items as $index => $item) {
            $articulo = Articulo::find($item['articulo_id']);
            if ($articulo) {
                $precioInfo = $this->obtenerPrecioConLista($articulo);

                // Si tiene ajuste manual, mantenerlo y recalcular sobre el nuevo base
                if (($item['ajuste_manual_tipo'] ?? null) !== null) {
                    $precioBase = $precioInfo['precio_base'];
                    $this->items[$index]['precio_base'] = $precioBase;

                    // Recalcular el precio manual sobre el nuevo base
                    if ($item['ajuste_manual_tipo'] === 'monto') {
                        // El monto es fijo, no cambia
                        $this->items[$index]['precio'] = $item['ajuste_manual_valor'];
                    } else {
                        // Porcentaje: positivo = descuento, negativo = recargo
                        $porcentaje = (float) $item['ajuste_manual_valor'];
                        $this->items[$index]['precio'] = round($precioBase - ($precioBase * $porcentaje / 100), 2);
                    }
                    $this->items[$index]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
                    $this->items[$index]['tiene_ajuste'] = true;
                } else {
                    // Sin ajuste manual: usar precio de lista
                    $this->items[$index]['precio'] = $precioInfo['precio'];
                    $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
                    $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
                }
            }
        }
    }

    /**
    /**
     * Procesa la venta con una sola forma de pago (sin desglose)
     *
     * Similar a procesarVentaConDesglose pero para pago único.
     * También verifica si debe emitir factura fiscal.
     */
    public function procesarVenta()
    {
        try {
            if (empty($this->items)) {
                $this->dispatch('toast-error', message: 'El carrito está vacío');

                return;
            }

            $sucursal = Sucursal::find($this->sucursalId);
            if (! $sucursal) {
                $this->dispatch('toast-error', message: 'Sucursal no encontrada');

                return;
            }

            $formaPago = FormaPago::find($this->formaPagoId);
            $esCuentaCorriente = $formaPago && strtoupper($formaPago->codigo) === 'CTA_CTE';
            $totalVenta = $this->resultado['total_final'] ?? 0;

            // Venta totalmente invitada (cortesia): no requiere cliente CC, caja ni pagos.
            // El detalle persiste con es_invitacion=true y monto_invitado>0; la cabecera
            // arranca con total_final=0 y estado_pago=pagado.
            $esInvitacionCompleta = $this->esInvitacionTotal && $totalVenta <= 0.005;

            // Validar cliente si es cuenta corriente (no aplica si todo es invitacion).
            if ($esCuentaCorriente && ! $esInvitacionCompleta) {
                if (! $this->clienteSeleccionado) {
                    $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');

                    return;
                }

                $cliente = Cliente::find($this->clienteSeleccionado);
                if (! $cliente || ! $cliente->tiene_cuenta_corriente) {
                    $this->dispatch('toast-error', message: 'El cliente no tiene cuenta corriente habilitada');

                    return;
                }

                // Verificar límite de crédito
                $nuevoSaldo = $cliente->saldo_deudor_cache + $totalVenta;
                if ($cliente->limite_credito > 0 && $nuevoSaldo > $cliente->limite_credito) {
                    $this->dispatch('toast-error', message: 'El cliente excede su límite de crédito');

                    return;
                }
            }

            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            if (! $esCuentaCorriente && ! $esInvitacionCompleta) {
                if (! $cajaId) {
                    $this->dispatch('toast-error', message: 'Debe seleccionar una caja');

                    return;
                }

                $caja = Caja::find($cajaId);
                if (! $caja || ! $caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: 'La caja debe estar abierta');

                    return;
                }
            }

            // Verificar si debe generar factura fiscal
            // Se factura si:
            // 1. Automático: sucursal.facturacion_fiscal_automatica = true Y forma de pago tiene factura_fiscal = true
            // 2. Manual: el usuario marcó el checkbox emitirFacturaFiscal
            $comprobanteFiscalService = new ComprobanteFiscalService;
            $pagosParaValidar = [[
                'forma_pago_id' => $this->formaPagoId,
                'monto_final' => $totalVenta,
            ]];
            $debeFacturarAutomatico = $comprobanteFiscalService->debeGenerarFacturaFiscal($sucursal, $pagosParaValidar);
            $debeFacturarManual = $this->emitirFacturaFiscal;
            // Una venta totalmente invitada nunca se factura: no hay base imponible (total=0).
            $debeFacturar = ! $esInvitacionCompleta && ($debeFacturarAutomatico || $debeFacturarManual);

            DB::connection('pymes_tenant')->beginTransaction();

            try {
                // Preparar datos de la venta
                $datosVenta = [
                    'sucursal_id' => $this->sucursalId,
                    'cliente_id' => $this->clienteSeleccionado,
                    'caja_id' => $cajaId,
                    'usuario_id' => Auth::id(),
                    // Cortesia total: no hay forma de pago real. Si pasamos el id
                    // del selector (default Efectivo), el listado y el detalle
                    // muestran "Efectivo $0" lo cual confunde al operador. Mejor
                    // dejar en null y que la vista muestre "Cortesia".
                    'forma_pago_id' => $esInvitacionCompleta ? null : $this->formaPagoId,
                    'forma_venta_id' => $this->formaVentaId,
                    'canal_venta_id' => $this->canalVentaId,
                    'lista_precio_id' => $this->listaPrecioId,
                    'descuento' => $this->resultado['total_descuentos'] ?? 0,
                    'observaciones' => $this->observaciones,
                    'total' => $totalVenta,
                    // Campos de cuenta corriente
                    'es_cuenta_corriente' => $esCuentaCorriente,
                    'saldo_pendiente_cache' => $esCuentaCorriente ? $totalVenta : 0,
                    'fecha_vencimiento' => $esCuentaCorriente
                        ? now()->addDays($cliente->dias_credito ?? 30)->toDateString()
                        : null,
                    // Descuento general (RF-38)
                    'descuento_general_tipo' => $this->descuentoGeneralActivo ? $this->descuentoGeneralTipo : null,
                    'descuento_general_valor' => $this->descuentoGeneralActivo ? $this->descuentoGeneralValor : null,
                    'descuento_general_monto' => $this->descuentoGeneralMonto,
                    'descuento_general_aplicado_por' => $this->descuentoGeneralActivo ? $this->descuentoGeneralAplicadoPor : null,
                    // Cupón (RF-19)
                    'cupon_id' => $this->cuponAplicado && $this->cuponInfo ? $this->cuponInfo['id'] : null,
                    'monto_cupon' => $this->cuponMontoDescuento,
                    // Puntos (RF-09)
                    'puntos_usados' => $this->canjePuntosActivo ? $this->canjePuntosUnidades : 0,
                    // Invitacion (cortesia). Cabecera: solo se llena cuando la venta
                    // completa es cortesia. total_invitado se persiste siempre como
                    // cache del SUM(detalle.monto_invitado) para reportes.
                    'es_invitacion_total' => $this->esInvitacionTotal,
                    'invitacion_motivo' => $this->esInvitacionTotal
                        ? (trim($this->motivoInvitacionTotal) ?: null)
                        : null,
                    'invitado_por_usuario_id' => $this->esInvitacionTotal ? Auth::id() : null,
                    'invitado_at' => $this->esInvitacionTotal ? now() : null,
                    'total_invitado' => (float) $this->totalInvitado,
                ];

                $descuentoCuponPorItem = $this->calcularDescuentoCuponPorItem();

                $detalles = [];
                foreach ($this->items as $index => $item) {
                    $esConcepto = (bool) ($item['es_concepto'] ?? false);
                    $detalles[] = [
                        'articulo_id' => $item['articulo_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'descuento' => 0,
                        'descuento_cupon' => $descuentoCuponPorItem[$index] ?? 0,
                        'opcionales' => $esConcepto ? [] : ($item['opcionales'] ?? []),
                        'precio_opcionales' => $esConcepto ? 0 : ($item['precio_opcionales'] ?? 0),
                        // Info de IVA
                        'tipo_iva_id' => $this->resolverTipoIvaId($item),
                        'iva_porcentaje' => $item['iva_porcentaje'] ?? 21,
                        'precio_iva_incluido' => $item['precio_iva_incluido'] ?? true,
                        // Canje por puntos (RF-10)
                        'pagado_con_puntos' => $esConcepto ? false : ($item['pagado_con_puntos'] ?? false),
                        'puntos_usados' => $esConcepto || ! ($item['pagado_con_puntos'] ?? false)
                            ? 0
                            : $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0)) * (float) ($item['cantidad'] ?? 1),
                        // Concepto libre
                        'es_concepto' => $esConcepto,
                        'concepto_descripcion' => $esConcepto ? ($item['nombre'] ?? null) : null,
                        'concepto_categoria_id' => $esConcepto ? ($item['categoria_id'] ?? null) : null,
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

                $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

                // Si la venta es totalmente invitada, no hay pago: no se crea VentaPago,
                // no afecta caja ni cuenta empresa. Sigue el flow para cupon/puntos/CC
                // (que ya validan sus propias precondiciones).
                $ventaPagoSimple = null;
                if (! $esInvitacionCompleta) {
                    // Crear VentaPago para el pago único
                    $afectaCaja = ! $esCuentaCorriente && $cajaId;
                    $movimientoCajaId = null;

                    if ($afectaCaja) {
                        $movimiento = MovimientoCaja::crearIngresoVenta(
                            Caja::find($cajaId),
                            $venta,
                            $totalVenta,
                            Auth::id()
                        );
                        $movimientoCajaId = $movimiento->id;

                        $caja = Caja::find($cajaId);
                        $caja->aumentarSaldo($totalVenta);
                    }

                    $ventaPagoSimple = VentaPago::create([
                        'venta_id' => $venta->id,
                        'forma_pago_id' => $this->formaPagoId,
                        'concepto_pago_id' => $formaPago?->concepto_pago_id,
                        'monto_base' => $totalVenta,
                        'ajuste_porcentaje' => 0,
                        'monto_ajuste' => 0,
                        'monto_final' => $totalVenta,
                        'monto_recibido' => $totalVenta,
                        'vuelto' => 0,
                        'es_cuenta_corriente' => $esCuentaCorriente,
                        'afecta_caja' => $afectaCaja,
                        'estado' => 'activo',
                        'movimiento_caja_id' => $movimientoCajaId,
                        'moneda_id' => $formaPago?->moneda_id ?? Moneda::obtenerPrincipal()?->id,
                    ]);
                }

                // Si la forma de pago tiene cuenta empresa vinculada, registrar movimiento
                if (! $esInvitacionCompleta && ! $esCuentaCorriente && $formaPago && $formaPago->cuenta_empresa_id) {
                    try {
                        $movCuenta = CuentaEmpresaService::registrarMovimientoAutomatico(
                            CuentaEmpresa::find($formaPago->cuenta_empresa_id),
                            'ingreso', $totalVenta, 'venta',
                            'VentaPago', $ventaPagoSimple->id,
                            "Venta #{$venta->numero} - {$formaPago->nombre}",
                            Auth::id(), sucursal_activa()
                        );
                        $ventaPagoSimple->update(['movimiento_cuenta_empresa_id' => $movCuenta->id]);
                    } catch (\Exception $e) {
                        Log::warning('Error al registrar movimiento en cuenta empresa', ['error' => $e->getMessage()]);
                    }
                }

                // Generar comprobante fiscal si corresponde
                $comprobanteFiscal = null;
                if ($debeFacturar) {
                    try {
                        $comprobanteFiscal = $comprobanteFiscalService->crearComprobanteFiscal($venta);

                        Log::info('Comprobante fiscal emitido', [
                            'venta_id' => $venta->id,
                            'comprobante_id' => $comprobanteFiscal->id,
                            'cae' => $comprobanteFiscal->cae,
                        ]);
                    } catch (Exception $e) {
                        // Si el usuario pidió factura fiscal y falla, NO grabar la venta
                        Log::error('Error al emitir comprobante fiscal - cancelando venta', [
                            'error' => $e->getMessage(),
                        ]);

                        // Hacer rollback de toda la transacción
                        DB::connection('pymes_tenant')->rollBack();

                        // Notificar al usuario del error (sin limpiar carrito para que pueda reintentar)
                        $this->dispatch('toast-error', message: 'Error al emitir factura fiscal: '.$e->getMessage());

                        return;
                    }
                }

                // Registrar uso de cupón si se aplicó uno (RF-19)
                if ($this->cuponAplicado && $this->cuponInfo && $this->cuponMontoDescuento > 0) {
                    $cuponObj = Cupon::find($this->cuponInfo['id']);
                    if ($cuponObj) {
                        $this->cuponService->aplicarCuponEnVenta(
                            $cuponObj,
                            $venta,
                            $this->cuponMontoDescuento,
                            Auth::id()
                        );
                    }
                }

                // Registrar canje de puntos como pago (RF-09)
                if ($this->canjePuntosActivo && $this->canjePuntosMonto > 0 && $this->clienteSeleccionado) {
                    // Crear VentaPago especial para puntos
                    $ventaPagoPuntos = VentaPago::create([
                        'venta_id' => $venta->id,
                        'forma_pago_id' => $this->formaPagoId, // Se usa la FP principal como referencia
                        'monto_base' => $this->canjePuntosMonto,
                        'ajuste_porcentaje' => 0,
                        'monto_ajuste' => 0,
                        'monto_final' => $this->canjePuntosMonto,
                        'es_pago_puntos' => true,
                        'puntos_usados' => $this->canjePuntosUnidades,
                        'afecta_caja' => false,
                        'estado' => 'activo',
                    ]);

                    $this->puntosService->canjearPuntosComoDescuento(
                        $this->clienteSeleccionado,
                        $this->sucursalId,
                        $this->canjePuntosMonto,
                        $ventaPagoPuntos->id,
                        $venta->id,
                        Auth::id()
                    );

                    $this->puntosService->actualizarCacheCliente($this->clienteSeleccionado);
                    $venta->update(['puntos_usados' => $this->canjePuntosUnidades]);
                }

                // Registrar canjes de artículos por puntos (RF-10)
                $puntosArticulosCanjeados = $this->calcularPuntosUsadosEnArticulos();
                if ($puntosArticulosCanjeados > 0 && $this->clienteSeleccionado) {
                    foreach ($this->items as $item) {
                        if ($item['pagado_con_puntos'] ?? false) {
                            $puntosItem = $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0)) * (float) ($item['cantidad'] ?? 1);
                            $this->puntosService->canjearArticuloConPuntos(
                                $this->clienteSeleccionado,
                                $item['articulo_id'],
                                $this->sucursalId,
                                $puntosItem,
                                $venta->id,
                                Auth::id()
                            );
                        }
                    }
                    $this->puntosService->actualizarCacheCliente($this->clienteSeleccionado);
                    // Sumar puntos de artículos a los ya registrados
                    $puntosUsadosTotal = ($venta->puntos_usados ?? 0) + $puntosArticulosCanjeados;
                    $venta->update(['puntos_usados' => $puntosUsadosTotal]);
                }

                // Registrar movimientos de cuenta corriente si el cliente tiene CC habilitada
                // Se hace DESPUÉS de la facturación para que los comprobantes fiscales ya existan
                if ($this->clienteSeleccionado) {
                    $clienteCC = Cliente::find($this->clienteSeleccionado);
                    if ($clienteCC && $clienteCC->tiene_cuenta_corriente) {
                        $ventaService = new \App\Services\VentaService;
                        $ventaService->procesarPagosCuentaCorriente($venta, auth()->id());
                    }
                }

                DB::connection('pymes_tenant')->commit();

                // Mensaje de éxito
                $mensaje = "Venta #{$venta->numero} creada exitosamente";
                if ($comprobanteFiscal && $comprobanteFiscal->cae) {
                    $mensaje .= " - Factura {$comprobanteFiscal->numero_formateado} CAE: {$comprobanteFiscal->cae}";
                }

                $this->dispatch('toast-success', message: $mensaje);

                // Acumular puntos de fidelización (post-commit, no crítico)
                $this->acumularPuntosPostVenta($venta);

                // Mostrar advertencias de stock si las hay (modo 'advierte')
                if (! empty($this->ventaService->advertenciasStock)) {
                    foreach ($this->ventaService->advertenciasStock as $adv) {
                        $this->dispatch('toast-warning', message: __('Advertencia de stock').': '.$adv);
                    }
                }

                // Disparar evento para impresion automatica
                $this->dispararEventoImpresion($venta, $comprobanteFiscal);

                $this->limpiarCarrito(false); // Sin mensaje, ya mostramos toast-success

            } catch (Exception $e) {
                DB::connection('pymes_tenant')->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('Error al procesar venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: '.$e->getMessage());
        }
    }

    public function confirmarLimpiarCarrito()
    {
        if (empty($this->items)) {
            return;
        }
        $this->mostrarConfirmLimpiar = true;
    }

    public function cancelarLimpiarCarrito()
    {
        $this->mostrarConfirmLimpiar = false;
    }

    public function ejecutarLimpiarCarrito()
    {
        $this->mostrarConfirmLimpiar = false;
        $this->limpiarCarrito();
    }

    public function limpiarCarrito($mostrarMensaje = true)
    {
        // Resetear carrito y resultado
        $this->items = [];
        $this->resultado = null;

        // Resetear cliente
        $this->clienteSeleccionado = null;
        $this->clienteNombre = '';
        $this->busquedaCliente = '';
        $this->clientesResultados = [];

        // Resetear búsqueda de artículos
        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->cantidadAgregar = 1;
        $this->mostrarModalArticuloRapido = false;
        $this->mostrarModalBusquedaArticulos = false;
        $this->busquedaArticuloModal = '';
        $this->articulosModalResultados = [];
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = [];

        // Resetear observaciones
        $this->observaciones = null;

        // Resetear modal de pago y desglose
        $this->mostrarModalPago = false;
        $this->desglosePagos = [];
        $this->montoPendienteDesglose = 0;
        $this->totalConAjustes = 0;
        $this->nuevoPago = [
            'forma_pago_id' => null,
            'monto' => null,
            'cuotas' => 1,
            'monto_recibido' => 0,
        ];
        $this->cuotasDisponibles = [];
        $this->ajusteFormaPagoInfo = [
            'nombre' => '',
            'porcentaje' => 0,
            'monto' => 0,
            'total_con_ajuste' => 0,
            'es_mixta' => false,
        ];

        // Resetear cuotas del selector principal
        $this->cuotasFormaPagoDisponibles = [];
        $this->cuotaSeleccionadaId = null;
        $this->formaPagoPermiteCuotas = false;
        $this->infoCuotaSeleccionada = [
            'cantidad_cuotas' => 1,
            'recargo_porcentaje' => 0,
            'recargo_monto' => 0,
            'valor_cuota' => 0,
            'total_con_recargo' => 0,
            'descripcion' => '1 pago',
        ];
        $this->cuotasSelectorAbierto = false;
        $this->cuotasDesgloseSelectorAbierto = false;
        $this->cuotasDesgloseConMontos = [];

        // Resetear ajuste manual
        $this->ajusteManualPopoverIndex = null;
        $this->ajusteManualTipo = 'monto';
        $this->ajusteManualValor = null;

        // Resetear descuento general
        $this->showModalDescuentos = false;
        $this->descuentoGeneralActivo = false;
        $this->descuentoGeneralTipo = null;
        $this->descuentoGeneralValor = null;
        $this->descuentoGeneralMonto = 0;
        $this->descuentoGeneralInputValor = null;
        $this->descuentoGeneralInputTipo = 'porcentaje';

        // Resetear cupón
        $this->cuponCodigoInput = '';
        $this->cuponAplicado = false;
        $this->cuponInfo = null;
        $this->cuponMontoDescuento = 0;
        $this->cuponArticulosBonificados = [];

        // Resetear canje de puntos
        $this->puntosDisponibles = false;
        $this->puntosSaldoCliente = 0;
        $this->canjePuntosActivo = false;
        $this->canjePuntosMonto = null;
        $this->canjePuntosUnidades = 0;
        $this->canjePuntosMaximo = 0;
        $this->puntosMinimoCanje = 0;
        $this->canjePuntosInputMonto = null;

        // Resetear facturación fiscal (pero mantener la config de sucursal)
        $this->montoFacturaFiscal = 0;
        $this->desgloseIvaFiscal = [];
        // Reestablecer emitirFacturaFiscal según la forma de pago por defecto
        $this->actualizarFacturaFiscalSegunFP();

        // Resetear selección de punto de venta fiscal
        $this->showPuntoVentaModal = false;
        $this->puntoVentaSeleccionadoId = null;
        $this->puntosVentaDisponibles = [];

        // Resetear modal de vuelto
        $this->mostrarModalVuelto = false;
        $this->pagoConVuelto = [
            'forma_pago_id' => null,
            'nombre' => '',
            'total_a_pagar' => 0,
            'monto_recibido' => 0,
            'vuelto' => 0,
        ];

        // Resetear wizard de opcionales
        $this->mostrarWizardOpcionales = false;
        $this->wizardArticuloId = null;
        $this->wizardArticuloData = null;
        $this->wizardGrupos = [];
        $this->wizardPasoActual = 0;
        $this->wizardSelecciones = [];
        $this->wizardEditandoIndex = null;

        // Resetear modales
        $this->mostrarModalConsulta = false;
        $this->mostrarModalConcepto = false;
        $this->mostrarModalClienteRapido = false;
        $this->mostrarConfirmLimpiar = false;
        $this->articuloConsulta = null;
        $this->modoConsulta = false;
        $this->modoBusqueda = false;
        $this->itemResaltado = null;

        // Resetear concepto
        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = 0;

        // Resetear cliente rápido
        $this->resetClienteRapido();

        // Resetear estado de invitaciones (trait WithInvitaciones)
        $this->invitarTodo = false;
        $this->motivoInvitacionTotal = '';
        $this->totalInvitado = 0.0;
        $this->mostrarModalInvitarTodo = false;
        $this->mostrarModalDesinvitarTodo = false;
        $this->mostrarModalInvitarItem = false;
        $this->mostrarModalDesinvitarItem = false;
        $this->invitarItemIndex = null;
        $this->invitarItemMotivo = '';
        $this->desinvitarItemIndex = null;

        // Volver a valores por defecto de selectores (primera forma de pago según orden)
        $this->formaPagoId = $this->formasPago[0]['id'] ?? 1;

        if ($mostrarMensaje) {
            $this->dispatch('toast-info', message: 'Carrito limpiado');
        }
    }

    /**
     * Dispara el evento para impresion automatica despues de una venta
     */
    protected function dispararEventoImpresion($venta, $comprobanteFiscal = null): void
    {
        try {
            // Obtener configuracion de impresion de la sucursal
            $config = \App\Models\ConfiguracionImpresion::where('sucursal_id', $this->sucursalId)->first();

            $imprimirFacturaConfig = $config?->impresion_automatica_factura ?? true;
            $imprimirTicketConfig = $config?->impresion_automatica_venta ?? true;

            // Determinar si hay porcion no fiscal comparando totales
            $totalVenta = (float) $venta->total_final;
            $totalFacturado = $comprobanteFiscal ? (float) $comprobanteFiscal->total : 0;

            // Hay porcion no fiscal si el total facturado es menor al total de la venta
            $tieneMontoNoFiscal = $totalFacturado < ($totalVenta - 0.01); // tolerancia de 1 centavo

            // Si hay factura fiscal y esta habilitada su impresion
            $imprimirFactura = $comprobanteFiscal && $imprimirFacturaConfig;

            // Imprimir ticket si:
            // - Hay monto no fiscal (venta mixta) Y ticket habilitado
            // - O no hay factura Y ticket habilitado
            $imprimirTicket = $imprimirTicketConfig && ($tieneMontoNoFiscal || ! $comprobanteFiscal);

            // Solo disparar si hay algo que imprimir
            if ($imprimirTicket || $imprimirFactura) {
                $this->dispatch('venta-completada', [
                    'ventaId' => $venta->id,
                    'imprimirTicket' => $imprimirTicket,
                    'imprimirFactura' => $imprimirFactura,
                    'comprobanteId' => $comprobanteFiscal?->id,
                ]);
            }
        } catch (\Exception $e) {
            // No interrumpir el flujo si falla la impresion
            \Illuminate\Support\Facades\Log::warning('Error al disparar evento de impresion', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================
    // OVERRIDES PARA TRAIT WithInvitaciones
    // =========================================

    /**
     * En NuevaVenta los permisos viven bajo `func.ventas.*` (Pedidos usa
     * `func.pedidos_mostrador.*`). Los nombres concretos:
     *   - func.ventas.invitar_venta     → invitar la venta completa
     *   - func.ventas.invitar_renglon   → invitar un item individual
     */
    protected function getPermisoInvitacionPrefix(): string
    {
        return 'func.ventas';
    }

    protected function getPermisoInvitarTotalSuffix(): string
    {
        return 'invitar_venta';
    }

    /**
     * Confirma la venta como cortesía total en un solo click desde el modal
     * de cobro o el botón "Invitar/Cortesía" de la columna lateral.
     *
     * Llama al trait para marcar todos los items y luego ejecuta el flujo
     * normal de cobro. `procesarVenta()` detecta `esInvitacionCompleta`
     * (`esInvitacionTotal && total_final≤0.005`) y persiste sin VentaPago,
     * sin movimiento de caja, sin cuenta empresa y sin factura fiscal.
     */
    public function confirmarInvitacionTotal(): void
    {
        $this->confirmarInvitarTodo();

        // Si el trait no pudo marcar (sin permiso, motivo vacío), abortamos.
        if (! $this->esInvitacionTotal) {
            return;
        }

        $this->procesarVenta();
    }
}
