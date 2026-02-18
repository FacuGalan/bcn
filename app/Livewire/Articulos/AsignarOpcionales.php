<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\GrupoOpcional;
use App\Services\OpcionalService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para asignar grupos opcionales a artículos.
 *
 * Muestra el listado de artículos con la cantidad de grupos asignados.
 * Al hacer click en un artículo, abre un modal para gestionar sus grupos:
 * - Ver grupos asignados con sus opciones
 * - Agregar grupo (crea para todas las sucursales)
 * - Quitar grupo (elimina de todas las sucursales)
 */
#[Layout('layouts.app')]
class AsignarOpcionales extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filterAsignacion = 'all'; // all, con_grupos, sin_grupos

    // Modal asignación
    public bool $showModal = false;
    public ?int $articuloId = null;
    public string $articuloNombre = '';
    public array $gruposAsignados = [];

    // Panel inline agregar grupo (dentro del modal principal)
    public bool $mostrandoAgregarGrupo = false;
    public string $busquedaGrupo = '';

    // Submodal confirmar desasignación
    public bool $showDesasignarModal = false;
    public ?int $grupoADesasignar = null;
    public ?string $nombreGrupoADesasignar = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAsignacion(): void
    {
        $this->resetPage();
    }

    protected function getArticulos()
    {
        $sucursalId = sucursal_activa();

        $query = Articulo::with(['categoriaModel'])
            ->withCount(['gruposOpcionales as grupos_count' => function ($q) use ($sucursalId) {
                if ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                }
            }])
            ->where('activo', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->search . '%')
                  ->orWhere('nombre', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterAsignacion === 'con_grupos') {
            $query->whereHas('gruposOpcionales', function ($q) use ($sucursalId) {
                if ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                }
            });
        } elseif ($this->filterAsignacion === 'sin_grupos') {
            $query->whereDoesntHave('gruposOpcionales', function ($q) use ($sucursalId) {
                if ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                }
            });
        }

        return $query->orderBy('nombre')->paginate(15);
    }

    public function gestionarArticulo(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $this->articuloId = $articulo->id;
        $this->articuloNombre = $articulo->nombre;

        $this->cargarGruposAsignados();
        $this->showModal = true;
    }

    protected function cargarGruposAsignados(): void
    {
        if (!$this->articuloId) return;

        $sucursalId = sucursal_activa();

        // Obtener grupos asignados con opcionales del catálogo global (no de la sucursal)
        $asignaciones = ArticuloGrupoOpcional::with([
                'grupoOpcional.opcionales' => fn($q) => $q->where('activo', true)->orderBy('orden'),
            ])
            ->where('articulo_id', $this->articuloId)
            ->where('sucursal_id', $sucursalId)
            ->orderBy('orden')
            ->get();

        $this->gruposAsignados = $asignaciones->map(function ($asig) {
            return [
                'id' => $asig->id,
                'grupo_id' => $asig->grupo_opcional_id,
                'nombre' => $asig->grupoOpcional->nombre,
                'tipo' => $asig->grupoOpcional->tipo,
                'obligatorio' => $asig->grupoOpcional->obligatorio,
                'activo' => $asig->activo,
                'orden' => $asig->orden,
                'opciones' => $asig->grupoOpcional->opcionales->map(fn($op) => [
                    'id' => $op->id,
                    'nombre' => $op->nombre,
                    'precio_extra' => $op->precio_extra,
                ])->toArray(),
            ];
        })->toArray();
    }

    public function abrirAgregarGrupo(): void
    {
        $this->busquedaGrupo = '';
        $this->mostrandoAgregarGrupo = true;
    }

    public function getGruposDisponiblesProperty(): array
    {
        $gruposYaAsignados = collect($this->gruposAsignados)->pluck('grupo_id')->toArray();

        $query = GrupoOpcional::where('activo', true)
            ->whereNotIn('id', $gruposYaAsignados)
            ->withCount(['opcionales' => fn($q) => $q->where('activo', true)]);

        if ($this->busquedaGrupo) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->busquedaGrupo . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->busquedaGrupo . '%');
            });
        }

        return $query->orderBy('nombre')->limit(20)->get()->map(fn($g) => [
            'id' => $g->id,
            'nombre' => $g->nombre,
            'tipo' => $g->tipo,
            'obligatorio' => $g->obligatorio,
            'opcionales_count' => $g->opcionales_count,
        ])->toArray();
    }

    public function asignarGrupo(int $grupoId): void
    {
        if (!$this->articuloId) return;

        $service = app(OpcionalService::class);
        $count = $service->asignarGrupoAArticulo($this->articuloId, $grupoId);

        $grupo = GrupoOpcional::find($grupoId);
        $nombre = $grupo ? $grupo->nombre : '';

        $this->dispatch('notify',
            message: __('Grupo ":nombre" asignado en :count sucursales', ['nombre' => $nombre, 'count' => $count]),
            type: 'success'
        );

        $this->mostrandoAgregarGrupo = false;
        $this->cargarGruposAsignados();
    }

    /**
     * Mueve un grupo una posición arriba (intercambia orden con el anterior).
     * Aplica el cambio en TODAS las sucursales.
     */
    public function moverGrupoArriba(int $index): void
    {
        if ($index <= 0 || !$this->articuloId) return;

        $grupoActual = $this->gruposAsignados[$index];
        $grupoAnterior = $this->gruposAsignados[$index - 1];

        $this->intercambiarOrden($grupoActual['grupo_id'], $grupoAnterior['grupo_id']);
        $this->cargarGruposAsignados();
    }

    /**
     * Mueve un grupo una posición abajo (intercambia orden con el siguiente).
     * Aplica el cambio en TODAS las sucursales.
     */
    public function moverGrupoAbajo(int $index): void
    {
        if ($index >= count($this->gruposAsignados) - 1 || !$this->articuloId) return;

        $grupoActual = $this->gruposAsignados[$index];
        $grupoSiguiente = $this->gruposAsignados[$index + 1];

        $this->intercambiarOrden($grupoActual['grupo_id'], $grupoSiguiente['grupo_id']);
        $this->cargarGruposAsignados();
    }

    /**
     * Intercambia el orden de dos grupos en TODAS las sucursales del artículo.
     * Primero normaliza los valores de orden para evitar duplicados.
     */
    protected function intercambiarOrden(int $grupoIdA, int $grupoIdB): void
    {
        // Normalizar: asignar orden secuencial basado en el orden actual
        $sucursalId = sucursal_activa();
        $asignaciones = ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
            ->where('sucursal_id', $sucursalId)
            ->orderBy('orden')
            ->get();

        foreach ($asignaciones as $i => $asig) {
            if ($asig->orden !== $i) {
                ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
                    ->where('grupo_opcional_id', $asig->grupo_opcional_id)
                    ->update(['orden' => $i]);
            }
        }

        // Ahora intercambiar
        $ordenA = ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
            ->where('grupo_opcional_id', $grupoIdA)
            ->where('sucursal_id', $sucursalId)
            ->value('orden');

        $ordenB = ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
            ->where('grupo_opcional_id', $grupoIdB)
            ->where('sucursal_id', $sucursalId)
            ->value('orden');

        ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
            ->where('grupo_opcional_id', $grupoIdA)
            ->update(['orden' => $ordenB]);

        ArticuloGrupoOpcional::where('articulo_id', $this->articuloId)
            ->where('grupo_opcional_id', $grupoIdB)
            ->update(['orden' => $ordenA]);
    }

    public function confirmarDesasignar(int $grupoId, string $nombre): void
    {
        $this->grupoADesasignar = $grupoId;
        $this->nombreGrupoADesasignar = $nombre;
        $this->showDesasignarModal = true;
    }

    public function desasignarGrupo(): void
    {
        if (!$this->articuloId || !$this->grupoADesasignar) return;

        $service = app(OpcionalService::class);
        $service->desasignarGrupoDeArticulo($this->articuloId, $this->grupoADesasignar);

        $this->dispatch('notify', message: __('Grupo desasignado correctamente'), type: 'success');
        $this->showDesasignarModal = false;
        $this->grupoADesasignar = null;
        $this->nombreGrupoADesasignar = null;
        $this->cargarGruposAsignados();
    }

    public function cancelarDesasignar(): void
    {
        $this->showDesasignarModal = false;
        $this->grupoADesasignar = null;
        $this->nombreGrupoADesasignar = null;
    }

    public function cancelarModal(): void
    {
        $this->showModal = false;
        $this->mostrandoAgregarGrupo = false;
        $this->articuloId = null;
        $this->articuloNombre = '';
        $this->gruposAsignados = [];
    }

    public function cancelarAgregarGrupo(): void
    {
        $this->mostrandoAgregarGrupo = false;
        $this->busquedaGrupo = '';
    }

    public function render()
    {
        return view('livewire.articulos.asignar-opcionales', [
            'articulos' => $this->getArticulos(),
        ]);
    }
}
