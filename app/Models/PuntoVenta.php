<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Punto de Venta
 *
 * Representa un punto de venta AFIP asociado a un CUIT.
 * Los certificados digitales se manejan a nivel de CUIT.
 *
 * @property int $id
 * @property int $cuit_id
 * @property int $numero Numero de punto de venta (1-99999)
 * @property string|null $nombre Descripcion o alias
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PuntoVenta extends Model
{
    use SoftDeletes;

    /**
     * Conexion de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'puntos_venta';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'cuit_id',
        'numero',
        'nombre',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'numero' => 'integer',
        'activo' => 'boolean',
    ];

    /**
     * Relacion con CUIT
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class);
    }

    /**
     * Relacion con cajas
     */
    public function cajas(): BelongsToMany
    {
        return $this->belongsToMany(Caja::class, 'punto_venta_caja')
            ->withPivot('es_defecto')
            ->withTimestamps();
    }

    /**
     * Scope para puntos de venta activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para filtrar por CUIT
     */
    public function scopePorCuit($query, int $cuitId)
    {
        return $query->where('cuit_id', $cuitId);
    }

    /**
     * Obtiene el numero formateado con ceros a la izquierda (4 digitos)
     */
    public function getNumeroFormateadoAttribute(): string
    {
        return str_pad($this->numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el nombre para mostrar
     */
    public function getNombreMostrarAttribute(): string
    {
        $texto = "PV {$this->numero_formateado}";

        if ($this->nombre) {
            $texto .= " - {$this->nombre}";
        }

        return $texto;
    }

    /**
     * Obtiene la identificacion completa (CUIT + PV)
     */
    public function getIdentificacionCompletaAttribute(): string
    {
        return $this->cuit->cuit_formateado . ' - PV ' . $this->numero_formateado;
    }
}
