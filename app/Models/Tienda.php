<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Tienda (RF-13, D15) — BD CONFIG.
 *
 * Registro GLOBAL de tiendas online: la tienda es POR SUCURSAL (no por
 * comercio) — el slug de la URL pública identifica comercio+sucursal sin
 * abrir la BD tenant, y el middleware api.tenant lo usa para configurar el
 * contexto. `sucursal_id` es FK lógico a la sucursal tenant del comercio.
 */
class Tienda extends Model
{
    protected $connection = 'config';

    protected $table = 'tiendas';

    protected $fillable = [
        'comercio_id',
        'sucursal_id',
        'slug',
        'habilitada',
        'dominio_propio',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'sucursal_id' => 'integer',
    ];

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    public function scopeHabilitadas(Builder $query): Builder
    {
        return $query->where('habilitada', true);
    }

    public function scopePorSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
