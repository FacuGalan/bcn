<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Relación Cobro - Venta
 *
 * Tabla pivot que relaciona cobros con ventas saldadas.
 */
class CobroVenta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cobro_ventas';

    public $timestamps = false;

    protected $fillable = [
        'cobro_id',
        'venta_id',
        'monto_aplicado',
        'interes_aplicado',
        'saldo_anterior',
        'saldo_posterior',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
        'interes_aplicado' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
