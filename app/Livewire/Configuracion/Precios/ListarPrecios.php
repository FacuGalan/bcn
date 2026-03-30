<?php

namespace App\Livewire\Configuracion\Precios;

use App\Models\ListaPrecio;
use App\Traits\SucursalAware;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire para listar y gestionar listas de precios
 *
 * FASE 2 - Sistema de Listas de Precios
 */
#[Lazy]
class ListarPrecios extends Component
{
    use SucursalAware, WithPagination;

    // Filtros
    public $busqueda = '';

    public $activoFiltro = 'todos';

    public $esListaBaseFiltro = '';

    // Ordenamiento
    public $ordenarPor = 'prioridad';

    public $ordenDireccion = 'asc';

    // Toggle de filtros móvil
    public $showFilters = false;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;

    public ?int $listaAEliminar = null;

    public ?string $nombreListaAEliminar = null;

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'ordenarPor' => ['except' => 'prioridad'],
        'ordenDireccion' => ['except' => 'asc'],
    ];

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="5" :rows="6" />
        HTML;
    }

    public function updatingBusqueda()
    {
        $this->resetPage();
    }

    public function limpiarFiltros()
    {
        $this->reset([
            'busqueda',
            'activoFiltro',
            'esListaBaseFiltro',
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

    public function toggleActivo($listaId)
    {
        $lista = ListaPrecio::find($listaId);
        if ($lista) {
            // No permitir desactivar la lista base
            if ($lista->es_lista_base && $lista->activo) {
                $this->js("window.notify('".__('No se puede desactivar la lista base')."', 'error')");

                return;
            }

            $lista->activo = ! $lista->activo;
            $lista->save();

            $mensaje = $lista->activo ? __('Lista activada correctamente') : __('Lista desactivada correctamente');
            $this->js("window.notify('".addslashes($mensaje)."', 'success')");
        }
    }

    /**
     * Abre el modal de confirmación de eliminación
     */
    public function confirmarEliminar($listaId)
    {
        $lista = ListaPrecio::find($listaId);
        if ($lista) {
            // No permitir eliminar la lista base
            if ($lista->es_lista_base) {
                $this->js("window.notify('".__('No se puede eliminar la lista base')."', 'error')");

                return;
            }

            $this->listaAEliminar = $lista->id;
            $this->nombreListaAEliminar = $lista->nombre;
            $this->showDeleteModal = true;
        }
    }

    /**
     * Cierra el modal de confirmación
     */
    public function cancelarEliminar()
    {
        $this->showDeleteModal = false;
        $this->listaAEliminar = null;
        $this->nombreListaAEliminar = null;
    }

    /**
     * Ejecuta la eliminación después de confirmar
     */
    public function eliminar()
    {
        if (! $this->listaAEliminar) {
            return;
        }

        $lista = ListaPrecio::find($this->listaAEliminar);
        if ($lista) {
            // Doble verificación: no permitir eliminar la lista base
            if ($lista->es_lista_base) {
                $this->js("window.notify('".__('No se puede eliminar la lista base')."', 'error')");
                $this->cancelarEliminar();

                return;
            }

            $lista->delete(); // Soft delete

            $this->js("window.notify('".__('Lista eliminada correctamente')."', 'success')");
        }

        $this->cancelarEliminar();
    }

    public function duplicar($listaId)
    {
        $original = ListaPrecio::with(['condiciones', 'articulos'])->find($listaId);
        if (! $original) {
            $this->js("window.notify('".__('Lista no encontrada')."', 'error')");

            return;
        }

        // Crear copia de la lista
        $nueva = $original->replicate();
        $nueva->nombre = $original->nombre.' (Copia)';
        $nueva->codigo = null; // Se debe asignar un nuevo código
        $nueva->es_lista_base = false;
        $nueva->prioridad = $original->prioridad + 1;
        $nueva->save();

        // Copiar condiciones
        foreach ($original->condiciones as $condicion) {
            $nuevaCondicion = $condicion->replicate();
            $nuevaCondicion->lista_precio_id = $nueva->id;
            $nuevaCondicion->save();
        }

        // Copiar artículos
        foreach ($original->articulos as $articulo) {
            $nuevoArticulo = $articulo->replicate();
            $nuevoArticulo->lista_precio_id = $nueva->id;
            $nuevoArticulo->save();
        }

        $this->js("window.notify('".__('Lista duplicada correctamente')."', 'success')");
    }

    public function render()
    {
        $sucursalId = sucursal_activa();

        $query = ListaPrecio::query()
            ->where('sucursal_id', $sucursalId)
            ->withCount(['condiciones', 'articulos']);

        // Aplicar filtros
        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('codigo', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('descripcion', 'like', '%'.$this->busqueda.'%');
            });
        }

        if ($this->activoFiltro !== 'todos') {
            $query->where('activo', $this->activoFiltro === 'activos');
        }

        if ($this->esListaBaseFiltro !== '') {
            $query->where('es_lista_base', $this->esListaBaseFiltro === 'si');
        }

        // Aplicar ordenamiento
        switch ($this->ordenarPor) {
            case 'nombre':
                $query->orderBy('nombre', $this->ordenDireccion);
                break;
            case 'ajuste':
                $query->orderBy('ajuste_porcentaje', $this->ordenDireccion);
                break;
            case 'prioridad':
            default:
                $query->orderBy('prioridad', $this->ordenDireccion);
                break;
        }

        $listas = $query->paginate(20);

        return view('livewire.configuracion.precios.listar-precios', [
            'listas' => $listas,
        ]);
    }
}
