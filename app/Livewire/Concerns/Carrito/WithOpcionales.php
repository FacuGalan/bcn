<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;

/**
 * Wizard de opcionales en NuevaVenta.
 *
 * Encapsula:
 * - Estado del wizard (articulo, grupos, paso actual, selecciones, indice de edicion).
 * - Toggle/cantidad de opciones por grupo (con auto-avance al alcanzar max_seleccion).
 * - Navegacion (avanzar/retroceder/saltar/cerrar).
 * - Confirmacion: agrega item al carrito con opcionales seleccionados, o actualiza
 *   un item existente si se esta editando.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->cantidadAgregar             (WithCarritoItems)
 * - $this->items                       (WithCarritoItems)
 * - $this->verificarStockAlAgregar()   (WithCarritoItems)
 * - $this->opcionalService             (NuevaVenta)
 * - $this->sucursalId                  (SucursalAware)
 * - $this->descuentoGeneralActivo, $this->descuentoGeneralTipo,
 *   $this->descuentoGeneralValor      (NuevaVenta — iran a WithDescuentos)
 * - $this->calcularVenta()             (NuevaVenta — ira a WithCalculoVenta)
 */
trait WithOpcionales
{
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
    // SELECCION DE OPCIONES
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

    // =========================================
    // NAVEGACION DEL WIZARD
    // =========================================

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

    // =========================================
    // CONFIRMAR / EDITAR / CERRAR
    // =========================================

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
                'ajuste_manual_origen' => null,
                'precio_sin_ajuste_manual' => null,
                'opcionales' => $opcionalesItem,
                'precio_opcionales' => $precioOpcionalesTotal,
                // Canje por puntos (RF-25)
                'puntos_canje' => $data['puntos_canje'] ?? null,
                'pagado_con_puntos' => false,
            ];

            // RF-34: Herencia de descuento general % a items nuevos.
            // Excepción: items bonificados por un cupón ya aplicado NO heredan
            // (el cupón tiene prioridad sobre el descuento general).
            $estaBonificadoPorCupon = $this->cuponAplicado
                && in_array($data['articulo_id'] ?? null, $this->cuponArticulosBonificados ?? []);

            if ($this->descuentoGeneralActivo
                && $this->descuentoGeneralTipo === 'porcentaje'
                && ! $estaBonificadoPorCupon) {
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
                $this->items[$lastIndex]['ajuste_manual_origen'] = 'descuento_general';
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
}
