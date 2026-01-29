<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Pivot CUIT-Sucursal
 *
 * Permite asignar CUITs a sucursales específicas.
 * Un CUIT puede estar asociado a múltiples sucursales.
 *
 * @property int $id
 * @property int $cuit_id
 * @property int $sucursal_id
 * @property bool $es_principal Si es el CUIT principal de la sucursal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CuitSucursal extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'cuit_sucursal';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'cuit_id',
        'sucursal_id',
        'es_principal',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'es_principal' => 'boolean',
    ];

    /**
     * Relación con CUIT
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class);
    }

    /**
     * Relación con Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Scope para obtener solo los principales
     */
    public function scopePrincipales($query)
    {
        return $query->where('es_principal', true);
    }

    /**
     * Scope para filtrar por sucursal
     */
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Scope para filtrar por CUIT
     */
    public function scopePorCuit($query, int $cuitId)
    {
        return $query->where('cuit_id', $cuitId);
    }
}
