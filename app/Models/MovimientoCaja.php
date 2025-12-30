<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\User;
use App\Models\Venta;
use App\Models\Caja;

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
 *
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
    ];

    protected $casts = [
        'monto' => 'decimal:2',
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

    /**
     * Relación polimórfica a la entidad referenciada
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
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
     * Crea un movimiento de apertura de caja
     */
    public static function crearApertura(Caja $caja, float $fondoInicial, int $usuarioId): self
    {
        return static::create([
            'caja_id' => $caja->id,
            'tipo' => self::TIPO_INGRESO,
            'concepto' => "Apertura de caja - Fondo inicial",
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
