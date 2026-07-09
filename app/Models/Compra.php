<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo Compra
 *
 * Representa una compra realizada en una sucursal.
 * Registra el crédito fiscal de IVA para reportes.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int $proveedor_id
 * @property int|null $caja_id
 * @property int $usuario_id
 * @property string $numero_comprobante
 * @property \Carbon\Carbon $fecha
 * @property string $tipo_comprobante
 * @property float $subtotal
 * @property float $total
 * @property float $total_iva
 * @property string $forma_pago
 * @property string $estado
 * @property float $saldo_pendiente
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Sucursal $sucursal
 * @property-read Proveedor $proveedor
 * @property-read Caja|null $caja
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|CompraDetalle[] $detalles
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class Compra extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'compras';

    protected $fillable = [
        'sucursal_id',
        'proveedor_id',
        'compra_origen_id',
        'cuit_id',
        'cuenta_compra_id',
        'caja_id',
        'usuario_id',
        'numero_comprobante',
        'numero_comprobante_proveedor',
        'fecha',
        'fecha_comprobante',
        'fecha_vencimiento',
        'tipo_comprobante',
        'subtotal',
        'neto_gravado',
        'neto_no_gravado',
        'neto_exento',
        'descuento_global_porcentaje',
        'descuento_global_monto',
        'total',
        'total_iva',
        'forma_pago',
        'estado',
        'saldo_pendiente',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_comprobante' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'neto_gravado' => 'decimal:2',
        'neto_no_gravado' => 'decimal:2',
        'neto_exento' => 'decimal:2',
        'descuento_global_porcentaje' => 'decimal:2',
        'descuento_global_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    /**
     * CUIT del comercio al que se imputa fiscalmente la compra (RF-05).
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class, 'cuit_id')->withTrashed();
    }

    /**
     * Percepciones/retenciones sufridas en la factura del proveedor (RF-05).
     */
    public function percepciones(): HasMany
    {
        return $this->hasMany(CompraPercepcion::class, 'compra_id');
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
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'compra_id');
    }

    /**
     * Desglose de IVA por alícuota del comprobante (RF-14) — fuente canónica
     * del crédito fiscal y del Libro IVA Compras.
     */
    public function ivas(): HasMany
    {
        return $this->hasMany(CompraIva::class, 'compra_id');
    }

    /**
     * Conceptos de pie de factura (RF-15): flete, impuestos internos, etc.
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(CompraConcepto::class, 'compra_id');
    }

    /**
     * Cuenta de compra para reportes de gastos (RF-22).
     */
    public function cuentaCompra(): BelongsTo
    {
        return $this->belongsTo(CuentaCompra::class, 'cuenta_compra_id');
    }

    /**
     * Compra original de una nota de crédito (RF-21); NULL en compras
     * normales y NC sueltas.
     */
    public function compraOrigen(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_origen_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(Compra::class, 'compra_origen_id');
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

    public function scopePorProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
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
     * Verifica si la compra está completada
     */
    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Verifica si la compra está pendiente
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Verifica si la compra está cancelada
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
     * Verifica si es compra en cuenta corriente
     */
    public function esCtaCte(): bool
    {
        return $this->forma_pago === 'cta_cte';
    }

    /**
     * Calcula el total de la compra basado en los detalles
     */
    public function calcularTotales(): array
    {
        $subtotal = 0;
        $totalIva = 0;

        foreach ($this->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
            $totalIva += $detalle->iva_monto;
        }

        $total = $subtotal + $totalIva;

        return [
            'subtotal' => round($subtotal, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Actualiza los totales de la compra
     */
    public function actualizarTotales(): bool
    {
        $totales = $this->calcularTotales();

        $this->subtotal = $totales['subtotal'];
        $this->total_iva = $totales['total_iva'];
        $this->total = $totales['total'];

        if (! $this->esCtaCte()) {
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
     * Cancela la compra
     */
    public function cancelar(): bool
    {
        if ($this->estaCancelada()) {
            return false;
        }

        $this->estado = 'cancelada';

        return $this->save();
    }

    /**
     * Verifica si es una transferencia interna entre sucursales
     */
    public function esTransferenciaInterna(): bool
    {
        return $this->proveedor && $this->proveedor->esSucursalInterna();
    }
}
