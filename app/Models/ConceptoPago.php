<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo ConceptoPago
 *
 * Define los tipos/conceptos base de pago disponibles en el sistema.
 * Esta tabla es fija y no editable por el usuario.
 * Los conceptos agrupan las formas de pago por tipo.
 *
 * CONCEPTOS DISPONIBLES:
 * - efectivo: Pago en efectivo
 * - tarjeta_debito: Tarjeta de débito
 * - tarjeta_credito: Tarjeta de crédito
 * - transferencia: Transferencia bancaria
 * - wallet: Billeteras digitales (MercadoPago, PayPal, etc.)
 * - cheque: Pago con cheque
 * - otro: Otros medios de pago
 *
 * @property int $id
 * @property string $codigo Código único del concepto
 * @property string $nombre Nombre del concepto
 * @property string|null $descripcion Descripción detallada
 * @property bool $permite_cuotas Si este concepto permite cuotas
 * @property bool $permite_vuelto Si este concepto permite vuelto (efectivo)
 * @property bool $activo Si está activo
 * @property int $orden Orden de visualización
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|FormaPago[] $formasPago
 * @property-read \Illuminate\Database\Eloquent\Collection|FormaPago[] $formasPagoMixtas
 */
class ConceptoPago extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'conceptos_pago';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'permite_cuotas',
        'permite_vuelto',
        'activo',
        'orden',
    ];

    protected $casts = [
        'permite_cuotas' => 'boolean',
        'permite_vuelto' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Constantes de códigos ====================

    public const EFECTIVO = 'efectivo';
    public const TARJETA_DEBITO = 'tarjeta_debito';
    public const TARJETA_CREDITO = 'tarjeta_credito';
    public const TRANSFERENCIA = 'transferencia';
    public const WALLET = 'wallet';
    public const CHEQUE = 'cheque';
    public const CREDITO_CLIENTE = 'credito_cliente';

    // ==================== Relaciones ====================

    /**
     * Formas de pago simples que usan este concepto
     */
    public function formasPago(): HasMany
    {
        return $this->hasMany(FormaPago::class, 'concepto_pago_id');
    }

    /**
     * Formas de pago mixtas que incluyen este concepto
     */
    public function formasPagoMixtas(): BelongsToMany
    {
        return $this->belongsToMany(
            FormaPago::class,
            'forma_pago_conceptos',
            'concepto_pago_id',
            'forma_pago_id'
        )->withTimestamps();
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo conceptos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Ordenados por orden
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }

    /**
     * Scope: Solo conceptos que permiten cuotas
     */
    public function scopeConCuotas($query)
    {
        return $query->where('permite_cuotas', true);
    }

    /**
     * Scope: Solo conceptos que permiten vuelto
     */
    public function scopeConVuelto($query)
    {
        return $query->where('permite_vuelto', true);
    }

    /**
     * Scope: Por código
     */
    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // ==================== Métodos Estáticos ====================

    /**
     * Obtiene un concepto por código
     */
    public static function obtenerPorCodigo(string $codigo): ?self
    {
        return static::where('codigo', $codigo)->first();
    }

    /**
     * Obtiene el concepto de efectivo
     */
    public static function efectivo(): ?self
    {
        return static::obtenerPorCodigo(self::EFECTIVO);
    }

    /**
     * Obtiene el concepto de tarjeta de crédito
     */
    public static function tarjetaCredito(): ?self
    {
        return static::obtenerPorCodigo(self::TARJETA_CREDITO);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Verifica si es efectivo
     */
    public function esEfectivo(): bool
    {
        return $this->codigo === self::EFECTIVO;
    }

    /**
     * Verifica si es tarjeta de crédito
     */
    public function esTarjetaCredito(): bool
    {
        return $this->codigo === self::TARJETA_CREDITO;
    }

    /**
     * Verifica si es tarjeta de débito
     */
    public function esTarjetaDebito(): bool
    {
        return $this->codigo === self::TARJETA_DEBITO;
    }

    /**
     * Verifica si es transferencia
     */
    public function esTransferencia(): bool
    {
        return $this->codigo === self::TRANSFERENCIA;
    }

    /**
     * Verifica si es wallet/billetera digital
     */
    public function esWallet(): bool
    {
        return $this->codigo === self::WALLET;
    }

    /**
     * Verifica si es una forma de pago electrónica
     */
    public function esElectronico(): bool
    {
        return in_array($this->codigo, [
            self::TARJETA_DEBITO,
            self::TARJETA_CREDITO,
            self::TRANSFERENCIA,
            self::WALLET,
        ]);
    }

    /**
     * Obtiene todas las formas de pago activas que usan este concepto
     * (tanto simples como en mixtas)
     */
    public function obtenerFormasPagoActivas()
    {
        // Formas de pago simples con este concepto
        $simples = $this->formasPago()
            ->where('activo', true)
            ->where('es_mixta', false)
            ->get();

        return $simples;
    }
}
