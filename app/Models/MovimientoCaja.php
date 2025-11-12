<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

/**
 * Modelo MovimientoCaja
 *
 * Registra todos los movimientos de dinero en una caja.
 * Mantiene un historial completo con saldo anterior y posterior.
 *
 * @property int $id
 * @property int $caja_id
 * @property string $tipo_movimiento
 * @property string $concepto
 * @property float $monto
 * @property string $forma_pago
 * @property string|null $referencia
 * @property int|null $venta_id
 * @property int|null $compra_id
 * @property int|null $transferencia_id
 * @property float $saldo_anterior
 * @property float $saldo_posterior
 * @property int $usuario_id
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Caja $caja
 * @property-read User $usuario
 * @property-read Venta|null $venta
 * @property-read Compra|null $compra
 * @property-read TransferenciaEfectivo|null $transferencia
 */
class MovimientoCaja extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'movimientos_caja';

    protected $fillable = [
        'caja_id',
        'tipo_movimiento',
        'concepto',
        'monto',
        'forma_pago',
        'referencia',
        'venta_id',
        'compra_id',
        'transferencia_id',
        'saldo_anterior',
        'saldo_posterior',
        'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    // Relaciones
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaEfectivo::class, 'transferencia_id');
    }

    // Scopes
    public function scopeIngresos($query)
    {
        return $query->where('tipo_movimiento', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo_movimiento', 'egreso');
    }

    public function scopePorCaja($query, int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    public function scopePorFormaPago($query, string $forma)
    {
        return $query->where('forma_pago', $forma);
    }

    public function scopePorFecha($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }

        if ($hasta) {
            $query->where('created_at', '<=', $hasta);
        }

        return $query;
    }

    public function scopeAperturas($query)
    {
        return $query->where('tipo_movimiento', 'apertura');
    }

    public function scopeCierres($query)
    {
        return $query->where('tipo_movimiento', 'cierre');
    }

    public function scopeAjustes($query)
    {
        return $query->where('tipo_movimiento', 'ajuste');
    }

    // Métodos auxiliares

    /**
     * Verifica si es un ingreso
     */
    public function esIngreso(): bool
    {
        return $this->tipo_movimiento === 'ingreso';
    }

    /**
     * Verifica si es un egreso
     */
    public function esEgreso(): bool
    {
        return $this->tipo_movimiento === 'egreso';
    }

    /**
     * Verifica si es una apertura de caja
     */
    public function esApertura(): bool
    {
        return $this->tipo_movimiento === 'apertura';
    }

    /**
     * Verifica si es un cierre de caja
     */
    public function esCierre(): bool
    {
        return $this->tipo_movimiento === 'cierre';
    }

    /**
     * Verifica si es un ajuste manual
     */
    public function esAjuste(): bool
    {
        return $this->tipo_movimiento === 'ajuste';
    }

    /**
     * Verifica si está asociado a una venta
     */
    public function tieneVenta(): bool
    {
        return !is_null($this->venta_id);
    }

    /**
     * Verifica si está asociado a una compra
     */
    public function tieneCompra(): bool
    {
        return !is_null($this->compra_id);
    }

    /**
     * Verifica si está asociado a una transferencia
     */
    public function tieneTransferencia(): bool
    {
        return !is_null($this->transferencia_id);
    }

    /**
     * Obtiene el monto con signo según el tipo de movimiento
     */
    public function obtenerMontoConSigno(): float
    {
        return $this->esIngreso() ? $this->monto : -$this->monto;
    }

    /**
     * Calcula y establece los saldos anterior y posterior
     */
    public function calcularSaldos(): void
    {
        $this->saldo_anterior = $this->caja->saldo_actual;

        if ($this->esIngreso()) {
            $this->saldo_posterior = $this->saldo_anterior + $this->monto;
        } else {
            $this->saldo_posterior = $this->saldo_anterior - $this->monto;
        }
    }
}
