<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;

/**
 * Manejo del carrito de items en NuevaVenta.
 *
 * Encapsula:
 * - Estado del carrito ($items) y propiedades de cantidad/modos.
 * - Dispatcher seleccionarArticulo segun modo activo (consulta/busqueda/agregar).
 * - Agregado de articulos al carrito (manual, primer resultado, scanner por codigo).
 * - Verificacion de stock al agregar (advierte o bloquea segun config sucursal).
 * - Eliminacion y actualizacion de cantidad de items.
 * - Activacion/desactivacion de modos (consulta de precios, busqueda en detalle).
 * - Resaltado y busqueda de items dentro del carrito.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->sucursalId                  (SucursalAware)
 * - $this->busquedaArticulo            (WithBusquedaArticulos)
 * - $this->articulosResultados         (WithBusquedaArticulos)
 * - $this->obtenerPrecioConLista()     (NuevaVenta — ira a WithCalculoVenta)
 * - $this->calcularVenta()             (NuevaVenta — ira a WithCalculoVenta)
 * - $this->consultarPrecios()          (NuevaVenta — ira a WithConsultaPrecios)
 * - $this->opcionalService             (NuevaVenta)
 * - $this->wizard*, $this->mostrarWizardOpcionales (NuevaVenta — iran a WithOpcionales)
 * - $this->descuentoGeneral*           (NuevaVenta — iran a WithDescuentos)
 * - $this->pesable*, $this->mostrarModalPesable (NuevaVenta)
 * - $this->itemResaltado               (NuevaVenta)
 */
trait WithCarritoItems
{
    // =========================================
    // PROPIEDADES DEL CARRITO
    // =========================================

    /** @var array Items en el carrito de venta */
    public $items = [];

    /** @var int Cantidad a agregar al seleccionar un artículo */
    public $cantidadAgregar = 1;

    /** @var bool Modo consulta de precios activo */
    public $modoConsulta = false;

    /** @var bool Modo búsqueda en detalle activo */
    public $modoBusqueda = false;

    // =========================================
    // DISPATCHER SEGUN MODO
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

    // =========================================
    // VERIFICACION DE STOCK
    // =========================================

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

    // =========================================
    // AGREGAR AL CARRITO
    // =========================================

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
                'ajuste_manual_origen' => null,
                'precio_sin_ajuste_manual' => null,
                // Opcionales (vacío para items sin opcionales)
                'opcionales' => [],
                'precio_opcionales' => 0,
                // Canje por puntos (RF-25)
                'puntos_canje' => $articulo->puntos_canje,
                'pagado_con_puntos' => false,
            ];

            // RF-34: Herencia de descuento general % a items nuevos.
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
                if ($nuevoPrecio < 0) {
                    $nuevoPrecio = 0;
                }
                $this->items[$lastIndex]['precio_sin_ajuste_manual'] = $precioInfo['precio'];
                $this->items[$lastIndex]['precio'] = $nuevoPrecio;
                $this->items[$lastIndex]['ajuste_manual_tipo'] = 'porcentaje';
                $this->items[$lastIndex]['ajuste_manual_valor'] = $this->descuentoGeneralValor;
                $this->items[$lastIndex]['ajuste_manual_origen'] = 'descuento_general';
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
            // Notificar al cajero: el scanner capturó un código que no existe en el
            // sistema. Antes retornaba en silencio y el cajero podía pensar que
            // había fallado el scanner y volver a escanear.
            $this->dispatch(
                'toast-warning',
                message: __('Código :codigo no encontrado', ['codigo' => $codigo])
            );

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

    // =========================================
    // ELIMINAR / ACTUALIZAR ITEMS
    // =========================================

    public function eliminarItem($index)
    {
        // Si el popover de ajuste manual estaba abierto sobre este item (o sobre uno
        // posterior cuyo índice se va a desplazar), cerrarlo para no quedar apuntando
        // a un item incorrecto tras el array_values.
        if ($this->ajusteManualPopoverIndex !== null && $this->ajusteManualPopoverIndex >= $index) {
            $this->cerrarAjusteManual();
        }
        // Si el wizard de opcionales estaba editando este item, cerrarlo: si dejamos
        // wizardEditandoIndex apuntando a un item que ya no existe, al confirmar se
        // crearía un item nuevo en lugar de actualizar.
        if (($this->wizardEditandoIndex ?? null) === $index) {
            $this->cerrarWizardOpcionales();
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calcularVenta();
    }

    public function actualizarCantidad($index, $cantidad)
    {
        $cantidad = max(0.001, (float) $cantidad);
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['cantidad'] = $cantidad;

        // Re-verificar stock con la cantidad nueva: la verificación inicial solo
        // corre al agregar; si el cajero sube cantidad de 1 a 100 sin esto, el
        // problema de stock recién aparece al confirmar la venta.
        $articuloId = $this->items[$index]['articulo_id'] ?? null;
        if ($articuloId) {
            $articulo = Articulo::find($articuloId);
            if ($articulo) {
                $opcionales = $this->items[$index]['opcionales'] ?? [];
                $this->verificarStockAlAgregar($articulo, $cantidad, $opcionales);
            }
        }

        $this->calcularVenta();
    }

    // =========================================
    // MODOS DE CONSULTA Y BUSQUEDA
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
}
