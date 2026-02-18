<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalleOpcional extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'venta_detalle_opcionales';

    public $timestamps = false;

    protected $fillable = [
        'venta_detalle_id',
        'grupo_opcional_id',
        'opcional_id',
        'nombre_grupo',
        'nombre_opcional',
        'cantidad',
        'precio_extra',
        'subtotal_extra',
        'created_at',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_extra' => 'decimal:2',
        'subtotal_extra' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function grupoOpcional(): BelongsTo
    {
        return $this->belongsTo(GrupoOpcional::class, 'grupo_opcional_id')->withTrashed();
    }

    public function opcional(): BelongsTo
    {
        return $this->belongsTo(Opcional::class, 'opcional_id')->withTrashed();
    }
}
