<?php

namespace App\Livewire\Stock;

use App\Models\Articulo;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Services\ProduccionService;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProduccionLote extends Component
{
    use SucursalAware;

    // Búsqueda de artículos
    public string $busquedaArticulo = '';

    public array $articulosResultados = [];

    // Artículo seleccionado (preview)
    public ?int $selectedArticuloId = null;

    public string $selectedArticuloNombre = '';

    public string $selectedArticuloUnidad = 'u';

    public ?int $selectedRecetaId = null;

    public string $selectedCantidadReceta = '1';

    public string $cantidadAProducir = '1';

    public array $previewIngredientes = [];

    // Lote
    public array $loteArticulos = [];

    public array $loteIngredientesConsolidados = [];

    public array $stockCache = [];

    // Config y estado
    public string $observaciones = '';

    public string $modoControlStock = 'bloquea';

    public bool $hayStockInsuficiente = false;

    public bool $confirmando = false;

    public function mount()
    {
        $sucursalId = sucursal_activa();
        $sucursal = Sucursal::find($sucursalId);
        $this->modoControlStock = $sucursal->control_stock_produccion ?? 'bloquea';
    }

    public function render()
    {
        return view('livewire.stock.produccion-lote');
    }

    /**
     * Busca artículos con receta activa (multi-word, min 3 chars, incluye categoría)
     */
    public function updatedBusquedaArticulo($value)
    {
        $value = trim($value);

        if (strlen($value) < 3) {
            $this->articulosResultados = [];

            return;
        }

        $sucursalId = sucursal_activa();

        // IDs de artículos con receta resuelta
        $articulosConReceta = Receta::where('activo', true)
            ->where('recetable_type', 'Articulo')
            ->where(function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId)
                    ->orWhere(function ($q2) use ($sucursalId) {
                        $q2->whereNull('sucursal_id')
                            ->whereNotExists(function ($sub) use ($sucursalId) {
                                $sub->selectRaw(1)
                                    ->from('recetas as r2')
                                    ->whereColumn('r2.recetable_type', 'recetas.recetable_type')
                                    ->whereColumn('r2.recetable_id', 'recetas.recetable_id')
                                    ->where('r2.sucursal_id', $sucursalId);
                            });
                    });
            })
            ->pluck('recetable_id');

        $query = Articulo::with('categoriaModel')
            ->whereIn('id', $articulosConReceta)
            ->where('activo', true);

        // Búsqueda multi-word (cada palabra debe coincidir en nombre, código, barras o categoría)
        $palabras = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
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

        $this->articulosResultados = $query->orderBy('nombre')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'codigo' => $a->codigo,
                'codigo_barras' => $a->codigo_barras,
                'categoria_nombre' => $a->categoriaModel->nombre ?? null,
            ])
            ->toArray();
    }

    /**
     * Selecciona un artículo del dropdown y carga su receta como preview
     */
    public function seleccionarArticulo(int $id)
    {
        $sucursalId = sucursal_activa();
        $articulo = Articulo::findOrFail($id);
        $receta = Receta::resolver('Articulo', $id, $sucursalId);

        if (! $receta) {
            $this->dispatch('notify', message: __('Este artículo no tiene una receta activa.'), type: 'error');

            return;
        }

        $this->selectedArticuloId = $id;
        $this->selectedArticuloNombre = $articulo->nombre;
        $this->selectedArticuloUnidad = $articulo->unidad_medida ?? 'u';
        $this->selectedRecetaId = $receta->id;
        $this->selectedCantidadReceta = (string) $receta->cantidad_producida;
        $this->cantidadAProducir = '1';

        // Cargar ingredientes del preview (SIN stock)
        $this->previewIngredientes = [];
        foreach ($receta->ingredientes as $ing) {
            $cantidadPorUnidad = (float) $ing->cantidad / (float) $receta->cantidad_producida;

            $this->previewIngredientes[] = [
                'articulo_id' => $ing->articulo_id,
                'nombre' => $ing->articulo->nombre ?? 'N/A',
                'unidad_medida' => $ing->articulo->unidad_medida ?? 'u',
                'cantidad_por_unidad' => round($cantidadPorUnidad, 3),
                'cantidad_receta' => round($cantidadPorUnidad, 3),
                'cantidad_real' => round($cantidadPorUnidad, 3),
            ];
        }

        // Limpiar búsqueda
        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
    }

    /**
     * Recalcula ingredientes del preview al cambiar cantidad
     */
    public function updatedCantidadAProducir()
    {
        $cantidad = max(0.001, (float) $this->cantidadAProducir);

        foreach ($this->previewIngredientes as $i => $ing) {
            $this->previewIngredientes[$i]['cantidad_receta'] = round($ing['cantidad_por_unidad'] * $cantidad, 3);
            $this->previewIngredientes[$i]['cantidad_real'] = round($ing['cantidad_por_unidad'] * $cantidad, 3);
        }
    }

    /**
     * Al editar cantidad real de un ingrediente consolidado, recalcular stock resultante
     */
    public function updatedLoteIngredientesConsolidados($value, $key)
    {
        // key format: "0.cantidad_real_editada"
        $parts = explode('.', $key);
        if (count($parts) === 2 && $parts[1] === 'cantidad_real_editada') {
            $index = (int) $parts[0];
            $nuevaCantidad = max(0, (float) $value);

            $this->loteIngredientesConsolidados[$index]['cantidad_real_editada'] = $nuevaCantidad;
            $this->loteIngredientesConsolidados[$index]['stock_resultante'] = round(
                $this->loteIngredientesConsolidados[$index]['stock_actual'] - $nuevaCantidad, 3
            );

            // Recalcular flag de stock insuficiente
            $this->hayStockInsuficiente = false;
            foreach ($this->loteIngredientesConsolidados as $ing) {
                if ($ing['stock_resultante'] < 0) {
                    $this->hayStockInsuficiente = true;
                    break;
                }
            }
        }
    }

    /**
     * Agrega el artículo seleccionado al lote.
     * Si ya existe, suma cantidades y recalcula ingredientes.
     */
    public function agregarAlLote()
    {
        if (! $this->selectedArticuloId) {
            return;
        }

        $cantidad = (float) $this->cantidadAProducir;
        if ($cantidad <= 0) {
            $this->dispatch('notify', message: __('La cantidad debe ser mayor a 0.'), type: 'error');

            return;
        }

        $ingredientes = [];
        foreach ($this->previewIngredientes as $ing) {
            $ingredientes[] = [
                'articulo_id' => $ing['articulo_id'],
                'nombre' => $ing['nombre'],
                'unidad_medida' => $ing['unidad_medida'],
                'cantidad_por_unidad' => (float) $ing['cantidad_por_unidad'],
                'cantidad_receta' => (float) $ing['cantidad_real'],
                'cantidad_real' => (float) $ing['cantidad_real'],
            ];
        }

        // Buscar si ya existe este artículo en el lote
        $existente = null;
        foreach ($this->loteArticulos as $index => $item) {
            if ($item['articulo_id'] === $this->selectedArticuloId) {
                $existente = $index;
                break;
            }
        }

        if ($existente !== null) {
            // Sumar cantidades
            $nuevaCantidad = $this->loteArticulos[$existente]['cantidad'] + $cantidad;
            $this->loteArticulos[$existente]['cantidad'] = $nuevaCantidad;

            // Recalcular ingredientes proporcionalmente
            foreach ($this->loteArticulos[$existente]['ingredientes'] as $i => $ing) {
                $this->loteArticulos[$existente]['ingredientes'][$i]['cantidad_receta'] = round($ing['cantidad_por_unidad'] * $nuevaCantidad, 3);
                $this->loteArticulos[$existente]['ingredientes'][$i]['cantidad_real'] = round($ing['cantidad_por_unidad'] * $nuevaCantidad, 3);
            }
        } else {
            $this->loteArticulos[] = [
                'articulo_id' => $this->selectedArticuloId,
                'nombre' => $this->selectedArticuloNombre,
                'cantidad' => $cantidad,
                'receta_id' => $this->selectedRecetaId,
                'cantidad_receta' => (float) $this->selectedCantidadReceta,
                'ingredientes' => $ingredientes,
            ];
        }

        $this->recalcularConsolidado();
        $this->limpiarSeleccion();
        $this->dispatch('notify', message: __('Agregado al lote.'), type: 'success');
    }

    /**
     * Quita un artículo del lote por índice
     */
    public function quitarDelLote(int $index)
    {
        unset($this->loteArticulos[$index]);
        $this->loteArticulos = array_values($this->loteArticulos);
        $this->recalcularConsolidado();
    }

    /**
     * Recalcula ingredientes consolidados con stock
     */
    public function recalcularConsolidado()
    {
        $sucursalId = sucursal_activa();
        $consolidado = [];

        foreach ($this->loteArticulos as $item) {
            foreach ($item['ingredientes'] as $ing) {
                $artId = $ing['articulo_id'];
                if (! isset($consolidado[$artId])) {
                    // Consultar stock con caché
                    if (! isset($this->stockCache[$artId])) {
                        $stock = Stock::where('articulo_id', $artId)
                            ->where('sucursal_id', $sucursalId)
                            ->first();
                        $this->stockCache[$artId] = $stock ? (float) $stock->cantidad : 0;
                    }

                    $consolidado[$artId] = [
                        'articulo_id' => $artId,
                        'nombre' => $ing['nombre'],
                        'unidad_medida' => $ing['unidad_medida'],
                        'total_necesario' => 0,
                        'stock_actual' => $this->stockCache[$artId],
                        'stock_resultante' => 0,
                        'cantidad_real_editada' => 0,
                    ];
                }
                $consolidado[$artId]['total_necesario'] += (float) $ing['cantidad_real'];
            }
        }

        // Calcular stock resultante y setear cantidad_real_editada = total_necesario
        $this->hayStockInsuficiente = false;
        foreach ($consolidado as &$ing) {
            $ing['total_necesario'] = round($ing['total_necesario'], 3);
            $ing['cantidad_real_editada'] = $ing['total_necesario'];
            $ing['stock_resultante'] = round($ing['stock_actual'] - $ing['total_necesario'], 3);
            if ($ing['stock_resultante'] < 0) {
                $this->hayStockInsuficiente = true;
            }
        }

        $this->loteIngredientesConsolidados = array_values($consolidado);
    }

    /**
     * Confirma el lote de producción
     */
    public function confirmarLote()
    {
        if (empty($this->loteArticulos)) {
            $this->dispatch('notify', message: __('El lote está vacío.'), type: 'error');

            return;
        }

        // Bloquear si hay stock insuficiente y modo es bloquea
        if ($this->hayStockInsuficiente && $this->modoControlStock === 'bloquea') {
            $this->dispatch('notify', message: __('No se puede confirmar: stock insuficiente de ingredientes.'), type: 'error');

            return;
        }

        $this->confirmando = true;

        // Mapear cantidades editadas del consolidado por articulo_id
        $cantidadesEditadas = [];
        foreach ($this->loteIngredientesConsolidados as $ing) {
            $cantidadesEditadas[$ing['articulo_id']] = (float) $ing['cantidad_real_editada'];
        }

        // Calcular totales originales por ingrediente para distribuir proporcionalmente
        $totalesOriginales = [];
        foreach ($this->loteArticulos as $item) {
            foreach ($item['ingredientes'] as $ing) {
                $artId = $ing['articulo_id'];
                if (! isset($totalesOriginales[$artId])) {
                    $totalesOriginales[$artId] = 0;
                }
                $totalesOriginales[$artId] += (float) $ing['cantidad_real'];
            }
        }

        // Armar cola para ProduccionService, distribuyendo cantidad editada proporcionalmente
        $cola = [];
        foreach ($this->loteArticulos as $item) {
            $ingredientes = [];
            foreach ($item['ingredientes'] as $ing) {
                $artId = $ing['articulo_id'];
                $totalOriginal = $totalesOriginales[$artId] ?? 1;
                $totalEditado = $cantidadesEditadas[$artId] ?? (float) $ing['cantidad_real'];
                // Proporción de este item sobre el total
                $proporcion = $totalOriginal > 0 ? (float) $ing['cantidad_real'] / $totalOriginal : 0;
                $cantidadReal = round($totalEditado * $proporcion, 3);

                $ingredientes[] = [
                    'articulo_id' => $artId,
                    'cantidad_receta' => (float) $ing['cantidad_receta'],
                    'cantidad_real' => $cantidadReal,
                ];
            }
            $cola[] = [
                'articulo_id' => $item['articulo_id'],
                'cantidad' => (float) $item['cantidad'],
                'receta_id' => $item['receta_id'],
                'cantidad_receta' => (float) $item['cantidad_receta'],
                'ingredientes' => $ingredientes,
            ];
        }

        try {
            $service = new ProduccionService;
            $resultado = $service->confirmarProduccion(
                $cola,
                sucursal_activa(),
                Auth::id(),
                $this->observaciones ?: null
            );

            $msg = __('Lote de producción confirmado correctamente.');
            if (! empty($resultado['advertencias'])) {
                $msg .= ' '.__('Advertencia: algunos ingredientes tenían stock insuficiente.');
            }

            session()->flash('notify', ['message' => $msg, 'type' => 'success']);
            $this->redirect(route('stock.produccion'), navigate: true);

        } catch (Exception $e) {
            $this->confirmando = false;
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    /**
     * Cancela y vuelve a producción
     */
    public function cancelar()
    {
        $this->redirect(route('stock.produccion'), navigate: true);
    }

    /**
     * Limpia la selección actual (preview)
     */
    protected function limpiarSeleccion()
    {
        $this->selectedArticuloId = null;
        $this->selectedArticuloNombre = '';
        $this->selectedArticuloUnidad = 'u';
        $this->selectedRecetaId = null;
        $this->selectedCantidadReceta = '1';
        $this->cantidadAProducir = '1';
        $this->previewIngredientes = [];
    }
}
