<?php

namespace App\Livewire\Configuracion\Promociones;

use App\Models\Promocion;
use App\Traits\SucursalAware;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class ListarPromociones extends Component
{
    use SucursalAware, WithPagination;

    // Filtros
    public $busqueda = '';

    public $tipoFiltro = '';

    public $activoFiltro = 'todos';

    public $vigenteFiltro = 'todos'; // todos, vigentes, vencidas

    public $cuponFiltro = 'todos'; // todos, con_cupon, sin_cupon

    public $combinableFiltro = 'todos';

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="5" :rows="6" />
        HTML;
    }

    /*
     * Computed property (no pública) para evitar que arrays traducibles
     * queden en el snapshot Livewire y generen CorruptComponentPayload.
     */
    public function getTiposPromocionProperty(): array
    {
        return [
            'descuento_porcentaje' => __('Descuento %'),
            'descuento_monto' => __('Descuento $'),
            'precio_fijo' => __('Precio Fijo'),
            'recargo_porcentaje' => __('Recargo %'),
            'recargo_monto' => __('Recargo $'),
            'descuento_escalonado' => __('Descuento Escalonado'),
        ];
    }

    // Ordenamiento
    public $ordenarPor = 'prioridad';

    public $ordenDireccion = 'asc';

    // Toggle de filtros m�vil
    public $showFilters = false;

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'tipoFiltro' => ['except' => ''],
        'ordenarPor' => ['except' => 'prioridad'],
        'ordenDireccion' => ['except' => 'asc'],
    ];

    public function updatingBusqueda()
    {
        $this->resetPage();
    }

    public function limpiarFiltros()
    {
        $this->reset([
            'busqueda',
            'tipoFiltro',
            'activoFiltro',
            'vigenteFiltro',
            'cuponFiltro',
            'combinableFiltro',
        ]);
        $this->resetPage();
    }

    public function ordenar($campo)
    {
        if ($this->ordenarPor === $campo) {
            $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $campo;
            $this->ordenDireccion = 'asc';
        }
    }

    public function toggleActivo($promocionId)
    {
        $promocion = Promocion::find($promocionId);
        if ($promocion) {
            $promocion->activo = ! $promocion->activo;
            $promocion->save();

            $mensaje = $promocion->activo ? __('Promoción activada correctamente') : __('Promoción desactivada correctamente');
            $this->js("window.notify('".addslashes($mensaje)."', 'success')");
        }
    }

    public function eliminar($promocionId)
    {
        $promocion = Promocion::find($promocionId);
        if ($promocion) {
            // Soft delete - mantiene el registro con deleted_at para estadisticas
            $promocion->delete();

            $this->js("window.notify('".__('Promoción eliminada correctamente')."', 'success')");
        }
    }

    public function duplicar($promocionId)
    {
        $promocionOriginal = Promocion::with(['condiciones', 'escalas'])->find($promocionId);

        if ($promocionOriginal) {
            // Crear una copia de la promocion
            $nuevaPromocion = $promocionOriginal->replicate();
            $nuevaPromocion->nombre = $promocionOriginal->nombre.' (Copia)';
            $nuevaPromocion->codigo_cupon = null; // Los cupones deben ser unicos
            $nuevaPromocion->activo = false; // Crear inactiva por defecto
            $nuevaPromocion->usos_actuales = 0;
            $nuevaPromocion->save();

            // Duplicar condiciones
            foreach ($promocionOriginal->condiciones as $condicion) {
                $nuevaCondicion = $condicion->replicate();
                $nuevaCondicion->promocion_id = $nuevaPromocion->id;
                $nuevaCondicion->save();
            }

            // Duplicar escalas
            foreach ($promocionOriginal->escalas as $escala) {
                $nuevaEscala = $escala->replicate();
                $nuevaEscala->promocion_id = $nuevaPromocion->id;
                $nuevaEscala->save();
            }

            $this->js("window.notify('".__('Promoción duplicada correctamente')."', 'success')");
        }
    }

    public function render()
    {
        $sucursalId = sucursal_activa();

        $query = Promocion::query()
            ->with([
                'condiciones',
                'escalas',
            ])
            ->where('sucursal_id', $sucursalId);

        // Aplicar filtros
        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('descripcion', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('codigo_cupon', 'like', '%'.$this->busqueda.'%');
            });
        }

        if ($this->tipoFiltro) {
            $query->where('tipo', $this->tipoFiltro);
        }

        if ($this->activoFiltro !== 'todos') {
            $query->where('activo', $this->activoFiltro === 'activos');
        }

        if ($this->vigenteFiltro === 'vigentes') {
            $query->vigentes();
        } elseif ($this->vigenteFiltro === 'vencidas') {
            $query->where('vigencia_hasta', '<', now());
        }

        if ($this->cuponFiltro === 'con_cupon') {
            $query->conCupon();
        } elseif ($this->cuponFiltro === 'sin_cupon') {
            $query->automaticas();
        }

        if ($this->combinableFiltro !== 'todos') {
            $query->where('combinable', $this->combinableFiltro === 'combinables');
        }

        // Aplicar ordenamiento
        switch ($this->ordenarPor) {
            case 'prioridad':
                $query->orderBy('prioridad', $this->ordenDireccion);
                break;
            case 'nombre':
                $query->orderBy('nombre', $this->ordenDireccion);
                break;
            case 'vigencia':
                $query->orderBy('vigencia_desde', $this->ordenDireccion);
                break;
            case 'tipo':
                $query->orderBy('tipo', $this->ordenDireccion);
                break;
        }

        $promociones = $query->paginate(20);

        return view('livewire.configuracion.promociones.listar-promociones', [
            'promociones' => $promociones,
        ]);
    }

    /**
     * Obtiene el color del badge seg�n el tipo de promoci�n
     */
    public function getColorTipo($tipo)
    {
        return match ($tipo) {
            'descuento_porcentaje' => 'bg-green-100 text-green-800',
            'descuento_monto' => 'bg-blue-100 text-blue-800',
            'precio_fijo' => 'bg-purple-100 text-purple-800',
            'recargo_porcentaje' => 'bg-red-100 text-red-800',
            'recargo_monto' => 'bg-orange-100 text-orange-800',
            'descuento_escalonado' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Obtiene el �cono seg�n el tipo de promoci�n
     */
    public function getIconoTipo($tipo)
    {
        return match ($tipo) {
            'descuento_porcentaje' => 'heroicon-o-percent-badge',
            'descuento_monto' => 'heroicon-o-banknotes',
            'precio_fijo' => 'heroicon-o-tag',
            'recargo_porcentaje', 'recargo_monto' => 'heroicon-o-arrow-trending-up',
            'descuento_escalonado' => 'heroicon-o-chart-bar',
            default => 'heroicon-o-gift',
        };
    }
}
