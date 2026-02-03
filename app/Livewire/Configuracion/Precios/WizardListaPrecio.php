<?php

namespace App\Livewire\Configuracion\Precios;

use Livewire\Component;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioCondicion;
use App\Models\ListaPrecioArticulo;
use App\Models\Sucursal;
use App\Models\FormaPago;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\Articulo;
use App\Models\Categoria;

/**
 * Wizard para crear/editar Listas de Precios
 *
 * PASOS:
 * 1. Datos básicos (nombre, código, descripción, sucursal)
 * 2. Configuración de precios (ajuste, redondeo, promociones)
 * 3. Vigencia (fechas, días, horarios, cantidades)
 * 4. Condiciones (forma pago, forma venta, canal, montos)
 * 5. Artículos específicos (opcional)
 */
class WizardListaPrecio extends Component
{
    // Control del wizard
    public int $pasoActual = 1;
    public int $totalPasos = 5;
    public bool $modoEdicion = false;
    public bool $esListaBase = false;
    public $listaId = null;

    // Paso 1: Datos básicos
    public $sucursalId = null;
    public string $nombre = '';
    public string $codigo = '';
    public string $descripcion = '';
    public int $prioridad = 100;

    // Paso 2: Configuración de precios
    public $ajustePorcentaje = 0;
    public $tipoAjuste = 'recargo'; // 'recargo' o 'descuento'
    public $porcentajeAbsoluto = 0; // Valor absoluto del porcentaje (sin tipo para flexibilidad con input)
    public $redondeo = 'ninguno';
    public $aplicaPromociones = true;
    public $promocionesAlcance = 'todos';

    // Paso 3: Vigencia
    public $vigenciaDesde = null;
    public $vigenciaHasta = null;
    public $diasSemana = [];
    public $horaDesde = null;
    public $horaHasta = null;
    public $cantidadMinima = null;
    public $cantidadMaxima = null;

    // Paso 4: Condiciones
    public $condiciones = [];
    public $nuevaCondicionTipo = '';
    public $nuevaCondicionFormaPagoId = null;
    public $nuevaCondicionFormaVentaId = null;
    public $nuevaCondicionCanalVentaId = null;
    public $nuevaCondicionMontoMinimo = null;
    public $nuevaCondicionMontoMaximo = null;

    // Paso 5: Artículos específicos
    public $articulosEspecificos = [];
    public $busquedaArticulo = '';
    public $articulosEncontrados = [];
    public $categoriasSeleccionadas = [];

    // Opciones
    public $opcionesRedondeo = [];
    public $opcionesPromocionesAlcance = [];
    public $opcionesDiasSemana = [];
    public $opcionesTipoCondicion = [];

    public function boot()
    {
        $this->opcionesRedondeo = [
            'ninguno' => __('Sin redondeo'),
            'entero' => __('Entero más cercano'),
            'decena' => __('Decena más cercana'),
            'centena' => __('Centena más cercana'),
        ];

        $this->opcionesPromocionesAlcance = [
            'todos' => __('A toda la venta'),
            'excluir_lista' => __('Excluir artículos con precio en esta lista'),
        ];

        $this->opcionesDiasSemana = [
            0 => __('Domingo'),
            1 => __('Lunes'),
            2 => __('Martes'),
            3 => __('Miércoles'),
            4 => __('Jueves'),
            5 => __('Viernes'),
            6 => __('Sábado'),
        ];

        $this->opcionesTipoCondicion = [
            'por_forma_pago' => __('Por forma de pago'),
            'por_forma_venta' => __('Por forma de venta'),
            'por_canal' => __('Por canal de venta'),
            'por_total_compra' => __('Por total de compra'),
        ];
    }

    protected $rules = [
        'sucursalId' => 'required',
        'nombre' => 'required|min:3|max:100',
        'codigo' => 'nullable|max:50',
        'descripcion' => 'nullable|max:500',
        'prioridad' => 'required|integer|min:1|max:999',
        'ajustePorcentaje' => 'required|numeric|min:-100|max:1000',
        'redondeo' => 'required|in:ninguno,entero,decena,centena',
    ];

    protected function messages()
    {
        return [
            'sucursalId.required' => __('Debes seleccionar una sucursal'),
            'nombre.required' => __('El nombre es obligatorio'),
            'nombre.min' => __('El nombre debe tener al menos 3 caracteres'),
            'prioridad.required' => __('La prioridad es obligatoria'),
            'prioridad.min' => __('La prioridad debe ser al menos 1'),
            'ajustePorcentaje.required' => __('El ajuste porcentual es obligatorio'),
            'ajustePorcentaje.min' => __('El descuento máximo es 100%'),
        ];
    }

    public function mount($id = null)
    {
        // Si hay ID, cargar lista para edición
        if ($id) {
            $this->cargarListaParaEdicion($id);
        } else {
            // Valor por defecto: primera sucursal
            $primeraSucursal = Sucursal::select('id')->orderBy('nombre')->first();
            if ($primeraSucursal) {
                $this->sucursalId = $primeraSucursal->id;
            }
        }
    }

    /**
     * Computed properties para cargar colecciones sin almacenarlas en el estado
     */
    public function getSucursalesProperty()
    {
        return Sucursal::select('id', 'nombre')->orderBy('nombre')->get();
    }

    public function getFormasPagoProperty()
    {
        return FormaPago::where('activo', true)->orderBy('nombre')->get();
    }

    public function getFormasVentaProperty()
    {
        return FormaVenta::activas()->get();
    }

    public function getCanalesVentaProperty()
    {
        return CanalVenta::activos()->get();
    }

    public function getCategoriasProperty()
    {
        return Categoria::orderBy('nombre')->get();
    }

    protected function cargarListaParaEdicion($id)
    {
        $lista = ListaPrecio::with(['condiciones', 'articulos.articulo', 'articulos.categoria'])->find($id);

        if (!$lista) {
            session()->flash('error', __('Lista de precios no encontrada'));
            return redirect()->route('configuracion.precios');
        }

        $this->modoEdicion = true;
        $this->listaId = $lista->id;
        $this->esListaBase = (bool) $lista->es_lista_base;

        // Si es lista base, solo mostrar pasos 1 y 2
        if ($this->esListaBase) {
            $this->totalPasos = 2;
        }

        // Paso 1
        $this->sucursalId = $lista->sucursal_id;
        $this->nombre = $lista->nombre;
        $this->codigo = $lista->codigo;
        $this->descripcion = $lista->descripcion;
        $this->prioridad = $lista->prioridad;

        // Paso 2
        $this->ajustePorcentaje = (float) $lista->ajuste_porcentaje;
        $this->sincronizarDesdeAjustePorcentaje(); // Sincroniza tipoAjuste y porcentajeAbsoluto
        $this->redondeo = $lista->redondeo;
        $this->aplicaPromociones = $lista->aplica_promociones;
        $this->promocionesAlcance = $lista->promociones_alcance;

        // Paso 3
        $this->vigenciaDesde = $lista->vigencia_desde?->format('Y-m-d');
        $this->vigenciaHasta = $lista->vigencia_hasta?->format('Y-m-d');
        $this->diasSemana = $lista->dias_semana ?? [];
        $this->horaDesde = $lista->hora_desde;
        $this->horaHasta = $lista->hora_hasta;
        $this->cantidadMinima = $lista->cantidad_minima;
        $this->cantidadMaxima = $lista->cantidad_maxima;

        // Paso 4: Condiciones
        $this->condiciones = $lista->condiciones->map(function ($c) {
            return [
                'id' => $c->id,
                'tipo' => $c->tipo_condicion,
                'forma_pago_id' => $c->forma_pago_id,
                'forma_venta_id' => $c->forma_venta_id,
                'canal_venta_id' => $c->canal_venta_id,
                'monto_minimo' => $c->monto_minimo,
                'monto_maximo' => $c->monto_maximo,
                'descripcion' => $c->obtenerDescripcion(),
            ];
        })->toArray();

        // Paso 5: Artículos específicos
        $this->articulosEspecificos = $lista->articulos->map(function ($a) {
            return [
                'id' => $a->id,
                'articulo_id' => $a->articulo_id,
                'categoria_id' => $a->categoria_id,
                'nombre' => $a->obtenerNombre(),
                'tipo' => $a->obtenerTipo(),
                'precio_fijo' => $a->precio_fijo,
                'ajuste_porcentaje' => $a->ajuste_porcentaje,
                'precio_base_original' => $a->precio_base_original,
            ];
        })->toArray();
    }

    public function updatedBusquedaArticulo($value)
    {
        if (strlen($value) >= 2) {
            $this->articulosEncontrados = Articulo::where(function($q) use ($value) {
                $q->where('nombre', 'like', '%' . $value . '%')
                  ->orWhere('codigo', 'like', '%' . $value . '%');
            })
            ->limit(15)
            ->get()
            ->toArray();
        } else {
            $this->articulosEncontrados = [];
        }
    }

    /**
     * Cuando cambia el tipo de ajuste, recalcula el porcentaje real
     */
    public function updatedTipoAjuste()
    {
        $this->calcularAjustePorcentaje();
    }

    /**
     * Cuando cambia el porcentaje absoluto, recalcula el porcentaje real
     */
    public function updatedPorcentajeAbsoluto($value)
    {
        // Si el valor está vacío o no es numérico, establecer en 0
        if ($value === '' || $value === null || !is_numeric($value)) {
            $this->porcentajeAbsoluto = 0;
        } else {
            $this->porcentajeAbsoluto = abs((float) $value);
        }
        $this->calcularAjustePorcentaje();
    }

    /**
     * Calcula el ajuste porcentaje real basado en tipo y valor absoluto
     */
    protected function calcularAjustePorcentaje()
    {
        $valor = abs((float) $this->porcentajeAbsoluto);
        $this->ajustePorcentaje = $this->tipoAjuste === 'descuento' ? -$valor : $valor;
    }

    /**
     * Sincroniza el tipo y porcentaje absoluto desde el ajuste porcentaje
     * (usado al cargar para edición)
     */
    protected function sincronizarDesdeAjustePorcentaje()
    {
        $ajuste = (float) $this->ajustePorcentaje;
        $this->tipoAjuste = $ajuste < 0 ? 'descuento' : 'recargo';
        $this->porcentajeAbsoluto = abs($ajuste);
    }

    public function siguiente()
    {
        if (!$this->validarPasoActual()) {
            return;
        }

        if ($this->pasoActual < $this->totalPasos) {
            $this->pasoActual++;
        }
    }

    public function anterior()
    {
        if ($this->pasoActual > 1) {
            $this->pasoActual--;
        }
    }

    public function irAPaso($paso)
    {
        // En modo edición, permitir ir a cualquier paso
        // En modo creación, solo permitir ir a pasos anteriores o al actual
        if ($paso >= 1 && $paso <= $this->totalPasos) {
            if ($this->modoEdicion || $paso <= $this->pasoActual) {
                $this->pasoActual = $paso;
            }
        }
    }

    protected function validarPasoActual()
    {
        switch ($this->pasoActual) {
            case 1:
                $this->validate([
                    'sucursalId' => 'required',
                    'nombre' => 'required|min:3|max:100',
                    'prioridad' => 'required|integer|min:1|max:999',
                ]);
                break;

            case 2:
                $this->validate([
                    'ajustePorcentaje' => 'required|numeric|min:-100|max:1000',
                    'redondeo' => 'required|in:ninguno,entero,decena,centena',
                ]);
                break;
        }

        return true;
    }

    // ==================== Métodos para Condiciones (Paso 4) ====================

    public function agregarCondicion()
    {
        if (!$this->nuevaCondicionTipo) {
            $this->js("window.notify('" . __('Selecciona un tipo de condición') . "', 'error')");
            return;
        }

        $condicion = [
            'id' => null,
            'tipo' => $this->nuevaCondicionTipo,
            'forma_pago_id' => null,
            'forma_venta_id' => null,
            'canal_venta_id' => null,
            'monto_minimo' => null,
            'monto_maximo' => null,
            'descripcion' => '',
        ];

        switch ($this->nuevaCondicionTipo) {
            case 'por_forma_pago':
                if (!$this->nuevaCondicionFormaPagoId) {
                    $this->js("window.notify('" . __('Selecciona una forma de pago') . "', 'error')");
                    return;
                }
                $condicion['forma_pago_id'] = $this->nuevaCondicionFormaPagoId;
                $formaPago = $this->formasPago->firstWhere('id', $this->nuevaCondicionFormaPagoId);
                $condicion['descripcion'] = __('Forma de pago') . ': ' . ($formaPago->nombre ?? 'N/A');
                break;

            case 'por_forma_venta':
                if (!$this->nuevaCondicionFormaVentaId) {
                    $this->js("window.notify('" . __('Selecciona una forma de venta') . "', 'error')");
                    return;
                }
                $condicion['forma_venta_id'] = $this->nuevaCondicionFormaVentaId;
                $formaVenta = $this->formasVenta->firstWhere('id', $this->nuevaCondicionFormaVentaId);
                $condicion['descripcion'] = __('Forma de venta') . ': ' . ($formaVenta->nombre ?? 'N/A');
                break;

            case 'por_canal':
                if (!$this->nuevaCondicionCanalVentaId) {
                    $this->js("window.notify('" . __('Selecciona un canal de venta') . "', 'error')");
                    return;
                }
                $condicion['canal_venta_id'] = $this->nuevaCondicionCanalVentaId;
                $canal = $this->canalesVenta->firstWhere('id', $this->nuevaCondicionCanalVentaId);
                $condicion['descripcion'] = __('Canal') . ': ' . ($canal->nombre ?? 'N/A');
                break;

            case 'por_total_compra':
                if (!$this->nuevaCondicionMontoMinimo && !$this->nuevaCondicionMontoMaximo) {
                    $this->js("window.notify('" . __('Ingresa al menos un monto (mínimo o máximo)') . "', 'error')");
                    return;
                }
                $condicion['monto_minimo'] = $this->nuevaCondicionMontoMinimo;
                $condicion['monto_maximo'] = $this->nuevaCondicionMontoMaximo;

                if ($this->nuevaCondicionMontoMinimo && $this->nuevaCondicionMontoMaximo) {
                    $condicion['descripcion'] = __('Total entre') . " \${$this->nuevaCondicionMontoMinimo} " . __('y') . " \${$this->nuevaCondicionMontoMaximo}";
                } elseif ($this->nuevaCondicionMontoMinimo) {
                    $condicion['descripcion'] = __('Total mínimo') . " \${$this->nuevaCondicionMontoMinimo}";
                } else {
                    $condicion['descripcion'] = __('Total máximo') . " \${$this->nuevaCondicionMontoMaximo}";
                }
                break;
        }

        $this->condiciones[] = $condicion;
        $this->limpiarNuevaCondicion();
        $this->js("window.notify('" . __('Condición agregada') . "', 'success')");
    }

    public function eliminarCondicion($index)
    {
        unset($this->condiciones[$index]);
        $this->condiciones = array_values($this->condiciones);
    }

    protected function limpiarNuevaCondicion()
    {
        $this->nuevaCondicionTipo = '';
        $this->nuevaCondicionFormaPagoId = null;
        $this->nuevaCondicionFormaVentaId = null;
        $this->nuevaCondicionCanalVentaId = null;
        $this->nuevaCondicionMontoMinimo = null;
        $this->nuevaCondicionMontoMaximo = null;
    }

    // ==================== Métodos para Artículos (Paso 5) ====================

    public function agregarArticulo($articuloId)
    {
        // Verificar que no esté ya agregado
        foreach ($this->articulosEspecificos as $art) {
            if ($art['articulo_id'] == $articuloId) {
                $this->js("window.notify('" . __('Este artículo ya está agregado') . "', 'warning')");
                return;
            }
        }

        $articulo = Articulo::find($articuloId);
        if (!$articulo) {
            return;
        }

        $this->articulosEspecificos[] = [
            'id' => null,
            'articulo_id' => $articulo->id,
            'categoria_id' => null,
            'nombre' => $articulo->nombre,
            'tipo' => 'articulo',
            'precio_fijo' => null,
            'ajuste_porcentaje' => $this->ajustePorcentaje, // Usa el ajuste de la lista por defecto
            'precio_base_original' => $articulo->precio_base,
        ];

        $this->busquedaArticulo = '';
        $this->articulosEncontrados = [];
        $this->js("window.notify('" . __('Artículo agregado') . "', 'success')");
    }

    public function agregarCategoria($categoriaId)
    {
        // Verificar que no esté ya agregada
        foreach ($this->articulosEspecificos as $art) {
            if ($art['categoria_id'] == $categoriaId) {
                $this->js("window.notify('" . __('Esta categoría ya está agregada') . "', 'warning')");
                return;
            }
        }

        $categoria = Categoria::find($categoriaId);
        if (!$categoria) {
            return;
        }

        $this->articulosEspecificos[] = [
            'id' => null,
            'articulo_id' => null,
            'categoria_id' => $categoria->id,
            'nombre' => $categoria->nombre,
            'tipo' => 'categoria',
            'precio_fijo' => null,
            'ajuste_porcentaje' => $this->ajustePorcentaje, // Usa el ajuste de la lista por defecto
            'precio_base_original' => null,
        ];

        $this->js("window.notify('" . __('Categoría agregada') . "', 'success')");
    }

    public function eliminarArticuloEspecifico($index)
    {
        unset($this->articulosEspecificos[$index]);
        $this->articulosEspecificos = array_values($this->articulosEspecificos);
    }

    /**
     * Recalcula el porcentaje cuando se ingresa un monto fijo
     */
    public function recalcularPorcentajeDesdeMontoFijo($index)
    {
        if (!isset($this->articulosEspecificos[$index])) {
            return;
        }

        $art = &$this->articulosEspecificos[$index];
        $precioFijo = $art['precio_fijo'];
        $precioBase = $art['precio_base_original'];

        if ($precioFijo !== null && $precioFijo !== '' && $precioBase > 0) {
            // Calcular el porcentaje: ((nuevo - base) / base) * 100
            $porcentaje = (((float)$precioFijo - $precioBase) / $precioBase) * 100;
            $this->articulosEspecificos[$index]['ajuste_porcentaje'] = round($porcentaje, 2);
        }
    }

    // ==================== Guardar ====================

    public function guardar()
    {
        $this->validate([
            'sucursalId' => 'required',
            'nombre' => 'required|min:3|max:100',
            'prioridad' => 'required|integer|min:1|max:999',
            'ajustePorcentaje' => 'required|numeric|min:-100|max:1000',
        ]);

        try {
            \DB::beginTransaction();

            // Crear o actualizar lista
            $lista = $this->modoEdicion
                ? ListaPrecio::find($this->listaId)
                : new ListaPrecio();

            // Si es lista base, solo guardar campos permitidos
            if ($this->esListaBase) {
                $lista->fill([
                    'sucursal_id' => $this->sucursalId,
                    'nombre' => $this->nombre,
                    'codigo' => $this->codigo ?: null,
                    'descripcion' => $this->descripcion ?: null,
                    'ajuste_porcentaje' => $this->ajustePorcentaje,
                    'redondeo' => $this->redondeo,
                    'prioridad' => $this->prioridad,
                    // Mantener es_lista_base = true
                    'es_lista_base' => true,
                    // Las listas base siempre aplican promociones y no tienen restricciones
                    'aplica_promociones' => true,
                    'promociones_alcance' => 'todos',
                    'vigencia_desde' => null,
                    'vigencia_hasta' => null,
                    'dias_semana' => null,
                    'hora_desde' => null,
                    'hora_hasta' => null,
                    'cantidad_minima' => null,
                    'cantidad_maxima' => null,
                ]);
            } else {
                $lista->fill([
                    'sucursal_id' => $this->sucursalId,
                    'nombre' => $this->nombre,
                    'codigo' => $this->codigo ?: null,
                    'descripcion' => $this->descripcion ?: null,
                    'ajuste_porcentaje' => $this->ajustePorcentaje,
                    'redondeo' => $this->redondeo,
                    'aplica_promociones' => $this->aplicaPromociones,
                    'promociones_alcance' => $this->promocionesAlcance,
                    'vigencia_desde' => $this->vigenciaDesde ?: null,
                    'vigencia_hasta' => $this->vigenciaHasta ?: null,
                    'dias_semana' => !empty($this->diasSemana) ? $this->diasSemana : null,
                    'hora_desde' => $this->horaDesde ?: null,
                    'hora_hasta' => $this->horaHasta ?: null,
                    'cantidad_minima' => $this->cantidadMinima ?: null,
                    'cantidad_maxima' => $this->cantidadMaxima ?: null,
                    'es_lista_base' => false,
                    'prioridad' => $this->prioridad,
                    'activo' => true,
                ]);
            }

            $lista->save();

            // Solo guardar condiciones y artículos si NO es lista base
            if (!$this->esListaBase) {
                // Guardar condiciones
                $this->guardarCondiciones($lista);

                // Guardar artículos específicos
                $this->guardarArticulosEspecificos($lista);
            }

            \DB::commit();

            $mensaje = $this->modoEdicion
                ? __('Lista de precios actualizada correctamente')
                : __('Lista de precios creada correctamente');

            session()->flash('notify', [
                'message' => $mensaje,
                'type' => 'success'
            ]);

            return $this->redirect(route('configuracion.precios'), navigate: true);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error al guardar lista de precios: ' . $e->getMessage());
            $this->js("window.notify('" . addslashes(__('Error al guardar: ') . $e->getMessage()) . "', 'error', 7000)");
        }
    }

    protected function guardarCondiciones(ListaPrecio $lista)
    {
        // Obtener IDs existentes
        $idsExistentes = collect($this->condiciones)
            ->pluck('id')
            ->filter()
            ->toArray();

        // Eliminar condiciones que ya no están
        $lista->condiciones()
            ->whereNotIn('id', $idsExistentes)
            ->delete();

        // Crear o actualizar condiciones
        foreach ($this->condiciones as $cond) {
            if ($cond['id']) {
                // Actualizar existente
                ListaPrecioCondicion::where('id', $cond['id'])->update([
                    'tipo_condicion' => $cond['tipo'],
                    'forma_pago_id' => $cond['forma_pago_id'],
                    'forma_venta_id' => $cond['forma_venta_id'],
                    'canal_venta_id' => $cond['canal_venta_id'],
                    'monto_minimo' => $cond['monto_minimo'],
                    'monto_maximo' => $cond['monto_maximo'],
                ]);
            } else {
                // Crear nueva
                ListaPrecioCondicion::create([
                    'lista_precio_id' => $lista->id,
                    'tipo_condicion' => $cond['tipo'],
                    'forma_pago_id' => $cond['forma_pago_id'],
                    'forma_venta_id' => $cond['forma_venta_id'],
                    'canal_venta_id' => $cond['canal_venta_id'],
                    'monto_minimo' => $cond['monto_minimo'],
                    'monto_maximo' => $cond['monto_maximo'],
                ]);
            }
        }
    }

    protected function guardarArticulosEspecificos(ListaPrecio $lista)
    {
        // Obtener IDs existentes
        $idsExistentes = collect($this->articulosEspecificos)
            ->pluck('id')
            ->filter()
            ->toArray();

        // Eliminar artículos que ya no están
        $lista->articulos()
            ->whereNotIn('id', $idsExistentes)
            ->delete();

        // Crear o actualizar artículos
        foreach ($this->articulosEspecificos as $art) {
            $data = [
                'lista_precio_id' => $lista->id,
                'articulo_id' => $art['articulo_id'],
                'categoria_id' => $art['categoria_id'],
                'precio_fijo' => $art['precio_fijo'],
                'ajuste_porcentaje' => $art['ajuste_porcentaje'],
                'precio_base_original' => $art['precio_base_original'],
            ];

            if ($art['id']) {
                ListaPrecioArticulo::where('id', $art['id'])->update($data);
            } else {
                ListaPrecioArticulo::create($data);
            }
        }
    }

    public function render()
    {
        return view('livewire.configuracion.precios.wizard-lista-precio');
    }
}
