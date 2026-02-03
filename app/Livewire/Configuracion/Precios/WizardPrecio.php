<?php

namespace App\Livewire\Configuracion\Precios;

use Livewire\Component;
use App\Models\PrecioBase;
use App\Models\Sucursal;
use App\Models\Articulo;
use App\Models\FormaVenta;
use App\Models\CanalVenta;

class WizardPrecio extends Component
{
    // Control del wizard
    public $pasoActual = 1;
    public $totalPasos = 3;

    // Paso 1: Artículo
    public $articuloId = null;
    public $busquedaArticulo = '';

    // Paso 2: Contexto
    public $sucursalesSeleccionadas = [];
    public $formaVentaId = null;
    public $canalVentaId = null;

    // Paso 3: Precio y vigencia
    public $precio = null;
    public $vigenciaDesde = null;
    public $vigenciaHasta = null;
    public $activo = true;

    // Colecciones
    public $articulos = [];
    public $sucursales = [];
    public $formasVenta = [];
    public $canalesVenta = [];

    // Conflictos
    public $preciosConflictivos = [];

    protected $rules = [
        'articuloId' => 'required',
        'sucursalesSeleccionadas' => 'required|array|min:1',
        'precio' => 'required|numeric|min:0',
    ];

    protected function messages()
    {
        return [
            'articuloId.required' => __('Debes seleccionar un artículo'),
            'sucursalesSeleccionadas.required' => __('Debes seleccionar al menos una sucursal'),
            'sucursalesSeleccionadas.min' => __('Debes seleccionar al menos una sucursal'),
            'precio.required' => __('Debes ingresar un precio'),
            'precio.numeric' => __('El precio debe ser un número'),
            'precio.min' => __('El precio debe ser mayor o igual a 0'),
        ];
    }

    public function mount()
    {
        // Cargar colecciones iniciales
        $this->sucursales = Sucursal::select('id', 'nombre')->orderBy('nombre')->get();
        $this->formasVenta = FormaVenta::activas()->get();
        $this->canalesVenta = CanalVenta::activos()->get();
    }

    public function updatedBusquedaArticulo($value)
    {
        if (strlen($value) >= 2) {
            $this->articulos = Articulo::where(function($q) use ($value) {
                $q->where('nombre', 'like', '%' . $value . '%')
                  ->orWhere('codigo', 'like', '%' . $value . '%');
            })
            ->limit(20)
            ->get();
        } else {
            $this->articulos = [];
        }
    }

    public function seleccionarArticulo($articuloId)
    {
        $this->articuloId = $articuloId;
        $articulo = Articulo::find($articuloId);
        $this->busquedaArticulo = $articulo ? $articulo->nombre : '';
        $this->articulos = [];
    }

    public function siguiente()
    {
        // Validar paso actual antes de avanzar
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
        if ($paso <= $this->pasoActual && $paso >= 1) {
            $this->pasoActual = $paso;
        }
    }

    private function validarPasoActual()
    {
        switch ($this->pasoActual) {
            case 1:
                if (!$this->articuloId) {
                    session()->flash('error', __('Debes seleccionar un artículo'));
                    return false;
                }
                break;

            case 2:
                if (empty($this->sucursalesSeleccionadas)) {
                    session()->flash('error', __('Debes seleccionar al menos una sucursal'));
                    return false;
                }
                break;

            case 3:
                $this->validate();
                // Verificar conflictos al llegar al paso 3
                $this->verificarConflictos();
                break;
        }

        return true;
    }

    /**
     * Verifica si hay precios que se solaparían con el nuevo precio
     * Solo detecta conflictos cuando la especificidad es IDÉNTICA
     */
    public function verificarConflictos()
    {
        $this->preciosConflictivos = [];

        foreach ($this->sucursalesSeleccionadas as $sucursalId) {
            $query = PrecioBase::where('articulo_id', $this->articuloId)
                ->where('sucursal_id', $sucursalId);

            // Verificar especificidad EXACTA (no jerárquica)
            // Solo es conflicto si forma_venta Y canal_venta son exactamente iguales
            if ($this->formaVentaId) {
                $query->where('forma_venta_id', $this->formaVentaId);
            } else {
                $query->whereNull('forma_venta_id');
            }

            if ($this->canalVentaId) {
                $query->where('canal_venta_id', $this->canalVentaId);
            } else {
                $query->whereNull('canal_venta_id');
            }

            // Verificar solapamiento de fechas
            if ($this->vigenciaDesde || $this->vigenciaHasta) {
                $query->where(function($q) {
                    $q->where(function($sq) {
                        // Caso 1: El precio existente NO tiene vigencia (permanente)
                        $sq->whereNull('vigencia_desde')
                           ->whereNull('vigencia_hasta');
                    })
                    ->orWhere(function($sq) {
                        // Caso 2: Hay solapamiento de fechas
                        if ($this->vigenciaDesde && $this->vigenciaHasta) {
                            // El nuevo precio tiene inicio Y fin
                            $sq->where(function($ssq) {
                                $ssq->where(function($sssq) {
                                    // El existente empieza dentro del rango nuevo
                                    $sssq->where('vigencia_desde', '>=', $this->vigenciaDesde)
                                         ->where('vigencia_desde', '<=', $this->vigenciaHasta);
                                })
                                ->orWhere(function($sssq) {
                                    // El existente termina dentro del rango nuevo
                                    $sssq->where('vigencia_hasta', '>=', $this->vigenciaDesde)
                                         ->where('vigencia_hasta', '<=', $this->vigenciaHasta);
                                })
                                ->orWhere(function($sssq) {
                                    // El existente contiene al rango nuevo
                                    $sssq->where('vigencia_desde', '<=', $this->vigenciaDesde)
                                         ->where('vigencia_hasta', '>=', $this->vigenciaHasta);
                                });
                            });
                        } elseif ($this->vigenciaDesde) {
                            // Solo tiene fecha de inicio (precio permanente desde esa fecha)
                            $sq->where(function($ssq) {
                                $ssq->whereNull('vigencia_hasta')
                                    ->orWhere('vigencia_hasta', '>=', $this->vigenciaDesde);
                            });
                        } elseif ($this->vigenciaHasta) {
                            // Solo tiene fecha de fin (precio permanente hasta esa fecha)
                            $sq->where(function($ssq) {
                                $ssq->whereNull('vigencia_desde')
                                    ->orWhere('vigencia_desde', '<=', $this->vigenciaHasta);
                            });
                        }
                    });
                });
            } else {
                // El nuevo precio es permanente, se solapa con cualquier precio existente
                // No agregar condición de fecha
            }

            $conflictos = $query->with(['sucursal', 'formaVenta', 'canalVenta'])->get();

            if ($conflictos->count() > 0) {
                foreach ($conflictos as $conflicto) {
                    $this->preciosConflictivos[] = $conflicto;
                }
            }
        }
    }

    public function guardar()
    {
        $this->validate();

        // Verificar conflictos una última vez
        $this->verificarConflictos();

        if (count($this->preciosConflictivos) > 0) {
            $this->js("window.notify('" . addslashes(__('No se puede crear el precio porque hay conflictos con precios existentes. Por favor, revisa la sección de advertencias.')) . "', 'error', 7000)");
            return;
        }

        try {
            $preciosCreados = 0;

            // Crear un precio por cada sucursal seleccionada
            foreach ($this->sucursalesSeleccionadas as $sucursalId) {
                $precio = new PrecioBase();
                $precio->sucursal_id = $sucursalId;
                $precio->articulo_id = $this->articuloId;
                $precio->forma_venta_id = $this->formaVentaId;
                $precio->canal_venta_id = $this->canalVentaId;
                $precio->precio = $this->precio;
                $precio->vigencia_desde = $this->vigenciaDesde;
                $precio->vigencia_hasta = $this->vigenciaHasta;
                $precio->activo = $this->activo;

                $precio->save();
                $preciosCreados++;
            }

            $mensaje = $preciosCreados === 1
                ? __('Precio creado correctamente')
                : __('Se crearon :count precios correctamente', ['count' => $preciosCreados]);

            session()->flash('notify', [
                'message' => $mensaje,
                'type' => 'success'
            ]);

            return redirect()->route('configuracion.precios');

        } catch (\Exception $e) {
            \Log::error('Error al crear precio: ' . $e->getMessage());

            $errorMsg = addslashes($e->getMessage());
            $this->js("window.notify('" . addslashes(__('Error al crear precio: ') . $e->getMessage()) . "', 'error', 7000)");
        }
    }

    public function render()
    {
        return view('livewire.configuracion.precios.wizard-precio');
    }
}
