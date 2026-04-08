<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponUso extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cupon_usos';

    public $timestamps = false;

    protected $fillable = [
        'cupon_id',
        'venta_id',
        'cliente_id',
        'sucursal_id',
        'monto_descontado',
        'fecha',
        'usuario_id',
        'created_at',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto_descontado' => 'decimal:2',
    ];

    // --- Relaciones ---

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
