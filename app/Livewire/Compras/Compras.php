<?php

namespace App\Livewire\Compras;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\CompraService;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Componente Livewire: Compras
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Listar compras existentes con filtros y búsqueda
 * 2. Abrir modal para crear nueva compra
 * 3. Buscar y agregar artículos al carrito de compra
 * 4. Calcular totales automáticamente (subtotal, IVA - crédito fiscal, total)
 * 5. Seleccionar proveedor (obligatorio)
 * 6. Seleccionar forma de pago y caja
 * 7. Procesar la compra usando CompraService
 * 8. Ver detalles de compras existentes
 * 9. Cancelar compras (si tiene permisos)
 * 10. Registrar pagos para compras a cuenta corriente
 *
 * PROPIEDADES PRINCIPALES:
 * =======================
 * @property Collection $compras - Lista paginada de compras
 * @property array $carrito - Items en el carrito de compra actual
 * @property float $subtotal - Subtotal calculado del carrito (sin IVA)
 * @property float $totalIva - IVA total calculado (crédito fiscal)
 * @property float $total - Total final de la compra
 * @property int|null $proveedorSeleccionado - ID del proveedor seleccionado
 * @property string $formaPago - Forma de pago (efectivo, debito, credito, cta_cte)
 * @property int|null $cajaSeleccionada - ID de la caja para registrar el pago
 *
 * MODALES:
 * ========
 * @property bool $showCompraModal - Modal para crear compra
 * @property bool $showDetalleModal - Modal para ver detalles de compra
 * @property bool $showPagoModal - Modal para registrar pago
 *
 * FILTROS:
 * ========
 * @property string $search - Búsqueda por número de comprobante, proveedor
 * @property string $filterEstado - Filtro por estado (all, completada, pendiente, cancelada)
 * @property string $filterFormaPago - Filtro por forma de pago
 * @property string $filterFechaDesde - Fecha desde
 * @property string $filterFechaHasta - Fecha hasta
 *
 * FLUJO DE CREACIÓN DE COMPRA:
 * ===========================
 * 1. Usuario hace clic en "Nueva Compra"
 * 2. Se abre modal de compra ($showCompraModal = true)
 * 3. Usuario selecciona proveedor (obligatorio)
 * 4. Usuario busca y agrega artículos al carrito
 * 5. Por cada artículo agregado:
 *    - Se especifica cantidad y precio unitario sin IVA
 *    - Se calcula IVA según tipo de artículo
 *    - Se agrega al array $carrito
 *    - Se recalculan los totales
 * 6. Usuario selecciona forma de pago
 * 7. Si forma de pago != cta_cte, usuario selecciona caja
 * 8. Usuario hace clic en "Procesar Compra"
 * 9. Se llama a procesarCompra() que:
 *    - Valida todos los datos
 *    - Llama a CompraService->crearCompra()
 *    - Muestra mensaje de éxito/error
 *    - Cierra modal y limpia carrito
 *    - Refresca lista de compras
 *
 * CÁLCULOS AUTOMÁTICOS:
 * ====================
 * - calcularTotales(): Recalcula subtotal, IVA y total del carrito
 *   En compras, el IVA es CRÉDITO FISCAL (se suma al total pero se recupera)
 *   Se ejecuta automáticamente al:
 *   - Agregar artículo
 *   - Eliminar artículo
 *   - Cambiar cantidad
 *   - Cambiar precio
 *
 * VALIDACIONES:
 * =============
 * - Proveedor obligatorio
 * - Caja obligatoria si forma_pago != cta_cte
 * - Saldo suficiente en caja si forma_pago = efectivo
 * - Carrito no vacío
 * - Cantidades > 0
 * - Precios > 0
 *
 * DIFERENCIAS CON VENTAS:
 * ======================
 * - Compras AUMENTAN stock (ventas lo disminuyen)
 * - Compras generan EGRESOS de caja (ventas generan ingresos)
 * - Compras tienen CRÉDITO FISCAL de IVA (ventas tienen débito fiscal)
 * - Compras requieren PROVEEDOR (ventas tienen cliente opcional)
 * - Compras no validan stock disponible (las ventas sí)
 *
 * DEPENDENCIAS:
 * =============
 * - CompraService: Para crear y cancelar compras, registrar pagos
 * - Models: Compra, Proveedor, Articulo, Caja, Sucursal
 *
 * PERMISOS REQUERIDOS:
 * ===================
 * - compras.ver: Ver lista de compras
 * - compras.crear: Crear nuevas compras
 * - compras.cancelar: Cancelar compras existentes
 * - compras.pagar: Registrar pagos a compras en cta_cte
 *
 * FASE 4 - Sistema Multi-Sucursal (Componentes Livewire)
 *
 * @package App\Livewire\Compras
 * @author BCN Pymes
 * @version 1.0.0
 */
class Compras extends Component
{
    use WithPagination;

    // =========================================
    // PROPIEDADES DE LISTADO Y FILTROS
    // =========================================

    /**
     * Búsqueda por número de comprobante o nombre de proveedor
     * @var string
     */
    public $search = '';

    /**
     * Filtro por estado de compra
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
     * Controla visibilidad de filtros en móvil
     * @var bool
     */
    public $showFilters = false;

    // =========================================
    // PROPIEDADES DEL FORMULARIO / CARRITO
    // =========================================

    /**
     * Items en el carrito de compra
     * Estructura de cada item:
     * [
     *   'articulo_id' => int,
     *   'articulo' => Articulo (modelo completo),
     *   'cantidad' => float,
     *   'precio_sin_iva' => float,
     *   'iva_monto' => float,
     *   'subtotal' => float (precio_sin_iva * cantidad + iva_monto)
     * ]
     * @var array
     */
    public $carrito = [];

    /**
     * ID del proveedor seleccionado
     * @var int|null
     */
    public $proveedorSeleccionado = null;

    /**
     * Búsqueda de artículos
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
    public $tipoComprobante = 'factura_a';

    /**
     * Número de comprobante (ingreso manual)
     * @var string|null
     */
    public $numeroComprobante = null;

    /**
     * Fecha de la compra
     * @var string
     */
    public $fechaCompra = null;

    /**
     * Observaciones de la compra
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
     * Total de IVA (crédito fiscal)
     * @var float
     */
    public $totalIva = 0;

    /**
     * Total final de la compra
     * @var float
     */
    public $total = 0;

    // =========================================
    // PROPIEDADES DE MODALES
    // =========================================

    /**
     * Controla visibilidad del modal de compra
     * @var bool
     */
    public $showCompraModal = false;

    /**
     * Controla visibilidad del modal de detalles
     * @var bool
     */
    public $showDetalleModal = false;

    /**
     * ID de la compra para ver detalles
     * @var int|null
     */
    public $compraDetalleId = null;

    /**
     * Controla visibilidad del modal de pago
     * @var bool
     */
    public $showPagoModal = false;

    /**
     * ID de la compra para registrar pago
     * @var int|null
     */
    public $compraPagoId = null;

    /**
     * Monto del pago a registrar
     * @var float
     */
    public $montoPago = 0;

    /**
     * Caja para registrar el pago
     * @var int|null
     */
    public $cajaPago = null;

    // =========================================
    // INYECCIÓN DE DEPENDENCIAS
    // =========================================

    /**
     * Servicio de compras
     * @var CompraService
     */
    protected $compraService;

    /**
     * Constructor - Inyecta dependencias
     */
    public function boot(CompraService $compraService)
    {
        $this->compraService = $compraService;
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
        $this->fechaCompra = now()->format('Y-m-d');
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        $compras = $this->obtenerCompras();
        $proveedores = Proveedor::activos()
                                ->orderBy('nombre')
                                ->get();
        $cajas = Caja::porSucursal($this->obtenerSucursalActual())
                    ->activas()
                    ->get();

        return view('livewire.compras.compras', [
            'compras' => $compras,
            'proveedores' => $proveedores,
            'cajas' => $cajas,
            'compraDetalle' => $this->compraDetalleId ? Compra::with(['detalles.articulo', 'proveedor', 'caja'])->find($this->compraDetalleId) : null,
            'compraPago' => $this->compraPagoId ? Compra::find($this->compraPagoId) : null,
        ]);
    }

    // =========================================
    // MÉTODOS DE LISTADO Y FILTROS
    // =========================================

    /**
     * Obtiene las compras filtradas y paginadas
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function obtenerCompras()
    {
        $query = Compra::with(['proveedor', 'caja', 'usuario'])
                     ->where('sucursal_id', $this->obtenerSucursalActual());

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('numero_comprobante', 'like', "%{$this->search}%")
                  ->orWhereHas('proveedor', function ($q2) {
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
        // TODO: Obtener de la sesión cuando implementemos selector de sucursal
        // Por ahora retornamos la primera sucursal activa
        return Sucursal::activas()->first()->id ?? 1;
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
        $this->filterFechaDesde = now()->subMonth()->format('Y-m-d');
        $this->filterFechaHasta = now()->format('Y-m-d');
    }

    // =========================================
    // MÉTODOS DEL FORMULARIO / CARRITO
    // =========================================

    /**
     * Abre el modal para nueva compra
     */
    public function abrirCompraModal()
    {
        $this->resetCompraForm();
        $this->showCompraModal = true;
    }

    /**
     * Resetea todas las propiedades del formulario
     */
    protected function resetCompraForm()
    {
        $this->carrito = [];
        $this->proveedorSeleccionado = null;
        $this->buscarArticulo = '';
        $this->formaPago = 'efectivo';
        $this->cajaSeleccionada = null;
        $this->tipoComprobante = 'factura_a';
        $this->numeroComprobante = null;
        $this->fechaCompra = now()->format('Y-m-d');
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

            // Verificar si ya está en el carrito
            $key = $this->buscarEnCarrito($articuloId);

            if ($key !== null) {
                // Ya está en el carrito, aumentar cantidad
                $this->carrito[$key]['cantidad']++;
            } else {
                // Obtener precio del artículo (precio de compra)
                $precio = $articulo->obtenerPrecio($this->obtenerSucursalActual(), 'costo');
                $precioSinIva = $precio ? $precio->precio : 0;

                // Agregar al carrito
                $this->carrito[] = [
                    'articulo_id' => $articulo->id,
                    'articulo' => $articulo,
                    'cantidad' => 1,
                    'precio_sin_iva' => $precioSinIva,
                    'iva_monto' => 0,
                    'subtotal' => $precioSinIva,
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
            $this->dispatch('toast-error', message: __('Error al agregar artículo: :message', ['message' => $e->getMessage()]));
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
     * Actualiza el precio sin IVA de un artículo
     *
     * @param int $index
     * @param float $precio
     */
    public function actualizarPrecio($index, $precio)
    {
        if (isset($this->carrito[$index])) {
            $this->carrito[$index]['precio_sin_iva'] = max(0, (float) $precio);
            $this->calcularTotales();
        }
    }

    /**
     * Calcula los totales del carrito
     * En compras, el IVA es CRÉDITO FISCAL
     */
    public function calcularTotales()
    {
        $this->subtotal = 0;
        $this->totalIva = 0;

        foreach ($this->carrito as &$item) {
            $articulo = $item['articulo'];
            $tipoIva = $articulo->tipoIva;

            // Calcular subtotal sin IVA
            $subtotalSinIva = $item['precio_sin_iva'] * $item['cantidad'];

            // Calcular IVA (crédito fiscal)
            $ivaMonto = $subtotalSinIva * ($tipoIva->porcentaje / 100);

            // Subtotal del item (sin IVA + IVA)
            $item['iva_monto'] = $ivaMonto;
            $item['subtotal'] = $subtotalSinIva + $ivaMonto;

            $this->subtotal += $subtotalSinIva;
            $this->totalIva += $ivaMonto;
        }

        // Total = Subtotal + IVA
        $this->total = $this->subtotal + $this->totalIva;
    }

    // =========================================
    // MÉTODOS DE PROCESAMIENTO DE COMPRA
    // =========================================

    /**
     * Procesa la compra y la crea en la base de datos
     */
    public function procesarCompra()
    {
        try {
            // Validar carrito no vacío
            if (empty($this->carrito)) {
                $this->dispatch('toast-error', message: __('El carrito está vacío'));
                return;
            }

            // Validar proveedor
            if (!$this->proveedorSeleccionado) {
                $this->dispatch('toast-error', message: __('Debe seleccionar un proveedor'));
                return;
            }

            // Validar caja si no es cuenta corriente
            if ($this->formaPago !== 'cta_cte' && !$this->cajaSeleccionada) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja');
                return;
            }

            // Preparar datos de la compra
            $datosCompra = [
                'sucursal_id' => $this->obtenerSucursalActual(),
                'proveedor_id' => $this->proveedorSeleccionado,
                'caja_id' => $this->cajaSeleccionada,
                'usuario_id' => Auth::id(),
                'numero_comprobante' => $this->numeroComprobante,
                'fecha' => $this->fechaCompra,
                'tipo_comprobante' => $this->tipoComprobante,
                'forma_pago' => $this->formaPago,
                'observaciones' => $this->observaciones,
                'total' => $this->total,
            ];

            // Preparar detalles
            $detalles = [];
            foreach ($this->carrito as $item) {
                $detalles[] = [
                    'articulo_id' => $item['articulo_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_sin_iva'] + ($item['iva_monto'] / $item['cantidad']),
                    'precio_sin_iva' => $item['precio_sin_iva'],
                ];
            }

            // Crear la compra usando el servicio
            $compra = $this->compraService->crearCompra($datosCompra, $detalles);

            // Éxito
            $this->dispatch('toast-success', message: "Compra #{$compra->numero_comprobante} creada exitosamente");
            $this->showCompraModal = false;
            $this->resetCompraForm();

        } catch (Exception $e) {
            Log::error('Error al procesar compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar compra: ' . $e->getMessage());
        }
    }

    /**
     * Cancela el formulario y cierra el modal
     */
    public function cancelarCompraModal()
    {
        $this->showCompraModal = false;
        $this->resetCompraForm();
    }

    // =========================================
    // MÉTODOS DE DETALLES Y ACCIONES
    // =========================================

    /**
     * Abre el modal de detalles de una compra
     *
     * @param int $compraId
     */
    public function verDetalle($compraId)
    {
        $this->compraDetalleId = $compraId;
        $this->showDetalleModal = true;
    }

    /**
     * Cierra el modal de detalles
     */
    public function cerrarDetalle()
    {
        $this->showDetalleModal = false;
        $this->compraDetalleId = null;
    }

    /**
     * Cancela una compra
     *
     * @param int $compraId
     */
    public function cancelarCompra($compraId)
    {
        try {
            $this->compraService->cancelarCompra($compraId);
            $this->dispatch('toast-success', message: 'Compra cancelada exitosamente');

            if ($this->showDetalleModal) {
                $this->cerrarDetalle();
            }

        } catch (Exception $e) {
            Log::error('Error al cancelar compra', [
                'compra_id' => $compraId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: 'Error al cancelar compra: ' . $e->getMessage());
        }
    }

    /**
     * Abre el modal para registrar pago
     *
     * @param int $compraId
     */
    public function abrirModalPago($compraId)
    {
        $compra = Compra::findOrFail($compraId);
        $this->compraPagoId = $compraId;
        $this->montoPago = $compra->saldo_pendiente;
        $this->cajaPago = null;
        $this->showPagoModal = true;
    }

    /**
     * Registra un pago a una compra en cuenta corriente
     */
    public function registrarPago()
    {
        try {
            // Validaciones
            if ($this->montoPago <= 0) {
                $this->dispatch('toast-error', message: 'El monto debe ser mayor a cero');
                return;
            }

            if (!$this->cajaPago) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja');
                return;
            }

            // Registrar el pago
            $this->compraService->registrarPago(
                $this->compraPagoId,
                $this->montoPago,
                $this->cajaPago,
                Auth::id()
            );

            $this->dispatch('toast-success', message: 'Pago registrado exitosamente');
            $this->showPagoModal = false;
            $this->compraPagoId = null;
            $this->montoPago = 0;
            $this->cajaPago = null;

        } catch (Exception $e) {
            Log::error('Error al registrar pago', [
                'compra_id' => $this->compraPagoId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('toast-error', message: 'Error al registrar pago: ' . $e->getMessage());
        }
    }

    /**
     * Cancela el modal de pago
     */
    public function cancelarModalPago()
    {
        $this->showPagoModal = false;
        $this->compraPagoId = null;
        $this->montoPago = 0;
        $this->cajaPago = null;
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
     * Se ejecuta cuando cambia la forma de pago en el formulario
     * Si cambia a cta_cte, deselecciona la caja
     */
    public function updatedFormaPago()
    {
        if ($this->formaPago === 'cta_cte') {
            $this->cajaSeleccionada = null;
        }
    }
}
