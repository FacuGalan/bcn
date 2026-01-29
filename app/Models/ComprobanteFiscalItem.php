<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Item de Comprobante Fiscal
 *
 * Representa un item/línea de un comprobante fiscal.
 * Los datos se copian de ventas_detalle para mantener inmutabilidad.
 */
class ComprobanteFiscalItem extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'comprobante_fiscal_items';

    public $timestamps = false;

    protected $fillable = [
        'comprobante_fiscal_id',
        'venta_detalle_id',
        'codigo',
        'descripcion',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'bonificacion',
        'subtotal',
        'iva_codigo_afip',
        'iva_alicuota',
        'iva_importe',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'bonificacion' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_alicuota' => 'decimal:2',
        'iva_importe' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function comprobanteFiscal(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    // ==================== Métodos ====================

    /**
     * Obtiene el total con IVA del item
     */
    public function getTotalConIvaAttribute(): float
    {
        return $this->subtotal + $this->iva_importe;
    }
}
