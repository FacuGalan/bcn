<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Relación Comprobante Fiscal - Venta
 *
 * Tabla pivot que relaciona comprobantes fiscales con ventas.
 * Permite que un comprobante cubra múltiples ventas y viceversa.
 */
class ComprobanteFiscalVenta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'comprobante_fiscal_ventas';

    public $timestamps = false;

    protected $fillable = [
        'comprobante_fiscal_id',
        'venta_id',
        'monto',
        'es_anulacion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'es_anulacion' => 'boolean',
    ];

    // ==================== Relaciones ====================

    public function comprobanteFiscal(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
