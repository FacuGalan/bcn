<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de pago a proveedor (RF-19, análogo de Cobro): agrupa las
 * aplicaciones a compras y el desglose de formas de pago con su origen de
 * fondos (D14). TODO pago pasa por PagoProveedorService.
 */
class PagoProveedor extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pagos_proveedores';

    protected $fillable = [
        'numero',
        'proveedor_id',
        'sucursal_id',
        'caja_id',
        'fecha',
        'monto_total',
        'saldo_favor_usado',
        'monto_a_favor',
        'tipo',
        'observaciones',
        'estado',
        'motivo_anulacion',
        'anulado_por_usuario_id',
        'anulado_at',
        'cierre_turno_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_total' => 'decimal:2',
        'saldo_favor_usado' => 'decimal:2',
        'monto_a_favor' => 'decimal:2',
        'anulado_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(PagoProveedorCompra::class, 'pago_proveedor_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoProveedorPago::class, 'pago_proveedor_id');
    }

    // ==================== SCOPES / HELPERS ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    public function esAnticipo(): bool
    {
        return $this->tipo === 'anticipo';
    }
}
