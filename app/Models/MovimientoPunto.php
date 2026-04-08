<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoPunto extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'movimientos_puntos';

    protected $fillable = [
        'cliente_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'puntos',
        'monto_asociado',
        'documento_tipo',
        'documento_id',
        'venta_id',
        'venta_pago_id',
        'cupon_id',
        'concepto',
        'observaciones',
        'estado',
        'anulado_por_movimiento_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'puntos' => 'integer',
        'monto_asociado' => 'decimal:2',
    ];

    // --- Constantes de tipo ---

    public const TIPO_ACUMULACION = 'acumulacion';

    public const TIPO_CANJE_DESCUENTO = 'canje_descuento';

    public const TIPO_CANJE_ARTICULO = 'canje_articulo';

    public const TIPO_CANJE_CUPON = 'canje_cupon';

    public const TIPO_AJUSTE_MANUAL = 'ajuste_manual';

    public const TIPO_ANULACION = 'anulacion';

    public const DOC_VENTA = 'venta';

    public const DOC_VENTA_PAGO = 'venta_pago';

    public const DOC_CUPON = 'cupon';

    public const DOC_AJUSTE = 'ajuste';

    // --- Relaciones ---

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaPago(): BelongsTo
    {
        return $this->belongsTo(VentaPago::class);
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function anuladoPorMovimiento(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anulado_por_movimiento_id');
    }

    // --- Scopes ---

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopePorCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // --- Factory Methods (patrón MovimientoCuentaCorriente) ---

    public static function crearMovimientoAcumulacion(
        int $clienteId,
        int $sucursalId,
        int $puntos,
        float $montoAsociado,
        int $ventaId,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'fecha' => now(),
            'tipo' => self::TIPO_ACUMULACION,
            'puntos' => $puntos,
            'monto_asociado' => $montoAsociado,
            'documento_tipo' => self::DOC_VENTA,
            'documento_id' => $ventaId,
            'venta_id' => $ventaId,
            'concepto' => "Puntos por venta #{$ventaId}",
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);
    }

    public static function crearMovimientoCanjeDescuento(
        int $clienteId,
        int $sucursalId,
        int $puntosUsados,
        float $montoDescuento,
        int $ventaPagoId,
        int $ventaId,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'fecha' => now(),
            'tipo' => self::TIPO_CANJE_DESCUENTO,
            'puntos' => -$puntosUsados,
            'monto_asociado' => $montoDescuento,
            'documento_tipo' => self::DOC_VENTA_PAGO,
            'documento_id' => $ventaPagoId,
            'venta_id' => $ventaId,
            'venta_pago_id' => $ventaPagoId,
            'concepto' => "Canje puntos como pago - Venta #{$ventaId}",
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);
    }

    public static function crearMovimientoCanjeArticulo(
        int $clienteId,
        int $sucursalId,
        int $puntosUsados,
        int $articuloId,
        int $ventaId,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'fecha' => now(),
            'tipo' => self::TIPO_CANJE_ARTICULO,
            'puntos' => -$puntosUsados,
            'monto_asociado' => 0,
            'documento_tipo' => self::DOC_VENTA,
            'documento_id' => $ventaId,
            'venta_id' => $ventaId,
            'concepto' => "Canje artículo #{$articuloId} con {$puntosUsados} puntos - Venta #{$ventaId}",
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);
    }

    public static function crearMovimientoCanjeCupon(
        int $clienteId,
        int $sucursalId,
        int $puntosUsados,
        int $cuponId,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'fecha' => now(),
            'tipo' => self::TIPO_CANJE_CUPON,
            'puntos' => -$puntosUsados,
            'monto_asociado' => 0,
            'documento_tipo' => self::DOC_CUPON,
            'documento_id' => $cuponId,
            'cupon_id' => $cuponId,
            'concepto' => "Canje puntos por cupón #{$cuponId}",
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);
    }

    public static function crearMovimientoAjusteManual(
        int $clienteId,
        int $sucursalId,
        int $puntos,
        string $concepto,
        ?string $observaciones,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'fecha' => now(),
            'tipo' => self::TIPO_AJUSTE_MANUAL,
            'puntos' => $puntos,
            'monto_asociado' => 0,
            'documento_tipo' => self::DOC_AJUSTE,
            'documento_id' => null,
            'concepto' => $concepto,
            'observaciones' => $observaciones,
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un contraasiento para anular un movimiento (patrón append-only).
     * Ambos movimientos permanecen con estado 'activo' — se cancelan matemáticamente.
     */
    public static function crearContraasiento(
        self $movimientoOriginal,
        string $motivo,
        int $usuarioId
    ): self {
        $contraasiento = static::create([
            'cliente_id' => $movimientoOriginal->cliente_id,
            'sucursal_id' => $movimientoOriginal->sucursal_id,
            'fecha' => now(),
            'tipo' => self::TIPO_ANULACION,
            'puntos' => -$movimientoOriginal->puntos, // Invertido
            'monto_asociado' => $movimientoOriginal->monto_asociado,
            'documento_tipo' => $movimientoOriginal->documento_tipo,
            'documento_id' => $movimientoOriginal->documento_id,
            'venta_id' => $movimientoOriginal->venta_id,
            'venta_pago_id' => $movimientoOriginal->venta_pago_id,
            'cupon_id' => $movimientoOriginal->cupon_id,
            'concepto' => "Anulación: {$movimientoOriginal->concepto}",
            'observaciones' => $motivo,
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);

        // Vincular original con contraasiento para trazabilidad
        $movimientoOriginal->update([
            'anulado_por_movimiento_id' => $contraasiento->id,
        ]);

        return $contraasiento;
    }

    // --- Métodos de consulta estáticos ---

    /**
     * Calcula saldo de puntos de un cliente (suma de todos los movimientos activos).
     */
    public static function calcularSaldo(int $clienteId, ?int $sucursalId = null): int
    {
        $query = static::where('cliente_id', $clienteId)
            ->where('estado', 'activo');

        if ($sucursalId !== null) {
            $query->where('sucursal_id', $sucursalId);
        }

        return (int) $query->sum('puntos');
    }

    /**
     * Calcula totales históricos de un cliente.
     */
    public static function calcularTotales(int $clienteId, ?int $sucursalId = null): array
    {
        $query = static::where('cliente_id', $clienteId)
            ->where('estado', 'activo');

        if ($sucursalId !== null) {
            $query->where('sucursal_id', $sucursalId);
        }

        $acumulados = (int) (clone $query)->where('puntos', '>', 0)->sum('puntos');
        $canjeados = (int) abs((clone $query)->where('puntos', '<', 0)->sum('puntos'));

        return [
            'acumulados' => $acumulados,
            'canjeados' => $canjeados,
            'saldo' => $acumulados - $canjeados,
        ];
    }

    // --- Métodos de instancia ---

    public function esAcumulacion(): bool
    {
        return $this->tipo === self::TIPO_ACUMULACION;
    }

    public function esCanje(): bool
    {
        return in_array($this->tipo, [self::TIPO_CANJE_DESCUENTO, self::TIPO_CANJE_ARTICULO, self::TIPO_CANJE_CUPON]);
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_por_movimiento_id !== null;
    }
}
