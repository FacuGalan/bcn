<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo GrupoOpcional
 *
 * Catálogo global de grupos de opciones reutilizables.
 * Ej: "Panes a elección", "Salsas", "Agregados".
 * Disponibles para todas las sucursales; la asignación determina el uso.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $obligatorio
 * @property string $tipo 'seleccionable' o 'cuantitativo'
 * @property int $min_seleccion
 * @property int|null $max_seleccion
 * @property bool $activo
 * @property int $orden
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Opcional[] $opcionales
 * @property-read \Illuminate\Database\Eloquent\Collection|ArticuloGrupoOpcional[] $articuloGrupoOpcionales
 */
class GrupoOpcional extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'grupos_opcionales';

    protected $fillable = [
        'nombre',
        'descripcion',
        'obligatorio',
        'tipo',
        'min_seleccion',
        'max_seleccion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
        'activo' => 'boolean',
        'min_seleccion' => 'integer',
        'max_seleccion' => 'integer',
        'orden' => 'integer',
    ];

    // Tipos
    public const TIPO_SELECCIONABLE = 'seleccionable';
    public const TIPO_CUANTITATIVO = 'cuantitativo';

    // ==================== Relaciones ====================

    public function opcionales(): HasMany
    {
        return $this->hasMany(Opcional::class, 'grupo_opcional_id')->orderBy('orden');
    }

    public function articuloGrupoOpcionales(): HasMany
    {
        return $this->hasMany(ArticuloGrupoOpcional::class, 'grupo_opcional_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // ==================== Métodos ====================

    public function esSeleccionable(): bool
    {
        return $this->tipo === self::TIPO_SELECCIONABLE;
    }

    public function esCuantitativo(): bool
    {
        return $this->tipo === self::TIPO_CUANTITATIVO;
    }

    /**
     * Cantidad de opcionales activos en este grupo
     */
    public function cantidadOpcionalesActivos(): int
    {
        return $this->opcionales()->where('activo', true)->count();
    }
}
