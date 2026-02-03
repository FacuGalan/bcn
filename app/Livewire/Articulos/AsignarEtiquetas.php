<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\Etiqueta;
use App\Models\GrupoEtiqueta;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente para asignar etiquetas a artículos de forma masiva
 *
 * Dos modos de operación:
 * - etiqueta_a_articulos: Seleccionar una etiqueta y asignarla a múltiples artículos
 * - articulo_a_etiquetas: Seleccionar un artículo y asignarle múltiples etiquetas
 */
#[Layout('layouts.app')]
class AsignarEtiquetas extends Component
{
    use WithPagination;

    // Modo de operación
    public string $modo = 'etiqueta_a_articulos';

    // Búsqueda
    public string $busquedaArticulo = '';
    public string $busquedaEtiqueta = '';

    // Modo: Etiqueta a Artículos
    public ?int $etiquetaSeleccionada = null;
    public array $articulosSeleccionados = [];

    // Modo: Artículo a Etiquetas
    public ?int $articuloSeleccionado = null;
    public array $etiquetasSeleccionadas = [];

    // Para mostrar info
    public ?string $nombreEtiquetaSeleccionada = null;
    public ?string $grupoEtiquetaSeleccionada = null;
    public ?string $colorEtiquetaSeleccionada = null;
    public ?string $nombreArticuloSeleccionado = null;

    public function updatingBusquedaArticulo()
    {
        $this->resetPage('articulosPage');
    }

    public function updatingBusquedaEtiqueta()
    {
        $this->resetPage('etiquetasPage');
    }

    /**
     * Cambia el modo de operación
     */
    public function cambiarModo(string $modo): void
    {
        $this->modo = $modo;
        $this->reset([
            'etiquetaSeleccionada', 'articulosSeleccionados',
            'articuloSeleccionado', 'etiquetasSeleccionadas',
            'nombreEtiquetaSeleccionada', 'grupoEtiquetaSeleccionada',
            'colorEtiquetaSeleccionada', 'nombreArticuloSeleccionado',
            'busquedaArticulo', 'busquedaEtiqueta'
        ]);
    }

    /**
     * Vuelve al listado de etiquetas
     */
    public function volver()
    {
        return redirect()->route('articulos.etiquetas');
    }

    // ==================== Modo: Etiqueta a Artículos ====================

    /**
     * Selecciona una etiqueta para asignar a artículos
     */
    public function seleccionarEtiqueta(int $etiquetaId): void
    {
        $etiqueta = Etiqueta::with('grupo')->find($etiquetaId);
        if ($etiqueta) {
            $this->etiquetaSeleccionada = $etiqueta->id;
            $this->nombreEtiquetaSeleccionada = $etiqueta->nombre;
            $this->grupoEtiquetaSeleccionada = $etiqueta->grupo->nombre;
            $this->colorEtiquetaSeleccionada = $etiqueta->color ?? $etiqueta->grupo->color;

            // Cargar artículos que ya tienen esta etiqueta
            $this->articulosSeleccionados = $etiqueta->articulos()->pluck('articulos.id')->toArray();
        }
    }

    /**
     * Limpia la etiqueta seleccionada
     */
    public function limpiarEtiqueta(): void
    {
        $this->etiquetaSeleccionada = null;
        $this->nombreEtiquetaSeleccionada = null;
        $this->grupoEtiquetaSeleccionada = null;
        $this->colorEtiquetaSeleccionada = null;
        $this->articulosSeleccionados = [];
    }

    /**
     * Toggle selección de artículo
     */
    public function toggleArticulo(int $articuloId): void
    {
        if (in_array($articuloId, $this->articulosSeleccionados)) {
            $this->articulosSeleccionados = array_values(array_diff($this->articulosSeleccionados, [$articuloId]));
        } else {
            $this->articulosSeleccionados[] = $articuloId;
        }
    }

    /**
     * Selecciona todos los artículos visibles
     */
    public function seleccionarTodosArticulos(): void
    {
        $articulos = $this->getArticulosQuery()->pluck('id')->toArray();
        $this->articulosSeleccionados = array_unique(array_merge($this->articulosSeleccionados, $articulos));
    }

    /**
     * Deselecciona todos los artículos
     */
    public function deseleccionarTodosArticulos(): void
    {
        $this->articulosSeleccionados = [];
    }

    /**
     * Guarda la asignación de etiqueta a artículos
     */
    public function guardarEtiquetaArticulos(): void
    {
        if (!$this->etiquetaSeleccionada) {
            $this->js("window.notify('" . __('Selecciona una etiqueta primero') . "', 'error')");
            return;
        }

        $etiqueta = Etiqueta::find($this->etiquetaSeleccionada);
        if (!$etiqueta) {
            return;
        }

        // Sincronizar artículos con la etiqueta
        $etiqueta->articulos()->sync($this->articulosSeleccionados);

        $count = count($this->articulosSeleccionados);
        $this->js("window.notify('" . __('Etiqueta asignada a :count artículo(s)', ['count' => $count]) . "', 'success')");
    }

    // ==================== Modo: Artículo a Etiquetas ====================

    /**
     * Selecciona un artículo para asignarle etiquetas
     */
    public function seleccionarArticulo(int $articuloId): void
    {
        $articulo = Articulo::find($articuloId);
        if ($articulo) {
            $this->articuloSeleccionado = $articulo->id;
            $this->nombreArticuloSeleccionado = $articulo->nombre;

            // Cargar etiquetas que ya tiene el artículo
            $this->etiquetasSeleccionadas = $articulo->etiquetas()->pluck('etiquetas.id')->toArray();
        }
    }

    /**
     * Limpia el artículo seleccionado
     */
    public function limpiarArticulo(): void
    {
        $this->articuloSeleccionado = null;
        $this->nombreArticuloSeleccionado = null;
        $this->etiquetasSeleccionadas = [];
    }

    /**
     * Toggle selección de etiqueta
     */
    public function toggleEtiqueta(int $etiquetaId): void
    {
        if (in_array($etiquetaId, $this->etiquetasSeleccionadas)) {
            $this->etiquetasSeleccionadas = array_values(array_diff($this->etiquetasSeleccionadas, [$etiquetaId]));
        } else {
            $this->etiquetasSeleccionadas[] = $etiquetaId;
        }
    }

    /**
     * Guarda la asignación de etiquetas al artículo
     */
    public function guardarArticuloEtiquetas(): void
    {
        if (!$this->articuloSeleccionado) {
            $this->js("window.notify('" . __('Selecciona un artículo primero') . "', 'error')");
            return;
        }

        $articulo = Articulo::find($this->articuloSeleccionado);
        if (!$articulo) {
            return;
        }

        // Sincronizar etiquetas con el artículo
        $articulo->etiquetas()->sync($this->etiquetasSeleccionadas);

        $count = count($this->etiquetasSeleccionadas);
        $this->js("window.notify('" . __(':count etiqueta(s) asignada(s) al artículo', ['count' => $count]) . "', 'success')");
    }

    // ==================== Queries ====================

    /**
     * Obtiene los artículos filtrados
     */
    protected function getArticulosQuery()
    {
        $query = Articulo::where('activo', true);

        if ($this->busquedaArticulo) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->busquedaArticulo . '%')
                  ->orWhere('nombre', 'like', '%' . $this->busquedaArticulo . '%');
            });
        }

        return $query->orderBy('nombre');
    }

    /**
     * Obtiene los grupos con sus etiquetas
     */
    protected function getGruposEtiquetas()
    {
        $query = GrupoEtiqueta::with(['etiquetas' => function ($q) {
            $q->where('activo', true)->orderBy('orden')->orderBy('nombre');

            if ($this->busquedaEtiqueta) {
                $q->where('nombre', 'like', '%' . $this->busquedaEtiqueta . '%');
            }
        }])
        ->where('activo', true)
        ->orderBy('orden')
        ->orderBy('nombre');

        if ($this->busquedaEtiqueta) {
            $query->whereHas('etiquetas', function ($q) {
                $q->where('activo', true)
                  ->where('nombre', 'like', '%' . $this->busquedaEtiqueta . '%');
            });
        }

        return $query->get();
    }

    public function render()
    {
        return view('livewire.articulos.asignar-etiquetas', [
            'articulos' => $this->getArticulosQuery()->paginate(15, ['*'], 'articulosPage'),
            'gruposEtiquetas' => $this->getGruposEtiquetas(),
        ]);
    }
}
