<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Percepción/retención sufrida en la factura de un proveedor (RF-05
 * sistema-impositivo). Desglose fiscal de la compra, paralelo a
 * ComprobanteFiscalTributo (que es del lado de ventas).
 *
 * Cada fila se imputa al CUIT de la compra (compras.cuit_id) y alimenta el
 * ledger fiscal (movimientos_fiscales, sentido sufrido) vía
 * ImpuestoService::registrarDesdeCompra.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-05, Fase 6).
 */
class CompraPercepcion extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'compra_percepciones';

    protected $fillable = [
        'compra_id',
        'impuesto_id',
        'base_imponible',
        'alicuota',
        'monto',
        'certificado_numero',
    ];

    protected $casts = [
        'base_imponible' => 'decimal:2',
        'alicuota' => 'decimal:4',
        'monto' => 'decimal:2',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }
}
