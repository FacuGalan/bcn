<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo RepartidorFondo (RF-09, D4)
 *
 * Fondo de cambio entregado al repartidor desde una caja. Es un FONDO de
 * ciclo largo, no una caja: puede quedar abierto entre salidas ("se lo queda
 * para seguir viajando") y se rinde cuando se decide cerrarlo. El saldo
 * teorico se CALCULA de los movimientos append-only
 * (repartidor_fondo_movimientos) — nunca se cachea.
 *
 * Regla contable (D13): el efectivo cobrado en la calle NO genera
 * MovimientoCaja al registrarse; la caja recibe UN ingreso neto al rendir.
 * Entre vuelta y rendicion, los reportes de tesoreria muestran la linea
 * informativa "en fondos de repartidores".
 */
class RepartidorFondo extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'repartidor_fondos';

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_RENDIDO = 'rendido';

    protected $fillable = [
        'repartidor_id',
        'sucursal_id',
        'caja_origen_id',
        'estado',
        'monto_inicial',
        'monto_rendido',
        'diferencia',
        'caja_rendicion_id',
        'usuario_apertura_id',
        'usuario_cierre_id',
        'abierto_at',
        'rendido_at',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:2',
        'monto_rendido' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'abierto_at' => 'datetime',
        'rendido_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'repartidor_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cajaOrigen(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_origen_id');
    }

    public function cajaRendicion(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_rendicion_id');
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_apertura_id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cierre_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(RepartidorFondoMovimiento::class, 'fondo_id');
    }

    // ==================== SCOPES / HELPERS ====================

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ABIERTO);
    }

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    /**
     * Saldo teorico del fondo: suma con signo de los movimientos append-only.
     * (Espejado por RepartidorService::saldoTeorico para uso con locking.)
     */
    public function saldoTeorico(): float
    {
        return (float) $this->movimientos()->sum('monto');
    }
}
