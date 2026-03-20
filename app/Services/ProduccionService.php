<?php

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\Produccion;
use App\Models\ProduccionDetalle;
use App\Models\ProduccionIngrediente;
use App\Models\Stock;
use App\Models\Sucursal;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProduccionService
{
    /**
     * Valida stock de ingredientes según control_stock_produccion de la sucursal.
     * Consolida ingredientes de toda la cola.
     *
     * @param  array  $cola  [{articulo_id, cantidad, ingredientes: [{articulo_id, cantidad_real}]}]
     * @return array ['ok' => bool, 'faltantes' => [...], 'modo' => string]
     */
    public function validarStock(array $cola, int $sucursalId): array
    {
        $sucursal = Sucursal::findOrFail($sucursalId);
        $modo = $sucursal->control_stock_produccion ?? 'bloquea';

        if ($modo === 'no_controla') {
            return ['ok' => true, 'faltantes' => [], 'modo' => $modo];
        }

        // Consolidar ingredientes totales
        $ingredientesTotales = [];
        foreach ($cola as $item) {
            foreach ($item['ingredientes'] as $ing) {
                $artId = $ing['articulo_id'];
                if (! isset($ingredientesTotales[$artId])) {
                    $ingredientesTotales[$artId] = 0;
                }
                $ingredientesTotales[$artId] += (float) $ing['cantidad_real'];
            }
        }

        // Verificar disponibilidad
        $faltantes = [];
        foreach ($ingredientesTotales as $articuloId => $cantidadNecesaria) {
            $stock = Stock::where('articulo_id', $articuloId)
                ->where('sucursal_id', $sucursalId)
                ->first();

            $disponible = $stock ? (float) $stock->cantidad : 0;

            if ($disponible < $cantidadNecesaria) {
                $faltantes[] = [
                    'articulo_id' => $articuloId,
                    'necesario' => $cantidadNecesaria,
                    'disponible' => $disponible,
                    'diferencia' => $cantidadNecesaria - $disponible,
                ];
            }
        }

        $ok = empty($faltantes) || $modo === 'advierte';

        return [
            'ok' => $ok,
            'faltantes' => $faltantes,
            'modo' => $modo,
        ];
    }

    /**
     * Confirma una producción (lote o individual).
     *
     * @param  array  $cola  [{articulo_id, cantidad, receta_id, cantidad_receta, ingredientes: [{articulo_id, cantidad_receta, cantidad_real}]}]
     * @return array ['produccion' => Produccion, 'advertencias' => [...]]
     *
     * @throws Exception
     */
    public function confirmarProduccion(array $cola, int $sucursalId, int $usuarioId, ?string $observaciones = null): array
    {
        // Validar stock
        $validacion = $this->validarStock($cola, $sucursalId);

        if (! $validacion['ok']) {
            throw new Exception('Stock insuficiente de ingredientes para producir.');
        }

        $advertencias = [];
        if (! empty($validacion['faltantes']) && $validacion['modo'] === 'advierte') {
            $advertencias = $validacion['faltantes'];
        }

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Crear cabecera
            $produccion = Produccion::create([
                'sucursal_id' => $sucursalId,
                'usuario_id' => $usuarioId,
                'fecha' => now()->toDateString(),
                'estado' => 'confirmado',
                'observaciones' => $observaciones,
            ]);

            // Por cada artículo en la cola
            foreach ($cola as $item) {
                $detalle = ProduccionDetalle::create([
                    'produccion_id' => $produccion->id,
                    'articulo_id' => $item['articulo_id'],
                    'receta_id' => $item['receta_id'],
                    'cantidad_producida' => $item['cantidad'],
                    'cantidad_receta' => $item['cantidad_receta'],
                ]);

                // Procesar ingredientes (salida de stock)
                foreach ($item['ingredientes'] as $ing) {
                    ProduccionIngrediente::create([
                        'produccion_detalle_id' => $detalle->id,
                        'articulo_id' => $ing['articulo_id'],
                        'cantidad_receta' => $ing['cantidad_receta'],
                        'cantidad_real' => $ing['cantidad_real'],
                    ]);

                    // Disminuir stock del ingrediente
                    $stockIng = Stock::where('articulo_id', $ing['articulo_id'])
                        ->where('sucursal_id', $sucursalId)
                        ->first();

                    if ($stockIng) {
                        $stockIng->disminuir((float) $ing['cantidad_real'], true);
                    }

                    // Crear movimiento de salida
                    MovimientoStock::crearMovimientoProduccionSalida(
                        $ing['articulo_id'],
                        $sucursalId,
                        (float) $ing['cantidad_real'],
                        $produccion->id,
                        "Producción #{$produccion->id}: ingrediente consumido",
                        $usuarioId
                    );
                }

                // Aumentar stock del producto terminado
                $stockProd = Stock::firstOrCreate(
                    ['articulo_id' => $item['articulo_id'], 'sucursal_id' => $sucursalId],
                    ['cantidad' => 0, 'ultima_actualizacion' => now()]
                );
                $stockProd->aumentar((float) $item['cantidad']);

                // Crear movimiento de entrada
                MovimientoStock::crearMovimientoProduccionEntrada(
                    $item['articulo_id'],
                    $sucursalId,
                    (float) $item['cantidad'],
                    $produccion->id,
                    "Producción #{$produccion->id}: producto terminado",
                    $usuarioId
                );
            }

            DB::connection('pymes_tenant')->commit();

            Log::info('Producción confirmada', [
                'produccion_id' => $produccion->id,
                'sucursal_id' => $sucursalId,
                'usuario_id' => $usuarioId,
                'items' => count($cola),
            ]);

            return [
                'produccion' => $produccion->load('detalles.ingredientes'),
                'advertencias' => $advertencias,
            ];

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al confirmar producción', [
                'sucursal_id' => $sucursalId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Anula una producción con contraasiento.
     *
     * @throws Exception
     */
    public function anularProduccion(int $produccionId, int $usuarioId, string $motivo): Produccion
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $produccion = Produccion::with('detalles.ingredientes')->findOrFail($produccionId);

            if ($produccion->estaAnulada()) {
                throw new Exception('Esta producción ya está anulada.');
            }

            // Marcar como anulada
            $produccion->update([
                'estado' => 'anulado',
                'anulado_por_usuario_id' => $usuarioId,
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            $sucursalId = $produccion->sucursal_id;

            // Revertir movimientos por cada detalle
            foreach ($produccion->detalles as $detalle) {
                // Revertir producto terminado (disminuir stock)
                $stockProd = Stock::where('articulo_id', $detalle->articulo_id)
                    ->where('sucursal_id', $sucursalId)
                    ->first();

                if ($stockProd) {
                    $stockProd->disminuir((float) $detalle->cantidad_producida, true);
                }

                // Contraasiento de entrada
                MovimientoStock::crearMovimientoProduccionSalida(
                    $detalle->articulo_id,
                    $sucursalId,
                    (float) $detalle->cantidad_producida,
                    $produccion->id,
                    "Anulación producción #{$produccion->id}: revertir producto terminado",
                    $usuarioId
                );

                // Revertir ingredientes (aumentar stock)
                foreach ($detalle->ingredientes as $ing) {
                    $stockIng = Stock::where('articulo_id', $ing->articulo_id)
                        ->where('sucursal_id', $sucursalId)
                        ->first();

                    if ($stockIng) {
                        $stockIng->aumentar((float) $ing->cantidad_real);
                    }

                    // Contraasiento de salida
                    MovimientoStock::crearMovimientoProduccionEntrada(
                        $ing->articulo_id,
                        $sucursalId,
                        (float) $ing->cantidad_real,
                        $produccion->id,
                        "Anulación producción #{$produccion->id}: revertir ingrediente",
                        $usuarioId
                    );
                }
            }

            DB::connection('pymes_tenant')->commit();

            Log::info('Producción anulada', [
                'produccion_id' => $produccion->id,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
            ]);

            return $produccion->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al anular producción', [
                'produccion_id' => $produccionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
