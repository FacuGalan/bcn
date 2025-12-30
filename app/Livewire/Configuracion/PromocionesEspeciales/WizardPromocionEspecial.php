<?php

namespace App\Livewire\Configuracion\PromocionesEspeciales;

use Livewire\Component;
use App\Models\PromocionEspecial;
use App\Models\PromocionEspecialGrupo;
use App\Models\PromocionEspecialEscala;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Sucursal;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\FormaPago;
use App\Models\ListaPrecio;

class WizardPromocionEspecial extends Component
{
    // Control del wizard
    public $pasoActual = 1;
    public $totalPasos = 4;
    public $modoEdicion = false;
    public $promocionId = null;

    // Paso 1: Tipo (4 opciones)
    public $tipo = null; // 'nxm', 'nxm_avanzado', 'combo', 'menu'

    // Paso 2: Configuración básica
    public $nombre = '';
    public $descripcion = '';
    public $sucursalesSeleccionadas = [];
    public $prioridad = 1;
    public $activo = true;

    // ===== Para NxM básico =====
    public $nxmLleva = 3;
    public $nxmBonifica = 1;
    public $beneficioTipo = 'gratis'; // 'gratis' o 'descuento'
    public $beneficioPorcentaje = 50;
    public $nxmAplicaA = 'articulo'; // 'articulo' o 'categoria'
    public $nxmArticuloId = null;
    public $nxmCategoriaId = null;
    public $busquedaArticuloNxM = '';
    public $articulosNxMResultados = [];
    public $mostrarBuscadorNxM = false;
    public $usarEscalas = false;
    public $escalas = [];

    // ===== Para NxM avanzado (triggers y rewards separados) =====
    public $gruposTrigger = []; // [{nombre, articulos: [{id, nombre}]}]
    public $gruposReward = [];  // [{nombre, articulos: [{id, nombre}]}]
    public $busquedaArticuloTrigger = '';
    public $articulosTriggerResultados = [];
    public $mostrarBuscadorTrigger = false;
    public $mostrarCategoriasTrigger = false;
    public $grupoTriggerActivo = 0;
    public $busquedaArticuloReward = '';
    public $articulosRewardResultados = [];
    public $mostrarBuscadorReward = false;
    public $mostrarCategoriasReward = false;
    public $grupoRewardActivo = 0;

    // ===== Para Combo básico =====
    public $precioTipo = 'fijo'; // 'fijo' o 'porcentaje'
    public $precioValor = null;
    public $comboItems = []; // [{articulo_id, nombre, cantidad, precio_unitario}]
    public $busquedaArticuloCombo = '';
    public $articulosComboResultados = [];
    public $mostrarBuscadorCombo = false;

    // ===== Para Menú (grupos con opciones) =====
    public $gruposMenu = []; // [{nombre, cantidad, articulos: [{id, nombre, precio}]}]
    public $busquedaArticuloMenu = '';
    public $articulosMenuResultados = [];
    public $mostrarBuscadorMenu = false;
    public $grupoMenuActivo = 0;

    // Paso 3: Condiciones
    public $vigenciaDesde = null;
    public $vigenciaHasta = null;
    public $diasSemana = [];
    public $horaDesde = null;
    public $horaHasta = null;
    public $formaVentaId = null;
    public $canalVentaId = null;
    public $formaPagoId = null;
    public $usosMaximos = null;

    // ===== Paso 5: Simulador =====
    public $simuladorSucursalId = null;
    public $simuladorFormaVentaId = null;
    public $simuladorCanalVentaId = null;
    public $simuladorFormaPagoId = null;
    public $simuladorListaPrecioId = null;
    public $itemsSimulador = [];
    public $resultadoSimulador = null;
    public $busquedaArticuloSimulador = '';
    public $articulosSimuladorResultados = [];
    public $mostrarFiltrosSimulador = false;
    public $mostrarBuscadorArticulosSimulador = false;
    public $listasPreciosSimulador = [];
    public $promocionesEspecialesCompetidoras = [];
    public $prioridadesTemporales = []; // [id_promocion => prioridad_temporal]

    // Colecciones
    public $sucursales = [];
    public $categorias = [];
    public $formasVenta = [];
    public $canalesVenta = [];
    public $formasPago = [];

    public function mount($id = null)
    {
        $this->sucursales = Sucursal::orderBy('nombre')->get();
        $this->categorias = Categoria::activas()->orderBy('nombre')->get();
        $this->formasVenta = FormaVenta::activas()->get();
        $this->canalesVenta = CanalVenta::activos()->get();
        $this->formasPago = FormaPago::activas()->get();

        if ($id) {
            $this->cargarPromocionParaEdicion($id);
        }
    }

    protected function cargarPromocionParaEdicion($id)
    {
        $promo = PromocionEspecial::with(['grupos.articulos', 'escalas'])->findOrFail($id);

        $this->modoEdicion = true;
        $this->promocionId = $id;
        $this->pasoActual = 2;

        // Datos básicos
        $this->tipo = $promo->tipo;
        $this->nombre = $promo->nombre;
        $this->descripcion = $promo->descripcion;
        $this->sucursalesSeleccionadas = [$promo->sucursal_id];
        $this->prioridad = $promo->prioridad;
        $this->activo = $promo->activo;

        // NxM básico
        if ($promo->tipo === PromocionEspecial::TIPO_NXM) {
            $this->nxmLleva = $promo->nxm_lleva;
            $this->nxmBonifica = $promo->nxm_bonifica;
            $this->beneficioTipo = $promo->beneficio_tipo ?? 'gratis';
            $this->beneficioPorcentaje = $promo->beneficio_porcentaje ?? 50;
            $this->usarEscalas = $promo->usa_escalas;

            if ($promo->nxm_articulo_id) {
                $this->nxmAplicaA = 'articulo';
                $this->nxmArticuloId = $promo->nxm_articulo_id;
                $articulo = Articulo::find($promo->nxm_articulo_id);
                $this->busquedaArticuloNxM = $articulo?->nombre;
            } elseif ($promo->nxm_categoria_id) {
                $this->nxmAplicaA = 'categoria';
                $this->nxmCategoriaId = $promo->nxm_categoria_id;
            }

            if ($promo->usa_escalas) {
                $this->escalas = $promo->escalas->map(fn($e) => [
                    'cantidad_desde' => $e->cantidad_desde,
                    'cantidad_hasta' => $e->cantidad_hasta,
                    'lleva' => $e->lleva,
                    'bonifica' => $e->bonifica,
                    'beneficio_tipo' => $e->beneficio_tipo ?? 'gratis',
                    'beneficio_porcentaje' => $e->beneficio_porcentaje ?? 50,
                ])->toArray();
            }
        }

        // NxM avanzado
        if ($promo->tipo === PromocionEspecial::TIPO_NXM_AVANZADO) {
            $this->usarEscalas = $promo->usa_escalas;
            $this->beneficioTipo = $promo->beneficio_tipo ?? 'gratis';
            $this->beneficioPorcentaje = $promo->beneficio_porcentaje ?? 50;

            if ($promo->usa_escalas) {
                $this->escalas = $promo->escalas->map(fn($e) => [
                    'cantidad_desde' => $e->cantidad_desde,
                    'cantidad_hasta' => $e->cantidad_hasta,
                    'lleva' => $e->lleva,
                    'bonifica' => $e->bonifica,
                    'beneficio_tipo' => $e->beneficio_tipo ?? 'gratis',
                    'beneficio_porcentaje' => $e->beneficio_porcentaje ?? 50,
                ])->toArray();
            } else {
                $this->nxmLleva = $promo->nxm_lleva;
                $this->nxmBonifica = $promo->nxm_bonifica;
            }

            // Cargar grupos trigger
            $this->gruposTrigger = $promo->grupos()
                ->where('es_trigger', true)
                ->with('articulos')
                ->get()
                ->map(fn($g) => [
                    'nombre' => $g->nombre,
                    'articulos' => $g->articulos->map(fn($a) => [
                        'id' => $a->id,
                        'nombre' => $a->nombre,
                    ])->toArray(),
                ])->toArray();

            // Cargar grupos reward
            $this->gruposReward = $promo->grupos()
                ->where('es_reward', true)
                ->with('articulos')
                ->get()
                ->map(fn($g) => [
                    'nombre' => $g->nombre,
                    'articulos' => $g->articulos->map(fn($a) => [
                        'id' => $a->id,
                        'nombre' => $a->nombre,
                    ])->toArray(),
                ])->toArray();

            if (empty($this->gruposTrigger)) {
                $this->gruposTrigger = [['nombre' => 'Artículos que activan', 'articulos' => []]];
            }
            if (empty($this->gruposReward)) {
                $this->gruposReward = [['nombre' => 'Artículos bonificables', 'articulos' => []]];
            }
        }

        // Combo básico
        if ($promo->tipo === PromocionEspecial::TIPO_COMBO) {
            $this->precioTipo = $promo->precio_tipo;
            $this->precioValor = $promo->precio_valor;

            $this->comboItems = $promo->grupos()
                ->with('articulos')
                ->get()
                ->flatMap(function($g) {
                    return $g->articulos->map(fn($a) => [
                        'articulo_id' => $a->id,
                        'nombre' => $a->nombre,
                        'cantidad' => $g->cantidad,
                        'precio_unitario' => $a->precio_base ?? 0,
                    ]);
                })->toArray();
        }

        // Menú
        if ($promo->tipo === PromocionEspecial::TIPO_MENU) {
            $this->precioTipo = $promo->precio_tipo;
            $this->precioValor = $promo->precio_valor;

            $this->gruposMenu = $promo->grupos()
                ->with('articulos')
                ->orderBy('orden')
                ->get()
                ->map(fn($g) => [
                    'nombre' => $g->nombre,
                    'cantidad' => $g->cantidad,
                    'articulos' => $g->articulos->map(fn($a) => [
                        'id' => $a->id,
                        'nombre' => $a->nombre,
                        'precio' => $a->precio_base ?? 0,
                    ])->toArray(),
                ])->toArray();

            if (empty($this->gruposMenu)) {
                $this->gruposMenu = [['nombre' => 'Plato principal', 'cantidad' => 1, 'articulos' => []]];
            }
        }

        // Condiciones
        $this->vigenciaDesde = $promo->vigencia_desde?->format('Y-m-d');
        $this->vigenciaHasta = $promo->vigencia_hasta?->format('Y-m-d');
        $this->diasSemana = $promo->dias_semana ?? [];
        $this->horaDesde = $promo->hora_desde;
        $this->horaHasta = $promo->hora_hasta;
        $this->formaVentaId = $promo->forma_venta_id;
        $this->canalVentaId = $promo->canal_venta_id;
        $this->formaPagoId = $promo->forma_pago_id;
        $this->usosMaximos = $promo->usos_maximos;
    }

    // ==================== Navegación ====================

    public function seleccionarTipo($tipo)
    {
        $this->tipo = $tipo;

        // Inicializar estructuras según el tipo
        if ($tipo === 'nxm_avanzado') {
            if (empty($this->gruposTrigger)) {
                $this->gruposTrigger = [['nombre' => 'Artículos que activan', 'articulos' => []]];
            }
            if (empty($this->gruposReward)) {
                $this->gruposReward = [['nombre' => 'Artículos bonificables', 'articulos' => []]];
            }
        }

        if ($tipo === 'menu') {
            if (empty($this->gruposMenu)) {
                $this->gruposMenu = [
                    ['nombre' => 'Plato principal', 'cantidad' => 1, 'articulos' => []],
                    ['nombre' => 'Bebida', 'cantidad' => 1, 'articulos' => []],
                    ['nombre' => 'Postre', 'cantidad' => 1, 'articulos' => []],
                ];
            }
        }

        $this->siguiente();
    }

    public function siguiente()
    {
        if (!$this->validarPasoActual()) {
            return;
        }

        if ($this->pasoActual < $this->totalPasos) {
            $this->pasoActual++;

            // Inicializar simulador cuando entramos al paso 4
            if ($this->pasoActual === 4) {
                $this->inicializarSimulador();
            }
        }
    }

    public function anterior()
    {
        $minPaso = $this->modoEdicion ? 2 : 1;
        if ($this->pasoActual > $minPaso) {
            $this->pasoActual--;
        }
    }

    public function irAPaso($paso)
    {
        $minPaso = $this->modoEdicion ? 2 : 1;

        // En modo edición, permitir ir a cualquier paso
        if ($this->modoEdicion) {
            if ($paso >= $minPaso && $paso <= $this->totalPasos) {
                $this->pasoActual = $paso;

                // Si vamos al simulador (paso 4), inicializar el simulador
                if ($paso == 4) {
                    $this->inicializarSimulador();
                }
            }
        } else {
            // En modo creación, solo permitir ir a pasos anteriores o al actual
            if ($paso >= $minPaso && $paso <= $this->pasoActual) {
                $this->pasoActual = $paso;
            }
        }
    }

    protected function validarPasoActual(): bool
    {
        if ($this->pasoActual == 1) {
            if (!$this->tipo) {
                $this->js("window.notify('Selecciona un tipo de promoción', 'error')");
                return false;
            }
        }

        if ($this->pasoActual == 2) {
            if (empty($this->nombre)) {
                $this->js("window.notify('El nombre es obligatorio', 'error')");
                return false;
            }
            if (empty($this->sucursalesSeleccionadas)) {
                $this->js("window.notify('Selecciona al menos una sucursal', 'error')");
                return false;
            }

            // Validaciones específicas por tipo
            return match($this->tipo) {
                'nxm' => $this->validarNxMBasico(),
                'nxm_avanzado' => $this->validarNxMAvanzado(),
                'combo' => $this->validarCombo(),
                'menu' => $this->validarMenu(),
                default => true,
            };
        }

        return true;
    }

    protected function validarNxMBasico(): bool
    {
        if ($this->usarEscalas) {
            if (empty($this->escalas)) {
                $this->js("window.notify('Agrega al menos una escala', 'error')");
                return false;
            }
            foreach ($this->escalas as $index => $escala) {
                if (empty($escala['cantidad_desde']) || empty($escala['lleva']) || empty($escala['bonifica'])) {
                    $this->js("window.notify('Completa todos los campos de la escala " . ($index + 1) . "', 'error')");
                    return false;
                }
                if ($escala['bonifica'] >= $escala['lleva']) {
                    $this->js("window.notify('En la escala " . ($index + 1) . ", bonifica debe ser menor que lleva', 'error')");
                    return false;
                }
                if (($escala['beneficio_tipo'] ?? 'gratis') === 'descuento' && empty($escala['beneficio_porcentaje'])) {
                    $this->js("window.notify('En la escala " . ($index + 1) . ", define el porcentaje de descuento', 'error')");
                    return false;
                }
            }
        } else {
            if ($this->nxmLleva < 2 || $this->nxmBonifica < 1) {
                $this->js("window.notify('Configura correctamente el NxM', 'error')");
                return false;
            }
            if ($this->nxmBonifica >= $this->nxmLleva) {
                $this->js("window.notify('La cantidad a bonificar debe ser menor que la cantidad a llevar', 'error')");
                return false;
            }
            if ($this->beneficioTipo === 'descuento' && (!$this->beneficioPorcentaje || $this->beneficioPorcentaje <= 0 || $this->beneficioPorcentaje > 100)) {
                $this->js("window.notify('El porcentaje de descuento debe estar entre 1 y 100', 'error')");
                return false;
            }
        }

        if ($this->nxmAplicaA === 'articulo' && !$this->nxmArticuloId) {
            $this->js("window.notify('Selecciona un artículo', 'error')");
            return false;
        }
        if ($this->nxmAplicaA === 'categoria' && !$this->nxmCategoriaId) {
            $this->js("window.notify('Selecciona una categoría', 'error')");
            return false;
        }

        return true;
    }

    protected function validarNxMAvanzado(): bool
    {
        // Validar escalas o lleva/bonifica
        if ($this->usarEscalas) {
            if (empty($this->escalas)) {
                $this->js("window.notify('Agrega al menos una escala', 'error')");
                return false;
            }
            foreach ($this->escalas as $index => $escala) {
                if (empty($escala['cantidad_desde']) || empty($escala['lleva']) || empty($escala['bonifica'])) {
                    $this->js("window.notify('Completa todos los campos de la escala " . ($index + 1) . "', 'error')");
                    return false;
                }
                if (($escala['beneficio_tipo'] ?? 'gratis') === 'descuento' && empty($escala['beneficio_porcentaje'])) {
                    $this->js("window.notify('En la escala " . ($index + 1) . ", define el porcentaje de descuento', 'error')");
                    return false;
                }
            }
        } else {
            if ($this->nxmLleva < 2 || $this->nxmBonifica < 1 || $this->nxmBonifica >= $this->nxmLleva) {
                $this->js("window.notify('Configura correctamente el NxM (lleva > bonifica)', 'error')");
                return false;
            }
            if ($this->beneficioTipo === 'descuento' && (!$this->beneficioPorcentaje || $this->beneficioPorcentaje <= 0 || $this->beneficioPorcentaje > 100)) {
                $this->js("window.notify('El porcentaje de descuento debe estar entre 1 y 100', 'error')");
                return false;
            }
        }

        // Validar que hay artículos trigger
        $totalTriggers = collect($this->gruposTrigger)->sum(fn($g) => count($g['articulos'] ?? []));
        if ($totalTriggers < 1) {
            $this->js("window.notify('Agrega al menos un artículo que active la promoción', 'error')");
            return false;
        }

        // Validar que hay artículos reward
        $totalRewards = collect($this->gruposReward)->sum(fn($g) => count($g['articulos'] ?? []));
        if ($totalRewards < 1) {
            $this->js("window.notify('Agrega al menos un artículo bonificable', 'error')");
            return false;
        }

        return true;
    }

    protected function validarCombo(): bool
    {
        if (count($this->comboItems) < 1) {
            $this->js("window.notify('Agrega al menos un artículo al combo', 'error')");
            return false;
        }

        $totalUnidades = array_sum(array_column($this->comboItems, 'cantidad'));
        if ($totalUnidades < 2) {
            $this->js("window.notify('El combo debe tener al menos 2 unidades en total', 'error')");
            return false;
        }

        if (!$this->precioValor || $this->precioValor <= 0) {
            $this->js("window.notify('Define el precio/descuento del combo', 'error')");
            return false;
        }

        if ($this->precioTipo === 'porcentaje' && $this->precioValor > 100) {
            $this->js("window.notify('El porcentaje no puede ser mayor a 100%', 'error')");
            return false;
        }

        return true;
    }

    protected function validarMenu(): bool
    {
        if (empty($this->gruposMenu)) {
            $this->js("window.notify('Agrega al menos un grupo al menú', 'error')");
            return false;
        }

        foreach ($this->gruposMenu as $index => $grupo) {
            if (empty($grupo['articulos'])) {
                $this->js("window.notify('El grupo \"" . ($grupo['nombre'] ?: 'Grupo ' . ($index + 1)) . "\" necesita al menos un artículo', 'error')");
                return false;
            }
        }

        if (!$this->precioValor || $this->precioValor <= 0) {
            $this->js("window.notify('Define el precio/descuento del menú', 'error')");
            return false;
        }

        if ($this->precioTipo === 'porcentaje' && $this->precioValor > 100) {
            $this->js("window.notify('El porcentaje no puede ser mayor a 100%', 'error')");
            return false;
        }

        return true;
    }

    // ==================== Búsqueda de artículos NxM básico ====================

    public function abrirBuscadorNxM()
    {
        $this->mostrarBuscadorNxM = true;
        $this->busquedaArticuloNxM = '';
        $this->articulosNxMResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function cerrarBuscadorNxM()
    {
        $this->mostrarBuscadorNxM = false;
    }

    /**
     * Cuando cambia el tipo de beneficio, si es descuento, forzar bonifica a 1
     */
    public function updatedBeneficioTipo($value)
    {
        if ($value === 'descuento') {
            $this->nxmBonifica = 1;
        }
    }

    public function updatedBusquedaArticuloNxM($value)
    {
        $this->articulosNxMResultados = $this->buscarArticulos($value);
    }

    public function seleccionarArticuloNxM($articuloId)
    {
        $articulo = Articulo::find($articuloId);
        if ($articulo) {
            $this->nxmArticuloId = $articuloId;
            $this->busquedaArticuloNxM = $articulo->nombre;
            $this->mostrarBuscadorNxM = false;
        }
    }

    public function limpiarArticuloNxM()
    {
        $this->nxmArticuloId = null;
        $this->busquedaArticuloNxM = '';
    }

    // ==================== NxM Avanzado: Triggers ====================

    public function agregarGrupoTrigger()
    {
        $this->gruposTrigger[] = ['nombre' => '', 'articulos' => []];
        $this->grupoTriggerActivo = count($this->gruposTrigger) - 1;
    }

    public function eliminarGrupoTrigger($index)
    {
        unset($this->gruposTrigger[$index]);
        $this->gruposTrigger = array_values($this->gruposTrigger);
        if ($this->grupoTriggerActivo >= count($this->gruposTrigger)) {
            $this->grupoTriggerActivo = max(0, count($this->gruposTrigger) - 1);
        }
    }

    public function abrirBuscadorTrigger($grupoIndex)
    {
        $this->grupoTriggerActivo = $grupoIndex;
        $this->mostrarBuscadorTrigger = true;
        $this->busquedaArticuloTrigger = '';
        $this->articulosTriggerResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function cerrarBuscadorTrigger()
    {
        $this->mostrarBuscadorTrigger = false;
    }

    public function updatedBusquedaArticuloTrigger($value)
    {
        $this->articulosTriggerResultados = $this->buscarArticulos($value);
    }

    public function agregarArticuloTrigger($articuloId)
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        // Verificar que no esté ya en el grupo
        foreach ($this->gruposTrigger[$this->grupoTriggerActivo]['articulos'] ?? [] as $art) {
            if ($art['id'] == $articuloId) {
                $this->js("window.notify('Este artículo ya está en el grupo', 'warning')");
                return;
            }
        }

        $this->gruposTrigger[$this->grupoTriggerActivo]['articulos'][] = [
            'id' => $articuloId,
            'nombre' => $articulo->nombre,
        ];

        // Limpiar búsqueda y recargar lista completa
        $this->busquedaArticuloTrigger = '';
        $this->articulosTriggerResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();

        // Mantener foco en el input
        $this->js("setTimeout(() => { document.querySelector('[wire\\\\:model\\\\.live\\\\.debounce\\\\.200ms=\"busquedaArticuloTrigger\"]')?.focus(); }, 50);");
    }

    public function seleccionarPrimerArticuloTrigger()
    {
        if (!empty($this->articulosTriggerResultados)) {
            $this->agregarArticuloTrigger($this->articulosTriggerResultados[0]['id']);
        }
    }

    public function eliminarArticuloTrigger($grupoIndex, $articuloIndex)
    {
        unset($this->gruposTrigger[$grupoIndex]['articulos'][$articuloIndex]);
        $this->gruposTrigger[$grupoIndex]['articulos'] = array_values($this->gruposTrigger[$grupoIndex]['articulos']);
    }

    public function abrirCategoriasTrigger($grupoIndex)
    {
        $this->grupoTriggerActivo = $grupoIndex;
        $this->mostrarCategoriasTrigger = true;
        $this->mostrarBuscadorTrigger = false;
    }

    public function cerrarCategoriasTrigger()
    {
        $this->mostrarCategoriasTrigger = false;
    }

    public function agregarArticulosPorCategoriaTrigger($categoriaId)
    {
        $articulos = Articulo::where('activo', true)
            ->where('categoria_id', $categoriaId)
            ->get();

        $agregados = 0;
        foreach ($articulos as $articulo) {
            // Verificar que no esté ya en el grupo
            $existe = false;
            foreach ($this->gruposTrigger[$this->grupoTriggerActivo]['articulos'] ?? [] as $art) {
                if ($art['id'] == $articulo->id) {
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $this->gruposTrigger[$this->grupoTriggerActivo]['articulos'][] = [
                    'id' => $articulo->id,
                    'nombre' => $articulo->nombre,
                ];
                $agregados++;
            }
        }

        $this->mostrarCategoriasTrigger = false;

        if ($agregados > 0) {
            $this->js("window.notify('{$agregados} artículo(s) agregado(s)', 'success')");
        } else {
            $this->js("window.notify('No se agregaron artículos (ya estaban todos)', 'info')");
        }
    }

    // ==================== NxM Avanzado: Rewards ====================

    public function agregarGrupoReward()
    {
        $this->gruposReward[] = ['nombre' => '', 'articulos' => []];
        $this->grupoRewardActivo = count($this->gruposReward) - 1;
    }

    public function eliminarGrupoReward($index)
    {
        unset($this->gruposReward[$index]);
        $this->gruposReward = array_values($this->gruposReward);
        if ($this->grupoRewardActivo >= count($this->gruposReward)) {
            $this->grupoRewardActivo = max(0, count($this->gruposReward) - 1);
        }
    }

    public function abrirBuscadorReward($grupoIndex)
    {
        $this->grupoRewardActivo = $grupoIndex;
        $this->mostrarBuscadorReward = true;
        $this->busquedaArticuloReward = '';
        $this->articulosRewardResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function cerrarBuscadorReward()
    {
        $this->mostrarBuscadorReward = false;
    }

    public function updatedBusquedaArticuloReward($value)
    {
        $this->articulosRewardResultados = $this->buscarArticulos($value);
    }

    public function agregarArticuloReward($articuloId)
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        foreach ($this->gruposReward[$this->grupoRewardActivo]['articulos'] ?? [] as $art) {
            if ($art['id'] == $articuloId) {
                $this->js("window.notify('Este artículo ya está en el grupo', 'warning')");
                return;
            }
        }

        $this->gruposReward[$this->grupoRewardActivo]['articulos'][] = [
            'id' => $articuloId,
            'nombre' => $articulo->nombre,
        ];

        // Limpiar búsqueda y recargar lista completa
        $this->busquedaArticuloReward = '';
        $this->articulosRewardResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();

        // Mantener foco en el input
        $this->js("setTimeout(() => { document.querySelector('[wire\\\\:model\\\\.live\\\\.debounce\\\\.200ms=\"busquedaArticuloReward\"]')?.focus(); }, 50);");
    }

    public function seleccionarPrimerArticuloReward()
    {
        if (!empty($this->articulosRewardResultados)) {
            $this->agregarArticuloReward($this->articulosRewardResultados[0]['id']);
        }
    }

    public function eliminarArticuloReward($grupoIndex, $articuloIndex)
    {
        unset($this->gruposReward[$grupoIndex]['articulos'][$articuloIndex]);
        $this->gruposReward[$grupoIndex]['articulos'] = array_values($this->gruposReward[$grupoIndex]['articulos']);
    }

    public function abrirCategoriasReward($grupoIndex)
    {
        $this->grupoRewardActivo = $grupoIndex;
        $this->mostrarCategoriasReward = true;
        $this->mostrarBuscadorReward = false;
    }

    public function cerrarCategoriasReward()
    {
        $this->mostrarCategoriasReward = false;
    }

    public function agregarArticulosPorCategoriaReward($categoriaId)
    {
        $articulos = Articulo::where('activo', true)
            ->where('categoria_id', $categoriaId)
            ->get();

        $agregados = 0;
        foreach ($articulos as $articulo) {
            // Verificar que no esté ya en el grupo
            $existe = false;
            foreach ($this->gruposReward[$this->grupoRewardActivo]['articulos'] ?? [] as $art) {
                if ($art['id'] == $articulo->id) {
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $this->gruposReward[$this->grupoRewardActivo]['articulos'][] = [
                    'id' => $articulo->id,
                    'nombre' => $articulo->nombre,
                ];
                $agregados++;
            }
        }

        $this->mostrarCategoriasReward = false;

        if ($agregados > 0) {
            $this->js("window.notify('{$agregados} artículo(s) agregado(s)', 'success')");
        } else {
            $this->js("window.notify('No se agregaron artículos (ya estaban todos)', 'info')");
        }
    }

    // ==================== Combo básico ====================

    public function abrirBuscadorCombo()
    {
        $this->mostrarBuscadorCombo = true;
        $this->busquedaArticuloCombo = '';
        $this->articulosComboResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function cerrarBuscadorCombo()
    {
        $this->mostrarBuscadorCombo = false;
    }

    public function updatedBusquedaArticuloCombo($value)
    {
        $this->articulosComboResultados = $this->buscarArticulos($value);
    }

    public function agregarArticuloCombo($articuloId)
    {
        foreach ($this->comboItems as $item) {
            if ($item['articulo_id'] == $articuloId) {
                $this->js("window.notify('Este artículo ya está en el combo', 'warning')");
                return;
            }
        }

        $articulo = Articulo::find($articuloId);
        if ($articulo) {
            $this->comboItems[] = [
                'articulo_id' => $articuloId,
                'nombre' => $articulo->nombre,
                'cantidad' => 1,
                'precio_unitario' => $articulo->precio_base ?? 0,
            ];

            // Limpiar búsqueda y recargar lista completa
            $this->busquedaArticuloCombo = '';
            $this->articulosComboResultados = Articulo::where('activo', true)
                ->orderBy('nombre')
                ->limit(15)
                ->get()
                ->toArray();

            // Mantener foco en el input
            $this->js("setTimeout(() => { document.querySelector('[wire\\\\:model\\\\.live\\\\.debounce\\\\.200ms=\"busquedaArticuloCombo\"]')?.focus(); }, 50);");
        }
    }

    public function seleccionarPrimerArticuloCombo()
    {
        if (!empty($this->articulosComboResultados)) {
            $this->agregarArticuloCombo($this->articulosComboResultados[0]['id']);
        }
    }

    public function eliminarItemCombo($index)
    {
        unset($this->comboItems[$index]);
        $this->comboItems = array_values($this->comboItems);
    }

    public function getPrecioNormalComboProperty()
    {
        $total = 0;
        foreach ($this->comboItems as $item) {
            $total += ($item['precio_unitario'] ?? 0) * ($item['cantidad'] ?? 1);
        }
        return $total;
    }

    public function getAhorroComboProperty()
    {
        if (!$this->precioValor) return 0;

        if ($this->precioTipo === 'fijo') {
            return max(0, $this->precioNormalCombo - $this->precioValor);
        }

        // Porcentaje
        return $this->precioNormalCombo * ($this->precioValor / 100);
    }

    // ==================== Menú ====================

    public function agregarGrupoMenu()
    {
        $this->gruposMenu[] = ['nombre' => '', 'cantidad' => 1, 'articulos' => []];
        $this->grupoMenuActivo = count($this->gruposMenu) - 1;
    }

    public function eliminarGrupoMenu($index)
    {
        unset($this->gruposMenu[$index]);
        $this->gruposMenu = array_values($this->gruposMenu);
        if ($this->grupoMenuActivo >= count($this->gruposMenu)) {
            $this->grupoMenuActivo = max(0, count($this->gruposMenu) - 1);
        }
    }

    public function abrirBuscadorMenu($grupoIndex)
    {
        $this->grupoMenuActivo = $grupoIndex;
        $this->mostrarBuscadorMenu = true;
        $this->busquedaArticuloMenu = '';
        $this->articulosMenuResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function cerrarBuscadorMenu()
    {
        $this->mostrarBuscadorMenu = false;
    }

    public function updatedBusquedaArticuloMenu($value)
    {
        $this->articulosMenuResultados = $this->buscarArticulos($value);
    }

    public function agregarArticuloMenu($articuloId)
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        foreach ($this->gruposMenu[$this->grupoMenuActivo]['articulos'] ?? [] as $art) {
            if ($art['id'] == $articuloId) {
                $this->js("window.notify('Este artículo ya está en el grupo', 'warning')");
                return;
            }
        }

        $this->gruposMenu[$this->grupoMenuActivo]['articulos'][] = [
            'id' => $articuloId,
            'nombre' => $articulo->nombre,
            'precio' => $articulo->precio_base ?? 0,
        ];

        // Limpiar búsqueda y recargar lista completa
        $this->busquedaArticuloMenu = '';
        $this->articulosMenuResultados = Articulo::where('activo', true)
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->toArray();

        // Mantener foco en el input
        $this->js("setTimeout(() => { document.querySelector('[wire\\\\:model\\\\.live\\\\.debounce\\\\.200ms=\"busquedaArticuloMenu\"]')?.focus(); }, 50);");
    }

    public function seleccionarPrimerArticuloMenu()
    {
        if (!empty($this->articulosMenuResultados)) {
            $this->agregarArticuloMenu($this->articulosMenuResultados[0]['id']);
        }
    }

    public function eliminarArticuloMenu($grupoIndex, $articuloIndex)
    {
        unset($this->gruposMenu[$grupoIndex]['articulos'][$articuloIndex]);
        $this->gruposMenu[$grupoIndex]['articulos'] = array_values($this->gruposMenu[$grupoIndex]['articulos']);
    }

    public function getPrecioNormalMenuProperty()
    {
        // Suma del precio mínimo de cada grupo * cantidad
        $total = 0;
        foreach ($this->gruposMenu as $grupo) {
            if (!empty($grupo['articulos'])) {
                $precioMin = min(array_column($grupo['articulos'], 'precio'));
                $total += $precioMin * ($grupo['cantidad'] ?? 1);
            }
        }
        return $total;
    }

    // ==================== Escalas ====================

    public function agregarEscala()
    {
        $this->escalas[] = [
            'cantidad_desde' => null,
            'cantidad_hasta' => null,
            'lleva' => null,
            'bonifica' => null,
            'beneficio_tipo' => 'gratis',
            'beneficio_porcentaje' => 50,
        ];
    }

    public function eliminarEscala($index)
    {
        unset($this->escalas[$index]);
        $this->escalas = array_values($this->escalas);
    }

    /**
     * Cuando cambia el tipo de beneficio en una escala, si es descuento, forzar bonifica a 1
     */
    public function updatedEscalas($value, $key)
    {
        // El key viene como "0.beneficio_tipo" o "1.beneficio_tipo"
        if (str_contains($key, 'beneficio_tipo')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];

            if (isset($this->escalas[$index]) && $value === 'descuento') {
                $this->escalas[$index]['bonifica'] = 1;
            }
        }
    }

    // ==================== Helpers ====================

    protected function buscarArticulos($busqueda): array
    {
        $query = Articulo::where('activo', true);

        if (strlen($busqueda) >= 1) {
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
            });
        }

        return $query->orderBy('nombre')->limit(15)->get()->toArray();
    }

    // ==================== Guardar ====================

    public function guardar()
    {
        if (!$this->validarPasoActual()) {
            return;
        }

        try {
            if ($this->modoEdicion) {
                $this->actualizarPromocion();
            } else {
                $this->crearPromocion();
            }

            // Guardar las prioridades modificadas de otras promociones
            $this->guardarPrioridadesModificadas();

            return redirect()->route('configuracion.promociones-especiales');

        } catch (\Exception $e) {
            \Log::error('Error al guardar promoción especial: ' . $e->getMessage());
            $this->js("window.notify('Error al guardar: " . addslashes($e->getMessage()) . "', 'error')");
        }
    }

    /**
     * Guarda las prioridades modificadas de las promociones competidoras
     */
    protected function guardarPrioridadesModificadas(): void
    {
        if (empty($this->prioridadesTemporales)) {
            return;
        }

        foreach ($this->promocionesEspecialesCompetidoras as $promo) {
            $prioridadTemporal = $this->prioridadesTemporales[$promo->id] ?? null;

            // Solo actualizar si cambió
            if ($prioridadTemporal !== null && $prioridadTemporal != $promo->prioridad) {
                $promo->update(['prioridad' => $prioridadTemporal]);
            }
        }
    }

    protected function crearPromocion()
    {
        foreach ($this->sucursalesSeleccionadas as $sucursalId) {
            $promo = PromocionEspecial::create([
                'sucursal_id' => $sucursalId,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
                'tipo' => $this->tipo,
                'nxm_lleva' => $this->esNxM() && !$this->usarEscalas ? $this->nxmLleva : null,
                'nxm_bonifica' => $this->esNxM() && !$this->usarEscalas ? $this->nxmBonifica : null,
                'beneficio_tipo' => $this->esNxM() && !$this->usarEscalas ? $this->beneficioTipo : 'gratis',
                'beneficio_porcentaje' => $this->esNxM() && !$this->usarEscalas && $this->beneficioTipo === 'descuento' ? $this->beneficioPorcentaje : null,
                'nxm_articulo_id' => $this->tipo === 'nxm' && $this->nxmAplicaA === 'articulo' ? $this->nxmArticuloId : null,
                'nxm_categoria_id' => $this->tipo === 'nxm' && $this->nxmAplicaA === 'categoria' ? $this->nxmCategoriaId : null,
                'usa_escalas' => $this->esNxM() ? $this->usarEscalas : false,
                'precio_tipo' => $this->esComboOMenu() ? $this->precioTipo : 'fijo',
                'precio_valor' => $this->esComboOMenu() ? $this->precioValor : null,
                'prioridad' => $this->prioridad,
                'activo' => true,
                'vigencia_desde' => $this->vigenciaDesde,
                'vigencia_hasta' => $this->vigenciaHasta,
                'dias_semana' => !empty($this->diasSemana) ? $this->diasSemana : null,
                'hora_desde' => $this->horaDesde,
                'hora_hasta' => $this->horaHasta,
                'forma_venta_id' => $this->formaVentaId,
                'canal_venta_id' => $this->canalVentaId,
                'forma_pago_id' => $this->formaPagoId,
                'usos_maximos' => $this->usosMaximos,
            ]);

            $this->guardarDatosEspecificos($promo);
        }

        $cant = count($this->sucursalesSeleccionadas);
        $msg = $cant > 1 ? "Promoción creada en {$cant} sucursales" : 'Promoción creada correctamente';
        $this->js("window.notify('{$msg}', 'success')");
    }

    protected function actualizarPromocion()
    {
        $promo = PromocionEspecial::findOrFail($this->promocionId);

        $promo->update([
            'sucursal_id' => $this->sucursalesSeleccionadas[0] ?? $promo->sucursal_id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'nxm_lleva' => $this->esNxM() && !$this->usarEscalas ? $this->nxmLleva : null,
            'nxm_bonifica' => $this->esNxM() && !$this->usarEscalas ? $this->nxmBonifica : null,
            'beneficio_tipo' => $this->esNxM() && !$this->usarEscalas ? $this->beneficioTipo : 'gratis',
            'beneficio_porcentaje' => $this->esNxM() && !$this->usarEscalas && $this->beneficioTipo === 'descuento' ? $this->beneficioPorcentaje : null,
            'nxm_articulo_id' => $this->tipo === 'nxm' && $this->nxmAplicaA === 'articulo' ? $this->nxmArticuloId : null,
            'nxm_categoria_id' => $this->tipo === 'nxm' && $this->nxmAplicaA === 'categoria' ? $this->nxmCategoriaId : null,
            'usa_escalas' => $this->esNxM() ? $this->usarEscalas : false,
            'precio_tipo' => $this->esComboOMenu() ? $this->precioTipo : 'fijo',
            'precio_valor' => $this->esComboOMenu() ? $this->precioValor : null,
            'prioridad' => $this->prioridad,
            'activo' => $this->activo,
            'vigencia_desde' => $this->vigenciaDesde,
            'vigencia_hasta' => $this->vigenciaHasta,
            'dias_semana' => !empty($this->diasSemana) ? $this->diasSemana : null,
            'hora_desde' => $this->horaDesde,
            'hora_hasta' => $this->horaHasta,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
            'forma_pago_id' => $this->formaPagoId,
            'usos_maximos' => $this->usosMaximos,
        ]);

        // Limpiar datos anteriores
        $promo->grupos()->delete();
        $promo->escalas()->delete();

        $this->guardarDatosEspecificos($promo);

        $this->js("window.notify('Promoción actualizada correctamente', 'success')");
    }

    protected function guardarDatosEspecificos(PromocionEspecial $promo)
    {
        // Guardar escalas para NxM
        if ($this->esNxM() && $this->usarEscalas) {
            foreach ($this->escalas as $escala) {
                if (!empty($escala['cantidad_desde']) && !empty($escala['lleva']) && !empty($escala['bonifica'])) {
                    $beneficioTipo = $escala['beneficio_tipo'] ?? 'gratis';
                    $promo->escalas()->create([
                        'cantidad_desde' => $escala['cantidad_desde'],
                        'cantidad_hasta' => $escala['cantidad_hasta'],
                        'lleva' => $escala['lleva'],
                        'bonifica' => $escala['bonifica'],
                        'beneficio_tipo' => $beneficioTipo,
                        'beneficio_porcentaje' => $beneficioTipo === 'descuento' ? ($escala['beneficio_porcentaje'] ?? null) : null,
                    ]);
                }
            }
        }

        // Guardar grupos para NxM avanzado
        if ($this->tipo === 'nxm_avanzado') {
            $orden = 0;
            foreach ($this->gruposTrigger as $grupo) {
                if (!empty($grupo['articulos'])) {
                    $g = $promo->grupos()->create([
                        'nombre' => $grupo['nombre'] ?: 'Triggers',
                        'cantidad' => 1,
                        'es_trigger' => true,
                        'es_reward' => false,
                        'orden' => $orden++,
                    ]);
                    $g->articulos()->attach(array_column($grupo['articulos'], 'id'));
                }
            }
            foreach ($this->gruposReward as $grupo) {
                if (!empty($grupo['articulos'])) {
                    $g = $promo->grupos()->create([
                        'nombre' => $grupo['nombre'] ?: 'Rewards',
                        'cantidad' => 1,
                        'es_trigger' => false,
                        'es_reward' => true,
                        'orden' => $orden++,
                    ]);
                    $g->articulos()->attach(array_column($grupo['articulos'], 'id'));
                }
            }
        }

        // Guardar grupos para Combo
        if ($this->tipo === 'combo') {
            foreach ($this->comboItems as $index => $item) {
                $g = $promo->grupos()->create([
                    'nombre' => null,
                    'cantidad' => $item['cantidad'],
                    'es_trigger' => false,
                    'es_reward' => false,
                    'orden' => $index,
                ]);
                $g->articulos()->attach($item['articulo_id']);
            }
        }

        // Guardar grupos para Menú
        if ($this->tipo === 'menu') {
            foreach ($this->gruposMenu as $index => $grupo) {
                if (!empty($grupo['articulos'])) {
                    $g = $promo->grupos()->create([
                        'nombre' => $grupo['nombre'],
                        'cantidad' => $grupo['cantidad'] ?? 1,
                        'es_trigger' => false,
                        'es_reward' => false,
                        'orden' => $index,
                    ]);
                    $g->articulos()->attach(array_column($grupo['articulos'], 'id'));
                }
            }
        }
    }

    protected function esNxM(): bool
    {
        return in_array($this->tipo, ['nxm', 'nxm_avanzado']);
    }

    protected function esComboOMenu(): bool
    {
        return in_array($this->tipo, ['combo', 'menu']);
    }

    // ==================== Simulador de Promociones Especiales ====================

    /**
     * Inicializa el simulador cuando entramos al paso 4
     */
    public function inicializarSimulador()
    {
        // Usar la primera sucursal seleccionada o la primera disponible
        if (!$this->simuladorSucursalId) {
            $this->simuladorSucursalId = $this->sucursalesSeleccionadas[0] ?? $this->sucursales->first()?->id;
        }

        $this->cargarListasPreciosSimulador();
        $this->cargarPromocionesCompetidoras();
    }

    /**
     * Carga las listas de precios para la sucursal seleccionada
     */
    public function cargarListasPreciosSimulador()
    {
        if (!$this->simuladorSucursalId) {
            $this->listasPreciosSimulador = [];
            return;
        }

        $this->listasPreciosSimulador = ListaPrecio::where('sucursal_id', $this->simuladorSucursalId)
            ->where('activo', true)
            ->orderBy('es_lista_base', 'desc')
            ->orderBy('prioridad')
            ->get()
            ->map(fn($lista) => [
                'id' => (int) $lista->id,
                'nombre' => $lista->nombre,
                'es_lista_base' => (bool) $lista->es_lista_base,
                'ajuste_porcentaje' => (float) $lista->ajuste_porcentaje,
                'descripcion_ajuste' => $lista->obtenerDescripcionAjuste(),
                'aplica_promociones' => (bool) $lista->aplica_promociones,
                'promociones_alcance' => $lista->promociones_alcance,
            ])
            ->toArray();
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

    /**
     * Obtiene el precio de un artículo validando condiciones de la lista
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
     * Carga las promociones especiales competidoras
     */
    public function cargarPromocionesCompetidoras()
    {
        if (!$this->simuladorSucursalId) {
            $this->promocionesEspecialesCompetidoras = [];
            $this->prioridadesTemporales = [];
            return;
        }

        $query = PromocionEspecial::where('sucursal_id', $this->simuladorSucursalId)
            ->where('activo', true)
            ->with(['grupos.articulos', 'escalas', 'articuloNxM', 'categoriaNxM']);

        // Si estamos editando, excluir la promoción actual
        if ($this->modoEdicion && $this->promocionId) {
            $query->where('id', '!=', $this->promocionId);
        }

        $this->promocionesEspecialesCompetidoras = $query->orderBy('prioridad')
            ->get();

        // Inicializar prioridades temporales con los valores actuales
        $this->prioridadesTemporales = [];
        foreach ($this->promocionesEspecialesCompetidoras as $promo) {
            $this->prioridadesTemporales[$promo->id] = $promo->prioridad;
        }
    }

    /**
     * Actualiza la prioridad temporal de una promoción competidora
     */
    public function actualizarPrioridadCompetidora($promocionId, $nuevaPrioridad)
    {
        $nuevaPrioridad = (int) $nuevaPrioridad;
        if ($nuevaPrioridad < 1) {
            $nuevaPrioridad = 1;
        }

        $this->prioridadesTemporales[$promocionId] = $nuevaPrioridad;
        $this->simularVenta();
    }

    /**
     * Actualiza la prioridad de la promoción actual y resimula
     */
    public function actualizarPrioridadActual($nuevaPrioridad)
    {
        $nuevaPrioridad = (int) $nuevaPrioridad;
        if ($nuevaPrioridad < 1) {
            $nuevaPrioridad = 1;
        }

        $this->prioridad = $nuevaPrioridad;
        $this->simularVenta();
    }

    /**
     * Obtiene la prioridad efectiva de una promoción (temporal si existe, sino la original)
     */
    public function obtenerPrioridadEfectiva($promocionId): int
    {
        return $this->prioridadesTemporales[$promocionId] ?? 0;
    }

    /**
     * Handler cuando cambia la sucursal del simulador
     */
    public function updatedSimuladorSucursalId()
    {
        // Resetear la lista de precios seleccionada antes de cargar las nuevas
        $this->simuladorListaPrecioId = null;

        // Cargar listas de precios de la nueva sucursal
        $this->cargarListasPreciosSimulador();

        // Seleccionar la lista base por defecto
        $this->simuladorListaPrecioId = $this->obtenerIdListaBaseSimulador();

        // Recalcular precios de artículos ya agregados con la nueva lista
        $this->actualizarPreciosItems();

        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    /**
     * Handler cuando cambia la lista de precios
     */
    public function updatedSimuladorListaPrecioId()
    {
        $this->actualizarPreciosItems();
        $this->simularVenta();
    }

    /**
     * Handler cuando cambia la prioridad de la promoción actual
     */
    public function updatedPrioridad()
    {
        // Re-simular si estamos en el paso del simulador
        if ($this->pasoActual === 4) {
            $this->simularVenta();
        }
    }

    /**
     * Handler cuando cambia la forma de venta, canal o forma de pago
     */
    public function updatedSimuladorFormaVentaId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItems();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorCanalVentaId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItems();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    public function updatedSimuladorFormaPagoId()
    {
        // Recalcular precios ya que las condiciones de la lista pueden depender de esto
        $this->actualizarPreciosItems();
        $this->cargarPromocionesCompetidoras();
        $this->simularVenta();
    }

    /**
     * Abre el buscador de artículos del simulador
     */
    public function abrirBuscadorArticulosSimulador()
    {
        $this->mostrarBuscadorArticulosSimulador = true;
        $this->busquedaArticuloSimulador = '';
        $this->articulosSimuladorResultados = $this->buscarArticulosSimulador('');
    }

    /**
     * Cierra el buscador de artículos del simulador
     */
    public function cerrarBuscadorArticulosSimulador()
    {
        $this->mostrarBuscadorArticulosSimulador = false;
        $this->busquedaArticuloSimulador = '';
        $this->articulosSimuladorResultados = [];
    }

    /**
     * Handler para búsqueda en el simulador
     */
    public function updatedBusquedaArticuloSimulador($value)
    {
        $this->articulosSimuladorResultados = $this->buscarArticulosSimulador($value);
    }

    /**
     * Busca artículos con precio de la lista seleccionada
     */
    protected function buscarArticulosSimulador(string $busqueda): array
    {
        $query = Articulo::with('categoriaModel')->where('activo', true);

        if (strlen($busqueda) >= 1) {
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo', 'like', '%' . $busqueda . '%')
                  ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
            });
        }

        $articulos = $query->orderBy('nombre')->limit(15)->get();

        return $articulos->map(function($art) {
            $precioInfo = $this->obtenerPrecioConLista($art);

            return [
                'id' => $art->id,
                'nombre' => $art->nombre,
                'codigo' => $art->codigo,
                'categoria_id' => $art->categoria_id,
                'categoria_nombre' => $art->categoriaModel?->nombre,
                'precio_base' => $precioInfo['precio_base'],
                'precio' => $precioInfo['precio'],
            ];
        })->toArray();
    }

    /**
     * Agrega un artículo al simulador
     */
    public function agregarArticuloSimulador($articuloId)
    {
        $articulo = Articulo::with('categoriaModel')->find($articuloId);
        if (!$articulo) return;

        // Obtener precio de la lista validando condiciones
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

        // Limpiar búsqueda y recargar resultados
        $this->busquedaArticuloSimulador = '';
        $this->articulosSimuladorResultados = $this->buscarArticulosSimulador('');

        // Mantener foco en el input
        $this->js("setTimeout(() => { document.querySelector('[wire\\\\:model\\\\.live\\\\.debounce\\\\.200ms=\"busquedaArticuloSimulador\"]')?.focus(); }, 50);");

        $this->simularVenta();
    }

    /**
     * Agrega el primer artículo de los resultados
     */
    public function agregarPrimerArticuloSimulador()
    {
        if (!empty($this->articulosSimuladorResultados)) {
            $this->agregarArticuloSimulador($this->articulosSimuladorResultados[0]['id']);
        }
    }

    /**
     * Elimina un artículo del simulador
     */
    public function eliminarItemSimulador($index)
    {
        unset($this->itemsSimulador[$index]);
        $this->itemsSimulador = array_values($this->itemsSimulador);
        $this->simularVenta();
    }

    /**
     * Actualiza los precios de los items según la lista seleccionada
     */
    protected function actualizarPreciosItems()
    {
        foreach ($this->itemsSimulador as &$item) {
            $articulo = Articulo::find($item['articulo_id']);
            if (!$articulo) continue;

            $precioInfo = $this->obtenerPrecioConLista($articulo);
            $item['precio_base'] = $precioInfo['precio_base'];
            $item['precio'] = $precioInfo['precio'];
            $item['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
        }
    }

    /**
     * Handler cuando cambia la cantidad de un item
     */
    public function updatedItemsSimulador()
    {
        $this->simularVenta();
    }

    /**
     * Ejecuta la simulación de venta con promociones especiales
     * Esta es la lógica central que implementa el "consumo" de artículos
     */
    public function simularVenta()
    {
        if (empty($this->itemsSimulador)) {
            $this->resultadoSimulador = null;
            return;
        }

        // Preparar el contexto de venta
        $contexto = [
            'forma_venta_id' => $this->simuladorFormaVentaId,
            'canal_venta_id' => $this->simuladorCanalVentaId,
            'forma_pago_id' => $this->simuladorFormaPagoId,
            'fecha' => now(),
            'hora' => now()->format('H:i:s'),
            'dia_semana' => strtolower(now()->locale('es')->dayName),
        ];

        // Verificar exclusión de artículos por lista de precios
        $articulosExcluidos = [];
        foreach ($this->itemsSimulador as $item) {
            $articuloId = $item['articulo_id'] ?? null;
            if ($articuloId && $this->articuloExcluidoDePromociones($articuloId)) {
                $articulosExcluidos[$articuloId] = true;
            }
        }

        // Crear pool de unidades disponibles
        // Cada unidad tiene un ID único para trackear su consumo
        $poolUnidades = [];
        $unidadId = 0;
        foreach ($this->itemsSimulador as $itemIndex => $item) {
            $cantidad = (int) ($item['cantidad'] ?? 1);
            $articuloId = $item['articulo_id'] ?? null;
            $excluido = isset($articulosExcluidos[$articuloId]);

            for ($i = 0; $i < $cantidad; $i++) {
                $poolUnidades[] = [
                    'id' => $unidadId++,
                    'item_index' => $itemIndex,
                    'articulo_id' => $articuloId,
                    'categoria_id' => $item['categoria_id'] ?? null,
                    'nombre' => $item['nombre'],
                    'precio' => (float) ($item['precio'] ?? 0),
                    'consumida' => false,
                    'consumida_por' => null,
                    'excluido_promociones' => $excluido,
                ];
            }
        }

        // Preparar todas las promociones especiales (la nueva + competidoras)
        $todasPromociones = $this->prepararPromocionesEspecialesParaSimulacion();

        // Ordenar por prioridad (menor = más prioritaria)
        usort($todasPromociones, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $resultado = [
            'items' => [],
            'subtotal' => 0,
            'promociones_aplicadas' => [],
            'promociones_no_aplicadas' => [],
            'total_descuentos' => 0,
            'total_final' => 0,
        ];

        // Calcular subtotal
        foreach ($this->itemsSimulador as $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 1);
            $resultado['subtotal'] += $precio * $cantidad;
        }

        // Evaluar cada promoción en orden de prioridad
        foreach ($todasPromociones as $promo) {
            // Verificar condiciones de la promoción
            if (!$this->promocionCumpleCondiciones($promo, $contexto)) {
                $resultado['promociones_no_aplicadas'][] = [
                    'id' => $promo['id'],
                    'nombre' => $promo['nombre'],
                    'es_nueva' => $promo['es_nueva'],
                    'razon' => 'No cumple condiciones',
                ];
                continue;
            }

            // Intentar aplicar la promoción con las unidades disponibles
            $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $poolUnidades);

            if ($aplicacion['aplicada']) {
                // Marcar unidades como consumidas
                foreach ($aplicacion['unidades_consumidas'] as $unidadIdConsumida) {
                    foreach ($poolUnidades as &$unidad) {
                        if ($unidad['id'] === $unidadIdConsumida) {
                            $unidad['consumida'] = true;
                            $unidad['consumida_por'] = $promo['nombre'];
                        }
                    }
                }

                $resultado['promociones_aplicadas'][] = [
                    'id' => $promo['id'],
                    'nombre' => $promo['nombre'],
                    'tipo' => $promo['tipo'],
                    'es_nueva' => $promo['es_nueva'],
                    'descuento' => $aplicacion['descuento'],
                    'descripcion' => $aplicacion['descripcion'],
                    'unidades_usadas' => count($aplicacion['unidades_consumidas']),
                ];

                $resultado['total_descuentos'] += $aplicacion['descuento'];
            } else {
                $resultado['promociones_no_aplicadas'][] = [
                    'id' => $promo['id'],
                    'nombre' => $promo['nombre'],
                    'es_nueva' => $promo['es_nueva'],
                    'razon' => $aplicacion['razon'] ?? 'No hay suficientes artículos',
                ];
            }
        }

        // Calcular total final
        $resultado['total_final'] = max(0, $resultado['subtotal'] - $resultado['total_descuentos']);

        // Preparar información de items con estado de consumo
        foreach ($this->itemsSimulador as $index => $item) {
            $unidadesDelItem = array_filter($poolUnidades, fn($u) => $u['item_index'] === $index);
            $unidadesConsumidas = array_filter($unidadesDelItem, fn($u) => $u['consumida']);
            $unidadesLibres = array_filter($unidadesDelItem, fn($u) => !$u['consumida']);
            $articuloId = $item['articulo_id'] ?? null;
            $excluido = $articuloId ? isset($articulosExcluidos[$articuloId]) : false;

            $resultado['items'][$index] = [
                'articulo_id' => $articuloId,
                'nombre' => $item['nombre'],
                'precio_base' => (float) ($item['precio_base'] ?? $item['precio'] ?? 0),
                'precio_lista' => (float) ($item['precio'] ?? 0),
                'cantidad' => (int) ($item['cantidad'] ?? 1),
                'precio_unitario' => (float) ($item['precio'] ?? 0),
                'subtotal' => (float) ($item['precio'] ?? 0) * (int) ($item['cantidad'] ?? 1),
                'unidades_consumidas' => count($unidadesConsumidas),
                'unidades_libres' => count($unidadesLibres),
                'excluido_promociones' => $excluido,
                'promociones_participantes' => array_unique(array_filter(
                    array_column($unidadesConsumidas, 'consumida_por')
                )),
            ];
        }

        $this->resultadoSimulador = $resultado;
    }

    /**
     * Prepara las promociones especiales para la simulación
     */
    protected function prepararPromocionesEspecialesParaSimulacion(): array
    {
        $promociones = [];

        // Agregar promociones competidoras
        foreach ($this->promocionesEspecialesCompetidoras as $promo) {
            $promociones[] = $this->convertirPromocionAArray($promo, false);
        }

        // Agregar la promoción que se está creando/editando
        $promociones[] = $this->construirPromocionNuevaParaSimulacion();

        return $promociones;
    }

    /**
     * Convierte un modelo PromocionEspecial a array para simulación
     */
    protected function convertirPromocionAArray(PromocionEspecial $promo, bool $esNueva): array
    {
        // Usar prioridad temporal si existe, sino la original
        $prioridadEfectiva = $this->prioridadesTemporales[$promo->id] ?? $promo->prioridad;

        return [
            'id' => $promo->id,
            'nombre' => $promo->nombre,
            'tipo' => $promo->tipo,
            'prioridad' => $prioridadEfectiva,
            'es_nueva' => $esNueva,
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
            'grupos_trigger' => $promo->gruposTrigger->map(fn($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray(),
            'grupos_reward' => $promo->gruposReward->map(fn($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray(),
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
            // Condiciones
            'forma_venta_id' => $promo->forma_venta_id,
            'canal_venta_id' => $promo->canal_venta_id,
            'forma_pago_id' => $promo->forma_pago_id,
            'vigencia_desde' => $promo->vigencia_desde,
            'vigencia_hasta' => $promo->vigencia_hasta,
            'dias_semana' => $promo->dias_semana ?? [],
            'hora_desde' => $promo->hora_desde,
            'hora_hasta' => $promo->hora_hasta,
        ];
    }

    /**
     * Construye la promoción nueva desde los datos del formulario
     */
    protected function construirPromocionNuevaParaSimulacion(): array
    {
        $gruposTriggerArray = [];
        foreach ($this->gruposTrigger as $grupo) {
            $gruposTriggerArray[] = [
                'nombre' => $grupo['nombre'] ?? '',
                'articulos_ids' => array_column($grupo['articulos'] ?? [], 'id'),
            ];
        }

        $gruposRewardArray = [];
        foreach ($this->gruposReward as $grupo) {
            $gruposRewardArray[] = [
                'nombre' => $grupo['nombre'] ?? '',
                'articulos_ids' => array_column($grupo['articulos'] ?? [], 'id'),
            ];
        }

        $gruposArray = [];
        if ($this->tipo === 'combo') {
            foreach ($this->comboItems as $item) {
                $gruposArray[] = [
                    'nombre' => null,
                    'cantidad' => $item['cantidad'] ?? 1,
                    'articulos' => [['id' => $item['articulo_id'], 'precio' => $item['precio_unitario'] ?? 0]],
                ];
            }
        } elseif ($this->tipo === 'menu') {
            foreach ($this->gruposMenu as $grupo) {
                $gruposArray[] = [
                    'nombre' => $grupo['nombre'],
                    'cantidad' => $grupo['cantidad'] ?? 1,
                    'articulos' => array_map(fn($a) => ['id' => $a['id'], 'precio' => $a['precio'] ?? 0], $grupo['articulos'] ?? []),
                ];
            }
        }

        return [
            'id' => 'nueva',
            'nombre' => $this->nombre ?: '(Esta promoción)',
            'tipo' => $this->tipo,
            'prioridad' => (int) ($this->prioridad ?? 1),
            'es_nueva' => true,
            // NxM básico
            'nxm_lleva' => $this->usarEscalas ? null : $this->nxmLleva,
            'nxm_bonifica' => $this->usarEscalas ? null : ($this->beneficioTipo === 'descuento' ? 1 : $this->nxmBonifica),
            'nxm_articulo_id' => ($this->tipo === 'nxm' && $this->nxmAplicaA === 'articulo') ? $this->nxmArticuloId : null,
            'nxm_categoria_id' => ($this->tipo === 'nxm' && $this->nxmAplicaA === 'categoria') ? $this->nxmCategoriaId : null,
            'beneficio_tipo' => $this->beneficioTipo ?? 'gratis',
            'beneficio_porcentaje' => $this->beneficioPorcentaje ?? 100,
            'usa_escalas' => $this->usarEscalas,
            'escalas' => $this->escalas,
            // NxM avanzado
            'grupos_trigger' => $gruposTriggerArray,
            'grupos_reward' => $gruposRewardArray,
            // Combo/Menu
            'precio_tipo' => $this->precioTipo,
            'precio_valor' => $this->precioValor,
            'grupos' => $gruposArray,
            // Condiciones
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
            'forma_pago_id' => $this->formaPagoId,
            'vigencia_desde' => $this->vigenciaDesde,
            'vigencia_hasta' => $this->vigenciaHasta,
            'dias_semana' => $this->diasSemana ?? [],
            'hora_desde' => $this->horaDesde,
            'hora_hasta' => $this->horaHasta,
        ];
    }

    /**
     * Verifica si una promoción cumple las condiciones del contexto
     */
    protected function promocionCumpleCondiciones(array $promo, array $contexto): bool
    {
        // Verificar forma de venta
        if ($promo['forma_venta_id'] && $contexto['forma_venta_id'] && $promo['forma_venta_id'] != $contexto['forma_venta_id']) {
            return false;
        }

        // Verificar canal de venta
        if ($promo['canal_venta_id'] && $contexto['canal_venta_id'] && $promo['canal_venta_id'] != $contexto['canal_venta_id']) {
            return false;
        }

        // Verificar forma de pago
        if ($promo['forma_pago_id'] && $contexto['forma_pago_id'] && $promo['forma_pago_id'] != $contexto['forma_pago_id']) {
            return false;
        }

        // Verificar vigencia por fecha
        if ($promo['vigencia_desde'] && $contexto['fecha'] < $promo['vigencia_desde']) {
            return false;
        }
        if ($promo['vigencia_hasta'] && $contexto['fecha'] > $promo['vigencia_hasta']) {
            return false;
        }

        // Verificar día de la semana
        if (!empty($promo['dias_semana']) && !in_array($contexto['dia_semana'], $promo['dias_semana'])) {
            return false;
        }

        // Verificar horario
        if ($promo['hora_desde'] && $contexto['hora'] < $promo['hora_desde']) {
            return false;
        }
        if ($promo['hora_hasta'] && $contexto['hora'] > $promo['hora_hasta']) {
            return false;
        }

        return true;
    }

    /**
     * Intenta aplicar una promoción especial al pool de unidades disponibles
     * Retorna información sobre si se aplicó y qué unidades consumió
     */
    protected function intentarAplicarPromocionEspecial(array $promo, array $poolUnidades): array
    {
        // Filtrar solo unidades disponibles (no consumidas Y no excluidas de promociones)
        $unidadesDisponibles = array_filter($poolUnidades, fn($u) => !$u['consumida'] && !($u['excluido_promociones'] ?? false));

        return match($promo['tipo']) {
            'nxm' => $this->aplicarNxMBasico($promo, $unidadesDisponibles),
            'nxm_avanzado' => $this->aplicarNxMAvanzado($promo, $unidadesDisponibles),
            'combo' => $this->aplicarCombo($promo, $unidadesDisponibles),
            'menu' => $this->aplicarMenu($promo, $unidadesDisponibles),
            default => ['aplicada' => false, 'razon' => 'Tipo de promoción no soportado'],
        };
    }

    /**
     * Aplica promoción NxM básico
     */
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
            // Buscar escala que aplique
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
                return ['aplicada' => false, 'razon' => 'No hay escala aplicable para la cantidad'];
            }

            $lleva = (int) $escalaAplicable['lleva'];
            $bonifica = (int) $escalaAplicable['bonifica'];
            $beneficioTipo = $escalaAplicable['beneficio_tipo'] ?? 'gratis';
            $beneficioPorcentaje = $escalaAplicable['beneficio_porcentaje'] ?? 100;
        }

        if ($cantidadDisponible < $lleva) {
            return ['aplicada' => false, 'razon' => "Necesita $lleva, hay $cantidadDisponible"];
        }

        // Ordenar por precio descendente (bonificar los más caros primero da el mayor descuento)
        usort($unidadesAplicables, fn($a, $b) => $b['precio'] <=> $a['precio']);

        // Calcular cuántas veces se puede aplicar la promoción
        $vecesAplicable = floor($cantidadDisponible / $lleva);
        $unidadesConsumidas = [];
        $descuentoTotal = 0;

        for ($vez = 0; $vez < $vecesAplicable; $vez++) {
            $offset = $vez * $lleva;
            $unidadesParaEstaVez = array_slice($unidadesAplicables, $offset, $lleva);

            // Las primeras 'bonifica' unidades reciben el beneficio
            for ($i = 0; $i < $bonifica && $i < count($unidadesParaEstaVez); $i++) {
                $unidad = $unidadesParaEstaVez[$i];
                if ($beneficioTipo === 'gratis') {
                    $descuentoTotal += $unidad['precio'];
                } else {
                    $descuentoTotal += $unidad['precio'] * ($beneficioPorcentaje / 100);
                }
            }

            // Marcar todas las unidades del grupo como consumidas
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

    /**
     * Aplica promoción NxM avanzado (triggers y rewards separados)
     */
    protected function aplicarNxMAvanzado(array $promo, array $unidadesDisponibles): array
    {
        // Obtener IDs de artículos trigger y reward
        $triggerIds = [];
        foreach ($promo['grupos_trigger'] as $grupo) {
            $triggerIds = array_merge($triggerIds, $grupo['articulos_ids'] ?? []);
        }
        $rewardIds = [];
        foreach ($promo['grupos_reward'] as $grupo) {
            $rewardIds = array_merge($rewardIds, $grupo['articulos_ids'] ?? []);
        }

        // Filtrar unidades trigger y reward
        $unidadesTrigger = array_values(array_filter($unidadesDisponibles, fn($u) => in_array($u['articulo_id'], $triggerIds)));
        $unidadesReward = array_values(array_filter($unidadesDisponibles, fn($u) => in_array($u['articulo_id'], $rewardIds)));

        // Determinar lleva/bonifica
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
            return ['aplicada' => false, 'razon' => "Necesita {$lleva} triggers, hay " . count($unidadesTrigger)];
        }

        if (count($unidadesReward) < $bonifica) {
            return ['aplicada' => false, 'razon' => "Necesita {$bonifica} rewards, hay " . count($unidadesReward)];
        }

        // Ordenar rewards por precio descendente
        usort($unidadesReward, fn($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = min(
            floor(count($unidadesTrigger) / $lleva),
            floor(count($unidadesReward) / $bonifica)
        );

        $unidadesConsumidas = [];
        $descuentoTotal = 0;

        for ($vez = 0; $vez < $vecesAplicable; $vez++) {
            // Consumir triggers
            for ($i = 0; $i < $lleva; $i++) {
                $idx = $vez * $lleva + $i;
                if (isset($unidadesTrigger[$idx])) {
                    $unidadesConsumidas[] = $unidadesTrigger[$idx]['id'];
                }
            }

            // Consumir rewards y calcular descuento
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

    /**
     * Aplica promoción tipo Combo/Pack
     */
    protected function aplicarCombo(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Combo sin artículos definidos'];
        }

        // Verificar que tengamos todos los artículos necesarios
        $unidadesConsumidas = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (int) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = $grupo['articulos'] ?? [];

            if (empty($articulosDelGrupo)) continue;

            $articuloId = $articulosDelGrupo[0]['id'] ?? null;
            if (!$articuloId) continue;

            // Buscar unidades disponibles de este artículo
            $unidadesDeEsteArticulo = array_values(array_filter(
                $unidadesDisponibles,
                fn($u) => $u['articulo_id'] == $articuloId && !in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteArticulo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => 'Faltan artículos para el combo'];
            }

            // Consumir las unidades necesarias
            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteArticulo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos para el combo'];
        }

        // Calcular descuento
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

    /**
     * Aplica promoción tipo Menú
     */
    protected function aplicarMenu(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Menú sin grupos definidos'];
        }

        $unidadesConsumidas = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (int) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = array_column($grupo['articulos'] ?? [], 'id');

            if (empty($articulosDelGrupo)) {
                return ['aplicada' => false, 'razon' => "Grupo '{$grupo['nombre']}' sin artículos"];
            }

            // Buscar unidades disponibles de cualquier artículo del grupo
            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn($u) => in_array($u['articulo_id'], $articulosDelGrupo) && !in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => "Faltan artículos para '{$grupo['nombre']}'"];
            }

            // Consumir las unidades (preferir las más baratas para el menú)
            usort($unidadesDeEsteGrupo, fn($a, $b) => $a['precio'] <=> $b['precio']);

            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteGrupo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos para el menú'];
        }

        // Calcular descuento
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

    public function render()
    {
        return view('livewire.configuracion.promociones-especiales.wizard-promocion-especial');
    }
}
