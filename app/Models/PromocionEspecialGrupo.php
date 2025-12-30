<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocionEspecialGrupo extends Model
{
    use HasFactory;

    protected $connection = 'pymes_tenant';
    protected $table = 'promocion_especial_grupos';

    protected $fillable = [
        'promocion_especial_id',
        'nombre',
        'cantidad',
        'es_trigger',
        'es_reward',
        'orden',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'es_trigger' => 'boolean',
        'es_reward' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function promocionEspecial()
    {
        return $this->belongsTo(PromocionEspecial::class);
    }

    public function articulos()
    {
        return $this->belongsToMany(
            Articulo::class,
            'promocion_especial_grupo_articulos',
            'grupo_id',
            'articulo_id'
        );
    }

    // ==================== Helpers ====================

    /**
     * Retorna una descripción legible del grupo
     */
    public function getDescripcionAttribute(): string
    {
        $desc = $this->nombre ?: 'Grupo ' . $this->id;

        if ($this->cantidad > 1) {
            $desc .= " (x{$this->cantidad})";
        }

        $roles = [];
        if ($this->es_trigger) $roles[] = 'Trigger';
        if ($this->es_reward) $roles[] = 'Reward';

        if (!empty($roles)) {
            $desc .= ' [' . implode(', ', $roles) . ']';
        }

        return $desc;
    }

    /**
     * Retorna los nombres de los artículos del grupo
     */
    public function getNombresArticulosAttribute(): string
    {
        return $this->articulos->pluck('nombre')->implode(', ');
    }

    /**
     * Verifica si un artículo pertenece a este grupo
     */
    public function contieneArticulo(int $articuloId): bool
    {
        return $this->articulos()->where('articulo_id', $articuloId)->exists();
    }
}
