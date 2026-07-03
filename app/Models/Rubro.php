<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Rubro (RF-13) — BD CONFIG.
 *
 * Catálogo global de rubros comerciales de la tienda (hamburguesería,
 * pizzería, kiosco...) para el futuro marketplace por rubro+radio.
 * OJO: `comercios.rubro` (string) es la categoría MCC de Mercado Pago —
 * CONVIVEN: este es el rubro comercial (`comercios.rubro_id`).
 */
class Rubro extends Model
{
    protected $connection = 'config';

    protected $table = 'rubros';

    protected $fillable = ['nombre', 'slug', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
