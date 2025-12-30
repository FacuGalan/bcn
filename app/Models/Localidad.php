<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Localidad
 *
 * Representa las localidades argentinas del padrón AFIP.
 * Esta es una tabla de referencia compartida en la base de datos config.
 *
 * @property int $id
 * @property int $provincia_id
 * @property string|null $codigo_postal
 * @property string $nombre
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Localidad extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'config';

    /**
     * Nombre de la tabla
     */
    protected $table = 'localidades';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'provincia_id',
        'codigo_postal',
        'nombre',
    ];

    /**
     * Relación con provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    /**
     * Scope para filtrar por provincia
     */
    public function scopePorProvincia($query, int $provinciaId)
    {
        return $query->where('provincia_id', $provinciaId);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopeBuscar($query, string $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%");
    }

    /**
     * Scope para ordenar alfabéticamente
     */
    public function scopeOrdenadas($query)
    {
        return $query->orderBy('nombre');
    }

    /**
     * Scope para buscar por código postal
     */
    public function scopePorCodigoPostal($query, string $cp)
    {
        return $query->where('codigo_postal', $cp);
    }

    /**
     * Obtiene el nombre completo con provincia
     */
    public function getNombreCompletoAttribute(): string
    {
        return $this->nombre . ', ' . $this->provincia->nombre;
    }

    /**
     * Obtiene localidades para select por provincia
     */
    public static function paraSelect(int $provinciaId): array
    {
        return static::porProvincia($provinciaId)
            ->ordenadas()
            ->get()
            ->mapWithKeys(function ($loc) {
                $label = $loc->nombre;
                if ($loc->codigo_postal) {
                    $label .= " ({$loc->codigo_postal})";
                }
                return [$loc->id => $label];
            })
            ->toArray();
    }
}
