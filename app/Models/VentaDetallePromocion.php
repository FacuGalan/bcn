<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentaDetallePromocion
 *
 * Registra las promociones aplicadas a cada item del detalle de venta.
 */
class VentaDetallePromocion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'venta_detalle_promociones';

    public $timestamps = false;

    protected $fillable = [
        'venta_detalle_id',
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

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
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

    /**
     * Obtiene una descripción formateada del beneficio
     */
    public function getDescripcionBeneficioAttribute(): string
    {
        return match($this->tipo_beneficio) {
            'porcentaje' => "-{$this->valor_beneficio}%",
            'monto_fijo' => "-\${$this->valor_beneficio}",
            'precio_especial' => "Precio esp.",
            'nx1' => "{$this->cantidad_requerida}x{$this->cantidad_bonificada}",
            default => '',
        };
    }
}
