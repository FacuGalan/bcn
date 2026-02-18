<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Articulo;
use App\Models\Sucursal;
use App\Models\MovimientoStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

/**
 * Servicio de Stock
 *
 * Maneja toda la lógica de negocio relacionada con inventario:
 * - Ajustes de stock (aumentos/disminuciones manuales)
 * - Reportes de stock (bajo mínimo, sobre máximo, sin stock)
 * - Consultas y validaciones de inventario
 * - Inicialización de stock para nuevos artículos
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class StockService
{
    /**
     * Realiza un ajuste manual de stock
     *
     * @param int $stockId
     * @param float $cantidad Positivo aumenta, negativo disminuye
     * @param int $usuarioId
     * @param string|null $motivo
     * @return Stock
     * @throws Exception
     */
    public function ajustarStock(int $stockId, float $cantidad, int $usuarioId, ?string $motivo = null): Stock
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $stock = Stock::findOrFail($stockId);

            // Validar que el ajuste no deje stock negativo
            $nuevoStock = $stock->cantidad + $cantidad;
            if ($nuevoStock < 0) {
                throw new Exception(
                    "El ajuste dejaría el stock en negativo. Stock actual: {$stock->cantidad}, " .
                    "Ajuste: {$cantidad}, Resultado: {$nuevoStock}"
                );
            }

            // Realizar el ajuste
            if (!$stock->ajustarStock($cantidad)) {
                throw new Exception('No se pudo realizar el ajuste de stock');
            }

            // Registrar movimiento de stock
            MovimientoStock::crearMovimientoAjuste(
                $stock->articulo_id,
                $stock->sucursal_id,
                $cantidad,
                'Ajuste manual',
                $usuarioId,
                $motivo ?: null
            );

            DB::connection('pymes_tenant')->commit();

            Log::info('Ajuste de stock realizado', [
                'stock_id' => $stock->id,
                'articulo_id' => $stock->articulo_id,
                'sucursal_id' => $stock->sucursal_id,
                'cantidad_anterior' => $stock->cantidad - $cantidad,
                'ajuste' => $cantidad,
                'cantidad_nueva' => $stock->cantidad,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
            ]);

            return $stock->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al ajustar stock', [
                'stock_id' => $stockId,
                'cantidad' => $cantidad,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Inicializa el stock para un artículo en todas las sucursales activas
     *
     * @param int $articuloId
     * @param float $cantidadInicial
     * @return Collection
     */
    public function inicializarStockEnSucursales(int $articuloId, float $cantidadInicial = 0): Collection
    {
        $articulo = Articulo::findOrFail($articuloId);

        $sucursales = Sucursal::activas()->get();
        $stocks = collect();

        foreach ($sucursales as $sucursal) {
            // Solo inicializar stock en sucursales donde el artículo controla stock
            if (!$articulo->controlaStock($sucursal->id)) {
                continue;
            }

            $stock = Stock::firstOrCreate(
                [
                    'articulo_id' => $articulo->id,
                    'sucursal_id' => $sucursal->id,
                ],
                [
                    'cantidad' => $cantidadInicial,
                    'cantidad_minima' => null,
                    'cantidad_maxima' => null,
                    'ultima_actualizacion' => now(),
                ]
            );

            $stocks->push($stock);
        }

        Log::info('Stock inicializado en sucursales', [
            'articulo_id' => $articulo->id,
            'cantidad_inicial' => $cantidadInicial,
            'sucursales' => $sucursales->count(),
        ]);

        return $stocks;
    }

    /**
     * Inicializa stock para un artículo en una sucursal específica
     *
     * @param int $articuloId
     * @param int $sucursalId
     * @param float $cantidadInicial
     * @param float|null $cantidadMinima
     * @param float|null $cantidadMaxima
     * @return Stock
     */
    public function inicializarStockEnSucursal(
        int $articuloId,
        int $sucursalId,
        float $cantidadInicial = 0,
        ?float $cantidadMinima = null,
        ?float $cantidadMaxima = null
    ): Stock {
        $articulo = Articulo::findOrFail($articuloId);

        if (!$articulo->controlaStock($sucursalId)) {
            throw new Exception('Este artículo no controla stock en esta sucursal');
        }

        $stock = Stock::firstOrCreate(
            [
                'articulo_id' => $articulo->id,
                'sucursal_id' => $sucursalId,
            ],
            [
                'cantidad' => $cantidadInicial,
                'cantidad_minima' => $cantidadMinima,
                'cantidad_maxima' => $cantidadMaxima,
                'ultima_actualizacion' => now(),
            ]
        );

        Log::info('Stock inicializado en sucursal', [
            'articulo_id' => $articulo->id,
            'sucursal_id' => $sucursalId,
            'cantidad_inicial' => $cantidadInicial,
        ]);

        return $stock;
    }

    /**
     * Actualiza los umbrales de stock (mínimo y máximo)
     *
     * @param int $stockId
     * @param float|null $cantidadMinima
     * @param float|null $cantidadMaxima
     * @return Stock
     */
    public function actualizarUmbrales(int $stockId, ?float $cantidadMinima, ?float $cantidadMaxima): Stock
    {
        $stock = Stock::findOrFail($stockId);

        $stock->cantidad_minima = $cantidadMinima;
        $stock->cantidad_maxima = $cantidadMaxima;
        $stock->save();

        Log::info('Umbrales de stock actualizados', [
            'stock_id' => $stock->id,
            'cantidad_minima' => $cantidadMinima,
            'cantidad_maxima' => $cantidadMaxima,
        ]);

        return $stock->fresh();
    }

    /**
     * Obtiene artículos con stock bajo el mínimo en una sucursal
     *
     * @param int $sucursalId
     * @return Collection
     */
    public function obtenerStockBajoMinimo(int $sucursalId): Collection
    {
        return Stock::with(['articulo', 'sucursal'])
                   ->porSucursal($sucursalId)
                   ->bajoMinimo()
                   ->get();
    }

    /**
     * Obtiene artículos con stock sobre el máximo en una sucursal
     *
     * @param int $sucursalId
     * @return Collection
     */
    public function obtenerStockSobreMaximo(int $sucursalId): Collection
    {
        return Stock::with(['articulo', 'sucursal'])
                   ->porSucursal($sucursalId)
                   ->sobreMaximo()
                   ->get();
    }

    /**
     * Obtiene artículos sin stock en una sucursal
     *
     * @param int $sucursalId
     * @return Collection
     */
    public function obtenerArticulosSinStock(int $sucursalId): Collection
    {
        return Stock::with(['articulo', 'sucursal'])
                   ->porSucursal($sucursalId)
                   ->where('cantidad', '<=', 0)
                   ->get();
    }

    /**
     * Obtiene el reporte consolidado de stock de todas las sucursales para un artículo
     *
     * @param int $articuloId
     * @return Collection
     */
    public function obtenerStockPorArticulo(int $articuloId): Collection
    {
        return Stock::with(['sucursal'])
                   ->porArticulo($articuloId)
                   ->get()
                   ->map(function ($stock) {
                       return [
                           'sucursal_id' => $stock->sucursal_id,
                           'sucursal_nombre' => $stock->sucursal->nombre,
                           'cantidad' => $stock->cantidad,
                           'cantidad_minima' => $stock->cantidad_minima,
                           'cantidad_maxima' => $stock->cantidad_maxima,
                           'necesita_reposicion' => $stock->necesitaReposicion(),
                           'esta_bajo_minimo' => $stock->estaBajoMinimo(),
                           'esta_sobre_maximo' => $stock->estaSobreMaximo(),
                           'ultima_actualizacion' => $stock->ultima_actualizacion,
                       ];
                   });
    }

    /**
     * Obtiene el stock total de un artículo (sumando todas las sucursales)
     *
     * @param int $articuloId
     * @return float
     */
    public function obtenerStockTotal(int $articuloId): float
    {
        return Stock::porArticulo($articuloId)->sum('cantidad');
    }

    /**
     * Verifica disponibilidad de un artículo en múltiples sucursales
     *
     * @param int $articuloId
     * @param float $cantidadNecesaria
     * @return array
     */
    public function verificarDisponibilidadEnSucursales(int $articuloId, float $cantidadNecesaria): array
    {
        $stocks = Stock::with('sucursal')
                     ->porArticulo($articuloId)
                     ->conExistencia()
                     ->get();

        $sucursalesDisponibles = [];
        $totalDisponible = 0;

        foreach ($stocks as $stock) {
            if ($stock->cantidad >= $cantidadNecesaria) {
                $sucursalesDisponibles[] = [
                    'sucursal_id' => $stock->sucursal_id,
                    'sucursal_nombre' => $stock->sucursal->nombre,
                    'cantidad_disponible' => $stock->cantidad,
                    'puede_satisfacer' => true,
                ];
            }

            $totalDisponible += $stock->cantidad;
        }

        return [
            'articulo_id' => $articuloId,
            'cantidad_necesaria' => $cantidadNecesaria,
            'total_disponible' => $totalDisponible,
            'puede_satisfacer_total' => $totalDisponible >= $cantidadNecesaria,
            'sucursales_con_stock_suficiente' => $sucursalesDisponibles,
            'cantidad_sucursales_disponibles' => count($sucursalesDisponibles),
        ];
    }

    /**
     * Realiza un inventario físico (ajuste total del stock)
     *
     * @param int $stockId
     * @param float $cantidadFisica
     * @param int $usuarioId
     * @param string|null $observaciones
     * @return array
     * @throws Exception
     */
    public function registrarInventarioFisico(
        int $stockId,
        float $cantidadFisica,
        int $usuarioId,
        ?string $observaciones = null
    ): array {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $resultado = $this->registrarInventarioFisicoInterno($stockId, $cantidadFisica, $usuarioId, $observaciones);

            DB::connection('pymes_tenant')->commit();

            Log::info('Inventario físico registrado', $resultado);

            return $resultado;

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al registrar inventario físico', [
                'stock_id' => $stockId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lógica interna de inventario físico SIN manejo de transacción.
     * Permite ser llamado desde una transacción externa (ej: inventario general bulk).
     *
     * @param int $stockId
     * @param float $cantidadFisica
     * @param int $usuarioId
     * @param string|null $observaciones
     * @return array
     */
    public function registrarInventarioFisicoInterno(
        int $stockId,
        float $cantidadFisica,
        int $usuarioId,
        ?string $observaciones = null
    ): array {
        $stock = Stock::findOrFail($stockId);

        $cantidadAnterior = $stock->cantidad;
        $diferencia = $cantidadFisica - $cantidadAnterior;

        // Actualizar el stock al valor físico
        $stock->cantidad = $cantidadFisica;
        $stock->ultima_actualizacion = now();
        $stock->save();

        // Registrar movimiento de stock (solo si hay diferencia)
        if ($diferencia != 0) {
            MovimientoStock::crearMovimientoInventarioFisico(
                $stock->articulo_id,
                $stock->sucursal_id,
                $cantidadAnterior,
                $cantidadFisica,
                $usuarioId,
                $observaciones
            );
        }

        return [
            'stock_id' => $stock->id,
            'articulo_id' => $stock->articulo_id,
            'sucursal_id' => $stock->sucursal_id,
            'cantidad_anterior' => $cantidadAnterior,
            'cantidad_fisica' => $cantidadFisica,
            'diferencia' => $diferencia,
            'tipo_diferencia' => $diferencia > 0 ? 'sobrante' : ($diferencia < 0 ? 'faltante' : 'sin_diferencia'),
            'porcentaje_diferencia' => $cantidadAnterior > 0 ? round(($diferencia / $cantidadAnterior) * 100, 2) : 0,
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
            'fecha' => now(),
        ];
    }

    /**
     * Obtiene artículos que necesitan reposición en una sucursal
     *
     * @param int $sucursalId
     * @return Collection
     */
    public function obtenerArticulosParaReposicion(int $sucursalId): Collection
    {
        return Stock::with(['articulo', 'sucursal'])
                   ->porSucursal($sucursalId)
                   ->bajoMinimo()
                   ->get()
                   ->map(function ($stock) {
                       $cantidadSugerida = $stock->cantidad_maxima
                           ? $stock->cantidad_maxima - $stock->cantidad
                           : ($stock->cantidad_minima * 2) - $stock->cantidad;

                       return [
                           'stock_id' => $stock->id,
                           'articulo_id' => $stock->articulo_id,
                           'articulo_nombre' => $stock->articulo->nombre,
                           'articulo_codigo' => $stock->articulo->codigo,
                           'cantidad_actual' => $stock->cantidad,
                           'cantidad_minima' => $stock->cantidad_minima,
                           'cantidad_maxima' => $stock->cantidad_maxima,
                           'cantidad_faltante' => $stock->cantidad_minima - $stock->cantidad,
                           'cantidad_sugerida_reposicion' => max(0, $cantidadSugerida),
                       ];
                   });
    }

    // ==================== Métodos de consulta de movimientos ====================

    /**
     * Obtiene movimientos de stock entre fechas para una sucursal
     */
    public function obtenerMovimientos(
        int $sucursalId,
        ?int $articuloId = null,
        ?string $tipo = null,
        $desde = null,
        $hasta = null
    ) {
        $query = MovimientoStock::with(['articulo', 'usuario', 'venta', 'compra', 'transferencia'])
            ->porSucursal($sucursalId)
            ->activos();

        if ($articuloId) {
            $query->porArticulo($articuloId);
        }

        if ($tipo) {
            $query->porTipo($tipo);
        }

        $query->entreFechas($desde, $hasta);

        return $query->orderBy('id', 'desc');
    }

    /**
     * Obtiene el stock de un artículo en una sucursal a una fecha determinada
     */
    public function obtenerStockAFecha(int $articuloId, int $sucursalId, $fecha): float
    {
        return MovimientoStock::calcularStockAFecha($articuloId, $sucursalId, $fecha);
    }

    /**
     * Obtiene el reporte kardex completo (movimientos con stock resultante)
     */
    public function obtenerKardex(int $articuloId, int $sucursalId, $desde = null, $hasta = null): Collection
    {
        return MovimientoStock::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->activos()
            ->entreFechas($desde, $hasta)
            ->with(['usuario', 'venta', 'compra', 'transferencia'])
            ->orderBy('id', 'asc')
            ->get();
    }
}
