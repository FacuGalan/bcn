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
use App\Livewire\Concerns\Carrito\WithOpcionales;
use App\Livewire\Concerns\Carrito\WithPuntos;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConceptoPago;
use App\Models\CuentaEmpresa;
use App\Models\Cupon;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\ListaPrecio;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\TipoCambio;
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
    use WithOpcionales;
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

    // =========================================
    // PROPIEDADES DEL SISTEMA DE PAGOS
    // =========================================

    /** @var bool Modal de pago visible */
    public $mostrarModalPago = false;

    /** @var array Desglose de pagos para venta mixta */
    public $desglosePagos = [];

    /** @var float Monto pendiente por asignar en desglose */
    public $montoPendienteDesglose = 0;

    /** @var float Total con ajustes aplicados */
    public $totalConAjustes = 0;

    /** @var array Forma de pago temporal para agregar al desglose */
    public $nuevoPago = [
        'forma_pago_id' => null,
        'monto' => null,
        'cuotas' => 1,
        'monto_recibido' => 0,
        'tipo_cambio_tasa' => null,
        'monto_moneda_extranjera' => null,
    ];

    /** @var bool Modal simple de pago en moneda extranjera */
    public $mostrarModalMonedaExtranjera = false;

    /** @var array Datos del pago en moneda extranjera (modal simple) */
    public $pagoMonedaExtranjera = [
        'forma_pago_id' => null,
        'nombre' => '',
        'moneda_codigo' => '',
        'moneda_simbolo' => '',
        'moneda_id' => null,
        'cotizacion' => 0,
        'monto_extranjera' => null,
        'total_venta' => 0,
        'equivalente_principal' => 0,
        'vuelto' => 0,
    ];

    /** @var bool Modal de cobro con vuelto (pago simple en moneda local) */
    public $mostrarModalVuelto = false;

    /** @var array Datos del pago con vuelto */
    public $pagoConVuelto = [
        'forma_pago_id' => null,
        'nombre' => '',
        'total_a_pagar' => 0,
        'monto_recibido' => 0,
        'vuelto' => 0,
    ];

    /** @var array Formas de pago disponibles para la sucursal actual (con ajustes) */
    public $formasPagoSucursal = [];

    /** @var array Cuotas disponibles para la forma de pago seleccionada */
    public $cuotasDisponibles = [];

    /** @var array Información del ajuste de la forma de pago seleccionada */
    public $ajusteFormaPagoInfo = [
        'nombre' => '',
        'porcentaje' => 0,
        'monto' => 0,
        'total_con_ajuste' => 0,
        'es_mixta' => false,
    ];

    // =========================================
    // PROPIEDADES DE CUOTAS (SELECTOR PRINCIPAL)
    // =========================================

    /** @var array Cuotas disponibles para la forma de pago seleccionada en el selector principal */
    public $cuotasFormaPagoDisponibles = [];

    /** @var int|null ID de la cuota seleccionada (null = 1 pago sin cuotas) */
    public $cuotaSeleccionadaId = null;

    /** @var bool Indica si la forma de pago seleccionada permite cuotas */
    public $formaPagoPermiteCuotas = false;

    /** @var array Información de la cuota seleccionada */
    public $infoCuotaSeleccionada = [
        'cantidad_cuotas' => 1,
        'recargo_porcentaje' => 0,
        'recargo_monto' => 0,
        'valor_cuota' => 0,
        'total_con_recargo' => 0,
        'descripcion' => '1 pago',
    ];

    /** @var bool Indica si el selector de cuotas está desplegado */
    public $cuotasSelectorAbierto = false;

    /** @var bool Indica si el selector de cuotas del desglose está desplegado */
    public $cuotasDesgloseSelectorAbierto = false;

    /** @var array Cuotas del desglose con montos calculados */
    public $cuotasDesgloseConMontos = [];

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
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
            'puntos_canje' => $articulo->puntos_canje,
            'pagado_con_puntos' => false,
        ];

        // Herencia de descuento general % a items nuevos
        if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'porcentaje') {
            $lastIndex = count($this->items) - 1;
            $precioBase = (float) $precioInfo['precio_base'];
            $nuevoPrecio = round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2);
            $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
            $this->items[$lastIndex]['precio'] = $nuevoPrecio;
            $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
            $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
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
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
        ];

        // RF-34: Herencia de descuento general % a conceptos nuevos
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
    // SISTEMA DE PAGOS CON DESGLOSE
    // =========================================

    /**
     * Carga las formas de pago disponibles para la sucursal con sus ajustes específicos
     */
    protected function cargarFormasPagoSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->formasPagoSucursal = [];

            return;
        }

        $formasPago = FormaPago::with(['conceptoPago', 'conceptosPermitidos', 'cuotas'])
            ->where('activo', true)
            ->orderBy('orden')->orderBy('id')
            ->get();

        $this->formasPagoSucursal = $formasPago->map(function ($fp) {
            // Obtener configuración específica de sucursal
            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $fp->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            // Verificar si está activa en la sucursal
            $activaEnSucursal = $configSucursal ? $configSucursal->activo : true;

            if (! $activaEnSucursal) {
                return null;
            }

            // Obtener ajuste (específico de sucursal o general)
            $ajustePorcentaje = $configSucursal && $configSucursal->ajuste_porcentaje !== null
                ? $configSucursal->ajuste_porcentaje
                : $fp->ajuste_porcentaje;

            // Obtener configuración de factura fiscal (específico de sucursal o general)
            $facturaFiscal = $configSucursal && $configSucursal->factura_fiscal !== null
                ? $configSucursal->factura_fiscal
                : ($fp->factura_fiscal ?? false);

            // Obtener cuotas disponibles para la sucursal
            $cuotasDisponibles = [];
            if ($fp->permite_cuotas && ! $fp->es_mixta) {
                foreach ($fp->cuotas as $cuota) {
                    $cuotaSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                        ->where('sucursal_id', $this->sucursalId)
                        ->first();

                    $activa = $cuotaSucursal ? $cuotaSucursal->activo : true;
                    if (! $activa) {
                        continue;
                    }

                    $recargo = $cuotaSucursal && $cuotaSucursal->recargo_porcentaje !== null
                        ? $cuotaSucursal->recargo_porcentaje
                        : $cuota->recargo_porcentaje;

                    $cuotasDisponibles[] = [
                        'id' => $cuota->id,
                        'cantidad' => $cuota->cantidad_cuotas,
                        'recargo' => $recargo,
                        'descripcion' => $cuota->descripcion,
                    ];
                }
            }

            // Datos de moneda para multi-moneda
            $monedaPrincipal = Moneda::obtenerPrincipal();
            $monedaId = $fp->moneda_id ?? $monedaPrincipal?->id;
            $esMonedaExtranjera = $monedaId && $monedaPrincipal && $monedaId != $monedaPrincipal->id;
            $monedaInfo = null;
            $ultimaTasa = null;

            if ($esMonedaExtranjera) {
                $monedaObj = Moneda::find($monedaId);
                $monedaInfo = $monedaObj ? [
                    'id' => $monedaObj->id,
                    'codigo' => $monedaObj->codigo,
                    'simbolo' => $monedaObj->simbolo,
                    'nombre' => $monedaObj->nombre,
                ] : null;
                $ultimaTasa = TipoCambio::obtenerTasaVenta($monedaId, $monedaPrincipal->id);
            }

            return [
                'id' => $fp->id,
                'nombre' => $fp->nombre,
                'codigo' => $fp->codigo,
                'concepto' => $fp->concepto,
                'concepto_pago_id' => $fp->concepto_pago_id,
                'concepto_nombre' => $fp->conceptoPago?->nombre,
                'es_mixta' => $fp->es_mixta ?? false,
                'permite_cuotas' => $fp->permite_cuotas && ! $fp->es_mixta,
                'ajuste_porcentaje' => $ajustePorcentaje ?? 0,
                'factura_fiscal' => $facturaFiscal,
                'permite_vuelto' => $fp->conceptoPago?->permite_vuelto ?? false,
                'cuotas' => $cuotasDisponibles,
                'conceptos_permitidos' => $fp->es_mixta
                    ? $fp->conceptosPermitidos->map(fn ($c) => [
                        'id' => $c->id,
                        'codigo' => $c->codigo,
                        'nombre' => $c->nombre,
                    ])->toArray()
                    : [],
                'moneda_id' => $monedaId,
                'es_moneda_extranjera' => $esMonedaExtranjera,
                'moneda_info' => $monedaInfo,
                'ultima_tasa' => $ultimaTasa,
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Obtiene el ajuste efectivo para una forma de pago en la sucursal actual
     */
    public function obtenerAjusteFormaPago(int $formaPagoId): float
    {
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', $formaPagoId);

        return $fp ? (float) $fp['ajuste_porcentaje'] : 0;
    }

    // =========================================
    // FACTURACIÓN FISCAL
    // =========================================

    /**
     * Carga la configuración de facturación fiscal de la sucursal actual
     */
    protected function cargarConfiguracionFiscalSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->sucursalFacturaAutomatica = false;

            return;
        }

        $sucursal = Sucursal::find($this->sucursalId);
        $this->sucursalFacturaAutomatica = $sucursal?->facturacion_fiscal_automatica ?? false;
    }

    /**
     * Actualiza el checkbox de factura fiscal según la forma de pago seleccionada
     * Solo aplica si la sucursal NO tiene facturación automática
     */
    public function actualizarFacturaFiscalSegunFP(): void
    {
        if ($this->sucursalFacturaAutomatica) {
            // Si es automática, el checkbox no se usa (se decide internamente)
            return;
        }

        // Cargar formas de pago si no están cargadas
        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        // Si es mixta, no se puede determinar aún (se decide en el desglose)
        if ($fp && ! $fp['es_mixta']) {
            $this->emitirFacturaFiscal = $fp['factura_fiscal'] ?? false;
        }
    }

    /**
     * Obtiene la configuración de factura fiscal de una forma de pago
     */
    public function obtenerFacturaFiscalFP(int $formaPagoId): bool
    {
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', $formaPagoId);

        return $fp ? (bool) ($fp['factura_fiscal'] ?? false) : false;
    }

    /**
     * Toggle del checkbox de factura fiscal en un pago del desglose
     */
    public function toggleFacturaFiscalDesglose(int $index): void
    {
        if (isset($this->desglosePagos[$index])) {
            $this->desglosePagos[$index]['factura_fiscal'] = ! $this->desglosePagos[$index]['factura_fiscal'];
            $this->calcularMontoFacturaFiscal();
        }
    }

    /**
     * Calcula el monto total que se facturará fiscalmente
     * y recalcula el desglose de IVA correspondiente
     */
    public function calcularMontoFacturaFiscal(): void
    {
        // Si es pago simple (no mixto)
        if (empty($this->desglosePagos) || count($this->desglosePagos) <= 1) {
            if ($this->sucursalFacturaAutomatica) {
                // Automática: usar la config de la FP
                $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);
                $emitir = $fp ? ($fp['factura_fiscal'] ?? false) : false;
            } else {
                // Manual: usar el checkbox
                $emitir = $this->emitirFacturaFiscal;
            }

            if ($emitir && $this->resultado) {
                $this->montoFacturaFiscal = $this->resultado['desglose_iva']['total_con_ajuste_fp']
                    ?? $this->resultado['desglose_iva']['total']
                    ?? $this->resultado['total_final']
                    ?? 0;
                // Formatear el desglose para AFIP (2 decimales, iva = neto * porcentaje)
                $this->formatearDesgloseParaAFIP();
            } else {
                $this->montoFacturaFiscal = 0;
                $this->desgloseIvaFiscal = [];
            }

            return;
        }

        // Para pagos mixtos: sumar los montos de las FP con factura_fiscal = true
        $montoFiscal = 0;
        foreach ($this->desglosePagos as $pago) {
            if ($pago['factura_fiscal'] ?? false) {
                $montoFiscal += $pago['monto_final'] ?? $pago['monto_base'] ?? 0;
            }
        }

        $this->montoFacturaFiscal = round($montoFiscal, 2);

        // Recalcular el desglose de IVA proporcionalmente
        if ($this->montoFacturaFiscal > 0 && $this->resultado) {
            $this->recalcularDesgloseIvaFiscal();
        } else {
            $this->desgloseIvaFiscal = [];
        }
    }

    /**
     * Formatea el desglose de IVA para cumplir con AFIP
     * Para pagos simples donde se factura el total (sin prorrateo)
     */
    protected function formatearDesgloseParaAFIP(): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        $desgloseOriginal = $this->resultado['desglose_iva'];

        // Formatear cada alícuota para AFIP
        // IMPORTANTE: IVA debe ser = neto * porcentaje / 100 exactamente
        $porAlicuota = [];
        $totalNeto = 0;
        $totalIva = 0;

        foreach ($desgloseOriginal['por_alicuota'] ?? [] as $alicuota) {
            if (! is_array($alicuota)) {
                continue;
            }

            $porcentaje = $alicuota['alicuota'] ?? $alicuota['porcentaje'] ?? 21;

            // Redondear neto a 2 decimales
            $netoAlicuota = round($alicuota['neto'] ?? 0, 2);

            // AFIP requiere que IVA = neto * porcentaje / 100 exactamente
            $ivaAlicuota = round($netoAlicuota * ($porcentaje / 100), 2);

            $porAlicuota[] = [
                'alicuota' => $porcentaje,
                'neto' => $netoAlicuota,
                'iva' => $ivaAlicuota,
                'subtotal' => round($netoAlicuota + $ivaAlicuota, 2),
            ];

            $totalNeto += $netoAlicuota;
            $totalIva += $ivaAlicuota;
        }

        // Verificar si hay diferencia por redondeo con el total a facturar
        $sumaCalculada = round($totalNeto + $totalIva, 2);
        $diferencia = round($this->montoFacturaFiscal - $sumaCalculada, 2);

        // Si hay diferencia, ajustar el neto de la última alícuota
        if ($diferencia != 0 && ! empty($porAlicuota)) {
            $lastIndex = count($porAlicuota) - 1;
            $porcentajeUltimo = $porAlicuota[$lastIndex]['alicuota'];

            // Ajustar el neto para que neto + iva = total
            $nuevoSubtotal = $porAlicuota[$lastIndex]['subtotal'] + $diferencia;
            $nuevoNeto = round($nuevoSubtotal / (1 + $porcentajeUltimo / 100), 2);
            $nuevoIva = round($nuevoNeto * ($porcentajeUltimo / 100), 2);

            // Recalcular totales
            $totalNeto = $totalNeto - $porAlicuota[$lastIndex]['neto'] + $nuevoNeto;
            $totalIva = $totalIva - $porAlicuota[$lastIndex]['iva'] + $nuevoIva;

            $porAlicuota[$lastIndex]['neto'] = $nuevoNeto;
            $porAlicuota[$lastIndex]['iva'] = $nuevoIva;
            $porAlicuota[$lastIndex]['subtotal'] = round($nuevoNeto + $nuevoIva, 2);
        }

        $this->desgloseIvaFiscal = [
            'por_alicuota' => $porAlicuota,
            'total_neto' => round($totalNeto, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($this->montoFacturaFiscal, 2),
        ];
    }

    /**
     * Recalcula el desglose de IVA para la factura fiscal (proporcional al monto fiscal)
     */
    protected function recalcularDesgloseIvaFiscal(): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        $desgloseOriginal = $this->resultado['desglose_iva'];
        $totalOriginal = $desgloseOriginal['total_con_ajuste_fp']
            ?? $desgloseOriginal['total']
            ?? $this->resultado['total_final']
            ?? 0;

        if ($totalOriginal <= 0) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        // Proporción del monto fiscal sobre el total
        $proporcion = $this->montoFacturaFiscal / $totalOriginal;

        // Recalcular cada alícuota proporcionalmente
        // IMPORTANTE: Para cumplir con AFIP, IVA debe ser = neto * porcentaje / 100
        $porAlicuota = [];
        $totalNeto = 0;
        $totalIva = 0;

        foreach ($desgloseOriginal['por_alicuota'] ?? [] as $alicuota) {
            // Verificar que el array tenga la estructura esperada
            if (! is_array($alicuota)) {
                continue;
            }

            $porcentaje = $alicuota['alicuota'] ?? $alicuota['porcentaje'] ?? 21;

            // Calcular neto proporcional (redondeado a 2 decimales)
            $netoAlicuota = round(($alicuota['neto'] ?? 0) * $proporcion, 2);

            // AFIP requiere que IVA = neto * porcentaje / 100 exactamente
            $ivaAlicuota = round($netoAlicuota * ($porcentaje / 100), 2);

            $porAlicuota[] = [
                'alicuota' => $porcentaje,
                'neto' => $netoAlicuota,
                'iva' => $ivaAlicuota,
                'subtotal' => round($netoAlicuota + $ivaAlicuota, 2),
            ];

            $totalNeto += $netoAlicuota;
            $totalIva += $ivaAlicuota;
        }

        // Verificar si hay diferencia por redondeo con el total a facturar
        $sumaCalculada = round($totalNeto + $totalIva, 2);
        $diferencia = round($this->montoFacturaFiscal - $sumaCalculada, 2);

        // Si hay diferencia, ajustar el neto de la última alícuota
        if ($diferencia != 0 && ! empty($porAlicuota)) {
            $lastIndex = count($porAlicuota) - 1;
            $porcentajeUltimo = $porAlicuota[$lastIndex]['alicuota'];

            // Ajustar el neto para que neto + iva = total
            // Si hay diferencia D, y tenemos neto + iva = subtotal
            // Necesitamos nuevo_neto + nuevo_iva = subtotal + D
            // Con nuevo_iva = nuevo_neto * p/100
            // nuevo_neto * (1 + p/100) = subtotal + D
            // nuevo_neto = (subtotal + D) / (1 + p/100)
            $nuevoSubtotal = $porAlicuota[$lastIndex]['subtotal'] + $diferencia;
            $nuevoNeto = round($nuevoSubtotal / (1 + $porcentajeUltimo / 100), 2);
            $nuevoIva = round($nuevoNeto * ($porcentajeUltimo / 100), 2);

            // Recalcular totales
            $totalNeto = $totalNeto - $porAlicuota[$lastIndex]['neto'] + $nuevoNeto;
            $totalIva = $totalIva - $porAlicuota[$lastIndex]['iva'] + $nuevoIva;

            $porAlicuota[$lastIndex]['neto'] = $nuevoNeto;
            $porAlicuota[$lastIndex]['iva'] = $nuevoIva;
            $porAlicuota[$lastIndex]['subtotal'] = round($nuevoNeto + $nuevoIva, 2);
        }

        $this->desgloseIvaFiscal = [
            'por_alicuota' => $porAlicuota,
            'total_neto' => round($totalNeto, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($this->montoFacturaFiscal, 2),
        ];
    }

    // =========================================
    // SELECCIÓN DE PUNTO DE VENTA FISCAL
    // =========================================

    /**
     * Verifica si el usuario puede y debe seleccionar un punto de venta para facturación
     * Retorna true si:
     * - El usuario tiene el permiso 'func.seleccion_cuit'
     * - La caja actual tiene más de un punto de venta configurado
     */
    protected function debeSeleccionarPuntoVenta(): bool
    {
        $cajaId = $this->cajaSeleccionada ?? caja_activa();
        if (! $cajaId) {
            return false;
        }

        // Verificar permiso del usuario
        $user = Auth::user();
        if (! $user || ! $user->hasPermissionTo('func.seleccion_cuit')) {
            return false;
        }

        // Verificar si la caja tiene múltiples puntos de venta
        $caja = Caja::find($cajaId);
        if (! $caja) {
            return false;
        }

        $cantidadPV = $caja->puntosVenta()->count();

        return $cantidadPV > 1;
    }

    /**
     * Carga los puntos de venta disponibles para la caja actual
     */
    protected function cargarPuntosVentaDisponibles(): void
    {
        $cajaId = $this->cajaSeleccionada ?? caja_activa();
        if (! $cajaId) {
            $this->puntosVentaDisponibles = [];

            return;
        }

        $caja = Caja::find($cajaId);
        if (! $caja) {
            $this->puntosVentaDisponibles = [];

            return;
        }

        // Obtener puntos de venta con información del CUIT
        $puntosVenta = $caja->puntosVenta()
            ->with('cuit')
            ->get()
            ->map(function ($pv) {
                return [
                    'id' => $pv->id,
                    'numero' => $pv->numero,
                    'nombre' => $pv->nombre,
                    'numero_formateado' => str_pad($pv->numero, 5, '0', STR_PAD_LEFT),
                    'cuit_numero' => $pv->cuit?->numero_cuit ?? 'Sin CUIT',
                    'cuit_razon_social' => $pv->cuit?->razon_social ?? '',
                    'es_defecto' => $pv->pivot->es_defecto ?? false,
                ];
            })
            ->toArray();

        $this->puntosVentaDisponibles = $puntosVenta;

        // Preseleccionar el punto de venta por defecto
        $pvDefecto = collect($puntosVenta)->firstWhere('es_defecto', true);
        $this->puntoVentaSeleccionadoId = $pvDefecto['id'] ?? ($puntosVenta[0]['id'] ?? null);
    }

    /**
     * Muestra el modal de selección de punto de venta
     */
    public function mostrarSeleccionPuntoVenta(): void
    {
        $this->cargarPuntosVentaDisponibles();
        $this->showPuntoVentaModal = true;
    }

    /**
     * Confirma la selección del punto de venta y continúa con la venta
     */
    public function confirmarPuntoVenta(): void
    {
        if (! $this->puntoVentaSeleccionadoId) {
            $this->dispatch('toast-error', message: 'Seleccione un punto de venta');

            return;
        }

        $this->showPuntoVentaModal = false;

        // Continuar con el procesamiento de la venta
        $this->procesarVentaConDesglose();
    }

    /**
     * Cancela la selección de punto de venta y vuelve a la venta
     */
    public function cancelarSeleccionPuntoVenta(): void
    {
        // Solo cerrar el modal sin procesar nada
        $this->showPuntoVentaModal = false;
        $this->puntoVentaSeleccionadoId = null;
    }

    /**
     * Inicia el proceso de cobro
     * Para pagos simples: procesa directamente
     * Para pagos mixtos: si hay desglose completo procesa, sino abre modal
     */
    public function iniciarCobro(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: 'El carrito está vacío');

            return;
        }

        if (! $this->formaPagoId) {
            $this->dispatch('toast-error', message: 'Seleccione una forma de pago');

            return;
        }

        // Si es mixta
        if ($this->ajusteFormaPagoInfo['es_mixta']) {
            // Si ya hay un desglose completo, verificar y procesar
            if ($this->desgloseCompleto()) {
                $this->verificarPuntoVentaYProcesar();

                return;
            }
            // Si no, abrir modal para desglosar
            if (! $this->mostrarModalPago) {
                $this->abrirModalDesglose();
            }

            return;
        }

        // Para pagos simples: preparar el desglose y procesar directamente
        $this->cargarFormasPagoSucursal();
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (! $fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');

            return;
        }

        // Si es moneda extranjera, abrir modal simple dedicado
        if ($fp['es_moneda_extranjera'] ?? false) {
            $totalVenta = $this->resultado['total_final'] ?? 0;
            $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
            $montoAjuste = round($totalVenta * ($ajuste / 100), 2);
            $totalConAjuste = round($totalVenta + $montoAjuste, 2);

            $this->pagoMonedaExtranjera = [
                'forma_pago_id' => $fp['id'],
                'nombre' => $fp['nombre'],
                'moneda_codigo' => $fp['moneda_info']['codigo'] ?? '',
                'moneda_simbolo' => $fp['moneda_info']['simbolo'] ?? '',
                'moneda_id' => $fp['moneda_id'],
                'cotizacion' => $fp['ultima_tasa'] ?? 0,
                'monto_extranjera' => null,
                'total_venta' => $totalConAjuste,
                'ajuste_porcentaje' => $ajuste,
                'equivalente_principal' => 0,
                'vuelto' => 0,
            ];
            $this->mostrarModalMonedaExtranjera = true;

            return;
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
        $montoAjuste = $this->ajusteFormaPagoInfo['monto'];
        $montoFinal = $this->ajusteFormaPagoInfo['total_con_ajuste'];

        // Si permite vuelto y NO es cuenta corriente, abrir modal de cobro con vuelto
        $permiteVuelto = $fp['permite_vuelto'] ?? false;
        $esCuentaCorriente = isset($fp['codigo']) && strtoupper($fp['codigo']) === 'CTA_CTE';

        if ($permiteVuelto && ! $esCuentaCorriente) {
            $this->pagoConVuelto = [
                'forma_pago_id' => $fp['id'],
                'nombre' => $fp['nombre'],
                'total_a_pagar' => $montoFinal,
                'monto_recibido' => $montoFinal,
                'vuelto' => 0,
            ];
            $this->mostrarModalVuelto = true;

            return;
        }

        // Obtener información de cuotas si hay seleccionada
        $cantidadCuotas = $this->ajusteFormaPagoInfo['cuotas'] ?? 1;
        $recargoCuotas = $this->ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0;

        // Crear desglose con un solo pago (incluyendo info de cuotas)
        $this->crearDesglosePagoSimple($fp, $totalBase, $ajuste, $montoAjuste, $montoFinal, $cantidadCuotas, $recargoCuotas, $esCuentaCorriente);
    }

    /**
     * Verifica si se debe mostrar el modal de selección de punto de venta
     * antes de procesar la venta. Si no es necesario, procesa directamente.
     */
    protected function verificarPuntoVentaYProcesar(): void
    {
        // Determinar si se va a generar factura fiscal
        $sucursal = Sucursal::find($this->sucursalId);
        if (! $sucursal) {
            $this->procesarVentaConDesglose();

            return;
        }

        $comprobanteFiscalService = new ComprobanteFiscalService;
        $debeFacturarAutomatico = $comprobanteFiscalService->debeGenerarFacturaFiscal($sucursal, $this->desglosePagos);
        $debeFacturarManual = $this->emitirFacturaFiscal;
        $debeFacturarDesglose = collect($this->desglosePagos)->contains('factura_fiscal', true);
        $debeFacturar = $debeFacturarAutomatico || $debeFacturarManual || $debeFacturarDesglose;

        // Si se va a facturar Y el usuario puede seleccionar punto de venta → mostrar modal
        if ($debeFacturar && $this->debeSeleccionarPuntoVenta()) {
            $this->mostrarSeleccionPuntoVenta();

            return;
        }

        // Si no se necesita selección, procesar directamente
        $this->procesarVentaConDesglose();
    }

    /**
     * Resetea el formulario de nuevo pago
     */
    protected function resetNuevoPago(): void
    {
        $this->nuevoPago = [
            'forma_pago_id' => null,
            'monto' => null,
            'cuotas' => 1,
            'monto_recibido' => 0,
            'tipo_cambio_tasa' => null,
            'monto_moneda_extranjera' => null,
        ];
        $this->cuotasDisponibles = [];
        $this->cuotasDesgloseConMontos = [];
        $this->cuotasDesgloseSelectorAbierto = false;
    }

    /**
     * Cuando cambia la forma de pago en el nuevo pago
     */
    public function updatedNuevoPagoFormaPagoId($value): void
    {
        if (! $value) {
            $this->cuotasDisponibles = [];
            $this->cuotasDesgloseConMontos = [];
            $this->cuotasDesgloseSelectorAbierto = false;
            $this->nuevoPago['tipo_cambio_tasa'] = null;
            $this->nuevoPago['monto_moneda_extranjera'] = null;

            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $value);
        $this->cuotasDisponibles = $fp ? $fp['cuotas'] : [];
        $this->nuevoPago['cuotas'] = 1;
        $this->cuotasDesgloseSelectorAbierto = false;

        // Pre-cargar tipo de cambio si es moneda extranjera
        if ($fp && ($fp['es_moneda_extranjera'] ?? false)) {
            $this->nuevoPago['tipo_cambio_tasa'] = $fp['ultima_tasa'];
            $this->nuevoPago['monto_moneda_extranjera'] = null;
        } else {
            $this->nuevoPago['tipo_cambio_tasa'] = null;
            $this->nuevoPago['monto_moneda_extranjera'] = null;
        }

        $this->calcularCuotasDesglose();
    }

    /**
     * Cuando cambia el monto en el nuevo pago, recalcular cuotas
     */
    public function updatedNuevoPagoMonto($value): void
    {
        $this->calcularCuotasDesglose();
    }

    /**
     * Toggle del selector de cuotas del desglose
     */
    public function toggleCuotasDesgloseSelector(): void
    {
        $this->cuotasDesgloseSelectorAbierto = ! $this->cuotasDesgloseSelectorAbierto;
    }

    /**
     * Selecciona una cuota en el desglose
     */
    public function seleccionarCuotaDesglose($cantidadCuotas): void
    {
        $this->nuevoPago['cuotas'] = (int) $cantidadCuotas;
        $this->cuotasDesgloseSelectorAbierto = false;
    }

    /**
     * Calcula las cuotas del desglose con montos basados en el monto ingresado
     */
    protected function calcularCuotasDesglose(): void
    {
        $this->cuotasDesgloseConMontos = [];

        if (empty($this->cuotasDisponibles)) {
            return;
        }

        $monto = (float) ($this->nuevoPago['monto'] ?? 0);
        if ($monto <= 0) {
            $monto = $this->montoPendienteDesglose;
        }

        // Obtener ajuste de la forma de pago
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        $ajusteFp = $fp ? ($fp['ajuste_porcentaje'] ?? 0) : 0;
        $montoConAjusteFp = round($monto + ($monto * $ajusteFp / 100), 2);

        foreach ($this->cuotasDisponibles as $cuota) {
            $cantCuotas = $cuota['cantidad'];
            $recargo = $cuota['recargo'] ?? 0;

            // Calcular recargo sobre el monto con ajuste de forma de pago
            $recargoMonto = round($montoConAjusteFp * ($recargo / 100), 2);
            $totalConRecargo = round($montoConAjusteFp + $recargoMonto, 2);
            $valorCuota = $cantCuotas > 0 ? round($totalConRecargo / $cantCuotas, 2) : 0;

            $this->cuotasDesgloseConMontos[] = [
                'cantidad' => $cantCuotas,
                'recargo' => $recargo,
                'recargo_monto' => $recargoMonto,
                'valor_cuota' => $valorCuota,
                'total_con_recargo' => $totalConRecargo,
                'descripcion' => $cuota['descripcion'] ?? null,
            ];
        }
    }

    /**
     * Agrega una forma de pago al desglose
     */
    public function agregarAlDesglose(): void
    {
        if (! $this->nuevoPago['forma_pago_id']) {
            $this->dispatch('toast-error', message: 'Seleccione una forma de pago');

            return;
        }

        // Si el monto está vacío o es 0, usar el monto pendiente
        $monto = $this->nuevoPago['monto'];
        if ($monto === null || $monto === '' || (float) $monto <= 0) {
            $monto = $this->montoPendienteDesglose;
        }
        $monto = (float) $monto;

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: 'No hay monto pendiente para agregar');

            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        if (! $fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');

            return;
        }

        $permiteVuelto = $fp['permite_vuelto'] ?? false;

        // Multi-moneda: si es moneda extranjera, convertir monto a moneda principal
        $esMonedaExtranjera = $fp['es_moneda_extranjera'] ?? false;
        $tipoCambioTasa = null;
        $montoMonedaOriginal = null;
        $monedaId = $fp['moneda_id'] ?? null;

        if ($esMonedaExtranjera) {
            $tipoCambioTasa = (float) ($this->nuevoPago['tipo_cambio_tasa'] ?? 0);
            if ($tipoCambioTasa <= 0) {
                $this->dispatch('toast-error', message: __('Ingrese la cotización para esta moneda'));

                return;
            }
            // El monto ingresado es en moneda extranjera, convertimos a principal
            $montoMonedaOriginal = $monto;
            $monto = round($monto * $tipoCambioTasa, 2);
        }

        // Validar que no exceda el pendiente (salvo que permita vuelto)
        if ($monto > $this->montoPendienteDesglose + 0.01 && ! $permiteVuelto) {
            $this->dispatch('toast-error', message: __('El monto excede el pendiente'));

            return;
        }

        // Validar que solo haya un pago en Cuenta Corriente
        $esCuentaCorriente = isset($fp['codigo']) && strtoupper($fp['codigo']) === 'CTA_CTE';
        if ($esCuentaCorriente) {
            $yaExisteCC = collect($this->desglosePagos)->contains(function ($pago) {
                $fpExistente = collect($this->formasPagoSucursal)->firstWhere('id', $pago['forma_pago_id']);

                return $fpExistente && isset($fpExistente['codigo']) && strtoupper($fpExistente['codigo']) === 'CTA_CTE';
            });

            if ($yaExisteCC) {
                $this->dispatch('toast-error', message: __('Solo se permite un pago en Cuenta Corriente por venta'));

                return;
            }
        }

        // Si el monto excede el pendiente y permite vuelto, calcular vuelto
        $montoRecibido = null;
        $vuelto = 0;
        $montoParaBase = $monto;

        if ($permiteVuelto && $monto > $this->montoPendienteDesglose + 0.01) {
            // El cliente paga de más: base = pendiente, recibido = lo que paga, vuelto = diferencia
            $montoRecibido = $monto;
            $montoParaBase = $this->montoPendienteDesglose;
            // Si es moneda extranjera, ajustar monto_moneda_original proporcionalmente
            if ($esMonedaExtranjera && $tipoCambioTasa > 0) {
                $montoMonedaOriginal = $montoMonedaOriginal; // mantener lo que entregó en USD
            }
        }

        // Calcular ajuste (sobre monto base en moneda principal)
        $ajuste = $fp['ajuste_porcentaje'];
        $montoAjuste = round($montoParaBase * ($ajuste / 100), 2);
        $montoConAjuste = round($montoParaBase + $montoAjuste, 2);

        // Calcular cuotas si aplica
        $cuotas = (int) ($this->nuevoPago['cuotas'] ?? 1);
        $recargoCuotas = 0;
        $montoFinal = $montoConAjuste;

        if ($cuotas > 1 && $fp['permite_cuotas']) {
            $cuotaConfig = collect($fp['cuotas'])->firstWhere('cantidad', $cuotas);
            if ($cuotaConfig) {
                $recargoCuotas = $cuotaConfig['recargo'];
                $montoRecargoCuotas = round($montoConAjuste * ($recargoCuotas / 100), 2);
                $montoFinal = round($montoConAjuste + $montoRecargoCuotas, 2);
            }
        }

        // Calcular vuelto si pagó de más
        if ($montoRecibido !== null) {
            $vuelto = round($montoRecibido - $montoFinal, 2);
            if ($vuelto < 0) {
                $vuelto = 0;
            }
        } elseif ($permiteVuelto) {
            $montoRecibido = $montoFinal;
        }

        $this->desglosePagos[] = [
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'],
            'monto_base' => $montoParaBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $montoRecibido,
            'vuelto' => $vuelto,
            'permite_vuelto' => $fp['permite_vuelto'],
            'permite_cuotas' => $fp['permite_cuotas'],
            'cuotas_disponibles' => $fp['cuotas'],
            'factura_fiscal' => $fp['factura_fiscal'] ?? false,
            'es_cuenta_corriente' => $esCuentaCorriente,
            'moneda_id' => $monedaId,
            'es_moneda_extranjera' => $esMonedaExtranjera,
            'moneda_info' => $fp['moneda_info'] ?? null,
            'tipo_cambio_tasa' => $tipoCambioTasa,
            'monto_moneda_original' => $montoMonedaOriginal,
        ];

        $this->montoPendienteDesglose = round($this->montoPendienteDesglose - $montoParaBase, 2);
        if ($this->montoPendienteDesglose < 0) {
            $this->montoPendienteDesglose = 0;
        }

        // Recalcular el monto fiscal
        $this->calcularMontoFacturaFiscal();
        $this->recalcularTotalConAjustes();
        $this->resetNuevoPago();

        // Devolver el foco al selector de formas de pago si hay pendiente
        if ($this->montoPendienteDesglose > 0.01) {
            $this->dispatch('focus-busqueda-fp');
        }
    }

    /**
     * Asigna el monto pendiente al nuevo pago
     */
    public function asignarMontoPendiente(): void
    {
        $this->nuevoPago['monto'] = $this->montoPendienteDesglose;
    }

    /**
     * Elimina un pago del desglose
     */
    public function eliminarDelDesglose(int $index): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = $this->desglosePagos[$index];
        $this->montoPendienteDesglose = round($this->montoPendienteDesglose + $pago['monto_base'], 2);

        unset($this->desglosePagos[$index]);
        $this->desglosePagos = array_values($this->desglosePagos);
        $this->recalcularTotalConAjustes();

        // Recalcular el monto fiscal
        $this->calcularMontoFacturaFiscal();
    }

    /**
     * Recalcula el total con todos los ajustes del desglose
     */
    protected function recalcularTotalConAjustes(): void
    {
        $this->totalConAjustes = array_sum(array_column($this->desglosePagos, 'monto_final'));

        // Recalcular el desglose de IVA con los ajustes de pagos mixtos
        $this->recalcularDesgloseIvaMixto();
    }

    /**
     * Recalcula el desglose de IVA basándose en los pagos mixtos
     *
     * Cuando hay pagos mixtos, cada pago puede tener diferente ajuste (descuento/recargo).
     * Esta función distribuye proporcionalmente los ajustes entre las alícuotas de IVA.
     */
    protected function recalcularDesgloseIvaMixto(): void
    {
        // Si no hay desglose de IVA, no hacer nada
        if (! isset($this->resultado['desglose_iva'])) {
            return;
        }

        // Si no hay pagos en el desglose, limpiar valores mixtos existentes
        if (empty($this->desglosePagos)) {
            $this->limpiarDesgloseIvaMixto();

            return;
        }

        $desglose = $this->resultado['desglose_iva'];

        // El total base es la suma de monto_base de todos los pagos (sin ajustes de FP)
        $totalBase = array_sum(array_column($this->desglosePagos, 'monto_base'));

        // El total final es la suma de monto_final (con ajustes de FP y recargos de cuotas)
        $totalFinal = array_sum(array_column($this->desglosePagos, 'monto_final'));

        if ($totalBase <= 0) {
            return;
        }

        // El ajuste total de forma de pago + recargos de cuotas
        $ajusteTotal = $totalFinal - $totalBase;

        // Separar el ajuste de forma de pago del recargo de cuotas para mostrar en el desglose
        $totalAjusteFP = array_sum(array_column($this->desglosePagos, 'monto_ajuste'));
        $totalRecargoCuotas = 0;
        foreach ($this->desglosePagos as $pago) {
            if ($pago['recargo_cuotas'] > 0) {
                $montoConAjuste = $pago['monto_base'] + $pago['monto_ajuste'];
                $totalRecargoCuotas += round($montoConAjuste * ($pago['recargo_cuotas'] / 100), 3);
            }
        }

        // Recalcular cada alícuota con el ajuste proporcional
        $nuevoPorAlicuota = [];
        $totalNetoMixto = 0;
        $totalIvaMixto = 0;

        foreach ($desglose['por_alicuota'] as $alicuota) {
            // La proporción de esta alícuota respecto al total (usando subtotal que incluye IVA)
            // Usamos el subtotal sin descuento de promociones como base proporcional
            $subtotalAlicuota = $alicuota['subtotal'] ?? ($alicuota['neto'] + $alicuota['iva']);
            $proporcion = $subtotalAlicuota / $totalBase;

            // El ajuste proporcional para esta alícuota (el ajuste es sobre montos CON IVA)
            $ajusteAlicuota = round($ajusteTotal * $proporcion, 3);

            // Nuevo subtotal de esta alícuota
            $nuevoSubtotal = round($subtotalAlicuota + $ajusteAlicuota, 3);

            // Calcular nuevo neto e IVA
            // El ajuste "incluye" IVA proporcionalmente, así que dividimos para obtener neto
            if ($alicuota['porcentaje'] > 0) {
                $nuevoNeto = round($nuevoSubtotal / (1 + $alicuota['porcentaje'] / 100), 3);
                $nuevoIva = round($nuevoNeto * ($alicuota['porcentaje'] / 100), 3);
            } else {
                // Para exentos o no gravados
                $nuevoNeto = $nuevoSubtotal;
                $nuevoIva = 0;
            }

            $totalNetoMixto += $nuevoNeto;
            $totalIvaMixto += $nuevoIva;

            // Mantener los valores originales y agregar los de pago mixto
            $nuevaAlicuota = $alicuota;
            $nuevaAlicuota['neto_mixto'] = $nuevoNeto;
            $nuevaAlicuota['iva_mixto'] = $nuevoIva;
            $nuevaAlicuota['subtotal_mixto'] = round($nuevoNeto + $nuevoIva, 3);

            $nuevoPorAlicuota[] = $nuevaAlicuota;
        }

        // Actualizar el desglose con los nuevos valores de pago mixto
        $this->resultado['desglose_iva']['por_alicuota'] = $nuevoPorAlicuota;
        $this->resultado['desglose_iva']['ajuste_forma_pago_mixto'] = round($totalAjusteFP, 3);
        $this->resultado['desglose_iva']['recargo_cuotas_mixto'] = round($totalRecargoCuotas, 3);
        $this->resultado['desglose_iva']['total_neto_mixto'] = round($totalNetoMixto, 3);
        $this->resultado['desglose_iva']['total_iva_mixto'] = round($totalIvaMixto, 3);
        $this->resultado['desglose_iva']['total_mixto'] = round($totalNetoMixto + $totalIvaMixto, 3);
    }

    /**
     * Actualiza las cuotas de un pago en el desglose
     */
    public function actualizarCuotasDesglose(int $index, int $cuotas): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = &$this->desglosePagos[$index];

        if (! $pago['permite_cuotas'] || $cuotas < 1) {
            return;
        }

        $pago['cuotas'] = $cuotas;
        $montoConAjuste = $pago['monto_base'] + $pago['monto_ajuste'];

        if ($cuotas > 1) {
            $cuotaConfig = collect($pago['cuotas_disponibles'])->firstWhere('cantidad', $cuotas);
            if ($cuotaConfig) {
                $pago['recargo_cuotas'] = $cuotaConfig['recargo'];
                $montoRecargo = round($montoConAjuste * ($cuotaConfig['recargo'] / 100), 2);
                $pago['monto_final'] = round($montoConAjuste + $montoRecargo, 2);
            }
        } else {
            $pago['recargo_cuotas'] = 0;
            $pago['monto_final'] = $montoConAjuste;
        }

        if ($pago['permite_vuelto']) {
            $pago['monto_recibido'] = $pago['monto_final'];
            $pago['vuelto'] = 0;
        }

        $this->recalcularTotalConAjustes();
    }

    /**
     * Actualiza el monto recibido y calcula el vuelto
     */
    public function actualizarMontoRecibido(int $index, $monto): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = &$this->desglosePagos[$index];
        $montoRecibido = (float) $monto;
        $pago['monto_recibido'] = $montoRecibido;
        $pago['vuelto'] = max(0, round($montoRecibido - $pago['monto_final'], 2));
    }

    /**
     * Cierra el modal de pago
     * Si el desglose está completo, mantiene los totales calculados
     */
    public function cerrarModalPago(): void
    {
        // Si el desglose está completo, actualizar el ajusteFormaPagoInfo con los totales
        if ($this->desgloseCompleto() && ! empty($this->desglosePagos)) {
            $totalBase = $this->resultado['total_final'] ?? 0;
            $totalConAjustes = $this->totalConAjustes;
            $montoAjuste = $totalConAjustes - $totalBase;

            $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
            $this->ajusteFormaPagoInfo['total_con_ajuste'] = $totalConAjustes;
        }

        $this->mostrarModalPago = false;
        // No limpiar el desglose si está completo (para poder procesar después)
        if (! $this->desgloseCompleto()) {
            $this->desglosePagos = [];
            $this->montoPendienteDesglose = 0;
            $this->totalConAjustes = 0;
            // Limpiar valores mixtos del desglose de IVA
            $this->limpiarDesgloseIvaMixto();
        }
        $this->resetNuevoPago();
    }

    // =========================================
    // MODAL SIMPLE DE MONEDA EXTRANJERA
    // =========================================

    /**
     * Actualiza el cálculo en vivo del modal de moneda extranjera
     */
    public function updatedPagoMonedaExtranjeraMontoExtranjera($value): void
    {
        $this->calcularEquivalenteMonedaExtranjera();
    }

    public function updatedPagoMonedaExtranjeraCotizacion($value): void
    {
        $this->calcularEquivalenteMonedaExtranjera();
    }

    protected function calcularEquivalenteMonedaExtranjera(): void
    {
        $monto = (float) ($this->pagoMonedaExtranjera['monto_extranjera'] ?? 0);
        $cotizacion = (float) ($this->pagoMonedaExtranjera['cotizacion'] ?? 0);
        $totalVenta = (float) ($this->pagoMonedaExtranjera['total_venta'] ?? 0);

        if ($monto > 0 && $cotizacion > 0) {
            $equivalente = round($monto * $cotizacion, 2);
            $this->pagoMonedaExtranjera['equivalente_principal'] = $equivalente;
            $this->pagoMonedaExtranjera['vuelto'] = max(0, round($equivalente - $totalVenta, 2));
        } else {
            $this->pagoMonedaExtranjera['equivalente_principal'] = 0;
            $this->pagoMonedaExtranjera['vuelto'] = 0;
        }
    }

    /**
     * Confirma el pago en moneda extranjera y crea el desglose
     */
    public function confirmarPagoMonedaExtranjera(): void
    {
        $monto = (float) ($this->pagoMonedaExtranjera['monto_extranjera'] ?? 0);
        $cotizacion = (float) ($this->pagoMonedaExtranjera['cotizacion'] ?? 0);
        $totalVenta = (float) ($this->pagoMonedaExtranjera['total_venta'] ?? 0);
        $ajuste = (float) ($this->pagoMonedaExtranjera['ajuste_porcentaje'] ?? 0);

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese el monto en moneda extranjera'));

            return;
        }
        if ($cotizacion <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese la cotización'));

            return;
        }

        $equivalente = round($monto * $cotizacion, 2);
        if ($equivalente < $totalVenta - 0.01) {
            $this->dispatch('toast-error', message: __('El monto es insuficiente para cubrir la venta'));

            return;
        }

        $vuelto = max(0, round($equivalente - $totalVenta, 2));
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->pagoMonedaExtranjera['forma_pago_id']);

        if (! $fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));

            return;
        }

        // Calcular base sin ajuste para registro correcto
        $totalBase = $this->resultado['total_final'] ?? 0;
        $montoAjuste = round($totalBase * ($ajuste / 100), 2);

        $this->desglosePagos = [[
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'] ?? null,
            'monto_base' => $totalBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $totalVenta,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'monto_recibido' => $equivalente,
            'vuelto' => $vuelto,
            'factura_fiscal' => $this->sucursalFacturaAutomatica
                ? ($fp['factura_fiscal'] ?? false)
                : $this->emitirFacturaFiscal,
            'es_cuenta_corriente' => false,
            'moneda_id' => $this->pagoMonedaExtranjera['moneda_id'],
            'es_moneda_extranjera' => true,
            'moneda_info' => $fp['moneda_info'] ?? null,
            'tipo_cambio_tasa' => $cotizacion,
            'monto_moneda_original' => $monto,
        ]];

        $this->totalConAjustes = $totalVenta;
        $this->montoPendienteDesglose = 0;
        $this->mostrarModalMonedaExtranjera = false;

        $this->calcularMontoFacturaFiscal();
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Cierra el modal de moneda extranjera sin confirmar
     */
    public function cerrarModalMonedaExtranjera(): void
    {
        $this->mostrarModalMonedaExtranjera = false;
    }

    // =========================================
    // MODAL DE COBRO CON VUELTO (MONEDA LOCAL)
    // =========================================

    /**
     * Actualiza el cálculo de vuelto en vivo
     */
    public function updatedPagoConVueltoMontoRecibido($value): void
    {
        $monto = (float) ($value ?? 0);
        $total = (float) ($this->pagoConVuelto['total_a_pagar'] ?? 0);
        $this->pagoConVuelto['vuelto'] = max(0, round($monto - $total, 2));
    }

    /**
     * Confirma el pago con vuelto y procesa la venta
     */
    public function confirmarPagoConVuelto(): void
    {
        $montoRecibido = (float) ($this->pagoConVuelto['monto_recibido'] ?? 0);
        $totalAPagar = (float) ($this->pagoConVuelto['total_a_pagar'] ?? 0);

        if ($montoRecibido < $totalAPagar - 0.01) {
            $this->dispatch('toast-error', message: __('El monto recibido es insuficiente'));

            return;
        }

        $vuelto = max(0, round($montoRecibido - $totalAPagar, 2));

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->pagoConVuelto['forma_pago_id']);
        if (! $fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));

            return;
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
        $montoAjuste = $this->ajusteFormaPagoInfo['monto'];
        $cantidadCuotas = $this->ajusteFormaPagoInfo['cuotas'] ?? 1;
        $recargoCuotas = $this->ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0;

        $this->crearDesglosePagoSimple($fp, $totalBase, $ajuste, $montoAjuste, $totalAPagar, $cantidadCuotas, $recargoCuotas, false, $montoRecibido, $vuelto);
        $this->mostrarModalVuelto = false;
    }

    /**
     * Cierra el modal de vuelto sin confirmar
     */
    public function cerrarModalVuelto(): void
    {
        $this->mostrarModalVuelto = false;
    }

    /**
     * Crea el desglose de pago simple y procesa la venta
     */
    protected function crearDesglosePagoSimple(
        array $fp,
        float $totalBase,
        float $ajuste,
        float $montoAjuste,
        float $montoFinal,
        int $cantidadCuotas,
        float $recargoCuotas,
        bool $esCuentaCorriente,
        ?float $montoRecibido = null,
        float $vuelto = 0
    ): void {
        $this->desglosePagos = [[
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'] ?? null,
            'monto_base' => $totalBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cantidadCuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $montoRecibido,
            'vuelto' => $vuelto,
            'factura_fiscal' => $this->sucursalFacturaAutomatica
                ? ($fp['factura_fiscal'] ?? false)
                : $this->emitirFacturaFiscal,
            'es_cuenta_corriente' => $esCuentaCorriente,
        ]];

        $this->totalConAjustes = $montoFinal;
        $this->montoPendienteDesglose = 0;

        $this->calcularMontoFacturaFiscal();
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Limpia los valores de pago mixto del desglose de IVA
     */
    protected function limpiarDesgloseIvaMixto(): void
    {
        if (! isset($this->resultado['desglose_iva'])) {
            return;
        }

        // Eliminar valores mixtos de cada alícuota
        if (isset($this->resultado['desglose_iva']['por_alicuota'])) {
            foreach ($this->resultado['desglose_iva']['por_alicuota'] as &$alicuota) {
                unset($alicuota['neto_mixto']);
                unset($alicuota['iva_mixto']);
                unset($alicuota['subtotal_mixto']);
            }
        }

        // Eliminar totales mixtos
        unset($this->resultado['desglose_iva']['ajuste_forma_pago_mixto']);
        unset($this->resultado['desglose_iva']['recargo_cuotas_mixto']);
        unset($this->resultado['desglose_iva']['total_neto_mixto']);
        unset($this->resultado['desglose_iva']['total_iva_mixto']);
        unset($this->resultado['desglose_iva']['total_mixto']);
    }

    /**
     * Verifica si el desglose está completo y listo para procesar
     */
    public function desgloseCompleto(): bool
    {
        // Debe haber al menos un pago
        if (empty($this->desglosePagos)) {
            return false;
        }

        // No debe quedar monto pendiente (tolerancia de 0.01)
        if ($this->montoPendienteDesglose > 0.01) {
            return false;
        }

        return true;
    }

    /**
     * Confirma el desglose y cierra el modal (NO procesa la venta)
     * La venta se procesa con el botón "Cobrar" de la vista principal
     */
    public function confirmarPago(): void
    {
        if (! $this->desgloseCompleto()) {
            $this->dispatch('toast-error', message: 'Complete el desglose de pagos');

            return;
        }

        // Actualizar ajusteFormaPagoInfo con los totales del desglose
        $totalBase = $this->resultado['total_final'] ?? 0;
        $montoAjuste = $this->totalConAjustes - $totalBase;

        $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
        $this->ajusteFormaPagoInfo['total_con_ajuste'] = $this->totalConAjustes;

        // Cerrar modal manteniendo el desglose
        $this->mostrarModalPago = false;

        $this->dispatch('toast-success', message: 'Desglose confirmado. Haga clic en Cobrar para finalizar.');
    }

    // =========================================
    // PROCESAMIENTO DE VENTA
    // =========================================

    /**
     * Procesa la venta con el desglose de pagos
     *
     * Flujo completo:
     * 1. Validaciones previas
     * 2. Crear la venta con todos los campos de contexto
     * 3. Guardar desglose de pagos con nuevos campos
     * 4. Registrar movimientos de caja
     * 5. Verificar si debe generar factura fiscal
     * 6. Si corresponde, emitir comprobante fiscal via ARCA
     * 7. Actualizar campos de cuenta corriente si aplica
     */
    protected function procesarVentaConDesglose(): void
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

            $cajaId = $this->cajaSeleccionada ?? caja_activa();

            // Validar formas de pago del cupón (Opción C: 100% formas válidas)
            if ($this->cuponAplicado && $this->cuponInfo) {
                $cuponValidar = Cupon::find($this->cuponInfo['id']);
                if ($cuponValidar && $cuponValidar->tieneRestriccionFormasPago()) {
                    $fpIds = collect($this->desglosePagos)->pluck('forma_pago_id')->unique()->toArray();
                    $validacionFP = $this->cuponService->validarFormasPagoCupon($cuponValidar, $fpIds);
                    if (! $validacionFP['valid']) {
                        $this->dispatch('toast-error', message: $validacionFP['message']);

                        return;
                    }
                }
            }

            // Verificar si hay pagos a cuenta corriente (usar el flag o verificar código)
            $tieneCuentaCorriente = false;
            $montoCuentaCorriente = 0;

            foreach ($this->desglosePagos as $pago) {
                // Usar el flag si existe, sino verificar por código
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }

                if ($esCC) {
                    $tieneCuentaCorriente = true;
                    $montoCuentaCorriente += $pago['monto_final'];

                    // Cuenta corriente requiere cliente
                    if (! $this->clienteSeleccionado) {
                        $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');

                        return;
                    }

                    // Verificar que el cliente tiene cuenta corriente habilitada
                    $cliente = Cliente::find($this->clienteSeleccionado);
                    if (! $cliente || ! $cliente->tiene_cuenta_corriente) {
                        $this->dispatch('toast-error', message: 'El cliente no tiene cuenta corriente habilitada');

                        return;
                    }

                    // Verificar límite de crédito
                    $nuevoSaldo = $cliente->saldo_deudor_cache + $montoCuentaCorriente;
                    if ($cliente->limite_credito > 0 && $nuevoSaldo > $cliente->limite_credito) {
                        $this->dispatch('toast-error', message: 'El cliente excede su límite de crédito');

                        return;
                    }
                }
            }

            // Verificar caja para pagos que la requieren (CC no requiere caja)
            $requiereCaja = false;
            foreach ($this->desglosePagos as $pago) {
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }
                if (! $esCC) {
                    $requiereCaja = true;
                    break;
                }
            }

            if ($requiereCaja && ! $cajaId) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja');

                return;
            }

            // Verificar caja abierta
            if ($cajaId) {
                $caja = Caja::find($cajaId);
                if (! $caja || ! $caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: 'La caja debe estar abierta');

                    return;
                }
            }

            // Determinar forma de pago principal
            $formaPagoPrincipalId = $this->formaPagoId;
            if (count($this->desglosePagos) > 1) {
                $formaMixta = FormaPago::where('es_mixta', true)->where('activo', true)->first();
                $formaPagoPrincipalId = $formaMixta?->id ?? $this->desglosePagos[0]['forma_pago_id'];
            }

            $formaPagoPrincipal = FormaPago::find($formaPagoPrincipalId);
            $formaPagoCodigo = $formaPagoPrincipal?->concepto ?? 'efectivo';
            if ($formaPagoPrincipal?->es_mixta) {
                $formaPagoCodigo = 'mixto';
            }

            // Verificar si debe generar factura fiscal
            // Se factura si:
            // 1. Automático: sucursal.facturacion_fiscal_automatica = true Y alguna forma de pago tiene factura_fiscal = true
            // 2. Manual: el usuario marcó el checkbox emitirFacturaFiscal
            // 3. Desglose: algún pago en el desglose tiene factura_fiscal = true
            $comprobanteFiscalService = new ComprobanteFiscalService;
            $debeFacturarAutomatico = $comprobanteFiscalService->debeGenerarFacturaFiscal($sucursal, $this->desglosePagos);
            $debeFacturarManual = $this->emitirFacturaFiscal;
            $debeFacturarDesglose = collect($this->desglosePagos)->contains('factura_fiscal', true);
            $debeFacturar = $debeFacturarAutomatico || $debeFacturarManual || $debeFacturarDesglose;

            DB::connection('pymes_tenant')->beginTransaction();

            try {
                // Obtener desglose de IVA calculado
                $desgloseIva = $this->resultado['desglose_iva'] ?? [];

                // Calcular totales incluyendo ajuste de forma de pago
                $subtotal = $this->resultado['subtotal'] ?? 0;
                $descuentoPromociones = $this->resultado['total_descuentos'] ?? 0;
                $totalAntesAjusteFP = $this->resultado['total_final'] ?? 0;

                // Calcular ajuste de forma de pago (suma de monto_ajuste de todos los pagos)
                $totalAjusteFP = array_sum(array_column($this->desglosePagos, 'monto_ajuste'));
                $totalFinal = array_sum(array_column($this->desglosePagos, 'monto_final'));

                // Preparar datos de la venta con totales ya calculados
                $datosVenta = [
                    'sucursal_id' => $this->sucursalId,
                    'cliente_id' => $this->clienteSeleccionado,
                    'caja_id' => $cajaId,
                    'usuario_id' => Auth::id(),
                    'forma_pago_id' => $formaPagoPrincipalId,
                    'forma_venta_id' => $this->formaVentaId,
                    'canal_venta_id' => $this->canalVentaId,
                    'lista_precio_id' => $this->listaPrecioId,
                    'observaciones' => $this->observaciones,
                    // Totales ya calculados (no recalcular)
                    'subtotal' => $subtotal,
                    'descuento' => $descuentoPromociones, // Solo descuentos de promociones
                    'total' => $totalAntesAjusteFP, // Total después de promociones, antes de ajuste FP
                    'ajuste_forma_pago' => $totalAjusteFP, // Suma de ajustes de formas de pago
                    'total_final' => $totalFinal,   // Total real cobrado (con ajuste FP)
                    'iva' => $desgloseIva['total_iva'] ?? 0,
                    // Campos de cuenta corriente
                    'es_cuenta_corriente' => $tieneCuentaCorriente,
                    'saldo_pendiente_cache' => $montoCuentaCorriente,
                    'fecha_vencimiento' => $tieneCuentaCorriente
                        ? now()->addDays($cliente->dias_credito ?? 30)->toDateString()
                        : null,
                    // Flag para indicar que no debe recalcular
                    '_usar_totales_proporcionados' => true,
                    // Promociones aplicadas para guardar en tablas de promociones
                    '_promociones_comunes' => $this->resultado['promociones_comunes_aplicadas'] ?? [],
                    '_promociones_especiales' => $this->resultado['promociones_especiales_aplicadas'] ?? [],
                    // Descuento general (RF-38)
                    'descuento_general_tipo' => $this->descuentoGeneralActivo ? $this->descuentoGeneralTipo : null,
                    'descuento_general_valor' => $this->descuentoGeneralActivo ? $this->descuentoGeneralValor : null,
                    'descuento_general_monto' => $this->descuentoGeneralMonto,
                    // Cupón (RF-19)
                    'cupon_id' => $this->cuponAplicado && $this->cuponInfo ? $this->cuponInfo['id'] : null,
                    'monto_cupon' => $this->cuponMontoDescuento,
                    // Puntos (RF-09)
                    'puntos_usados' => $this->canjePuntosActivo ? $this->canjePuntosUnidades : 0,
                ];

                // Calcular descuento cupón por item para trazabilidad
                $descuentoCuponPorItem = $this->calcularDescuentoCuponPorItem();

                // Construir detalles con información de promociones
                $detalles = [];
                foreach ($this->items as $index => $item) {
                    $itemResultado = $this->resultado['items'][$index] ?? [];
                    $descuentoPromocion = $itemResultado['descuento_comun'] ?? 0;
                    $promocionesComunes = $itemResultado['promociones_comunes'] ?? [];
                    $promocionesEspeciales = $itemResultado['promociones_especiales'] ?? [];
                    $tienePromocion = ! empty($promocionesComunes) || ! empty($promocionesEspeciales);
                    $esConcepto = (bool) ($item['es_concepto'] ?? false);

                    $detalles[] = [
                        'articulo_id' => $item['articulo_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'precio_lista' => $item['precio_base'] ?? $item['precio'],
                        'lista_precio_id' => $esConcepto ? null : $this->listaPrecioId, // Conceptos no usan lista de precios
                        'descuento' => 0, // Descuento manual (no promoción)
                        'descuento_promocion' => $esConcepto ? 0 : $descuentoPromocion, // Conceptos no tienen promociones
                        'descuento_cupon' => $descuentoCuponPorItem[$index] ?? 0,
                        'tiene_promocion' => $esConcepto ? false : $tienePromocion,
                        // Info de IVA del item
                        'tipo_iva_id' => $this->resolverTipoIvaId($item),
                        'iva_porcentaje' => $item['iva_porcentaje'] ?? 21,
                        'precio_iva_incluido' => $item['precio_iva_incluido'] ?? true,
                        // Info de ajuste manual si existe
                        'ajuste_manual_tipo' => $item['ajuste_manual_tipo'] ?? null,
                        'ajuste_manual_valor' => $item['ajuste_manual_valor'] ?? null,
                        'precio_sin_ajuste_manual' => $item['precio_sin_ajuste_manual'] ?? null,
                        // Opcionales seleccionados (conceptos no tienen opcionales)
                        'opcionales' => $esConcepto ? [] : ($item['opcionales'] ?? []),
                        'precio_opcionales' => $esConcepto ? 0 : ($item['precio_opcionales'] ?? 0),
                        // Info de promociones para guardar en venta_detalle_promociones
                        '_promociones_item' => $esConcepto ? [] : [
                            'promociones_comunes' => $promocionesComunes,
                            'promociones_especiales' => $promocionesEspeciales,
                        ],
                        // Canje por puntos (RF-10) — conceptos no se pagan con puntos
                        'pagado_con_puntos' => $esConcepto ? false : ($item['pagado_con_puntos'] ?? false),
                        'puntos_usados' => $esConcepto || ! ($item['pagado_con_puntos'] ?? false)
                            ? 0
                            : $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0)) * (float) ($item['cantidad'] ?? 1),
                        // Concepto libre
                        'es_concepto' => $esConcepto,
                        'concepto_descripcion' => $esConcepto ? ($item['nombre'] ?? null) : null,
                        'concepto_categoria_id' => $esConcepto ? ($item['categoria_id'] ?? null) : null,
                    ];
                }

                // Crear la venta
                $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

                // Guardar desglose de pagos con nuevos campos
                $pagosCreados = []; // Mapeo de índice => VentaPago ID para facturación parcial
                foreach ($this->desglosePagos as $index => $pago) {
                    $fp = FormaPago::find($pago['forma_pago_id']);
                    $esCuentaCorriente = $fp && strtoupper($fp->codigo) === 'CTA_CTE';

                    // Determinar si es pago en efectivo (solo efectivo afecta la caja física)
                    $conceptoPago = null;
                    if (! empty($pago['concepto_pago_id'])) {
                        $conceptoPago = ConceptoPago::find($pago['concepto_pago_id']);
                    } elseif ($fp && $fp->concepto_pago_id) {
                        $conceptoPago = $fp->conceptoPago;
                    }
                    $esEfectivo = $conceptoPago && $conceptoPago->esEfectivo();

                    // Solo afecta la caja física si es efectivo
                    $afectaCaja = $esEfectivo && $cajaId && ! $esCuentaCorriente;

                    // Crear movimiento de caja SOLO si es efectivo
                    $movimientoCajaId = null;
                    if ($afectaCaja) {
                        $caja = Caja::find($cajaId);
                        $vuelto = (float) ($pago['vuelto'] ?? 0);
                        $esMonedaExtranjera = ! empty($pago['es_moneda_extranjera']) && ! empty($pago['tipo_cambio_tasa']);

                        if ($esMonedaExtranjera && $vuelto > 0) {
                            // Moneda extranjera con vuelto: ingreso por el TOTAL recibido + egreso por vuelto
                            $montoRecibido = (float) ($pago['monto_recibido'] ?? $pago['monto_final']);
                            $movimiento = MovimientoCaja::crearIngresoVenta($caja, $venta, $montoRecibido, Auth::id());

                            $tcRecord = TipoCambio::ultimaTasa($pago['moneda_id'], Moneda::obtenerPrincipal()?->id);
                            $movimiento->update([
                                'moneda_id' => $pago['moneda_id'],
                                'monto_moneda_original' => $pago['monto_moneda_original'],
                                'tipo_cambio_id' => $tcRecord?->id,
                            ]);

                            // Egreso por el vuelto entregado
                            MovimientoCaja::create([
                                'caja_id' => $caja->id,
                                'tipo' => MovimientoCaja::TIPO_EGRESO,
                                'concepto' => "Vuelto Venta #{$venta->numero}",
                                'monto' => $vuelto,
                                'usuario_id' => Auth::id(),
                                'referencia_tipo' => MovimientoCaja::REF_VUELTO_VENTA,
                                'referencia_id' => $venta->id,
                            ]);
                        } else {
                            // Caso normal: sin vuelto o misma moneda
                            $movimiento = MovimientoCaja::crearIngresoVenta($caja, $venta, $pago['monto_final'], Auth::id());

                            if ($esMonedaExtranjera) {
                                $tcRecord = TipoCambio::ultimaTasa($pago['moneda_id'], Moneda::obtenerPrincipal()?->id);
                                $movimiento->update([
                                    'moneda_id' => $pago['moneda_id'],
                                    'monto_moneda_original' => $pago['monto_moneda_original'],
                                    'tipo_cambio_id' => $tcRecord?->id,
                                ]);
                            }
                        }

                        $movimientoCajaId = $movimiento->id;

                        // Actualizar saldo de caja (siempre neto en moneda principal)
                        $caja->aumentarSaldo($pago['monto_final']);
                    }

                    // Obtener moneda de la forma de pago
                    $fpMonedaId = null;
                    $fpObj = FormaPago::find($pago['forma_pago_id']);
                    if ($fpObj) {
                        $fpMonedaId = $fpObj->moneda_id;
                    }

                    $ventaPago = VentaPago::create([
                        'venta_id' => $venta->id,
                        'forma_pago_id' => $pago['forma_pago_id'],
                        'concepto_pago_id' => $pago['concepto_pago_id'],
                        'monto_base' => $pago['monto_base'],
                        'ajuste_porcentaje' => $pago['ajuste_porcentaje'],
                        'monto_ajuste' => $pago['monto_ajuste'],
                        'monto_final' => $pago['monto_final'],
                        'monto_recibido' => $pago['monto_recibido'],
                        'vuelto' => $pago['vuelto'] ?? 0,
                        'cuotas' => $pago['cuotas'] > 1 ? $pago['cuotas'] : null,
                        'recargo_cuotas_porcentaje' => $pago['cuotas'] > 1 ? $pago['recargo_cuotas'] : null,
                        'recargo_cuotas_monto' => $pago['cuotas'] > 1
                            ? round(($pago['monto_base'] + $pago['monto_ajuste']) * ($pago['recargo_cuotas'] / 100), 2)
                            : null,
                        'monto_cuota' => $pago['cuotas'] > 1
                            ? round($pago['monto_final'] / $pago['cuotas'], 2)
                            : null,
                        'es_cuenta_corriente' => $esCuentaCorriente,
                        'afecta_caja' => $afectaCaja,
                        'estado' => 'activo',
                        'movimiento_caja_id' => $movimientoCajaId,
                        'moneda_id' => $pago['moneda_id'] ?? $fpMonedaId ?? Moneda::obtenerPrincipal()?->id,
                        'monto_moneda_original' => $pago['monto_moneda_original'] ?? null,
                        'tipo_cambio_tasa' => $pago['tipo_cambio_tasa'] ?? null,
                    ]);

                    // Si la forma de pago tiene cuenta empresa vinculada, registrar movimiento
                    if (! $esCuentaCorriente) {
                        $fpVinculada = FormaPago::find($pago['forma_pago_id']);
                        if ($fpVinculada && $fpVinculada->cuenta_empresa_id) {
                            try {
                                $movCuenta = CuentaEmpresaService::registrarMovimientoAutomatico(
                                    CuentaEmpresa::find($fpVinculada->cuenta_empresa_id),
                                    'ingreso', $pago['monto_final'], 'venta',
                                    'VentaPago', $ventaPago->id,
                                    "Venta #{$venta->numero} - {$fpVinculada->nombre}",
                                    Auth::id(), sucursal_activa()
                                );
                                $ventaPago->update(['movimiento_cuenta_empresa_id' => $movCuenta->id]);
                            } catch (\Exception $e) {
                                Log::warning('Error al registrar movimiento en cuenta empresa', ['error' => $e->getMessage()]);
                            }
                        }
                    }

                    // Guardar ID y si requiere factura fiscal para usarlo después
                    $pagosCreados[$index] = [
                        'id' => $ventaPago->id,
                        'monto_final' => $ventaPago->monto_final,
                        'factura_fiscal' => $pago['factura_fiscal'] ?? false,
                    ];
                }

                // Generar comprobante fiscal si corresponde
                $comprobanteFiscal = null;
                if ($debeFacturar) {
                    try {
                        // Filtrar pagos creados que tienen factura_fiscal = true
                        // Ahora usamos los IDs reales de VentaPago
                        $pagosConFactura = array_filter($pagosCreados, fn ($p) => $p['factura_fiscal'] ?? false);
                        $opcionesFiscal = [];

                        // Si hay pagos específicos con factura fiscal, pasar para facturación parcial
                        if (! empty($pagosConFactura)) {
                            $opcionesFiscal['pagos_facturar'] = array_values($pagosConFactura);

                            Log::info('Facturación parcial - pagos con factura fiscal', [
                                'venta_id' => $venta->id,
                                'total_pagos_creados' => count($pagosCreados),
                                'pagos_con_factura' => count($pagosConFactura),
                                'pagos_facturar' => $opcionesFiscal['pagos_facturar'],
                            ]);
                        }

                        // Pasar el desglose de IVA ya calculado (con proporciones correctas)
                        if (! empty($this->desgloseIvaFiscal)) {
                            $opcionesFiscal['desglose_iva'] = $this->desgloseIvaFiscal;
                            $opcionesFiscal['total_a_facturar'] = $this->montoFacturaFiscal;
                        }

                        // Pasar el punto de venta seleccionado si el usuario eligió uno
                        if ($this->puntoVentaSeleccionadoId) {
                            $puntoVentaSeleccionado = PuntoVenta::with('cuit')->find($this->puntoVentaSeleccionadoId);
                            if ($puntoVentaSeleccionado) {
                                $opcionesFiscal['punto_venta'] = $puntoVentaSeleccionado;
                            }
                        }

                        $comprobanteFiscal = $comprobanteFiscalService->crearComprobanteFiscal($venta, $opcionesFiscal);

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
            Log::error('Error al procesar venta con desglose', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: '.$e->getMessage());
        }
    }

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

            // Validar cliente si es cuenta corriente
            if ($esCuentaCorriente) {
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
            if (! $esCuentaCorriente) {
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
            $debeFacturar = $debeFacturarAutomatico || $debeFacturarManual;

            DB::connection('pymes_tenant')->beginTransaction();

            try {
                // Preparar datos de la venta
                $datosVenta = [
                    'sucursal_id' => $this->sucursalId,
                    'cliente_id' => $this->clienteSeleccionado,
                    'caja_id' => $cajaId,
                    'usuario_id' => Auth::id(),
                    'forma_pago_id' => $this->formaPagoId,
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
                    // Cupón (RF-19)
                    'cupon_id' => $this->cuponAplicado && $this->cuponInfo ? $this->cuponInfo['id'] : null,
                    'monto_cupon' => $this->cuponMontoDescuento,
                    // Puntos (RF-09)
                    'puntos_usados' => $this->canjePuntosActivo ? $this->canjePuntosUnidades : 0,
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
                    ];
                }

                $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

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

                // Si la forma de pago tiene cuenta empresa vinculada, registrar movimiento
                if (! $esCuentaCorriente && $formaPago && $formaPago->cuenta_empresa_id) {
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
}
