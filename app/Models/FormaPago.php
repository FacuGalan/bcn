<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo FormaPago
 *
 * Define las diferentes formas de pago disponibles a nivel comercio.
 * Cada forma de pago puede ser habilitada/deshabilitada por sucursal
 * y puede tener configuración de cuotas con recargos.
 *
 * TIPOS DE FORMAS DE PAGO:
 * - Simple: Tiene un único concepto de pago (efectivo, tarjeta, etc.)
 * - Mixta: Acepta múltiples conceptos de pago (es_mixta = true)
 *
 * CONCEPTOS DISPONIBLES (en tabla conceptos_pago):
 * - efectivo: Pago en efectivo
 * - tarjeta_debito: Tarjeta de débito
 * - tarjeta_credito: Tarjeta de crédito
 * - transferencia: Transferencia bancaria
 * - wallet: Billeteras digitales (MercadoPago, PayPal, etc.)
 * - cheque: Pago con cheque
 * - otro: Otros medios de pago
 *
 * @property int $id
 * @property string $nombre Nombre de la forma de pago
 * @property string|null $codigo Código alfanumérico
 * @property string $concepto Concepto/tipo de forma de pago (enum legacy)
 * @property string|null $descripcion Descripción detallada
 * @property int|null $concepto_pago_id FK al concepto de pago (NULL para mixtas)
 * @property bool $es_mixta Si es una forma de pago mixta
 * @property bool $permite_cuotas Si permite pago en cuotas
 * @property float $ajuste_porcentaje Ajuste porcentual: positivo=recargo, negativo=descuento
 * @property bool $factura_fiscal Si esta forma de pago genera factura fiscal por defecto
 * @property bool $activo Si la forma de pago está activa a nivel comercio
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read ConceptoPago|null $conceptoPago
 * @property-read \Illuminate\Database\Eloquent\Collection|ConceptoPago[] $conceptosPermitidos
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|FormaPagoSucursal[] $formaPagoSucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|FormaPagoCuota[] $cuotas
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $promocionesCondiciones
 */
class FormaPago extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'formas_pago';

    protected $fillable = [
        'nombre',
        'codigo',
        'concepto',
        'descripcion',
        'concepto_pago_id',
        'es_mixta',
        'permite_cuotas',
        'ajuste_porcentaje',
        'factura_fiscal',
        'activo',
    ];

    protected $casts = [
        'permite_cuotas' => 'boolean',
        'es_mixta' => 'boolean',
        'ajuste_porcentaje' => 'decimal:2',
        'factura_fiscal' => 'boolean',
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Concepto de pago asociado (para formas de pago simples)
     * NULL para formas de pago mixtas
     */
    public function conceptoPago(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_pago_id');
    }

    /**
     * Conceptos de pago permitidos (para formas de pago mixtas)
     * Se usa la tabla pivot forma_pago_conceptos
     */
    public function conceptosPermitidos(): BelongsToMany
    {
        return $this->belongsToMany(
            ConceptoPago::class,
            'forma_pago_conceptos',
            'forma_pago_id',
            'concepto_pago_id'
        )->withTimestamps();
    }

    /**
     * Sucursales donde esta forma de pago está habilitada
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'formas_pago_sucursales', 'forma_pago_id', 'sucursal_id')
                    ->withPivot('activo')
                    ->withTimestamps();
    }

    /**
     * Configuraciones por sucursal
     */
    public function formaPagoSucursales(): HasMany
    {
        return $this->hasMany(FormaPagoSucursal::class, 'forma_pago_id');
    }

    /**
     * Configuración de cuotas disponibles
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(FormaPagoCuota::class, 'forma_pago_id');
    }

    /**
     * Condiciones de promociones que aplican a esta forma de pago
     */
    public function promocionesCondiciones(): HasMany
    {
        return $this->hasMany(PromocionCondicion::class, 'forma_pago_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo formas de pago activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Solo formas que permiten cuotas
     */
    public function scopeConCuotas($query)
    {
        return $query->where('permite_cuotas', true);
    }

    /**
     * Scope: Por concepto
     */
    public function scopePorConcepto($query, string $concepto)
    {
        return $query->where('concepto', $concepto);
    }

    /**
     * Scope: Efectivo
     */
    public function scopeEfectivo($query)
    {
        return $query->where('concepto', 'efectivo');
    }

    /**
     * Scope: Tarjetas (débito o crédito)
     */
    public function scopeTarjetas($query)
    {
        return $query->whereIn('concepto', ['tarjeta_debito', 'tarjeta_credito']);
    }

    /**
     * Scope: Solo formas de pago simples (no mixtas)
     */
    public function scopeSimples($query)
    {
        return $query->where('es_mixta', false);
    }

    /**
     * Scope: Solo formas de pago mixtas
     */
    public function scopeMixtas($query)
    {
        return $query->where('es_mixta', true);
    }

    /**
     * Scope: Por concepto de pago (usando nueva relación)
     */
    public function scopePorConceptoPago($query, int $conceptoPagoId)
    {
        return $query->where('concepto_pago_id', $conceptoPagoId);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Verifica si está habilitada en una sucursal específica
     */
    public function estaHabilitadaEnSucursal(int $sucursalId): bool
    {
        return $this->sucursales()
                    ->where('sucursal_id', $sucursalId)
                    ->wherePivot('activo', true)
                    ->exists();
    }

    /**
     * Obtiene las cuotas disponibles activas
     */
    public function obtenerCuotasDisponibles()
    {
        if (!$this->permite_cuotas) {
            return collect([]);
        }

        return $this->cuotas()
                    ->where('activo', true)
                    ->orderBy('cantidad_cuotas')
                    ->get();
    }

    /**
     * Calcula el recargo/descuento para un monto dado
     *
     * @param float $monto
     * @return array ['tipo' => 'recargo'|'descuento'|'ninguno', 'porcentaje' => float, 'monto' => float]
     */
    public function calcularAjuste(float $monto): array
    {
        if ($this->ajuste_porcentaje > 0) {
            return [
                'tipo' => 'recargo',
                'porcentaje' => $this->ajuste_porcentaje,
                'monto' => round($monto * ($this->ajuste_porcentaje / 100), 2),
            ];
        }

        if ($this->ajuste_porcentaje < 0) {
            return [
                'tipo' => 'descuento',
                'porcentaje' => abs($this->ajuste_porcentaje),
                'monto' => round($monto * (abs($this->ajuste_porcentaje) / 100), 2),
            ];
        }

        return [
            'tipo' => 'ninguno',
            'porcentaje' => 0,
            'monto' => 0,
        ];
    }

    /**
     * Verifica si es una forma de pago electrónica
     */
    public function esElectronica(): bool
    {
        return in_array($this->concepto, [
            'tarjeta_debito',
            'tarjeta_credito',
            'transferencia',
            'wallet',
        ]);
    }

    /**
     * Verifica si es efectivo
     */
    public function esEfectivo(): bool
    {
        // Usar nueva relación si existe
        if ($this->concepto_pago_id && $this->conceptoPago) {
            return $this->conceptoPago->esEfectivo();
        }
        // Fallback al campo legacy
        return $this->concepto === 'efectivo';
    }

    /**
     * Verifica si es una forma de pago mixta
     */
    public function esMixta(): bool
    {
        return $this->es_mixta === true;
    }

    /**
     * Verifica si es una forma de pago simple (no mixta)
     */
    public function esSimple(): bool
    {
        return !$this->esMixta();
    }

    /**
     * Obtiene el concepto de pago asociado
     * Para simples: retorna el concepto directo
     * Para mixtas: retorna null (tiene múltiples conceptos)
     */
    public function obtenerConcepto(): ?ConceptoPago
    {
        if ($this->esMixta()) {
            return null;
        }
        return $this->conceptoPago;
    }

    /**
     * Obtiene todos los conceptos que acepta esta forma de pago
     * Para simples: retorna array con un solo concepto
     * Para mixtas: retorna array con todos los conceptos permitidos
     */
    public function obtenerConceptos()
    {
        if ($this->esMixta()) {
            return $this->conceptosPermitidos()->activos()->ordenados()->get();
        }

        // Para simples, retornar el concepto único como colección
        $concepto = $this->conceptoPago;
        return $concepto ? collect([$concepto]) : collect([]);
    }

    /**
     * Verifica si esta forma de pago acepta un concepto específico
     */
    public function aceptaConcepto(int $conceptoPagoId): bool
    {
        if ($this->esMixta()) {
            return $this->conceptosPermitidos()->where('conceptos_pago.id', $conceptoPagoId)->exists();
        }
        return $this->concepto_pago_id === $conceptoPagoId;
    }

    /**
     * Verifica si esta forma de pago requiere desglose
     * (es mixta y tiene más de un concepto)
     */
    public function requiereDesglose(): bool
    {
        if (!$this->esMixta()) {
            return false;
        }
        return $this->conceptosPermitidos()->count() > 1;
    }

    /**
     * Obtiene las formas de pago simples activas que pueden usarse
     * en el desglose de esta forma de pago mixta
     *
     * @param int|null $conceptoPagoId Filtrar por concepto específico
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerFormasPagoParaDesglose(?int $conceptoPagoId = null)
    {
        if (!$this->esMixta()) {
            return collect([]);
        }

        $conceptosIds = $this->conceptosPermitidos()->pluck('conceptos_pago.id');

        $query = static::activas()
            ->simples()
            ->whereIn('concepto_pago_id', $conceptosIds);

        if ($conceptoPagoId) {
            $query->where('concepto_pago_id', $conceptoPagoId);
        }

        return $query->with('conceptoPago')->get();
    }

    /**
     * Verifica si el concepto permite cuotas
     */
    public function conceptoPermiteCuotas(): bool
    {
        if ($this->esMixta()) {
            // Una mixta permite cuotas si alguno de sus conceptos las permite
            return $this->conceptosPermitidos()->where('permite_cuotas', true)->exists();
        }

        if ($this->conceptoPago) {
            return $this->conceptoPago->permite_cuotas;
        }

        return $this->permite_cuotas;
    }

    /**
     * Verifica si el concepto permite vuelto
     */
    public function conceptoPermiteVuelto(): bool
    {
        if ($this->esMixta()) {
            // Una mixta permite vuelto si alguno de sus conceptos lo permite
            return $this->conceptosPermitidos()->where('permite_vuelto', true)->exists();
        }

        if ($this->conceptoPago) {
            return $this->conceptoPago->permite_vuelto;
        }

        return $this->esEfectivo();
    }
}
