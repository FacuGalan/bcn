<?php

namespace App\Livewire\Compras;

use App\Models\Articulo;
use App\Models\Compra;
use App\Models\HistorialPrecio;
use App\Services\CostoService;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Revisión de precios post-compra (RF-10, Pantallas §2).
 *
 * Sub-componente SIN ruta: lo monta el listado `Compras` desde el detalle de
 * una compra o desde el aviso post-confirmación del editor. Es RETOMABLE:
 * calcula SIEMPRE contra el costo y el precio VIGENTES (no snapshot) — si
 * otra compra posterior ya cambió el costo, la revisión lo refleja.
 *
 * Lista los artículos de la compra cuyo margen real quedó bajo la utilidad
 * objetivo; aplicar en lote escribe el precio (override de la sucursal de la
 * compra si existe, si no el global — regla RF-10) + HistorialPrecio.
 *
 * Gates: abrirla requiere func.costos.ver (muestra costos/márgenes); aplicar
 * requiere func.compras.revisar_precios.
 */
class RevisionPreciosCompra extends Component
{
    public int $compraId;

    public int $sucursalId;

    /** sin_redondeo | entero | decena | centena (opciones de PrecioService::aplicarRedondeo) */
    public string $tipoRedondeo = 'sin_redondeo';

    /**
     * @var array<int, array> filas: {articulo_id, nombre, codigo, costo, precio_actual,
     *                        margen_real, objetivo, sugerido, precio_nuevo, seleccionado, alcance}
     */
    public array $filas = [];

    public bool $cargada = false;

    public function mount(int $compraId): void
    {
        $this->compraId = $compraId;
        $compra = Compra::findOrFail($compraId);
        $this->sucursalId = (int) $compra->sucursal_id;

        $this->recalcular();
    }

    /**
     * Recalcula contra los valores VIGENTES (retomable, RF-10): margen real
     * por artículo de la compra; solo quedan los que están bajo el objetivo.
     */
    public function recalcular(): void
    {
        $compra = Compra::with('detalles.articulo')->findOrFail($this->compraId);
        $costoService = app(CostoService::class);

        $this->filas = $compra->detalles->pluck('articulo')
            ->filter()
            ->unique('id')
            ->map(function (Articulo $articulo) use ($costoService) {
                $margen = $costoService->margenReal($articulo, $this->sucursalId);

                if ($margen === null || $margen['margen_real'] >= $margen['utilidad_objetivo']) {
                    return null;
                }

                $sugerido = $costoService->precioSugerido($articulo, $this->sucursalId, null, $this->tipoRedondeo);

                // Alcance RF-10: override de la sucursal si existe, si no el global.
                $tieneOverride = DB::connection('pymes_tenant')->table('articulos_sucursales')
                    ->where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $this->sucursalId)
                    ->whereNotNull('precio_base')
                    ->exists();

                return [
                    'articulo_id' => $articulo->id,
                    'nombre' => $articulo->nombre,
                    'codigo' => $articulo->codigo,
                    'costo' => $margen['costo_rector'],
                    'precio_actual' => $margen['precio_final'],
                    'margen_real' => round($margen['margen_real'], 1),
                    'objetivo' => round($margen['utilidad_objetivo'], 1),
                    'sugerido' => $sugerido !== null ? round($sugerido, 2) : null,
                    'precio_nuevo' => $sugerido !== null ? (string) round($sugerido, 2) : '',
                    'seleccionado' => true,
                    'alcance' => $tieneOverride ? 'sucursal' : 'global',
                ];
            })
            ->filter()
            ->values()
            ->all();

        $this->cargada = true;
    }

    /** Cambiar el redondeo re-sugiere todos los precios nuevos. */
    public function updatedTipoRedondeo(): void
    {
        $this->recalcular();
    }

    public function toggleTodas(bool $valor): void
    {
        foreach ($this->filas as $i => $fila) {
            $this->filas[$i]['seleccionado'] = $valor;
        }
    }

    public function puedeAplicar(): bool
    {
        return (bool) auth()->user()?->hasPermissionTo('func.compras.revisar_precios');
    }

    /**
     * Aplica en lote los precios seleccionados: override sucursal u global
     * según el alcance de cada fila + HistorialPrecio origen 'revision_compra'.
     */
    public function aplicar(): void
    {
        if (! $this->puedeAplicar()) {
            $this->dispatch('notify', type: 'error', message: __('No tenés permiso para aplicar la revisión de precios'));

            return;
        }

        $aAplicar = collect($this->filas)->filter(function ($fila) {
            $nuevo = (float) str_replace(',', '.', (string) $fila['precio_nuevo']);

            return $fila['seleccionado'] && $nuevo > 0 && round($nuevo, 2) !== round((float) $fila['precio_actual'], 2);
        });

        if ($aAplicar->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: __('No hay precios seleccionados para aplicar'));

            return;
        }

        try {
            DB::connection('pymes_tenant')->transaction(function () use ($aAplicar) {
                foreach ($aAplicar as $fila) {
                    $nuevo = round((float) str_replace(',', '.', (string) $fila['precio_nuevo']), 2);

                    if ($fila['alcance'] === 'sucursal') {
                        DB::connection('pymes_tenant')->table('articulos_sucursales')
                            ->where('articulo_id', $fila['articulo_id'])
                            ->where('sucursal_id', $this->sucursalId)
                            ->update(['precio_base' => $nuevo]);
                    } else {
                        Articulo::whereKey($fila['articulo_id'])->update(['precio_base' => $nuevo]);
                    }

                    HistorialPrecio::registrar([
                        'articulo_id' => $fila['articulo_id'],
                        'sucursal_id' => $fila['alcance'] === 'sucursal' ? $this->sucursalId : null,
                        'precio_anterior' => (float) $fila['precio_actual'],
                        'precio_nuevo' => $nuevo,
                        'origen' => 'revision_compra',
                        'detalle' => __('Revisión post-compra :numero', ['numero' => Compra::find($this->compraId)?->numero_comprobante]),
                    ]);
                }
            });

            $this->dispatch('notify', type: 'success', message: trans_choice(
                ':n precio actualizado|:n precios actualizados',
                $aAplicar->count(),
                ['n' => $aAplicar->count()],
            ));

            // Retomable: recalcular contra los nuevos vigentes (los que
            // quedaron sobre el objetivo desaparecen de la lista).
            $this->recalcular();
        } catch (Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cerrar(): void
    {
        $this->dispatch('cerrar-revision-precios');
    }

    public function render()
    {
        return view('livewire.compras.revision-precios-compra', [
            'compra' => Compra::with('proveedor:id,nombre')->find($this->compraId),
        ]);
    }
}
