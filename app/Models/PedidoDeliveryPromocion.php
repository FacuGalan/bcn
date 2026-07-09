<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PedidoDeliveryPromocion
 *
 * Promocion aplicada a nivel pedido delivery (no a item especifico). Espejo
 * de PedidoMostradorPromocion.
 */
class PedidoDeliveryPromocion extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedido_delivery_promociones';

    public $timestamps = false;

    protected $fillable = [
        'pedido_delivery_id',
        'tipo_promocion',
        'promocion_id',
        'promocion_especial_id',
        'forma_pago_id',
        'codigo_cupon',
        'descripcion_promocion',
        'tipo_beneficio',
        'valor_beneficio',
        'descuento_aplicado',
        'monto_minimo_requerido',
    ];

    protected $casts = [
        'valor_beneficio' => 'decimal:2',
        'descuento_aplicado' => 'decimal:2',
        'monto_minimo_requerido' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoDelivery::class, 'pedido_delivery_id');
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    public function promocionEspecial(): BelongsTo
    {
        return $this->belongsTo(PromocionEspecial::class, 'promocion_especial_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function esPromocionEspecial(): bool
    {
        return $this->tipo_promocion === 'promocion_especial';
    }

    public function esPromocionComun(): bool
    {
        return $this->tipo_promocion === 'promocion';
    }
}
