<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-T41 — BD CONFIG. Tienda favorita de un consumidor (cuenta global).
 * El favorito apunta a la TIENDA (comercio+sucursal), no al comercio pelado:
 * es lo que el consumidor ve y visita.
 */
class ConsumidorFavorito extends Model
{
    protected $connection = 'config';

    protected $table = 'consumidor_favoritos';

    protected $fillable = ['consumidor_id', 'tienda_id'];

    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'tienda_id');
    }
}
