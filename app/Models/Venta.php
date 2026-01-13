<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Venta
 *
 * Representa una venta realizada en una sucursal.
 *
 * @property int $id
 * @property string $numero
 * @property int $sucursal_id
 * @property int|null $cliente_id
 * @property int|null $caja_id
 * @property int|null $canal_venta_id
 * @property int|null $forma_venta_id
 * @property int|null $lista_precio_id
 * @property int|null $punto_venta_id
 * @property int|null $forma_pago_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $fecha
 * @property float $subtotal
 * @property float $iva
 * @property float $descuento
 * @property float $total
 * @property float $ajuste_forma_pago
 * @property float $total_final
 * @property string $estado
 * @property bool $es_cuenta_corriente
 * @property float $saldo_pendiente_cache
 * @property \Carbon\Carbon|null $fecha_vencimiento
 * @property float $monto_fiscal_cache
 * @property float $monto_no_fiscal_cache
 * @property int|null $anulado_por_usuario_id
 * @property \Carbon\Carbon|null $anulado_at
 * @property string|null $motivo_anulacion
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read Sucursal $sucursal
 * @property-read Cliente|null $cliente
 * @property-read Caja|null $caja
 * @property-read FormaPago|null $formaPago
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $detalles
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaPago[] $pagos
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class Venta extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'ventas';

    protected $fillable = [
        'numero',
        'sucursal_id',
        'cliente_id',
        'caja_id',
        'canal_venta_id',
        'forma_venta_id',
        'lista_precio_id',
        'punto_venta_id',
        'forma_pago_id',        // FK a formas_pago (para mixtas el detalle está en venta_pagos)
        'usuario_id',
        'fecha',
        'subtotal',
        'iva',
        'descuento',
        'total',
        'ajuste_forma_pago',      // Suma de ajustes (recargos/descuentos) de formas de pago
        'total_final',
        'estado',
        'es_cuenta_corriente',
        'saldo_pendiente_cache',
        'fecha_vencimiento',
        'monto_fiscal_cache',
        'monto_no_fiscal_cache',
        'anulado_por_usuario_id',
        'anulado_at',
        'motivo_anulacion',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'anulado_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'ajuste_forma_pago' => 'decimal:2',
        'total_final' => 'decimal:2',
        'saldo_pendiente_cache' => 'decimal:2',
        'monto_fiscal_cache' => 'decimal:2',
        'monto_no_fiscal_cache' => 'decimal:2',
        'es_cuenta_corriente' => 'boolean',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function canalVenta(): BelongsTo
    {
        return $this->belongsTo(CanalVenta::class, 'canal_venta_id');
    }

    public function formaVenta(): BelongsTo
    {
        return $this->belongsTo(FormaVenta::class, 'forma_venta_id');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    public function promociones(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id');
    }

    public function promocionesEspeciales(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id')
            ->where('tipo_promocion', 'promocion_especial');
    }

    public function promocionesComunes(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id')
            ->where('tipo_promocion', 'promocion');
    }

    // Scopes
    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'cancelada');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopePorFecha($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('fecha', '>=', $desde);
        }

        if ($hasta) {
            $query->where('fecha', '<=', $hasta);
        }

        return $query;
    }

    public function scopeConSaldoPendiente($query)
    {
        return $query->where('saldo_pendiente_cache', '>', 0);
    }

    public function scopeCtaCte($query)
    {
        return $query->where('es_cuenta_corriente', true);
    }

    // Métodos auxiliares

    /**
     * Verifica si la venta está completada
     */
    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Verifica si la venta está pendiente
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Verifica si la venta está cancelada
     */
    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Verifica si tiene saldo pendiente
     */
    public function tieneSaldoPendiente(): bool
    {
        return $this->saldo_pendiente_cache > 0;
    }

    /**
     * Verifica si es venta en cuenta corriente
     */
    public function esCtaCte(): bool
    {
        return $this->es_cuenta_corriente;
    }

    /**
     * Calcula el total de la venta basado en los detalles
     */
    public function calcularTotales(): array
    {
        $subtotal = 0;
        $totalIva = 0;

        foreach ($this->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
            $totalIva += $detalle->iva_monto ?? 0;
        }

        $total = $subtotal + $totalIva - $this->descuento;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($this->descuento, 2),
            'iva' => round($totalIva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Actualiza los totales de la venta
     */
    public function actualizarTotales(): bool
    {
        $totales = $this->calcularTotales();

        $this->subtotal = $totales['subtotal'];
        $this->iva = $totales['iva'];
        $this->total = $totales['total'];

        if (!$this->esCtaCte()) {
            $this->saldo_pendiente_cache = 0;
        } else {
            $this->saldo_pendiente_cache = $this->total;
        }

        return $this->save();
    }

    /**
     * Registra un pago
     */
    public function registrarPago(float $monto): bool
    {
        if ($monto <= 0 || $monto > $this->saldo_pendiente_cache) {
            return false;
        }

        $this->saldo_pendiente_cache -= $monto;

        if ($this->saldo_pendiente_cache == 0 && $this->estado === 'pendiente') {
            $this->estado = 'completada';
        }

        return $this->save();
    }

    /**
     * Cancela la venta
     */
    public function cancelar(): bool
    {
        if ($this->estaCancelada()) {
            return false;
        }

        $this->estado = 'cancelada';
        return $this->save();
    }
}
