<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desglose de tributos no-IVA de un comprobante fiscal (RF-04
 * sistema-impositivo): percepciones IIBB y otros tributos calculados al
 * emitir. Paralelo a ComprobanteFiscalIva; el total alimenta el campo
 * comprobantes_fiscales.tributos y viaja a ARCA en el array Tributos.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-04).
 */
class ComprobanteFiscalTributo extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'comprobante_fiscal_tributos';

    protected $fillable = [
        'comprobante_fiscal_id',
        'impuesto_id',
        'base_imponible',
        'alicuota',
        'monto',
        'codigo_arca',
    ];

    protected $casts = [
        'base_imponible' => 'decimal:2',
        'alicuota' => 'decimal:4',
        'monto' => 'decimal:2',
        'codigo_arca' => 'integer',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'comprobante_fiscal_id')->withTrashed();
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }
}
