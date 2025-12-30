<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo GrupoEtiqueta
 *
 * Representa un grupo/categoría de etiquetas (ej: "Marca", "Color", "Tamaño").
 * Permite organizar las etiquetas en grupos lógicos para filtrado y clasificación.
 *
 * @property int $id
 * @property string $nombre Nombre del grupo (ej: "Marca")
 * @property string|null $codigo Código único del grupo
 * @property string|null $descripcion Descripción del grupo
 * @property string $color Color hex para identificación visual
 * @property bool $activo Si el grupo está activo
 * @property int $orden Orden de visualización
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Etiqueta[] $etiquetas
 */
class GrupoEtiqueta extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'grupos_etiquetas';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
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
     * Etiquetas que pertenecen a este grupo
     */
    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class, 'grupo_etiqueta_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo grupos activos
     */
    public function scopeActivos($query)
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
     * Scope: Buscar por código
     */
    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Obtiene las etiquetas activas de este grupo
     */
    public function etiquetasActivas()
    {
        return $this->etiquetas()->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }

    /**
     * Verifica si el grupo tiene etiquetas
     */
    public function tieneEtiquetas(): bool
    {
        return $this->etiquetas()->exists();
    }

    /**
     * Cuenta las etiquetas activas
     */
    public function contarEtiquetasActivas(): int
    {
        return $this->etiquetas()->where('activo', true)->count();
    }
}
