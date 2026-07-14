<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial append-only de cambios de costo (RF-03), espejo del patrón
 * HistorialPrecio. Solo costo_ultimo y costo_reposicion (el PPP no se
 * historiza: reconstruible desde movimientos_stock.costo).
 *
 * Vocabulario de `origen` (ENUM): 'compra' (confirmación), 'manual' (ABM),
 * 'importacion', 'cancelacion' (reversa) y 'masivo' (cambio masivo de
 * costos, RF-C2 hardening-circuito-precios).
 */
class HistorialCosto extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'pymes_tenant';

    protected $table = 'historial_costos';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'tipo_costo',
        'costo_anterior',
        'costo_nuevo',
        'porcentaje_cambio',
        'origen',
        'compra_id',
        'proveedor_id',
        'usuario_id',
        'detalle',
    ];

    protected $casts = [
        'costo_anterior' => 'decimal:4',
        'costo_nuevo' => 'decimal:4',
        'porcentaje_cambio' => 'decimal:2',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_costo', $tipo);
    }

    public function scopePorOrigen($query, string $origen)
    {
        return $query->where('origen', $origen);
    }
}
