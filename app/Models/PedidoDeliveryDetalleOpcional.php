<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PedidoDeliveryDetalleOpcional
 *
 * Opcional aplicado a una linea de pedido delivery. Espejo de
 * PedidoMostradorDetalleOpcional.
 */
class PedidoDeliveryDetalleOpcional extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedido_delivery_detalle_opcionales';

    public $timestamps = false;

    protected $fillable = [
        'pedido_delivery_detalle_id',
        'grupo_opcional_id',
        'opcional_id',
        'nombre_grupo',
        'nombre_opcional',
        'cantidad',
        'precio_extra',
        'subtotal_extra',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_extra' => 'decimal:2',
        'subtotal_extra' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDeliveryDetalle::class, 'pedido_delivery_detalle_id');
    }

    public function grupoOpcional(): BelongsTo
    {
        return $this->belongsTo(GrupoOpcional::class, 'grupo_opcional_id');
    }

    public function opcional(): BelongsTo
    {
        return $this->belongsTo(Opcional::class, 'opcional_id');
    }
}
