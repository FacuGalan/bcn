<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo DeliverySalida (RF-08)
 *
 * Salida de reparto: agrupa pedidos `listo` de un repartidor. Registrar la
 * salida pasa todos los pedidos a `en_camino`. Puede sumar pedidos mientras
 * este `armando`; la vuelta marca resultado por pedido (entregado / no
 * entregado con motivo) via el pivot append-only delivery_salida_pedidos.
 * El circuito de vuelta, cobros y fondo SIEMPRE opera sobre salidas (el pase
 * manual a en_camino crea una salida implicita de 1 pedido).
 */
class DeliverySalida extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'delivery_salidas';

    public const ESTADO_ARMANDO = 'armando';

    public const ESTADO_EN_CAMINO = 'en_camino';

    public const ESTADO_FINALIZADA = 'finalizada';

    public const ESTADOS = [
        self::ESTADO_ARMANDO => 'Armando',
        self::ESTADO_EN_CAMINO => 'En camino',
        self::ESTADO_FINALIZADA => 'Finalizada',
    ];

    protected $fillable = [
        'sucursal_id',
        'repartidor_id',
        'estado',
        'salida_at',
        'vuelta_at',
        'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'salida_at' => 'datetime',
        'vuelta_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'repartidor_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Historial append-only de pedidos de esta salida (con resultado y motivo).
     */
    public function salidaPedidos(): HasMany
    {
        return $this->hasMany(DeliverySalidaPedido::class, 'salida_id');
    }

    /**
     * Pedidos cuya salida ACTUAL es esta (pedidos_delivery.salida_id).
     */
    public function pedidosActuales(): HasMany
    {
        return $this->hasMany(PedidoDelivery::class, 'salida_id');
    }

    // ==================== SCOPES ====================

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::ESTADO_ARMANDO, self::ESTADO_EN_CAMINO]);
    }

    public function estaArmando(): bool
    {
        return $this->estado === self::ESTADO_ARMANDO;
    }
}
