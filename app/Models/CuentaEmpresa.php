<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaEmpresa extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cuentas_empresa';

    const TIPO_BANCO = 'banco';

    const TIPO_BILLETERA = 'billetera_digital';

    const SUBTIPOS = [
        'cuenta_corriente' => 'Cuenta Corriente',
        'caja_ahorro' => 'Caja de Ahorro',
        'mercadopago' => 'MercadoPago',
        'uala' => 'Ualá',
        'paypal' => 'PayPal',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'nombre',
        'tipo',
        'subtipo',
        'identificador_externo',
        'cuit_id',
        'conciliacion_automatica',
        'banco',
        'numero_cuenta',
        'cbu',
        'alias',
        'titular',
        'moneda_id',
        'saldo_actual',
        'activo',
        'orden',
        'color',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'activo' => 'boolean',
        'conciliacion_automatica' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    /**
     * CUIT al que se imputan fiscalmente los impuestos que el proveedor de
     * pago descuenta en esta cuenta (sistema-impositivo RF-07).
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class)->withTrashed();
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'cuenta_empresa_sucursal', 'cuenta_empresa_id', 'sucursal_id')
            ->withPivot('activo')
            ->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCuentaEmpresa::class, 'cuenta_empresa_id');
    }

    public function conciliaciones(): HasMany
    {
        return $this->hasMany(ConciliacionCuenta::class, 'cuenta_empresa_id');
    }

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeBancos($query)
    {
        return $query->where('tipo', self::TIPO_BANCO);
    }

    public function scopeBilleteras($query)
    {
        return $query->where('tipo', self::TIPO_BILLETERA);
    }

    /**
     * Cuenta identificada por su identidad en el proveedor de pago externo:
     * (subtipo, identificador_externo). Es el match que usa el auto-vínculo
     * de integraciones (findOrCreateParaIntegracion).
     */
    public function scopePorIdentidad($query, string $subtipo, string $identificadorExterno)
    {
        return $query->where('subtipo', $subtipo)
            ->where('identificador_externo', $identificadorExterno);
    }

    // ==================== Accessors ====================

    public function getNombreCompletoAttribute(): string
    {
        if ($this->tipo === self::TIPO_BANCO && $this->banco) {
            return "{$this->banco} - {$this->nombre}";
        }

        return $this->nombre;
    }

    // ==================== Métodos ====================

    public function estaDisponibleEnSucursal(int $sucursalId): bool
    {
        // Si no tiene sucursales asignadas, está disponible en todas
        if ($this->sucursales()->count() === 0) {
            return true;
        }

        return $this->sucursales()
            ->where('sucursal_id', $sucursalId)
            ->wherePivot('activo', true)
            ->exists();
    }

    public function registrarIngreso(float $monto): void
    {
        $this->increment('saldo_actual', $monto);
    }

    public function registrarEgreso(float $monto): void
    {
        $this->decrement('saldo_actual', $monto);
    }

    public function esBanco(): bool
    {
        return $this->tipo === self::TIPO_BANCO;
    }

    public function esBilletera(): bool
    {
        return $this->tipo === self::TIPO_BILLETERA;
    }
}
