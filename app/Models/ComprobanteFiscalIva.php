<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Desglose de IVA de Comprobante Fiscal
 *
 * Representa el desglose de IVA por alícuota de un comprobante fiscal.
 * AFIP requiere informar el IVA desagregado por alícuota.
 */
class ComprobanteFiscalIva extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'comprobante_fiscal_iva';

    public $timestamps = false;

    protected $fillable = [
        'comprobante_fiscal_id',
        'codigo_afip',
        'alicuota',
        'base_imponible',
        'importe',
    ];

    protected $casts = [
        'codigo_afip' => 'integer',
        'alicuota' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function comprobanteFiscal(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class);
    }

    // ==================== Métodos ====================

    /**
     * Obtiene el nombre de la alícuota
     */
    public function getNombreAlicuotaAttribute(): string
    {
        $nombres = [
            3 => 'IVA 0%',
            4 => 'IVA 10.5%',
            5 => 'IVA 21%',
            6 => 'IVA 27%',
            8 => 'IVA 5%',
            9 => 'IVA 2.5%',
        ];

        return $nombres[$this->codigo_afip] ?? "IVA {$this->alicuota}%";
    }
}
