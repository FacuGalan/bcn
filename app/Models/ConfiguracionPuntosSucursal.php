<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionPuntosSucursal extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'configuracion_puntos_sucursales';

    protected $fillable = [
        'sucursal_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // --- Relaciones ---

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
