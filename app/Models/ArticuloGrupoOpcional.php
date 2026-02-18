<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo ArticuloGrupoOpcional
 *
 * Asignación de un grupo opcional a un artículo en una sucursal específica.
 * Siempre explícito con sucursal_id (NOT NULL). Sin override patterns.
 *
 * @property int $id
 * @property int $articulo_id
 * @property int $grupo_opcional_id
 * @property int $sucursal_id
 * @property bool $activo
 * @property int $orden
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Articulo $articulo
 * @property-read GrupoOpcional $grupoOpcional
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|ArticuloGrupoOpcionalOpcion[] $opciones
 */
class ArticuloGrupoOpcional extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'articulo_grupo_opcional';

    protected $fillable = [
        'articulo_id',
        'grupo_opcional_id',
        'sucursal_id',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function grupoOpcional(): BelongsTo
    {
        return $this->belongsTo(GrupoOpcional::class, 'grupo_opcional_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Opciones (detalle) de este grupo para este artículo en esta sucursal
     */
    public function opciones(): HasMany
    {
        return $this->hasMany(ArticuloGrupoOpcionalOpcion::class, 'articulo_grupo_opcional_id')
                    ->orderBy('orden');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorArticulo($query, int $articuloId)
    {
        return $query->where('articulo_id', $articuloId);
    }

    // ==================== Métodos ====================

    /**
     * Obtiene solo las opciones activas y disponibles (para la venta)
     */
    public function opcionesDisponibles()
    {
        return $this->opciones()
                    ->where('activo', true)
                    ->where('disponible', true)
                    ->whereHas('opcional', fn($q) => $q->where('activo', true));
    }

    /**
     * Restablece los valores default de todas las opciones desde el catálogo global
     */
    public function restablecerDefaults(): void
    {
        $opcionalesDelGrupo = $this->grupoOpcional->opcionales()->activos()->get();

        foreach ($this->opciones as $opcion) {
            $opcionalGlobal = $opcionalesDelGrupo->firstWhere('id', $opcion->opcional_id);
            if ($opcionalGlobal) {
                $opcion->update([
                    'precio_extra' => $opcionalGlobal->precio_extra,
                    'orden' => $opcionalGlobal->orden,
                    'activo' => true,
                    'disponible' => true,
                ]);
            }
        }

        // Agregar opciones que faltan
        $opcionesExistentes = $this->opciones->pluck('opcional_id');
        foreach ($opcionalesDelGrupo as $opcional) {
            if (!$opcionesExistentes->contains($opcional->id)) {
                $this->opciones()->create([
                    'opcional_id' => $opcional->id,
                    'precio_extra' => $opcional->precio_extra,
                    'orden' => $opcional->orden,
                    'activo' => true,
                    'disponible' => true,
                ]);
            }
        }
    }
}
