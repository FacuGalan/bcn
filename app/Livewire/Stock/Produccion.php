<?php

namespace App\Livewire\Stock;

use App\Models\Articulo;
use App\Models\Produccion as ProduccionModel;
use App\Models\Receta;
use App\Models\Stock;
use App\Services\ProduccionService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Produccion extends Component
{
    use WithPagination;

    // Búsqueda de artículos con receta
    public string $search = '';

    // Cola de producción (batch)
    public array $colaProduccion = [];

    // Modal producir artículo individual
    public bool $showProducirModal = false;

    public ?int $producirArticuloId = null;

    public string $producirArticuloNombre = '';

    public string $producirCantidad = '1';

    public array $producirIngredientes = [];

    public ?int $producirRecetaId = null;

    public string $producirCantidadReceta = '1';

    // Modal confirmar lote
    public bool $showConfirmarLoteModal = false;

    public array $resumenIngredientes = [];

    public string $loteObservaciones = '';

    // Modal historial
    public bool $showHistorialModal = false;

    public string $filterFechaDesde = '';

    public string $filterFechaHasta = '';

    public array $historial = [];

    // Modal anulación
    public bool $showAnularModal = false;

    public ?int $anularProduccionId = null;

    public string $motivoAnulacion = '';

    // Modal detalle producción
    public bool $showDetalleModal = false;

    public ?array $detalleProduccion = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $sucursalId = sucursal_activa();
        $articulos = $this->getArticulosConReceta($sucursalId);

        return view('livewire.stock.produccion', [
            'articulos' => $articulos,
        ]);
    }

    /**
     * Obtiene artículos con receta resuelta para la sucursal activa
     */
    protected function getArticulosConReceta(int $sucursalId)
    {
        // Obtener IDs de artículos que tienen receta resuelta
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

        $query = Articulo::whereIn('id', $articulosConReceta)
            ->where('activo', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('codigo', 'like', '%'.$this->search.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$this->search.'%');
            });
        }

        return $query->with('categoriaModel')
            ->orderBy('nombre')
            ->paginate(15);
    }

    /**
     * Abre modal para producir un artículo
     */
    public function abrirProducir(int $articuloId)
    {
        $sucursalId = sucursal_activa();
        $articulo = Articulo::findOrFail($articuloId);
        $receta = Receta::resolver('Articulo', $articuloId, $sucursalId);

        if (! $receta) {
            $this->dispatch('notify', message: __('Este artículo no tiene una receta activa.'), type: 'error');

            return;
        }

        $this->producirArticuloId = $articuloId;
        $this->producirArticuloNombre = $articulo->nombre;
        $this->producirCantidad = '1';
        $this->producirRecetaId = $receta->id;
        $this->producirCantidadReceta = (string) $receta->cantidad_producida;

        // Cargar ingredientes
        $this->producirIngredientes = [];
        foreach ($receta->ingredientes as $ing) {
            $stock = Stock::where('articulo_id', $ing->articulo_id)
                ->where('sucursal_id', $sucursalId)
                ->first();

            $cantidadPorUnidad = (float) $ing->cantidad / (float) $receta->cantidad_producida;

            $this->producirIngredientes[] = [
                'articulo_id' => $ing->articulo_id,
                'nombre' => $ing->articulo->nombre ?? 'N/A',
                'unidad_medida' => $ing->articulo->unidad_medida ?? 'u',
                'cantidad_por_unidad' => round($cantidadPorUnidad, 3),
                'cantidad_receta' => round($cantidadPorUnidad * 1, 3),
                'cantidad_real' => round($cantidadPorUnidad * 1, 3),
                'stock_disponible' => $stock ? (float) $stock->cantidad : 0,
            ];
        }

        $this->showProducirModal = true;
    }

    /**
     * Recalcula totales de ingredientes al cambiar la cantidad (lifecycle hook automático)
     */
    public function updatedProducirCantidad()
    {
        $cantidad = max(0.001, (float) $this->producirCantidad);

        foreach ($this->producirIngredientes as $i => $ing) {
            $this->producirIngredientes[$i]['cantidad_receta'] = round($ing['cantidad_por_unidad'] * $cantidad, 3);
            $this->producirIngredientes[$i]['cantidad_real'] = round($ing['cantidad_por_unidad'] * $cantidad, 3);
        }
    }

    /**
     * Agrega artículo actual a la cola de producción
     */
    public function agregarACola()
    {
        $cantidad = (float) $this->producirCantidad;
        if ($cantidad <= 0) {
            $this->dispatch('notify', message: __('La cantidad debe ser mayor a 0.'), type: 'error');

            return;
        }

        $ingredientes = [];
        foreach ($this->producirIngredientes as $ing) {
            $ingredientes[] = [
                'articulo_id' => $ing['articulo_id'],
                'nombre' => $ing['nombre'],
                'unidad_medida' => $ing['unidad_medida'],
                'cantidad_receta' => (float) $ing['cantidad_receta'],
                'cantidad_real' => (float) $ing['cantidad_real'],
                'stock_disponible' => $ing['stock_disponible'],
            ];
        }

        $this->colaProduccion[] = [
            'articulo_id' => $this->producirArticuloId,
            'nombre' => $this->producirArticuloNombre,
            'cantidad' => $cantidad,
            'receta_id' => $this->producirRecetaId,
            'cantidad_receta' => (float) $this->producirCantidadReceta,
            'ingredientes' => $ingredientes,
        ];

        $this->cerrarProducirModal();
        $this->dispatch('notify', message: __('Agregado a cola de producción.'), type: 'success');
    }

    /**
     * Produce un artículo individual directamente
     */
    public function producirIndividual()
    {
        $cantidad = (float) $this->producirCantidad;
        if ($cantidad <= 0) {
            $this->dispatch('notify', message: __('La cantidad debe ser mayor a 0.'), type: 'error');

            return;
        }

        $ingredientes = [];
        foreach ($this->producirIngredientes as $ing) {
            $ingredientes[] = [
                'articulo_id' => $ing['articulo_id'],
                'cantidad_receta' => (float) $ing['cantidad_receta'],
                'cantidad_real' => (float) $ing['cantidad_real'],
            ];
        }

        $cola = [[
            'articulo_id' => $this->producirArticuloId,
            'cantidad' => $cantidad,
            'receta_id' => $this->producirRecetaId,
            'cantidad_receta' => (float) $this->producirCantidadReceta,
            'ingredientes' => $ingredientes,
        ]];

        try {
            $service = new ProduccionService;
            $resultado = $service->confirmarProduccion(
                $cola,
                sucursal_activa(),
                Auth::id()
            );

            $this->cerrarProducirModal();

            $msg = __('Producción confirmada correctamente.');
            if (! empty($resultado['advertencias'])) {
                $msg .= ' '.__('Advertencia: algunos ingredientes tenían stock insuficiente.');
            }
            $this->dispatch('notify', message: $msg, type: 'success');

        } catch (Exception $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    /**
     * Quita un artículo de la cola
     */
    public function quitarDeCola(int $index)
    {
        unset($this->colaProduccion[$index]);
        $this->colaProduccion = array_values($this->colaProduccion);
    }

    /**
     * Limpia toda la cola
     */
    public function limpiarCola()
    {
        $this->colaProduccion = [];
    }

    /**
     * Abre modal de confirmación de lote
     */
    public function abrirConfirmarLote()
    {
        if (empty($this->colaProduccion)) {
            $this->dispatch('notify', message: __('La cola de producción está vacía.'), type: 'error');

            return;
        }

        // Consolidar ingredientes
        $consolidado = [];
        foreach ($this->colaProduccion as $item) {
            foreach ($item['ingredientes'] as $ing) {
                $artId = $ing['articulo_id'];
                if (! isset($consolidado[$artId])) {
                    $consolidado[$artId] = [
                        'articulo_id' => $artId,
                        'nombre' => $ing['nombre'],
                        'unidad_medida' => $ing['unidad_medida'],
                        'total_necesario' => 0,
                        'stock_disponible' => $ing['stock_disponible'],
                    ];
                }
                $consolidado[$artId]['total_necesario'] += (float) $ing['cantidad_real'];
            }
        }

        // Calcular diferencia
        foreach ($consolidado as &$ing) {
            $ing['total_necesario'] = round($ing['total_necesario'], 3);
            $ing['diferencia'] = round($ing['stock_disponible'] - $ing['total_necesario'], 3);
        }

        $this->resumenIngredientes = array_values($consolidado);
        $this->loteObservaciones = '';
        $this->showConfirmarLoteModal = true;
    }

    /**
     * Confirma producción del lote completo
     */
    public function confirmarLote()
    {
        if (empty($this->colaProduccion)) {
            return;
        }

        // Preparar cola para el servicio
        $cola = [];
        foreach ($this->colaProduccion as $item) {
            $ingredientes = [];
            foreach ($item['ingredientes'] as $ing) {
                $ingredientes[] = [
                    'articulo_id' => $ing['articulo_id'],
                    'cantidad_receta' => (float) $ing['cantidad_receta'],
                    'cantidad_real' => (float) $ing['cantidad_real'],
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
                $this->loteObservaciones ?: null
            );

            $this->colaProduccion = [];
            $this->showConfirmarLoteModal = false;

            $msg = __('Producción de lote confirmada correctamente.');
            if (! empty($resultado['advertencias'])) {
                $msg .= ' '.__('Advertencia: algunos ingredientes tenían stock insuficiente.');
            }
            $this->dispatch('notify', message: $msg, type: 'success');

        } catch (Exception $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    /**
     * Abre modal de historial de producciones
     */
    public function verHistorial()
    {
        $this->filterFechaDesde = now()->subDays(30)->toDateString();
        $this->filterFechaHasta = now()->toDateString();
        $this->cargarHistorial();
        $this->showHistorialModal = true;
    }

    /**
     * Carga el historial filtrado
     */
    public function cargarHistorial()
    {
        $sucursalId = sucursal_activa();

        $query = ProduccionModel::with(['usuario', 'anuladoPorUsuario', 'detalles.articulo'])
            ->porSucursal($sucursalId)
            ->orderBy('id', 'desc');

        if ($this->filterFechaDesde) {
            $query->where('fecha', '>=', $this->filterFechaDesde);
        }
        if ($this->filterFechaHasta) {
            $query->where('fecha', '<=', $this->filterFechaHasta);
        }

        $this->historial = $query->limit(50)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'fecha' => $p->fecha->format('d/m/Y'),
                'estado' => $p->estado,
                'observaciones' => $p->observaciones,
                'usuario' => $p->usuario->name ?? 'N/A',
                'anulado_por' => $p->anuladoPorUsuario->name ?? null,
                'fecha_anulacion' => $p->fecha_anulacion ? $p->fecha_anulacion->format('d/m/Y H:i') : null,
                'motivo_anulacion' => $p->motivo_anulacion,
                'articulos' => $p->detalles->map(fn ($d) => [
                    'nombre' => $d->articulo->nombre ?? 'N/A',
                    'cantidad' => (float) $d->cantidad_producida,
                ])->toArray(),
                'total_articulos' => $p->detalles->count(),
            ];
        })->toArray();
    }

    /**
     * Ver detalle de una producción
     */
    public function verDetalle(int $produccionId)
    {
        $produccion = ProduccionModel::with(['usuario', 'anuladoPorUsuario', 'detalles.articulo', 'detalles.ingredientes.articulo'])
            ->findOrFail($produccionId);

        $this->detalleProduccion = [
            'id' => $produccion->id,
            'fecha' => $produccion->fecha->format('d/m/Y'),
            'estado' => $produccion->estado,
            'observaciones' => $produccion->observaciones,
            'usuario' => $produccion->usuario->name ?? 'N/A',
            'anulado_por' => $produccion->anuladoPorUsuario->name ?? null,
            'motivo_anulacion' => $produccion->motivo_anulacion,
            'detalles' => $produccion->detalles->map(fn ($d) => [
                'articulo' => $d->articulo->nombre ?? 'N/A',
                'cantidad_producida' => (float) $d->cantidad_producida,
                'ingredientes' => $d->ingredientes->map(fn ($i) => [
                    'articulo' => $i->articulo->nombre ?? 'N/A',
                    'cantidad_receta' => (float) $i->cantidad_receta,
                    'cantidad_real' => (float) $i->cantidad_real,
                ])->toArray(),
            ])->toArray(),
        ];

        $this->showDetalleModal = true;
    }

    /**
     * Abre modal de anulación
     */
    public function abrirAnular(int $produccionId)
    {
        $this->anularProduccionId = $produccionId;
        $this->motivoAnulacion = '';
        $this->showAnularModal = true;
    }

    public function cancelarAnulacion(): void
    {
        $this->showAnularModal = false;
        $this->anularProduccionId = null;
        $this->motivoAnulacion = '';
    }

    /**
     * Confirma anulación de producción
     */
    public function confirmarAnulacion()
    {
        if (empty($this->motivoAnulacion)) {
            $this->dispatch('notify', message: __('Debe ingresar un motivo de anulación.'), type: 'error');

            return;
        }

        try {
            $service = new ProduccionService;
            $service->anularProduccion(
                $this->anularProduccionId,
                Auth::id(),
                $this->motivoAnulacion
            );

            $this->showAnularModal = false;
            $this->anularProduccionId = null;
            $this->motivoAnulacion = '';
            $this->cargarHistorial();

            $this->dispatch('notify', message: __('Producción anulada correctamente. Contraasientos generados.'), type: 'success');

        } catch (Exception $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    /**
     * Cancela/cierra el modal de confirmar lote
     */
    public function cancelarConfirmarLote(): void
    {
        $this->showConfirmarLoteModal = false;
        $this->resumenIngredientes = [];
        $this->loteObservaciones = '';
    }

    /**
     * Cierra el modal de producir
     */
    public function cerrarProducirModal()
    {
        $this->showProducirModal = false;
        $this->producirArticuloId = null;
        $this->producirArticuloNombre = '';
        $this->producirCantidad = '1';
        $this->producirIngredientes = [];
        $this->producirRecetaId = null;
        $this->producirCantidadReceta = '1';
    }

    /**
     * Cierra modal de historial
     */
    public function cerrarHistorial()
    {
        $this->showHistorialModal = false;
        $this->historial = [];
    }

    /**
     * Cierra modal de detalle
     */
    public function cerrarDetalle()
    {
        $this->showDetalleModal = false;
        $this->detalleProduccion = null;
    }
}
