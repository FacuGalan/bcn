<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentaPromocion
 *
 * Registra las promociones aplicadas a nivel de venta.
 */
class VentaPromocion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'venta_promociones';

    public $timestamps = false;

    protected $fillable = [
        'venta_id',
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

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
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

    /**
     * Verifica si es una promoción especial
     */
    public function esPromocionEspecial(): bool
    {
        return $this->tipo_promocion === 'promocion_especial';
    }

    /**
     * Verifica si es una promoción común
     */
    public function esPromocionComun(): bool
    {
        return $this->tipo_promocion === 'promocion';
    }
}
