<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo FormaPagoCuotaSucursal
 *
 * Tabla pivot que permite configurar recargos específicos de cuotas por sucursal.
 * Si no existe registro para una sucursal, se usa el recargo del plan general.
 *
 * @property int $id
 * @property int $forma_pago_cuota_id ID del plan de cuotas
 * @property int $sucursal_id ID de la sucursal
 * @property float|null $recargo_porcentaje Recargo específico (null = usar el del plan general)
 * @property bool $activo Si este plan está activo en esta sucursal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read FormaPagoCuota $cuota
 * @property-read Sucursal $sucursal
 */
class FormaPagoCuotaSucursal extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'formas_pago_cuotas_sucursales';

    protected $fillable = [
        'forma_pago_cuota_id',
        'sucursal_id',
        'recargo_porcentaje',
        'activo',
    ];

    protected $casts = [
        'recargo_porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Plan de cuotas asociado
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(FormaPagoCuota::class, 'forma_pago_cuota_id');
    }

    /**
     * Sucursal asociada
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo registros activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Por sucursal
     */
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Obtiene el recargo efectivo (el de la sucursal o el del plan general si es null)
     */
    public function getRecargoEfectivo(): float
    {
        if ($this->recargo_porcentaje !== null) {
            return (float) $this->recargo_porcentaje;
        }

        return (float) ($this->cuota->recargo_porcentaje ?? 0);
    }

    /**
     * Verifica si tiene un recargo específico para esta sucursal
     */
    public function tieneRecargoEspecifico(): bool
    {
        return $this->recargo_porcentaje !== null;
    }
}
