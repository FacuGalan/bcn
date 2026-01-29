<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de Pago de Cobro
 *
 * Representa el desglose de formas de pago utilizadas en un cobro.
 */
class CobroPago extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cobro_pagos';

    protected $fillable = [
        'cobro_id',
        'forma_pago_id',
        'concepto_pago_id',
        'monto_base',
        'ajuste_porcentaje',
        'monto_ajuste',
        'monto_final',
        'monto_recibido',
        'vuelto',
        'cuotas',
        'recargo_cuotas_porcentaje',
        'recargo_cuotas_monto',
        'monto_cuota',
        'referencia',
        'observaciones',
        'afecta_caja',
        'movimiento_caja_id',
        'estado',
        'cierre_turno_id',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'ajuste_porcentaje' => 'decimal:2',
        'monto_ajuste' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'cuotas' => 'integer',
        'recargo_cuotas_porcentaje' => 'decimal:2',
        'recargo_cuotas_monto' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'afecta_caja' => 'boolean',
    ];

    // ==================== Relaciones ====================

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class);
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class);
    }

    public function conceptoPago(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class);
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class);
    }

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
}
