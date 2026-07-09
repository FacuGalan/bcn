<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Repartidor (RF-07, D3)
 *
 * Entidad propia (no es un User): puede ser `propio` (empleado) o `tercero`
 * (cadete externo). `user_id` es un FK logico opcional a config.users para la
 * futura app de repartidores. Si `envio_es_del_repartidor`, el costo de envio
 * cobrado NO es ingreso del comercio: se liquida al repartidor al rendir su
 * fondo (RF-09).
 */
class Repartidor extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'repartidores';

    public const TIPO_PROPIO = 'propio';

    public const TIPO_TERCERO = 'tercero';

    public const TIPOS = [
        self::TIPO_PROPIO => 'Propio',
        self::TIPO_TERCERO => 'Tercero',
    ];

    protected $fillable = [
        'nombre',
        'telefono',
        'tipo',
        'envio_es_del_repartidor',
        'user_id',
        'activo',
    ];

    protected $casts = [
        'envio_es_del_repartidor' => 'boolean',
        'activo' => 'boolean',
    ];

    // ==================== RELACIONES ====================

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'repartidor_sucursal', 'repartidor_id', 'sucursal_id')
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoDelivery::class, 'repartidor_id');
    }

    public function salidas(): HasMany
    {
        return $this->hasMany(DeliverySalida::class, 'repartidor_id');
    }

    public function fondos(): HasMany
    {
        return $this->hasMany(RepartidorFondo::class, 'repartidor_id');
    }

    /**
     * Fondo abierto del repartidor en una sucursal (a lo sumo UNO por
     * repartidor+sucursal, RF-09).
     */
    public function fondoAbierto(int $sucursalId): ?RepartidorFondo
    {
        return $this->fondos()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', RepartidorFondo::ESTADO_ABIERTO)
            ->first();
    }

    // ==================== SCOPES ====================

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->whereHas('sucursales', fn (Builder $q) => $q->where('sucursales.id', $sucursalId));
    }

    public function esTercero(): bool
    {
        return $this->tipo === self::TIPO_TERCERO;
    }
}
