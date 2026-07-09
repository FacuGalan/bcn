<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo artículo ↔ proveedor (RF-04/RF-16): código del artículo en el
 * catálogo del proveedor, factor de conversión bulto→unidades de stock (D8),
 * descuentos habituales que se precargan en el renglón y último costo
 * computable de ESE proveedor. Upsert al confirmar compra (CostoService).
 */
class ArticuloProveedor extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'articulo_proveedor';

    protected $fillable = [
        'articulo_id',
        'proveedor_id',
        'codigo_proveedor',
        'factor_conversion',
        'descuentos_habituales',
        'costo_ultimo',
        'fecha_ultima_compra',
        'activo',
    ];

    protected $casts = [
        'factor_conversion' => 'decimal:4',
        'descuentos_habituales' => 'array',
        'costo_ultimo' => 'decimal:4',
        'fecha_ultima_compra' => 'datetime',
        'activo' => 'boolean',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Búsqueda por código del proveedor en la carga de compra (RF-04).
     * Puede devolver varios (código duplicado ⇒ selector en la UI).
     */
    public function scopePorCodigo($query, int $proveedorId, string $codigo)
    {
        return $query->where('proveedor_id', $proveedorId)
            ->where('codigo_proveedor', $codigo)
            ->where('activo', true);
    }
}
