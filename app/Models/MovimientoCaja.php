<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo MovimientoCaja
 *
 * Registra movimientos de EFECTIVO físico en una caja.
 * Solo se usa para dinero que entra/sale físicamente de la caja.
 *
 * Para el cierre de caja:
 * - Esta tabla controla el efectivo real
 * - Las ventas con otros medios de pago se consultan en ventas_pagos
 *
 * @property int $id
 * @property int $caja_id
 * @property string $tipo (ingreso|egreso)
 * @property string $concepto Descripción del movimiento
 * @property float $monto
 * @property int $usuario_id
 * @property string|null $referencia_tipo Tipo de entidad relacionada (venta, compra, cobro, etc.)
 * @property int|null $referencia_id ID de la entidad relacionada
 * @property int|null $cierre_turno_id ID del cierre de turno (si ya fue cerrado)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Caja $caja
 * @property-read User $usuario
 * @property-read CierreTurno|null $cierreTurno
 * @property-read Model|null $referencia Entidad relacionada (polimórfica)
 */
class MovimientoCaja extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'caja_id',
        'tipo',
        'concepto',
        'monto',
        'usuario_id',
        'referencia_tipo',
        'referencia_id',
        'cierre_turno_id',
        'moneda_id',
        'tipo_cambio_id',
        'tipo_cambio_tasa',
        'monto_moneda_original',
        'anulado_por_movimiento_id',  // FK logico al contraasiento que anula este movimiento
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_moneda_original' => 'decimal:2',
        'tipo_cambio_tasa' => 'decimal:6',
    ];

    // ==================== Constantes ====================

    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    public const REF_VENTA = 'venta';

    public const REF_COMPRA = 'compra';

    public const REF_COBRO = 'cobro';

    public const REF_PAGO_PROVEEDOR = 'pago_proveedor';

    public const REF_AJUSTE = 'ajuste';

    public const REF_APERTURA = 'apertura';

    public const REF_RETIRO = 'retiro';

    public const REF_TRANSFERENCIA = 'transferencia';

    public const REF_INGRESO_MANUAL = 'ingreso_manual';

    public const REF_EGRESO_MANUAL = 'egreso_manual';

    public const REF_VUELTO_VENTA = 'vuelto_venta';

    public const REF_VUELTO_COBRO = 'vuelto_cobro';

    public const REF_ANULACION_VENTA = 'anulacion_venta';

    public const REF_ANULACION_COBRO = 'anulacion_cobro';

    public const REF_PEDIDO_MOSTRADOR = 'pedido_mostrador';

    public const REF_ANULACION_PEDIDO_MOSTRADOR = 'anulacion_pedido_mostrador';

    public const REF_PEDIDO_DELIVERY = 'pedido_delivery';

    public const REF_ANULACION_PEDIDO_DELIVERY = 'anulacion_pedido_delivery';

    public const REF_FONDO_REPARTIDOR = 'fondo_repartidor';

    // ==================== Relaciones ====================

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function tipoCambio(): BelongsTo
    {
        return $this->belongsTo(TipoCambio::class, 'tipo_cambio_id');
    }

    /**
     * Relación polimórfica a la entidad referenciada
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
    }

    /**
     * Contraasiento que anula este movimiento (si fue anulado).
     */
    public function anuladoPorMovimiento(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anulado_por_movimiento_id');
    }

    /**
     * Movimiento original que este contraasiento anula (relación inversa).
     */
    public function anula(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'anulado_por_movimiento_id');
    }

    // ==================== Scopes ====================

    public function scopeIngresos($query)
    {
        return $query->where('tipo', self::TIPO_INGRESO);
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', self::TIPO_EGRESO);
    }

    public function scopePorCaja($query, int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    public function scopePorFecha($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }

        if ($hasta) {
            $query->where('created_at', '<=', $hasta);
        }

        return $query;
    }

    public function scopePorReferencia($query, string $tipo, ?int $id = null)
    {
        $query->where('referencia_tipo', $tipo);
        if ($id !== null) {
            $query->where('referencia_id', $id);
        }

        return $query;
    }

    public function scopeVentas($query)
    {
        return $query->where('referencia_tipo', self::REF_VENTA);
    }

    public function scopeAperturas($query)
    {
        return $query->where('referencia_tipo', self::REF_APERTURA);
    }

    public function scopeRetiros($query)
    {
        return $query->where('referencia_tipo', self::REF_RETIRO);
    }

    public function scopeAjustes($query)
    {
        return $query->where('referencia_tipo', self::REF_AJUSTE);
    }

    /**
     * Movimientos que aún no fueron cerrados
     */
    public function scopeNoCerrados($query)
    {
        return $query->whereNull('cierre_turno_id');
    }

    /**
     * Movimientos que ya fueron cerrados
     */
    public function scopeCerrados($query)
    {
        return $query->whereNotNull('cierre_turno_id');
    }

    /**
     * Movimientos de un cierre específico
     */
    public function scopeDelCierre($query, int $cierreTurnoId)
    {
        return $query->where('cierre_turno_id', $cierreTurnoId);
    }

    /**
     * Movimientos no anulados (sin contraasiento vinculado).
     */
    public function scopeNoAnulado($query)
    {
        return $query->whereNull('anulado_por_movimiento_id');
    }

    /**
     * Movimientos anulados (con contraasiento vinculado).
     */
    public function scopeAnulado($query)
    {
        return $query->whereNotNull('anulado_por_movimiento_id');
    }

    // ==================== Métodos auxiliares ====================

    /**
     * Verifica si es un ingreso
     */
    public function esIngreso(): bool
    {
        return $this->tipo === self::TIPO_INGRESO;
    }

    /**
     * Verifica si es un egreso
     */
    public function esEgreso(): bool
    {
        return $this->tipo === self::TIPO_EGRESO;
    }

    /**
     * Verifica si es una apertura de caja
     */
    public function esApertura(): bool
    {
        return $this->referencia_tipo === self::REF_APERTURA;
    }

    /**
     * Verifica si es un ajuste manual
     */
    public function esAjuste(): bool
    {
        return $this->referencia_tipo === self::REF_AJUSTE;
    }

    /**
     * Verifica si es un retiro
     */
    public function esRetiro(): bool
    {
        return $this->referencia_tipo === self::REF_RETIRO;
    }

    /**
     * Verifica si está asociado a una venta
     */
    public function esDeVenta(): bool
    {
        return $this->referencia_tipo === self::REF_VENTA;
    }

    /**
     * Verifica si está asociado a un cobro
     */
    public function esDeCobro(): bool
    {
        return $this->referencia_tipo === self::REF_COBRO;
    }

    /**
     * Verifica si este movimiento fue anulado por un contraasiento.
     */
    public function estaAnulado(): bool
    {
        return $this->anulado_por_movimiento_id !== null;
    }

    /**
     * Obtiene el monto con signo según el tipo de movimiento
     */
    public function obtenerMontoConSigno(): float
    {
        return $this->esIngreso() ? $this->monto : -$this->monto;
    }

    /**
     * Crea un movimiento de ingreso por venta en efectivo
     */
    public static function crearIngresoVenta(Caja $caja, Venta $venta, float $monto, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_INGRESO,
            'concepto' => "Venta #{$venta->numero} - Efectivo",
            'monto' => $monto,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REF_VENTA,
            'referencia_id' => $venta->id,
        ]);
    }

    /**
     * Crea un movimiento de ingreso por cobro en efectivo
     */
    public static function crearIngresoCobro(Caja $caja, $cobro, float $monto, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_INGRESO,
            'concepto' => "Cobro #{$cobro->id} - Efectivo",
            'monto' => $monto,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REF_COBRO,
            'referencia_id' => $cobro->id,
        ]);
    }

    /**
     * Egreso por pago a proveedor (espejo de crearIngresoCobro — spec
     * compras-costos RF-19/D14: no había factory de egreso).
     */
    public static function crearEgresoPagoProveedor(Caja $caja, PagoProveedor $pago, float $monto, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_EGRESO,
            'concepto' => __('Pago a proveedor — OP :numero', ['numero' => $pago->numero]),
            'monto' => $monto,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REF_PAGO_PROVEEDOR,
            'referencia_id' => $pago->id,
        ]);
    }

    /**
     * Crea un movimiento de apertura de caja
     */
    public static function crearApertura(Caja $caja, float $fondoInicial, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_INGRESO,
            'concepto' => 'Apertura de caja - Fondo inicial',
            'monto' => $fondoInicial,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REF_APERTURA,
            'referencia_id' => null,
        ]);
    }

    /**
     * Crea un movimiento de retiro de efectivo
     */
    public static function crearRetiro(Caja $caja, float $monto, string $motivo, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_EGRESO,
            'concepto' => "Retiro: {$motivo}",
            'monto' => $monto,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REF_RETIRO,
            'referencia_id' => null,
        ]);
    }

    /**
     * Crea un contraasiento que anula este movimiento (patrón append-only).
     *
     * Tanto el original como el contraasiento permanecen activos en BD y se
     * cancelan matemáticamente entre sí. El original queda vinculado al
     * contraasiento via `anulado_por_movimiento_id`.
     *
     * El contraasiento preserva moneda + tipo_cambio_id + tipo_cambio_tasa +
     * monto_moneda_original del original (snapshot inmutable).
     *
     * Ajusta el saldo de la caja según el tipo del contraasiento (responsabilidad
     * del factory, no del caller).
     *
     * @param  string  $referenciaTipo  REF_ANULACION_VENTA o REF_ANULACION_COBRO típicamente
     * @param  int|null  $referenciaId  Si null, usa el referencia_id del original
     * @param  string|null  $conceptoOverride  Si null, prefija "Anulación: " al concepto original
     */
    public static function crearContraasiento(
        self $movimientoOriginal,
        int $usuarioId,
        string $referenciaTipo,
        ?int $referenciaId = null,
        ?string $conceptoOverride = null
    ): self {
        $tipoInverso = $movimientoOriginal->tipo === self::TIPO_INGRESO
            ? self::TIPO_EGRESO
            : self::TIPO_INGRESO;

        $caja = $movimientoOriginal->caja;

        $contraasiento = static::create([
            'caja_id' => $movimientoOriginal->caja_id,
            'tipo' => $tipoInverso,
            'concepto' => $conceptoOverride ?? "Anulación: {$movimientoOriginal->concepto}",
            'monto' => $movimientoOriginal->monto,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId ?? $movimientoOriginal->referencia_id,
            'moneda_id' => $movimientoOriginal->moneda_id,
            'tipo_cambio_id' => $movimientoOriginal->tipo_cambio_id,
            'tipo_cambio_tasa' => $movimientoOriginal->tipo_cambio_tasa,
            'monto_moneda_original' => $movimientoOriginal->monto_moneda_original,
        ]);

        $movimientoOriginal->update([
            'anulado_por_movimiento_id' => $contraasiento->id,
        ]);

        if ($caja) {
            if ($tipoInverso === self::TIPO_EGRESO) {
                $caja->disminuirSaldo($movimientoOriginal->monto);
            } else {
                $caja->aumentarSaldo($movimientoOriginal->monto);
            }
        }

        return $contraasiento;
    }

    // ==================== MÉTODOS DE CIERRE ====================

    /**
     * Verifica si el movimiento ya fue cerrado
     */
    public function estaCerrado(): bool
    {
        return $this->cierre_turno_id !== null;
    }

    /**
     * Verifica si el movimiento aún no fue cerrado
     */
    public function estaPendiente(): bool
    {
        return $this->cierre_turno_id === null;
    }

    /**
     * Marca el movimiento como parte de un cierre
     */
    public function marcarComoCerrado(int $cierreTurnoId): bool
    {
        $this->cierre_turno_id = $cierreTurnoId;

        return $this->save();
    }
}
