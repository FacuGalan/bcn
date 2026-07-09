<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desglose de IVA del comprobante de compra por alícuota (RF-14, espejo de
 * ComprobanteFiscalIva). Es la fuente CANÓNICA del crédito fiscal
 * (alimenta $ivaCredito de ImpuestoService::registrarDesdeCompra) y del
 * Libro IVA Compras — se carga desde la factura física, nunca se deriva
 * de la suma de renglones.
 */
class CompraIva extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'compra_ivas';

    protected $fillable = [
        'compra_id',
        'alicuota',
        'base_imponible',
        'importe',
    ];

    protected $casts = [
        'alicuota' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }
}
