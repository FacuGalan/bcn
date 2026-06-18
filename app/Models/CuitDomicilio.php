<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de Domicilio fiscal de un CUIT (RF-11, Fase 9)
 *
 * Espejo de AFIP: cada CUIT declara N domicilios y cada punto de venta se asocia
 * a uno. La jurisdicción de la operación (IIBB) sale de la provincia del
 * domicilio del PV, no de la sucursal física.
 *
 * @property int $id
 * @property int $cuit_id
 * @property string $tipo fiscal|comercial|otro
 * @property string $provincia Código ISO 3166-2 (ej: AR-B) — jurisdicción
 * @property int|null $localidad_id Ref soft a localidades (config)
 * @property string $direccion
 * @property string|null $codigo_postal Diferido — no usado en UI
 * @property float|null $latitud
 * @property float|null $longitud
 * @property bool $es_principal
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CuitDomicilio extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'cuit_domicilios';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'cuit_id',
        'tipo',
        'provincia',
        'localidad_id',
        'direccion',
        'codigo_postal',
        'latitud',
        'longitud',
        'es_principal',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'cuit_id' => 'integer',
        'localidad_id' => 'integer',
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];

    // ==================== RELACIONES ====================

    /**
     * CUIT al que pertenece el domicilio
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class);
    }

    /**
     * Localidad (tabla en config, ref soft sin FK cross-DB)
     */
    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }

    /**
     * Provincia por código ISO (tabla en config). La jurisdicción se guarda como
     * ISO 3166-2 en la columna `provincia`, no como provincia_id.
     */
    public function provinciaRef(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia', 'codigo');
    }

    /**
     * Puntos de venta declarados con este domicilio
     */
    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    // ==================== SCOPES ====================

    /**
     * Scope para domicilios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para el domicilio principal
     */
    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }
}
