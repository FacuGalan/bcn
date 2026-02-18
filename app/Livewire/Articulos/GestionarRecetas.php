<?php

namespace App\Livewire\Articulos;

use App\Models\Articulo;
use App\Models\Opcional;
use App\Models\Receta;
use App\Models\RecetaIngrediente;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para gestión de recetas.
 *
 * Muestra todas las recetas genéricas definidas (artículos y opcionales),
 * permite editar, eliminar y copiar recetas entre elementos del mismo tipo.
 */
#[Layout('layouts.app')]
class GestionarRecetas extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filterTipo = 'all'; // all, Articulo, Opcional
    public string $filterEstado = 'all'; // all, con_receta, sin_receta

    // Modal editor de receta
    public bool $showRecetaModal = false;
    public ?int $recetableId = null;
    public string $recetableType = '';
    public string $recetableNombre = '';
    public ?int $recetaId = null;

    // Propiedades para el partial _receta-editor
    public array $recetaIngredientes = [];
    public string $busquedaIngrediente = '';
    public array $resultadosBusqueda = [];
    public string $recetaCantidadProducida = '1.000';
    public string $recetaNotas = '';
    public bool $recetaEsOverride = false;
    public ?string $recetaSucursalNombre = null;

    // Modal eliminar receta
    public bool $showDeleteRecetaModal = false;

    // Modal copiar receta
    public bool $showCopiarModal = false;
    public ?int $copiarDesdeRecetaId = null;
    public string $copiarDesdeNombre = '';
    public string $copiarDesdeType = '';
    public string $busquedaDestino = '';
    public array $destinosSeleccionados = [];

    // Modal nueva receta (crear + asignar)
    public bool $showNuevaRecetaModal = false;
    public string $nuevaRecetaTipo = 'Articulo'; // Articulo o Opcional
    public int $nuevaRecetaPaso = 1; // 1=armar receta, 2=asignar destinos
    public array $nuevaRecetaIngredientes = [];
    public string $nuevaRecetaBusquedaIng = '';
    public array $nuevaRecetaResultadosIng = [];
    public string $nuevaRecetaCantProducida = '1.000';
    public string $nuevaRecetaNotas = '';
    public string $nuevaRecetaBusquedaDest = '';
    public array $nuevaRecetaDestinos = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEstado(): void
    {
        $this->resetPage();
    }

    protected function getRecetas()
    {
        $query = Receta::with(['recetable' => function ($morphTo) {
                $morphTo->morphWith([
                    Opcional::class => ['grupoOpcional'],
                ]);
            }, 'ingredientes.articulo'])
            ->whereNull('sucursal_id')
            ->where('activo', true);

        if ($this->filterTipo !== 'all') {
            $query->where('recetable_type', $this->filterTipo);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHasMorph('recetable', [Articulo::class], function ($sub) use ($search) {
                    $sub->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                })->orWhereHasMorph('recetable', [Opcional::class], function ($sub) use ($search) {
                    $sub->where('nombre', 'like', "%{$search}%");
                });
            });
        }

        return $query->orderBy('recetable_type')
            ->orderBy('recetable_id')
            ->paginate(15);
    }

    // ===== Editor de Receta =====

    public function editarReceta(int $recetaId): void
    {
        $receta = Receta::with(['ingredientes.articulo', 'recetable' => function ($morphTo) {
            $morphTo->morphWith([
                Opcional::class => ['grupoOpcional'],
            ]);
        }])->findOrFail($recetaId);

        $this->recetaId = $receta->id;
        $this->recetableId = $receta->recetable_id;
        $this->recetableType = $receta->recetable_type;

        $nombre = $receta->recetable?->nombre ?? __('Eliminado');
        if ($receta->recetable_type === 'Opcional' && $receta->recetable?->grupoOpcional) {
            $nombre .= ' (' . $receta->recetable->grupoOpcional->nombre . ')';
        }
        $this->recetableNombre = $nombre;
        $this->recetaCantidadProducida = (string) $receta->cantidad_producida;
        $this->recetaNotas = $receta->notas ?? '';
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;

        $this->recetaIngredientes = $receta->ingredientes->map(fn($ing) => [
            'articulo_id' => $ing->articulo_id,
            'codigo' => $ing->articulo->codigo ?? '',
            'nombre' => $ing->articulo->nombre ?? __('Artículo eliminado'),
            'unidad_medida' => $ing->articulo->unidad_medida ?? '',
            'cantidad' => (string) $ing->cantidad,
        ])->toArray();

        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->showRecetaModal = true;
    }

    public function updatedBusquedaIngrediente(): void
    {
        if (strlen($this->busquedaIngrediente) < 2) {
            $this->resultadosBusqueda = [];
            return;
        }

        $excluirIds = collect($this->recetaIngredientes)->pluck('articulo_id')->toArray();

        // Si es receta de artículo, excluir el artículo mismo
        if ($this->recetableType === 'Articulo' && $this->recetableId) {
            $excluirIds[] = $this->recetableId;
        }

        $this->resultadosBusqueda = Articulo::where('activo', true)
            ->whereNotIn('id', $excluirIds)
            ->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->busquedaIngrediente . '%')
                  ->orWhere('nombre', 'like', '%' . $this->busquedaIngrediente . '%');
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad_medida'])
            ->map(fn($a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'unidad_medida' => $a->unidad_medida,
            ])
            ->toArray();
    }

    public function agregarPrimerIngrediente(): void
    {
        if (count($this->resultadosBusqueda) > 0) {
            $this->agregarIngrediente($this->resultadosBusqueda[0]['id']);
        }
    }

    public function agregarIngrediente(int $articuloId): void
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        foreach ($this->recetaIngredientes as $ing) {
            if ($ing['articulo_id'] == $articuloId) return;
        }

        $this->recetaIngredientes[] = [
            'articulo_id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'unidad_medida' => $articulo->unidad_medida,
            'cantidad' => '1.000',
        ];

        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
    }

    public function eliminarIngrediente(int $index): void
    {
        unset($this->recetaIngredientes[$index]);
        $this->recetaIngredientes = array_values($this->recetaIngredientes);
    }

    public function guardarReceta(): void
    {
        if (!$this->recetaId) return;

        if (empty($this->recetaIngredientes)) {
            $this->dispatch('notify', message: __('La receta debe tener al menos un ingrediente'), type: 'error');
            return;
        }

        foreach ($this->recetaIngredientes as $ing) {
            if (!isset($ing['cantidad']) || (float) $ing['cantidad'] <= 0) {
                $this->dispatch('notify', message: __('Todas las cantidades deben ser mayores a 0'), type: 'error');
                return;
            }
        }

        DB::connection('pymes_tenant')->transaction(function () {
            $receta = Receta::findOrFail($this->recetaId);
            $receta->update([
                'cantidad_producida' => $this->recetaCantidadProducida,
                'notas' => $this->recetaNotas ?: null,
            ]);

            $receta->ingredientes()->delete();
            foreach ($this->recetaIngredientes as $ing) {
                $receta->ingredientes()->create([
                    'articulo_id' => $ing['articulo_id'],
                    'cantidad' => $ing['cantidad'],
                ]);
            }
        });

        $this->dispatch('notify', message: __('Receta guardada correctamente'), type: 'success');
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function confirmarEliminarReceta(): void
    {
        if ($this->recetaId) {
            $this->showDeleteRecetaModal = true;
        }
    }

    public function eliminarReceta(): void
    {
        if (!$this->recetaId) return;

        $receta = Receta::find($this->recetaId);
        if ($receta) {
            $receta->ingredientes()->delete();
            $receta->delete();
        }

        $this->dispatch('notify', message: __('Receta eliminada correctamente'), type: 'success');
        $this->showDeleteRecetaModal = false;
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarReceta(): void
    {
        $this->showRecetaModal = false;
        $this->resetReceta();
    }

    public function cancelarEliminarReceta(): void
    {
        $this->showDeleteRecetaModal = false;
    }

    // ===== Copiar Receta =====

    public function abrirCopiar(int $recetaId): void
    {
        $receta = Receta::with(['recetable' => function ($morphTo) {
            $morphTo->morphWith([
                Opcional::class => ['grupoOpcional'],
            ]);
        }])->findOrFail($recetaId);

        $this->copiarDesdeRecetaId = $receta->id;
        $nombre = $receta->recetable?->nombre ?? __('Eliminado');
        if ($receta->recetable_type === 'Opcional' && $receta->recetable?->grupoOpcional) {
            $nombre .= ' (' . $receta->recetable->grupoOpcional->nombre . ')';
        }
        $this->copiarDesdeNombre = $nombre;
        $this->copiarDesdeType = $receta->recetable_type;
        $this->busquedaDestino = '';
        $this->destinosSeleccionados = [];
        $this->showCopiarModal = true;
    }

    public function getDestinosCopiaProperty(): array
    {
        if (!$this->copiarDesdeRecetaId) {
            return [];
        }

        // IDs que ya tienen receta genérica
        $idsConReceta = Receta::where('recetable_type', $this->copiarDesdeType)
            ->whereNull('sucursal_id')
            ->where('activo', true)
            ->pluck('recetable_id')
            ->toArray();

        return $this->buscarDestinos($this->copiarDesdeType, $this->busquedaDestino, $idsConReceta);
    }

    protected function buscarDestinos(string $type, string $search, array $excluirIds): array
    {
        if ($type === 'Articulo') {
            $query = Articulo::where('activo', true)
                ->where('es_materia_prima', false)
                ->whereNotIn('id', $excluirIds);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', '%' . $search . '%')
                      ->orWhere('codigo', 'like', '%' . $search . '%');
                });
            }

            return $query->orderBy('nombre')
                ->limit(50)
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn($a) => [
                    'id' => $a->id,
                    'label' => $a->codigo . ' - ' . $a->nombre,
                ])
                ->toArray();
        } else {
            $query = Opcional::with('grupoOpcional')
                ->where('activo', true)
                ->whereNotIn('id', $excluirIds);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', '%' . $search . '%')
                      ->orWhereHas('grupoOpcional', fn($g) => $g->where('nombre', 'like', '%' . $search . '%'));
                });
            }

            return $query->orderBy('nombre')
                ->limit(50)
                ->get(['id', 'nombre', 'grupo_opcional_id'])
                ->map(fn($o) => [
                    'id' => $o->id,
                    'label' => $o->nombre . ($o->grupoOpcional ? ' (' . $o->grupoOpcional->nombre . ')' : ''),
                ])
                ->toArray();
        }
    }

    public function toggleDestinoSeleccionado(int $id): void
    {
        if (in_array($id, $this->destinosSeleccionados)) {
            $this->destinosSeleccionados = array_values(array_diff($this->destinosSeleccionados, [$id]));
        } else {
            $this->destinosSeleccionados[] = $id;
        }
    }

    public function ejecutarCopia(): void
    {
        if (empty($this->destinosSeleccionados) || !$this->copiarDesdeRecetaId) {
            return;
        }

        $recetaOrigen = Receta::with('ingredientes')->find($this->copiarDesdeRecetaId);
        if (!$recetaOrigen) return;

        $count = 0;

        DB::connection('pymes_tenant')->transaction(function () use ($recetaOrigen, &$count) {
            foreach ($this->destinosSeleccionados as $destinoId) {
                // Verificar que no tenga receta genérica ya
                $existe = Receta::where('recetable_type', $this->copiarDesdeType)
                    ->where('recetable_id', $destinoId)
                    ->whereNull('sucursal_id')
                    ->where('activo', true)
                    ->exists();

                if ($existe) continue;

                $nuevaReceta = Receta::create([
                    'recetable_type' => $this->copiarDesdeType,
                    'recetable_id' => $destinoId,
                    'sucursal_id' => null,
                    'cantidad_producida' => $recetaOrigen->cantidad_producida,
                    'notas' => $recetaOrigen->notas,
                    'activo' => true,
                ]);

                foreach ($recetaOrigen->ingredientes as $ing) {
                    $nuevaReceta->ingredientes()->create([
                        'articulo_id' => $ing->articulo_id,
                        'cantidad' => $ing->cantidad,
                    ]);
                }

                $count++;
            }
        });

        $this->dispatch('notify', message: __('Receta copiada a :count destinos', ['count' => $count]), type: 'success');
        $this->cerrarCopiar();
    }

    public function cerrarCopiar(): void
    {
        $this->showCopiarModal = false;
        $this->copiarDesdeRecetaId = null;
        $this->copiarDesdeNombre = '';
        $this->copiarDesdeType = '';
        $this->busquedaDestino = '';
        $this->destinosSeleccionados = [];
    }

    // ===== Nueva Receta (crear + asignar masivo) =====

    public function abrirNuevaReceta(): void
    {
        $this->nuevaRecetaTipo = 'Articulo';
        $this->nuevaRecetaPaso = 1;
        $this->nuevaRecetaIngredientes = [];
        $this->nuevaRecetaBusquedaIng = '';
        $this->nuevaRecetaResultadosIng = [];
        $this->nuevaRecetaCantProducida = '1.000';
        $this->nuevaRecetaNotas = '';
        $this->nuevaRecetaBusquedaDest = '';
        $this->nuevaRecetaDestinos = [];
        $this->showNuevaRecetaModal = true;
    }

    public function updatedNuevaRecetaBusquedaIng(): void
    {
        if (strlen($this->nuevaRecetaBusquedaIng) < 2) {
            $this->nuevaRecetaResultadosIng = [];
            return;
        }

        $excluirIds = collect($this->nuevaRecetaIngredientes)->pluck('articulo_id')->toArray();

        $this->nuevaRecetaResultadosIng = Articulo::where('activo', true)
            ->whereNotIn('id', $excluirIds)
            ->where(function ($q) {
                $q->where('codigo', 'like', '%' . $this->nuevaRecetaBusquedaIng . '%')
                  ->orWhere('nombre', 'like', '%' . $this->nuevaRecetaBusquedaIng . '%');
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad_medida'])
            ->map(fn($a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'unidad_medida' => $a->unidad_medida,
            ])
            ->toArray();
    }

    public function nuevaRecetaAgregarPrimerIng(): void
    {
        if (count($this->nuevaRecetaResultadosIng) > 0) {
            $this->nuevaRecetaAgregarIng($this->nuevaRecetaResultadosIng[0]['id']);
        }
    }

    public function nuevaRecetaAgregarIng(int $articuloId): void
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) return;

        foreach ($this->nuevaRecetaIngredientes as $ing) {
            if ($ing['articulo_id'] == $articuloId) return;
        }

        $this->nuevaRecetaIngredientes[] = [
            'articulo_id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'unidad_medida' => $articulo->unidad_medida,
            'cantidad' => '1.000',
        ];

        $this->nuevaRecetaBusquedaIng = '';
        $this->nuevaRecetaResultadosIng = [];
    }

    public function nuevaRecetaEliminarIng(int $index): void
    {
        unset($this->nuevaRecetaIngredientes[$index]);
        $this->nuevaRecetaIngredientes = array_values($this->nuevaRecetaIngredientes);
    }

    public function nuevaRecetaSiguiente(): void
    {
        if (empty($this->nuevaRecetaIngredientes)) {
            $this->dispatch('notify', message: __('La receta debe tener al menos un ingrediente'), type: 'error');
            return;
        }

        foreach ($this->nuevaRecetaIngredientes as $ing) {
            if (!isset($ing['cantidad']) || (float) $ing['cantidad'] <= 0) {
                $this->dispatch('notify', message: __('Todas las cantidades deben ser mayores a 0'), type: 'error');
                return;
            }
        }

        $this->nuevaRecetaPaso = 2;
        $this->nuevaRecetaBusquedaDest = '';
        $this->nuevaRecetaDestinos = [];
    }

    public function nuevaRecetaVolver(): void
    {
        $this->nuevaRecetaPaso = 1;
    }

    public function getNuevaRecetaListaDestinosProperty(): array
    {
        // IDs que ya tienen receta genérica de este tipo
        $idsConReceta = Receta::where('recetable_type', $this->nuevaRecetaTipo)
            ->whereNull('sucursal_id')
            ->where('activo', true)
            ->pluck('recetable_id')
            ->toArray();

        return $this->buscarDestinos($this->nuevaRecetaTipo, $this->nuevaRecetaBusquedaDest, $idsConReceta);
    }

    public function toggleNuevaRecetaDestino(int $id): void
    {
        if (in_array($id, $this->nuevaRecetaDestinos)) {
            $this->nuevaRecetaDestinos = array_values(array_diff($this->nuevaRecetaDestinos, [$id]));
        } else {
            $this->nuevaRecetaDestinos[] = $id;
        }
    }

    public function guardarNuevaReceta(): void
    {
        if (empty($this->nuevaRecetaDestinos) || empty($this->nuevaRecetaIngredientes)) {
            return;
        }

        $count = 0;

        DB::connection('pymes_tenant')->transaction(function () use (&$count) {
            foreach ($this->nuevaRecetaDestinos as $destinoId) {
                $existe = Receta::where('recetable_type', $this->nuevaRecetaTipo)
                    ->where('recetable_id', $destinoId)
                    ->whereNull('sucursal_id')
                    ->where('activo', true)
                    ->exists();

                if ($existe) continue;

                $receta = Receta::create([
                    'recetable_type' => $this->nuevaRecetaTipo,
                    'recetable_id' => $destinoId,
                    'sucursal_id' => null,
                    'cantidad_producida' => $this->nuevaRecetaCantProducida,
                    'notas' => $this->nuevaRecetaNotas ?: null,
                    'activo' => true,
                ]);

                foreach ($this->nuevaRecetaIngredientes as $ing) {
                    $receta->ingredientes()->create([
                        'articulo_id' => $ing['articulo_id'],
                        'cantidad' => $ing['cantidad'],
                    ]);
                }

                $count++;
            }
        });

        $this->dispatch('notify', message: __('Receta creada para :count destinos', ['count' => $count]), type: 'success');
        $this->cerrarNuevaReceta();
    }

    public function cerrarNuevaReceta(): void
    {
        $this->showNuevaRecetaModal = false;
        $this->nuevaRecetaTipo = 'Articulo';
        $this->nuevaRecetaPaso = 1;
        $this->nuevaRecetaIngredientes = [];
        $this->nuevaRecetaBusquedaIng = '';
        $this->nuevaRecetaResultadosIng = [];
        $this->nuevaRecetaCantProducida = '1.000';
        $this->nuevaRecetaNotas = '';
        $this->nuevaRecetaBusquedaDest = '';
        $this->nuevaRecetaDestinos = [];
    }

    // ===== Helpers =====

    protected function resetReceta(): void
    {
        $this->recetableId = null;
        $this->recetableType = '';
        $this->recetableNombre = '';
        $this->recetaId = null;
        $this->recetaIngredientes = [];
        $this->busquedaIngrediente = '';
        $this->resultadosBusqueda = [];
        $this->recetaCantidadProducida = '1.000';
        $this->recetaNotas = '';
        $this->recetaEsOverride = false;
        $this->recetaSucursalNombre = null;
    }

    public function render()
    {
        return view('livewire.articulos.gestionar-recetas', [
            'recetas' => $this->getRecetas(),
        ]);
    }
}
