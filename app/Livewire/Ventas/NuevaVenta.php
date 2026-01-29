<?php

namespace App\Livewire\Ventas;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Traits\CajaAware;
use App\Traits\AperturaTurnoTrait;
use App\Services\CajaService;
use App\Services\VentaService;
use App\Models\Cliente;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\FormaPago;
use App\Models\FormaPagoSucursal;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\ConceptoPago;
use App\Models\VentaPago;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use App\Models\Categoria;
use App\Models\Promocion;
use App\Models\PromocionEspecial;
use App\Models\PuntoVenta;
use App\Models\TipoIva;
use App\Models\CondicionIva;
use App\Models\MovimientoCaja;
use App\Services\ARCA\ComprobanteFiscalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Componente Livewire: Nueva Venta (POS)
 *
 * Sistema completo de punto de venta con:
 * - Búsqueda de artículos por nombre, código y código de barras
 * - Cálculo de precios según lista de precios
 * - Aplicación de promociones especiales (NxM, Combo, Menú)
 * - Aplicación de promociones comunes (descuentos, etc.)
 * - Selectores de forma de venta, canal de venta, forma de pago y lista de precios
 *
 * @package App\Livewire\Ventas
 */
class NuevaVenta extends Component
{
    use CajaAware;
    use AperturaTurnoTrait;

    // =========================================
    // PROPIEDADES DEL POS / CARRITO
    // =========================================

    /** @var array Items en el carrito de venta */
    public $items = [];

    /** @var int|null ID del cliente seleccionado */
    public $clienteSeleccionado = null;

    /** @var string Búsqueda de artículos */
    public $busquedaArticulo = '';

    /** @var string Input para lector de código de barras */
    public $codigoBarrasInput = '';

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

    /** @var string Nombre para alta rápida de cliente */
    public $clienteRapidoNombre = '';

    /** @var string Teléfono para alta rápida de cliente */
    public $clienteRapidoTelefono = '';

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

    /** @var array Formas de venta disponibles */
    public $formasVenta = [];

    /** @var array Canales de venta disponibles */
    public $canalesVenta = [];

    /** @var array Formas de pago disponibles */
    public $formasPago = [];

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

    public function boot(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    // =========================================
    // CICLO DE VIDA
    // =========================================

    public function mount()
    {
        $this->sucursalId = sucursal_activa() ?? Sucursal::activas()->first()?->id ?? 1;
        $this->cajaSeleccionada = caja_activa();

        // Validar estado de la caja
        $this->actualizarEstadoCaja();

        // Cargar colecciones
        $this->formasVenta = FormaVenta::activas()->get()->toArray();
        $this->canalesVenta = CanalVenta::activos()->get()->toArray();
        $this->formasPago = FormaPago::activas()->get()->toArray();

        // Cargar configuración de facturación fiscal de la sucursal
        $this->cargarConfiguracionFiscalSucursal();

        // Cargar listas de precios
        $this->cargarListasPrecios();

        // Seleccionar lista base por defecto
        $this->listaPrecioId = $this->obtenerIdListaBase();

        // Valores por defecto: Efectivo (ID 1) para forma de pago
        $efectivo = collect($this->formasPago)->firstWhere('codigo', 'efectivo');
        $this->formaPagoId = $efectivo['id'] ?? $this->formasPago[0]['id'] ?? 1;

        // Valores por defecto: Local (ID 1) para forma de venta
        $local = collect($this->formasVenta)->firstWhere('codigo', 'local');
        $this->formaVentaId = $local['id'] ?? $this->formasVenta[0]['id'] ?? 1;

        // Valores por defecto: POS para canal de venta (no visible en UI pero se usa en cálculos)
        $pos = collect($this->canalesVenta)->firstWhere('codigo', 'pos');
        $this->canalVentaId = $pos['id'] ?? $this->canalesVenta[0]['id'] ?? 1;

        // Establecer factura fiscal según la forma de pago por defecto
        $this->actualizarFacturaFiscalSegunFP();
    }

    public function render()
    {
        return view('livewire.ventas.nueva-venta');
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

        if (!empty($this->items)) {
            $this->items = [];
            $this->resultado = null;
            $this->dispatch('toast-info', message: 'Caja cambiada. El carrito ha sido limpiado.');
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

            if (!$caja) {
                $this->dispatch('toast-error', message: 'Caja no encontrada');
                return;
            }

            // Verificar que la caja esté cerrada pero tenga movimientos pendientes (pausada)
            if ($caja->estado === 'abierta') {
                $this->dispatch('toast-info', message: 'La caja ya está activa');
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

            $this->dispatch('toast-success', message: 'Caja activada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al activar caja', ['error' => $e->getMessage(), 'caja_id' => $cajaId]);
            $this->dispatch('toast-error', message: 'Error al activar la caja: ' . $e->getMessage());
        }
    }

    // =========================================
    // MÉTODOS DE LISTAS DE PRECIOS
    // =========================================

    protected function cargarListasPrecios(): void
    {
        if (!$this->sucursalId) {
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
            if (!empty($lista['es_lista_base']) && $lista['es_lista_base'] === true) {
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

        // Solo mostrar resultados si hay al menos 3 caracteres
        if (strlen($value) < 3) {
            $this->articulosResultados = [];
            return;
        }

        $this->cargarArticulosResultados($value);
    }

    protected function cargarArticulosResultados(string $busqueda): void
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        $query->where(function($q) use ($busqueda) {
            $q->where('nombre', 'like', '%' . $busqueda . '%')
              ->orWhere('codigo', 'like', '%' . $busqueda . '%')
              ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
        });

        // Filtrar por sucursal si hay artículos habilitados por sucursal
        if ($this->sucursalId) {
            $query->where(function($q) {
                $q->whereHas('sucursales', function($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                         ->where('articulos_sucursales.activo', 1);
                })->orWhereDoesntHave('sucursales');
            });
        }

        $articulos = $query->orderBy('nombre')->limit(15)->get();

        $this->articulosResultados = $articulos->map(function($art) {
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
        $precioBaseArticulo = (float) $articulo->precio_base;

        // Obtener precio de la lista base
        $listaBase = ListaPrecio::obtenerListaBase($this->sucursalId);
        $precioListaBase = $precioBaseArticulo;
        if ($listaBase) {
            $precioInfoBase = $listaBase->obtenerPrecioArticulo($articulo);
            $precioListaBase = $precioInfoBase['precio'];
        }

        // Si no hay lista seleccionada, usar lista base
        if (!$this->listaPrecioId) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        $listaPrecio = ListaPrecio::find($this->listaPrecioId);
        if (!$listaPrecio) {
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

        if (!$listaPrecio->validarCondiciones($contexto)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Lista diferente a la base y cumple condiciones
        $precioInfo = $listaPrecio->obtenerPrecioArticulo($articulo);
        return [
            'precio' => $precioInfo['precio'],
            'precio_base' => $precioBaseArticulo,
            'tiene_ajuste' => true,
        ];
    }

    // =========================================
    // MÉTODOS DE AGREGAR ARTÍCULOS
    // =========================================

    public function agregarArticulo($articuloId)
    {
        $articulo = Articulo::with(['categoriaModel', 'tipoIva'])->find($articuloId);
        if (!$articulo) return;

        $precioInfo = $this->obtenerPrecioConLista($articulo);

        // Obtener información de IVA del artículo
        $tipoIva = $articulo->tipoIva;
        $ivaInfo = [
            'codigo' => $tipoIva?->codigo ?? 5,
            'porcentaje' => (float) ($tipoIva?->porcentaje ?? 21),
            'nombre' => $tipoIva?->nombre ?? 'IVA 21%',
        ];

        $this->items[] = [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'categoria_id' => $articulo->categoria_id,
            'categoria_nombre' => $articulo->categoriaModel?->nombre,
            'precio_base' => $precioInfo['precio_base'],
            'precio' => $precioInfo['precio'],
            'tiene_ajuste' => $precioInfo['tiene_ajuste'],
            'cantidad' => 1,
            // Información de IVA
            'iva_codigo' => $ivaInfo['codigo'],
            'iva_porcentaje' => $ivaInfo['porcentaje'],
            'iva_nombre' => $ivaInfo['nombre'],
            'precio_iva_incluido' => $articulo->precio_iva_incluido ?? true,
            // Campos para ajuste manual de precio
            'ajuste_manual_tipo' => null, // 'monto' o 'porcentaje'
            'ajuste_manual_valor' => null, // Valor del ajuste
            'precio_sin_ajuste_manual' => null, // Precio antes del ajuste manual (para mostrar tachado)
        ];

        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
        $this->calcularVenta();
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
            ->where(function($q) use ($busqueda) {
                $q->where('codigo_barras', $busqueda)
                  ->orWhere('codigo', $busqueda);
            })
            ->first();

        $articuloId = null;

        if ($articuloPorCodigo) {
            $articuloId = $articuloPorCodigo->id;
        } elseif (!empty($this->articulosResultados)) {
            $articuloId = $this->articulosResultados[0]['id'];
        }

        if (!$articuloId) {
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
     * Agrega un artículo por código de barras (para lector)
     * Sin debounce, busca coincidencia exacta
     */
    public function agregarPorCodigoBarras()
    {
        $codigo = trim($this->codigoBarrasInput);

        if (empty($codigo)) {
            return;
        }

        // Buscar coincidencia exacta por código de barras o código
        $articulo = Articulo::where('activo', true)
            ->where(function($q) use ($codigo) {
                $q->where('codigo_barras', $codigo)
                  ->orWhere('codigo', $codigo);
            })
            ->first();

        if ($articulo) {
            $this->agregarArticulo($articulo->id);
            $this->codigoBarrasInput = '';
        } else {
            $this->dispatch('toast-error', message: "No se encontró artículo con código: {$codigo}");
            $this->codigoBarrasInput = '';
        }
    }

    public function eliminarItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calcularVenta();
    }

    public function actualizarCantidad($index, $cantidad)
    {
        $cantidad = max(1, (int) $cantidad);
        if (isset($this->items[$index])) {
            $this->items[$index]['cantidad'] = $cantidad;
            $this->calcularVenta();
        }
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

        if (!$articulo) {
            $this->dispatch('toast-error', message: 'Artículo no encontrado');
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
            $this->dispatch('toast-success', message: 'Artículo encontrado en el detalle');
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
            ->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])
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
        ];

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
            $query->where(function($q) {
                // Clientes vinculados a la sucursal y activos en ella
                $q->whereHas('sucursales', function($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                         ->where('clientes_sucursales.activo', true);
                })
                // O clientes sin vinculación a ninguna sucursal (disponibles para todas)
                ->orWhereDoesntHave('sucursales');
            });
        }

        // Búsqueda inteligente por nombre, CUIT y teléfono
        $query->where(function($q) use ($busqueda) {
            $q->where('nombre', 'like', '%' . $busqueda . '%')
              ->orWhere('cuit', 'like', '%' . $busqueda . '%')
              ->orWhere('telefono', 'like', '%' . $busqueda . '%');
        });

        $this->clientesResultados = $query->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(fn($c) => [
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

        if (!$cliente) {
            return;
        }

        // Obtener condición IVA del emisor desde la caja activa
        try {
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            if (!$cajaId) {
                return;
            }

            $caja = Caja::with('puntosVenta.cuit.condicionIva')->find($cajaId);
            if (!$caja) {
                return;
            }

            $puntoVenta = $caja->puntoVentaDefecto();
            if (!$puntoVenta || !$puntoVenta->cuit) {
                return;
            }

            $condicionEmisor = $puntoVenta->cuit->condicionIva;
            if (!$condicionEmisor) {
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
        if (empty($this->clientesResultados) && !empty($this->busquedaCliente)) {
            // Si no hay resultados, buscar
            $this->buscarClientes($this->busquedaCliente);
        }

        if (!empty($this->clientesResultados)) {
            $this->seleccionarCliente($this->clientesResultados[0]['id']);
        }
    }

    /**
     * Abre el modal de alta rápida de cliente
     */
    public function abrirModalClienteRapido()
    {
        $this->clienteRapidoNombre = '';
        $this->clienteRapidoTelefono = '';
        $this->mostrarModalClienteRapido = true;
    }

    /**
     * Cierra el modal de alta rápida de cliente
     */
    public function cerrarModalClienteRapido()
    {
        $this->mostrarModalClienteRapido = false;
        $this->clienteRapidoNombre = '';
        $this->clienteRapidoTelefono = '';
    }

    /**
     * Guarda un cliente desde el alta rápida y lo selecciona
     */
    public function guardarClienteRapido()
    {
        $this->validate([
            'clienteRapidoNombre' => 'required|min:2|max:255',
            'clienteRapidoTelefono' => 'nullable|max:50',
        ], [
            'clienteRapidoNombre.required' => 'El nombre es obligatorio',
            'clienteRapidoNombre.min' => 'El nombre debe tener al menos 2 caracteres',
        ]);

        try {
            // Obtener el ID de Consumidor Final desde la tabla de condiciones
            $consumidorFinal = CondicionIva::where('codigo', CondicionIva::CONSUMIDOR_FINAL)->first();

            // Crear el cliente con los campos que existen en la tabla
            $cliente = Cliente::create([
                'nombre' => $this->clienteRapidoNombre,
                'telefono' => $this->clienteRapidoTelefono ?: null,
                'condicion_iva_id' => $consumidorFinal?->id,
                'activo' => true,
            ]);

            // Seleccionar el cliente recién creado
            $this->clienteSeleccionado = $cliente->id;
            $this->clienteNombre = $cliente->nombre;
            $this->busquedaCliente = '';
            $this->clientesResultados = [];

            // Cerrar modal
            $this->cerrarModalClienteRapido();

            $this->dispatch('notify',
                message: 'Cliente "' . $cliente->nombre . '" creado correctamente',
                type: 'success'
            );

        } catch (Exception $e) {
            Log::error('Error al crear cliente rápido: ' . $e->getMessage());
            $this->dispatch('notify',
                message: 'Error al crear el cliente',
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
        if ($this->ajusteFormaPagoInfo['es_mixta'] && !empty($this->items)) {
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
        $this->cuotasSelectorAbierto = !$this->cuotasSelectorAbierto;
    }

    /**
     * Carga las cuotas disponibles para la forma de pago seleccionada
     */
    protected function cargarCuotasFormaPago(): void
    {
        $this->cuotasFormaPagoDisponibles = [];
        $this->formaPagoPermiteCuotas = false;

        if (!$this->formaPagoId) {
            return;
        }

        $formaPago = FormaPago::find($this->formaPagoId);

        if (!$formaPago || !$formaPago->permite_cuotas || $formaPago->es_mixta) {
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
            if ($configSucursal && !$configSucursal->activo) {
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

        if (!$this->cuotaSeleccionadaId) {
            return;
        }

        $cuotaInfo = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $this->cuotaSeleccionadaId);

        if (!$cuotaInfo) {
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

        $desc = "{$cantCuotas} cuotas de $" . number_format($cuotaInfo['valor_cuota'], 2, ',', '.');

        if ($recargo > 0) {
            $desc .= " (+{$recargo}%)";
        } else {
            $desc .= " (sin interés)";
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

        if (!$this->formaPagoId || !$this->resultado) {
            return;
        }

        // Cargar formas de pago si no están cargadas
        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (!$fp) {
            // Intentar cargar desde la base de datos
            $formaPago = FormaPago::find($this->formaPagoId);
            if (!$formaPago) {
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
        $montoAjuste = round($totalBase * ($ajustePorcentaje / 100), 2);
        $totalConAjuste = round($totalBase + $montoAjuste, 2);

        // Variables para cuotas
        $cantidadCuotas = 1;
        $recargoCuotasPorcentaje = 0;
        $recargoCuotasMonto = 0;
        $valorCuota = $totalConAjuste;

        // Si hay cuota seleccionada, aplicar recargo de cuotas
        if ($this->cuotaSeleccionadaId && !empty($this->cuotasFormaPagoDisponibles)) {
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
     * @param float $montoAjusteFormaPago Monto del ajuste de forma de pago (negativo = descuento)
     * @param float $montoRecargoCuotas Monto del recargo por cuotas (siempre positivo o cero)
     */
    protected function actualizarDesgloseIvaConAjusteFormaPago(float $montoAjusteFormaPago, float $montoRecargoCuotas): void
    {
        if (!$this->resultado || !isset($this->resultado['desglose_iva'])) {
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
        if (empty($this->items) || !$this->resultado) {
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
        if (empty($this->items) || !$this->resultado) {
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

        if ($index === null || !isset($this->items[$index])) {
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
        if (!isset($this->items[$index])) {
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
        if (!$infoPromos['aplica_promociones']) {
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
                    $tienePrecioEspecial = $listaPrecio->articulos()
                        ->where('articulo_id', $unidad['articulo_id'])
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
            $cantidad = (int) ($item['cantidad'] ?? 1);
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
                'cantidad' => (int) ($item['cantidad'] ?? 1),
                'excluido_promociones' => isset($articulosExcluidos[$articuloId]),
            ];
        }

        // 1. Aplicar promociones especiales primero (NxM, Combo, Menú)
        if ($infoPromos['aplica_promociones']) {
            $promocionesEspeciales = $this->obtenerPromocionesEspeciales($contexto);

            foreach ($promocionesEspeciales as $promo) {
                $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $poolUnidades);

                if ($aplicacion['aplicada']) {
                    // Marcar unidades como consumidas usando índice para modificar el array original
                    foreach ($aplicacion['unidades_consumidas'] as $unidadIdConsumida) {
                        foreach ($poolUnidades as $idx => $unidad) {
                            if ($unidad['id'] === $unidadIdConsumida) {
                                $poolUnidades[$idx]['consumida'] = true;
                                $poolUnidades[$idx]['consumida_por'] = $promo['nombre'];
                                // Guardar info completa de la promo para trazabilidad
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

                    $resultado['promociones_especiales_aplicadas'][] = [
                        'id' => $promo['id'],
                        'promocion_especial_id' => $promo['id'], // ID explícito para BD
                        'nombre' => $promo['nombre'],
                        'tipo' => $promo['tipo'],
                        'descuento' => $aplicacion['descuento'],
                        'descripcion' => $aplicacion['descripcion'],
                        'unidades_usadas' => count($aplicacion['unidades_consumidas']),
                    ];

                    $resultado['total_descuentos'] += $aplicacion['descuento'];
                }
            }

            // 2. Aplicar promociones comunes a items (soporta combinabilidad)
            $promocionesComunes = $this->obtenerPromocionesComunes($contexto);

            // Calcular unidades libres por item para promociones comunes
            foreach ($itemsParaPromos as $itemIndex => &$itemPromo) {
                $unidadesDelItem = array_values(array_filter($poolUnidades, fn($u) => $u['item_index'] === $itemIndex));
                $unidadesLibres = array_values(array_filter($unidadesDelItem, fn($u) => !($u['consumida'] ?? false)));
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
            $unidadesDelItem = array_values(array_filter($poolUnidades, fn($u) => $u['item_index'] === $index));
            $unidadesConsumidas = array_values(array_filter($unidadesDelItem, fn($u) => $u['consumida'] ?? false));
            $unidadesLibres = array_values(array_filter($unidadesDelItem, fn($u) => !($u['consumida'] ?? false)));
            $articuloId = $item['articulo_id'] ?? null;
            $excluido = isset($articulosExcluidos[$articuloId]);

            // Obtener promociones comunes aplicadas a este item
            $promocionesComunes = $itemsParaPromos[$index]['promociones_comunes'] ?? [];
            $descuentoComun = $itemsParaPromos[$index]['total_descuento_comun'] ?? 0;

            // Obtener info completa de promociones especiales aplicadas a este item
            $promosEspecialesItem = [];
            foreach ($unidadesConsumidas as $unidad) {
                if (!empty($unidad['promo_especial_info'])) {
                    $promoKey = $unidad['promo_especial_info']['id'];
                    // Evitar duplicados usando el ID como clave
                    if (!isset($promosEspecialesItem[$promoKey])) {
                        $promosEspecialesItem[$promoKey] = $unidad['promo_especial_info'];
                    }
                }
            }

            $resultado['items'][$index] = [
                'articulo_id' => $articuloId,
                'nombre' => $item['nombre'],
                'precio_base' => (float) ($item['precio_base'] ?? $item['precio'] ?? 0),
                'precio_lista' => (float) ($item['precio'] ?? 0),
                'cantidad' => (int) ($item['cantidad'] ?? 1),
                'subtotal' => (float) ($item['precio'] ?? 0) * (int) ($item['cantidad'] ?? 1),
                'unidades_consumidas' => count($unidadesConsumidas),
                'unidades_libres' => count($unidadesLibres),
                'excluido_promociones' => $excluido,
                'tiene_ajuste' => $item['tiene_ajuste'] ?? false,
                'promociones_especiales' => array_values($promosEspecialesItem), // Array de objetos completos
                'promociones_comunes' => $promocionesComunes,
                'descuento_comun' => $descuentoComun,
            ];
        }

        // Calcular total final
        $resultado['total_final'] = max(0, $resultado['subtotal'] - $resultado['total_descuentos']);

        // Calcular desglose de IVA
        $resultado['desglose_iva'] = $this->calcularDesgloseIva(
            $resultado['items'],
            $resultado['total_descuentos'],
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
     * @param array $items Items del resultado con precio y cantidad
     * @param float $totalDescuentos Total de descuentos aplicados (promociones)
     * @param float $subtotal Subtotal antes de descuentos
     * @return array Desglose por alícuota + totales
     */
    protected function calcularDesgloseIva(array $items, float $totalDescuentos, float $subtotal): array
    {
        // Inicializar acumuladores por alícuota
        $porAlicuota = [];

        // Calcular neto e IVA de cada item y agrupar
        foreach ($this->items as $index => $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 1);
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
            if (!isset($porAlicuota[$ivaCodigo])) {
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
            $cantidad = (int) ($item['cantidad'] ?? 1);

            for ($i = 0; $i < $cantidad; $i++) {
                $pool[] = [
                    'id' => 'u_' . ($idCounter++),
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

        if (!$listaSeleccionada) {
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
            ->where(function($q) {
                $q->whereNull('vigencia_desde')
                  ->orWhere('vigencia_desde', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('vigencia_hasta')
                  ->orWhere('vigencia_hasta', '>=', now());
            })
            ->with(['grupos.articulos', 'escalas'])
            ->orderBy('prioridad')
            ->get();

        return $promociones->filter(function($promo) use ($contexto) {
            // Verificar usos disponibles
            if (!$promo->tieneUsosDisponibles()) {
                return false;
            }
            return $this->promocionEspecialCumpleCondiciones($promo, $contexto);
        })->map(function($promo) {
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

        // Verificar forma de pago
        if ($promo->forma_pago_id) {
            if (empty($contexto['forma_pago_id']) || $promo->forma_pago_id != $contexto['forma_pago_id']) {
                return false;
            }
        }

        // Verificar día de la semana
        if (!empty($promo->dias_semana) && !in_array($contexto['dia_semana'], $promo->dias_semana)) {
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
            // NxM básico
            'nxm_lleva' => $promo->nxm_lleva,
            'nxm_bonifica' => $promo->nxm_bonifica,
            'nxm_articulo_id' => $promo->nxm_articulo_id,
            'nxm_categoria_id' => $promo->nxm_categoria_id,
            'beneficio_tipo' => $promo->beneficio_tipo ?? 'gratis',
            'beneficio_porcentaje' => $promo->beneficio_porcentaje ?? 100,
            'usa_escalas' => $promo->usa_escalas,
            'escalas' => $promo->escalas->toArray(),
            // NxM avanzado
            'grupos_trigger' => $promo->gruposTrigger ? $promo->gruposTrigger->map(fn($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            'grupos_reward' => $promo->gruposReward ? $promo->gruposReward->map(fn($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            // Combo/Menu
            'precio_tipo' => $promo->precio_tipo,
            'precio_valor' => $promo->precio_valor,
            'grupos' => $promo->grupos->map(fn($g) => [
                'nombre' => $g->nombre,
                'cantidad' => $g->cantidad,
                'articulos' => $g->articulos->map(fn($a) => [
                    'id' => $a->id,
                    'precio' => $a->precio_base,
                ])->toArray(),
            ])->toArray(),
        ];
    }

    protected function intentarAplicarPromocionEspecial(array $promo, array $poolUnidades): array
    {
        // Filtrar solo unidades disponibles
        $unidadesDisponibles = array_filter($poolUnidades, fn($u) => !$u['consumida'] && !($u['excluido_promociones'] ?? false));

        return match($promo['tipo']) {
            'nxm' => $this->aplicarNxMBasico($promo, $unidadesDisponibles),
            'nxm_avanzado' => $this->aplicarNxMAvanzado($promo, $unidadesDisponibles),
            'combo' => $this->aplicarCombo($promo, $unidadesDisponibles),
            'menu' => $this->aplicarMenu($promo, $unidadesDisponibles),
            default => ['aplicada' => false, 'razon' => 'Tipo de promoción no soportado'],
        };
    }

    protected function aplicarNxMBasico(array $promo, array $unidadesDisponibles): array
    {
        // Filtrar unidades que aplican a esta promoción
        $unidadesAplicables = array_filter($unidadesDisponibles, function($u) use ($promo) {
            if ($promo['nxm_articulo_id']) {
                return $u['articulo_id'] == $promo['nxm_articulo_id'];
            }
            if ($promo['nxm_categoria_id']) {
                return $u['categoria_id'] == $promo['nxm_categoria_id'];
            }
            return false;
        });

        $cantidadDisponible = count($unidadesAplicables);

        // Determinar lleva/bonifica según escalas o valores fijos
        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && !empty($promo['escalas'])) {
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (int) ($escala['cantidad_desde'] ?? 0);
                $hasta = (int) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
                if ($cantidadDisponible >= $desde && ($hasta === 0 || $cantidadDisponible <= $hasta)) {
                    $escalaAplicable = $escala;
                    break;
                }
            }

            if (!$escalaAplicable) {
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

        // Ordenar por precio descendente
        usort($unidadesAplicables, fn($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = floor($cantidadDisponible / $lleva);
        $unidadesConsumidas = [];
        $descuentoTotal = 0;

        for ($vez = 0; $vez < $vecesAplicable; $vez++) {
            $offset = $vez * $lleva;
            $unidadesParaEstaVez = array_slice($unidadesAplicables, $offset, $lleva);

            for ($i = 0; $i < $bonifica && $i < count($unidadesParaEstaVez); $i++) {
                $unidad = $unidadesParaEstaVez[$i];
                if ($beneficioTipo === 'gratis') {
                    $descuentoTotal += $unidad['precio'];
                } else {
                    $descuentoTotal += $unidad['precio'] * ($beneficioPorcentaje / 100);
                }
            }

            foreach ($unidadesParaEstaVez as $unidad) {
                $unidadesConsumidas[] = $unidad['id'];
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

        $unidadesTrigger = array_values(array_filter($unidadesDisponibles, fn($u) => in_array($u['articulo_id'], $triggerIds)));
        $unidadesReward = array_values(array_filter($unidadesDisponibles, fn($u) => in_array($u['articulo_id'], $rewardIds)));

        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && !empty($promo['escalas'])) {
            $cantidadTrigger = count($unidadesTrigger);
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (int) ($escala['cantidad_desde'] ?? 0);
                $hasta = (int) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
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

        usort($unidadesReward, fn($a, $b) => $b['precio'] <=> $a['precio']);

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
            $cantidadRequerida = (int) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = $grupo['articulos'] ?? [];

            if (empty($articulosDelGrupo)) continue;

            // Obtener todos los IDs de artículos válidos para este grupo
            $articulosIdsDelGrupo = array_column($articulosDelGrupo, 'id');

            // Buscar unidades de CUALQUIER artículo del grupo (no solo el primero)
            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn($u) => in_array($u['articulo_id'], $articulosIdsDelGrupo) && !in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => 'Faltan artículos para el grupo'];
            }

            // Ordenar por precio ascendente para consumir los más baratos primero
            usort($unidadesDeEsteGrupo, fn($a, $b) => $a['precio'] <=> $b['precio']);

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
                ? "Combo a $" . number_format($promo['precio_valor'], 0, ',', '.')
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
            $cantidadRequerida = (int) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = array_column($grupo['articulos'] ?? [], 'id');

            if (empty($articulosDelGrupo)) {
                return ['aplicada' => false, 'razon' => "Grupo '{$grupo['nombre']}' sin artículos"];
            }

            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn($u) => in_array($u['articulo_id'], $articulosDelGrupo) && !in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => "Faltan artículos para '{$grupo['nombre']}'"];
            }

            usort($unidadesDeEsteGrupo, fn($a, $b) => $a['precio'] <=> $b['precio']);

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
                ? "Menú a $" . number_format($promo['precio_valor'], 0, ',', '.')
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
        return $promociones->filter(function($promo) use ($contexto) {
            // Verificar día de la semana
            if (!$promo->aplicaEnDiaSemana($contexto['dia_semana'])) {
                return false;
            }
            // Verificar horario
            if (!$promo->aplicaEnHorario($contexto['hora'])) {
                return false;
            }
            return true;
        })->map(function($promo) {
            return $this->convertirPromocionComunAArray($promo);
        })->toArray();
    }

    protected function convertirPromocionComunAArray($promo): array
    {
        $condiciones = $promo->condiciones;
        $condicionArticulo = $condiciones->firstWhere('tipo_condicion', 'por_articulo');
        $condicionCategoria = $condiciones->firstWhere('tipo_condicion', 'por_categoria');
        $condicionMontoMinimo = $condiciones->firstWhere('tipo_condicion', 'por_total_compra');
        $condicionCantidadMinima = $condiciones->firstWhere('tipo_condicion', 'por_cantidad');
        $condicionFormaPago = $condiciones->firstWhere('tipo_condicion', 'por_forma_pago');
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
            'articulo_id' => $condicionArticulo?->articulo_id,
            'categoria_id' => $condicionCategoria?->categoria_id,
            'monto_minimo' => $condicionMontoMinimo?->monto_minimo,
            'cantidad_minima' => $condicionCantidadMinima?->cantidad_minima,
            'forma_pago_id' => $condicionFormaPago?->forma_pago_id,
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
        if (!empty($promo['monto_minimo'])) {
            if (($contexto['subtotal'] ?? 0) < (float) $promo['monto_minimo']) {
                return false;
            }
        }

        // Verificar cantidad mínima
        if (!empty($promo['cantidad_minima'])) {
            if (($contexto['cantidad_total'] ?? 0) < (int) $promo['cantidad_minima']) {
                return false;
            }
        }

        // Verificar forma de pago: si la promoción requiere una forma de pago específica
        if (!empty($promo['forma_pago_id'])) {
            // Si el contexto tiene una forma de pago seleccionada, debe coincidir
            if (!empty($contexto['forma_pago_id'])) {
                if ($promo['forma_pago_id'] != $contexto['forma_pago_id']) {
                    return false;
                }
            } else {
                // Si no hay forma de pago seleccionada pero la promo requiere una, no aplica
                return false;
            }
        }

        // Verificar forma de venta
        if (!empty($promo['forma_venta_id'])) {
            if (!empty($contexto['forma_venta_id'])) {
                if ($promo['forma_venta_id'] != $contexto['forma_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar canal de venta
        if (!empty($promo['canal_venta_id'])) {
            if (!empty($contexto['canal_venta_id'])) {
                if ($promo['canal_venta_id'] != $contexto['canal_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar día de la semana
        if (!empty($promo['dias_semana']) && !in_array($contexto['dia_semana'], $promo['dias_semana'])) {
            return false;
        }

        // Verificar horario
        if (!empty($promo['hora_desde']) && $contexto['hora'] < $promo['hora_desde']) {
            return false;
        }
        if (!empty($promo['hora_hasta']) && $contexto['hora'] > $promo['hora_hasta']) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si una promoción aplica a un item específico
     */
    protected function promocionAplicaAItem(array $promo, ?int $articuloId, ?int $categoriaId): bool
    {
        // Si la promoción es para un artículo específico
        if (!empty($promo['articulo_id'])) {
            return $promo['articulo_id'] == $articuloId;
        }

        // Si la promoción es para una categoría específica
        if (!empty($promo['categoria_id'])) {
            return $promo['categoria_id'] == $categoriaId;
        }

        // Si no tiene restricción de artículo ni categoría, aplica a todos
        return true;
    }

    /**
     * Aplica promociones comunes a los items (soporta múltiples promociones combinables)
     */
    protected function aplicarPromocionesComunes(array $promociones, array &$items, array $contexto): array
    {
        $promocionesAplicadas = [];
        $cantidadTotal = array_sum(array_column($items, 'cantidad'));
        $subtotal = array_sum(array_map(fn($i) => $i['precio'] * $i['cantidad'], $items));

        $contextoCompleto = array_merge($contexto, [
            'subtotal' => $subtotal,
            'cantidad_total' => $cantidadTotal,
        ]);

        // Filtrar promociones que cumplen condiciones generales
        $promocionesValidas = array_filter($promociones, fn($p) => $this->promocionCumpleCondiciones($p, $contextoCompleto));

        // Procesar cada item
        foreach ($items as $itemIndex => &$item) {
            $articuloId = $item['articulo_id'];
            $categoriaId = $item['categoria_id'] ?? null;
            $cantidad = (int) $item['cantidad'];
            $precioUnitario = (float) $item['precio'];
            $subtotalItem = $precioUnitario * $cantidad;

            // Saltar items excluidos de promociones
            if (!empty($item['excluido_promociones'])) {
                continue;
            }

            // Filtrar promociones que aplican a este item
            $promocionesParaItem = array_filter($promocionesValidas, fn($p) => $this->promocionAplicaAItem($p, $articuloId, $categoriaId));

            if (empty($promocionesParaItem)) {
                continue;
            }

            // Encontrar la mejor combinación de promociones para este item
            $mejorCombinacion = $this->encontrarMejorCombinacion(
                array_values($promocionesParaItem),
                $subtotalItem,
                $cantidad
            );

            if (!empty($mejorCombinacion['promociones'])) {
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
                    if (!$yaExiste) {
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

        $mejorResultado = [
            'monto_final' => $montoInicial,
            'promociones' => [],
        ];

        $n = count($promociones);
        $totalCombinaciones = pow(2, $n);

        // Evaluar todas las combinaciones posibles
        for ($i = 1; $i < $totalCombinaciones; $i++) {
            $combinacion = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i & (1 << $j)) {
                    $combinacion[] = $promociones[$j];
                }
            }

            // Verificar si la combinación es válida (respeta reglas de combinabilidad)
            if (!$this->esCombinacionValida($combinacion)) {
                continue;
            }

            // Calcular resultado de esta combinación
            $resultado = $this->calcularCombinacion($combinacion, $montoInicial, $cantidad);

            // Si es mejor (menor monto final), guardarla
            if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                $mejorResultado = $resultado;
            }
        }

        return $mejorResultado;
    }

    /**
     * Verifica si una combinación de promociones es válida
     */
    protected function esCombinacionValida(array $combinacion): bool
    {
        if (count($combinacion) <= 1) {
            return true;
        }

        // Si hay más de una promoción, todas deben ser combinables
        foreach ($combinacion as $promo) {
            if (!$promo['combinable']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcula el resultado de aplicar una combinación de promociones
     */
    protected function calcularCombinacion(array $combinacion, float $montoInicial, int $cantidad): array
    {
        // Ordenar por prioridad
        usort($combinacion, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $montoActual = $montoInicial;
        $promocionesAplicadas = [];

        foreach ($combinacion as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoActual, $cantidad);

            if ($ajuste['valor'] > 0) {
                $montoActual -= $ajuste['valor'];
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
                $descripcion = "$" . number_format($promo['valor'], 0, ',', '.') . " dto";
                break;

            case 'precio_fijo':
                $precioFijoTotal = (float) $promo['valor'] * $cantidad;
                $valor = max(0, $monto - $precioFijoTotal);
                $descripcion = "Precio fijo $" . number_format($promo['valor'], 0, ',', '.');
                break;

            case 'descuento_escalonado':
                if (!empty($promo['escalas'])) {
                    $escalas = collect($promo['escalas'])
                        ->filter(fn($e) => !empty($e['cantidad_desde']) && !empty($e['valor']))
                        ->sortByDesc('cantidad_desde');

                    foreach ($escalas as $escala) {
                        if ($cantidad >= $escala['cantidad_desde']) {
                            $tipoDesc = $escala['tipo_descuento'] ?? 'porcentaje';
                            if ($tipoDesc === 'porcentaje') {
                                $porcentaje = (float) $escala['valor'];
                                $valor = round($monto * ($porcentaje / 100), 2);
                                $descripcion = "{$porcentaje}% dto escalonado";
                            } else {
                                $valor = min((float) $escala['valor'], $monto);
                                $descripcion = "Monto fijo escalonado";
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
        if (!$this->sucursalId) {
            $this->formasPagoSucursal = [];
            return;
        }

        $formasPago = FormaPago::with(['conceptoPago', 'conceptosPermitidos', 'cuotas'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $this->formasPagoSucursal = $formasPago->map(function ($fp) {
            // Obtener configuración específica de sucursal
            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $fp->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            // Verificar si está activa en la sucursal
            $activaEnSucursal = $configSucursal ? $configSucursal->activo : true;

            if (!$activaEnSucursal) {
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
            if ($fp->permite_cuotas && !$fp->es_mixta) {
                foreach ($fp->cuotas as $cuota) {
                    $cuotaSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                        ->where('sucursal_id', $this->sucursalId)
                        ->first();

                    $activa = $cuotaSucursal ? $cuotaSucursal->activo : true;
                    if (!$activa) continue;

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

            return [
                'id' => $fp->id,
                'nombre' => $fp->nombre,
                'codigo' => $fp->codigo,
                'concepto' => $fp->concepto,
                'concepto_pago_id' => $fp->concepto_pago_id,
                'concepto_nombre' => $fp->conceptoPago?->nombre,
                'es_mixta' => $fp->es_mixta ?? false,
                'permite_cuotas' => $fp->permite_cuotas && !$fp->es_mixta,
                'ajuste_porcentaje' => $ajustePorcentaje ?? 0,
                'factura_fiscal' => $facturaFiscal,
                'permite_vuelto' => $fp->conceptoPago?->permite_vuelto ?? false,
                'cuotas' => $cuotasDisponibles,
                'conceptos_permitidos' => $fp->es_mixta
                    ? $fp->conceptosPermitidos->map(fn($c) => [
                        'id' => $c->id,
                        'codigo' => $c->codigo,
                        'nombre' => $c->nombre,
                    ])->toArray()
                    : [],
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
        if (!$this->sucursalId) {
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
        if ($fp && !$fp['es_mixta']) {
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
            $this->desglosePagos[$index]['factura_fiscal'] = !$this->desglosePagos[$index]['factura_fiscal'];
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
        if (!$this->resultado || !isset($this->resultado['desglose_iva'])) {
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
            if (!is_array($alicuota)) {
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
        if ($diferencia != 0 && !empty($porAlicuota)) {
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
        if (!$this->resultado || !isset($this->resultado['desglose_iva'])) {
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
            if (!is_array($alicuota)) {
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
        if ($diferencia != 0 && !empty($porAlicuota)) {
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
        if (!$cajaId) {
            return false;
        }

        // Verificar permiso del usuario
        $user = Auth::user();
        if (!$user || !$user->hasPermissionTo('func.seleccion_cuit')) {
            return false;
        }

        // Verificar si la caja tiene múltiples puntos de venta
        $caja = Caja::find($cajaId);
        if (!$caja) {
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
        if (!$cajaId) {
            $this->puntosVentaDisponibles = [];
            return;
        }

        $caja = Caja::find($cajaId);
        if (!$caja) {
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
        if (!$this->puntoVentaSeleccionadoId) {
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
        if (empty($this->items) || !$this->resultado) {
            $this->dispatch('toast-error', message: 'El carrito está vacío');
            return;
        }

        if (!$this->formaPagoId) {
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
            if (!$this->mostrarModalPago) {
                $this->abrirModalDesglose();
            }
            return;
        }

        // Para pagos simples: preparar el desglose y procesar directamente
        $this->cargarFormasPagoSucursal();
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (!$fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');
            return;
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
        $montoAjuste = $this->ajusteFormaPagoInfo['monto'];
        $montoFinal = $this->ajusteFormaPagoInfo['total_con_ajuste'];

        // Obtener información de cuotas si hay seleccionada
        $cantidadCuotas = $this->ajusteFormaPagoInfo['cuotas'] ?? 1;
        $recargoCuotas = $this->ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0;

        // Crear desglose con un solo pago (incluyendo info de cuotas)
        $this->desglosePagos = [[
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'concepto_pago_id' => $fp['concepto_pago_id'] ?? null,
            'monto_base' => $totalBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cantidadCuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => null,
            'vuelto' => 0,
            'factura_fiscal' => $this->sucursalFacturaAutomatica
                ? ($fp['factura_fiscal'] ?? false)  // Si es automática, usar config de FP
                : $this->emitirFacturaFiscal,       // Si no, usar el checkbox del usuario
        ]];

        $this->totalConAjustes = $montoFinal;
        $this->montoPendienteDesglose = 0;

        // Calcular el monto fiscal
        $this->calcularMontoFacturaFiscal();

        // Verificar si necesita selección de punto de venta
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Verifica si se debe mostrar el modal de selección de punto de venta
     * antes de procesar la venta. Si no es necesario, procesa directamente.
     */
    protected function verificarPuntoVentaYProcesar(): void
    {
        // Determinar si se va a generar factura fiscal
        $sucursal = Sucursal::find($this->sucursalId);
        if (!$sucursal) {
            $this->procesarVentaConDesglose();
            return;
        }

        $comprobanteFiscalService = new ComprobanteFiscalService();
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
        if (!$value) {
            $this->cuotasDisponibles = [];
            $this->cuotasDesgloseConMontos = [];
            $this->cuotasDesgloseSelectorAbierto = false;
            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $value);
        $this->cuotasDisponibles = $fp ? $fp['cuotas'] : [];
        $this->nuevoPago['cuotas'] = 1;
        $this->cuotasDesgloseSelectorAbierto = false;
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
        $this->cuotasDesgloseSelectorAbierto = !$this->cuotasDesgloseSelectorAbierto;
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
        if (!$this->nuevoPago['forma_pago_id']) {
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

        if ($monto > $this->montoPendienteDesglose + 0.01) {
            $this->dispatch('toast-error', message: 'El monto excede el pendiente');
            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        if (!$fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');
            return;
        }

        // Calcular ajuste
        $ajuste = $fp['ajuste_porcentaje'];
        $montoAjuste = round($monto * ($ajuste / 100), 2);
        $montoConAjuste = round($monto + $montoAjuste, 2);

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

        $this->desglosePagos[] = [
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'concepto_pago_id' => $fp['concepto_pago_id'],
            'monto_base' => $monto,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $fp['permite_vuelto'] ? $montoFinal : null,
            'vuelto' => 0,
            'permite_vuelto' => $fp['permite_vuelto'],
            'permite_cuotas' => $fp['permite_cuotas'],
            'cuotas_disponibles' => $fp['cuotas'],
            'factura_fiscal' => $fp['factura_fiscal'] ?? false, // Por defecto según config de FP
        ];

        $this->montoPendienteDesglose = round($this->montoPendienteDesglose - $monto, 2);

        // Recalcular el monto fiscal
        $this->calcularMontoFacturaFiscal();
        $this->recalcularTotalConAjustes();
        $this->resetNuevoPago();
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
        if (!isset($this->desglosePagos[$index])) {
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
        if (!isset($this->resultado['desglose_iva'])) {
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
        if (!isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = &$this->desglosePagos[$index];

        if (!$pago['permite_cuotas'] || $cuotas < 1) {
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
        if (!isset($this->desglosePagos[$index])) {
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
        if ($this->desgloseCompleto() && !empty($this->desglosePagos)) {
            $totalBase = $this->resultado['total_final'] ?? 0;
            $totalConAjustes = $this->totalConAjustes;
            $montoAjuste = $totalConAjustes - $totalBase;

            $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
            $this->ajusteFormaPagoInfo['total_con_ajuste'] = $totalConAjustes;
        }

        $this->mostrarModalPago = false;
        // No limpiar el desglose si está completo (para poder procesar después)
        if (!$this->desgloseCompleto()) {
            $this->desglosePagos = [];
            $this->montoPendienteDesglose = 0;
            $this->totalConAjustes = 0;
            // Limpiar valores mixtos del desglose de IVA
            $this->limpiarDesgloseIvaMixto();
        }
        $this->resetNuevoPago();
    }

    /**
     * Limpia los valores de pago mixto del desglose de IVA
     */
    protected function limpiarDesgloseIvaMixto(): void
    {
        if (!isset($this->resultado['desglose_iva'])) {
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
        if (!$this->desgloseCompleto()) {
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
            if (!$sucursal) {
                $this->dispatch('toast-error', message: 'Sucursal no encontrada');
                return;
            }

            $cajaId = $this->cajaSeleccionada ?? caja_activa();

            // Verificar si hay pagos a cuenta corriente
            $tieneCuentaCorriente = false;
            $montoCuentaCorriente = 0;

            foreach ($this->desglosePagos as $pago) {
                $fp = FormaPago::find($pago['forma_pago_id']);
                if ($fp && strtoupper($fp->codigo) === 'CTA_CTE') {
                    $tieneCuentaCorriente = true;
                    $montoCuentaCorriente += $pago['monto_final'];

                    // Cuenta corriente requiere cliente
                    if (!$this->clienteSeleccionado) {
                        $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');
                        return;
                    }

                    // Verificar que el cliente tiene cuenta corriente habilitada
                    $cliente = Cliente::find($this->clienteSeleccionado);
                    if (!$cliente || !$cliente->tiene_cuenta_corriente) {
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

            // Verificar caja para pagos que la requieren
            $requiereCaja = false;
            foreach ($this->desglosePagos as $pago) {
                $fp = FormaPago::find($pago['forma_pago_id']);
                if ($fp && strtoupper($fp->codigo) !== 'CTA_CTE') {
                    $requiereCaja = true;
                    break;
                }
            }

            if ($requiereCaja && !$cajaId) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja');
                return;
            }

            // Verificar caja abierta
            if ($cajaId) {
                $caja = Caja::find($cajaId);
                if (!$caja || !$caja->estaAbierta()) {
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
            $comprobanteFiscalService = new ComprobanteFiscalService();
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
                ];

                // Construir detalles con información de promociones
                $detalles = [];
                foreach ($this->items as $index => $item) {
                    $itemResultado = $this->resultado['items'][$index] ?? [];
                    $descuentoPromocion = $itemResultado['descuento_comun'] ?? 0;
                    $promocionesComunes = $itemResultado['promociones_comunes'] ?? [];
                    $promocionesEspeciales = $itemResultado['promociones_especiales'] ?? [];
                    $tienePromocion = !empty($promocionesComunes) || !empty($promocionesEspeciales);

                    $detalles[] = [
                        'articulo_id' => $item['articulo_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'precio_lista' => $item['precio_base'] ?? $item['precio'],
                        'lista_precio_id' => $this->listaPrecioId, // Lista de precios usada
                        'descuento' => 0, // Descuento manual (no promoción)
                        'descuento_promocion' => $descuentoPromocion,
                        'tiene_promocion' => $tienePromocion,
                        // Info de IVA del item
                        'iva_porcentaje' => $item['iva_porcentaje'] ?? 21,
                        'precio_iva_incluido' => $item['precio_iva_incluido'] ?? true,
                        // Info de ajuste manual si existe
                        'ajuste_manual_tipo' => $item['ajuste_manual_tipo'] ?? null,
                        'ajuste_manual_valor' => $item['ajuste_manual_valor'] ?? null,
                        'precio_sin_ajuste_manual' => $item['precio_sin_ajuste_manual'] ?? null,
                        // Info de promociones para guardar en venta_detalle_promociones
                        '_promociones_item' => [
                            'promociones_comunes' => $promocionesComunes,
                            'promociones_especiales' => $promocionesEspeciales,
                        ],
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
                    if (!empty($pago['concepto_pago_id'])) {
                        $conceptoPago = ConceptoPago::find($pago['concepto_pago_id']);
                    } elseif ($fp && $fp->concepto_pago_id) {
                        $conceptoPago = $fp->conceptoPago;
                    }
                    $esEfectivo = $conceptoPago && $conceptoPago->esEfectivo();

                    // Solo afecta la caja física si es efectivo
                    $afectaCaja = $esEfectivo && $cajaId && !$esCuentaCorriente;

                    // Crear movimiento de caja SOLO si es efectivo
                    $movimientoCajaId = null;
                    if ($afectaCaja) {
                        $caja = Caja::find($cajaId);
                        $movimiento = MovimientoCaja::crearIngresoVenta($caja, $venta, $pago['monto_final'], Auth::id());
                        $movimientoCajaId = $movimiento->id;

                        // Actualizar saldo de caja
                        $caja->aumentarSaldo($pago['monto_final']);
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
                        // Nuevos campos
                        'es_cuenta_corriente' => $esCuentaCorriente,
                        'afecta_caja' => $afectaCaja,
                        'estado' => 'activo',
                        'movimiento_caja_id' => $movimientoCajaId,
                    ]);

                    // Guardar ID y si requiere factura fiscal para usarlo después
                    $pagosCreados[$index] = [
                        'id' => $ventaPago->id,
                        'monto_final' => $ventaPago->monto_final,
                        'factura_fiscal' => $pago['factura_fiscal'] ?? false,
                    ];
                }

                // Actualizar saldo del cliente si hay cuenta corriente
                if ($tieneCuentaCorriente && $this->clienteSeleccionado) {
                    $cliente = Cliente::find($this->clienteSeleccionado);
                    $cliente->increment('saldo_deudor_cache', $montoCuentaCorriente);
                    $cliente->update(['ultimo_movimiento_cc_at' => now()]);
                }

                // Generar comprobante fiscal si corresponde
                $comprobanteFiscal = null;
                if ($debeFacturar) {
                    try {
                        // Filtrar pagos creados que tienen factura_fiscal = true
                        // Ahora usamos los IDs reales de VentaPago
                        $pagosConFactura = array_filter($pagosCreados, fn($p) => $p['factura_fiscal'] ?? false);
                        $opcionesFiscal = [];

                        // Si hay pagos específicos con factura fiscal, pasar para facturación parcial
                        if (!empty($pagosConFactura)) {
                            $opcionesFiscal['pagos_facturar'] = array_values($pagosConFactura);

                            Log::info('Facturación parcial - pagos con factura fiscal', [
                                'venta_id' => $venta->id,
                                'total_pagos_creados' => count($pagosCreados),
                                'pagos_con_factura' => count($pagosConFactura),
                                'pagos_facturar' => $opcionesFiscal['pagos_facturar'],
                            ]);
                        }

                        // Pasar el desglose de IVA ya calculado (con proporciones correctas)
                        if (!empty($this->desgloseIvaFiscal)) {
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
                        $this->dispatch('toast-error', message: 'Error al emitir factura fiscal: ' . $e->getMessage());
                        return;
                    }
                }

                DB::connection('pymes_tenant')->commit();

                // Mensaje de éxito
                $mensaje = "Venta #{$venta->numero} creada exitosamente";
                if ($comprobanteFiscal && $comprobanteFiscal->cae) {
                    $mensaje .= " - Factura {$comprobanteFiscal->numero_formateado} CAE: {$comprobanteFiscal->cae}";
                }

                $this->dispatch('toast-success', message: $mensaje);

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
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: ' . $e->getMessage());
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
            if (!$sucursal) {
                $this->dispatch('toast-error', message: 'Sucursal no encontrada');
                return;
            }

            $formaPago = FormaPago::find($this->formaPagoId);
            $esCuentaCorriente = $formaPago && strtoupper($formaPago->codigo) === 'CTA_CTE';
            $totalVenta = $this->resultado['total_final'] ?? 0;

            // Validar cliente si es cuenta corriente
            if ($esCuentaCorriente) {
                if (!$this->clienteSeleccionado) {
                    $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');
                    return;
                }

                $cliente = Cliente::find($this->clienteSeleccionado);
                if (!$cliente || !$cliente->tiene_cuenta_corriente) {
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
            if (!$esCuentaCorriente) {
                if (!$cajaId) {
                    $this->dispatch('toast-error', message: 'Debe seleccionar una caja');
                    return;
                }

                $caja = Caja::find($cajaId);
                if (!$caja || !$caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: 'La caja debe estar abierta');
                    return;
                }
            }

            // Verificar si debe generar factura fiscal
            // Se factura si:
            // 1. Automático: sucursal.facturacion_fiscal_automatica = true Y forma de pago tiene factura_fiscal = true
            // 2. Manual: el usuario marcó el checkbox emitirFacturaFiscal
            $comprobanteFiscalService = new ComprobanteFiscalService();
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
                ];

                $detalles = [];
                foreach ($this->items as $item) {
                    $detalles[] = [
                        'articulo_id' => $item['articulo_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'descuento' => 0,
                    ];
                }

                $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

                // Crear VentaPago para el pago único
                $afectaCaja = !$esCuentaCorriente && $cajaId;
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

                VentaPago::create([
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
                ]);

                // Actualizar saldo del cliente si es cuenta corriente
                if ($esCuentaCorriente && $this->clienteSeleccionado) {
                    $cliente = Cliente::find($this->clienteSeleccionado);
                    $cliente->increment('saldo_deudor_cache', $totalVenta);
                    $cliente->update(['ultimo_movimiento_cc_at' => now()]);
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
                        $this->dispatch('toast-error', message: 'Error al emitir factura fiscal: ' . $e->getMessage());
                        return;
                    }
                }

                DB::connection('pymes_tenant')->commit();

                // Mensaje de éxito
                $mensaje = "Venta #{$venta->numero} creada exitosamente";
                if ($comprobanteFiscal && $comprobanteFiscal->cae) {
                    $mensaje .= " - Factura {$comprobanteFiscal->numero_formateado} CAE: {$comprobanteFiscal->cae}";
                }

                $this->dispatch('toast-success', message: $mensaje);

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
                'trace' => $e->getTraceAsString()
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: ' . $e->getMessage());
        }
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
        $this->codigoBarrasInput = '';
        $this->articulosResultados = [];

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

        // Resetear facturación fiscal (pero mantener la config de sucursal)
        $this->montoFacturaFiscal = 0;
        $this->desgloseIvaFiscal = [];
        // Reestablecer emitirFacturaFiscal según la forma de pago por defecto
        $this->actualizarFacturaFiscalSegunFP();

        // Resetear selección de punto de venta fiscal
        $this->showPuntoVentaModal = false;
        $this->puntoVentaSeleccionadoId = null;
        $this->puntosVentaDisponibles = [];

        // Resetear modales
        $this->mostrarModalConsulta = false;
        $this->mostrarModalConcepto = false;
        $this->mostrarModalClienteRapido = false;
        $this->articuloConsulta = null;
        $this->modoConsulta = false;
        $this->modoBusqueda = false;
        $this->itemResaltado = null;

        // Resetear concepto
        $this->conceptoDescripcion = '';
        $this->conceptoCategoriaId = null;
        $this->conceptoImporte = 0;

        // Resetear cliente rápido
        $this->clienteRapidoNombre = '';
        $this->clienteRapidoTelefono = '';

        // Volver a valores por defecto de selectores
        $efectivo = collect($this->formasPago)->firstWhere('codigo', 'efectivo');
        $this->formaPagoId = $efectivo['id'] ?? $this->formasPago[0]['id'] ?? 1;

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
            $imprimirTicket = $imprimirTicketConfig && ($tieneMontoNoFiscal || !$comprobanteFiscal);

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
                'error' => $e->getMessage()
            ]);
        }
    }
}
