<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo Venta
 *
 * Representa una venta realizada en una sucursal.
 * Soporta diferentes tipos de comprobantes y formas de pago.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int|null $cliente_id
 * @property int|null $caja_id
 * @property int $usuario_id
 * @property string $numero_comprobante
 * @property \Carbon\Carbon $fecha
 * @property string $tipo_comprobante
 * @property float $subtotal
 * @property float $descuento
 * @property float $total
 * @property float $total_iva
 * @property string $forma_pago
 * @property string $estado
 * @property float $saldo_pendiente
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read Cliente|null $cliente
 * @property-read Caja|null $caja
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $detalles
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class Venta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'ventas';

    protected $fillable = [
        'sucursal_id',
        'cliente_id',
        'caja_id',
        'usuario_id',
        'numero_comprobante',
        'fecha',
        'tipo_comprobante',
        'subtotal',
        'descuento',
        'total',
        'total_iva',
        'forma_pago',
        'estado',
        'saldo_pendiente',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'venta_id');
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
        return $query->where('saldo_pendiente', '>', 0);
    }

    public function scopeCtaCte($query)
    {
        return $query->where('forma_pago', 'cta_cte');
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
        return $this->saldo_pendiente > 0;
    }

    /**
     * Verifica si es venta en cuenta corriente
     */
    public function esCtaCte(): bool
    {
        return $this->forma_pago === 'cta_cte';
    }

    /**
     * Verifica si es factura A
     */
    public function esFacturaA(): bool
    {
        return str_contains(strtolower($this->tipo_comprobante), 'factura_a');
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
            $totalIva += $detalle->iva_monto;
        }

        $total = $subtotal + $totalIva - $this->descuento;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($this->descuento, 2),
            'total_iva' => round($totalIva, 2),
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
        $this->total_iva = $totales['total_iva'];
        $this->total = $totales['total'];

        if (!$this->esCtaCte()) {
            $this->saldo_pendiente = 0;
        } else {
            $this->saldo_pendiente = $this->total;
        }

        return $this->save();
    }

    /**
     * Registra un pago
     */
    public function registrarPago(float $monto): bool
    {
        if ($monto <= 0 || $monto > $this->saldo_pendiente) {
            return false;
        }

        $this->saldo_pendiente -= $monto;

        if ($this->saldo_pendiente == 0 && $this->estado === 'pendiente') {
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
