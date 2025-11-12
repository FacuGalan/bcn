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
     * Controla visibilidad de filtros en móvil
     * @var bool
     */
    public $showFilters = false;

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

        return view('livewire.ventas.ventas', [
            'ventas' => $ventas,
            'clientes' => $clientes,
            'cajas' => $cajas,
            'ventaDetalle' => $this->ventaDetalleId ? Venta::with(['detalles.articulo', 'cliente', 'caja'])->find($this->ventaDetalleId) : null,
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
        $query = Venta::with(['cliente', 'caja', 'usuario'])
                     ->where('sucursal_id', $this->obtenerSucursalActual());

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('numero_comprobante', 'like', "%{$this->search}%")
                  ->orWhereHas('cliente', function ($q2) {
                      $q2->where('nombre', 'like', "%{$this->search}%");
                  });
            });
        }

        // Filtro de estado
        if ($this->filterEstado !== 'all') {
            $query->where('estado', $this->filterEstado);
        }

        // Filtro de forma de pago
        if ($this->filterFormaPago !== 'all') {
            $query->where('forma_pago', $this->filterFormaPago);
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
            $cajaActual = caja_activa();
            if ($cajaActual) {
                $query->where('caja_id', $cajaActual);
            }
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
                $this->dispatch('toast-error', message: 'El artículo no está disponible en esta sucursal');
                return;
            }

            // Validar stock si controla stock
            if ($articulo->controla_stock) {
                if (!$articulo->tieneStockSuficiente($this->obtenerSucursalActual(), 1)) {
                    $this->dispatch('toast-error', message: 'Stock insuficiente para este artículo');
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
            $this->dispatch('toast-success', message: 'Artículo agregado al carrito');

        } catch (Exception $e) {
            Log::error('Error al agregar artículo al carrito', [
                'articulo_id' => $articuloId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: 'Error al agregar artículo: ' . $e->getMessage());
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
            $this->dispatch('toast-success', message: 'Artículo eliminado del carrito');
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
                $this->dispatch('toast-error', message: 'El carrito está vacío');
                return;
            }

            // Validar cliente si es cuenta corriente
            if ($this->formaPago === 'cta_cte' && !$this->clienteSeleccionado) {
                $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');
                return;
            }

            // Determinar la caja a usar
            $cajaId = $this->cajaSeleccionada ?? caja_activa();

            // Validar caja si no es cuenta corriente
            if ($this->formaPago !== 'cta_cte' && !$cajaId) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja o tener una caja activa');
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
            $this->dispatch('toast-success', message: "Venta #{$venta->numero_comprobante} creada exitosamente");
            $this->showPosModal = false;
            $this->resetPOS();

        } catch (Exception $e) {
            Log::error('Error al procesar venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: ' . $e->getMessage());
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
     * Cancela una venta
     *
     * @param int $ventaId
     */
    public function cancelarVenta($ventaId)
    {
        try {
            $this->ventaService->cancelarVenta($ventaId);
            $this->dispatch('toast-success', message: 'Venta cancelada exitosamente');

            if ($this->showDetalleModal) {
                $this->cerrarDetalle();
            }

        } catch (Exception $e) {
            Log::error('Error al cancelar venta', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: 'Error al cancelar venta: ' . $e->getMessage());
        }
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
}
