<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Cobro
 *
 * Representa un cobro realizado para saldar cuenta corriente.
 * Un cobro puede aplicarse a múltiples ventas.
 */
class Cobro extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'cobros';

    protected $fillable = [
        'sucursal_id',
        'cliente_id',
        'caja_id',
        'numero_recibo',
        'fecha',
        'hora',
        'monto_cobrado',
        'interes_aplicado',
        'descuento_aplicado',
        'monto_aplicado_a_deuda',
        'monto_a_favor',
        'estado',
        'observaciones',
        'usuario_id',
        'anulado_por_usuario_id',
        'anulado_at',
        'motivo_anulacion',
        'cierre_turno_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'monto_cobrado' => 'decimal:2',
        'interes_aplicado' => 'decimal:2',
        'descuento_aplicado' => 'decimal:2',
        'monto_aplicado_a_deuda' => 'decimal:2',
        'monto_a_favor' => 'decimal:2',
        'anulado_at' => 'datetime',
    ];

    // ==================== Relaciones ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class, 'cobro_ventas')
            ->withPivot(['monto_aplicado', 'interes_aplicado', 'saldo_anterior', 'saldo_posterior'])
            ->withTimestamps();
    }

    public function cobroVentas(): HasMany
    {
        return $this->hasMany(CobroVenta::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(CobroPago::class);
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

    public function scopeAnulados($query)
    {
        return $query->where('estado', 'anulado');
    }

    // ==================== Métodos ====================

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    /**
     * Anula el cobro y revierte los saldos
     */
    public function anular(int $usuarioId, string $motivo): void
    {
        $this->update([
            'estado' => 'anulado',
            'anulado_por_usuario_id' => $usuarioId,
            'anulado_at' => now(),
            'motivo_anulacion' => $motivo,
        ]);

        // Revertir saldos en ventas
        foreach ($this->cobroVentas as $cobroVenta) {
            $venta = $cobroVenta->venta;
            $venta->increment('saldo_pendiente_cache', $cobroVenta->monto_aplicado);
        }

        // Anular pagos asociados
        foreach ($this->pagos as $pago) {
            $pago->update(['estado' => 'anulado']);
        }
    }
}
