<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponArticulo extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cupon_articulos';

    public $timestamps = false;

    protected $fillable = [
        'cupon_id',
        'articulo_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    // --- Relaciones ---

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
