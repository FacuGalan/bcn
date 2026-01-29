<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de Provincia
 *
 * Representa las provincias argentinas.
 * Esta es una tabla de referencia compartida en la base de datos config.
 *
 * @property int $id
 * @property string $codigo Código ISO 3166-2:AR (ej: AR-C, AR-B)
 * @property string $nombre
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Provincia extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'config';

    /**
     * Nombre de la tabla
     */
    protected $table = 'provincias';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'codigo',
        'nombre',
    ];

    /**
     * Relación con localidades
     */
    public function localidades(): HasMany
    {
        return $this->hasMany(Localidad::class);
    }

    /**
     * Scope para ordenar alfabéticamente
     */
    public function scopeOrdenadas($query)
    {
        return $query->orderBy('nombre');
    }

    /**
     * Scope para buscar por código
     */
    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    /**
     * Obtiene todas las provincias ordenadas para select
     */
    public static function paraSelect(): array
    {
        return static::ordenadas()
            ->pluck('nombre', 'id')
            ->toArray();
    }
}
