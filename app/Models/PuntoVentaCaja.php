<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Pivot Punto de Venta-Caja
 *
 * Permite asignar puntos de venta a cajas específicas.
 * Un punto de venta puede estar asociado a múltiples cajas.
 *
 * @property int $id
 * @property int $punto_venta_id
 * @property int $caja_id
 * @property bool $es_defecto Si es el punto de venta por defecto de la caja
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PuntoVentaCaja extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'punto_venta_caja';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'punto_venta_id',
        'caja_id',
        'es_defecto',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'es_defecto' => 'boolean',
    ];

    /**
     * Relación con Punto de Venta
     */
    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    /**
     * Relación con Caja
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Scope para obtener solo los por defecto
     */
    public function scopePorDefecto($query)
    {
        return $query->where('es_defecto', true);
    }

    /**
     * Scope para filtrar por caja
     */
    public function scopePorCaja($query, int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    /**
     * Scope para filtrar por punto de venta
     */
    public function scopePorPuntoVenta($query, int $puntoVentaId)
    {
        return $query->where('punto_venta_id', $puntoVentaId);
    }
}
