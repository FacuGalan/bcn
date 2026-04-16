<?php

namespace App\Livewire\Ventas;

use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConceptoPago;
use App\Models\CondicionIva;
use App\Models\CuentaEmpresa;
use App\Models\Cuit;
use App\Models\Cupon;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\GrupoEtiqueta;
use App\Models\HistorialPrecio;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\Promocion;
use App\Models\PromocionEspecial;
use App\Models\PuntoVenta;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TipoCambio;
use App\Models\TipoIva;
use App\Models\VentaPago;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Services\ARCA\PadronARCAService;
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

    // =========================================
    // PROPIEDADES DEL POS / CARRITO
    // =========================================

    /** @var array Items en el carrito de venta */
    public $items = [];

    /** @var int|null ID del cliente seleccionado */
    public $clienteSeleccionado = null;

    /** @var string Búsqueda de artículos */
    public $busquedaArticulo = '';

    /** @var int Cantidad a agregar al seleccionar un artículo */
    public $cantidadAgregar = 1;

    /** @var array Artículos encontrados en la búsqueda */
    public $articulosResultados = [];

    /** @var bool Modo consulta de precios activo */
    public $modoConsulta = false;

    /** @var bool Modo búsqueda en detalle activo */
    public $modoBusqueda = false;

    /** @var array|null Artículo seleccionado para consulta de precios */
    public $articuloConsulta = null;

    /** @var bool Modal de consulta visible */
    public $mostrarModalConsulta = false;

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

    // =========================================
    // PROPIEDADES DE ALTA RÁPIDA DE ARTÍCULO
    // =========================================

    public bool $mostrarModalArticuloRapido = false;

    public string $artRapidoNombre = '';

    public ?int $artRapidoCategoriaId = null;

    public string $artRapidoCodigo = '';

    public string $artRapidoCodigoBarras = '';

    public string $artRapidoUnidadMedida = 'unidad';

    public ?int $artRapidoTipoIvaId = null;

    public ?float $artRapidoPrecioBase = null;

    public $artRapidoCategorias = [];

    public $artRapidoTiposIva = [];

    // =========================================
    // PROPIEDADES DE MODAL BÚSQUEDA ARTÍCULOS
    // =========================================

    public bool $mostrarModalBusquedaArticulos = false;

    public string $busquedaArticuloModal = '';

    public array $articulosModalResultados = [];

    public array $etiquetasModalSeleccionadas = [];

    public $gruposEtiquetasModal = [];

    // =========================================
    // PROPIEDADES DE BÚSQUEDA DE CLIENTES
    // =========================================

    /** @var string Búsqueda de clientes */
    public $busquedaCliente = '';

    /** @var array Clientes encontrados en la búsqueda */
    public $clientesResultados = [];

    /** @var string Nombre del cliente seleccionado */
    public $clienteNombre = '';

    /** @var string Condición IVA del cliente seleccionado */
    public $clienteCondicionIva = '';

    /** @var string Tipo de factura que se emitirá (A, B, C) */
    public $tipoFacturaCliente = 'B';

    /** @var bool Modal de alta rápida de cliente visible */
    public $mostrarModalClienteRapido = false;

    /** @var bool Modal de confirmación para limpiar carrito */
    public bool $mostrarConfirmLimpiar = false;

    // Campos del modal de alta de cliente
    public string $clienteRapidoNombre = '';

    public string $clienteRapidoRazonSocial = '';

    public string $clienteRapidoCuit = '';

    public string $clienteRapidoEmail = '';

    public string $clienteRapidoTelefono = '';

    public string $clienteRapidoDireccion = '';

    public ?int $clienteRapidoCondicionIvaId = null;

    // CUIT / ARCA
    public string $clienteRapidoModoAlta = 'manual';

    public bool $clienteRapidoArcaDisponible = false;

    public bool $clienteRapidoConsultandoCuit = false;

    public string $clienteRapidoErrorCuit = '';

    public string $clienteRapidoExitoCuit = '';

    public bool $clienteRapidoDatosDesdeArca = false;

    public string $clienteRapidoValidacionCuitMsg = '';

    public string $clienteRapidoValidacionCuitTipo = '';

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

    // =========================================
    // PROPIEDADES DE AJUSTE MANUAL DE PRECIOS
    // =========================================

    /** @var int|null Índice del item con popover de ajuste abierto */
    public $ajusteManualPopoverIndex = null;

    /** @var string Tipo de ajuste en el popover ('monto' o 'porcentaje') */
    public $ajusteManualTipo = 'monto';

    /** @var float|null Valor ingresado en el popover de ajuste */
    public $ajusteManualValor = null;

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
    // PROPIEDADES DE DESCUENTO GENERAL
    // =========================================

    /** @var bool Modal de descuentos y beneficios visible */
    public bool $showModalDescuentos = false;

    /** @var bool Si hay un descuento general activo */
    public bool $descuentoGeneralActivo = false;

    /** @var string|null Tipo de descuento general: 'porcentaje' o 'monto_fijo' */
    public ?string $descuentoGeneralTipo = null;

    /** @var float|null Valor del descuento general (% o $) */
    public ?float $descuentoGeneralValor = null;

    /** @var float Monto efectivo descontado por descuento general (calculado) */
    public float $descuentoGeneralMonto = 0;

    /** @var float|null Tope de descuento % del usuario (MAX de sus roles, null = sin tope) */
    public ?float $topeDescuentoUsuario = null;

    /** @var float|null Valor temporal del input en el modal */
    public ?float $descuentoGeneralInputValor = null;

    /** @var string Tipo temporal del input en el modal */
    public string $descuentoGeneralInputTipo = 'porcentaje';

    // =========================================
    // PROPIEDADES DE CUPÓN
    // =========================================

    /** @var string Código de cupón ingresado en el modal */
    public string $cuponCodigoInput = '';

    /** @var bool Si hay un cupón aplicado en la venta */
    public bool $cuponAplicado = false;

    /** @var array|null Info del cupón validado para mostrar en UI */
    public ?array $cuponInfo = null;

    /** @var float Monto de descuento del cupón (calculado) */
    public float $cuponMontoDescuento = 0;

    /** @var array IDs de artículos bonificados por el cupón */
    public array $cuponArticulosBonificados = [];

    // =========================================
    // PROPIEDADES DE CANJE DE PUNTOS
    // =========================================

    /** @var bool Si el programa de puntos está activo para esta venta */
    public bool $puntosDisponibles = false;

    /** @var int Saldo de puntos del cliente seleccionado */
    public int $puntosSaldoCliente = 0;

    /** @var bool Si hay un canje de puntos como pago activo */
    public bool $canjePuntosActivo = false;

    /** @var float|null Monto $ que el cliente quiere pagar con puntos */
    public ?float $canjePuntosMonto = null;

    /** @var int Puntos que se consumirán con el canje */
    public int $canjePuntosUnidades = 0;

    /** @var float Valor máximo canjeable en $ según saldo */
    public float $canjePuntosMaximo = 0;

    /** @var int Mínimo de puntos para habilitar canje */
    public int $puntosMinimoCanje = 0;

    /** @var float|null Input temporal en el modal */
    public ?float $canjePuntosInputMonto = null;

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
    // PROPIEDADES DEL WIZARD DE OPCIONALES
    // =========================================

    /** @var bool Modal del wizard de opcionales visible */
    public bool $mostrarWizardOpcionales = false;

    /** @var int|null ID del artículo en el wizard */
    public ?int $wizardArticuloId = null;

    /** @var array|null Datos del artículo en el wizard {nombre, precio, precioInfo, ivaInfo} */
    public ?array $wizardArticuloData = null;

    /** @var array Grupos opcionales del artículo (resultado de obtenerOpcionalesParaVenta) */
    public array $wizardGrupos = [];

    /** @var int Índice del grupo actual en el wizard (0-based) */
    public int $wizardPasoActual = 0;

    /** @var array Selecciones del usuario [grupo_id => [opcional_id => cantidad, ...], ...] */
    public array $wizardSelecciones = [];

    /** @var int|null Índice del item en el carrito si se está editando (null = nuevo) */
    public ?int $wizardEditandoIndex = null;

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
    // MÉTODOS DE BÚSQUEDA DE ARTÍCULOS
    // =========================================

    public function updatedBusquedaArticulo($value)
    {
        $value = trim($value);

        if (empty($value)) {
            $this->articulosResultados = [];

            return;
        }

        $this->cargarArticulosResultados($value);
    }

    protected function cargarArticulosResultados(string $busqueda): void
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        // Separar la búsqueda en palabras individuales para búsqueda inteligente
        $palabras = preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY);

        // Cada palabra debe coincidir en nombre, código, código de barras O nombre de categoría
        foreach ($palabras as $palabra) {
            $query->where(function ($q) use ($palabra) {
                $q->where('nombre', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$palabra.'%')
                    ->orWhereHas('categoriaModel', function ($subQ) use ($palabra) {
                        $subQ->where('nombre', 'like', '%'.$palabra.'%');
                    });
            });
        }

        // Filtrar por sucursal si hay artículos habilitados por sucursal
        if ($this->sucursalId) {
            $query->where(function ($q) {
                $q->whereHas('sucursales', function ($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                        ->where('articulos_sucursales.activo', 1);
                })->orWhereDoesntHave('sucursales');
            });
        }

        $articulos = $query->orderBy('nombre')->limit(15)->get();

        $this->articulosResultados = $articulos->map(function ($art) {
            return [
                'id' => $art->id,
                'nombre' => $art->nombre,
                'codigo' => $art->codigo,
                'codigo_barras' => $art->codigo_barras,
                'categoria_id' => $art->categoria_id,
                'categoria_nombre' => $art->categoriaModel?->nombre,
            ];
        })->toArray();
    }

    /**
     * Obtiene el precio de un artículo según la lista de precios seleccionada
     */
    protected function obtenerPrecioConLista(Articulo $articulo): array
    {
        $precioBaseArticulo = $articulo->obtenerPrecioBaseEfectivo($this->sucursalId);

        // Obtener precio de la lista base
        $listaBase = ListaPrecio::obtenerListaBase($this->sucursalId);
        $precioListaBase = $precioBaseArticulo;
        if ($listaBase) {
            $precioInfoBase = $listaBase->obtenerPrecioArticulo($articulo, $precioBaseArticulo);
            $precioListaBase = $precioInfoBase['precio'];
        }

        // Si no hay lista seleccionada, usar lista base
        if (! $this->listaPrecioId) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        $listaPrecio = ListaPrecio::find($this->listaPrecioId);
        if (! $listaPrecio) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Si la lista seleccionada ES la base, no mostrar ajuste
        if ($listaPrecio->es_lista_base) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Validar condiciones de la lista
        $contexto = [
            'forma_pago_id' => $this->formaPagoId,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
        ];

        if (! $listaPrecio->validarCondiciones($contexto)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Lista estática que no cubre este artículo → caer a lista base
        if ($listaPrecio->estatica && ! $listaPrecio->cubreArticulo($articulo)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Lista diferente a la base y cumple condiciones
        $precioInfo = $listaPrecio->obtenerPrecioArticulo($articulo, $precioBaseArticulo);

        return [
            'precio' => $precioInfo['precio'],
            'precio_base' => $precioBaseArticulo,
            'tiene_ajuste' => true,
        ];
    }

    // =========================================
    // MÉTODOS DE AGREGAR ARTÍCULOS
    // =========================================

    /**
     * Dispatcher: según el modo activo, consulta precios, busca en detalle o agrega al carrito.
     */
    public function seleccionarArticulo($articuloId)
    {
        if ($this->modoConsulta) {
            $this->consultarPrecios($articuloId);

            return;
        }

        if ($this->modoBusqueda) {
            $this->buscarEnDetalle($articuloId);

            return;
        }

        $this->agregarArticulo($articuloId);
    }

    /**
     * Verifica stock al agregar un artículo al detalle.
     * Solo muestra notificaciones (rojo=bloquea, amarillo=advierte).
     * Nunca bloquea el agregado; el bloqueo real ocurre al confirmar la venta.
     */
    protected function verificarStockAlAgregar(Articulo $articulo, float $cantidad, array $opcionales = []): void
    {
        $sucursal = Sucursal::find($this->sucursalId);
        $controlStock = $sucursal->control_stock_venta ?? 'bloquea';

        if ($controlStock === 'no_controla') {
            return;
        }

        $modoStock = $articulo->getModoStock($this->sucursalId);
        if ($modoStock === 'ninguno') {
            return;
        }

        $faltantes = [];

        if ($modoStock === 'unitario') {
            $stock = Stock::where('sucursal_id', $this->sucursalId)
                ->where('articulo_id', $articulo->id)
                ->first();
            $disponible = $stock ? (float) $stock->cantidad : 0;

            // Sumar cantidad ya en el carrito para el mismo artículo
            $enCarrito = 0;
            foreach ($this->items as $item) {
                if (($item['articulo_id'] ?? null) == $articulo->id && empty($item['opcionales'])) {
                    $enCarrito += (float) ($item['cantidad'] ?? 0);
                }
            }

            $totalNecesario = $enCarrito + $cantidad;
            if ($disponible < $totalNecesario) {
                $faltantes[] = "'{$articulo->nombre}': disponible ".round($disponible, 2).', necesario '.round($totalNecesario, 2);
            }
        } elseif ($modoStock === 'receta') {
            $receta = $articulo->resolverReceta($this->sucursalId);
            if ($receta) {
                foreach ($receta->ingredientes as $ingrediente) {
                    $cantNecesaria = $ingrediente->cantidad * $cantidad / $receta->cantidad_producida;
                    $stock = Stock::where('sucursal_id', $this->sucursalId)
                        ->where('articulo_id', $ingrediente->articulo_id)
                        ->first();
                    $disponible = $stock ? (float) $stock->cantidad : 0;
                    if ($disponible < $cantNecesaria) {
                        $nombre = $ingrediente->articulo->nombre ?? "Artículo #{$ingrediente->articulo_id}";
                        $faltantes[] = "'{$nombre}': disponible ".round($disponible, 2).', necesario '.round($cantNecesaria, 2);
                    }
                }
            }

            // Verificar ingredientes de opcionales con receta
            foreach ($opcionales as $grupo) {
                foreach ($grupo['selecciones'] ?? [] as $sel) {
                    $recetaOpc = Receta::resolver('Opcional', $sel['opcional_id'], $this->sucursalId);
                    if ($recetaOpc) {
                        $cantOpcional = ($sel['cantidad'] ?? 1) * $cantidad;
                        foreach ($recetaOpc->ingredientes as $ingrediente) {
                            $cantNecesaria = $ingrediente->cantidad * $cantOpcional / $recetaOpc->cantidad_producida;
                            $stock = Stock::where('sucursal_id', $this->sucursalId)
                                ->where('articulo_id', $ingrediente->articulo_id)
                                ->first();
                            $disponible = $stock ? (float) $stock->cantidad : 0;
                            if ($disponible < $cantNecesaria) {
                                $nombre = $ingrediente->articulo->nombre ?? "Artículo #{$ingrediente->articulo_id}";
                                $faltantes[] = "'{$nombre}': disponible ".round($disponible, 2).', necesario '.round($cantNecesaria, 2);
                            }
                        }
                    }
                }
            }
        }

        if (empty($faltantes)) {
            return;
        }

        $mensajes = array_unique($faltantes);
        $tipo = ($controlStock === 'bloquea') ? 'toast-error' : 'toast-warning';
        $prefijo = ($controlStock === 'bloquea') ? __('Stock insuficiente') : __('Advertencia de stock');

        foreach ($mensajes as $msg) {
            $this->dispatch($tipo, message: $prefijo.': '.$msg);
        }
    }

    public function agregarArticulo($articuloId)
    {
        $articulo = Articulo::with(['categoriaModel', 'tipoIva'])->find($articuloId);
        if (! $articulo) {
            return;
        }

        // Si es pesable, abrir modal para ingresar cantidad/valor
        if ($articulo->pesable) {
            $precioInfo = $this->obtenerPrecioConLista($articulo);
            $this->pesableArticuloId = $articulo->id;
            $this->pesablePrecioUnitario = (float) $precioInfo['precio'];
            $this->pesableUnidadMedida = $articulo->unidad_medida ?? 'kg';
            $this->pesableNombreArticulo = $articulo->nombre;
            $this->pesableCantidad = null;
            $this->pesableValor = null;
            $this->mostrarModalPesable = true;

            return;
        }

        $precioInfo = $this->obtenerPrecioConLista($articulo);

        // Obtener información de IVA del artículo
        $tipoIva = $articulo->tipoIva;
        $ivaInfo = [
            'codigo' => $tipoIva?->codigo ?? 5,
            'porcentaje' => (float) ($tipoIva?->porcentaje ?? 21),
            'nombre' => $tipoIva?->nombre ?? 'IVA 21%',
        ];

        // Verificar si el artículo tiene opcionales en esta sucursal
        $grupos = $this->opcionalService->obtenerOpcionalesParaVenta($articuloId, $this->sucursalId);
        if (! empty($grupos)) {
            // Tiene opcionales: abrir wizard en vez de agregar directo
            $this->wizardArticuloId = $articulo->id;
            $this->wizardArticuloData = [
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
                'categoria_id' => $articulo->categoria_id,
                'categoria_nombre' => $articulo->categoriaModel?->nombre,
                'precio_base' => $precioInfo['precio_base'],
                'precio' => $precioInfo['precio'],
                'tiene_ajuste' => $precioInfo['tiene_ajuste'],
                'iva_codigo' => $ivaInfo['codigo'],
                'iva_porcentaje' => $ivaInfo['porcentaje'],
                'iva_nombre' => $ivaInfo['nombre'],
                'precio_iva_incluido' => $articulo->precio_iva_incluido ?? true,
                'puntos_canje' => $articulo->puntos_canje,
            ];
            $this->wizardGrupos = $grupos;
            $this->wizardPasoActual = 0;
            $this->wizardSelecciones = [];
            $this->wizardEditandoIndex = null;
            $this->mostrarWizardOpcionales = true;
            $this->busquedaArticulo = '';
            $this->articulosResultados = [];

            return;
        }

        // Sin opcionales: notificar stock si corresponde
        $this->verificarStockAlAgregar($articulo, $this->cantidadAgregar);

        // Flujo normal
        // Buscar renglón existente con mismo artículo y mismo precio (sin ajuste manual)
        $indiceExistente = null;
        $precioNuevo = $precioInfo['precio'];
        foreach ($this->items as $idx => $item) {
            if (
                ($item['articulo_id'] ?? null) == $articulo->id
                && ! ($item['es_concepto'] ?? false)
                && (float) ($item['precio'] ?? 0) === (float) $precioNuevo
                && empty($item['ajuste_manual_tipo'])
                && empty($item['opcionales'])
            ) {
                $indiceExistente = $idx;
                break;
            }
        }

        if ($indiceExistente !== null) {
            $this->items[$indiceExistente]['cantidad'] += $this->cantidadAgregar;
        } else {
            $this->items[] = [
                'articulo_id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
                'categoria_id' => $articulo->categoria_id,
                'categoria_nombre' => $articulo->categoriaModel?->nombre,
                'precio_base' => $precioInfo['precio_base'],
                'precio' => $precioInfo['precio'],
                'tiene_ajuste' => $precioInfo['tiene_ajuste'],
                'cantidad' => $this->cantidadAgregar,
                // Información de IVA
                'iva_codigo' => $ivaInfo['codigo'],
                'iva_porcentaje' => $ivaInfo['porcentaje'],
                'iva_nombre' => $ivaInfo['nombre'],
                'precio_iva_incluido' => $articulo->precio_iva_incluido ?? true,
                // Campos para ajuste manual de precio
                'ajuste_manual_tipo' => null,
                'ajuste_manual_valor' => null,
                'precio_sin_ajuste_manual' => null,
                // Opcionales (vacío para items sin opcionales)
                'opcionales' => [],
                'precio_opcionales' => 0,
                // Canje por puntos (RF-25)
                'puntos_canje' => $articulo->puntos_canje,
                'pagado_con_puntos' => false,
            ];

            // RF-34: Herencia de descuento general % a items nuevos
            if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'porcentaje') {
                $lastIndex = count($this->items) - 1;
                $precioBase = (float) $precioInfo['precio_base'];
                $nuevoPrecio = round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2);
                if ($nuevoPrecio < 0) {
                    $nuevoPrecio = 0;
                }
                $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
                $this->items[$lastIndex]['precio'] = $nuevoPrecio;
                $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
                $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
                $this->items[$lastIndex]['tiene_ajuste'] = true;
            }
        }

        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->cantidadAgregar = 1;
        $this->calcularVenta();
        $this->dispatch('scroll-carrito-abajo');
    }

    /**
     * Agrega el primer artículo de la lista de resultados (para Enter)
     * Si hay coincidencia exacta por código de barras o código, agrega ese artículo
     */
    public function agregarPrimerArticulo()
    {
        $busqueda = trim($this->busquedaArticulo);

        // Si no hay búsqueda, no hacer nada
        if (empty($busqueda)) {
            return;
        }

        // Primero intentar coincidencia exacta por código de barras o código
        $articuloPorCodigo = Articulo::where('activo', true)
            ->where(function ($q) use ($busqueda) {
                $q->where('codigo_barras', $busqueda)
                    ->orWhere('codigo', $busqueda);
            })
            ->first();

        $articuloId = null;

        if ($articuloPorCodigo) {
            $articuloId = $articuloPorCodigo->id;
        } elseif (! empty($this->articulosResultados)) {
            $articuloId = $this->articulosResultados[0]['id'];
        }

        if (! $articuloId) {
            return;
        }

        // Verificar si está en modo consulta
        if ($this->modoConsulta) {
            $this->consultarPrecios($articuloId);

            return;
        }

        // Verificar si está en modo búsqueda en detalle
        if ($this->modoBusqueda) {
            $this->buscarEnDetalle($articuloId);

            return;
        }

        // Modo normal: agregar al carrito
        $this->agregarArticulo($articuloId);
    }

    /**
     * Agrega artículo por código directo (usado por scanner para evitar race conditions).
     * El código se captura en Alpine y se pasa como parámetro, sin depender de wire:model.
     */
    public function agregarPorCodigo(string $codigo)
    {
        $codigo = trim($codigo);
        if (empty($codigo)) {
            return;
        }

        $articulo = Articulo::where('activo', true)
            ->where(function ($q) use ($codigo) {
                $q->where('codigo_barras', $codigo)
                    ->orWhere('codigo', $codigo);
            })
            ->first();

        if (! $articulo) {
            return;
        }

        if ($this->modoConsulta) {
            $this->consultarPrecios($articulo->id);

            return;
        }

        if ($this->modoBusqueda) {
            $this->buscarEnDetalle($articulo->id);

            return;
        }

        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->agregarArticulo($articulo->id);
    }

    public function eliminarItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calcularVenta();
    }

    public function actualizarCantidad($index, $cantidad)
    {
        $cantidad = max(0.001, (float) $cantidad);
        if (isset($this->items[$index])) {
            $this->items[$index]['cantidad'] = $cantidad;
            $this->calcularVenta();
        }
    }

    // =========================================
    // ALTA RÁPIDA DE ARTÍCULO
    // =========================================

    public function abrirModalArticuloRapido(): void
    {
        $this->resetArticuloRapido();

        $this->artRapidoCategorias = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color', 'prefijo']);

        $this->artRapidoTiposIva = TipoIva::orderBy('nombre')
            ->get(['id', 'nombre', 'porcentaje']);

        // Preseleccionar IVA 21% por defecto
        $iva21 = $this->artRapidoTiposIva->firstWhere('porcentaje', 21);
        $this->artRapidoTipoIvaId = $iva21?->id;

        // Pre-llenar con lo que el usuario escribió en la búsqueda
        $busqueda = trim($this->busquedaArticulo);
        if (! empty($busqueda)) {
            $this->artRapidoNombre = $busqueda;
        }

        $this->mostrarModalArticuloRapido = true;
    }

    public function cerrarModalArticuloRapido(): void
    {
        $this->mostrarModalArticuloRapido = false;
        $this->resetArticuloRapido();
        $this->dispatch('focus-busqueda');
    }

    protected function resetArticuloRapido(): void
    {
        $this->artRapidoNombre = '';
        $this->artRapidoCategoriaId = null;
        $this->artRapidoCodigo = '';
        $this->artRapidoCodigoBarras = '';
        $this->artRapidoUnidadMedida = 'unidad';
        $this->artRapidoTipoIvaId = null;
        $this->artRapidoPrecioBase = null;
        $this->artRapidoCategorias = [];
        $this->artRapidoTiposIva = [];
    }

    public function updatedArtRapidoCategoriaId($value): void
    {
        if (! $value) {
            return;
        }

        $categoria = Categoria::find($value);
        if ($categoria && $categoria->prefijo) {
            $ultimoCodigo = Articulo::where('codigo', 'like', $categoria->prefijo.'-%')
                ->orderByRaw('CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED) DESC')
                ->value('codigo');

            if ($ultimoCodigo) {
                $numero = (int) last(explode('-', $ultimoCodigo)) + 1;
            } else {
                $numero = 1;
            }

            $this->artRapidoCodigo = $categoria->prefijo.'-'.str_pad($numero, 3, '0', STR_PAD_LEFT);
        }
    }

    public function guardarArticuloRapido(): void
    {
        $this->validate([
            'artRapidoNombre' => 'required|string|min:2|max:200',
            'artRapidoCodigo' => 'required|string|max:50|unique:pymes_tenant.articulos,codigo',
            'artRapidoCodigoBarras' => 'nullable|string|max:50',
            'artRapidoCategoriaId' => 'nullable|exists:pymes_tenant.categorias,id',
            'artRapidoUnidadMedida' => 'required|string|max:50',
            'artRapidoTipoIvaId' => 'required|exists:pymes_tenant.tipos_iva,id',
            'artRapidoPrecioBase' => 'required|numeric|min:0',
        ], [
            'artRapidoNombre.required' => __('El nombre es obligatorio'),
            'artRapidoNombre.min' => __('El nombre debe tener al menos 2 caracteres'),
            'artRapidoCodigo.required' => __('El código es obligatorio'),
            'artRapidoCodigo.unique' => __('Ya existe un artículo con este código'),
            'artRapidoTipoIvaId.required' => __('Seleccione un tipo de IVA'),
            'artRapidoPrecioBase.required' => __('El precio es obligatorio'),
        ]);

        try {
            $articulo = Articulo::create([
                'nombre' => $this->artRapidoNombre,
                'codigo' => $this->artRapidoCodigo,
                'codigo_barras' => $this->artRapidoCodigoBarras ?: null,
                'categoria_id' => $this->artRapidoCategoriaId,
                'unidad_medida' => $this->artRapidoUnidadMedida,
                'tipo_iva_id' => $this->artRapidoTipoIvaId,
                'precio_iva_incluido' => true,
                'precio_base' => $this->artRapidoPrecioBase,
                'es_materia_prima' => false,
                'activo' => true,
            ]);

            HistorialPrecio::registrar([
                'articulo_id' => $articulo->id,
                'precio_anterior' => 0,
                'precio_nuevo' => $this->artRapidoPrecioBase,
                'origen' => 'articulo_crear',
            ]);

            // Sincronizar con todas las sucursales (activo solo en la actual)
            $todasSucursales = Sucursal::pluck('id')->toArray();
            $sucursalActiva = sucursal_activa();
            $syncData = [];
            foreach ($todasSucursales as $sucursalId) {
                $esActiva = $sucursalId == $sucursalActiva;
                $syncData[$sucursalId] = [
                    'activo' => $esActiva,
                    'modo_stock' => 'ninguno',
                    'vendible' => true,
                    'precio_base' => null,
                ];
            }
            $articulo->sucursales()->sync($syncData);

            // Agregar el artículo recién creado al carrito
            $this->agregarArticulo($articulo->id);

            $this->cerrarModalArticuloRapido();

            $this->dispatch('notify',
                message: __('Artículo ":nombre" creado y agregado', ['nombre' => $articulo->nombre]),
                type: 'success'
            );
        } catch (\Exception $e) {
            \Log::error('Error al crear artículo rápido: '.$e->getMessage());
            $this->dispatch('notify',
                message: __('Error al crear el artículo'),
                type: 'error'
            );
        }
    }

    // =========================================
    // MODAL DE BÚSQUEDA DE ARTÍCULOS
    // =========================================

    public function abrirModalBusquedaArticulos(): void
    {
        $this->busquedaArticuloModal = '';
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = GrupoEtiqueta::where('activo', true)
            ->with(['etiquetas' => fn ($q) => $q->where('activo', true)->orderBy('orden')->orderBy('nombre')])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
        $this->cargarArticulosModal();
        $this->mostrarModalBusquedaArticulos = true;
    }

    public function cerrarModalBusquedaArticulos(): void
    {
        $this->mostrarModalBusquedaArticulos = false;
        $this->busquedaArticuloModal = '';
        $this->articulosModalResultados = [];
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = [];
        $this->dispatch('focus-busqueda');
    }

    public function updatedBusquedaArticuloModal(): void
    {
        $this->cargarArticulosModal();
    }

    public function updatedEtiquetasModalSeleccionadas(): void
    {
        $this->cargarArticulosModal();
    }

    protected function cargarArticulosModal(): void
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        // Filtrar por sucursal activa
        if ($this->sucursalId) {
            $query->whereHas('sucursales', function ($q) {
                $q->where('sucursal_id', $this->sucursalId)
                    ->where('articulos_sucursales.activo', 1);
            });
        }

        // Filtrar por etiquetas seleccionadas
        if (! empty($this->etiquetasModalSeleccionadas)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasModalSeleccionadas);
            });
        }

        // Filtrar por búsqueda (nombre, código, código de barras, categoría)
        $busqueda = trim($this->busquedaArticuloModal);
        if (! empty($busqueda)) {
            $palabras = preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($palabras as $palabra) {
                $query->where(function ($q) use ($palabra) {
                    $q->where('nombre', 'like', '%'.$palabra.'%')
                        ->orWhere('codigo', 'like', '%'.$palabra.'%')
                        ->orWhere('codigo_barras', 'like', '%'.$palabra.'%')
                        ->orWhereHas('categoriaModel', function ($subQ) use ($palabra) {
                            $subQ->where('nombre', 'like', '%'.$palabra.'%');
                        });
                });
            }
        }

        $articulos = $query->orderBy('nombre')->limit(100)->get();

        $this->articulosModalResultados = $articulos->map(fn ($a) => [
            'id' => $a->id,
            'nombre' => $a->nombre,
            'codigo' => $a->codigo,
            'codigo_barras' => $a->codigo_barras,
            'categoria' => $a->categoriaModel?->nombre,
            'precio_base' => $a->precio_base,
        ])->toArray();
    }

    public function seleccionarArticuloModal(int $articuloId): void
    {
        $this->cerrarModalBusquedaArticulos();
        $this->seleccionarArticulo($articuloId);
    }

    // =========================================
    // MODOS DE CONSULTA Y BÚSQUEDA
    // =========================================

    /**
     * Activa el modo consulta de precios
     */
    public function activarModoConsulta()
    {
        $this->modoConsulta = true;
        $this->modoBusqueda = false;
        $this->dispatch('focus-busqueda');
    }

    /**
     * Activa el modo búsqueda en detalle
     */
    public function activarModoBusqueda()
    {
        $this->modoBusqueda = true;
        $this->modoConsulta = false;
        $this->dispatch('focus-busqueda');
    }

    /**
     * Desactiva todos los modos especiales
     */
    public function desactivarModos()
    {
        $this->modoConsulta = false;
        $this->modoBusqueda = false;
    }

    /**
     * Consulta precios de un artículo en todas las listas
     */
    public function consultarPrecios($articuloId)
    {
        $articulo = Articulo::with('categoriaModel')->find($articuloId);

        if (! $articulo) {
            $this->dispatch('toast-error', message: __('Artículo no encontrado'));

            return;
        }

        // Obtener todas las listas de precios de la sucursal
        $listasPrecios = ListaPrecio::where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->orderBy('es_lista_base', 'desc')
            ->orderBy('prioridad')
            ->get();

        $precioBase = $articulo->precio_base;
        $precios = [];

        foreach ($listasPrecios as $lista) {
            // Verificar si tiene precio específico en la lista
            $detalleArticulo = ListaPrecioArticulo::buscarParaArticulo(
                $lista->id,
                $articuloId,
                $articulo->categoria_id
            );

            $tienePrecioEspecifico = false;
            $ajusteAplicado = $lista->ajuste_porcentaje ?? 0;

            if ($detalleArticulo) {
                // Usar el método calcularPrecio del modelo
                $resultado = $detalleArticulo->calcularPrecio($precioBase, $lista->ajuste_porcentaje ?? 0);
                $precioFinal = $resultado['precio'];
                $ajusteAplicado = $resultado['ajuste_porcentaje'];
                $tienePrecioEspecifico = in_array($resultado['tipo'], ['precio_fijo', 'ajuste_detalle']);
            } else {
                // Aplicar ajuste porcentual del encabezado
                $precioFinal = $precioBase * (1 + ($ajusteAplicado / 100));
            }

            $precios[] = [
                'lista_id' => $lista->id,
                'lista_nombre' => $lista->nombre,
                'es_lista_base' => $lista->es_lista_base,
                'ajuste_porcentaje' => $ajusteAplicado,
                'precio' => round($precioFinal, 2),
                'tiene_precio_especifico' => $tienePrecioEspecifico,
            ];
        }

        $this->articuloConsulta = [
            'id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'categoria' => $articulo->categoriaModel?->nombre ?? $articulo->categoria,
            'precio_base' => $precioBase,
            'precios' => $precios,
        ];

        $this->mostrarModalConsulta = true;
        $this->modoConsulta = false;
        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
    }

    /**
     * Cierra el modal de consulta
     */
    public function cerrarModalConsulta()
    {
        $this->mostrarModalConsulta = false;
        $this->articuloConsulta = null;
        $this->dispatch('focus-busqueda');
    }

    /**
     * Agrega un artículo al carrito y cierra el modal de consulta
     */
    public function agregarArticuloYCerrarConsulta($articuloId)
    {
        $this->agregarArticulo($articuloId);
        $this->cerrarModalConsulta();
    }

    /**
     * Busca un artículo en el detalle y lo resalta
     */
    public function buscarEnDetalle($articuloId)
    {
        $indiceEncontrado = null;

        foreach ($this->items as $index => $item) {
            if ($item['articulo_id'] == $articuloId) {
                $indiceEncontrado = $index;
                break;
            }
        }

        if ($indiceEncontrado !== null) {
            $this->itemResaltado = $indiceEncontrado;
            $this->dispatch('scroll-to-item', index: $indiceEncontrado);
            $this->dispatch('auto-clear-resaltado');
            $this->dispatch('toast-success', message: __('Artículo encontrado en el detalle'));
        } else {
            $this->dispatch('toast-warning', message: 'El artículo no está en el detalle de la venta');
        }

        $this->modoBusqueda = false;
        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
    }

    /**
     * Limpia el resaltado del item
     */
    public function limpiarResaltado()
    {
        $this->itemResaltado = null;
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
    // MÉTODOS DEL WIZARD DE OPCIONALES
    // =========================================

    /**
     * Toggle de una opción en el grupo actual (tipo seleccionable)
     */
    public function toggleOpcion($opcionalId)
    {
        $grupo = $this->wizardGrupos[$this->wizardPasoActual] ?? null;
        if (! $grupo) {
            return;
        }

        // Verificar que la opción esté disponible
        $opcion = collect($grupo['opciones'])->firstWhere('opcional_id', $opcionalId);
        if (! $opcion || ! ($opcion['disponible'] ?? true)) {
            return;
        }

        $grupoId = $grupo['grupo_id'];
        $selecciones = $this->wizardSelecciones[$grupoId] ?? [];

        if (isset($selecciones[$opcionalId])) {
            unset($selecciones[$opcionalId]);
        } else {
            $selecciones[$opcionalId] = 1;
        }

        $this->wizardSelecciones[$grupoId] = $selecciones;

        // Auto-avance si alcanzamos max_seleccion
        $max = $grupo['max_seleccion'];
        if ($max !== null && count($selecciones) >= $max) {
            $this->confirmarPasoWizard();
        }
    }

    /**
     * Cambia la cantidad de una opción (tipo cuantitativo)
     */
    public function cambiarCantidadOpcion($opcionalId, $delta)
    {
        $grupo = $this->wizardGrupos[$this->wizardPasoActual] ?? null;
        if (! $grupo) {
            return;
        }

        // Verificar que la opción esté disponible
        $opcion = collect($grupo['opciones'])->firstWhere('opcional_id', $opcionalId);
        if (! $opcion || ! ($opcion['disponible'] ?? true)) {
            return;
        }

        $grupoId = $grupo['grupo_id'];
        $selecciones = $this->wizardSelecciones[$grupoId] ?? [];

        $cantidadActual = $selecciones[$opcionalId] ?? 0;
        $nuevaCantidad = max(0, $cantidadActual + (int) $delta);

        if ($nuevaCantidad > 0) {
            $selecciones[$opcionalId] = $nuevaCantidad;
        } else {
            unset($selecciones[$opcionalId]);
        }

        $this->wizardSelecciones[$grupoId] = $selecciones;

        // Auto-avance si la suma de cantidades alcanza max_seleccion
        $max = $grupo['max_seleccion'];
        if ($max !== null) {
            $sumaTotal = array_sum($selecciones);
            if ($sumaTotal >= $max) {
                $this->confirmarPasoWizard();
            }
        }
    }

    /**
     * Confirma el paso actual y avanza al siguiente o finaliza
     */
    public function confirmarPasoWizard($forzar = false)
    {
        // Validar obligatorio (Ctrl+Enter fuerza el avance)
        if (! $forzar) {
            $grupo = $this->wizardGrupos[$this->wizardPasoActual] ?? null;
            if ($grupo && $grupo['obligatorio']) {
                $grupoId = $grupo['grupo_id'];
                $selecciones = $this->wizardSelecciones[$grupoId] ?? [];
                $cantidadSeleccionada = ($grupo['tipo'] === 'cuantitativo')
                    ? array_sum($selecciones)
                    : count($selecciones);
                if ($cantidadSeleccionada < 1) {
                    return;
                }
            }
        }

        if ($this->wizardPasoActual < count($this->wizardGrupos) - 1) {
            $this->wizardPasoActual++;
        } else {
            $this->confirmarWizardOpcionales();
        }
    }

    /**
     * Retrocede al grupo anterior
     */
    public function anteriorPasoWizard()
    {
        if ($this->wizardPasoActual > 0) {
            $this->wizardPasoActual--;
        }
    }

    /**
     * Cierra el wizard y agrega el artículo con las selecciones hechas hasta ahora
     * (Esc salta todos los grupos restantes)
     */
    public function saltearWizardOpcionales()
    {
        $this->confirmarWizardOpcionales();
    }

    /**
     * Confirma el wizard y agrega el item al carrito con opcionales seleccionados
     */
    public function confirmarWizardOpcionales()
    {
        $data = $this->wizardArticuloData;
        if (! $data) {
            $this->cerrarWizardOpcionales();

            return;
        }

        // Construir array de opcionales seleccionados y calcular precio extra total
        $opcionalesItem = [];
        $precioOpcionalesTotal = 0;

        foreach ($this->wizardGrupos as $grupo) {
            $grupoId = $grupo['grupo_id'];
            $selecciones = $this->wizardSelecciones[$grupoId] ?? [];

            if (empty($selecciones)) {
                continue;
            }

            $seleccionesDetalle = [];
            foreach ($grupo['opciones'] as $opcion) {
                $cantidad = $selecciones[$opcion['opcional_id']] ?? 0;
                if ($cantidad > 0) {
                    $precioExtra = (float) $opcion['precio_extra'];
                    $seleccionesDetalle[] = [
                        'opcional_id' => $opcion['opcional_id'],
                        'nombre' => $opcion['nombre'],
                        'cantidad' => $cantidad,
                        'precio_extra' => $precioExtra,
                    ];
                    $precioOpcionalesTotal += $precioExtra * $cantidad;
                }
            }

            if (! empty($seleccionesDetalle)) {
                $opcionalesItem[] = [
                    'grupo_id' => $grupoId,
                    'grupo_nombre' => $grupo['nombre'],
                    'tipo' => $grupo['tipo'],
                    'selecciones' => $seleccionesDetalle,
                ];
            }
        }

        $precioConOpcionales = (float) $data['precio'] + $precioOpcionalesTotal;

        // Notificar stock del artículo + opcionales
        $articulo = Articulo::find($this->wizardArticuloId);
        if ($articulo) {
            $this->verificarStockAlAgregar($articulo, $this->cantidadAgregar, $opcionalesItem);
        }

        if ($this->wizardEditandoIndex !== null && isset($this->items[$this->wizardEditandoIndex])) {
            // Editando un item existente: actualizar opcionales y precio
            $this->items[$this->wizardEditandoIndex]['opcionales'] = $opcionalesItem;
            $this->items[$this->wizardEditandoIndex]['precio_opcionales'] = $precioOpcionalesTotal;
            $this->items[$this->wizardEditandoIndex]['precio'] = $precioConOpcionales;
        } else {
            // Nuevo item: siempre crea línea nueva (nunca agrupa con opcionales)
            $this->items[] = [
                'articulo_id' => $this->wizardArticuloId,
                'nombre' => $data['nombre'],
                'codigo' => $data['codigo'],
                'categoria_id' => $data['categoria_id'],
                'categoria_nombre' => $data['categoria_nombre'],
                'precio_base' => $data['precio_base'],
                'precio' => $precioConOpcionales,
                'tiene_ajuste' => $data['tiene_ajuste'],
                'cantidad' => $this->cantidadAgregar,
                'iva_codigo' => $data['iva_codigo'],
                'iva_porcentaje' => $data['iva_porcentaje'],
                'iva_nombre' => $data['iva_nombre'],
                'precio_iva_incluido' => $data['precio_iva_incluido'],
                'ajuste_manual_tipo' => null,
                'ajuste_manual_valor' => null,
                'precio_sin_ajuste_manual' => null,
                'opcionales' => $opcionalesItem,
                'precio_opcionales' => $precioOpcionalesTotal,
                // Canje por puntos (RF-25)
                'puntos_canje' => $data['puntos_canje'] ?? null,
                'pagado_con_puntos' => false,
            ];

            // RF-34: Herencia de descuento general % a items nuevos
            if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'porcentaje') {
                $lastIndex = count($this->items) - 1;
                $precioBase = (float) $data['precio_base'] + $precioOpcionalesTotal;
                $nuevoPrecio = round($precioBase - ($precioBase * $this->descuentoGeneralValor / 100), 2);
                if ($nuevoPrecio < 0) {
                    $nuevoPrecio = 0;
                }
                $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioConOpcionales;
                $this->items[$lastIndex]['precio'] = $nuevoPrecio;
                $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
                $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
                $this->items[$lastIndex]['tiene_ajuste'] = true;
            }
        }

        $this->cerrarWizardOpcionales();
        $this->calcularVenta();
    }

    /**
     * Abre el wizard para editar opcionales de un item existente en el carrito
     */
    public function editarOpcionalesItem($index)
    {
        $item = $this->items[$index] ?? null;
        if (! $item || empty($item['articulo_id'])) {
            return;
        }

        $grupos = $this->opcionalService->obtenerOpcionalesParaVenta($item['articulo_id'], $this->sucursalId);
        if (empty($grupos)) {
            return;
        }

        $this->wizardArticuloId = $item['articulo_id'];
        $this->wizardArticuloData = [
            'nombre' => $item['nombre'],
            'codigo' => $item['codigo'],
            'categoria_id' => $item['categoria_id'],
            'categoria_nombre' => $item['categoria_nombre'],
            'precio_base' => $item['precio_base'],
            'precio' => (float) $item['precio'] - (float) ($item['precio_opcionales'] ?? 0),
            'tiene_ajuste' => $item['tiene_ajuste'],
            'iva_codigo' => $item['iva_codigo'],
            'iva_porcentaje' => $item['iva_porcentaje'],
            'iva_nombre' => $item['iva_nombre'],
            'precio_iva_incluido' => $item['precio_iva_incluido'],
        ];
        $this->wizardGrupos = $grupos;
        $this->wizardPasoActual = 0;
        $this->wizardEditandoIndex = $index;

        // Pre-cargar selecciones existentes del item
        $this->wizardSelecciones = [];
        foreach ($item['opcionales'] ?? [] as $grupoSel) {
            $selMap = [];
            foreach ($grupoSel['selecciones'] as $sel) {
                $selMap[$sel['opcional_id']] = $sel['cantidad'];
            }
            $this->wizardSelecciones[$grupoSel['grupo_id']] = $selMap;
        }

        $this->mostrarWizardOpcionales = true;
    }

    /**
     * Cierra el wizard sin agregar/modificar nada
     */
    public function cerrarWizardOpcionales()
    {
        $this->mostrarWizardOpcionales = false;
        $this->wizardArticuloId = null;
        $this->wizardArticuloData = null;
        $this->wizardGrupos = [];
        $this->wizardPasoActual = 0;
        $this->wizardSelecciones = [];
        $this->wizardEditandoIndex = null;
        $this->cantidadAgregar = 1;
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
    // MÉTODOS DE BÚSQUEDA DE CLIENTES
    // =========================================

    /**
     * Handler para cambios en la búsqueda de clientes
     */
    public function updatedBusquedaCliente($value)
    {
        $value = trim($value);

        // Solo mostrar resultados si hay al menos 2 caracteres
        if (strlen($value) < 2) {
            $this->clientesResultados = [];

            return;
        }

        $this->buscarClientes($value);
    }

    /**
     * Busca clientes por nombre, filtrando por sucursal
     */
    protected function buscarClientes(string $busqueda): void
    {
        $query = Cliente::where('activo', true);

        // Filtrar por sucursal si está seleccionada
        if ($this->sucursalId) {
            $query->where(function ($q) {
                // Clientes vinculados a la sucursal y activos en ella
                $q->whereHas('sucursales', function ($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                        ->where('clientes_sucursales.activo', true);
                })
                // O clientes sin vinculación a ninguna sucursal (disponibles para todas)
                    ->orWhereDoesntHave('sucursales');
            });
        }

        // Búsqueda inteligente por nombre, CUIT y teléfono
        $query->where(function ($q) use ($busqueda) {
            $q->where('nombre', 'like', '%'.$busqueda.'%')
                ->orWhere('cuit', 'like', '%'.$busqueda.'%')
                ->orWhere('telefono', 'like', '%'.$busqueda.'%');
        });

        $this->clientesResultados = $query->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'cuit' => $c->cuit,
                'telefono' => $c->telefono,
            ])
            ->toArray();
    }

    /**
     * Selecciona un cliente de los resultados de búsqueda
     */
    public function seleccionarCliente($clienteId)
    {
        $cliente = Cliente::with('condicionIva')->find($clienteId);

        if ($cliente) {
            $this->clienteSeleccionado = $cliente->id;
            $this->clienteNombre = $cliente->nombre;
            $this->busquedaCliente = '';
            $this->clientesResultados = [];

            // Cargar condición IVA del cliente
            $this->clienteCondicionIva = $cliente->condicionIva?->nombre ?? 'Consumidor Final';
            $this->determinarTipoFacturaCliente($cliente);

            // Cargar saldo de puntos del cliente (RF-23)
            $this->cargarSaldoPuntosCliente($cliente);

            // Si el cliente tiene lista de precios asignada, actualizarla
            if ($cliente->lista_precio_id) {
                $this->listaPrecioId = $cliente->lista_precio_id;
                $this->actualizarPreciosItems();
                $this->calcularVenta();
            }
        }
    }

    /**
     * Determina el tipo de factura según la condición IVA del cliente y del emisor
     */
    protected function determinarTipoFacturaCliente(?Cliente $cliente = null): void
    {
        // Por defecto, Factura B (Consumidor Final)
        $this->tipoFacturaCliente = 'B';

        if (! $cliente) {
            return;
        }

        // Obtener condición IVA del emisor desde la caja activa
        try {
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            if (! $cajaId) {
                return;
            }

            $caja = Caja::with('puntosVenta.cuit.condicionIva')->find($cajaId);
            if (! $caja) {
                return;
            }

            $puntoVenta = $caja->puntoVentaDefecto();
            if (! $puntoVenta || ! $puntoVenta->cuit) {
                return;
            }

            $condicionEmisor = $puntoVenta->cuit->condicionIva;
            if (! $condicionEmisor) {
                return;
            }

            // Si el emisor es Monotributista o Exento, siempre es C
            if ($condicionEmisor->esMonotributista() || $condicionEmisor->esExento()) {
                $this->tipoFacturaCliente = 'C';

                return;
            }

            // Emisor es Responsable Inscripto
            if ($condicionEmisor->esResponsableInscripto()) {
                $condicionCliente = $cliente->condicionIva;

                // Factura A: Cliente RI o Monotributista (códigos 1, 6, 13, 16)
                // Factura B: Cliente CF, Exento u otros
                if ($condicionCliente && ($condicionCliente->esResponsableInscripto() || $condicionCliente->esMonotributista())) {
                    $this->tipoFacturaCliente = 'A';
                } else {
                    $this->tipoFacturaCliente = 'B';
                }
            }
        } catch (\Exception $e) {
            // Si hay error, mantener B por defecto
            $this->tipoFacturaCliente = 'B';
        }
    }

    /**
     * Limpia la selección de cliente
     */
    public function limpiarCliente()
    {
        $this->clienteSeleccionado = null;
        $this->clienteNombre = '';
        $this->clienteCondicionIva = '';
        $this->tipoFacturaCliente = 'B';
        $this->busquedaCliente = '';
        $this->clientesResultados = [];
    }

    /**
     * Selecciona el primer cliente de la lista de resultados (para Enter)
     */
    public function seleccionarPrimerCliente()
    {
        if (empty($this->clientesResultados) && ! empty($this->busquedaCliente)) {
            // Si no hay resultados, buscar
            $this->buscarClientes($this->busquedaCliente);
        }

        if (! empty($this->clientesResultados)) {
            $this->seleccionarCliente($this->clientesResultados[0]['id']);
        }
    }

    /**
     * Abre el modal de alta de cliente
     */
    public function abrirModalClienteRapido()
    {
        $this->resetClienteRapido();
        $consumidorFinal = CondicionIva::where('codigo', CondicionIva::CONSUMIDOR_FINAL)->first();
        $this->clienteRapidoCondicionIvaId = $consumidorFinal?->id;
        $this->clienteRapidoArcaDisponible = PadronARCAService::estaDisponible();
        $this->mostrarModalClienteRapido = true;
    }

    /**
     * Cierra el modal de alta de cliente
     */
    public function cerrarModalClienteRapido()
    {
        $this->mostrarModalClienteRapido = false;
        $this->resetClienteRapido();
    }

    /**
     * Resetea campos del modal de cliente
     */
    protected function resetClienteRapido(): void
    {
        $this->clienteRapidoNombre = '';
        $this->clienteRapidoRazonSocial = '';
        $this->clienteRapidoCuit = '';
        $this->clienteRapidoEmail = '';
        $this->clienteRapidoTelefono = '';
        $this->clienteRapidoDireccion = '';
        $this->clienteRapidoCondicionIvaId = null;
        $this->clienteRapidoModoAlta = 'manual';
        $this->clienteRapidoConsultandoCuit = false;
        $this->clienteRapidoErrorCuit = '';
        $this->clienteRapidoExitoCuit = '';
        $this->clienteRapidoDatosDesdeArca = false;
        $this->clienteRapidoValidacionCuitMsg = '';
        $this->clienteRapidoValidacionCuitTipo = '';
    }

    /**
     * Validación en tiempo real del CUIT (modo manual)
     */
    public function updatedClienteRapidoCuit(): void
    {
        if ($this->clienteRapidoModoAlta !== 'manual') {
            return;
        }

        $this->clienteRapidoDatosDesdeArca = false;
        $this->clienteRapidoValidacionCuitMsg = '';
        $this->clienteRapidoValidacionCuitTipo = '';

        if (empty($this->clienteRapidoCuit)) {
            return;
        }

        $cuitLimpio = preg_replace('/\D/', '', $this->clienteRapidoCuit);

        if (strlen($cuitLimpio) < 11) {
            return;
        }

        if (strlen($cuitLimpio) > 11) {
            $this->clienteRapidoValidacionCuitMsg = __('El CUIT debe tener 11 dígitos');
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        if (! Cuit::validarCuit($cuitLimpio)) {
            $this->clienteRapidoValidacionCuitMsg = __('CUIT inválido (dígito verificador incorrecto)');
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        $existente = Cliente::withTrashed()->where(function ($q) use ($cuitLimpio) {
            $q->where('cuit', $this->clienteRapidoCuit)->orWhere('cuit', $cuitLimpio);
        })->first();

        if ($existente) {
            $this->clienteRapidoValidacionCuitMsg = $existente->trashed()
                ? __('Existe un cliente eliminado con este CUIT: :nombre', ['nombre' => $existente->nombre])
                : __('Ya existe un cliente con este CUIT: :nombre', ['nombre' => $existente->nombre]);
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        if ($this->clienteRapidoArcaDisponible) {
            try {
                $cuitComercio = PadronARCAService::obtenerCuitDisponible();
                if ($cuitComercio) {
                    $servicio = new PadronARCAService($cuitComercio);
                    $datos = $servicio->consultarCuit($cuitLimpio);
                    if ($datos['condicion_iva_id']) {
                        $this->clienteRapidoCondicionIvaId = $datos['condicion_iva_id'];
                        $this->clienteRapidoDatosDesdeArca = true;
                        $condicion = CondicionIva::find($datos['condicion_iva_id']);
                        $this->clienteRapidoValidacionCuitMsg = __('CUIT válido — :condicion (según ARCA)', ['condicion' => $condicion->nombre ?? '']);
                        $this->clienteRapidoValidacionCuitTipo = 'success';
                    }

                    return;
                }
            } catch (\Exception $e) {
                Log::info('Validación ARCA en modo manual falló', ['error' => $e->getMessage()]);
            }
        }

        $this->clienteRapidoValidacionCuitMsg = __('CUIT válido');
        $this->clienteRapidoValidacionCuitTipo = 'success';
    }

    /**
     * Consulta CUIT en ARCA (modo CUIT)
     */
    public function consultarCuitClienteRapido(): void
    {
        $this->clienteRapidoErrorCuit = '';
        $this->clienteRapidoExitoCuit = '';

        if (empty($this->clienteRapidoCuit)) {
            $this->clienteRapidoErrorCuit = __('Ingrese un CUIT para consultar');

            return;
        }

        $cuitLimpio = preg_replace('/\D/', '', $this->clienteRapidoCuit);

        if (! Cuit::validarCuit($cuitLimpio)) {
            $this->clienteRapidoErrorCuit = __('El CUIT ingresado no es válido. Verifique el número.');

            return;
        }

        $existente = Cliente::withTrashed()->where(function ($q) use ($cuitLimpio) {
            $q->where('cuit', $this->clienteRapidoCuit)->orWhere('cuit', $cuitLimpio);
        })->first();

        if ($existente) {
            $this->clienteRapidoErrorCuit = $existente->trashed()
                ? __('Existe un cliente eliminado con este CUIT: :nombre', ['nombre' => $existente->nombre])
                : __('Ya existe un cliente con el CUIT :cuit: :nombre', ['cuit' => $this->clienteRapidoCuit, 'nombre' => $existente->nombre]);

            return;
        }

        $this->clienteRapidoConsultandoCuit = true;

        try {
            $cuitComercio = PadronARCAService::obtenerCuitDisponible();
            if (! $cuitComercio) {
                $this->clienteRapidoErrorCuit = __('No hay certificados ARCA configurados para realizar la consulta');
                $this->clienteRapidoConsultandoCuit = false;

                return;
            }

            $servicio = new PadronARCAService($cuitComercio);
            $datos = $servicio->consultarCuit($cuitLimpio);

            $this->clienteRapidoRazonSocial = $datos['denominacion'] ?? '';
            $this->clienteRapidoNombre = $datos['denominacion'] ?? '';
            $this->clienteRapidoDireccion = $datos['direccion'] ?? '';
            $this->clienteRapidoCuit = $cuitLimpio;

            if ($datos['condicion_iva_id']) {
                $this->clienteRapidoCondicionIvaId = $datos['condicion_iva_id'];
            }

            $this->clienteRapidoDatosDesdeArca = true;

            $estadoTexto = $datos['estado_activo'] ? __('Activo') : __('Inactivo');
            $this->clienteRapidoExitoCuit = __('Datos obtenidos correctamente. Estado: :estado', ['estado' => $estadoTexto]);

        } catch (\Exception $e) {
            $this->clienteRapidoErrorCuit = $e->getMessage();
            Log::error('Error al consultar padrón ARCA', ['cuit' => $cuitLimpio, 'error' => $e->getMessage()]);
        }

        $this->clienteRapidoConsultandoCuit = false;
    }

    /**
     * Guarda un cliente y lo selecciona
     */
    public function guardarClienteRapido()
    {
        $this->validate([
            'clienteRapidoNombre' => 'required|min:2|max:255',
            'clienteRapidoEmail' => 'nullable|email|max:191',
            'clienteRapidoTelefono' => 'nullable|max:50',
            'clienteRapidoCuit' => 'nullable|string|max:20',
        ], [
            'clienteRapidoNombre.required' => __('El nombre es obligatorio'),
            'clienteRapidoNombre.min' => __('El nombre debe tener al menos 2 caracteres'),
            'clienteRapidoEmail.email' => __('Ingrese un email válido'),
        ]);

        try {
            $cliente = Cliente::create([
                'nombre' => $this->clienteRapidoNombre,
                'razon_social' => $this->clienteRapidoRazonSocial ?: null,
                'cuit' => $this->clienteRapidoCuit ?: null,
                'email' => $this->clienteRapidoEmail ?: null,
                'telefono' => $this->clienteRapidoTelefono ?: null,
                'direccion' => $this->clienteRapidoDireccion ?: null,
                'condicion_iva_id' => $this->clienteRapidoCondicionIvaId,
                'activo' => true,
            ]);

            // Asignar sucursal activa con lista base
            $sucursalActiva = sucursal_activa();
            if ($sucursalActiva) {
                $listaPrecioId = ListaPrecio::where('sucursal_id', $sucursalActiva)
                    ->where('es_lista_base', true)
                    ->value('id');
                $cliente->sucursales()->syncWithoutDetaching([
                    $sucursalActiva => ['activo' => true, 'lista_precio_id' => $listaPrecioId],
                ]);
            }

            // Seleccionar el cliente recién creado
            $this->clienteSeleccionado = $cliente->id;
            $this->clienteNombre = $cliente->nombre;
            $this->busquedaCliente = '';
            $this->clientesResultados = [];

            $this->cerrarModalClienteRapido();

            $this->dispatch('notify',
                message: __('Cliente ":nombre" creado correctamente', ['nombre' => $cliente->nombre]),
                type: 'success'
            );

        } catch (Exception $e) {
            Log::error('Error al crear cliente: '.$e->getMessage());
            $this->dispatch('notify',
                message: __('Error al crear el cliente'),
                type: 'error'
            );
        }
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

    // =========================================
    // AJUSTE MANUAL DE PRECIOS
    // =========================================

    /**
     * Abre el popover de ajuste manual para un item
     */
    public function abrirAjusteManual(int $index, string $tipo): void
    {
        $this->ajusteManualPopoverIndex = $index;
        $this->ajusteManualTipo = $tipo; // 'monto' o 'porcentaje'
        $this->ajusteManualValor = null;
    }

    /**
     * Cierra el popover de ajuste manual
     */
    public function cerrarAjusteManual(): void
    {
        $this->ajusteManualPopoverIndex = null;
        $this->ajusteManualTipo = 'monto';
        $this->ajusteManualValor = null;
        // Devolver foco al buscador de artículos
        $this->dispatch('focus-busqueda');
    }

    /**
     * Aplica el ajuste manual al precio del item
     */
    public function aplicarAjusteManual(): void
    {
        $index = $this->ajusteManualPopoverIndex;

        if ($index === null || ! isset($this->items[$index])) {
            $this->cerrarAjusteManual();

            return;
        }

        $item = $this->items[$index];
        $precioBase = (float) $item['precio_base'];
        $valor = $this->ajusteManualValor;

        // Validar que se ingresó un valor
        if ($valor === null || $valor === '') {
            $this->dispatch('toast-error', message: 'Ingrese un valor');

            return;
        }

        $valor = (float) $valor;

        if ($this->ajusteManualTipo === 'monto') {
            // El valor es el nuevo precio directo
            if ($valor <= 0) {
                $this->dispatch('toast-error', message: 'El precio debe ser mayor a cero');

                return;
            }
            $nuevoPrecio = $valor;
        } else {
            // El valor es un porcentaje (positivo = descuento, negativo = recargo)
            if ($valor < -100 || $valor > 100) {
                $this->dispatch('toast-error', message: 'El porcentaje debe estar entre -100% y 100%');

                return;
            }
            // Positivo resta (descuento), negativo suma (recargo)
            $nuevoPrecio = round($precioBase - ($precioBase * $valor / 100), 2);
            if ($nuevoPrecio <= 0) {
                $this->dispatch('toast-error', message: 'El precio resultante debe ser mayor a cero');

                return;
            }
        }

        // Guardar el precio anterior para mostrar tachado
        $this->items[$index]['precio_sin_ajuste_manual'] = $item['precio'];
        $this->items[$index]['precio'] = $nuevoPrecio;
        $this->items[$index]['ajuste_manual_tipo'] = $this->ajusteManualTipo;
        $this->items[$index]['ajuste_manual_valor'] = $valor;
        // Marcar que tiene ajuste (para mostrar visualmente)
        $this->items[$index]['tiene_ajuste'] = true;

        $this->cerrarAjusteManual();
        $this->calcularVenta();

        $tipoTexto = $this->ajusteManualTipo === 'monto' ? 'Precio manual' : 'Descuento';
        $this->dispatch('toast-success', message: "{$tipoTexto} aplicado");
    }

    /**
     * Quita el ajuste manual de un item y restaura el precio calculado
     */
    public function quitarAjusteManual(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $item = $this->items[$index];

        // Solo procesar si tiene ajuste manual
        if ($item['ajuste_manual_tipo'] === null) {
            return;
        }

        // Recalcular el precio según lista de precios
        $articulo = Articulo::find($item['articulo_id']);
        if ($articulo) {
            $precioInfo = $this->obtenerPrecioConLista($articulo);
            $this->items[$index]['precio'] = $precioInfo['precio'];
            $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
            $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
        }

        // Limpiar campos de ajuste manual
        $this->items[$index]['ajuste_manual_tipo'] = null;
        $this->items[$index]['ajuste_manual_valor'] = null;
        $this->items[$index]['precio_sin_ajuste_manual'] = null;

        $this->calcularVenta();
        $this->dispatch('toast-info', message: 'Ajuste manual eliminado');
    }

    // =========================================
    // DESCUENTO GENERAL
    // =========================================

    /**
     * Abre el modal de Descuentos y Beneficios
     */
    public function abrirModalDescuentos(): void
    {
        // Inicializar inputs del modal con los valores activos (si hay)
        if ($this->descuentoGeneralActivo) {
            $this->descuentoGeneralInputTipo = $this->descuentoGeneralTipo;
            $this->descuentoGeneralInputValor = $this->descuentoGeneralValor;
        } else {
            $this->descuentoGeneralInputTipo = 'porcentaje';
            $this->descuentoGeneralInputValor = null;
        }

        $this->showModalDescuentos = true;
    }

    /**
     * Cierra el modal de Descuentos y Beneficios
     */
    public function cerrarModalDescuentos(): void
    {
        $this->showModalDescuentos = false;
    }

    /**
     * Carga el tope de descuento del usuario (MAX de sus roles)
     */
    protected function cargarTopeDescuentoUsuario(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $tope = DB::connection('pymes_tenant')
            ->table('roles')
            ->join('model_has_roles', function ($join) use ($user) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.model_id', $user->id);
            })
            ->whereNotNull('roles.descuento_maximo_porcentaje')
            ->max('roles.descuento_maximo_porcentaje');

        $this->topeDescuentoUsuario = $tope !== null ? (float) $tope : null;
    }

    /**
     * Aplica descuento general al carrito.
     * % → aplica ajuste_manual masivo a todos los items
     * $ → se resta del total en calcularVenta()
     */
    public function aplicarDescuentoGeneral(): void
    {
        $tipo = $this->descuentoGeneralInputTipo;
        $valor = $this->descuentoGeneralInputValor;

        if ($valor === null || $valor === '' || (float) $valor <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese un valor mayor a cero'));

            return;
        }

        $valor = (float) $valor;

        // Verificar permiso
        $user = Auth::user();
        if (! $user || ! $user->hasPermissionTo('func.descuento_general')) {
            $this->dispatch('toast-error', message: __('No tiene permiso para aplicar descuento general'));

            return;
        }

        // Validar tope por rol
        if ($this->topeDescuentoUsuario !== null) {
            if ($tipo === 'porcentaje' && $valor > $this->topeDescuentoUsuario) {
                $this->dispatch('toast-error', message: __('El descuento supera el máximo permitido para su rol')." ({$this->topeDescuentoUsuario}%)");

                return;
            }

            if ($tipo === 'monto_fijo') {
                // El monto fijo no puede superar el % tope del total pre-descuento
                $totalPreDescuento = $this->resultado['subtotal'] ?? 0;
                $maxMonto = round($totalPreDescuento * $this->topeDescuentoUsuario / 100, 2);
                if ($valor > $maxMonto) {
                    $this->dispatch('toast-error', message: __('El descuento supera el máximo permitido para su rol')." (\${$maxMonto})");

                    return;
                }
            }
        }

        if ($tipo === 'porcentaje') {
            if ($valor > 100) {
                $this->dispatch('toast-error', message: __('El porcentaje no puede superar 100%'));

                return;
            }
        }

        // RF-33: Exclusividad % / $ — si ya hay uno activo, quitar primero
        // RF-35: Re-aplicar % pisa ajustes individuales previos
        if ($this->descuentoGeneralActivo) {
            if ($this->descuentoGeneralTipo === 'porcentaje') {
                $this->restaurarPreciosOriginalesItems();
            }
            $this->descuentoGeneralActivo = false;
        }

        if ($tipo === 'porcentaje') {
            $this->aplicarDescuentoPorcentajeATodosLosItems($valor);
        }

        // Guardar estado del descuento general
        $this->descuentoGeneralActivo = true;
        $this->descuentoGeneralTipo = $tipo;
        $this->descuentoGeneralValor = $valor;

        $this->calcularVenta();

        $etiqueta = $tipo === 'porcentaje' ? "{$valor}%" : "\${$valor}";
        $this->dispatch('toast-success', message: __('Descuento general aplicado').": {$etiqueta}");
    }

    /**
     * Quita el descuento general, restaurando precios originales si era porcentaje
     */
    public function quitarDescuentoGeneral(): void
    {
        if (! $this->descuentoGeneralActivo) {
            return;
        }

        // Si era porcentaje, restaurar precios de todos los items
        if ($this->descuentoGeneralTipo === 'porcentaje') {
            $this->restaurarPreciosOriginalesItems();
        }

        $this->descuentoGeneralActivo = false;
        $this->descuentoGeneralTipo = null;
        $this->descuentoGeneralValor = null;
        $this->descuentoGeneralMonto = 0;
        $this->descuentoGeneralInputValor = null;
        $this->descuentoGeneralInputTipo = 'porcentaje';

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Descuento general eliminado'));
    }

    /**
     * Aplica ajuste_manual porcentaje a todos los items del carrito (RF-31)
     */
    protected function aplicarDescuentoPorcentajeATodosLosItems(float $porcentaje): void
    {
        foreach ($this->items as $index => $item) {
            $precioBase = (float) $item['precio_base'];
            $nuevoPrecio = round($precioBase - ($precioBase * $porcentaje / 100), 2);

            if ($nuevoPrecio < 0) {
                $nuevoPrecio = 0;
            }

            $this->items[$index]['precio_sin_ajuste_manual'] = $item['precio_base'];
            $this->items[$index]['precio'] = $nuevoPrecio;
            $this->items[$index]['ajuste_manual_tipo'] = 'porcentaje';
            $this->items[$index]['ajuste_manual_valor'] = $porcentaje;
            $this->items[$index]['tiene_ajuste'] = true;
        }
    }

    /**
     * Restaura precios originales de todos los items (quita ajuste manual masivo)
     */
    protected function restaurarPreciosOriginalesItems(): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['ajuste_manual_tipo'] === null) {
                continue;
            }

            $articuloId = $item['articulo_id'] ?? null;
            if ($articuloId) {
                $articulo = Articulo::find($articuloId);
                if ($articulo) {
                    $precioInfo = $this->obtenerPrecioConLista($articulo);
                    $this->items[$index]['precio'] = $precioInfo['precio'];
                    $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
                    $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
                }
            } else {
                // Concepto: restaurar precio original
                $this->items[$index]['precio'] = $item['precio_sin_ajuste_manual'] ?? $item['precio'];
                $this->items[$index]['tiene_ajuste'] = false;
            }

            $this->items[$index]['ajuste_manual_tipo'] = null;
            $this->items[$index]['ajuste_manual_valor'] = null;
            $this->items[$index]['precio_sin_ajuste_manual'] = null;
        }
    }

    // =========================================
    // CUPÓN EN VENTA
    // =========================================

    /**
     * Valida un cupón por código ingresado en el modal.
     */
    public function validarCupon(): void
    {
        $codigo = trim($this->cuponCodigoInput);
        if (empty($codigo)) {
            $this->dispatch('toast-error', message: __('Ingrese un código de cupón'));

            return;
        }

        $resultado = $this->cuponService->validarCupon($codigo, $this->clienteSeleccionado);

        if (! $resultado['valid']) {
            $this->cuponInfo = null;
            $this->dispatch('toast-error', message: $resultado['message']);

            return;
        }

        $cupon = $resultado['cupon'];

        // Calcular descuento preview
        $totalParaCupon = $this->resultado['total_final'] ?? 0;
        $articuloIdsEnCarrito = collect($this->items)->pluck('articulo_id')->filter()->values()->toArray();
        $descuento = $this->cuponService->calcularDescuento($cupon, $totalParaCupon, $articuloIdsEnCarrito);

        // Guardar info para mostrar en UI
        $formasPagoPermitidas = $cupon->formasPago()->pluck('nombre', 'formas_pago.id')->toArray();

        $this->cuponInfo = [
            'id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'tipo' => $cupon->tipo,
            'descripcion' => $cupon->descripcion,
            'modo_descuento' => $cupon->modo_descuento,
            'valor_descuento' => (float) $cupon->valor_descuento,
            'aplica_a' => $cupon->aplica_a,
            'uso_actual' => $cupon->uso_actual,
            'uso_maximo' => $cupon->uso_maximo,
            'fecha_vencimiento' => $cupon->fecha_vencimiento?->format('d/m/Y'),
            'monto_descuento' => $descuento['monto_descuento'],
            'articulos_bonificados' => $descuento['articulos_bonificados'],
            'formas_pago_permitidas' => $formasPagoPermitidas,
        ];

        $this->dispatch('toast-success', message: __('Cupón válido'));
    }

    /**
     * Aplica el cupón validado a la venta actual.
     */
    public function aplicarCupon(): void
    {
        if (! $this->cuponInfo) {
            $this->dispatch('toast-error', message: __('Primero valide un cupón'));

            return;
        }

        // Re-validar por seguridad
        $cupon = Cupon::find($this->cuponInfo['id']);
        if (! $cupon || ! $cupon->estaVigente() || ! $cupon->tieneUsosDisponibles()) {
            $this->cuponInfo = null;
            $this->dispatch('toast-error', message: __('Cupón inválido'));

            return;
        }

        if (! $cupon->puedeSerUsadoPor($this->clienteSeleccionado)) {
            $this->dispatch('toast-error', message: __('Este cupón pertenece a otro cliente'));

            return;
        }

        $this->cuponAplicado = true;

        // Recalcular descuento con artículos bonificados (respetando cantidad del pivot)
        if ($cupon->aplicaAArticulos()) {
            $articulosCupon = $cupon->articulos()->get()->keyBy('id');
            $bonificados = [];
            $itemsParaCalculo = [];

            foreach ($this->items as $item) {
                $articuloId = $item['articulo_id'] ?? null;
                if ($articuloId && $articulosCupon->has($articuloId)) {
                    $bonificados[] = $articuloId;
                    $itemsParaCalculo[] = $item;
                }
            }
            $this->cuponArticulosBonificados = $bonificados;

            $totalParaCupon = $this->resultado['total_final'] ?? 0;
            $descuento = $this->cuponService->calcularDescuento($cupon, $totalParaCupon, $bonificados, $itemsParaCalculo);
            $this->cuponMontoDescuento = $descuento['monto_descuento'];
            $this->cuponInfo['monto_descuento'] = $this->cuponMontoDescuento;
        } else {
            $this->cuponArticulosBonificados = [];
        }

        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Cupón aplicado').": {$cupon->codigo}");
    }

    /**
     * Quita el cupón aplicado de la venta.
     */
    public function quitarCupon(): void
    {
        $this->cuponAplicado = false;
        $this->cuponInfo = null;
        $this->cuponMontoDescuento = 0;
        $this->cuponArticulosBonificados = [];
        $this->cuponCodigoInput = '';

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Cupón eliminado'));
    }

    /**
     * Calcula el descuento del cupón por cada item del carrito para trazabilidad.
     * Retorna array indexado por posición del item.
     */
    private function calcularDescuentoCuponPorItem(): array
    {
        $resultado = [];

        if (! $this->cuponAplicado || ! $this->cuponInfo || $this->cuponMontoDescuento <= 0) {
            return $resultado;
        }

        $cupon = Cupon::find($this->cuponInfo['id']);
        if (! $cupon) {
            return $resultado;
        }

        if ($cupon->aplicaAArticulos()) {
            $articulosCupon = $cupon->articulos()->get()->keyBy('id');
            $montoElegibleTotal = 0;
            $elegiblesPorItem = [];

            // Agrupar índices por articulo_id para respetar cantidad global
            $indicesPorArticulo = [];
            foreach ($this->items as $index => $item) {
                $articuloId = (int) ($item['articulo_id'] ?? 0);
                if (! $articulosCupon->has($articuloId)) {
                    continue;
                }
                $indicesPorArticulo[$articuloId][] = $index;
            }

            // Calcular monto elegible respetando cantidad global por artículo
            foreach ($indicesPorArticulo as $articuloId => $indices) {
                $pivotCantidad = $articulosCupon->get($articuloId)->pivot->cantidad;

                if ($pivotCantidad === null) {
                    // Sin límite: todas las unidades elegibles
                    foreach ($indices as $index) {
                        $item = $this->items[$index];
                        $montoElegible = (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
                        $elegiblesPorItem[$index] = $montoElegible;
                        $montoElegibleTotal += $montoElegible;
                    }
                } else {
                    // Con límite: ordenar por precio DESC para priorizar más caros
                    $indicesOrdenados = $indices;
                    usort($indicesOrdenados, fn ($a, $b) => ((float) ($this->items[$b]['precio'] ?? 0)) <=> ((float) ($this->items[$a]['precio'] ?? 0)));

                    $cantidadRestante = $pivotCantidad;
                    foreach ($indicesOrdenados as $index) {
                        if ($cantidadRestante <= 0) {
                            break;
                        }
                        $item = $this->items[$index];
                        $cantidadEnCarrito = (float) ($item['cantidad'] ?? 1);
                        $cantidadElegible = min($cantidadEnCarrito, $cantidadRestante);
                        $montoElegible = (float) ($item['precio'] ?? 0) * $cantidadElegible;
                        $elegiblesPorItem[$index] = $montoElegible;
                        $montoElegibleTotal += $montoElegible;
                        $cantidadRestante -= $cantidadElegible;
                    }
                }
            }

            if ($montoElegibleTotal <= 0) {
                return $resultado;
            }

            // Distribuir el descuento total proporcionalmente
            foreach ($elegiblesPorItem as $index => $montoElegible) {
                if ($cupon->esPorcentaje()) {
                    $resultado[$index] = round($montoElegible * ((float) $cupon->valor_descuento / 100), 2);
                } else {
                    // Monto fijo: prorratear proporcionalmente
                    $proporcion = $montoElegible / $montoElegibleTotal;
                    $resultado[$index] = round(min((float) $cupon->valor_descuento, $montoElegibleTotal) * $proporcion, 2);
                }
            }
        }
        // Para aplica_a = 'total', no se desglosa por item (queda en ventas.monto_cupon)

        return $resultado;
    }

    // =========================================
    // CANJE DE PUNTOS COMO PAGO
    // =========================================

    /**
     * Carga saldo de puntos al seleccionar un cliente (RF-23).
     */
    protected function cargarSaldoPuntosCliente(?Cliente $cliente = null): void
    {
        $this->puntosDisponibles = false;
        $this->puntosSaldoCliente = 0;
        $this->canjePuntosMaximo = 0;
        $this->puntosMinimoCanje = 0;

        if (! $cliente || ! $cliente->programa_puntos_activo) {
            return;
        }

        if (! $this->puntosService->isProgramaActivo($this->sucursalId)) {
            return;
        }

        $config = $this->puntosService->getConfiguracion();
        if (! $config) {
            return;
        }

        $sucursalIdParaSaldo = $config->esPorSucursal() ? $this->sucursalId : null;
        $saldo = $this->puntosService->obtenerSaldo($cliente->id, $sucursalIdParaSaldo);

        $this->puntosSaldoCliente = $saldo;
        $this->puntosMinimoCanje = $config->minimo_canje;
        $this->puntosDisponibles = $saldo >= $config->minimo_canje;

        // Calcular máximo canjeable en $
        if ($this->puntosDisponibles && $config->valor_punto_canje > 0) {
            $this->canjePuntosMaximo = round($saldo * (float) $config->valor_punto_canje, 2);
        }
    }

    /**
     * Aplica canje de puntos como pago (RF-24).
     */
    public function aplicarCanjePuntos(): void
    {
        $monto = $this->canjePuntosInputMonto;

        if ($monto === null || $monto <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese un monto mayor a cero'));

            return;
        }

        $monto = (float) $monto;

        if (! $this->clienteSeleccionado || ! $this->puntosDisponibles) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        // Calcular máximo real (descontando artículos canjeados)
        $maximoCanjeable = $this->canjePuntosMaximoReal;

        if ($maximoCanjeable <= 0) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        // Limitar al máximo canjeable con puntos libres
        if ($monto > $maximoCanjeable) {
            $monto = $maximoCanjeable;
        }

        // Limitar al total de la venta
        $totalVenta = $this->resultado['total_final'] ?? 0;
        if ($monto > $totalVenta) {
            $monto = $totalVenta;
        }

        $config = $this->puntosService->getConfiguracion();
        if (! $config || $config->valor_punto_canje <= 0) {
            $this->dispatch('toast-error', message: __('Programa de puntos no configurado'));

            return;
        }

        $puntosNecesarios = (int) ceil($monto / (float) $config->valor_punto_canje);
        $puntosLibres = max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos());

        if ($puntosNecesarios > $puntosLibres) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes').". {$puntosNecesarios} pts necesarios, {$puntosLibres} pts disponibles");

            return;
        }

        $this->canjePuntosActivo = true;
        $this->canjePuntosMonto = round($monto, 2);
        $this->canjePuntosUnidades = $puntosNecesarios;

        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Canje de puntos aplicado').": \${$this->canjePuntosMonto} ({$puntosNecesarios} pts)");
    }

    /**
     * Quita el canje de puntos.
     */
    public function quitarCanjePuntos(): void
    {
        $this->canjePuntosActivo = false;
        $this->canjePuntosMonto = null;
        $this->canjePuntosUnidades = 0;
        $this->canjePuntosInputMonto = null;

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Canje de puntos eliminado'));
    }

    /**
     * Canjea un artículo del carrito por puntos (RF-10, RF-25).
     * El artículo se marca como pagado_con_puntos y se descuenta del total.
     */
    public function canjearArticuloConPuntos(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        if (! $this->clienteSeleccionado || ! $this->puntosDisponibles) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        $valorPunto = $this->valorPuntoCanje;
        if ($valorPunto <= 0) {
            $this->dispatch('toast-error', message: __('Configuración de puntos incompleta'));

            return;
        }

        $item = $this->items[$index];
        $precioUnitario = (float) ($item['precio'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 1);

        // Calcular puntos desde el precio del artículo usando la configuración
        $puntosTotal = $this->calcularPuntosCanjePorPrecio($precioUnitario) * $cantidad;

        // Verificar saldo disponible (descontando artículos canjeados + canje como descuento)
        $puntosYaUsados = $this->calcularPuntosUsadosEnArticulos() + $this->canjePuntosUnidades;
        $puntosLibres = $this->puntosSaldoCliente - $puntosYaUsados;

        if ($puntosTotal > $puntosLibres) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes').". {$puntosTotal} pts necesarios, {$puntosLibres} pts disponibles");

            return;
        }

        $this->items[$index]['pagado_con_puntos'] = true;
        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Canjeado con puntos').": {$item['nombre']} ({$puntosTotal} pts)");
    }

    /**
     * Quita el canje por puntos de un artículo del carrito.
     */
    public function quitarCanjeArticulo(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['pagado_con_puntos'] = false;
        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Canje de artículo eliminado'));
    }

    /**
     * Obtiene el valor de 1 punto en $ desde la configuración (no serializado por Livewire).
     */
    #[Computed]
    public function valorPuntoCanje(): float
    {
        $config = $this->puntosService->getConfiguracion();

        return $config ? (float) $config->valor_punto_canje : 0;
    }

    /**
     * Puntos libres del cliente (saldo - artículos canjeados - canje como descuento).
     */
    #[Computed]
    public function puntosLibres(): int
    {
        return max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos() - $this->canjePuntosUnidades);
    }

    /**
     * Máximo canjeable en $ considerando puntos ya usados en artículos.
     */
    #[Computed]
    public function canjePuntosMaximoReal(): float
    {
        $puntosLibres = max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos());
        $valorPunto = $this->valorPuntoCanje;

        return $valorPunto > 0 ? round($puntosLibres * $valorPunto, 2) : 0;
    }

    /**
     * Resuelve el tipo_iva_id de un item del carrito.
     * - Artículos: del modelo Articulo.
     * - Conceptos con categoría: del tipo_iva de la categoría.
     * - Conceptos sin categoría: null (VentaService usa iva_porcentaje del detalle).
     */
    protected function resolverTipoIvaId(array $item): ?int
    {
        if (! ($item['es_concepto'] ?? false)) {
            if (! empty($item['articulo_id'])) {
                $articulo = Articulo::find($item['articulo_id']);

                return $articulo?->tipo_iva_id;
            }

            return null;
        }

        if (! empty($item['categoria_id'])) {
            $categoria = Categoria::find($item['categoria_id']);

            return $categoria?->tipo_iva_id;
        }

        return null;
    }

    /**
     * Calcula puntos necesarios para canjear un artículo desde su precio.
     */
    protected function calcularPuntosCanjePorPrecio(float $precio): int
    {
        $valorPunto = $this->valorPuntoCanje;
        if ($valorPunto <= 0) {
            return 0;
        }

        return (int) ceil($precio / $valorPunto);
    }

    /**
     * Calcula los puntos totales usados en artículos canjeados del carrito.
     */
    protected function calcularPuntosUsadosEnArticulos(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            if ($item['pagado_con_puntos'] ?? false) {
                $puntos = $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0));
                $total += $puntos * (float) ($item['cantidad'] ?? 1);
            }
        }

        return $total;
    }

    /**
     * Acumula puntos de fidelización después de completar la venta (RF-05).
     * Se ejecuta post-commit — si falla, la venta ya se creó.
     */
    protected function acumularPuntosPostVenta($venta): void
    {
        if (! $venta->cliente_id) {
            return;
        }

        try {
            // Obtener los pagos reales de la venta con su multiplicador (sucursal > genérico)
            $sucursalId = $venta->sucursal_id;
            $pagos = VentaPago::where('venta_id', $venta->id)
                ->get()
                ->map(function ($pago) use ($sucursalId) {
                    $fp = FormaPago::find($pago->forma_pago_id);
                    $multiplicador = (float) ($fp->multiplicador_puntos ?? 1.00);

                    // Override por sucursal si existe
                    if ($sucursalId && $fp) {
                        $fpSucursal = \App\Models\FormaPagoSucursal::where('forma_pago_id', $fp->id)
                            ->where('sucursal_id', $sucursalId)
                            ->first();
                        if ($fpSucursal && $fpSucursal->multiplicador_puntos !== null) {
                            $multiplicador = (float) $fpSucursal->multiplicador_puntos;
                        }
                    }

                    return [
                        'monto_final' => (float) $pago->monto_final,
                        'es_pago_puntos' => (bool) $pago->es_pago_puntos,
                        'es_cuenta_corriente' => (bool) $pago->es_cuenta_corriente,
                        'multiplicador_puntos' => $multiplicador,
                    ];
                });

            $this->puntosService->acumularPuntosPorVenta($venta, $pagos, Auth::id());
        } catch (Exception $e) {
            Log::warning('Error al acumular puntos post-venta', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================
    // CÁLCULO DE VENTA CON PROMOCIONES
    // =========================================

    public function calcularVenta()
    {
        if (empty($this->items)) {
            $this->resultado = null;

            return;
        }

        $resultado = [
            'items' => [],
            'subtotal' => 0,
            'promociones_especiales_aplicadas' => [],
            'promociones_comunes_aplicadas' => [],
            'total_descuentos' => 0,
            'total_final' => 0,
        ];

        // Crear pool de unidades disponibles
        $poolUnidades = $this->crearPoolUnidades();

        // Obtener información de promociones de la lista seleccionada
        $infoPromos = $this->obtenerInfoPromocionesLista();

        // Marcar artículos excluidos de promociones
        $articulosExcluidos = [];
        if (! $infoPromos['aplica_promociones']) {
            // La lista no aplica promociones, todos excluidos
            foreach ($poolUnidades as &$unidad) {
                $unidad['excluido_promociones'] = true;
                $articulosExcluidos[$unidad['articulo_id']] = true;
            }
        } elseif ($infoPromos['promociones_alcance'] === 'excluir_lista' && $this->listaPrecioId) {
            // Solo excluir artículos con precio especial en la lista
            $listaPrecio = ListaPrecio::find($this->listaPrecioId);
            if ($listaPrecio) {
                foreach ($poolUnidades as &$unidad) {
                    $categoriaId = $unidad['categoria_id'] ?? null;
                    $tienePrecioEspecial = $listaPrecio->articulos()
                        ->where(function ($query) use ($unidad, $categoriaId) {
                            $query->where('articulo_id', $unidad['articulo_id']);
                            if ($categoriaId) {
                                $query->orWhere('categoria_id', $categoriaId);
                            }
                        })
                        ->exists();
                    if ($tienePrecioEspecial) {
                        $unidad['excluido_promociones'] = true;
                        $articulosExcluidos[$unidad['articulo_id']] = true;
                    }
                }
            }
        }

        // Calcular subtotal
        foreach ($this->items as $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $resultado['subtotal'] += $precio * $cantidad;
        }

        // Contexto de la venta
        $contexto = [
            'sucursal_id' => $this->sucursalId,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
            'forma_pago_id' => $this->formaPagoId,
            'fecha' => now()->format('Y-m-d'),
            'dia_semana' => (int) now()->dayOfWeek,
            'hora' => now()->format('H:i:s'),
        ];

        // Preparar items para promociones comunes (con info de exclusión)
        $itemsParaPromos = [];
        foreach ($this->items as $index => $item) {
            $articuloId = $item['articulo_id'] ?? null;
            $itemsParaPromos[$index] = [
                'articulo_id' => $articuloId,
                'categoria_id' => $item['categoria_id'] ?? null,
                'nombre' => $item['nombre'],
                'precio' => (float) ($item['precio'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'excluido_promociones' => isset($articulosExcluidos[$articuloId]),
            ];
        }

        // 1. Aplicar promociones especiales (NxM, Combo, Menú)
        //    a) Forzadas: se aplican siempre por orden de prioridad (ej: liquidar stock).
        //    b) Automáticas: el sistema elige la combinación que MÁS ahorra al cliente.
        if ($infoPromos['aplica_promociones']) {
            $promocionesEspeciales = $this->obtenerPromocionesEspeciales($contexto);

            $forzadas = array_values(array_filter(
                $promocionesEspeciales,
                fn ($p) => ($p['modo_aplicacion'] ?? 'automatica') === 'forzada'
            ));
            $automaticas = array_values(array_filter(
                $promocionesEspeciales,
                fn ($p) => ($p['modo_aplicacion'] ?? 'automatica') === 'automatica'
            ));

            // a) Aplicar forzadas secuencialmente (comportamiento legacy: greedy por prioridad)
            foreach ($forzadas as $promo) {
                $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $poolUnidades);
                if ($aplicacion['aplicada']) {
                    $this->consumirUnidadesPromoEspecial($poolUnidades, $promo, $aplicacion);
                    $resultado['promociones_especiales_aplicadas'][] = $this->armarResultadoPromoEspecial($promo, $aplicacion);
                    $resultado['total_descuentos'] += $aplicacion['descuento'];
                }
            }

            // b) Aplicar automáticas: elegir el subset que maximiza el ahorro sobre el pool restante
            if (! empty($automaticas)) {
                $mejor = $this->encontrarMejorCombinacionEspeciales($automaticas, $poolUnidades);
                foreach ($mejor['aplicaciones'] as $aplicacionGanadora) {
                    $promo = $aplicacionGanadora['promo'];
                    $aplicacion = $aplicacionGanadora['aplicacion'];
                    $this->consumirUnidadesPromoEspecial($poolUnidades, $promo, $aplicacion);
                    $resultado['promociones_especiales_aplicadas'][] = $this->armarResultadoPromoEspecial($promo, $aplicacion);
                    $resultado['total_descuentos'] += $aplicacion['descuento'];
                }
            }

            // 2. Aplicar promociones comunes a items (soporta combinabilidad)
            $promocionesComunes = $this->obtenerPromocionesComunes($contexto);

            // Calcular unidades libres por item para promociones comunes
            foreach ($itemsParaPromos as $itemIndex => &$itemPromo) {
                $unidadesDelItem = array_values(array_filter($poolUnidades, fn ($u) => $u['item_index'] === $itemIndex));
                $unidadesLibres = array_values(array_filter($unidadesDelItem, fn ($u) => ! ($u['consumida'] ?? false)));
                $cantidadLibre = count($unidadesLibres);
                $cantidadTotal = count($unidadesDelItem);

                // Solo excluir si TODAS las unidades fueron consumidas
                if ($cantidadLibre === 0 && $cantidadTotal > 0) {
                    $itemPromo['excluido_promociones'] = true;
                } elseif ($cantidadLibre < $cantidadTotal) {
                    // Hay unidades parcialmente consumidas - ajustar cantidad
                    $itemPromo['cantidad_original'] = $itemPromo['cantidad'];
                    $itemPromo['cantidad'] = $cantidadLibre;
                }
            }
            unset($itemPromo);

            $resultado['promociones_comunes_aplicadas'] = $this->aplicarPromocionesComunes(
                $promocionesComunes,
                $itemsParaPromos,
                $contexto
            );

            // Sumar descuentos de promociones comunes
            foreach ($resultado['promociones_comunes_aplicadas'] as $promoComun) {
                $resultado['total_descuentos'] += $promoComun['descuento'];
            }
        }

        // Preparar información de items con estado
        foreach ($this->items as $index => $item) {
            // Filtrar unidades de este item específico y reindexar
            $unidadesDelItem = array_values(array_filter($poolUnidades, fn ($u) => $u['item_index'] === $index));
            $unidadesConsumidas = array_values(array_filter($unidadesDelItem, fn ($u) => $u['consumida'] ?? false));
            $unidadesLibres = array_values(array_filter($unidadesDelItem, fn ($u) => ! ($u['consumida'] ?? false)));
            $articuloId = $item['articulo_id'] ?? null;
            $excluido = isset($articulosExcluidos[$articuloId]);

            // Obtener promociones comunes aplicadas a este item
            $promocionesComunes = $itemsParaPromos[$index]['promociones_comunes'] ?? [];
            $descuentoComun = $itemsParaPromos[$index]['total_descuento_comun'] ?? 0;

            // Obtener info completa de promociones especiales aplicadas a este item
            $promosEspecialesItem = [];
            foreach ($unidadesConsumidas as $unidad) {
                if (! empty($unidad['promo_especial_info'])) {
                    $promoKey = $unidad['promo_especial_info']['id'];
                    // Evitar duplicados usando el ID como clave
                    if (! isset($promosEspecialesItem[$promoKey])) {
                        $promosEspecialesItem[$promoKey] = $unidad['promo_especial_info'];
                    }
                }
            }

            $resultado['items'][$index] = [
                'articulo_id' => $articuloId,
                'nombre' => $item['nombre'],
                'precio_base' => (float) ($item['precio_base'] ?? $item['precio'] ?? 0),
                'precio_lista' => (float) ($item['precio'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'subtotal' => (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1),
                'unidades_consumidas' => count($unidadesConsumidas),
                'unidades_libres' => count($unidadesLibres),
                'excluido_promociones' => $excluido,
                'tiene_ajuste' => $item['tiene_ajuste'] ?? false,
                'promociones_especiales' => array_values($promosEspecialesItem), // Array de objetos completos
                'promociones_comunes' => $promocionesComunes,
                'descuento_comun' => $descuentoComun,
            ];
        }

        // Validar límite máximo de descuento (70% del subtotal)
        if ($resultado['subtotal'] > 0) {
            $maxDescuento = $resultado['subtotal'] * 0.70;
            if ($resultado['total_descuentos'] > $maxDescuento) {
                Log::warning("Descuento total {$resultado['total_descuentos']} excede 70% del subtotal {$resultado['subtotal']}");
                $resultado['total_descuentos'] = $maxDescuento;
            }
        }

        // Calcular total final (después de descuentos de promociones)
        $resultado['total_final'] = max(0, $resultado['subtotal'] - $resultado['total_descuentos']);

        // Aplicar descuento general monto_fijo (RF-32): se resta del total DESPUÉS de promociones
        $resultado['descuento_general_monto'] = 0;
        if ($this->descuentoGeneralActivo) {
            if ($this->descuentoGeneralTipo === 'monto_fijo') {
                $montoFijo = min($this->descuentoGeneralValor, $resultado['total_final']);
                $resultado['descuento_general_monto'] = round($montoFijo, 2);
                $resultado['total_final'] = max(0, $resultado['total_final'] - $montoFijo);
            } elseif ($this->descuentoGeneralTipo === 'porcentaje') {
                // Para %: el monto es la suma de los descuentos aplicados por renglón (ya está en los precios)
                $montoTotal = 0;
                foreach ($this->items as $item) {
                    if ($item['ajuste_manual_tipo'] === 'porcentaje' && $item['precio_sin_ajuste_manual'] !== null) {
                        $precioOriginal = (float) $item['precio_sin_ajuste_manual'];
                        $precioActual = (float) $item['precio'];
                        $montoTotal += ($precioOriginal - $precioActual) * (float) ($item['cantidad'] ?? 1);
                    }
                }
                $resultado['descuento_general_monto'] = round($montoTotal, 2);
            }
            $this->descuentoGeneralMonto = $resultado['descuento_general_monto'];
        }

        // Aplicar cupón (RF-17, RF-18): se resta del total DESPUÉS de desc. general
        $resultado['monto_cupon'] = 0;
        if ($this->cuponAplicado && $this->cuponInfo) {
            $cuponId = $this->cuponInfo['id'];
            $cupon = Cupon::find($cuponId);
            if ($cupon) {
                if ($cupon->aplicaATotal()) {
                    $descuento = $this->cuponService->calcularDescuento($cupon, $resultado['total_final']);
                    $this->cuponMontoDescuento = $descuento['monto_descuento'];
                } elseif ($cupon->aplicaAArticulos()) {
                    // Recalcular con límite de cantidad por artículo
                    $articulosCupon = $cupon->articulos()->get()->keyBy('id');
                    $bonificados = [];
                    $itemsParaCalculo = [];
                    foreach ($this->items as $item) {
                        $articuloId = $item['articulo_id'] ?? null;
                        if ($articuloId && $articulosCupon->has($articuloId)) {
                            $bonificados[] = $articuloId;
                            $itemsParaCalculo[] = $item;
                        }
                    }
                    $descuento = $this->cuponService->calcularDescuento(
                        $cupon, $resultado['total_final'], $bonificados, $itemsParaCalculo
                    );
                    $this->cuponMontoDescuento = $descuento['monto_descuento'];
                    $this->cuponArticulosBonificados = $bonificados;
                }
                $resultado['monto_cupon'] = $this->cuponMontoDescuento;
                $resultado['total_final'] = max(0, $resultado['total_final'] - $this->cuponMontoDescuento);
                $this->cuponInfo['monto_descuento'] = $this->cuponMontoDescuento;
            }
        }

        // Aplicar artículos canjeados con puntos (RF-10, RF-11): se restan del total
        $resultado['articulos_canjeados_monto'] = 0;
        foreach ($this->items as $item) {
            if ($item['pagado_con_puntos'] ?? false) {
                $resultado['articulos_canjeados_monto'] += (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
            }
        }
        if ($resultado['articulos_canjeados_monto'] > 0) {
            $resultado['total_final'] = max(0, $resultado['total_final'] - $resultado['articulos_canjeados_monto']);
        }

        // Aplicar canje de puntos como pago (RF-09): se resta del total
        $resultado['puntos_usados_monto'] = 0;
        if ($this->canjePuntosActivo && $this->canjePuntosMonto > 0) {
            $montoCanje = min($this->canjePuntosMonto, $resultado['total_final']);
            $resultado['puntos_usados_monto'] = round($montoCanje, 2);
            $resultado['total_final'] = max(0, $resultado['total_final'] - $montoCanje);
        }

        // Calcular desglose de IVA (incluye todos los descuentos)
        $totalDescuentosParaIva = $resultado['total_descuentos'];
        if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'monto_fijo') {
            $totalDescuentosParaIva += $resultado['descuento_general_monto'];
        }
        $totalDescuentosParaIva += $resultado['monto_cupon'];
        $totalDescuentosParaIva += $resultado['articulos_canjeados_monto'];
        $totalDescuentosParaIva += $resultado['puntos_usados_monto'];
        $resultado['desglose_iva'] = $this->calcularDesgloseIva(
            $resultado['items'],
            $totalDescuentosParaIva,
            $resultado['subtotal']
        );

        $this->resultado = $resultado;

        // Recalcular cuotas si la forma de pago permite cuotas (porque el total cambió)
        if ($this->formaPagoPermiteCuotas && $this->formaPagoId) {
            $this->cargarCuotasFormaPago();
        }

        // Recalcular ajuste de forma de pago si hay una seleccionada
        if ($this->formaPagoId) {
            $this->calcularAjusteFormaPago();
        }
    }

    /**
     * Calcula el desglose de IVA por alícuota
     *
     * Los precios de los items ya incluyen IVA, por lo que:
     * 1. Calculamos el neto de cada item: precio / (1 + alícuota/100)
     * 2. Agrupamos por código de alícuota
     * 3. Si hay descuentos, los prorrateamos proporcionalmente a los netos
     * 4. Recalculamos el IVA sobre los netos con descuento
     *
     * @param  array  $items  Items del resultado con precio y cantidad
     * @param  float  $totalDescuentos  Total de descuentos aplicados (promociones)
     * @param  float  $subtotal  Subtotal antes de descuentos
     * @return array Desglose por alícuota + totales
     */
    protected function calcularDesgloseIva(array $items, float $totalDescuentos, float $subtotal): array
    {
        // Inicializar acumuladores por alícuota
        $porAlicuota = [];

        // Calcular neto e IVA de cada item y agrupar
        foreach ($this->items as $index => $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $ivaCodigo = $item['iva_codigo'] ?? 5;
            $ivaPorcentaje = (float) ($item['iva_porcentaje'] ?? 21);
            $ivaNombre = $item['iva_nombre'] ?? 'IVA 21%';
            $precioIvaIncluido = $item['precio_iva_incluido'] ?? true;

            $subtotalItem = $precio * $cantidad;

            // Calcular neto e IVA del item
            if ($ivaPorcentaje == 0) {
                // Exento o No Gravado: todo es neto
                $netoItem = $subtotalItem;
                $ivaItem = 0;
            } elseif ($precioIvaIncluido) {
                // Precio incluye IVA: neto = precio / (1 + alícuota/100)
                $netoItem = $subtotalItem / (1 + $ivaPorcentaje / 100);
                $ivaItem = $subtotalItem - $netoItem;
            } else {
                // Precio no incluye IVA (raro pero posible)
                $netoItem = $subtotalItem;
                $ivaItem = $subtotalItem * ($ivaPorcentaje / 100);
            }

            // Inicializar alícuota si no existe
            if (! isset($porAlicuota[$ivaCodigo])) {
                $porAlicuota[$ivaCodigo] = [
                    'codigo' => $ivaCodigo,
                    'nombre' => $ivaNombre,
                    'porcentaje' => $ivaPorcentaje,
                    'neto_sin_descuento' => 0,
                    'iva_sin_descuento' => 0,
                    'subtotal_sin_descuento' => 0,
                    'neto' => 0,
                    'iva' => 0,
                    'subtotal' => 0,
                    'descuento_aplicado' => 0,
                ];
            }

            // Acumular valores sin descuento
            $porAlicuota[$ivaCodigo]['neto_sin_descuento'] += $netoItem;
            $porAlicuota[$ivaCodigo]['iva_sin_descuento'] += $ivaItem;
            $porAlicuota[$ivaCodigo]['subtotal_sin_descuento'] += $subtotalItem;
        }

        // Calcular totales sin descuento
        $totalNetoSinDesc = array_sum(array_column($porAlicuota, 'neto_sin_descuento'));
        $totalIvaSinDesc = array_sum(array_column($porAlicuota, 'iva_sin_descuento'));

        // Prorratear descuentos si los hay
        // IMPORTANTE: Los descuentos se aplican al precio CON IVA, por lo que
        // hay que prorratear sobre el subtotal (con IVA) y luego convertir a neto
        $totalSubtotalSinDesc = array_sum(array_column($porAlicuota, 'subtotal_sin_descuento'));

        if ($totalDescuentos > 0 && $totalSubtotalSinDesc > 0) {
            foreach ($porAlicuota as $codigo => &$alicuota) {
                // Proporción del subtotal (con IVA) de esta alícuota sobre el total
                $proporcion = $alicuota['subtotal_sin_descuento'] / $totalSubtotalSinDesc;

                // Descuento asignado a esta alícuota (con IVA incluido)
                $descuentoAlicuotaConIva = $totalDescuentos * $proporcion;

                // Convertir el descuento a neto (el descuento "incluye" IVA proporcionalmente)
                if ($alicuota['porcentaje'] > 0) {
                    $descuentoNetoAlicuota = $descuentoAlicuotaConIva / (1 + $alicuota['porcentaje'] / 100);
                } else {
                    $descuentoNetoAlicuota = $descuentoAlicuotaConIva; // Exento o no gravado
                }

                // Nuevo neto después del descuento
                $nuevoNeto = max(0, $alicuota['neto_sin_descuento'] - $descuentoNetoAlicuota);

                // Recalcular IVA sobre el nuevo neto
                $nuevoIva = $nuevoNeto * ($alicuota['porcentaje'] / 100);

                $alicuota['descuento_aplicado'] = round($descuentoAlicuotaConIva, 3);
                $alicuota['neto'] = round($nuevoNeto, 3);
                $alicuota['iva'] = round($nuevoIva, 3);
                $alicuota['subtotal'] = round($nuevoNeto + $nuevoIva, 3);
            }
            unset($alicuota);
        } else {
            // Sin descuentos: neto final = neto sin descuento
            foreach ($porAlicuota as $codigo => &$alicuota) {
                $alicuota['neto'] = round($alicuota['neto_sin_descuento'], 3);
                $alicuota['iva'] = round($alicuota['iva_sin_descuento'], 3);
                $alicuota['subtotal'] = round($alicuota['subtotal_sin_descuento'], 3);
            }
            unset($alicuota);
        }

        // Redondear valores sin descuento
        foreach ($porAlicuota as $codigo => &$alicuota) {
            $alicuota['neto_sin_descuento'] = round($alicuota['neto_sin_descuento'], 3);
            $alicuota['iva_sin_descuento'] = round($alicuota['iva_sin_descuento'], 3);
            $alicuota['subtotal_sin_descuento'] = round($alicuota['subtotal_sin_descuento'], 3);
        }
        unset($alicuota);

        // Ordenar por código de alícuota
        ksort($porAlicuota);

        // Calcular totales finales
        $totalNeto = array_sum(array_column($porAlicuota, 'neto'));
        $totalIva = array_sum(array_column($porAlicuota, 'iva'));
        $totalFinal = array_sum(array_column($porAlicuota, 'subtotal'));

        return [
            'por_alicuota' => array_values($porAlicuota),
            'total_neto' => round($totalNeto, 3),
            'total_iva' => round($totalIva, 3),
            'total' => round($totalFinal, 3),
            'descuento_aplicado' => round($totalDescuentos, 3),
        ];
    }

    /**
     * Crea un pool de unidades individuales para aplicar promociones
     */
    protected function crearPoolUnidades(): array
    {
        $pool = [];
        $idCounter = 0;

        foreach ($this->items as $itemIndex => $item) {
            $cantidad = max(1, (float) ($item['cantidad'] ?? 1));

            for ($i = 0; $i < $cantidad; $i++) {
                $pool[] = [
                    'id' => 'u_'.($idCounter++),
                    'item_index' => $itemIndex,
                    'articulo_id' => $item['articulo_id'],
                    'categoria_id' => $item['categoria_id'] ?? null,
                    'precio' => (float) ($item['precio'] ?? 0),
                    'consumida' => false,
                    'consumida_por' => null,
                    'excluido_promociones' => false,
                ];
            }
        }

        return $pool;
    }

    /**
     * Obtiene la información de promociones de la lista seleccionada
     */
    protected function obtenerInfoPromocionesLista(): array
    {
        $listaSeleccionada = collect($this->listasPreciosDisponibles)
            ->firstWhere('id', $this->listaPrecioId);

        if (! $listaSeleccionada) {
            return [
                'aplica_promociones' => true,
                'promociones_alcance' => 'todos',
            ];
        }

        return [
            'aplica_promociones' => $listaSeleccionada['aplica_promociones'] ?? true,
            'promociones_alcance' => $listaSeleccionada['promociones_alcance'] ?? 'todos',
        ];
    }

    // =========================================
    // PROMOCIONES ESPECIALES
    // =========================================

    protected function obtenerPromocionesEspeciales(array $contexto): array
    {
        $promociones = PromocionEspecial::where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('vigencia_desde')
                    ->orWhere('vigencia_desde', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('vigencia_hasta')
                    ->orWhere('vigencia_hasta', '>=', now());
            })
            ->with(['grupos.articulos', 'escalas'])
            ->orderBy('prioridad')
            ->get();

        return $promociones->filter(function ($promo) use ($contexto) {
            // Verificar usos disponibles
            if (! $promo->tieneUsosDisponibles()) {
                return false;
            }

            return $this->promocionEspecialCumpleCondiciones($promo, $contexto);
        })->map(function ($promo) {
            return $this->convertirPromocionEspecialAArray($promo);
        })->toArray();
    }

    protected function promocionEspecialCumpleCondiciones($promo, array $contexto): bool
    {
        // Verificar forma de venta (si la promo requiere una específica)
        if ($promo->forma_venta_id) {
            if (empty($contexto['forma_venta_id']) || $promo->forma_venta_id != $contexto['forma_venta_id']) {
                return false;
            }
        }

        // Verificar canal de venta
        if ($promo->canal_venta_id) {
            if (empty($contexto['canal_venta_id']) || $promo->canal_venta_id != $contexto['canal_venta_id']) {
                return false;
            }
        }

        // Verificar formas de pago
        $fpIds = $promo->formas_pago_ids ?? ($promo->forma_pago_id ? [$promo->forma_pago_id] : []);
        if (! empty($fpIds)) {
            if (empty($contexto['forma_pago_id']) || ! in_array($contexto['forma_pago_id'], $fpIds)) {
                return false;
            }
        }

        // Verificar día de la semana
        if (! empty($promo->dias_semana) && ! in_array($contexto['dia_semana'], $promo->dias_semana)) {
            return false;
        }

        // Verificar horario
        if ($promo->hora_desde && $contexto['hora'] < $promo->hora_desde) {
            return false;
        }
        if ($promo->hora_hasta && $contexto['hora'] > $promo->hora_hasta) {
            return false;
        }

        return true;
    }

    protected function convertirPromocionEspecialAArray($promo): array
    {
        return [
            'id' => $promo->id,
            'nombre' => $promo->nombre,
            'tipo' => $promo->tipo,
            'prioridad' => $promo->prioridad,
            'modo_aplicacion' => $promo->modo_aplicacion ?? 'automatica',
            // NxM básico
            'nxm_lleva' => $promo->nxm_lleva,
            'nxm_bonifica' => $promo->nxm_bonifica,
            'nxm_articulos_ids' => $promo->nxm_articulos_ids ?? ($promo->nxm_articulo_id ? [$promo->nxm_articulo_id] : []),
            'nxm_categorias_ids' => $promo->nxm_categorias_ids ?? ($promo->nxm_categoria_id ? [$promo->nxm_categoria_id] : []),
            'beneficio_tipo' => $promo->beneficio_tipo ?? 'gratis',
            'beneficio_porcentaje' => $promo->beneficio_porcentaje ?? 100,
            'usa_escalas' => $promo->usa_escalas,
            'escalas' => $promo->escalas->toArray(),
            // NxM avanzado
            'grupos_trigger' => $promo->gruposTrigger ? $promo->gruposTrigger->map(fn ($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            'grupos_reward' => $promo->gruposReward ? $promo->gruposReward->map(fn ($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            // Combo/Menu
            'precio_tipo' => $promo->precio_tipo,
            'precio_valor' => $promo->precio_valor,
            'grupos' => $promo->grupos->map(fn ($g) => [
                'nombre' => $g->nombre,
                'cantidad' => $g->cantidad,
                'articulos' => $g->articulos->map(fn ($a) => [
                    'id' => $a->id,
                    'precio' => $a->precio_base,
                ])->toArray(),
            ])->toArray(),
        ];
    }

    protected function intentarAplicarPromocionEspecial(array $promo, array $poolUnidades): array
    {
        // Filtrar solo unidades disponibles
        $unidadesDisponibles = array_filter($poolUnidades, fn ($u) => ! $u['consumida'] && ! ($u['excluido_promociones'] ?? false));

        return match ($promo['tipo']) {
            'nxm' => $this->aplicarNxMBasico($promo, $unidadesDisponibles),
            'nxm_avanzado' => $this->aplicarNxMAvanzado($promo, $unidadesDisponibles),
            'combo' => $this->aplicarCombo($promo, $unidadesDisponibles),
            'menu' => $this->aplicarMenu($promo, $unidadesDisponibles),
            default => ['aplicada' => false, 'razon' => 'Tipo de promoción no soportado'],
        };
    }

    /**
     * Marca las unidades consumidas por una promo especial sobre el pool pasado por referencia.
     */
    protected function consumirUnidadesPromoEspecial(array &$poolUnidades, array $promo, array $aplicacion): void
    {
        foreach ($aplicacion['unidades_consumidas'] as $unidadIdConsumida) {
            foreach ($poolUnidades as $idx => $unidad) {
                if ($unidad['id'] === $unidadIdConsumida) {
                    $poolUnidades[$idx]['consumida'] = true;
                    $poolUnidades[$idx]['consumida_por'] = $promo['nombre'];
                    $poolUnidades[$idx]['promo_especial_info'] = [
                        'id' => $promo['id'],
                        'promocion_especial_id' => $promo['id'],
                        'nombre' => $promo['nombre'],
                        'tipo' => $promo['tipo'],
                        'descuento' => $aplicacion['descuento'],
                    ];
                }
            }
        }
    }

    /**
     * Arma el objeto "promoción aplicada" para el array de resultado.
     */
    protected function armarResultadoPromoEspecial(array $promo, array $aplicacion): array
    {
        return [
            'id' => $promo['id'],
            'promocion_especial_id' => $promo['id'],
            'nombre' => $promo['nombre'],
            'tipo' => $promo['tipo'],
            'descuento' => $aplicacion['descuento'],
            'descripcion' => $aplicacion['descripcion'],
            'unidades_usadas' => count($aplicacion['unidades_consumidas']),
        ];
    }

    /**
     * Encuentra la mejor combinación de promociones especiales AUTOMÁTICAS que maximiza
     * el ahorro total del cliente. Evalúa subsets exhaustivamente si hay ≤10 promos,
     * greedy por descuento estimado si hay más.
     *
     * Dentro de cada subset, las promos se aplican en orden de prioridad.
     *
     * Retorna: ['descuento_total' => float, 'aplicaciones' => [['promo' => ..., 'aplicacion' => ...], ...]]
     */
    protected function encontrarMejorCombinacionEspeciales(array $promociones, array $poolInicial): array
    {
        $mejor = ['descuento_total' => 0.0, 'aplicaciones' => []];

        if (empty($promociones)) {
            return $mejor;
        }

        $n = count($promociones);

        if ($n <= 10) {
            // Exhaustivo: probar todos los subsets no vacíos (2^n - 1)
            $totalSubsets = 1 << $n;
            for ($mask = 1; $mask < $totalSubsets; $mask++) {
                $subset = [];
                for ($j = 0; $j < $n; $j++) {
                    if ($mask & (1 << $j)) {
                        $subset[] = $promociones[$j];
                    }
                }

                $resultado = $this->evaluarSubsetEspeciales($subset, $poolInicial);

                if ($resultado['descuento_total'] > $mejor['descuento_total']) {
                    $mejor = $resultado;
                }
            }
        } else {
            // Greedy: ordenar por descuento estimado DESC y aplicar secuencialmente
            $conDescuento = [];
            foreach ($promociones as $promo) {
                $prueba = $this->intentarAplicarPromocionEspecial($promo, $poolInicial);
                $conDescuento[] = [
                    'promo' => $promo,
                    'descuento' => $prueba['aplicada'] ? (float) $prueba['descuento'] : 0.0,
                ];
            }
            usort($conDescuento, fn ($a, $b) => $b['descuento'] <=> $a['descuento']);
            $ordenadas = array_map(fn ($item) => $item['promo'], $conDescuento);

            $mejor = $this->evaluarSubsetEspeciales($ordenadas, $poolInicial);
        }

        return $mejor;
    }

    /**
     * Aplica un subset de promociones especiales sobre una copia del pool y
     * retorna el descuento total acumulado + las aplicaciones efectivas.
     * Las promos se evalúan en orden de prioridad; si una no puede aplicarse, se salta.
     */
    protected function evaluarSubsetEspeciales(array $subset, array $poolInicial): array
    {
        usort($subset, fn ($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $pool = $poolInicial;
        $descuentoTotal = 0.0;
        $aplicaciones = [];

        foreach ($subset as $promo) {
            $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $pool);
            if ($aplicacion['aplicada']) {
                $this->consumirUnidadesPromoEspecial($pool, $promo, $aplicacion);
                $descuentoTotal += (float) $aplicacion['descuento'];
                $aplicaciones[] = ['promo' => $promo, 'aplicacion' => $aplicacion];
            }
        }

        return [
            'descuento_total' => $descuentoTotal,
            'aplicaciones' => $aplicaciones,
        ];
    }

    protected function aplicarNxMBasico(array $promo, array $unidadesDisponibles): array
    {
        // Filtrar unidades que aplican a esta promoción
        $unidadesAplicables = array_filter($unidadesDisponibles, function ($u) use ($promo) {
            $tieneRestriccion = ! empty($promo['nxm_articulos_ids']) || ! empty($promo['nxm_categorias_ids']);

            if (! $tieneRestriccion) {
                return false;
            }

            if (! empty($promo['nxm_articulos_ids']) && in_array($u['articulo_id'], $promo['nxm_articulos_ids'])) {
                return true;
            }

            if (! empty($promo['nxm_categorias_ids']) && in_array($u['categoria_id'], $promo['nxm_categorias_ids'])) {
                return true;
            }

            return false;
        });

        $cantidadDisponible = count($unidadesAplicables);

        // Determinar lleva/bonifica según escalas o valores fijos
        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && ! empty($promo['escalas'])) {
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (float) ($escala['cantidad_desde'] ?? 0);
                $hasta = (float) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
                if ($cantidadDisponible >= $desde && ($hasta === 0 || $cantidadDisponible <= $hasta)) {
                    $escalaAplicable = $escala;
                    break;
                }
            }

            if (! $escalaAplicable) {
                return ['aplicada' => false, 'razon' => 'No hay escala aplicable'];
            }

            $lleva = (int) $escalaAplicable['lleva'];
            $bonifica = (int) $escalaAplicable['bonifica'];
            $beneficioTipo = $escalaAplicable['beneficio_tipo'] ?? 'gratis';
            $beneficioPorcentaje = $escalaAplicable['beneficio_porcentaje'] ?? 100;
        }

        if ($cantidadDisponible < $lleva) {
            return ['aplicada' => false, 'razon' => "Necesita $lleva, hay $cantidadDisponible"];
        }

        // Ordenar por precio descendente para bonificar los más caros
        usort($unidadesAplicables, fn ($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = floor($cantidadDisponible / $lleva);
        $totalUnidadesEnPromo = $lleva * $vecesAplicable;
        $totalBonificadas = $bonifica * $vecesAplicable;
        $unidadesConsumidas = [];
        $descuentoTotal = 0;

        // Bonificar los N items más caros del pool completo
        for ($i = 0; $i < $totalBonificadas && $i < $totalUnidadesEnPromo; $i++) {
            $unidad = $unidadesAplicables[$i];
            if ($beneficioTipo === 'gratis') {
                $descuentoTotal += $unidad['precio'];
            } else {
                $descuentoTotal += $unidad['precio'] * ($beneficioPorcentaje / 100);
            }
        }

        // Marcar todas las unidades participantes como consumidas
        for ($i = 0; $i < $totalUnidadesEnPromo; $i++) {
            $unidadesConsumidas[] = $unidadesAplicables[$i]['id'];
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No se pudo aplicar'];
        }

        $descripcionBeneficio = $beneficioTipo === 'gratis' ? 'gratis' : "{$beneficioPorcentaje}% dto";

        return [
            'aplicada' => true,
            'descuento' => $descuentoTotal,
            'descripcion' => "Lleva {$lleva} → {$bonifica} {$descripcionBeneficio} (x{$vecesAplicable})",
            'unidades_consumidas' => $unidadesConsumidas,
        ];
    }

    protected function aplicarNxMAvanzado(array $promo, array $unidadesDisponibles): array
    {
        $triggerIds = [];
        foreach ($promo['grupos_trigger'] as $grupo) {
            $triggerIds = array_merge($triggerIds, $grupo['articulos_ids'] ?? []);
        }
        $rewardIds = [];
        foreach ($promo['grupos_reward'] as $grupo) {
            $rewardIds = array_merge($rewardIds, $grupo['articulos_ids'] ?? []);
        }

        $unidadesTrigger = array_values(array_filter($unidadesDisponibles, fn ($u) => in_array($u['articulo_id'], $triggerIds)));
        $unidadesReward = array_values(array_filter($unidadesDisponibles, fn ($u) => in_array($u['articulo_id'], $rewardIds)));

        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && ! empty($promo['escalas'])) {
            $cantidadTrigger = count($unidadesTrigger);
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (float) ($escala['cantidad_desde'] ?? 0);
                $hasta = (float) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
                if ($cantidadTrigger >= $desde && ($hasta === 0 || $cantidadTrigger <= $hasta)) {
                    $escalaAplicable = $escala;
                    break;
                }
            }

            if ($escalaAplicable) {
                $lleva = (int) $escalaAplicable['lleva'];
                $bonifica = (int) $escalaAplicable['bonifica'];
                $beneficioTipo = $escalaAplicable['beneficio_tipo'] ?? 'gratis';
                $beneficioPorcentaje = $escalaAplicable['beneficio_porcentaje'] ?? 100;
            }
        }

        if (count($unidadesTrigger) < $lleva) {
            return ['aplicada' => false, 'razon' => "Necesita {$lleva} triggers"];
        }

        if (count($unidadesReward) < $bonifica) {
            return ['aplicada' => false, 'razon' => "Necesita {$bonifica} rewards"];
        }

        usort($unidadesReward, fn ($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = min(
            floor(count($unidadesTrigger) / $lleva),
            floor(count($unidadesReward) / $bonifica)
        );

        $unidadesConsumidas = [];
        $descuentoTotal = 0;

        for ($vez = 0; $vez < $vecesAplicable; $vez++) {
            for ($i = 0; $i < $lleva; $i++) {
                $idx = $vez * $lleva + $i;
                if (isset($unidadesTrigger[$idx])) {
                    $unidadesConsumidas[] = $unidadesTrigger[$idx]['id'];
                }
            }

            for ($i = 0; $i < $bonifica; $i++) {
                $idx = $vez * $bonifica + $i;
                if (isset($unidadesReward[$idx])) {
                    $unidad = $unidadesReward[$idx];
                    $unidadesConsumidas[] = $unidad['id'];

                    if ($beneficioTipo === 'gratis') {
                        $descuentoTotal += $unidad['precio'];
                    } else {
                        $descuentoTotal += $unidad['precio'] * ($beneficioPorcentaje / 100);
                    }
                }
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No se pudo aplicar'];
        }

        $descripcionBeneficio = $beneficioTipo === 'gratis' ? 'gratis' : "{$beneficioPorcentaje}% dto";

        return [
            'aplicada' => true,
            'descuento' => $descuentoTotal,
            'descripcion' => "Lleva {$lleva} → {$bonifica} {$descripcionBeneficio} (x{$vecesAplicable})",
            'unidades_consumidas' => $unidadesConsumidas,
        ];
    }

    protected function aplicarCombo(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Combo sin artículos'];
        }

        $unidadesConsumidas = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (float) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = $grupo['articulos'] ?? [];

            if (empty($articulosDelGrupo)) {
                continue;
            }

            // Obtener todos los IDs de artículos válidos para este grupo
            $articulosIdsDelGrupo = array_column($articulosDelGrupo, 'id');

            // Buscar unidades de CUALQUIER artículo del grupo (no solo el primero)
            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn ($u) => in_array($u['articulo_id'], $articulosIdsDelGrupo) && ! in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => 'Faltan artículos para el grupo'];
            }

            // Ordenar por precio ascendente para consumir los más baratos primero
            usort($unidadesDeEsteGrupo, fn ($a, $b) => $a['precio'] <=> $b['precio']);

            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteGrupo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos'];
        }

        $descuento = 0;
        if ($promo['precio_tipo'] === 'fijo') {
            $precioCombo = (float) $promo['precio_valor'];
            $descuento = max(0, $precioNormal - $precioCombo);
        } else {
            $porcentajeDto = (float) $promo['precio_valor'];
            $descuento = $precioNormal * ($porcentajeDto / 100);
        }

        return [
            'aplicada' => true,
            'descuento' => $descuento,
            'descripcion' => $promo['precio_tipo'] === 'fijo'
                ? 'Combo a $'.number_format($promo['precio_valor'], 0, ',', '.')
                : "Combo con {$promo['precio_valor']}% dto",
            'unidades_consumidas' => $unidadesConsumidas,
        ];
    }

    protected function aplicarMenu(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Menú sin grupos'];
        }

        $unidadesConsumidas = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (float) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = array_column($grupo['articulos'] ?? [], 'id');

            if (empty($articulosDelGrupo)) {
                return ['aplicada' => false, 'razon' => "Grupo '{$grupo['nombre']}' sin artículos"];
            }

            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn ($u) => in_array($u['articulo_id'], $articulosDelGrupo) && ! in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => "Faltan artículos para '{$grupo['nombre']}'"];
            }

            usort($unidadesDeEsteGrupo, fn ($a, $b) => $a['precio'] <=> $b['precio']);

            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteGrupo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos'];
        }

        $descuento = 0;
        if ($promo['precio_tipo'] === 'fijo') {
            $precioMenu = (float) $promo['precio_valor'];
            $descuento = max(0, $precioNormal - $precioMenu);
        } else {
            $porcentajeDto = (float) $promo['precio_valor'];
            $descuento = $precioNormal * ($porcentajeDto / 100);
        }

        return [
            'aplicada' => true,
            'descuento' => $descuento,
            'descripcion' => $promo['precio_tipo'] === 'fijo'
                ? 'Menú a $'.number_format($promo['precio_valor'], 0, ',', '.')
                : "Menú con {$promo['precio_valor']}% dto",
            'unidades_consumidas' => $unidadesConsumidas,
        ];
    }

    // =========================================
    // PROMOCIONES COMUNES
    // =========================================

    protected function obtenerPromocionesComunes(array $contexto): array
    {
        $promociones = Promocion::where('sucursal_id', $this->sucursalId)
            ->activas()
            ->vigentes()
            ->conUsosDisponibles()
            ->automaticas() // Solo promociones que no requieren cupón (cupón se maneja aparte)
            ->with(['condiciones', 'escalas'])
            ->ordenadoPorPrioridad()
            ->get();

        // Filtrar por día de la semana y horario
        return $promociones->filter(function ($promo) use ($contexto) {
            // Verificar día de la semana
            if (! $promo->aplicaEnDiaSemana($contexto['dia_semana'])) {
                return false;
            }
            // Verificar horario
            if (! $promo->aplicaEnHorario($contexto['hora'])) {
                return false;
            }

            return true;
        })->map(function ($promo) {
            return $this->convertirPromocionComunAArray($promo);
        })->toArray();
    }

    protected function convertirPromocionComunAArray($promo): array
    {
        $condiciones = $promo->condiciones;
        $articulosIds = $condiciones->where('tipo_condicion', 'por_articulo')
            ->pluck('articulo_id')->filter()->values()->toArray();
        $categoriasIds = $condiciones->where('tipo_condicion', 'por_categoria')
            ->pluck('categoria_id')->filter()->values()->toArray();
        $condicionMontoMinimo = $condiciones->firstWhere('tipo_condicion', 'por_total_compra');
        $condicionCantidadMinima = $condiciones->firstWhere('tipo_condicion', 'por_cantidad');
        $formasPagoIds = $condiciones->where('tipo_condicion', 'por_forma_pago')
            ->pluck('forma_pago_id')->filter()->values()->toArray();
        $condicionFormaVenta = $condiciones->firstWhere('tipo_condicion', 'por_forma_venta');
        $condicionCanalVenta = $condiciones->firstWhere('tipo_condicion', 'por_canal');

        return [
            'id' => $promo->id,
            'nombre' => $promo->nombre,
            'tipo' => $promo->tipo,
            'valor' => $promo->valor,
            'prioridad' => $promo->prioridad,
            'combinable' => $promo->combinable,
            'escalas' => $promo->escalas->toArray(),
            'articulos_ids' => $articulosIds,
            'categorias_ids' => $categoriasIds,
            'monto_minimo' => $condicionMontoMinimo?->monto_minimo,
            'cantidad_minima' => $condicionCantidadMinima?->cantidad_minima,
            'formas_pago_ids' => $formasPagoIds,
            'forma_venta_id' => $condicionFormaVenta?->forma_venta_id,
            'canal_venta_id' => $condicionCanalVenta?->canal_venta_id,
            'dias_semana' => $promo->dias_semana,
            'hora_desde' => $promo->hora_desde,
            'hora_hasta' => $promo->hora_hasta,
        ];
    }

    /**
     * Verifica si una promoción cumple las condiciones del contexto de venta
     */
    protected function promocionCumpleCondiciones(array $promo, array $contexto): bool
    {
        // Verificar monto mínimo
        if (! empty($promo['monto_minimo'])) {
            if (($contexto['subtotal'] ?? 0) < (float) $promo['monto_minimo']) {
                return false;
            }
        }

        // Verificar cantidad mínima
        if (! empty($promo['cantidad_minima'])) {
            if (($contexto['cantidad_total'] ?? 0) < (float) $promo['cantidad_minima']) {
                return false;
            }
        }

        // Verificar forma de pago: si la promoción requiere formas de pago específicas
        if (! empty($promo['formas_pago_ids'])) {
            if (! empty($contexto['forma_pago_id'])) {
                if (! in_array($contexto['forma_pago_id'], $promo['formas_pago_ids'])) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar forma de venta
        if (! empty($promo['forma_venta_id'])) {
            if (! empty($contexto['forma_venta_id'])) {
                if ($promo['forma_venta_id'] != $contexto['forma_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar canal de venta
        if (! empty($promo['canal_venta_id'])) {
            if (! empty($contexto['canal_venta_id'])) {
                if ($promo['canal_venta_id'] != $contexto['canal_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar día de la semana
        if (! empty($promo['dias_semana']) && ! in_array($contexto['dia_semana'], $promo['dias_semana'])) {
            return false;
        }

        // Verificar horario
        if (! empty($promo['hora_desde']) && $contexto['hora'] < $promo['hora_desde']) {
            return false;
        }
        if (! empty($promo['hora_hasta']) && $contexto['hora'] > $promo['hora_hasta']) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si una promoción aplica a un item específico
     */
    protected function promocionAplicaAItem(array $promo, ?int $articuloId, ?int $categoriaId): bool
    {
        $tieneRestriccion = ! empty($promo['articulos_ids']) || ! empty($promo['categorias_ids']);

        if (! $tieneRestriccion) {
            return true;
        }

        // Aplica si el artículo está en la lista O pertenece a una categoría seleccionada
        if (! empty($promo['articulos_ids']) && in_array($articuloId, $promo['articulos_ids'])) {
            return true;
        }

        if (! empty($promo['categorias_ids']) && in_array($categoriaId, $promo['categorias_ids'])) {
            return true;
        }

        return false;
    }

    /**
     * Aplica promociones comunes a los items (soporta múltiples promociones combinables)
     */
    protected function aplicarPromocionesComunes(array $promociones, array &$items, array $contexto): array
    {
        $promocionesAplicadas = [];
        $cantidadTotal = array_sum(array_column($items, 'cantidad'));
        $subtotal = array_sum(array_map(fn ($i) => $i['precio'] * $i['cantidad'], $items));

        $contextoCompleto = array_merge($contexto, [
            'subtotal' => $subtotal,
            'cantidad_total' => $cantidadTotal,
        ]);

        // Filtrar promociones que cumplen condiciones generales
        $promocionesValidas = array_filter($promociones, fn ($p) => $this->promocionCumpleCondiciones($p, $contextoCompleto));

        // Procesar cada item
        foreach ($items as $itemIndex => &$item) {
            $articuloId = $item['articulo_id'];
            $categoriaId = $item['categoria_id'] ?? null;
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio'];
            $subtotalItem = $precioUnitario * $cantidad;

            // Saltar items excluidos de promociones
            if (! empty($item['excluido_promociones'])) {
                continue;
            }

            // Filtrar promociones que aplican a este item
            $promocionesParaItem = array_filter($promocionesValidas, fn ($p) => $this->promocionAplicaAItem($p, $articuloId, $categoriaId));

            if (empty($promocionesParaItem)) {
                continue;
            }

            // Encontrar la mejor combinación de promociones para este item
            $mejorCombinacion = $this->encontrarMejorCombinacion(
                array_values($promocionesParaItem),
                $subtotalItem,
                $cantidad
            );

            if (! empty($mejorCombinacion['promociones'])) {
                $item['promociones_comunes'] = [];
                $item['total_descuento_comun'] = 0;

                foreach ($mejorCombinacion['promociones'] as $promoAplicada) {
                    // Guardar objeto completo (no solo el nombre) para trazabilidad
                    $item['promociones_comunes'][] = $promoAplicada;
                    $item['total_descuento_comun'] += $promoAplicada['descuento'];

                    // Agregar al resumen global si no existe
                    $yaExiste = false;
                    foreach ($promocionesAplicadas as &$pa) {
                        if ($pa['id'] === $promoAplicada['id']) {
                            $pa['descuento'] += $promoAplicada['descuento'];
                            $pa['items_afectados'][] = $itemIndex;
                            $yaExiste = true;
                            break;
                        }
                    }
                    if (! $yaExiste) {
                        $promocionesAplicadas[] = [
                            'id' => $promoAplicada['id'],
                            'nombre' => $promoAplicada['nombre'],
                            'tipo' => $promoAplicada['tipo'],
                            'descuento' => $promoAplicada['descuento'],
                            'descripcion' => $promoAplicada['descripcion'],
                            'items_afectados' => [$itemIndex],
                        ];
                    }
                }
            }
        }

        return $promocionesAplicadas;
    }

    /**
     * Encuentra la mejor combinación de promociones para un item
     */
    protected function encontrarMejorCombinacion(array $promociones, float $montoInicial, int $cantidad): array
    {
        if (empty($promociones)) {
            return ['monto_final' => $montoInicial, 'promociones' => []];
        }

        // Separar excluyentes de combinables
        $excluyentes = array_filter($promociones, fn ($p) => ! $p['combinable']);
        $combinables = array_values(array_filter($promociones, fn ($p) => $p['combinable']));

        $mejorResultado = ['monto_final' => $montoInicial, 'promociones' => []];

        // 1. Evaluar cada excluyente por separado — O(n)
        foreach ($excluyentes as $promo) {
            $resultado = $this->calcularCombinacion([$promo], $montoInicial, $cantidad);
            if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                $mejorResultado = $resultado;
            }
        }

        // 2. Evaluar combinables
        if (! empty($combinables)) {
            $n = count($combinables);

            if ($n <= 15) {
                // Exhaustiva para sets pequeños — O(2^n)
                $totalCombinaciones = pow(2, $n);
                for ($i = 1; $i < $totalCombinaciones; $i++) {
                    $combinacion = [];
                    for ($j = 0; $j < $n; $j++) {
                        if ($i & (1 << $j)) {
                            $combinacion[] = $combinables[$j];
                        }
                    }
                    $resultado = $this->calcularCombinacion($combinacion, $montoInicial, $cantidad);
                    if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                        $mejorResultado = $resultado;
                    }
                }
            } else {
                // Greedy para sets grandes — O(n log n)
                $resultado = $this->calcularCombinacionGreedy($combinables, $montoInicial, $cantidad);
                if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                    $mejorResultado = $resultado;
                }
            }
        }

        return $mejorResultado;
    }

    /**
     * Fallback greedy para sets grandes de promociones combinables.
     */
    protected function calcularCombinacionGreedy(array $combinables, float $montoInicial, int $cantidad): array
    {
        $conDescuento = [];
        foreach ($combinables as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoInicial, $cantidad);
            $conDescuento[] = ['promo' => $promo, 'descuento_estimado' => $ajuste['valor']];
        }

        usort($conDescuento, fn ($a, $b) => $b['descuento_estimado'] <=> $a['descuento_estimado']);
        $ordenadas = array_map(fn ($item) => $item['promo'], $conDescuento);

        return $this->calcularCombinacion($ordenadas, $montoInicial, $cantidad);
    }

    /**
     * Calcula el resultado de aplicar una combinación de promociones
     */
    protected function calcularCombinacion(array $combinacion, float $montoInicial, int $cantidad): array
    {
        // Ordenar por prioridad
        usort($combinacion, fn ($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $montoActual = $montoInicial;
        $promocionesAplicadas = [];

        foreach ($combinacion as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoActual, $cantidad);

            if ($ajuste['valor'] > 0) {
                $esRecargo = in_array($promo['tipo'], ['recargo_porcentaje', 'recargo_monto']);
                $montoActual = $esRecargo
                    ? $montoActual + $ajuste['valor']
                    : $montoActual - $ajuste['valor'];
                $promocionesAplicadas[] = [
                    'id' => $promo['id'],
                    'promocion_id' => $promo['id'], // ID explícito para guardar en BD
                    'nombre' => $promo['nombre'],
                    'tipo' => $promo['tipo'],
                    'tipo_beneficio' => $this->mapearTipoBeneficio($promo['tipo']),
                    'valor' => $promo['valor'] ?? 0, // Valor original (%, monto, etc.)
                    'descuento' => $ajuste['valor'],
                    'descuento_item' => $ajuste['valor'], // Alias para guardarPromocionesDetalle
                    'descripcion' => $ajuste['descripcion'],
                ];
            }
        }

        return [
            'monto_final' => max(0, $montoActual),
            'promociones' => $promocionesAplicadas,
        ];
    }

    /**
     * Calcula el ajuste de una promoción sobre un monto
     */
    protected function calcularAjustePromocion(array $promo, float $monto, int $cantidad): array
    {
        $valor = 0;
        $descripcion = '';

        switch ($promo['tipo']) {
            case 'descuento_porcentaje':
                $porcentaje = (float) $promo['valor'];
                $valor = round($monto * ($porcentaje / 100), 2);
                $descripcion = "{$porcentaje}% dto";
                break;

            case 'descuento_monto':
                $valor = min((float) $promo['valor'], $monto);
                $descripcion = '$'.number_format($promo['valor'], 0, ',', '.').' dto';
                break;

            case 'precio_fijo':
                $precioFijoTotal = (float) $promo['valor'] * $cantidad;
                $valor = max(0, $monto - $precioFijoTotal);
                $descripcion = 'Precio fijo $'.number_format($promo['valor'], 0, ',', '.');
                break;

            case 'descuento_escalonado':
                if (! empty($promo['escalas'])) {
                    $escalas = collect($promo['escalas'])
                        ->filter(fn ($e) => ! empty($e['cantidad_desde']) && ! empty($e['valor']))
                        ->sortByDesc('cantidad_desde');

                    foreach ($escalas as $escala) {
                        if ($cantidad >= $escala['cantidad_desde']) {
                            $tipoDesc = $escala['tipo_descuento'] ?? 'porcentaje';
                            if ($tipoDesc === 'porcentaje') {
                                $porcentaje = (float) $escala['valor'];
                                $valor = round($monto * ($porcentaje / 100), 2);
                                $descripcion = "{$porcentaje}% dto escalonado";
                            } elseif ($tipoDesc === 'precio_fijo') {
                                $precioFijoTotal = (float) $escala['valor'] * $cantidad;
                                $valor = max(0, $monto - $precioFijoTotal);
                                $descripcion = 'Precio fijo escalonado $'.number_format($escala['valor'], 0, ',', '.');
                            } else {
                                $valor = min((float) $escala['valor'], $monto);
                                $descripcion = 'Monto fijo escalonado';
                            }
                            break;
                        }
                    }
                }
                break;
        }

        return ['valor' => $valor, 'descripcion' => $descripcion];
    }

    /**
     * Mapea el tipo de promoción al tipo_beneficio para la BD
     */
    protected function mapearTipoBeneficio(string $tipo): string
    {
        return match ($tipo) {
            'descuento_porcentaje' => 'porcentaje',
            'descuento_monto' => 'monto_fijo',
            'precio_fijo' => 'precio_especial',
            'descuento_escalonado' => 'porcentaje', // Generalmente es %
            default => 'porcentaje',
        };
    }

    // =========================================
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
