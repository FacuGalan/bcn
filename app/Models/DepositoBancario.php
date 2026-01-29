<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DepositoBancario
 *
 * Registra los depósitos de efectivo de tesorería a cuentas bancarias.
 *
 * @property int $id
 * @property int $tesoreria_id
 * @property int $cuenta_bancaria_id
 * @property float $monto
 * @property \Carbon\Carbon $fecha_deposito
 * @property string|null $numero_comprobante
 * @property int $usuario_id
 * @property string $estado
 * @property \Carbon\Carbon|null $fecha_confirmacion
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Tesoreria $tesoreria
 * @property-read CuentaBancaria $cuentaBancaria
 * @property-read User $usuario
 */
class DepositoBancario extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'depositos_bancarios';

    protected $fillable = [
        'tesoreria_id',
        'cuenta_bancaria_id',
        'monto',
        'fecha_deposito',
        'numero_comprobante',
        'usuario_id',
        'estado',
        'fecha_confirmacion',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_deposito' => 'date',
        'fecha_confirmacion' => 'datetime',
    ];

    // Estados
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_CONFIRMADO => 'Confirmado',
        self::ESTADO_CANCELADO => 'Cancelado',
    ];

    // ==================== RELACIONES ====================

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeConfirmados($query)
    {
        return $query->where('estado', self::ESTADO_CONFIRMADO);
    }

    public function scopeDelPeriodo($query, \Carbon\Carbon $desde, \Carbon\Carbon $hasta)
    {
        return $query->whereBetween('fecha_deposito', [$desde, $hasta]);
    }

    // ==================== ACCESSORS ====================

    public function getEstaPendienteAttribute(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function getEstaConfirmadoAttribute(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }

    public function getEstaCanceladoAttribute(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    // ==================== MÉTODOS ====================

    /**
     * Confirma el depósito
     */
    public function confirmar(): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->estado = self::ESTADO_CONFIRMADO;
        $this->fecha_confirmacion = now();
        $this->save();

        // Actualizar saldo de la cuenta bancaria
        $this->cuentaBancaria->registrarDeposito($this->monto);

        return true;
    }

    /**
     * Cancela el depósito
     */
    public function cancelar(): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->estado = self::ESTADO_CANCELADO;
        $this->save();

        // Devolver el monto a la tesorería
        $this->tesoreria->saldo_actual += $this->monto;
        $this->tesoreria->save();

        return true;
    }
}
