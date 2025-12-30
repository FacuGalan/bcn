<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo FormaPagoCuota
 *
 * Define las configuraciones de cuotas disponibles para formas de pago
 * que permiten financiación (principalmente tarjetas de crédito).
 *
 * Cada configuración especifica la cantidad de cuotas y el recargo
 * porcentual que se aplica al total.
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property int $forma_pago_id ID de la forma de pago
 * @property int $cantidad_cuotas Cantidad de cuotas (1, 3, 6, 12, etc.)
 * @property float $recargo_porcentaje Recargo porcentual sobre el total
 * @property string|null $descripcion Descripción opcional del plan
 * @property bool $activo Si esta configuración está activa
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read FormaPago $formaPago
 * @property-read \Illuminate\Database\Eloquent\Collection|FormaPagoCuotaSucursal[] $sucursalesConfig
 */
class FormaPagoCuota extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'formas_pago_cuotas';

    protected $fillable = [
        'forma_pago_id',
        'cantidad_cuotas',
        'recargo_porcentaje',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'cantidad_cuotas' => 'integer',
        'recargo_porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Forma de pago a la que pertenece esta configuración
     */
    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    /**
     * Configuraciones específicas por sucursal
     */
    public function sucursalesConfig(): HasMany
    {
        return $this->hasMany(FormaPagoCuotaSucursal::class, 'forma_pago_cuota_id');
    }

    /**
     * Obtiene la configuración para una sucursal específica
     */
    public function getConfigSucursal(int $sucursalId): ?FormaPagoCuotaSucursal
    {
        return $this->sucursalesConfig()->where('sucursal_id', $sucursalId)->first();
    }

    /**
     * Obtiene el recargo efectivo para una sucursal
     */
    public function getRecargoParaSucursal(int $sucursalId): float
    {
        $config = $this->getConfigSucursal($sucursalId);

        if ($config && $config->recargo_porcentaje !== null) {
            return (float) $config->recargo_porcentaje;
        }

        return (float) $this->recargo_porcentaje;
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo configuraciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Ordenar por cantidad de cuotas
     */
    public function scopeOrdenadoPorCuotas($query)
    {
        return $query->orderBy('cantidad_cuotas');
    }

    /**
     * Scope: Por cantidad de cuotas específica
     */
    public function scopePorCantidadCuotas($query, int $cantidadCuotas)
    {
        return $query->where('cantidad_cuotas', $cantidadCuotas);
    }

    /**
     * Scope: Sin recargo (0%)
     */
    public function scopeSinRecargo($query)
    {
        return $query->where('recargo_porcentaje', 0);
    }

    /**
     * Scope: Con recargo
     */
    public function scopeConRecargo($query)
    {
        return $query->where('recargo_porcentaje', '>', 0);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Calcula el recargo en monto para un total dado
     *
     * @param float $total Total de la venta
     * @return float Monto del recargo
     */
    public function calcularRecargo(float $total): float
    {
        return round($total * ($this->recargo_porcentaje / 100), 2);
    }

    /**
     * Calcula el total con recargo
     *
     * @param float $total Total de la venta
     * @return float Total incluyendo recargo
     */
    public function calcularTotalConRecargo(float $total): float
    {
        return round($total + $this->calcularRecargo($total), 2);
    }

    /**
     * Calcula el valor de cada cuota
     *
     * @param float $total Total de la venta
     * @return float Valor de cada cuota
     */
    public function calcularValorCuota(float $total): float
    {
        $totalConRecargo = $this->calcularTotalConRecargo($total);
        return round($totalConRecargo / $this->cantidad_cuotas, 2);
    }

    /**
     * Obtiene información completa del plan de cuotas
     *
     * @param float $total Total de la venta
     * @return array Información del plan
     */
    public function obtenerInformacionPlan(float $total): array
    {
        $recargo = $this->calcularRecargo($total);
        $totalConRecargo = $this->calcularTotalConRecargo($total);
        $valorCuota = $this->calcularValorCuota($total);

        return [
            'cantidad_cuotas' => $this->cantidad_cuotas,
            'recargo_porcentaje' => $this->recargo_porcentaje,
            'recargo_monto' => $recargo,
            'total_original' => round($total, 2),
            'total_con_recargo' => $totalConRecargo,
            'valor_cuota' => $valorCuota,
            'descripcion' => $this->obtenerDescripcion($valorCuota),
        ];
    }

    /**
     * Obtiene una descripción legible del plan
     *
     * @param float|null $valorCuota Valor calculado de la cuota
     * @return string Descripción del plan
     */
    public function obtenerDescripcion(?float $valorCuota = null): string
    {
        if ($this->cantidad_cuotas === 1) {
            return '1 pago';
        }

        $desc = "{$this->cantidad_cuotas} cuotas";

        if ($valorCuota) {
            $desc .= " de $" . number_format($valorCuota, 2);
        }

        if ($this->recargo_porcentaje > 0) {
            $desc .= " ({$this->recargo_porcentaje}% recargo)";
        } else {
            $desc .= " (sin interés)";
        }

        return $desc;
    }

    /**
     * Verifica si es pago en una sola cuota
     */
    public function esUnPago(): bool
    {
        return $this->cantidad_cuotas === 1;
    }

    /**
     * Verifica si tiene recargo
     */
    public function tieneRecargo(): bool
    {
        return $this->recargo_porcentaje > 0;
    }
}
