<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduccionDetalle extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'produccion_detalles';

    protected $fillable = [
        'produccion_id',
        'articulo_id',
        'receta_id',
        'cantidad_producida',
        'cantidad_receta',
    ];

    protected $casts = [
        'cantidad_producida' => 'decimal:3',
        'cantidad_receta' => 'decimal:3',
    ];

    // Relaciones
    public function produccion(): BelongsTo
    {
        return $this->belongsTo(Produccion::class, 'produccion_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }

    public function ingredientes(): HasMany
    {
        return $this->hasMany(ProduccionIngrediente::class, 'produccion_detalle_id');
    }
}
