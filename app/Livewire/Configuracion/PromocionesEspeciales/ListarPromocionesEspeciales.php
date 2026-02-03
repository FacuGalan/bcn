<?php

namespace App\Livewire\Configuracion\PromocionesEspeciales;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PromocionEspecial;
use App\Services\SucursalService;

class ListarPromocionesEspeciales extends Component
{
    use WithPagination;

    // Filtros
    public $busqueda = '';
    public $sucursalFiltro = '';
    public $tipoFiltro = ''; // nxm, combo
    public $activoFiltro = 'todos';
    public $vigenteFiltro = 'todos';

    // Colecciones para filtros
    public $sucursales = [];

    // Ordenamiento
    public $ordenarPor = 'prioridad';
    public $ordenDireccion = 'asc';

    // Toggle de filtros móvil
    public $showFilters = false;

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'sucursalFiltro' => ['except' => ''],
        'tipoFiltro' => ['except' => ''],
        'ordenarPor' => ['except' => 'prioridad'],
        'ordenDireccion' => ['except' => 'asc'],
    ];

    public function mount()
    {
        $this->sucursales = SucursalService::getSucursalesDisponibles();
    }

    public function updatingBusqueda()
    {
        $this->resetPage();
    }

    public function limpiarFiltros()
    {
        $this->reset([
            'busqueda',
            'sucursalFiltro',
            'tipoFiltro',
            'activoFiltro',
            'vigenteFiltro',
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
        $promocion = PromocionEspecial::find($promocionId);
        if ($promocion) {
            $promocion->activo = !$promocion->activo;
            $promocion->save();

            $mensaje = $promocion->activo ? __('Promoción activada') : __('Promoción desactivada');
            $this->js("window.notify('" . addslashes($mensaje) . "', 'success')");
        }
    }

    public function eliminar($promocionId)
    {
        $promocion = PromocionEspecial::find($promocionId);
        if ($promocion) {
            $promocion->delete();
            $this->js("window.notify('" . __('Promoción eliminada correctamente') . "', 'success')");
        }
    }

    public function duplicar($promocionId)
    {
        $original = PromocionEspecial::with(['grupos.articulos', 'escalas'])->find($promocionId);

        if ($original) {
            $copia = $original->replicate();
            $copia->nombre = $original->nombre . ' (copia)';
            $copia->activo = false;
            $copia->usos_actuales = 0;
            $copia->save();

            // Duplicar grupos con sus articulos
            foreach ($original->grupos as $grupo) {
                $nuevoGrupo = $copia->grupos()->create([
                    'nombre' => $grupo->nombre,
                    'cantidad' => $grupo->cantidad,
                    'es_trigger' => $grupo->es_trigger,
                    'es_reward' => $grupo->es_reward,
                    'orden' => $grupo->orden,
                ]);
                // Duplicar articulos del grupo
                $nuevoGrupo->articulos()->attach($grupo->articulos->pluck('id'));
            }

            // Duplicar escalas (para NxM)
            foreach ($original->escalas as $escala) {
                $copia->escalas()->create([
                    'cantidad_desde' => $escala->cantidad_desde,
                    'cantidad_hasta' => $escala->cantidad_hasta,
                    'lleva' => $escala->lleva,
                    'paga' => $escala->paga,
                ]);
            }

            $this->js("window.notify('" . __('Promoción duplicada correctamente') . "', 'success')");
        }
    }

    public function getPromociones()
    {
        $query = PromocionEspecial::query()
            ->with(['sucursal', 'articuloNxM', 'categoriaNxM', 'grupos.articulos', 'gruposTrigger', 'gruposReward', 'escalas']);

        // Filtrar por sucursales del usuario
        $sucursalesUsuario = $this->sucursales->pluck('id')->toArray();
        $query->whereIn('sucursal_id', $sucursalesUsuario);

        // Búsqueda
        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->busqueda . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->busqueda . '%');
            });
        }

        // Filtro por sucursal
        if ($this->sucursalFiltro) {
            $query->where('sucursal_id', $this->sucursalFiltro);
        }

        // Filtro por tipo
        if ($this->tipoFiltro) {
            $query->where('tipo', $this->tipoFiltro);
        }

        // Filtro activo
        if ($this->activoFiltro !== 'todos') {
            $query->where('activo', $this->activoFiltro === 'activos');
        }

        // Filtro vigencia
        if ($this->vigenteFiltro === 'vigentes') {
            $query->vigentes();
        } elseif ($this->vigenteFiltro === 'vencidas') {
            $query->where(function ($q) {
                $q->whereNotNull('vigencia_hasta')
                  ->where('vigencia_hasta', '<', now());
            });
        }

        // Ordenamiento
        $query->orderBy($this->ordenarPor, $this->ordenDireccion);

        return $query->paginate(15);
    }

    public function render()
    {
        return view('livewire.configuracion.promociones-especiales.listar-promociones-especiales', [
            'promociones' => $this->getPromociones(),
        ]);
    }
}
