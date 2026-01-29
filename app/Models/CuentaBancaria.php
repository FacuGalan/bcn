<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CuentaBancaria
 *
 * Representa una cuenta bancaria asociada a una sucursal para realizar depósitos.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property string $banco
 * @property string $tipo_cuenta
 * @property string $numero_cuenta
 * @property string|null $cbu
 * @property string|null $alias
 * @property string|null $titular
 * @property string $moneda
 * @property float $saldo_actual
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|DepositoBancario[] $depositos
 */
class CuentaBancaria extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'sucursal_id',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'cbu',
        'alias',
        'titular',
        'moneda',
        'saldo_actual',
        'activo',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // Tipos de cuenta
    public const TIPO_CORRIENTE = 'corriente';
    public const TIPO_AHORRO = 'ahorro';
    public const TIPO_CAJA_AHORRO = 'caja_ahorro';

    public const TIPOS_CUENTA = [
        self::TIPO_CORRIENTE => 'Cuenta Corriente',
        self::TIPO_AHORRO => 'Caja de Ahorro',
        self::TIPO_CAJA_AHORRO => 'Caja de Ahorro',
    ];

    // ==================== RELACIONES ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function depositos(): HasMany
    {
        return $this->hasMany(DepositoBancario::class, 'cuenta_bancaria_id');
    }

    // ==================== SCOPES ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorBanco($query, string $banco)
    {
        return $query->where('banco', $banco);
    }

    // ==================== ACCESSORS ====================

    public function getNombreCompletoAttribute(): string
    {
        $tipo = self::TIPOS_CUENTA[$this->tipo_cuenta] ?? $this->tipo_cuenta;
        return "{$this->banco} - {$tipo} {$this->numero_cuenta}";
    }

    public function getCbuFormateadoAttribute(): ?string
    {
        if (!$this->cbu) {
            return null;
        }
        // Formatear CBU: XXXX XXXX XXXX XXXX XXXX XX
        return implode(' ', str_split($this->cbu, 4));
    }

    // ==================== MÉTODOS ====================

    /**
     * Registra un depósito en la cuenta
     */
    public function registrarDeposito(float $monto): void
    {
        $this->saldo_actual += $monto;
        $this->save();
    }

    /**
     * Obtiene el total de depósitos de un período
     */
    public function totalDepositosDelPeriodo(\Carbon\Carbon $desde, \Carbon\Carbon $hasta): float
    {
        return $this->depositos()
            ->where('estado', 'confirmado')
            ->whereBetween('fecha_deposito', [$desde, $hasta])
            ->sum('monto');
    }
}
