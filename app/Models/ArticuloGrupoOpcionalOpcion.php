<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ArticuloGrupoOpcionalOpcion
 *
 * Detalle de una opción dentro de una asignación grupo-artículo-sucursal.
 * Contiene el precio concreto, estado activo/disponible y orden.
 *
 * - activo: decisión administrativa ("no quiero esta opción acá")
 * - disponible: estado operativo ("se agotó")
 *
 * @property int $id
 * @property int $articulo_grupo_opcional_id
 * @property int $opcional_id
 * @property float $precio_extra
 * @property bool $activo
 * @property bool $disponible
 * @property int $orden
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read ArticuloGrupoOpcional $articuloGrupoOpcional
 * @property-read Opcional $opcional
 */
class ArticuloGrupoOpcionalOpcion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'articulo_grupo_opcional_opcion';

    protected $fillable = [
        'articulo_grupo_opcional_id',
        'opcional_id',
        'precio_extra',
        'activo',
        'disponible',
        'orden',
    ];

    protected $casts = [
        'precio_extra' => 'decimal:2',
        'activo' => 'boolean',
        'disponible' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function articuloGrupoOpcional(): BelongsTo
    {
        return $this->belongsTo(ArticuloGrupoOpcional::class, 'articulo_grupo_opcional_id');
    }

    public function opcional(): BelongsTo
    {
        return $this->belongsTo(Opcional::class, 'opcional_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true);
    }

    /**
     * Opciones que se muestran en la venta (activas + disponibles + opcional global activo)
     */
    public function scopeParaVenta($query)
    {
        return $query->where('activo', true)
                     ->where('disponible', true)
                     ->whereHas('opcional', fn($q) => $q->where('activo', true));
    }
}
