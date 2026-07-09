<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DeliverySalidaPedido (RF-08, historial append-only)
 *
 * Intento de entrega de un pedido dentro de una salida. Conserva TODOS los
 * intentos (re-despachos incluidos) con su resultado: `pedidos_delivery
 * .salida_id` apunta solo a la salida ACTUAL, este pivot guarda la historia.
 */
class DeliverySalidaPedido extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'delivery_salida_pedidos';

    public const RESULTADO_PENDIENTE = 'pendiente';

    public const RESULTADO_ENTREGADO = 'entregado';

    public const RESULTADO_NO_ENTREGADO = 'no_entregado';

    public const RESULTADOS = [
        self::RESULTADO_PENDIENTE => 'Pendiente',
        self::RESULTADO_ENTREGADO => 'Entregado',
        self::RESULTADO_NO_ENTREGADO => 'No entregado',
    ];

    protected $fillable = [
        'salida_id',
        'pedido_id',
        'resultado',
        'motivo',
    ];

    public function salida(): BelongsTo
    {
        return $this->belongsTo(DeliverySalida::class, 'salida_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoDelivery::class, 'pedido_id');
    }
}
