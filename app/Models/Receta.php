<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo Receta
 *
 * Define la fórmula de ingredientes para producir un artículo u opcional.
 * Polimórfica (recetable_type: 'Articulo' o 'Opcional').
 * Usa default + override por sucursal:
 * - sucursal_id = null → receta default para todas las sucursales
 * - sucursal_id = X → override para esa sucursal específica
 *
 * @property int $id
 * @property string $recetable_type 'Articulo' o 'Opcional'
 * @property int $recetable_id
 * @property int|null $sucursal_id null = default, con valor = override
 * @property float $cantidad_producida
 * @property string|null $notas
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Model $recetable (Articulo o Opcional)
 * @property-read Sucursal|null $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|RecetaIngrediente[] $ingredientes
 */
class Receta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'recetas';

    protected $fillable = [
        'recetable_type',
        'recetable_id',
        'sucursal_id',
        'cantidad_producida',
        'notas',
        'activo',
    ];

    protected $casts = [
        'cantidad_producida' => 'decimal:3',
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    public function recetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function ingredientes(): HasMany
    {
        return $this->hasMany(RecetaIngrediente::class, 'receta_id');
    }

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDefault($query)
    {
        return $query->whereNull('sucursal_id');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================== Métodos Estáticos ====================

    /**
     * Resuelve la receta para un artículo u opcional en una sucursal.
     * Prioriza override de sucursal sobre default.
     *
     * @param string $type 'Articulo' o 'Opcional'
     * @param int $id ID del artículo u opcional
     * @param int $sucursalId ID de la sucursal
     * @return self|null
     */
    public static function resolver(string $type, int $id, int $sucursalId): ?self
    {
        return static::where('recetable_type', $type)
            ->where('recetable_id', $id)
            ->where('activo', true)
            ->where(function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId)
                  ->orWhereNull('sucursal_id');
            })
            ->orderByRaw('sucursal_id IS NULL ASC')
            ->with('ingredientes.articulo')
            ->first();
    }

    // ==================== Métodos ====================

    /**
     * Indica si esta receta es la default (aplica a todas las sucursales)
     */
    public function esDefault(): bool
    {
        return $this->sucursal_id === null;
    }

    /**
     * Indica si esta receta es un override para una sucursal específica
     */
    public function esOverride(): bool
    {
        return $this->sucursal_id !== null;
    }
}
