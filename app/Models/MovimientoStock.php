<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo MovimientoStock
 *
 * Representa un movimiento en el historial de stock de un artículo.
 * Tabla append-only con contraasientos para anulaciones (mismo patrón que MovimientoCuentaCorriente).
 *
 * Stock calculado = SUM(entrada) - SUM(salida) de movimientos activos
 * Stock a fecha = último stock_resultante donde fecha <= $fecha (o calculado)
 *
 * @property int $id
 * @property int $articulo_id
 * @property int $sucursal_id
 * @property \Carbon\Carbon $fecha
 * @property string $tipo
 * @property float $entrada
 * @property float $salida
 * @property float $stock_resultante
 * @property string|null $documento_tipo
 * @property int|null $documento_id
 * @property int|null $venta_id
 * @property int|null $venta_detalle_id
 * @property int|null $compra_id
 * @property int|null $compra_detalle_id
 * @property int|null $transferencia_stock_id
 * @property string $concepto
 * @property string|null $observaciones
 * @property float|null $costo_unitario
 * @property string $estado
 * @property int|null $anulado_por_movimiento_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MovimientoStock extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'movimientos_stock';

    // Tipos de movimiento
    public const TIPO_VENTA = 'venta';
    public const TIPO_COMPRA = 'compra';
    public const TIPO_AJUSTE_MANUAL = 'ajuste_manual';
    public const TIPO_INVENTARIO_FISICO = 'inventario_fisico';
    public const TIPO_TRANSFERENCIA_SALIDA = 'transferencia_salida';
    public const TIPO_TRANSFERENCIA_ENTRADA = 'transferencia_entrada';
    public const TIPO_DEVOLUCION = 'devolucion';
    public const TIPO_ANULACION_VENTA = 'anulacion_venta';
    public const TIPO_ANULACION_COMPRA = 'anulacion_compra';
    public const TIPO_CARGA_INICIAL = 'carga_inicial';

    // Tipos de documento
    public const DOC_VENTA = 'venta';
    public const DOC_VENTA_DETALLE = 'venta_detalle';
    public const DOC_COMPRA = 'compra';
    public const DOC_COMPRA_DETALLE = 'compra_detalle';
    public const DOC_TRANSFERENCIA = 'transferencia';
    public const DOC_AJUSTE = 'ajuste';
    public const DOC_INVENTARIO = 'inventario';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'entrada',
        'salida',
        'stock_resultante',
        'documento_tipo',
        'documento_id',
        'venta_id',
        'venta_detalle_id',
        'compra_id',
        'compra_detalle_id',
        'transferencia_stock_id',
        'concepto',
        'observaciones',
        'costo_unitario',
        'estado',
        'anulado_por_movimiento_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'entrada' => 'decimal:2',
        'salida' => 'decimal:2',
        'stock_resultante' => 'decimal:2',
        'costo_unitario' => 'decimal:4',
    ];

    // ==================== Relaciones ====================

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class);
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaStock::class, 'transferencia_stock_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function anuladoPorMovimiento(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anulado_por_movimiento_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeAnulados($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopePorArticulo($query, int $articuloId)
    {
        return $query->where('articulo_id', $articuloId);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        if ($desde) {
            $query->where('fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->where('fecha', '<=', $hasta);
        }
        return $query;
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // ==================== Métodos de instancia ====================

    public function esActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function esAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    public function esEntrada(): bool
    {
        return $this->entrada > 0;
    }

    public function esSalida(): bool
    {
        return $this->salida > 0;
    }

    /**
     * Efecto neto del movimiento en el stock (+entrada, -salida)
     */
    public function getEfectoNetoAttribute(): float
    {
        return (float) $this->entrada - (float) $this->salida;
    }

    // ==================== Métodos estáticos de cálculo ====================

    /**
     * Calcula el stock actual de un artículo en una sucursal
     * sumando entradas y restando salidas de movimientos activos
     */
    public static function calcularStock(int $articuloId, int $sucursalId): float
    {
        return (float) static::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(entrada), 0) - COALESCE(SUM(salida), 0) as stock')
            ->value('stock') ?? 0;
    }

    /**
     * Calcula el stock a una fecha determinada
     */
    public static function calcularStockAFecha(int $articuloId, int $sucursalId, $fecha): float
    {
        return (float) static::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->where('fecha', '<=', $fecha)
            ->selectRaw('COALESCE(SUM(entrada), 0) - COALESCE(SUM(salida), 0) as stock')
            ->value('stock') ?? 0;
    }

    /**
     * Obtiene movimientos de un artículo en una sucursal entre fechas
     */
    public static function obtenerMovimientos(int $articuloId, int $sucursalId, $desde = null, $hasta = null)
    {
        return static::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->activos()
            ->entreFechas($desde, $hasta)
            ->with(['usuario', 'venta', 'compra', 'transferencia'])
            ->orderBy('id', 'desc')
            ->get();
    }

    // ==================== Métodos estáticos de creación ====================

    /**
     * Obtiene el stock_resultante para un nuevo movimiento.
     * Lee directamente de la tabla stock (cache), que siempre se actualiza
     * ANTES de crear el registro de movimiento.
     */
    protected static function calcularStockResultante(int $articuloId, int $sucursalId, float $entrada, float $salida): float
    {
        $stockActual = Stock::where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->value('cantidad');

        return (float) ($stockActual ?? 0);
    }

    /**
     * Crea un movimiento de venta (salida de stock)
     */
    public static function crearMovimientoVenta(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $ventaId,
        int $ventaDetalleId,
        string $concepto,
        int $usuarioId,
        ?float $costoUnitario = null
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, 0, $cantidad);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_VENTA,
            'entrada' => 0,
            'salida' => $cantidad,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_VENTA_DETALLE,
            'documento_id' => $ventaDetalleId,
            'venta_id' => $ventaId,
            'venta_detalle_id' => $ventaDetalleId,
            'concepto' => $concepto,
            'costo_unitario' => $costoUnitario,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de anulación de venta (entrada de stock)
     */
    public static function crearMovimientoAnulacionVenta(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $ventaId,
        int $ventaDetalleId,
        string $concepto,
        int $usuarioId
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, $cantidad, 0);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_ANULACION_VENTA,
            'entrada' => $cantidad,
            'salida' => 0,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_VENTA_DETALLE,
            'documento_id' => $ventaDetalleId,
            'venta_id' => $ventaId,
            'venta_detalle_id' => $ventaDetalleId,
            'concepto' => $concepto,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de compra (entrada de stock)
     */
    public static function crearMovimientoCompra(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $compraId,
        int $compraDetalleId,
        string $concepto,
        int $usuarioId,
        ?float $costoUnitario = null
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, $cantidad, 0);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_COMPRA,
            'entrada' => $cantidad,
            'salida' => 0,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_COMPRA_DETALLE,
            'documento_id' => $compraDetalleId,
            'compra_id' => $compraId,
            'compra_detalle_id' => $compraDetalleId,
            'concepto' => $concepto,
            'costo_unitario' => $costoUnitario,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de anulación de compra (salida de stock)
     */
    public static function crearMovimientoAnulacionCompra(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $compraId,
        int $compraDetalleId,
        string $concepto,
        int $usuarioId
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, 0, $cantidad);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_ANULACION_COMPRA,
            'entrada' => 0,
            'salida' => $cantidad,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_COMPRA_DETALLE,
            'documento_id' => $compraDetalleId,
            'compra_id' => $compraId,
            'compra_detalle_id' => $compraDetalleId,
            'concepto' => $concepto,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de ajuste manual
     */
    public static function crearMovimientoAjuste(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        string $concepto,
        int $usuarioId,
        ?string $observaciones = null
    ): self {
        $entrada = $cantidad > 0 ? $cantidad : 0;
        $salida = $cantidad < 0 ? abs($cantidad) : 0;
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, $entrada, $salida);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_AJUSTE_MANUAL,
            'entrada' => $entrada,
            'salida' => $salida,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_AJUSTE,
            'documento_id' => null,
            'concepto' => $concepto,
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de inventario físico (ajuste por diferencia)
     */
    public static function crearMovimientoInventarioFisico(
        int $articuloId,
        int $sucursalId,
        float $cantidadAnterior,
        float $cantidadFisica,
        int $usuarioId,
        ?string $observaciones = null
    ): self {
        $diferencia = $cantidadFisica - $cantidadAnterior;
        $entrada = $diferencia > 0 ? $diferencia : 0;
        $salida = $diferencia < 0 ? abs($diferencia) : 0;

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_INVENTARIO_FISICO,
            'entrada' => $entrada,
            'salida' => $salida,
            'stock_resultante' => $cantidadFisica,
            'documento_tipo' => self::DOC_INVENTARIO,
            'documento_id' => null,
            'concepto' => "Inventario físico: {$cantidadAnterior} → {$cantidadFisica}",
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de transferencia salida
     */
    public static function crearMovimientoTransferenciaSalida(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $transferenciaId,
        string $concepto,
        int $usuarioId
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, 0, $cantidad);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_TRANSFERENCIA_SALIDA,
            'entrada' => 0,
            'salida' => $cantidad,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_TRANSFERENCIA,
            'documento_id' => $transferenciaId,
            'transferencia_stock_id' => $transferenciaId,
            'concepto' => $concepto,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de transferencia entrada
     */
    public static function crearMovimientoTransferenciaEntrada(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        int $transferenciaId,
        string $concepto,
        int $usuarioId
    ): self {
        $stockResultante = static::calcularStockResultante($articuloId, $sucursalId, $cantidad, 0);

        return static::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'fecha' => now()->toDateString(),
            'tipo' => self::TIPO_TRANSFERENCIA_ENTRADA,
            'entrada' => $cantidad,
            'salida' => 0,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => self::DOC_TRANSFERENCIA,
            'documento_id' => $transferenciaId,
            'transferencia_stock_id' => $transferenciaId,
            'concepto' => $concepto,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un contraasiento para anular un movimiento
     * Mismo patrón que MovimientoCuentaCorriente: invierte entrada/salida
     */
    public static function crearContraasiento(
        self $movimientoOriginal,
        string $motivo,
        int $usuarioId
    ): self {
        $tipoAnulacion = match ($movimientoOriginal->tipo) {
            self::TIPO_VENTA => self::TIPO_ANULACION_VENTA,
            self::TIPO_COMPRA => self::TIPO_ANULACION_COMPRA,
            default => self::TIPO_AJUSTE_MANUAL,
        };

        // El contraasiento invierte entrada/salida
        $entrada = (float) $movimientoOriginal->salida;
        $salida = (float) $movimientoOriginal->entrada;
        $stockResultante = static::calcularStockResultante(
            $movimientoOriginal->articulo_id,
            $movimientoOriginal->sucursal_id,
            $entrada,
            $salida
        );

        $contraasiento = static::create([
            'articulo_id' => $movimientoOriginal->articulo_id,
            'sucursal_id' => $movimientoOriginal->sucursal_id,
            'fecha' => now()->toDateString(),
            'tipo' => $tipoAnulacion,
            'entrada' => $entrada,
            'salida' => $salida,
            'stock_resultante' => $stockResultante,
            'documento_tipo' => $movimientoOriginal->documento_tipo,
            'documento_id' => $movimientoOriginal->documento_id,
            'venta_id' => $movimientoOriginal->venta_id,
            'venta_detalle_id' => $movimientoOriginal->venta_detalle_id,
            'compra_id' => $movimientoOriginal->compra_id,
            'compra_detalle_id' => $movimientoOriginal->compra_detalle_id,
            'transferencia_stock_id' => $movimientoOriginal->transferencia_stock_id,
            'concepto' => "Anulación: {$movimientoOriginal->concepto}",
            'observaciones' => $motivo,
            'usuario_id' => $usuarioId,
        ]);

        // Vincular el original con su contraasiento (trazabilidad)
        $movimientoOriginal->update([
            'anulado_por_movimiento_id' => $contraasiento->id,
        ]);

        return $contraasiento;
    }

    // ==================== Helpers de presentación ====================

    /**
     * Retorna el color de badge según el tipo de movimiento
     */
    public function getBadgeColorAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_VENTA => 'red',
            self::TIPO_COMPRA => 'green',
            self::TIPO_AJUSTE_MANUAL => 'blue',
            self::TIPO_INVENTARIO_FISICO => 'purple',
            self::TIPO_TRANSFERENCIA_SALIDA => 'orange',
            self::TIPO_TRANSFERENCIA_ENTRADA => 'teal',
            self::TIPO_ANULACION_VENTA => 'pink',
            self::TIPO_ANULACION_COMPRA => 'yellow',
            self::TIPO_DEVOLUCION => 'indigo',
            self::TIPO_CARGA_INICIAL => 'gray',
            default => 'gray',
        };
    }

    /**
     * Retorna etiqueta legible del tipo
     */
    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_VENTA => __('Venta'),
            self::TIPO_COMPRA => __('Compra'),
            self::TIPO_AJUSTE_MANUAL => __('Ajuste Manual'),
            self::TIPO_INVENTARIO_FISICO => __('Inventario Físico'),
            self::TIPO_TRANSFERENCIA_SALIDA => __('Transferencia Salida'),
            self::TIPO_TRANSFERENCIA_ENTRADA => __('Transferencia Entrada'),
            self::TIPO_ANULACION_VENTA => __('Anulación Venta'),
            self::TIPO_ANULACION_COMPRA => __('Anulación Compra'),
            self::TIPO_DEVOLUCION => __('Devolución'),
            self::TIPO_CARGA_INICIAL => __('Carga Inicial'),
            default => $this->tipo,
        };
    }
}
