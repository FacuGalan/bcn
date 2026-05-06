<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use App\Models\GrupoEtiqueta;

/**
 * Búsqueda de artículos en NuevaVenta.
 *
 * Encapsula:
 * - Búsqueda inteligente en sidebar (palabras separadas, OR sobre nombre/código/cód. barras/categoría).
 * - Filtrado por sucursal activa (artículos vinculados a sucursal o sin vinculación).
 * - Modal de búsqueda extendida con filtros por etiquetas y grupos de etiquetas.
 *
 * Dependencias externas (resueltas vía $this-> desde el componente que use el trait):
 * - $this->sucursalId           (NuevaVenta / SucursalAware)
 * - $this->seleccionarArticulo() (NuevaVenta — dispatcher segun modo activo)
 */
trait WithBusquedaArticulos
{
    // =========================================
    // PROPIEDADES DE BÚSQUEDA EN SIDEBAR
    // =========================================

    /** @var string Búsqueda de artículos */
    public $busquedaArticulo = '';

    /** @var array Artículos encontrados en la búsqueda */
    public $articulosResultados = [];

    // =========================================
    // PROPIEDADES DE MODAL BÚSQUEDA ARTÍCULOS
    // =========================================

    public bool $mostrarModalBusquedaArticulos = false;

    public string $busquedaArticuloModal = '';

    public array $articulosModalResultados = [];

    public array $etiquetasModalSeleccionadas = [];

    public $gruposEtiquetasModal = [];

    // =========================================
    // BÚSQUEDA EN SIDEBAR
    // =========================================

    public function updatedBusquedaArticulo($value)
    {
        $value = trim($value);

        if (empty($value)) {
            $this->articulosResultados = [];

            return;
        }

        $this->cargarArticulosResultados($value);
    }

    protected function cargarArticulosResultados(string $busqueda): void
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        // Separar la búsqueda en palabras individuales para búsqueda inteligente
        $palabras = preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY);

        // Cada palabra debe coincidir en nombre, código, código de barras O nombre de categoría
        foreach ($palabras as $palabra) {
            $query->where(function ($q) use ($palabra) {
                $q->where('nombre', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$palabra.'%')
                    ->orWhereHas('categoriaModel', function ($subQ) use ($palabra) {
                        $subQ->where('nombre', 'like', '%'.$palabra.'%');
                    });
            });
        }

        // Filtrar por sucursal si hay artículos habilitados por sucursal
        if ($this->sucursalId) {
            $query->where(function ($q) {
                $q->whereHas('sucursales', function ($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                        ->where('articulos_sucursales.activo', 1);
                })->orWhereDoesntHave('sucursales');
            });
        }

        $articulos = $query->orderBy('nombre')->limit(15)->get();

        $this->articulosResultados = $articulos->map(function ($art) {
            return [
                'id' => $art->id,
                'nombre' => $art->nombre,
                'codigo' => $art->codigo,
                'codigo_barras' => $art->codigo_barras,
                'categoria_id' => $art->categoria_id,
                'categoria_nombre' => $art->categoriaModel?->nombre,
            ];
        })->toArray();
    }

    // =========================================
    // MODAL DE BÚSQUEDA DE ARTÍCULOS
    // =========================================

    public function abrirModalBusquedaArticulos(): void
    {
        $this->busquedaArticuloModal = '';
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = GrupoEtiqueta::where('activo', true)
            ->with(['etiquetas' => fn ($q) => $q->where('activo', true)->orderBy('orden')->orderBy('nombre')])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
        $this->cargarArticulosModal();
        $this->mostrarModalBusquedaArticulos = true;
    }

    public function cerrarModalBusquedaArticulos(): void
    {
        $this->mostrarModalBusquedaArticulos = false;
        $this->busquedaArticuloModal = '';
        $this->articulosModalResultados = [];
        $this->etiquetasModalSeleccionadas = [];
        $this->gruposEtiquetasModal = [];
        $this->dispatch('focus-busqueda');
    }

    public function updatedBusquedaArticuloModal(): void
    {
        $this->cargarArticulosModal();
    }

    public function updatedEtiquetasModalSeleccionadas(): void
    {
        $this->cargarArticulosModal();
    }

    protected function cargarArticulosModal(): void
    {
        $query = Articulo::with('categoriaModel')
            ->where('activo', true);

        // Filtrar por sucursal activa
        if ($this->sucursalId) {
            $query->whereHas('sucursales', function ($q) {
                $q->where('sucursal_id', $this->sucursalId)
                    ->where('articulos_sucursales.activo', 1);
            });
        }

        // Filtrar por etiquetas seleccionadas
        if (! empty($this->etiquetasModalSeleccionadas)) {
            $query->whereHas('etiquetas', function ($q) {
                $q->whereIn('etiquetas.id', $this->etiquetasModalSeleccionadas);
            });
        }

        // Filtrar por búsqueda (nombre, código, código de barras, categoría)
        $busqueda = trim($this->busquedaArticuloModal);
        if (! empty($busqueda)) {
            $palabras = preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($palabras as $palabra) {
                $query->where(function ($q) use ($palabra) {
                    $q->where('nombre', 'like', '%'.$palabra.'%')
                        ->orWhere('codigo', 'like', '%'.$palabra.'%')
                        ->orWhere('codigo_barras', 'like', '%'.$palabra.'%')
                        ->orWhereHas('categoriaModel', function ($subQ) use ($palabra) {
                            $subQ->where('nombre', 'like', '%'.$palabra.'%');
                        });
                });
            }
        }

        $articulos = $query->orderBy('nombre')->limit(100)->get();

        $this->articulosModalResultados = $articulos->map(fn ($a) => [
            'id' => $a->id,
            'nombre' => $a->nombre,
            'codigo' => $a->codigo,
            'codigo_barras' => $a->codigo_barras,
            'categoria' => $a->categoriaModel?->nombre,
            'precio_base' => $a->obtenerPrecioBaseEfectivo($this->sucursalId),
        ])->toArray();
    }

    public function seleccionarArticuloModal(int $articuloId): void
    {
        $this->cerrarModalBusquedaArticulos();
        $this->seleccionarArticulo($articuloId);
    }
}
