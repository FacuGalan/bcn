<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduccionIngrediente extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'produccion_ingredientes';

    protected $fillable = [
        'produccion_detalle_id',
        'articulo_id',
        'cantidad_receta',
        'cantidad_real',
    ];

    protected $casts = [
        'cantidad_receta' => 'decimal:3',
        'cantidad_real' => 'decimal:3',
    ];

    // Relaciones
    public function produccionDetalle(): BelongsTo
    {
        return $this->belongsTo(ProduccionDetalle::class, 'produccion_detalle_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }
}
