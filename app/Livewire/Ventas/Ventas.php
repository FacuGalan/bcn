<?php

namespace App\Livewire\Ventas;

use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\CajaAware;
use App\Services\VentaService;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Componente Livewire: Ventas / POS (Point of Sale)
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Listar ventas existentes con filtros y búsqueda
 * 2. Abrir modal de POS para crear nueva venta
 * 3. Buscar y agregar artículos al carrito de venta
 * 4. Calcular totales automáticamente (subtotal, IVA, descuentos, total)
 * 5. Validar stock disponible antes de agregar artículos
 * 6. Seleccionar cliente (opcional, obligatorio para cta_cte)
 * 7. Seleccionar forma de pago y caja
 * 8. Procesar la venta usando VentaService
 * 9. Ver detalles de ventas existentes
 * 10. Cancelar ventas (si tiene permisos)
 *
 * PROPIEDADES PRINCIPALES:
 * =======================
 * @property Collection $ventas - Lista paginada de ventas
 * @property array $carrito - Items en el carrito de venta actual
 * @property float $subtotal - Subtotal calculado del carrito
 * @property float $totalIva - IVA total calculado
 * @property float $descuentoGeneral - Descuento general aplicado
 * @property float $total - Total final de la venta
 * @property int|null $clienteSeleccionado - ID del cliente seleccionado
 * @property string $formaPago - Forma de pago (efectivo, debito, credito, cta_cte)
 * @property int|null $cajaSeleccionada - ID de la caja para registrar el pago
 *
 * MODALES:
 * ========
 * @property bool $showPosModal - Modal del POS para crear venta
 * @property bool $showDetalleModal - Modal para ver detalles de venta
 * @property bool $showBuscarArticuloModal - Modal para buscar artículos
 *
 * FILTROS:
 * ========
 * @property string $search - Búsqueda por número de comprobante, cliente
 * @property string $filterEstado - Filtro por estado (all, completada, pendiente, cancelada)
 * @property string $filterFormaPago - Filtro por forma de pago
 * @property string $filterFechaDesde - Fecha desde
 * @property string $filterFechaHasta - Fecha hasta
 *
 * FLUJO DE CREACIÓN DE VENTA:
 * ===========================
 * 1. Usuario hace clic en "Nueva Venta"
 * 2. Se abre modal de POS ($showPosModal = true)
 * 3. Usuario busca y agrega artículos al carrito
 * 4. Por cada artículo agregado:
 *    - Se valida stock disponible
 *    - Se calcula precio con IVA según configuración del artículo
 *    - Se agrega al array $carrito
 *    - Se recalculan los totales
 * 5. Usuario selecciona cliente (opcional, obligatorio para cta_cte)
 * 6. Usuario selecciona forma de pago
 * 7. Si forma de pago != cta_cte, usuario selecciona caja
 * 8. Usuario hace clic en "Procesar Venta"
 * 9. Se llama a procesarVenta() que:
 *    - Valida todos los datos
 *    - Llama a VentaService->crearVenta()
 *    - Muestra mensaje de éxito/error
 *    - Cierra modal y limpia carrito
 *    - Refresca lista de ventas
 *
 * CÁLCULOS AUTOMÁTICOS:
 * ====================
 * - calcularTotales(): Recalcula subtotal, IVA y total del carrito
 *   Se ejecuta automáticamente al:
 *   - Agregar artículo
 *   - Eliminar artículo
 *   - Cambiar cantidad
 *   - Cambiar descuento
 *   - Cambiar descuento general
 *
 * VALIDACIONES:
 * =============
 * - Stock disponible al agregar artículo
 * - Cliente obligatorio si forma_pago = cta_cte
 * - Caja obligatoria si forma_pago != cta_cte
 * - Carrito no vacío
 * - Cantidades > 0
 * - Precios > 0
 *
 * DEPENDENCIAS:
 * =============
 * - VentaService: Para crear y cancelar ventas
 * - Models: Venta, Cliente, Articulo, Caja, Sucursal
 *
 * PERMISOS REQUERIDOS:
 * ===================
 * - ventas.ver: Ver lista de ventas
 * - ventas.crear: Crear nuevas ventas
 * - ventas.cancelar: Cancelar ventas existentes
 *
 * FASE 4 - Sistema Multi-Sucursal (Componentes Livewire)
 *
 * @package App\Livewire\Ventas
 * @author BCN Pymes
 * @version 1.0.0
 */
class Ventas extends Component
{
    use WithPagination, CajaAware;

    // =========================================
    // PROPIEDADES DE LISTADO Y FILTROS
    // =========================================

    /**
     * Búsqueda por número de comprobante o nombre de cliente
     * @var string
     */
    public $search = '';

    /**
     * Filtro por estado de venta
     * Valores: 'all', 'completada', 'pendiente', 'cancelada'
     * @var string
     */
    public $filterEstado = 'all';

    /**
     * Filtro por forma de pago
     * Valores: 'all', 'efectivo', 'debito', 'credito', 'cta_cte'
     * @var string
     */
    public $filterFormaPago = 'all';

    /**
     * Filtro por fecha desde
     * @var string|null
     */
    public $filterFechaDesde = null;

    /**
     * Filtro por fecha hasta
     * @var string|null
     */
    public $filterFechaHasta = null;

    /**
     * Filtro por caja
     * Si es 'all', muestra todas las cajas
     * Si es 'actual', filtra por la caja activa
     * @var string
     */
    public $filterCaja = 'actual';

    /**
     * Filtro por comprobante fiscal
     * all = todas, con = con comprobante fiscal, sin = sin comprobante fiscal
     * @var string
     */
    public $filterComprobanteFiscal = 'all';

    /**
     * Controla visibilidad de filtros en móvil
     * @var bool
     */
    public $showFilters = false;

    // =========================================
    // PROPIEDADES DEL MODAL DE REIMPRESIÓN
    // =========================================

    /**
     * Controla visibilidad del modal de confirmación de reimpresión
     * @var bool
     */
    public $showReimprimirModal = false;

    /**
     * Tipo de documento a reimprimir: 'ticket' o 'fiscal'
     * @var string|null
     */
    public $reimprimirTipo = null;

    /**
     * ID del documento a reimprimir
     * @var int|null
     */
    public $reimprimirId = null;

    /**
     * Título del documento a reimprimir (para mostrar en el modal)
     * @var string
     */
    public $reimprimirTitulo = '';

    // =========================================
    // PROPIEDADES DEL MODAL DE CANCELACIÓN
    // =========================================

    /**
     * Controla visibilidad del modal de cancelación
     * @var bool
     */
    public $showCancelarModal = false;

    /**
     * ID de la venta a cancelar
     * @var int|null
     */
    public $cancelarVentaId = null;

    /**
     * Indica si la venta a cancelar permite conversión a cuenta corriente
     * (solo si NO es ya cuenta corriente y tiene cliente)
     * @var bool
     */
    public $cancelarPermiteCtaCte = false;

    /**
     * Motivo de cancelación ingresado por el usuario
     * @var string
     */
    public $cancelarMotivo = '';

    /**
     * Información de la venta a cancelar para mostrar en el modal
     * @var array
     */
    public $cancelarVentaInfo = [];

    /**
     * Indica si la venta tiene comprobantes fiscales (facturas autorizadas)
     * @var bool
     */
    public $cancelarTieneComprobanteFiscal = false;

    /**
     * Lista de comprobantes fiscales de la venta a cancelar
     * @var array
     */
    public $cancelarComprobantesFiscales = [];

    // =========================================
    // PROPIEDADES DEL POS / CARRITO
    // =========================================

    /**
     * Items en el carrito de venta
     * Estructura de cada item:
     * [
     *   'articulo_id' => int,
     *   'articulo' => Articulo (modelo completo),
     *   'cantidad' => float,
     *   'precio_unitario' => float,
     *   'descuento' => float,
     *   'subtotal' => float (cantidad * (precio_unitario - descuento))
     * ]
     * @var array
     */
    public $carrito = [];

    /**
     * ID del cliente seleccionado
     * @var int|null
     */
    public $clienteSeleccionado = null;

    /**
     * Búsqueda de artículos en el POS
     * @var string
     */
    public $buscarArticulo = '';

    /**
     * Forma de pago seleccionada
     * Valores: 'efectivo', 'debito', 'credito', 'cta_cte'
     * @var string
     */
    public $formaPago = 'efectivo';

    /**
     * ID de la caja seleccionada
     * @var int|null
     */
    public $cajaSeleccionada = null;

    /**
     * Tipo de comprobante
     * @var string
     */
    public $tipoComprobante = 'ticket';

    /**
     * Descuento general sobre el total (porcentaje)
     * @var float
     */
    public $descuentoGeneral = 0;

    /**
     * Observaciones de la venta
     * @var string|null
     */
    public $observaciones = null;

    // =========================================
    // PROPIEDADES DE TOTALES (CALCULADAS)
    // =========================================

    /**
     * Subtotal del carrito (sin IVA)
     * @var float
     */
    public $subtotal = 0;

    /**
     * Total de IVA
     * @var float
     */
    public $totalIva = 0;

    /**
     * Total final de la venta
     * @var float
     */
    public $total = 0;

    // =========================================
    // PROPIEDADES DE MODALES
    // =========================================

    /**
     * Controla visibilidad del modal de POS
     * @var bool
     */
    public $showPosModal = false;

    /**
     * Controla visibilidad del modal de detalles
     * @var bool
     */
    public $showDetalleModal = false;

    /**
     * ID de la venta para ver detalles
     * @var int|null
     */
    public $ventaDetalleId = null;

    // =========================================
    // INYECCIÓN DE DEPENDENCIAS
    // =========================================

    /**
     * Servicio de ventas
     * @var VentaService
     */
    protected $ventaService;

    /**
     * Escuchar evento de cambio de sucursal
     */
    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    /**
     * Constructor - Inyecta dependencias
     */
    public function boot(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    /**
     * Maneja el cambio de sucursal
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Cerrar modales si están abiertos
        $this->showPosModal = false;
        $this->showDetalleModal = false;

        // Limpiar carrito por seguridad (los datos son de otra sucursal)
        $this->resetPOS();

        // El componente se re-renderizará automáticamente con los datos de la nueva sucursal
    }

    /**
     * Cambia la caja activa
     * Este método es llamado desde el CajaSelector anidado
     */
    public function cambiarCaja($cajaId)
    {
        \App\Services\CajaService::establecerCajaActiva($cajaId);
        \App\Services\CajaService::clearCache();

        $caja = \App\Models\Caja::find($cajaId);
        if ($caja) {
            $this->dispatch('caja-changed', cajaId: $caja->id, cajaNombre: $caja->nombre);
        }
    }

    /**
     * Maneja el cambio de caja
     * Hook del trait CajaAware
     */
    protected function onCajaChanged($cajaId, $cajaNombre)
    {
        // Limpiar carrito por seguridad (la caja cambió)
        // El usuario debe iniciar una nueva venta con la nueva caja
        if ($this->showPosModal && !empty($this->carrito)) {
            $this->resetPOS();
        }

        // El componente se re-renderizará automáticamente
    }

    // =========================================
    // MÉTODOS DE CICLO DE VIDA
    // =========================================

    /**
     * Inicializa el componente
     */
    public function mount()
    {
        // Establecer fechas por defecto (último mes)
        $this->filterFechaDesde = now()->subMonth()->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        $ventas = $this->obtenerVentas();
        $clientes = Cliente::activos()
                           ->orderBy('nombre')
                           ->get();
        $cajas = Caja::porSucursal($this->obtenerSucursalActual())
                    ->activas()
                    ->get();

        // Obtener formas de pago activas de la sucursal
        $formasPago = \App\Models\FormaPagoSucursal::with('formaPago')
            ->porSucursal($this->obtenerSucursalActual())
            ->activos()
            ->get()
            ->pluck('formaPago')
            ->filter()
            ->sortBy('nombre');

        return view('livewire.ventas.ventas', [
            'ventas' => $ventas,
            'clientes' => $clientes,
            'cajas' => $cajas,
            'formasPago' => $formasPago,
            'ventaDetalle' => $this->ventaDetalleId ? Venta::with([
                'detalles.articulo',
                'cliente',
                'caja',
                'formaPago',
                'pagos.formaPago',
                'pagos.comprobanteFiscal',
                'promociones',
                'comprobantesFiscales',
                'usuario',
            ])->find($this->ventaDetalleId) : null,
        ]);
    }

    // =========================================
    // MÉTODOS DE LISTADO Y FILTROS
    // =========================================

    /**
     * Obtiene las ventas filtradas y paginadas
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function obtenerVentas()
    {
        $query = Venta::with(['cliente', 'caja', 'usuario', 'formaPago', 'comprobantesFiscales', 'pagos.formaPago'])
                     ->where('sucursal_id', $this->obtenerSucursalActual());

        // Filtro de búsqueda
        if ($this->search) {
            $searchTerm = trim($this->search);
            $query->where(function ($q) use ($searchTerm) {
                // 1. Buscar por ID de venta (si es numérico)
                if (is_numeric($searchTerm)) {
                    $q->where('id', $searchTerm);
                }

                // 2. Buscar por número de ticket (comprobante no fiscal)
                // SOLO si la venta NO tiene comprobante fiscal con es_total_venta = true
                // (porque si tiene factura por el total, el ticket no se muestra)
                $q->orWhere(function ($q2) use ($searchTerm) {
                    $q2->where('numero', 'like', "%{$searchTerm}%")
                       ->whereDoesntHave('comprobantesFiscales', function ($q3) {
                           $q3->where('es_total_venta', true);
                       });
                });

                // 3. Buscar por nombre de cliente
                $q->orWhereHas('cliente', function ($q2) use ($searchTerm) {
                    $q2->where('nombre', 'like', "%{$searchTerm}%")
                       ->orWhere('razon_social', 'like', "%{$searchTerm}%");
                });

                // 4. Buscar por comprobante fiscal (número formateado XXXX-XXXXXXXX)
                $q->orWhereHas('comprobantesFiscales', function ($q3) use ($searchTerm) {
                    // Si el término tiene formato XXXX-XXXXXXXX, extraer punto de venta y número
                    if (preg_match('/^(\d{1,4})-(\d{1,8})$/', $searchTerm, $matches)) {
                        $puntoVenta = intval($matches[1]);
                        $numeroComprobante = intval($matches[2]);
                        $q3->where('punto_venta_numero', $puntoVenta)
                           ->where('numero_comprobante', $numeroComprobante);
                    } else {
                        // Buscar parcial en número de comprobante o CAE
                        $q3->where('numero_comprobante', 'like', "%{$searchTerm}%")
                           ->orWhere('cae', 'like', "%{$searchTerm}%");
                    }
                });
            });
        }

        // Filtro de estado
        if ($this->filterEstado !== 'all') {
            $query->where('estado', $this->filterEstado);
        }

        // Filtro de forma de pago (busca en los pagos de la venta para incluir mixtas)
        if ($this->filterFormaPago !== 'all') {
            $formaPagoId = $this->filterFormaPago;
            $query->where(function ($q) use ($formaPagoId) {
                // Buscar en forma_pago_id principal O en los pagos de la venta
                $q->where('forma_pago_id', $formaPagoId)
                  ->orWhereHas('pagos', function ($q2) use ($formaPagoId) {
                      $q2->where('forma_pago_id', $formaPagoId)
                         ->where('estado', '!=', 'anulado');
                  });
            });
        }

        // Filtro de fechas
        if ($this->filterFechaDesde) {
            $query->whereDate('fecha', '>=', $this->filterFechaDesde);
        }

        if ($this->filterFechaHasta) {
            $query->whereDate('fecha', '<=', $this->filterFechaHasta);
        }

        // Filtro de caja
        if ($this->filterCaja === 'actual') {
            // Solo la caja activa
            $cajaActual = caja_activa();
            if ($cajaActual) {
                $query->where('caja_id', $cajaActual);
            }
        } elseif ($this->filterCaja === 'all') {
            // Todas las cajas a las que el usuario tiene acceso
            $cajasDisponibles = $this->cajasDisponibles();
            if ($cajasDisponibles->isNotEmpty()) {
                $query->whereIn('caja_id', $cajasDisponibles->pluck('id'));
            }
        }

        // Filtro por comprobante fiscal
        if ($this->filterComprobanteFiscal === 'con') {
            $query->whereHas('comprobantesFiscales');
        } elseif ($this->filterComprobanteFiscal === 'sin') {
            $query->whereDoesntHave('comprobantesFiscales');
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate(10);
    }

    /**
     * Obtiene la sucursal actual del usuario
     *
     * @return int
     */
    protected function obtenerSucursalActual()
    {
        return sucursal_activa() ?? Sucursal::activas()->first()->id ?? 1;
    }

    /**
     * Alterna visibilidad de filtros (móvil)
     */
    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Resetea los filtros a sus valores por defecto
     */
    public function resetFilters()
    {
        $this->search = '';
        $this->filterEstado = 'all';
        $this->filterFormaPago = 'all';
        $this->filterCaja = 'actual';
        $this->filterComprobanteFiscal = 'all';
        $this->filterFechaDesde = now()->subMonth()->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
    }

    // =========================================
    // MÉTODOS DEL POS / CARRITO
    // =========================================

    /**
     * Abre el modal del POS para nueva venta
     */
    public function abrirPOS()
    {
        $this->resetPOS();
        $this->showPosModal = true;
    }

    /**
     * Resetea todas las propiedades del POS
     */
    protected function resetPOS()
    {
        $this->carrito = [];
        $this->clienteSeleccionado = null;
        $this->buscarArticulo = '';
        $this->formaPago = 'efectivo';
        $this->cajaSeleccionada = null;
        $this->tipoComprobante = 'ticket';
        $this->descuentoGeneral = 0;
        $this->observaciones = null;
        $this->subtotal = 0;
        $this->totalIva = 0;
        $this->total = 0;
    }

    /**
     * Agrega un artículo al carrito
     *
     * @param int $articuloId
     */
    public function agregarAlCarrito($articuloId)
    {
        try {
            $articulo = Articulo::with('tipoIva')->findOrFail($articuloId);

            // Validar que el artículo esté disponible en la sucursal
            if (!$articulo->estaDisponibleEnSucursal($this->obtenerSucursalActual())) {
                $this->dispatch('toast-error', message: __('El artículo no está disponible en esta sucursal'));
                return;
            }

            // Validar stock si controla stock en esta sucursal
            if ($articulo->controlaStock($this->obtenerSucursalActual())) {
                if (!$articulo->tieneStockSuficiente($this->obtenerSucursalActual(), 1)) {
                    $this->dispatch('toast-error', message: __('Stock insuficiente para este artículo'));
                    return;
                }
            }

            // Verificar si ya está en el carrito
            $key = $this->buscarEnCarrito($articuloId);

            if ($key !== null) {
                // Ya está en el carrito, aumentar cantidad
                $this->carrito[$key]['cantidad']++;
            } else {
                // Obtener precio del artículo
                $precio = $articulo->obtenerPrecio($this->obtenerSucursalActual(), 'publico');
                $precioUnitario = $precio ? $precio->precio : 0;

                // Agregar al carrito
                $this->carrito[] = [
                    'articulo_id' => $articulo->id,
                    'articulo' => $articulo,
                    'cantidad' => 1,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => 0,
                    'subtotal' => $precioUnitario,
                ];
            }

            $this->calcularTotales();
            $this->buscarArticulo = ''; // Limpiar búsqueda
            $this->dispatch('toast-success', message: __('Artículo agregado al carrito'));

        } catch (Exception $e) {
            Log::error('Error al agregar artículo al carrito', [
                'articulo_id' => $articuloId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: __('Error al agregar artículo: ') . $e->getMessage());
        }
    }

    /**
     * Busca un artículo en el carrito por su ID
     *
     * @param int $articuloId
     * @return int|null Índice del artículo en el carrito, o null si no está
     */
    protected function buscarEnCarrito($articuloId)
    {
        foreach ($this->carrito as $key => $item) {
            if ($item['articulo_id'] == $articuloId) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Elimina un artículo del carrito
     *
     * @param int $index Índice del item en el array carrito
     */
    public function eliminarDelCarrito($index)
    {
        if (isset($this->carrito[$index])) {
            unset($this->carrito[$index]);
            $this->carrito = array_values($this->carrito); // Reindexar array
            $this->calcularTotales();
            $this->dispatch('toast-success', message: __('Artículo eliminado del carrito'));
        }
    }

    /**
     * Actualiza la cantidad de un artículo en el carrito
     *
     * @param int $index
     * @param float $cantidad
     */
    public function actualizarCantidad($index, $cantidad)
    {
        if (isset($this->carrito[$index])) {
            $cantidad = max(0, (float) $cantidad);

            if ($cantidad <= 0) {
                $this->eliminarDelCarrito($index);
                return;
            }

            $this->carrito[$index]['cantidad'] = $cantidad;
            $this->calcularTotales();
        }
    }

    /**
     * Actualiza el descuento de un artículo
     *
     * @param int $index
     * @param float $descuento
     */
    public function actualizarDescuento($index, $descuento)
    {
        if (isset($this->carrito[$index])) {
            $this->carrito[$index]['descuento'] = max(0, (float) $descuento);
            $this->calcularTotales();
        }
    }

    /**
     * Calcula los totales del carrito
     * Este método se ejecuta automáticamente cada vez que cambia el carrito
     */
    public function calcularTotales()
    {
        $this->subtotal = 0;
        $this->totalIva = 0;

        foreach ($this->carrito as &$item) {
            $articulo = $item['articulo'];
            $tipoIva = $articulo->tipoIva;

            // Calcular subtotal del item
            $precioConDescuento = $item['precio_unitario'] - $item['descuento'];
            $subtotalItem = $precioConDescuento * $item['cantidad'];

            // Calcular IVA según configuración del artículo
            if ($articulo->precio_iva_incluido) {
                // Precio incluye IVA, separar
                $precioSinIva = $tipoIva->obtenerPrecioSinIva($subtotalItem, true);
                $ivaItem = $subtotalItem - $precioSinIva;
                $this->subtotal += $precioSinIva;
            } else {
                // Precio no incluye IVA, calcular
                $ivaItem = $tipoIva->calcularIva($subtotalItem, false);
                $this->subtotal += $subtotalItem;
            }

            $this->totalIva += $ivaItem;
            $item['subtotal'] = $subtotalItem + $ivaItem;
        }

        // Aplicar descuento general
        $montoDescuentoGeneral = ($this->subtotal + $this->totalIva) * ($this->descuentoGeneral / 100);
        $this->total = $this->subtotal + $this->totalIva - $montoDescuentoGeneral;

        // Asegurar que no sea negativo
        $this->total = max(0, $this->total);
    }

    // =========================================
    // MÉTODOS DE PROCESAMIENTO DE VENTA
    // =========================================

    /**
     * Procesa la venta y la crea en la base de datos
     */
    public function procesarVenta()
    {
        try {
            // Validar carrito no vacío
            if (empty($this->carrito)) {
                $this->dispatch('toast-error', message: __('El carrito está vacío'));
                return;
            }

            // Validar cliente si es cuenta corriente
            if ($this->formaPago === 'cta_cte' && !$this->clienteSeleccionado) {
                $this->dispatch('toast-error', message: __('Debe seleccionar un cliente para ventas a cuenta corriente'));
                return;
            }

            // Determinar la caja a usar
            $cajaId = $this->cajaSeleccionada ?? caja_activa();

            // Validar caja si no es cuenta corriente
            if ($this->formaPago !== 'cta_cte' && !$cajaId) {
                $this->dispatch('toast-error', message: __('Debe seleccionar una caja o tener una caja activa'));
                return;
            }

            // Preparar datos de la venta
            $datosVenta = [
                'sucursal_id' => $this->obtenerSucursalActual(),
                'cliente_id' => $this->clienteSeleccionado,
                'caja_id' => $cajaId, // Usa la caja seleccionada o la activa
                'usuario_id' => Auth::id(),
                'tipo_comprobante' => $this->tipoComprobante,
                'forma_pago' => $this->formaPago,
                'descuento' => ($this->subtotal + $this->totalIva) * ($this->descuentoGeneral / 100),
                'observaciones' => $this->observaciones,
                'total' => $this->total,
            ];

            // Preparar detalles
            $detalles = [];
            foreach ($this->carrito as $item) {
                $detalles[] = [
                    'articulo_id' => $item['articulo_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento' => $item['descuento'],
                ];
            }

            // Crear la venta usando el servicio
            $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

            // Éxito
            $this->dispatch('toast-success', message: __('Venta #:numero creada exitosamente', ['numero' => $venta->numero_comprobante]));
            $this->showPosModal = false;
            $this->resetPOS();

        } catch (Exception $e) {
            Log::error('Error al procesar venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast-error', message: __('Error al procesar venta: ') . $e->getMessage());
        }
    }

    /**
     * Cancela el POS y cierra el modal
     */
    public function cancelarPOS()
    {
        $this->showPosModal = false;
        $this->resetPOS();
    }

    // =========================================
    // MÉTODOS DE DETALLES Y ACCIONES
    // =========================================

    /**
     * Abre el modal de detalles de una venta
     *
     * @param int $ventaId
     */
    public function verDetalle($ventaId)
    {
        $this->ventaDetalleId = $ventaId;
        $this->showDetalleModal = true;
    }

    /**
     * Cierra el modal de detalles
     */
    public function cerrarDetalle()
    {
        $this->showDetalleModal = false;
        $this->ventaDetalleId = null;
    }

    /**
     * Abre el modal de cancelación con las opciones disponibles
     *
     * @param int $ventaId
     */
    public function abrirCancelarModal($ventaId)
    {
        try {
            $venta = Venta::with(['cliente', 'pagos', 'comprobantesFiscales'])->findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                $this->dispatch('toast-error', message: __('La venta ya está cancelada'));
                return;
            }

            $this->cancelarVentaId = $ventaId;
            $this->cancelarMotivo = '';

            // Determinar si permite conversión a cuenta corriente
            // Solo si NO es ya cuenta corriente Y tiene cliente asignado
            $this->cancelarPermiteCtaCte = !$venta->es_cuenta_corriente && $venta->cliente_id !== null;

            // Detectar comprobantes fiscales autorizados y calcular saldo neto
            $todosComprobantes = $venta->comprobantesFiscales()
                ->autorizados()
                ->get();

            // Calcular saldo neto fiscal: facturas suman, notas de crédito restan
            $saldoFiscal = 0;
            $facturasPendientes = [];

            foreach ($todosComprobantes as $cf) {
                if ($cf->esFactura()) {
                    $saldoFiscal += floatval($cf->total);
                    $facturasPendientes[] = [
                        'id' => $cf->id,
                        'tipo' => $cf->tipo_legible,
                        'numero' => $cf->numero_formateado,
                        'total' => $cf->total,
                        'cae' => $cf->cae,
                    ];
                } elseif ($cf->esNotaCredito()) {
                    $saldoFiscal -= floatval($cf->total);
                }
            }

            // Si el saldo fiscal es 0 o menor, las facturas ya fueron anuladas
            // con notas de crédito y no se requiere emitir más
            $this->cancelarTieneComprobanteFiscal = $saldoFiscal > 0.01; // tolerancia para decimales
            $this->cancelarComprobantesFiscales = $this->cancelarTieneComprobanteFiscal ? $facturasPendientes : [];

            // Preparar información de pagos
            $pagosInfo = $venta->pagos->map(function ($pago) {
                return [
                    'id' => $pago->id,
                    'forma_pago' => $pago->formaPago?->nombre ?? __('Sin especificar'),
                    'monto' => $pago->monto_final,
                    'facturado' => $pago->comprobante_fiscal_id !== null,
                    'estado' => $pago->estado,
                ];
            })->toArray();

            // Preparar información para mostrar en el modal
            $this->cancelarVentaInfo = [
                'numero' => $venta->numero,
                'fecha' => $venta->fecha->format('d/m/Y H:i'),
                'total' => $venta->total_final,
                'cliente' => $venta->cliente?->nombre ?? __('Sin cliente'),
                'es_cuenta_corriente' => $venta->es_cuenta_corriente,
                'pagos' => $pagosInfo,
            ];

            $this->showCancelarModal = true;

        } catch (Exception $e) {
            Log::error('Error al abrir modal de cancelación', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: __('Error al cargar datos de la venta'));
        }
    }

    /**
     * Cierra el modal de cancelación
     */
    public function cerrarCancelarModal()
    {
        $this->showCancelarModal = false;
        $this->cancelarVentaId = null;
        $this->cancelarPermiteCtaCte = false;
        $this->cancelarMotivo = '';
        $this->cancelarVentaInfo = [];
        $this->cancelarTieneComprobanteFiscal = false;
        $this->cancelarComprobantesFiscales = [];
    }

    /**
     * Cancela la venta completamente
     * (revierte stock, pagos, saldo cliente si aplica)
     * Si tiene comprobantes fiscales, emite notas de crédito
     */
    public function ejecutarCancelacionCompleta()
    {
        try {
            $resultado = $this->ventaService->cancelarVentaCompleta(
                $this->cancelarVentaId,
                $this->cancelarMotivo ?: null,
                true // emitir nota de crédito si tiene comprobantes fiscales
            );

            $mensaje = __('Venta cancelada completamente');
            if (!empty($resultado['notas_credito'])) {
                $cantNC = count($resultado['notas_credito']);
                $mensaje .= '. ' . __('Se emitieron :cantidad nota(s) de crédito.', ['cantidad' => $cantNC]);
            }

            $this->dispatch('toast-success', message: $mensaje);
            $this->cerrarCancelarModal();

            if ($this->showDetalleModal) {
                $this->cerrarDetalle();
            }

        } catch (Exception $e) {
            Log::error('Error al cancelar venta completa', [
                'venta_id' => $this->cancelarVentaId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: __('Error al cancelar: ') . $e->getMessage());
        }
    }

    /**
     * Anula los pagos y convierte la venta a cuenta corriente
     * NO emite nota de crédito (mantiene la facturación fiscal)
     */
    public function ejecutarConversionACtaCte()
    {
        try {
            $this->ventaService->anularPagosYPasarACtaCte(
                $this->cancelarVentaId,
                $this->cancelarMotivo ?: null
            );

            $this->dispatch('toast-success', message: __('Pagos anulados. La venta se pasó a cuenta corriente.'));
            $this->cerrarCancelarModal();

            if ($this->showDetalleModal) {
                $this->cerrarDetalle();
            }

        } catch (Exception $e) {
            Log::error('Error al convertir venta a cuenta corriente', [
                'venta_id' => $this->cancelarVentaId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: __('Error: ') . $e->getMessage());
        }
    }

    /**
     * Anula solo la parte fiscal de la venta
     * - Emite nota de crédito para cada comprobante fiscal
     * - NO cancela la venta ni los pagos
     * - NO revierte stock
     * - Desmarca los pagos como facturados
     */
    public function ejecutarAnulacionFiscal()
    {
        try {
            $resultado = $this->ventaService->anularSoloParteFiscal(
                $this->cancelarVentaId,
                $this->cancelarMotivo ?: null
            );

            $cantNC = count($resultado['notas_credito']);
            $this->dispatch('toast-success', message: __('Se emitieron :cantidad nota(s) de crédito. La venta permanece activa.', ['cantidad' => $cantNC]));
            $this->cerrarCancelarModal();

            if ($this->showDetalleModal) {
                $this->cerrarDetalle();
            }

        } catch (Exception $e) {
            Log::error('Error al anular parte fiscal', [
                'venta_id' => $this->cancelarVentaId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: __('Error: ') . $e->getMessage());
        }
    }

    /**
     * Cancela una venta (método legacy - ahora abre el modal de opciones)
     *
     * @param int $ventaId
     */
    public function cancelarVenta($ventaId)
    {
        $this->abrirCancelarModal($ventaId);
    }

    // =========================================
    // EVENTOS LIVEWIRE
    // =========================================

    /**
     * Se ejecuta cuando cambia la búsqueda
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * Se ejecuta cuando cambia el filtro de estado
     */
    public function updatedFilterEstado()
    {
        $this->resetPage();
    }

    /**
     * Se ejecuta cuando cambia el filtro de forma de pago
     */
    public function updatedFilterFormaPago()
    {
        $this->resetPage();
    }

    /**
     * Se ejecuta cuando cambia el filtro de caja
     */
    public function updatedFilterCaja()
    {
        $this->resetPage();
    }

    /**
     * Se ejecuta cuando cambia la forma de pago en el POS
     * Si cambia a cta_cte, deselecciona la caja
     */
    public function updatedFormaPago()
    {
        if ($this->formaPago === 'cta_cte') {
            $this->cajaSeleccionada = null;
        }
    }

    /**
     * Se ejecuta cuando cambia el descuento general
     */
    public function updatedDescuentoGeneral()
    {
        $this->calcularTotales();
    }

    // =========================================
    // MÉTODOS DE REIMPRESIÓN
    // =========================================

    /**
     * Abre el modal para confirmar reimpresión de ticket
     */
    public function confirmarReimprimirTicket($ventaId, $numero)
    {
        $this->reimprimirTipo = 'ticket';
        $this->reimprimirId = $ventaId;
        $this->reimprimirTitulo = __('Ticket de Venta #:numero', ['numero' => $numero]);
        $this->showReimprimirModal = true;
    }

    /**
     * Abre el modal para confirmar reimpresión de comprobante fiscal
     */
    public function confirmarReimprimirFiscal($comprobanteId, $tipoLegible, $numero)
    {
        $this->reimprimirTipo = 'fiscal';
        $this->reimprimirId = $comprobanteId;
        $this->reimprimirTitulo = "{$tipoLegible} {$numero}";
        $this->showReimprimirModal = true;
    }

    /**
     * Ejecuta la reimpresión confirmada
     */
    public function ejecutarReimpresion()
    {
        if ($this->reimprimirTipo === 'ticket') {
            $this->dispatch('imprimir-ticket', ventaId: $this->reimprimirId);
            $this->dispatch('toast-info', message: __('Enviando ticket a impresión...'));
        } elseif ($this->reimprimirTipo === 'fiscal') {
            $this->dispatch('imprimir-comprobante-fiscal', comprobanteId: $this->reimprimirId);
            $this->dispatch('toast-info', message: __('Enviando comprobante fiscal a impresión...'));
        }

        $this->cerrarReimprimirModal();
    }

    /**
     * Cierra el modal de reimpresión
     */
    public function cerrarReimprimirModal()
    {
        $this->showReimprimirModal = false;
        $this->reimprimirTipo = null;
        $this->reimprimirId = null;
        $this->reimprimirTitulo = '';
    }

    /**
     * Reimprimir ticket de venta (método directo para el modal de detalle)
     */
    public function reimprimirTicket($ventaId)
    {
        $venta = Venta::find($ventaId);
        if ($venta) {
            $this->confirmarReimprimirTicket($ventaId, $venta->numero);
        }
    }

    /**
     * Reimprimir comprobante fiscal (método directo para el modal de detalle)
     */
    public function reimprimirComprobanteFiscal($comprobanteId)
    {
        $comprobante = \App\Models\ComprobanteFiscal::find($comprobanteId);
        if ($comprobante) {
            $this->confirmarReimprimirFiscal($comprobanteId, $comprobante->tipo_legible, $comprobante->numero_formateado);
        }
    }
}
