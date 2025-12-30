<?php

namespace App\Livewire\Componentes;

use Livewire\Component;
use App\Models\Articulo;
use App\Models\Promocion;
use App\Models\ListaPrecio;
use App\Models\Sucursal;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\FormaPago;

/**
 * Componente reutilizable para simular ventas y calcular promociones
 *
 * Uso:
 * <livewire:componentes.simulador-venta
 *     :promocion-preview="$datosPromocion"
 *     :excluir-promocion-id="$promocionId"
 *     :sucursales-permitidas="$sucursalesSeleccionadas"
 * />
 *
 * Parámetros:
 * - promocionPreview: array con datos de una promoción que se está creando/editando para incluir en la simulación
 * - excluirPromocionId: ID de promoción a excluir (útil en edición para no duplicarla)
 * - sucursalesPermitidas: array de IDs de sucursales permitidas para el filtro
 */
class SimuladorVenta extends Component
{
    // Configuración del componente
    public $promocionPreview = null; // Promoción en creación/edición para simular
    public $excluirPromocionId = null; // ID de promoción a excluir de la lista
    public $sucursalesPermitidas = []; // Sucursales disponibles para filtrar

    // Items del simulador
    public $itemsSimulador = [];
    public $resultadoSimulador = null;

    // Buscador de artículos
    public $busquedaArticuloSimulador = '';
    public $articulosSimuladorResultados = [];
    public $mostrarBuscadorArticulos = false;

    // Filtros del simulador
    public $simuladorSucursalId = null;
    public $simuladorFormaVentaId = null;
    public $simuladorCanalVentaId = null;
    public $simuladorFormaPagoId = null;
    public $simuladorListaPrecioId = null;

    // Listas de precios disponibles
    public $listasPreciosSimulador = [];

    // Colecciones para selects
    public $sucursales = [];
    public $formasVenta = [];
    public $canalesVenta = [];
    public $formasPago = [];

    // Promociones competidoras
    public $promocionesCompetidoras = [];

    // Control de UI
    public $mostrarFiltrosSimulador = true;

    protected $listeners = [
        'actualizarPromocionPreview' => 'setPromocionPreview',
        'refrescarSimulador' => 'refrescarTodo',
    ];

    public function mount(
        $promocionPreview = null,
        $excluirPromocionId = null,
        $sucursalesPermitidas = []
    ) {
        $this->promocionPreview = $promocionPreview;
        $this->excluirPromocionId = $excluirPromocionId;
        $this->sucursalesPermitidas = $sucursalesPermitidas;

        // Cargar colecciones
        $this->cargarColecciones();

        // Inicializar filtros si hay sucursales permitidas
        if (!empty($this->sucursalesPermitidas)) {
            $this->simuladorSucursalId = $this->sucursalesPermitidas[0];
            $this->cargarListasPreciosSimulador();
            $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();
            $this->cargarPromocionesCompetidoras();
        }
    }

    protected function cargarColecciones()
    {
        // Cargar sucursales según las permitidas
        if (!empty($this->sucursalesPermitidas)) {
            $this->sucursales = Sucursal::whereIn('id', $this->sucursalesPermitidas)
                ->select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        } else {
            $this->sucursales = Sucursal::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        }

        $this->formasVenta = FormaVenta::activas()->get();
        $this->canalesVenta = CanalVenta::activos()->get();
        $this->formasPago = FormaPago::activas()->get();
    }

    /**
     * Actualiza la promoción en preview (llamado desde el padre)
     */
    public function setPromocionPreview($datos)
    {
        $this->promocionPreview = $datos;
        $this->simularVenta();
    }

    /**
     * Actualiza las sucursales permitidas y refresca
     */
    public function setSucursalesPermitidas($sucursales)
    {
        $this->sucursalesPermitidas = $sucursales;
        $this->cargarColecciones();

        // Si la sucursal actual no está en las permitidas, cambiar
        if (!empty($sucursales) && !in_array($this->simuladorSucursalId, $sucursales)) {
            $this->simuladorSucursalId = $sucursales[0];
            $this->cargarListasPreciosSimulador();
            $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();
        }

        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    /**
     * Refresca todo el simulador
     */
    public function refrescarTodo()
    {
        $this->cargarPromocionesCompetidoras();
        $this->actualizarPreciosItemsSimulador();
        $this->simularVenta();
    }

    // ==================== Métodos de búsqueda de artículos ====================

    public function updatedBusquedaArticuloSimulador($value)
    {
        $this->cargarArticulosSimulador($value);
    }

    public function abrirBuscadorArticulos()
    {
        $this->mostrarBuscadorArticulos = true;
        $this->cargarArticulosSimulador($this->busquedaArticuloSimulador);
    }

    public function cerrarBuscadorArticulos()
    {
        $this->mostrarBuscadorArticulos = false;
        $this->articulosSimuladorResultados = [];
    }

    protected function cargarArticulosSimulador(string $busqueda = '')
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        if (strlen($busqueda) >= 1) {
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
            });
        }

        $articulos = $query->orderBy('nombre')
            ->limit(15)
            ->get();

        $this->articulosSimuladorResultados = $articulos->map(function($art) {
            return [
                'id' => $art->id,
                'nombre' => $art->nombre,
                'codigo' => $art->codigo,
                'codigo_barras' => $art->codigo_barras,
                'categoria_id' => $art->categoria_id,
                'categoria' => $art->categoriaModel ? ['nombre' => $art->categoriaModel->nombre] : null,
                'precio_base' => $art->precio_base ?? 0,
                'precio' => $this->obtenerPrecioConLista($art),
            ];
        })->toArray();
    }

    public function agregarArticuloSimulador($articuloId)
    {
        $articulo = Articulo::with('categoriaModel')->find($articuloId);
        if ($articulo) {
            $precioLista = $this->obtenerPrecioConLista($articulo);
            $this->itemsSimulador[] = [
                'articulo_id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
                'categoria_id' => $articulo->categoria_id,
                'categoria_nombre' => $articulo->categoriaModel?->nombre,
                'precio_base' => $articulo->precio_base ?? 0,
                'precio' => $precioLista,
                'cantidad' => 1,
            ];
            $this->busquedaArticuloSimulador = '';
            $this->articulosSimuladorResultados = [];
            $this->mostrarBuscadorArticulos = false;
            $this->simularVenta();
        }
    }

    public function agregarPrimerArticulo()
    {
        if (!empty($this->busquedaArticuloSimulador)) {
            $articuloPorBarras = Articulo::where('activo', true)
                ->where(function($q) {
                    $q->where('codigo_barras', $this->busquedaArticuloSimulador)
                      ->orWhere('codigo', $this->busquedaArticuloSimulador);
                })
                ->first();

            if ($articuloPorBarras) {
                $this->agregarArticuloSimulador($articuloPorBarras->id);
                return;
            }
        }

        if (!empty($this->articulosSimuladorResultados)) {
            $this->agregarArticuloSimulador($this->articulosSimuladorResultados[0]['id']);
        }
    }

    public function eliminarItemSimulador($index)
    {
        unset($this->itemsSimulador[$index]);
        $this->itemsSimulador = array_values($this->itemsSimulador);
        $this->simularVenta();
    }

    public function updatedItemsSimulador()
    {
        $this->simularVenta();
    }

    // ==================== Métodos de filtros ====================

    public function updatedSimuladorSucursalId()
    {
        $this->simuladorListaPrecioId = null;
        $this->cargarListasPreciosSimulador();
        $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorListaPrecioId()
    {
        $this->actualizarPreciosItemsSimulador();
        $this->simularVenta();
    }

    public function updatedSimuladorFormaVentaId()
    {
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorCanalVentaId()
    {
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorFormaPagoId()
    {
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    // ==================== Métodos de listas de precios ====================

    protected function cargarListasPreciosSimulador(): void
    {
        if (!$this->simuladorSucursalId) {
            $this->listasPreciosSimulador = [];
            return;
        }

        $this->listasPreciosSimulador = ListaPrecio::porSucursal($this->simuladorSucursalId)
            ->activas()
            ->ordenadoPorPrioridad()
            ->get()
            ->map(function ($lista) {
                return [
                    'id' => (int) $lista->id,
                    'nombre' => $lista->nombre,
                    'es_lista_base' => (bool) $lista->es_lista_base,
                    'ajuste_porcentaje' => (float) $lista->ajuste_porcentaje,
                    'descripcion_ajuste' => $lista->obtenerDescripcionAjuste(),
                ];
            })
            ->toArray();
    }

    protected function obtenerIdListaBaseSimulador(): ?int
    {
        if (empty($this->listasPreciosSimulador)) {
            return null;
        }

        foreach ($this->listasPreciosSimulador as $lista) {
            if ($lista['es_lista_base']) {
                return (int) $lista['id'];
            }
        }

        return isset($this->listasPreciosSimulador[0]['id'])
            ? (int) $this->listasPreciosSimulador[0]['id']
            : null;
    }

    protected function obtenerPrecioConLista(Articulo $articulo): float
    {
        if (!$this->simuladorListaPrecioId) {
            return (float) $articulo->precio_base;
        }

        $listaPrecio = ListaPrecio::find($this->simuladorListaPrecioId);
        if (!$listaPrecio) {
            return (float) $articulo->precio_base;
        }

        $contexto = [
            'forma_pago_id' => $this->simuladorFormaPagoId,
            'forma_venta_id' => $this->simuladorFormaVentaId,
            'canal_venta_id' => $this->simuladorCanalVentaId,
        ];

        if (!$listaPrecio->es_lista_base && !$listaPrecio->validarCondiciones($contexto)) {
            $listaBase = ListaPrecio::obtenerListaBase($this->simuladorSucursalId);
            if ($listaBase) {
                $precioInfo = $listaBase->obtenerPrecioArticulo($articulo);
                return $precioInfo['precio'];
            }
            return (float) $articulo->precio_base;
        }

        $precioInfo = $listaPrecio->obtenerPrecioArticulo($articulo);
        return $precioInfo['precio'];
    }

    protected function actualizarPreciosItemsSimulador(): void
    {
        foreach ($this->itemsSimulador as $index => $item) {
            $articulo = Articulo::find($item['articulo_id']);
            if ($articulo) {
                $this->itemsSimulador[$index]['precio'] = $this->obtenerPrecioConLista($articulo);
            }
        }
    }

    // ==================== Métodos de promociones ====================

    public function cargarPromocionesCompetidoras()
    {
        $sucursalId = $this->simuladorSucursalId;

        if (empty($sucursalId)) {
            $this->promocionesCompetidoras = collect();
            return;
        }

        $query = Promocion::query()
            ->activas()
            ->vigentes()
            ->with(['condiciones', 'escalas', 'sucursal'])
            ->where('sucursal_id', $sucursalId);

        // Excluir promoción si se especificó
        if ($this->excluirPromocionId) {
            $query->where('id', '!=', $this->excluirPromocionId);
        }

        // Filtrar por alcance de artículos si hay promoción en preview
        if ($this->promocionPreview) {
            $alcance = $this->promocionPreview['alcance_articulos'] ?? 'todos';
            $articuloId = $this->promocionPreview['articulo_id'] ?? null;
            $categoriaId = $this->promocionPreview['categoria_id'] ?? null;

            if ($alcance === 'articulo' && $articuloId) {
                $query->where(function ($q) use ($articuloId) {
                    $q->whereHas('condiciones', function ($subQ) use ($articuloId) {
                        $subQ->where('tipo_condicion', 'por_articulo')
                             ->where('articulo_id', $articuloId);
                    })->orWhereDoesntHave('condiciones', function ($subQ) {
                        $subQ->whereIn('tipo_condicion', ['por_articulo', 'por_categoria']);
                    });
                });
            } elseif ($alcance === 'categoria' && $categoriaId) {
                $query->where(function ($q) use ($categoriaId) {
                    $q->whereHas('condiciones', function ($subQ) use ($categoriaId) {
                        $subQ->where('tipo_condicion', 'por_categoria')
                             ->where('categoria_id', $categoriaId);
                    })->orWhereDoesntHave('condiciones', function ($subQ) {
                        $subQ->whereIn('tipo_condicion', ['por_articulo', 'por_categoria']);
                    });
                });
            }
        }

        // Filtrar por forma de venta
        if ($this->simuladorFormaVentaId) {
            $formaVentaFiltro = $this->simuladorFormaVentaId;
            $query->where(function ($q) use ($formaVentaFiltro) {
                $q->whereHas('condiciones', function ($subQ) use ($formaVentaFiltro) {
                    $subQ->where('tipo_condicion', 'por_forma_venta')
                         ->where('forma_venta_id', $formaVentaFiltro);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_forma_venta');
                });
            });
        }

        // Filtrar por canal de venta
        if ($this->simuladorCanalVentaId) {
            $canalVentaFiltro = $this->simuladorCanalVentaId;
            $query->where(function ($q) use ($canalVentaFiltro) {
                $q->whereHas('condiciones', function ($subQ) use ($canalVentaFiltro) {
                    $subQ->where('tipo_condicion', 'por_canal')
                         ->where('canal_venta_id', $canalVentaFiltro);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_canal');
                });
            });
        }

        // Filtrar por forma de pago
        if ($this->simuladorFormaPagoId) {
            $formaPagoFiltro = $this->simuladorFormaPagoId;
            $query->where(function ($q) use ($formaPagoFiltro) {
                $q->whereHas('condiciones', function ($subQ) use ($formaPagoFiltro) {
                    $subQ->where('tipo_condicion', 'por_forma_pago')
                         ->where('forma_pago_id', $formaPagoFiltro);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_forma_pago');
                });
            });
        }

        $this->promocionesCompetidoras = $query->ordenadoPorPrioridad()->get();
    }

    // ==================== Simulación de venta ====================

    public function simularVenta()
    {
        if (empty($this->itemsSimulador)) {
            $this->resultadoSimulador = null;
            return;
        }

        $resultado = [
            'items' => [],
            'subtotal' => 0,
            'promociones_resumen' => [],
            'total_descuentos' => 0,
            'total_recargos' => 0,
            'total_final' => 0,
            'combinaciones_evaluadas' => 0,
            'promociones_venta' => [],
        ];

        // Calcular subtotal y cantidad total
        $subtotalVenta = 0;
        $cantidadTotalVenta = 0;
        foreach ($this->itemsSimulador as $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 1);
            $subtotalVenta += $precio * $cantidad;
            $cantidadTotalVenta += $cantidad;
        }

        // Preparar promociones
        $todasPromociones = $this->prepararPromocionesParaSimulacion();

        // Separar por nivel de aplicación
        $promocionesArticulo = [];
        $promocionesVenta = [];
        foreach ($todasPromociones as $promo) {
            if ($promo['tipo'] === 'descuento_monto') {
                $promocionesVenta[] = $promo;
            } else {
                $promocionesArticulo[] = $promo;
            }
        }

        // Manejar combinabilidad
        $promocionNuevaNoCombinable = null;
        foreach ($todasPromociones as $promo) {
            if ($promo['es_nueva'] && !$promo['combinable']) {
                $promocionNuevaNoCombinable = $promo;
                break;
            }
        }

        if ($promocionNuevaNoCombinable) {
            $promocionesArticulo = array_filter($promocionesArticulo, fn($p) => $p['es_nueva']);
            $promocionesVenta = array_filter($promocionesVenta, fn($p) => $p['es_nueva']);
        } else {
            $promocionesArticulo = array_filter($promocionesArticulo, fn($p) => $p['combinable'] || $p['es_nueva']);
            $promocionesVenta = array_filter($promocionesVenta, fn($p) => $p['combinable'] || $p['es_nueva']);
        }

        // Contexto de la venta
        $contextoVenta = [
            'subtotal' => $subtotalVenta,
            'cantidad_total' => $cantidadTotalVenta,
            'forma_pago_id' => $this->simuladorFormaPagoId,
            'forma_venta_id' => $this->simuladorFormaVentaId,
            'canal_venta_id' => $this->simuladorCanalVentaId,
        ];

        // Procesar cada artículo
        foreach ($this->itemsSimulador as $item) {
            $itemResultado = $this->procesarItemSimulacion($item, $promocionesArticulo, $contextoVenta);
            $resultado['items'][] = $itemResultado;
            $resultado['subtotal'] += $itemResultado['subtotal_original'];
            $resultado['total_descuentos'] += $itemResultado['total_descuento'];
            $resultado['total_recargos'] += $itemResultado['total_recargo'];
            $resultado['combinaciones_evaluadas'] += $itemResultado['combinaciones_evaluadas'];
        }

        // Total parcial
        $totalParcial = $resultado['subtotal'] - $resultado['total_descuentos'] + $resultado['total_recargos'];

        // Aplicar promociones a nivel de venta
        $descuentoVenta = 0;
        foreach ($promocionesVenta as $promo) {
            if (!$this->promocionCumpleCondiciones($promo, $contextoVenta)) {
                continue;
            }

            if ($promo['tipo'] === 'descuento_monto' && $promo['valor'] > 0) {
                $descuentoMonto = min($promo['valor'], $totalParcial - $descuentoVenta);
                if ($descuentoMonto > 0) {
                    $resultado['promociones_venta'][] = [
                        'id' => $promo['id'],
                        'nombre' => $promo['nombre'],
                        'es_nueva' => $promo['es_nueva'],
                        'tipo_ajuste' => 'descuento',
                        'valor_ajuste' => $descuentoMonto,
                        'porcentaje' => null,
                        'monto_fijo' => $promo['valor'],
                        'combinable' => $promo['combinable'],
                    ];
                    $descuentoVenta += $descuentoMonto;
                }
            }
        }

        $resultado['total_descuentos'] += $descuentoVenta;
        $resultado['promociones_resumen'] = $this->construirResumenPromociones($resultado['items'], $todasPromociones, $contextoVenta, $resultado['promociones_venta']);
        $resultado['total_final'] = max(0, $resultado['subtotal'] - $resultado['total_descuentos'] + $resultado['total_recargos']);

        $this->resultadoSimulador = $resultado;
    }

    private function prepararPromocionesParaSimulacion(): array
    {
        $todasPromociones = [];

        // Agregar promociones existentes
        foreach ($this->promocionesCompetidoras as $promo) {
            $condiciones = $promo->condiciones;
            $condicionArticulo = $condiciones->firstWhere('tipo_condicion', 'por_articulo');
            $condicionCategoria = $condiciones->firstWhere('tipo_condicion', 'por_categoria');
            $condicionMontoMinimo = $condiciones->firstWhere('tipo_condicion', 'por_total_compra');
            $condicionCantidadMinima = $condiciones->firstWhere('tipo_condicion', 'por_cantidad');
            $condicionFormaPago = $condiciones->firstWhere('tipo_condicion', 'por_forma_pago');
            $condicionFormaVenta = $condiciones->firstWhere('tipo_condicion', 'por_forma_venta');
            $condicionCanalVenta = $condiciones->firstWhere('tipo_condicion', 'por_canal');

            $todasPromociones[] = [
                'id' => $promo->id,
                'nombre' => $promo->nombre,
                'tipo' => $promo->tipo,
                'valor' => $promo->valor,
                'prioridad' => $promo->prioridad,
                'combinable' => $promo->combinable,
                'es_nueva' => false,
                'escalas' => $promo->escalas->toArray(),
                'articulo_id' => $condicionArticulo?->articulo_id,
                'categoria_id' => $condicionCategoria?->categoria_id,
                'monto_minimo' => $condicionMontoMinimo?->monto_minimo,
                'cantidad_minima' => $condicionCantidadMinima?->cantidad_minima,
                'forma_pago_id' => $condicionFormaPago?->forma_pago_id,
                'forma_venta_id' => $condicionFormaVenta?->forma_venta_id,
                'canal_venta_id' => $condicionCanalVenta?->canal_venta_id,
            ];
        }

        // Agregar promoción en preview si existe
        if ($this->promocionPreview) {
            $todasPromociones[] = [
                'id' => 'nueva',
                'nombre' => $this->promocionPreview['nombre'] ?: '(Esta promoción)',
                'tipo' => $this->promocionPreview['tipo'],
                'valor' => $this->promocionPreview['valor'] ?? 0,
                'prioridad' => (int) ($this->promocionPreview['prioridad'] ?? 1),
                'combinable' => $this->promocionPreview['combinable'] ?? false,
                'es_nueva' => true,
                'escalas' => $this->promocionPreview['escalas'] ?? [],
                'articulo_id' => ($this->promocionPreview['alcance_articulos'] ?? 'todos') === 'articulo'
                    ? ($this->promocionPreview['articulo_id'] ?? null)
                    : null,
                'categoria_id' => ($this->promocionPreview['alcance_articulos'] ?? 'todos') === 'categoria'
                    ? ($this->promocionPreview['categoria_id'] ?? null)
                    : null,
                'monto_minimo' => $this->promocionPreview['monto_minimo'] ?? null,
                'cantidad_minima' => $this->promocionPreview['cantidad_minima'] ?? null,
                'forma_pago_id' => $this->promocionPreview['forma_pago_id'] ?? null,
                'forma_venta_id' => $this->promocionPreview['forma_venta_id'] ?? null,
                'canal_venta_id' => $this->promocionPreview['canal_venta_id'] ?? null,
            ];
        }

        return $todasPromociones;
    }

    private function procesarItemSimulacion(array $item, array $todasPromociones, array $contextoVenta): array
    {
        $precio = (float) ($item['precio'] ?? 0);
        $cantidad = (int) ($item['cantidad'] ?? 1);
        $subtotalOriginal = $precio * $cantidad;
        $articuloId = $item['articulo_id'] ?? null;
        $categoriaId = $item['categoria_id'] ?? null;

        $itemResultado = [
            'articulo_id' => $articuloId,
            'nombre' => $item['nombre'] ?? 'Artículo',
            'categoria_nombre' => $item['categoria_nombre'] ?? null,
            'precio_unitario' => $precio,
            'cantidad' => $cantidad,
            'subtotal_original' => $subtotalOriginal,
            'promociones_aplicadas' => [],
            'total_descuento' => 0,
            'total_recargo' => 0,
            'subtotal_final' => $subtotalOriginal,
            'combinaciones_evaluadas' => 0,
        ];

        $promocionesParaItem = [];
        foreach ($todasPromociones as $promo) {
            if ($this->promocionAplicaAItem($promo, $articuloId, $categoriaId)
                && $this->promocionCumpleCondiciones($promo, $contextoVenta)) {
                $promocionesParaItem[] = $promo;
            }
        }

        if (empty($promocionesParaItem)) {
            return $itemResultado;
        }

        $descuentos = array_filter($promocionesParaItem, fn($p) => $this->esDescuento($p['tipo']));
        $recargos = array_filter($promocionesParaItem, fn($p) => !$this->esDescuento($p['tipo']));

        $mejorDescuentos = $this->encontrarMejorCombinacionParaItem(
            array_values($descuentos),
            $subtotalOriginal,
            $cantidad,
            'descuento'
        );

        $mejorRecargos = $this->encontrarMejorCombinacionParaItem(
            array_values($recargos),
            $mejorDescuentos['monto_final'],
            $cantidad,
            'recargo'
        );

        $itemResultado['combinaciones_evaluadas'] = $mejorDescuentos['combinaciones_evaluadas'] + $mejorRecargos['combinaciones_evaluadas'];

        foreach ($mejorDescuentos['promociones_aplicadas'] as $pa) {
            $itemResultado['promociones_aplicadas'][] = $pa;
            $itemResultado['total_descuento'] += $pa['valor_ajuste'];
        }

        foreach ($mejorRecargos['promociones_aplicadas'] as $pa) {
            $itemResultado['promociones_aplicadas'][] = $pa;
            $itemResultado['total_recargo'] += $pa['valor_ajuste'];
        }

        $itemResultado['subtotal_final'] = max(0, $subtotalOriginal - $itemResultado['total_descuento'] + $itemResultado['total_recargo']);

        return $itemResultado;
    }

    private function promocionAplicaAItem(array $promo, ?int $articuloId, ?int $categoriaId): bool
    {
        if ($promo['articulo_id'] !== null) {
            return $promo['articulo_id'] == $articuloId;
        }

        if ($promo['categoria_id'] !== null) {
            return $promo['categoria_id'] == $categoriaId;
        }

        return true;
    }

    private function promocionCumpleCondiciones(array $promo, array $contextoVenta): bool
    {
        if (!empty($promo['monto_minimo'])) {
            if ($contextoVenta['subtotal'] < (float) $promo['monto_minimo']) {
                return false;
            }
        }

        if (!empty($promo['cantidad_minima'])) {
            if ($contextoVenta['cantidad_total'] < (int) $promo['cantidad_minima']) {
                return false;
            }
        }

        if (!empty($promo['forma_pago_id'])) {
            if (!empty($contextoVenta['forma_pago_id'])) {
                if ($promo['forma_pago_id'] != $contextoVenta['forma_pago_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if (!empty($promo['forma_venta_id'])) {
            if (!empty($contextoVenta['forma_venta_id'])) {
                if ($promo['forma_venta_id'] != $contextoVenta['forma_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if (!empty($promo['canal_venta_id'])) {
            if (!empty($contextoVenta['canal_venta_id'])) {
                if ($promo['canal_venta_id'] != $contextoVenta['canal_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    private function encontrarMejorCombinacionParaItem(array $promociones, float $montoInicial, int $cantidad, string $objetivo): array
    {
        if (empty($promociones)) {
            return [
                'monto_final' => $montoInicial,
                'promociones_aplicadas' => [],
                'combinaciones_evaluadas' => 0,
            ];
        }

        $mejorResultado = [
            'monto_final' => $objetivo === 'descuento' ? $montoInicial : PHP_FLOAT_MAX,
            'promociones_aplicadas' => [],
            'prioridad_suma' => PHP_INT_MAX,
        ];

        $combinacionesEvaluadas = 0;
        $n = count($promociones);
        $totalCombinaciones = pow(2, $n);

        for ($i = 1; $i < $totalCombinaciones; $i++) {
            $combinacion = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i & (1 << $j)) {
                    $combinacion[] = $promociones[$j];
                }
            }

            if (!$this->esCombinacionValida($combinacion)) {
                continue;
            }

            $combinacionesEvaluadas++;
            $resultadoCombinacion = $this->calcularCombinacionParaItem($combinacion, $montoInicial, $cantidad);

            $esMejor = false;
            if ($objetivo === 'descuento') {
                if ($resultadoCombinacion['monto_final'] < $mejorResultado['monto_final']) {
                    $esMejor = true;
                } elseif ($resultadoCombinacion['monto_final'] == $mejorResultado['monto_final']) {
                    if ($resultadoCombinacion['prioridad_suma'] < $mejorResultado['prioridad_suma']) {
                        $esMejor = true;
                    }
                }
            } else {
                if ($resultadoCombinacion['monto_final'] < $mejorResultado['monto_final']) {
                    $esMejor = true;
                }
            }

            if ($esMejor) {
                $mejorResultado = $resultadoCombinacion;
            }
        }

        if ($objetivo === 'recargo' && $mejorResultado['monto_final'] === PHP_FLOAT_MAX) {
            $mejorResultado['monto_final'] = $montoInicial;
        }

        $mejorResultado['combinaciones_evaluadas'] = $combinacionesEvaluadas;

        return $mejorResultado;
    }

    private function calcularCombinacionParaItem(array $combinacion, float $montoInicial, int $cantidad): array
    {
        usort($combinacion, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $montoActual = $montoInicial;
        $promocionesAplicadas = [];
        $prioridadSuma = 0;

        foreach ($combinacion as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoActual, $cantidad);

            if ($ajuste['valor'] > 0) {
                $montoAnterior = $montoActual;

                if ($ajuste['tipo'] === 'descuento') {
                    $montoActual -= $ajuste['valor'];
                } else {
                    $montoActual += $ajuste['valor'];
                }

                $promocionesAplicadas[] = [
                    'id' => $promo['id'],
                    'nombre' => $promo['nombre'],
                    'es_nueva' => $promo['es_nueva'],
                    'tipo_ajuste' => $ajuste['tipo'],
                    'valor_ajuste' => $ajuste['valor'],
                    'porcentaje' => $ajuste['porcentaje'] ?? null,
                    'monto_anterior' => $montoAnterior,
                    'monto_despues' => $montoActual,
                    'prioridad' => $promo['prioridad'],
                ];

                $prioridadSuma += (int) ($promo['prioridad'] ?? 0);
            }
        }

        return [
            'monto_final' => max(0, $montoActual),
            'promociones_aplicadas' => $promocionesAplicadas,
            'prioridad_suma' => $prioridadSuma,
        ];
    }

    private function construirResumenPromociones(array $items, array $todasPromociones, array $contextoVenta, array $promocionesVenta = []): array
    {
        $resumen = [];

        foreach ($todasPromociones as $promo) {
            $promoResumen = [
                'id' => $promo['id'],
                'nombre' => $promo['nombre'],
                'es_nueva' => $promo['es_nueva'],
                'prioridad' => $promo['prioridad'],
                'aplicada_en' => [],
                'total_descuento' => 0,
                'total_recargo' => 0,
            ];

            foreach ($items as $item) {
                foreach ($item['promociones_aplicadas'] as $pa) {
                    if ($pa['id'] === $promo['id']) {
                        $promoResumen['aplicada_en'][] = [
                            'articulo' => $item['nombre'],
                            'valor' => $pa['valor_ajuste'],
                            'tipo' => $pa['tipo_ajuste'],
                        ];
                        if ($pa['tipo_ajuste'] === 'descuento') {
                            $promoResumen['total_descuento'] += $pa['valor_ajuste'];
                        } else {
                            $promoResumen['total_recargo'] += $pa['valor_ajuste'];
                        }
                    }
                }
            }

            foreach ($promocionesVenta as $pv) {
                if ($pv['id'] === $promo['id']) {
                    $promoResumen['aplicada_en'][] = [
                        'articulo' => 'Total de la venta',
                        'valor' => $pv['valor_ajuste'],
                        'tipo' => $pv['tipo_ajuste'],
                    ];
                    if ($pv['tipo_ajuste'] === 'descuento') {
                        $promoResumen['total_descuento'] += $pv['valor_ajuste'];
                    } else {
                        $promoResumen['total_recargo'] += $pv['valor_ajuste'];
                    }
                }
            }

            $promoResumen['aplicada'] = count($promoResumen['aplicada_en']) > 0;

            if (!$promoResumen['aplicada']) {
                $promoResumen['razon'] = $this->determinarRazonNoAplicada($promo, $items, $contextoVenta);
            }

            $resumen[] = $promoResumen;
        }

        usort($resumen, function($a, $b) {
            if ($a['aplicada'] !== $b['aplicada']) {
                return $b['aplicada'] <=> $a['aplicada'];
            }
            return $a['prioridad'] <=> $b['prioridad'];
        });

        return $resumen;
    }

    private function determinarRazonNoAplicada(array $promo, array $items, array $contextoVenta): string
    {
        if (!empty($promo['forma_pago_id'])) {
            if (empty($contextoVenta['forma_pago_id'])) {
                return 'Requiere forma de pago específica';
            }
            if ($promo['forma_pago_id'] != $contextoVenta['forma_pago_id']) {
                return 'No aplica a esta forma de pago';
            }
        }

        if (!empty($promo['forma_venta_id'])) {
            if (empty($contextoVenta['forma_venta_id'])) {
                return 'Requiere forma de venta específica';
            }
            if ($promo['forma_venta_id'] != $contextoVenta['forma_venta_id']) {
                return 'No aplica a esta forma de venta';
            }
        }

        if (!empty($promo['canal_venta_id'])) {
            if (empty($contextoVenta['canal_venta_id'])) {
                return 'Requiere canal de venta específico';
            }
            if ($promo['canal_venta_id'] != $contextoVenta['canal_venta_id']) {
                return 'No aplica a este canal de venta';
            }
        }

        if (!empty($promo['monto_minimo'])) {
            $montoMinimo = (float) $promo['monto_minimo'];
            if ($contextoVenta['subtotal'] < $montoMinimo) {
                $faltante = $montoMinimo - $contextoVenta['subtotal'];
                return "Monto mínimo no alcanzado (faltan $" . number_format($faltante, 0, ',', '.') . ")";
            }
        }

        if (!empty($promo['cantidad_minima'])) {
            $cantidadMinima = (int) $promo['cantidad_minima'];
            if ($contextoVenta['cantidad_total'] < $cantidadMinima) {
                $faltante = $cantidadMinima - $contextoVenta['cantidad_total'];
                return "Cantidad mínima no alcanzada (faltan {$faltante} unidades)";
            }
        }

        $aplicaAlguno = false;
        foreach ($items as $item) {
            if ($this->promocionAplicaAItem($promo, $item['articulo_id'], $item['categoria_id'] ?? null)) {
                $aplicaAlguno = true;
                break;
            }
        }

        if (!$aplicaAlguno) {
            return 'No aplica a estos artículos';
        }

        return 'No incluida en combinación óptima';
    }

    private function esDescuento(string $tipo): bool
    {
        return in_array($tipo, ['descuento_porcentaje', 'descuento_monto', 'precio_fijo', 'descuento_escalonado']);
    }

    private function esCombinacionValida(array $combinacion): bool
    {
        if (count($combinacion) <= 1) {
            return true;
        }

        $noCombinable = 0;
        foreach ($combinacion as $promo) {
            if (!$promo['combinable']) {
                $noCombinable++;
            }
        }

        if ($noCombinable > 0 && count($combinacion) > 1) {
            return false;
        }

        return true;
    }

    private function calcularAjustePromocion($promo, $monto, $cantidad)
    {
        switch ($promo['tipo']) {
            case 'descuento_porcentaje':
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => $promo['valor'],
                    'valor' => round($monto * ($promo['valor'] / 100), 2),
                ];

            case 'descuento_monto':
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => null,
                    'valor' => min($promo['valor'], $monto),
                ];

            case 'precio_fijo':
                $precioFijoTotal = $promo['valor'] * $cantidad;
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => null,
                    'valor' => max(0, $monto - $precioFijoTotal),
                    'precio_fijo_unitario' => $promo['valor'],
                ];

            case 'recargo_porcentaje':
                return [
                    'tipo' => 'recargo',
                    'porcentaje' => $promo['valor'],
                    'valor' => round($monto * ($promo['valor'] / 100), 2),
                ];

            case 'recargo_monto':
                return [
                    'tipo' => 'recargo',
                    'porcentaje' => null,
                    'valor' => $promo['valor'],
                ];

            case 'descuento_escalonado':
                $escalas = collect($promo['escalas'])
                    ->filter(fn($e) => !empty($e['cantidad_desde']) && !empty($e['valor']))
                    ->sortByDesc('cantidad_desde');

                foreach ($escalas as $escala) {
                    if ($cantidad >= $escala['cantidad_desde']) {
                        $tipoDesc = $escala['tipo_descuento'] ?? 'porcentaje';
                        if ($tipoDesc === 'porcentaje') {
                            return [
                                'tipo' => 'descuento',
                                'porcentaje' => $escala['valor'],
                                'valor' => round($monto * ($escala['valor'] / 100), 2),
                            ];
                        } elseif ($tipoDesc === 'monto') {
                            return [
                                'tipo' => 'descuento',
                                'porcentaje' => null,
                                'valor' => min($escala['valor'], $monto),
                            ];
                        } elseif ($tipoDesc === 'precio_fijo') {
                            return [
                                'tipo' => 'descuento',
                                'porcentaje' => null,
                                'valor' => max(0, $monto - $escala['valor']),
                            ];
                        }
                    }
                }
                return ['tipo' => 'descuento', 'porcentaje' => 0, 'valor' => 0];

            default:
                return ['tipo' => 'descuento', 'porcentaje' => 0, 'valor' => 0];
        }
    }

    public function render()
    {
        return view('livewire.componentes.simulador-venta');
    }
}
