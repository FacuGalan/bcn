<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Etiqueta
 *
 * Representa una etiqueta específica dentro de un grupo (ej: "Samsung" dentro del grupo "Marca").
 * Las etiquetas se asignan a artículos para clasificación y filtrado.
 *
 * @property int $id
 * @property int $grupo_etiqueta_id ID del grupo al que pertenece
 * @property string $nombre Nombre de la etiqueta
 * @property string|null $codigo Código único dentro del grupo
 * @property string|null $color Color específico (usa el del grupo si es null)
 * @property bool $activo Si la etiqueta está activa
 * @property int $orden Orden de visualización
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read GrupoEtiqueta $grupo
 * @property-read \Illuminate\Database\Eloquent\Collection|Articulo[] $articulos
 */
class Etiqueta extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'etiquetas';

    protected $fillable = [
        'grupo_etiqueta_id',
        'nombre',
        'codigo',
        'color',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    /**
     * Grupo al que pertenece esta etiqueta
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoEtiqueta::class, 'grupo_etiqueta_id');
    }

    /**
     * Artículos que tienen esta etiqueta
     */
    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'articulo_etiqueta', 'etiqueta_id', 'articulo_id')
                    ->withTimestamps();
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo etiquetas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Ordenado por campo orden
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * Scope: Por grupo
     */
    public function scopeDelGrupo($query, int $grupoId)
    {
        return $query->where('grupo_etiqueta_id', $grupoId);
    }

    /**
     * Scope: Buscar por código
     */
    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Obtiene el color efectivo (el propio o el del grupo)
     */
    public function getColorEfectivo(): string
    {
        return $this->color ?? $this->grupo->color ?? '#6B7280';
    }

    /**
     * Obtiene el nombre completo con el grupo
     */
    public function getNombreCompleto(): string
    {
        return $this->grupo->nombre . ': ' . $this->nombre;
    }

    /**
     * Cuenta los artículos que tienen esta etiqueta
     */
    public function contarArticulos(): int
    {
        return $this->articulos()->count();
    }

    /**
     * Cuenta los artículos activos que tienen esta etiqueta
     */
    public function contarArticulosActivos(): int
    {
        return $this->articulos()->where('activo', true)->count();
    }
}
