<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PedidoDeliveryDetallePromocion
 *
 * Promocion aplicada a una linea de pedido delivery (a nivel item). Espejo de
 * PedidoMostradorDetallePromocion.
 */
class PedidoDeliveryDetallePromocion extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedido_delivery_detalle_promociones';

    public $timestamps = false;

    protected $fillable = [
        'pedido_delivery_detalle_id',
        'tipo_promocion',
        'promocion_id',
        'promocion_especial_id',
        'lista_precio_id',
        'descripcion_promocion',
        'tipo_beneficio',
        'valor_beneficio',
        'descuento_aplicado',
        'cantidad_requerida',
        'cantidad_bonificada',
    ];

    protected $casts = [
        'valor_beneficio' => 'decimal:2',
        'descuento_aplicado' => 'decimal:2',
        'cantidad_requerida' => 'integer',
        'cantidad_bonificada' => 'integer',
        'created_at' => 'datetime',
    ];

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDeliveryDetalle::class, 'pedido_delivery_detalle_id');
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    public function promocionEspecial(): BelongsTo
    {
        return $this->belongsTo(PromocionEspecial::class, 'promocion_especial_id');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }
}
