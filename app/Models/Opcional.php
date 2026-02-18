<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Opcional
 *
 * Opciones individuales dentro de un grupo opcional.
 * Catálogo global único por comercio. El mismo opcional (mismo id) se usa
 * en todas las sucursales para reportes cruzados.
 *
 * @property int $id
 * @property int $grupo_opcional_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property float $precio_extra Precio template/default
 * @property bool $activo
 * @property int $orden
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read GrupoOpcional $grupoOpcional
 * @property-read \Illuminate\Database\Eloquent\Collection|Receta[] $recetas
 * @property-read \Illuminate\Database\Eloquent\Collection|ArticuloGrupoOpcionalOpcion[] $asignaciones
 */
class Opcional extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'opcionales';

    protected $fillable = [
        'grupo_opcional_id',
        'nombre',
        'descripcion',
        'precio_extra',
        'activo',
        'orden',
    ];

    protected $casts = [
        'precio_extra' => 'decimal:2',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function grupoOpcional(): BelongsTo
    {
        return $this->belongsTo(GrupoOpcional::class, 'grupo_opcional_id');
    }

    /**
     * Recetas de este opcional (polimórfica).
     * Puede tener una default (sucursal_id=null) y overrides por sucursal.
     */
    public function recetas(): MorphMany
    {
        return $this->morphMany(Receta::class, 'recetable');
    }

    /**
     * Asignaciones a artículos (detalle de articulo_grupo_opcional_opcion)
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(ArticuloGrupoOpcionalOpcion::class, 'opcional_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // ==================== Métodos ====================

    /**
     * Resuelve la receta para una sucursal (override > default)
     */
    public function resolverReceta(int $sucursalId): ?Receta
    {
        return Receta::where('recetable_type', 'Opcional')
            ->where('recetable_id', $this->id)
            ->where('activo', true)
            ->where(function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId)
                  ->orWhereNull('sucursal_id');
            })
            ->orderByRaw('sucursal_id IS NULL ASC')
            ->first();
    }
}
