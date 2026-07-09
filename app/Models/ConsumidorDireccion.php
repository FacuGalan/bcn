<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección guardada de un consumidor (RF-13) — BD CONFIG.
 *
 * Reutilizable en cualquier comercio: el checkout de la tienda las precarga
 * y el pedido copia el snapshot (la dirección del pedido nunca referencia
 * esta fila).
 */
class ConsumidorDireccion extends Model
{
    protected $connection = 'config';

    protected $table = 'consumidor_direcciones';

    protected $fillable = [
        'consumidor_id',
        'alias',
        'direccion',
        'referencia',
        'localidad_id',
        'latitud',
        'longitud',
        'es_default',
    ];

    protected $casts = [
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'es_default' => 'boolean',
    ];

    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }
}
