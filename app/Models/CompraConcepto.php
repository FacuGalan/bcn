<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto de pie de factura de compra (RF-15, D9): flete, impuestos
 * internos, envases, otros. `monto` va en la MISMA base que los renglones
 * (neto si el comprobante discrimina IVA). Los que computan costo se
 * prorratean a los renglones por importe (landed cost); tipo_iva_id solo
 * alimenta la sugerencia del desglose compra_ivas.
 */
class CompraConcepto extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'compra_conceptos';

    protected $fillable = [
        'compra_id',
        'tipo',
        'descripcion',
        'monto',
        'tipo_iva_id',
        'computa_costo',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'computa_costo' => 'boolean',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeComputanCosto($query)
    {
        return $query->where('computa_costo', true);
    }
}
