<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón del desglose de formas de pago de una orden de pago (análogo de
 * CobroPago). Guarda el ORIGEN de fondos (D14: caja/tesorería/cuenta de
 * empresa), la FK al movimiento generado (contraasiento exacto al anular) y
 * el cierre de turno POR RENGLÓN (D16: turno cerrado bloquea solo renglones
 * de caja).
 */
class PagoProveedorPago extends Model
{
    public const ORIGEN_CAJA = 'caja';

    public const ORIGEN_TESORERIA = 'tesoreria';

    public const ORIGEN_CUENTA_EMPRESA = 'cuenta_empresa';

    protected $connection = 'pymes_tenant';

    protected $table = 'pago_proveedor_pagos';

    protected $fillable = [
        'pago_proveedor_id',
        'forma_pago_id',
        'monto',
        'origen',
        'caja_id',
        'cuenta_empresa_id',
        'movimiento_caja_id',
        'movimiento_cuenta_empresa_id',
        'movimiento_tesoreria_id',
        'cierre_turno_id',
        'estado',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function pagoProveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedor::class);
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function cuentaEmpresa(): BelongsTo
    {
        return $this->belongsTo(CuentaEmpresa::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
}
