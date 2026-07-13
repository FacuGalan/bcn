<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Costos vigentes de un artículo (RF-02): una fila por sucursal + la fila
 * consolidada del comercio (sucursal_id NULL).
 *
 * Los tres costos se persisten como costo COMPUTABLE (neto si el IVA fue
 * crédito fiscal; total pagado si no — ver fórmulas canónicas del spec).
 * ESCRITURA SOLO vía CostoService (única puerta): el UNIQUE de MySQL no
 * impide duplicar la fila consolidada (NULL admite N filas iguales).
 */
class ArticuloCosto extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'articulo_costos';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'costo_ultimo',
        'costo_promedio',
        'costo_reposicion',
        'proveedor_ultimo_id',
        'compra_ultima_id',
        'fecha_costo_ultimo',
    ];

    protected $casts = [
        'costo_ultimo' => 'decimal:4',
        'costo_promedio' => 'decimal:4',
        'costo_reposicion' => 'decimal:4',
        'fecha_costo_ultimo' => 'datetime',
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

    public function proveedorUltimo(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_ultimo_id');
    }

    public function compraUltima(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_ultima_id');
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeConsolidados($query)
    {
        return $query->whereNull('sucursal_id');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================
    // HELPERS
    // ==================

    /**
     * Costo rector para pricing (D1: v1 fijo en 'ultimo'; el lector formal
     * con la config es CostoService::costoRector()).
     */
    public function costoRector(): ?float
    {
        return $this->costo_ultimo !== null ? (float) $this->costo_ultimo : null;
    }

    /**
     * Reposición con fallback documentado (RF-02): NULL ⇒ costo_ultimo.
     */
    public function costoReposicionEfectivo(): ?float
    {
        $costo = $this->costo_reposicion ?? $this->costo_ultimo;

        return $costo !== null ? (float) $costo : null;
    }
}
