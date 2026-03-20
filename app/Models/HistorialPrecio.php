<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialPrecio extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'historial_precios';

    const UPDATED_AT = null;

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'precio_anterior',
        'precio_nuevo',
        'usuario_id',
        'origen',
        'porcentaje_cambio',
        'detalle',
    ];

    protected $casts = [
        'precio_anterior' => 'decimal:2',
        'precio_nuevo' => 'decimal:2',
        'porcentaje_cambio' => 'decimal:2',
    ];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id')->on('config');
    }

    /**
     * Registra un cambio de precio en el historial.
     */
    public static function registrar(array $datos): self
    {
        return static::create(array_merge($datos, [
            'usuario_id' => $datos['usuario_id'] ?? auth()->id(),
        ]));
    }
}
