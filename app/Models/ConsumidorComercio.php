<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapping consumidor global ↔ cliente tenant por comercio (RF-13, D11) —
 * BD CONFIG.
 *
 * `cliente_id` es FK LÓGICO al `clientes` de la BD tenant del comercio.
 * Se crea SOLO según la política del comercio: automático al primer pedido
 * si `comercios.tienda_alta_cliente_automatica`, o manual con "convertir en
 * cliente" desde el panel.
 */
class ConsumidorComercio extends Model
{
    protected $connection = 'config';

    protected $table = 'consumidor_comercio';

    protected $fillable = [
        'consumidor_id',
        'comercio_id',
        'cliente_id',
    ];

    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }
}
