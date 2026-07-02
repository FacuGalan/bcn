<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DeliveryZona (RF-05/RF-06)
 *
 * Zona de entrega por sucursal. v1: la zona es un CIRCULO (centro lat/lng +
 * radio_km); `poligono` JSON queda reservado para zonas dibujadas a futuro.
 * Tiene costo de envio propio (pisa el calculo por km), rangos horarios de
 * actividad y `orden` como prioridad de match (DeliveryEnvioService matchea
 * la primera zona activa cuyo circulo contenga el punto, respetando horario).
 */
class DeliveryZona extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'delivery_zonas';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'centro_lat',
        'centro_lng',
        'radio_km',
        'poligono',
        'costo_envio',
        'rangos_horarios',
        'orden',
        'activo',
    ];

    protected $casts = [
        'centro_lat' => 'decimal:7',
        'centro_lng' => 'decimal:7',
        'radio_km' => 'decimal:2',
        'poligono' => 'array',
        'costo_envio' => 'decimal:2',
        'rangos_horarios' => 'array',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('id');
    }
}
