<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo RecetaIngrediente
 *
 * Un ingrediente dentro de una receta.
 * Los ingredientes siempre apuntan a artículos (que son los que llevan stock).
 *
 * @property int $id
 * @property int $receta_id
 * @property int $articulo_id El ingrediente (siempre un artículo)
 * @property float $cantidad Cantidad necesaria del ingrediente
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Receta $receta
 * @property-read Articulo $articulo
 */
class RecetaIngrediente extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'receta_ingredientes';

    protected $fillable = [
        'receta_id',
        'articulo_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
    ];

    // ==================== Relaciones ====================

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
