<?php

namespace App\Livewire\Configuracion\Promociones;

use Livewire\Component;
use App\Models\Promocion;
use App\Models\PromocionCondicion;
use App\Models\PromocionEscala;
use App\Models\Sucursal;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\FormaPago;
use App\Models\ListaPrecio;

class WizardPromocion extends Component
{
    // Control del wizard
    public $pasoActual = 1;
    public $totalPasos = 5;
    public $modoEdicion = false;
    public $promocionId = null;

    // Paso 1: Tipo de promoción
    public $tipo = null;

    // Paso 2: Configuración básica
    public $nombre = '';
    public $descripcion = '';
    public $codigoCupon = '';
    public $valor = null;

    // Paso 5: Prioridad y simulador
    public $prioridad = 1;
    public $promocionesCompetidoras = [];
    public $mostrarModalEdicion = false;

    // Simulador de venta
    public $itemsSimulador = [];
    public $resultadoSimulador = null;
    public $busquedaArticuloSimulador = '';
    public $articulosSimuladorResultados = [];
    public $mostrarFiltrosSimulador = true;
    public $mostrarBuscadorArticulos = false;

    // Filtros del simulador
    public $simuladorSucursalId = null;
    public $simuladorFormaVentaId = null;
    public $simuladorCanalVentaId = null;
    public $simuladorFormaPagoId = null;
    public $simuladorListaPrecioId = null;

    // Listas de precios disponibles para el simulador
    public $listasPreciosSimulador = [];

    // Escalas (para tipo escalonado)
    public $escalas = [];

    // Paso 3: Alcance
    public $sucursalesSeleccionadas = [];
    public $articuloId = null;
    public $categoriaId = null;
    public $busquedaArticulo = '';
    public $alcanceArticulos = 'todos'; // todos, categoria, articulo
    public $mostrarBuscadorArticuloAlcance = false;

    // Paso 4: Condiciones y restricciones
    public $formaVentaId = null;
    public $canalVentaId = null;
    public $formaPagoId = null;
    public $montoMinimo = null;
    public $cantidadMinima = null;
    public $cantidadMaxima = null;
    public $vigenciaDesde = null;
    public $vigenciaHasta = null;
    public $horaDesde = null;
    public $horaHasta = null;
    public $diasSemana = [];
    public $usosMaximos = null;
    public $combinable = false;
    public $activo = true;

    // Colecciones
    public $articulos = [];
    public $sucursales = [];
    public $categorias = [];
    public $formasVenta = [];
    public $canalesVenta = [];
    public $formasPago = [];

    protected $rules = [
        'tipo' => 'required',
        'nombre' => 'required|min:3',
        'valor' => 'nullable|numeric|min:0',
        'prioridad' => 'required|integer|min:1',
        'sucursalesSeleccionadas' => 'required|array|min:1',
    ];

    protected $messages = [
        'tipo.required' => 'Debes seleccionar un tipo de promoción',
        'nombre.required' => 'El nombre de la promoción es obligatorio',
        'nombre.min' => 'El nombre debe tener al menos 3 caracteres',
        'valor.numeric' => 'El valor debe ser un número',
        'valor.min' => 'El valor no puede ser negativo',
        'prioridad.required' => 'La prioridad es obligatoria',
        'prioridad.integer' => 'La prioridad debe ser un número entero',
        'prioridad.min' => 'La prioridad debe ser al menos 1',
        'sucursalesSeleccionadas.required' => 'Debes seleccionar al menos una sucursal',
        'sucursalesSeleccionadas.min' => 'Debes seleccionar al menos una sucursal',
    ];

    public function mount($id = null)
    {
        $this->sucursales = Sucursal::select('id', 'nombre')->orderBy('nombre')->get();
        $this->categorias = Categoria::activas()->orderBy('nombre')->get();
        $this->formasVenta = FormaVenta::activas()->get();
        $this->canalesVenta = CanalVenta::activos()->get();
        $this->formasPago = FormaPago::activas()->get();

        // Inicializar escalas vacías para tipo escalonado
        $this->escalas = [
            ['cantidad_desde' => null, 'cantidad_hasta' => null, 'tipo_descuento' => 'porcentaje', 'valor' => null],
        ];

        // Si hay ID, cargar promoción para edición
        if ($id) {
            $this->cargarPromocionParaEdicion($id);
        }
    }

    /**
     * Carga una promoción existente para edición
     */
    protected function cargarPromocionParaEdicion($id)
    {
        $promocion = Promocion::with(['condiciones', 'escalas'])->findOrFail($id);

        $this->modoEdicion = true;
        $this->promocionId = $id;
        $this->pasoActual = 2; // En edición, empezamos en el paso 2 (configuración)

        // Cargar datos básicos
        $this->tipo = $promocion->tipo;
        $this->nombre = $promocion->nombre;
        $this->descripcion = $promocion->descripcion;
        $this->codigoCupon = $promocion->codigo_cupon;
        $this->valor = $promocion->valor;
        $this->prioridad = $promocion->prioridad;
        $this->combinable = $promocion->combinable;
        $this->activo = $promocion->activo;

        // Cargar alcance (sucursal)
        $this->sucursalesSeleccionadas = [$promocion->sucursal_id];

        // Cargar vigencia y restricciones
        $this->vigenciaDesde = $promocion->vigencia_desde?->format('Y-m-d');
        $this->vigenciaHasta = $promocion->vigencia_hasta?->format('Y-m-d');
        $this->diasSemana = $promocion->dias_semana ?? [];
        $this->horaDesde = $promocion->hora_desde;
        $this->horaHasta = $promocion->hora_hasta;
        $this->usosMaximos = $promocion->usos_maximos;

        // Cargar condiciones
        foreach ($promocion->condiciones as $condicion) {
            switch ($condicion->tipo_condicion) {
                case 'por_articulo':
                    $this->articuloId = $condicion->articulo_id;
                    $this->alcanceArticulos = 'articulo';
                    if ($this->articuloId) {
                        $articulo = Articulo::find($this->articuloId);
                        $this->busquedaArticulo = $articulo?->nombre;
                    }
                    break;
                case 'por_categoria':
                    $this->categoriaId = $condicion->categoria_id;
                    $this->alcanceArticulos = 'categoria';
                    break;
                case 'por_forma_venta':
                    $this->formaVentaId = $condicion->forma_venta_id;
                    break;
                case 'por_canal':
                    $this->canalVentaId = $condicion->canal_venta_id;
                    break;
                case 'por_forma_pago':
                    $this->formaPagoId = $condicion->forma_pago_id;
                    break;
                case 'por_total_compra':
                    $this->montoMinimo = $condicion->monto_minimo;
                    break;
                case 'por_cantidad':
                    $this->cantidadMinima = $condicion->cantidad_minima;
                    break;
            }
        }

        // Cargar escalas si es tipo escalonado
        if ($this->tipo === 'descuento_escalonado' && $promocion->escalas->count() > 0) {
            $this->escalas = $promocion->escalas->map(function ($escala) {
                return [
                    'cantidad_desde' => $escala->cantidad_desde,
                    'cantidad_hasta' => $escala->cantidad_hasta,
                    'tipo_descuento' => $escala->tipo_descuento,
                    'valor' => $escala->valor,
                ];
            })->toArray();
        }
    }

    public function updatedBusquedaArticulo($value)
    {
        $query = Articulo::where('activo', true);

        if (strlen($value) >= 1) {
            $query->where(function($q) use ($value) {
                $q->where('nombre', 'like', '%' . $value . '%')
                  ->orWhere('codigo', 'like', '%' . $value . '%')
                  ->orWhere('codigo_barras', 'like', '%' . $value . '%');
            });
        }

        $this->articulos = $query->orderBy('nombre')->limit(15)->get();
    }

    public function seleccionarTipo($tipo)
    {
        $this->tipo = $tipo;
        $this->siguiente();
    }

    public function seleccionarArticulo($articuloId)
    {
        $this->articuloId = $articuloId;
        $this->busquedaArticulo = Articulo::find($articuloId)->nombre;
        $this->articulos = [];
        $this->mostrarBuscadorArticuloAlcance = false;
    }

    public function limpiarArticulo()
    {
        $this->articuloId = null;
        $this->busquedaArticulo = '';
        $this->articulos = [];
    }

    /**
     * Abre el buscador de artículos del paso 3 (alcance)
     */
    public function abrirBuscadorArticuloAlcance()
    {
        $this->mostrarBuscadorArticuloAlcance = true;
        $this->busquedaArticulo = '';
        // Cargar artículos iniciales
        $this->articulos = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get();
    }

    /**
     * Cierra el buscador de artículos del paso 3 (alcance)
     */
    public function cerrarBuscadorArticuloAlcance()
    {
        $this->mostrarBuscadorArticuloAlcance = false;
        $this->busquedaArticulo = '';
        $this->articulos = [];
    }

    /**
     * Selecciona el primer artículo de la lista (para Enter en paso 3)
     */
    public function seleccionarPrimerArticulo()
    {
        if (!empty($this->busquedaArticulo)) {
            // Primero intentar coincidencia exacta por código
            $articuloPorCodigo = Articulo::where('activo', true)
                ->where(function($q) {
                    $q->where('codigo', $this->busquedaArticulo)
                      ->orWhere('codigo_barras', $this->busquedaArticulo);
                })
                ->first();

            if ($articuloPorCodigo) {
                $this->seleccionarArticulo($articuloPorCodigo->id);
                return;
            }
        }

        // Si no hay coincidencia exacta, usar el primer resultado
        if (!empty($this->articulos) && count($this->articulos) > 0) {
            $this->seleccionarArticulo($this->articulos[0]->id);
        }
    }

    // ==================== Métodos del Paso 5 ====================

    public function cargarPromocionesCompetidoras()
    {
        // Usar la sucursal seleccionada en el simulador (una sola sucursal)
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

        // En modo edición, excluir la promoción que se está editando
        if ($this->modoEdicion && $this->promocionId) {
            $query->where('id', '!=', $this->promocionId);
        }

        // Filtrar por alcance de artículos
        if ($this->alcanceArticulos === 'articulo' && $this->articuloId) {
            $query->where(function ($q) {
                $q->whereHas('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_articulo')
                         ->where('articulo_id', $this->articuloId);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->whereIn('tipo_condicion', ['por_articulo', 'por_categoria']);
                });
            });
        } elseif ($this->alcanceArticulos === 'categoria' && $this->categoriaId) {
            $query->where(function ($q) {
                $q->whereHas('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_categoria')
                         ->where('categoria_id', $this->categoriaId);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->whereIn('tipo_condicion', ['por_articulo', 'por_categoria']);
                });
            });
        }

        // Filtrar por forma de venta del simulador
        $formaVentaFiltro = $this->simuladorFormaVentaId;
        if ($formaVentaFiltro) {
            $query->where(function ($q) use ($formaVentaFiltro) {
                $q->whereHas('condiciones', function ($subQ) use ($formaVentaFiltro) {
                    $subQ->where('tipo_condicion', 'por_forma_venta')
                         ->where('forma_venta_id', $formaVentaFiltro);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_forma_venta');
                });
            });
        }

        // Filtrar por canal de venta del simulador
        $canalVentaFiltro = $this->simuladorCanalVentaId;
        if ($canalVentaFiltro) {
            $query->where(function ($q) use ($canalVentaFiltro) {
                $q->whereHas('condiciones', function ($subQ) use ($canalVentaFiltro) {
                    $subQ->where('tipo_condicion', 'por_canal')
                         ->where('canal_venta_id', $canalVentaFiltro);
                })->orWhereDoesntHave('condiciones', function ($subQ) {
                    $subQ->where('tipo_condicion', 'por_canal');
                });
            });
        }

        // Filtrar por forma de pago del simulador
        $formaPagoFiltro = $this->simuladorFormaPagoId;
        if ($formaPagoFiltro) {
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

    public function updatedBusquedaArticuloSimulador($value)
    {
        $this->cargarArticulosSimulador($value);
    }

    /**
     * Muestra el buscador de artículos y carga todos si no hay búsqueda
     */
    public function abrirBuscadorArticulos()
    {
        $this->mostrarBuscadorArticulos = true;
        $this->cargarArticulosSimulador($this->busquedaArticuloSimulador);
    }

    /**
     * Cierra el buscador de artículos
     */
    public function cerrarBuscadorArticulos()
    {
        $this->mostrarBuscadorArticulos = false;
        $this->articulosSimuladorResultados = [];
    }

    /**
     * Carga artículos para el simulador según búsqueda o todos ordenados
     */
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
            $precioInfo = $this->obtenerPrecioConLista($art);
            return [
                'id' => $art->id,
                'nombre' => $art->nombre,
                'codigo' => $art->codigo,
                'codigo_barras' => $art->codigo_barras,
                'categoria_id' => $art->categoria_id,
                'categoria' => $art->categoriaModel ? ['nombre' => $art->categoriaModel->nombre] : null,
                'precio_base' => $precioInfo['precio_base'],
                'precio' => $precioInfo['precio'],
            ];
        })->toArray();
    }

    public function agregarArticuloSimulador($articuloId)
    {
        $articulo = Articulo::with('categoriaModel')->find($articuloId);
        if ($articulo) {
            $precioInfo = $this->obtenerPrecioConLista($articulo);
            $this->itemsSimulador[] = [
                'articulo_id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
                'categoria_id' => $articulo->categoria_id,
                'categoria_nombre' => $articulo->categoriaModel?->nombre,
                'precio_base' => $precioInfo['precio_base'],
                'precio' => $precioInfo['precio'],
                'tiene_ajuste' => $precioInfo['tiene_ajuste'],
                'cantidad' => 1,
            ];
            $this->busquedaArticuloSimulador = '';
            $this->articulosSimuladorResultados = [];
            $this->mostrarBuscadorArticulos = false;
            $this->simularVenta();
        }
    }

    /**
     * Agrega el primer artículo de la lista de resultados (para Enter)
     * Si hay coincidencia exacta por código de barras, agrega ese artículo
     */
    public function agregarPrimerArticulo()
    {
        // Si hay búsqueda, primero intentar coincidencia exacta por código de barras
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

        // Si no hay coincidencia exacta, usar el primer resultado de la lista
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

    public function updatedPrioridad()
    {
        $this->simularVenta();
    }

    public function updatedMostrarModalEdicion($value)
    {
        // Al cerrar el modal, recalcular la simulación
        if ($value === false) {
            $this->cargarPromocionesCompetidoras();
            $this->simularVenta();
        }
    }

    public function updatedSimuladorSucursalId()
    {
        // Resetear la lista de precios seleccionada antes de cargar las nuevas
        $this->simuladorListaPrecioId = null;

        // Cargar listas de precios de la nueva sucursal
        $this->cargarListasPreciosSimulador();

        // Seleccionar la lista base por defecto
        $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();

        // Recalcular precios de artículos ya agregados con la nueva lista
        $this->actualizarPreciosItemsSimulador();

        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    /**
     * Obtiene el ID de la lista base del simulador
     */
    protected function obtenerIdListaBaseSimulador(): ?int
    {
        if (empty($this->listasPreciosSimulador)) {
            return null;
        }

        // Buscar específicamente la lista base
        foreach ($this->listasPreciosSimulador as $lista) {
            if ($lista['es_lista_base']) {
                return (int) $lista['id'];
            }
        }

        // Si no hay lista base (no debería pasar), usar la primera disponible
        return isset($this->listasPreciosSimulador[0]['id'])
            ? (int) $this->listasPreciosSimulador[0]['id']
            : null;
    }

    public function updatedSimuladorListaPrecioId()
    {
        // Recalcular precios de artículos ya agregados con la nueva lista
        $this->actualizarPreciosItemsSimulador();
        $this->simularVenta();
    }

    /**
     * Carga las listas de precios disponibles para la sucursal del simulador
     */
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
                    'aplica_promociones' => (bool) $lista->aplica_promociones,
                    'promociones_alcance' => $lista->promociones_alcance,
                ];
            })
            ->toArray();
    }

    /**
     * Obtiene el precio de un artículo según la lista de precios seleccionada
     * El precio_base ahora es el precio de la lista base (no el precio_base del artículo)
     * Solo se muestra diferencia cuando la lista aplicada NO es la base
     */
    protected function obtenerPrecioConLista(Articulo $articulo): array
    {
        $precioBaseArticulo = (float) $articulo->precio_base;

        // Obtener precio de la lista base
        $listaBase = ListaPrecio::obtenerListaBase($this->simuladorSucursalId);
        $precioListaBase = $precioBaseArticulo;
        if ($listaBase) {
            $precioInfoBase = $listaBase->obtenerPrecioArticulo($articulo);
            $precioListaBase = $precioInfoBase['precio'];
        }

        // Si no hay lista seleccionada, usar lista base
        if (!$this->simuladorListaPrecioId) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false, // Lista base = no mostrar ajuste
                'usa_lista_base' => true,
            ];
        }

        $listaPrecio = ListaPrecio::find($this->simuladorListaPrecioId);
        if (!$listaPrecio) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
                'usa_lista_base' => true,
            ];
        }

        // Si la lista seleccionada ES la base, no mostrar ajuste
        if ($listaPrecio->es_lista_base) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
                'usa_lista_base' => true,
            ];
        }

        // Validar condiciones de la lista (forma de pago, canal, forma de venta)
        $contexto = [
            'forma_pago_id' => $this->simuladorFormaPagoId,
            'forma_venta_id' => $this->simuladorFormaVentaId,
            'canal_venta_id' => $this->simuladorCanalVentaId,
        ];

        // Si la lista no cumple condiciones, usar lista base (sin mostrar ajuste)
        if (!$listaPrecio->validarCondiciones($contexto)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false, // Fallback a base = no mostrar ajuste
                'usa_lista_base' => true,
            ];
        }

        // Lista diferente a la base y cumple condiciones: SIEMPRE mostrar como ajuste
        $precioInfo = $listaPrecio->obtenerPrecioArticulo($articulo);
        return [
            'precio' => $precioInfo['precio'],
            'precio_base' => $precioBaseArticulo,
            'tiene_ajuste' => true, // Siempre true cuando se usa lista diferente a base
            'usa_lista_base' => false,
        ];
    }

    /**
     * Actualiza los precios de todos los items del simulador según la lista de precios actual
     */
    protected function actualizarPreciosItemsSimulador(): void
    {
        foreach ($this->itemsSimulador as $index => $item) {
            $articulo = Articulo::find($item['articulo_id']);
            if ($articulo) {
                $precioInfo = $this->obtenerPrecioConLista($articulo);
                $this->itemsSimulador[$index]['precio'] = $precioInfo['precio'];
                $this->itemsSimulador[$index]['precio_base'] = $precioInfo['precio_base'];
                $this->itemsSimulador[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
            }
        }
    }

    public function updatedSimuladorFormaVentaId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorCanalVentaId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorFormaPagoId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItemsSimulador();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    /**
     * Obtiene la información de promociones de la lista seleccionada
     */
    protected function obtenerInfoPromocionesLista(): array
    {
        $listaSeleccionada = collect($this->listasPreciosSimulador)
            ->firstWhere('id', $this->simuladorListaPrecioId);

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

    /**
     * Verifica si un artículo está excluido de promociones según la lista de precios
     */
    protected function articuloExcluidoDePromociones(int $articuloId): bool
    {
        $infoPromos = $this->obtenerInfoPromocionesLista();

        // Si la lista no aplica promociones, todos los artículos están excluidos
        if (!$infoPromos['aplica_promociones']) {
            return true;
        }

        // Si el alcance es 'excluir_lista', verificar si el artículo tiene precio especial
        if ($infoPromos['promociones_alcance'] === 'excluir_lista' && $this->simuladorListaPrecioId) {
            $listaPrecio = ListaPrecio::find($this->simuladorListaPrecioId);
            if ($listaPrecio) {
                // Verificar si el artículo tiene un precio específico en esta lista
                return $listaPrecio->articulos()
                    ->where('articulo_id', $articuloId)
                    ->exists();
            }
        }

        return false;
    }

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
            'promociones_venta' => [], // Promociones que aplican al total de la venta
        ];

        // Calcular subtotal y cantidad total ANTES de procesar promociones
        $subtotalVenta = 0;
        $cantidadTotalVenta = 0;
        foreach ($this->itemsSimulador as $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 1);
            $subtotalVenta += $precio * $cantidad;
            $cantidadTotalVenta += $cantidad;
        }

        // Preparar todas las promociones disponibles
        $todasPromociones = $this->prepararPromocionesParaSimulacion();

        // Separar promociones por nivel de aplicación
        $promocionesArticulo = [];
        $promocionesVenta = [];
        foreach ($todasPromociones as $promo) {
            // descuento_monto aplica al total de la venta, no por artículo
            // precio_fijo aplica por artículo (cambia el precio unitario) solo si tiene categoría/artículo específico
            if ($promo['tipo'] === 'descuento_monto') {
                $promocionesVenta[] = $promo;
            } else {
                $promocionesArticulo[] = $promo;
            }
        }

        // Verificar si la promoción nueva (que estamos creando) es NO combinable
        $promocionNuevaNoCombinable = null;
        foreach ($todasPromociones as $promo) {
            if ($promo['es_nueva'] && !$promo['combinable']) {
                $promocionNuevaNoCombinable = $promo;
                break;
            }
        }

        // Si la promoción nueva es NO combinable, solo debe aplicar ella (sin otras)
        if ($promocionNuevaNoCombinable) {
            // Filtrar para que solo quede la promoción nueva
            $promocionesArticulo = array_filter($promocionesArticulo, fn($p) => $p['es_nueva']);
            $promocionesVenta = array_filter($promocionesVenta, fn($p) => $p['es_nueva']);
        } else {
            // Si la nueva ES combinable, filtrar las NO combinables existentes
            // (las NO combinables no deben combinarse con la nueva combinable)
            $promocionesArticulo = array_filter($promocionesArticulo, fn($p) => $p['combinable'] || $p['es_nueva']);
            $promocionesVenta = array_filter($promocionesVenta, fn($p) => $p['combinable'] || $p['es_nueva']);
        }

        // Contexto de la venta para evaluar condiciones (incluye filtros del simulador)
        $contextoVenta = [
            'subtotal' => $subtotalVenta,
            'cantidad_total' => $cantidadTotalVenta,
            'forma_pago_id' => $this->simuladorFormaPagoId,
            'forma_venta_id' => $this->simuladorFormaVentaId,
            'canal_venta_id' => $this->simuladorCanalVentaId,
        ];

        // Procesar cada artículo individualmente (solo promociones a nivel artículo)
        foreach ($this->itemsSimulador as $item) {
            $itemResultado = $this->procesarItemSimulacion($item, $promocionesArticulo, $contextoVenta);
            $resultado['items'][] = $itemResultado;
            $resultado['subtotal'] += $itemResultado['subtotal_original'];
            $resultado['total_descuentos'] += $itemResultado['total_descuento'];
            $resultado['total_recargos'] += $itemResultado['total_recargo'];
            $resultado['combinaciones_evaluadas'] += $itemResultado['combinaciones_evaluadas'];
        }

        // Calcular total parcial después de promociones por artículo
        $totalParcial = $resultado['subtotal'] - $resultado['total_descuentos'] + $resultado['total_recargos'];

        // Aplicar promociones a nivel de venta (descuento_monto)
        // Nota: El filtrado de combinabilidad ya se hizo arriba al separar las promociones
        $descuentoVenta = 0;
        foreach ($promocionesVenta as $promo) {
            // Verificar condiciones de la promoción
            if (!$this->promocionCumpleCondiciones($promo, $contextoVenta)) {
                continue;
            }

            // Para descuento_monto: se descuenta el monto fijo del total
            if ($promo['tipo'] === 'descuento_monto' && $promo['valor'] > 0) {
                $descuentoMonto = min($promo['valor'], $totalParcial - $descuentoVenta); // No descontar más del total restante
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

        // Construir resumen de promociones aplicadas (incluyendo las de venta)
        $resultado['promociones_resumen'] = $this->construirResumenPromociones($resultado['items'], $todasPromociones, $contextoVenta, $resultado['promociones_venta']);

        $resultado['total_final'] = $resultado['subtotal'] - $resultado['total_descuentos'] + $resultado['total_recargos'];
        $resultado['total_final'] = max(0, $resultado['total_final']);

        $this->resultadoSimulador = $resultado;
    }

    /**
     * Prepara todas las promociones para la simulación
     */
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

        // Agregar la promoción que se está creando
        $todasPromociones[] = [
            'id' => 'nueva',
            'nombre' => $this->nombre ?: '(Esta promoción)',
            'tipo' => $this->tipo,
            'valor' => $this->valor ?? 0,
            'prioridad' => (int) ($this->prioridad ?: 1),
            'combinable' => $this->combinable,
            'es_nueva' => true,
            'escalas' => $this->escalas,
            'articulo_id' => $this->alcanceArticulos === 'articulo' ? $this->articuloId : null,
            'categoria_id' => $this->alcanceArticulos === 'categoria' ? $this->categoriaId : null,
            'monto_minimo' => $this->montoMinimo,
            'cantidad_minima' => $this->cantidadMinima,
            'forma_pago_id' => $this->formaPagoId,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
        ];

        return $todasPromociones;
    }

    /**
     * Procesa un item individual y encuentra las mejores promociones para él
     */
    private function procesarItemSimulacion(array $item, array $todasPromociones, array $contextoVenta): array
    {
        $precio = (float) ($item['precio'] ?? 0);
        $cantidad = (int) ($item['cantidad'] ?? 1);
        $subtotalOriginal = $precio * $cantidad;
        $articuloId = $item['articulo_id'] ?? null;
        $categoriaId = $item['categoria_id'] ?? null;

        // Verificar si el artículo está excluido de promociones por la lista de precios
        $excluido = $articuloId ? $this->articuloExcluidoDePromociones($articuloId) : false;

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
            'excluido_promociones' => $excluido,
        ];

        // Si el artículo está excluido de promociones, retornar sin aplicar ninguna
        if ($excluido) {
            return $itemResultado;
        }

        // Filtrar promociones que aplican a este artículo específico Y cumplen condiciones de monto/cantidad
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

        // Separar descuentos y recargos
        $descuentos = array_filter($promocionesParaItem, fn($p) => $this->esDescuento($p['tipo']));
        $recargos = array_filter($promocionesParaItem, fn($p) => !$this->esDescuento($p['tipo']));

        // Encontrar mejor combinación de descuentos para este item
        $mejorDescuentos = $this->encontrarMejorCombinacionParaItem(
            array_values($descuentos),
            $subtotalOriginal,
            $cantidad,
            'descuento'
        );

        // Encontrar mejor combinación de recargos
        $mejorRecargos = $this->encontrarMejorCombinacionParaItem(
            array_values($recargos),
            $mejorDescuentos['monto_final'],
            $cantidad,
            'recargo'
        );

        $itemResultado['combinaciones_evaluadas'] = $mejorDescuentos['combinaciones_evaluadas'] + $mejorRecargos['combinaciones_evaluadas'];

        // Registrar promociones aplicadas
        foreach ($mejorDescuentos['promociones_aplicadas'] as $pa) {
            $itemResultado['promociones_aplicadas'][] = $pa;
            $itemResultado['total_descuento'] += $pa['valor_ajuste'];
        }

        foreach ($mejorRecargos['promociones_aplicadas'] as $pa) {
            $itemResultado['promociones_aplicadas'][] = $pa;
            $itemResultado['total_recargo'] += $pa['valor_ajuste'];
        }

        $itemResultado['subtotal_final'] = $subtotalOriginal - $itemResultado['total_descuento'] + $itemResultado['total_recargo'];
        $itemResultado['subtotal_final'] = max(0, $itemResultado['subtotal_final']);

        return $itemResultado;
    }

    /**
     * Verifica si una promoción aplica a un item específico
     */
    private function promocionAplicaAItem(array $promo, ?int $articuloId, ?int $categoriaId): bool
    {
        // Si la promoción es para un artículo específico
        if ($promo['articulo_id'] !== null) {
            return $promo['articulo_id'] == $articuloId;
        }

        // Si la promoción es para una categoría específica
        if ($promo['categoria_id'] !== null) {
            return $promo['categoria_id'] == $categoriaId;
        }

        // Si no tiene restricción de artículo ni categoría, aplica a todos
        return true;
    }

    /**
     * Verifica si una promoción cumple con todas las condiciones del contexto de venta
     */
    private function promocionCumpleCondiciones(array $promo, array $contextoVenta): bool
    {
        // Verificar monto mínimo
        if (!empty($promo['monto_minimo'])) {
            if ($contextoVenta['subtotal'] < (float) $promo['monto_minimo']) {
                return false;
            }
        }

        // Verificar cantidad mínima
        if (!empty($promo['cantidad_minima'])) {
            if ($contextoVenta['cantidad_total'] < (int) $promo['cantidad_minima']) {
                return false;
            }
        }

        // Verificar forma de pago: si la promoción requiere una forma de pago específica
        if (!empty($promo['forma_pago_id'])) {
            // Si el simulador tiene una forma de pago seleccionada, debe coincidir
            if (!empty($contextoVenta['forma_pago_id'])) {
                if ($promo['forma_pago_id'] != $contextoVenta['forma_pago_id']) {
                    return false;
                }
            }
            // Si el simulador no tiene forma de pago seleccionada, la promoción no aplica
            // porque requiere una específica
            else {
                return false;
            }
        }

        // Verificar forma de venta
        if (!empty($promo['forma_venta_id'])) {
            if (!empty($contextoVenta['forma_venta_id'])) {
                if ($promo['forma_venta_id'] != $contextoVenta['forma_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar canal de venta
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

    /**
     * Encuentra la mejor combinación de promociones para un item
     */
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
                } elseif ($resultadoCombinacion['monto_final'] == $mejorResultado['monto_final']) {
                    if ($resultadoCombinacion['prioridad_suma'] < $mejorResultado['prioridad_suma']) {
                        $esMejor = true;
                    }
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

    /**
     * Calcula el resultado de aplicar una combinación de promociones a un item
     */
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

    /**
     * Construye un resumen de todas las promociones y su estado
     */
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

            // Buscar en qué items se aplicó esta promoción
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

            // Buscar también en promociones a nivel de venta (precio_fijo)
            foreach ($promocionesVenta as $pv) {
                if ($pv['id'] === $promo['id']) {
                    $promoResumen['aplicada_en'][] = [
                        'articulo' => 'Total de la venta',
                        'valor' => $pv['valor_ajuste'],
                        'tipo' => $pv['tipo_ajuste'],
                        'precio_fijo' => $pv['precio_fijo'] ?? null,
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
                // Determinar razón por la que no se aplicó
                $promoResumen['razon'] = $this->determinarRazonNoAplicada($promo, $items, $contextoVenta);
            }

            $resumen[] = $promoResumen;
        }

        // Ordenar: primero las aplicadas, luego por prioridad
        usort($resumen, function($a, $b) {
            if ($a['aplicada'] !== $b['aplicada']) {
                return $b['aplicada'] <=> $a['aplicada'];
            }
            return $a['prioridad'] <=> $b['prioridad'];
        });

        return $resumen;
    }

    /**
     * Determina la razón por la que una promoción no se aplicó
     */
    private function determinarRazonNoAplicada(array $promo, array $items, array $contextoVenta): string
    {
        // Verificar forma de pago
        if (!empty($promo['forma_pago_id'])) {
            if (empty($contextoVenta['forma_pago_id'])) {
                return 'Requiere forma de pago específica';
            }
            if ($promo['forma_pago_id'] != $contextoVenta['forma_pago_id']) {
                return 'No aplica a esta forma de pago';
            }
        }

        // Verificar forma de venta
        if (!empty($promo['forma_venta_id'])) {
            if (empty($contextoVenta['forma_venta_id'])) {
                return 'Requiere forma de venta específica';
            }
            if ($promo['forma_venta_id'] != $contextoVenta['forma_venta_id']) {
                return 'No aplica a esta forma de venta';
            }
        }

        // Verificar canal de venta
        if (!empty($promo['canal_venta_id'])) {
            if (empty($contextoVenta['canal_venta_id'])) {
                return 'Requiere canal de venta específico';
            }
            if ($promo['canal_venta_id'] != $contextoVenta['canal_venta_id']) {
                return 'No aplica a este canal de venta';
            }
        }

        // Verificar monto mínimo
        if (!empty($promo['monto_minimo'])) {
            $montoMinimo = (float) $promo['monto_minimo'];
            if ($contextoVenta['subtotal'] < $montoMinimo) {
                $faltante = $montoMinimo - $contextoVenta['subtotal'];
                return "Monto mínimo no alcanzado (faltan $" . number_format($faltante, 0, ',', '.') . ")";
            }
        }

        // Verificar cantidad mínima
        if (!empty($promo['cantidad_minima'])) {
            $cantidadMinima = (int) $promo['cantidad_minima'];
            if ($contextoVenta['cantidad_total'] < $cantidadMinima) {
                $faltante = $cantidadMinima - $contextoVenta['cantidad_total'];
                return "Cantidad mínima no alcanzada (faltan {$faltante} unidades)";
            }
        }

        // Verificar si aplica a algún artículo
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

    /**
     * Determina si un tipo de promoción es descuento o recargo
     */
    private function esDescuento(string $tipo): bool
    {
        return in_array($tipo, ['descuento_porcentaje', 'descuento_monto', 'precio_fijo', 'descuento_escalonado']);
    }

    /**
     * Verifica si una combinación de promociones es válida según reglas de combinabilidad
     */
    private function esCombinacionValida(array $combinacion): bool
    {
        if (count($combinacion) <= 1) {
            return true;
        }

        // Contar promociones no combinables
        $noCombinable = 0;
        foreach ($combinacion as $promo) {
            if (!$promo['combinable']) {
                $noCombinable++;
            }
        }

        // Si hay más de una promoción y alguna no es combinable, es inválido
        // (una no combinable puede estar sola, pero no con otras)
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
                // precio_fijo: el artículo pasa a valer el precio fijo
                // El descuento es la diferencia entre el subtotal original y (precio_fijo * cantidad)
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

    public function agregarEscala()
    {
        $this->escalas[] = ['cantidad_desde' => null, 'cantidad_hasta' => null, 'tipo_descuento' => 'porcentaje', 'valor' => null];
    }

    public function eliminarEscala($index)
    {
        if (count($this->escalas) > 1) {
            unset($this->escalas[$index]);
            $this->escalas = array_values($this->escalas);
        }
    }

    public function siguiente()
    {
        if (!$this->validarPasoActual()) {
            return;
        }

        if ($this->pasoActual < $this->totalPasos) {
            $this->pasoActual++;

            // Al entrar al paso 3, si es precio_fijo forzar alcance a artículo
            if ($this->pasoActual == 3 && $this->tipo === 'precio_fijo') {
                $this->alcanceArticulos = 'articulo';
            }

            // Al entrar al paso 5, inicializar el simulador
            if ($this->pasoActual == 5) {
                $this->inicializarSimulador();
            }
        }
    }

    public function anterior()
    {
        if ($this->pasoActual > 1) {
            $this->pasoActual--;
        }
    }

    /**
     * Navegar a un paso específico
     * En modo edición: permite ir a cualquier paso (excepto el 1 que es selección de tipo)
     * En modo creación: solo permite ir hacia atrás
     */
    public function irAPaso(int $paso)
    {
        if ($this->modoEdicion) {
            // En modo edición, permitir ir a cualquier paso excepto el 1 (tipo ya está definido)
            if ($paso >= 2 && $paso <= $this->totalPasos) {
                // Si vamos al paso 5, inicializar el simulador
                if ($paso == 5) {
                    $this->inicializarSimulador();
                }
                $this->pasoActual = $paso;
            }
        } else {
            // En modo creación, solo permitir ir hacia atrás o quedarse en el paso actual
            if ($paso >= 1 && $paso <= $this->pasoActual) {
                $this->pasoActual = $paso;
            }
        }
    }

    /**
     * Inicializa los filtros del simulador (usado al entrar al paso 5)
     */
    protected function inicializarSimulador()
    {
        // Inicializar filtros del simulador con la primera sucursal seleccionada
        if (empty($this->simuladorSucursalId) && !empty($this->sucursalesSeleccionadas)) {
            $this->simuladorSucursalId = $this->sucursalesSeleccionadas[0];
        }
        // Inicializar con los valores de restricciones si están definidos
        if (empty($this->simuladorFormaVentaId) && $this->formaVentaId) {
            $this->simuladorFormaVentaId = $this->formaVentaId;
        }
        if (empty($this->simuladorCanalVentaId) && $this->canalVentaId) {
            $this->simuladorCanalVentaId = $this->canalVentaId;
        }
        if (empty($this->simuladorFormaPagoId) && $this->formaPagoId) {
            $this->simuladorFormaPagoId = $this->formaPagoId;
        }

        // Cargar listas de precios y seleccionar la base por defecto
        $this->cargarListasPreciosSimulador();
        if (empty($this->simuladorListaPrecioId)) {
            $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();
        }

        $this->cargarPromocionesCompetidoras();
        if (!empty($this->itemsSimulador)) {
            $this->actualizarPreciosItemsSimulador();
            $this->simularVenta();
        }
    }

    private function validarEscalasNoSeSolapan()
    {
        $escalasOrdenadas = collect($this->escalas)
            ->sortBy('cantidad_desde')
            ->values()
            ->all();

        for ($i = 0; $i < count($escalasOrdenadas); $i++) {
            $escalaActual = $escalasOrdenadas[$i];
            $desde = (float) $escalaActual['cantidad_desde'];
            $hasta = $escalaActual['cantidad_hasta'] ? (float) $escalaActual['cantidad_hasta'] : PHP_FLOAT_MAX;

            // Validar que cantidad_desde sea menor o igual a cantidad_hasta
            if ($escalaActual['cantidad_hasta'] && $desde > $hasta) {
                $this->js("window.notify('Escala {$desde}-{$hasta}: \"Desde\" debe ser menor o igual a \"Hasta\"', 'error', 5000)");
                return false;
            }

            // Comparar con las escalas siguientes
            for ($j = $i + 1; $j < count($escalasOrdenadas); $j++) {
                $escalaSiguiente = $escalasOrdenadas[$j];
                $desdeSiguiente = (float) $escalaSiguiente['cantidad_desde'];
                $hastaSiguiente = $escalaSiguiente['cantidad_hasta'] ? (float) $escalaSiguiente['cantidad_hasta'] : PHP_FLOAT_MAX;

                // Verificar solapamiento
                // Hay solapamiento si:
                // - El inicio de la siguiente está dentro del rango actual
                // - El fin de la siguiente está dentro del rango actual
                // - El rango siguiente contiene completamente al actual
                if (($desdeSiguiente >= $desde && $desdeSiguiente <= $hasta) ||
                    ($hastaSiguiente >= $desde && $hastaSiguiente <= $hasta) ||
                    ($desdeSiguiente <= $desde && $hastaSiguiente >= $hasta)) {

                    $rangoActual = $escalaActual['cantidad_hasta'] ? "{$desde}-{$hasta}" : "{$desde}+";
                    $rangoSiguiente = $escalaSiguiente['cantidad_hasta'] ? "{$desdeSiguiente}-{$hastaSiguiente}" : "{$desdeSiguiente}+";

                    $this->js("window.notify('Las escalas {$rangoActual} y {$rangoSiguiente} se solapan. Ajusta los rangos para que no se crucen.', 'error', 6000)");
                    return false;
                }
            }
        }

        return true;
    }

    private function validarPasoActual()
    {
        switch ($this->pasoActual) {
            case 1:
                if (!$this->tipo) {
                    $this->js("window.notify('Debes seleccionar un tipo de promoción', 'error')");
                    return false;
                }
                break;

            case 2:
                if (!$this->nombre) {
                    $this->js("window.notify('Debes ingresar un nombre para la promoción', 'error')");
                    return false;
                }

                if ($this->tipo === 'descuento_escalonado') {
                    // Validar que las escalas estén completas
                    foreach ($this->escalas as $escala) {
                        if (empty($escala['cantidad_desde']) || empty($escala['valor'])) {
                            $this->js("window.notify('Todas las escalas deben tener cantidad desde y valor', 'error')");
                            return false;
                        }
                    }

                    // Validar que las escalas no se solapen
                    if (!$this->validarEscalasNoSeSolapan()) {
                        return false;
                    }
                } elseif (!str_contains($this->tipo, 'escalonado')) {
                    if (empty($this->valor)) {
                        $this->js("window.notify('Debes ingresar un valor', 'error')");
                        return false;
                    }
                }

                break;

            case 3:
                // Validar que precio_fijo requiera artículo específico
                if ($this->tipo === 'precio_fijo' && $this->alcanceArticulos !== 'articulo') {
                    $this->js("window.notify('La promoción de Precio Fijo debe aplicar a un artículo específico', 'error', 5000)");
                    return false;
                }
                if ($this->tipo === 'precio_fijo' && !$this->articuloId) {
                    $this->js("window.notify('Debes seleccionar un artículo para la promoción de Precio Fijo', 'error', 5000)");
                    return false;
                }
                if (empty($this->sucursalesSeleccionadas)) {
                    $this->js("window.notify('Debes seleccionar al menos una sucursal', 'error')");
                    return false;
                }
                break;
        }

        return true;
    }

    public function guardar()
    {
        // Si no es combinable, fijar prioridad en 1 automáticamente
        if (!$this->combinable) {
            $this->prioridad = 1;
        }

        \Log::info('Iniciando guardar promoción', [
            'modo' => $this->modoEdicion ? 'edicion' : 'creacion',
            'promocion_id' => $this->promocionId,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'valor' => $this->valor,
            'prioridad' => $this->prioridad,
            'combinable' => $this->combinable,
            'sucursales' => $this->sucursalesSeleccionadas,
        ]);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación', ['errors' => $e->errors()]);
            $errores = collect($e->errors())->flatten()->implode(', ');
            $this->js("window.notify('Error de validación: {$errores}', 'error', 7000)");
            throw $e;
        }

        try {
            if ($this->modoEdicion) {
                $this->actualizarPromocion();
            } else {
                $this->crearPromociones();
            }

            return redirect()->route('configuracion.promociones');

        } catch (\Exception $e) {
            \Log::error('Error al guardar promocion: ' . $e->getMessage());
            $errorMsg = addslashes($e->getMessage());
            $this->js("window.notify('Error al guardar promocion: {$errorMsg}', 'error', 7000)");
        }
    }

    /**
     * Crea nuevas promociones (una por cada sucursal seleccionada)
     */
    protected function crearPromociones()
    {
        $promocionesCreadas = 0;

        foreach ($this->sucursalesSeleccionadas as $sucursalId) {
            $promocion = Promocion::create([
                'sucursal_id' => $sucursalId,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
                'codigo_cupon' => $this->codigoCupon ?: null,
                'tipo' => $this->tipo,
                'valor' => $this->valor ?? 0,
                'prioridad' => $this->prioridad,
                'combinable' => $this->combinable,
                'activo' => $this->activo,
                'vigencia_desde' => $this->vigenciaDesde,
                'vigencia_hasta' => $this->vigenciaHasta,
                'dias_semana' => !empty($this->diasSemana) ? $this->diasSemana : null,
                'hora_desde' => $this->horaDesde,
                'hora_hasta' => $this->horaHasta,
                'usos_maximos' => $this->usosMaximos,
                'usos_actuales' => 0,
            ]);

            $this->guardarEscalas($promocion);
            $this->guardarCondiciones($promocion);

            $promocionesCreadas++;
        }

        $mensaje = $promocionesCreadas === 1
            ? 'Promocion creada correctamente'
            : "{$promocionesCreadas} promociones creadas correctamente";

        $this->js("window.notify('{$mensaje}', 'success')");
    }

    /**
     * Actualiza una promoción existente
     */
    protected function actualizarPromocion()
    {
        $promocion = Promocion::findOrFail($this->promocionId);

        // Actualizar datos principales
        $promocion->update([
            'sucursal_id' => $this->sucursalesSeleccionadas[0], // En edición solo hay una sucursal
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'codigo_cupon' => $this->codigoCupon ?: null,
            'valor' => $this->valor ?? 0,
            'prioridad' => $this->prioridad,
            'combinable' => $this->combinable,
            'activo' => $this->activo,
            'vigencia_desde' => $this->vigenciaDesde,
            'vigencia_hasta' => $this->vigenciaHasta,
            'dias_semana' => !empty($this->diasSemana) ? $this->diasSemana : null,
            'hora_desde' => $this->horaDesde,
            'hora_hasta' => $this->horaHasta,
            'usos_maximos' => $this->usosMaximos,
        ]);

        // Eliminar escalas y condiciones existentes
        $promocion->escalas()->delete();
        $promocion->condiciones()->delete();

        // Crear nuevas escalas y condiciones
        $this->guardarEscalas($promocion);
        $this->guardarCondiciones($promocion);

        $this->js("window.notify('Promocion actualizada correctamente', 'success')");
    }

    /**
     * Guarda las escalas de una promoción
     */
    protected function guardarEscalas(Promocion $promocion)
    {
        if ($this->tipo === 'descuento_escalonado') {
            foreach ($this->escalas as $escalaData) {
                if (!empty($escalaData['cantidad_desde']) && !empty($escalaData['valor'])) {
                    PromocionEscala::create([
                        'promocion_id' => $promocion->id,
                        'cantidad_desde' => $escalaData['cantidad_desde'],
                        'cantidad_hasta' => $escalaData['cantidad_hasta'] ?: null,
                        'tipo_descuento' => $escalaData['tipo_descuento'] ?? 'porcentaje',
                        'valor' => $escalaData['valor'],
                    ]);
                }
            }
        }
    }

    /**
     * Guarda las condiciones de una promoción
     */
    protected function guardarCondiciones(Promocion $promocion)
    {
        if ($this->articuloId) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_articulo',
                'articulo_id' => $this->articuloId,
            ]);
        }

        if ($this->categoriaId) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_categoria',
                'categoria_id' => $this->categoriaId,
            ]);
        }

        if ($this->formaVentaId) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_forma_venta',
                'forma_venta_id' => $this->formaVentaId,
            ]);
        }

        if ($this->canalVentaId) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_canal',
                'canal_venta_id' => $this->canalVentaId,
            ]);
        }

        if ($this->formaPagoId) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_forma_pago',
                'forma_pago_id' => $this->formaPagoId,
            ]);
        }

        if ($this->montoMinimo) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_total_compra',
                'monto_minimo' => $this->montoMinimo,
            ]);
        }

        if ($this->cantidadMinima) {
            PromocionCondicion::create([
                'promocion_id' => $promocion->id,
                'tipo_condicion' => 'por_cantidad',
                'cantidad_minima' => $this->cantidadMinima,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.configuracion.promociones.wizard-promocion');
    }
}
