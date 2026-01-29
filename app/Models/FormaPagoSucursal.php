<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo FormaPagoSucursal
 *
 * Tabla pivot que define qué formas de pago están habilitadas en cada sucursal.
 * Permite activar/desactivar formas de pago específicamente por sucursal.
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property int $forma_pago_id ID de la forma de pago
 * @property int $sucursal_id ID de la sucursal
 * @property bool $activo Si esta forma de pago está activa en esta sucursal
 * @property float|null $ajuste_porcentaje Ajuste específico para esta sucursal (null = usar el de la forma de pago)
 * @property bool|null $factura_fiscal Si genera factura fiscal (null = usar el de la forma de pago)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read FormaPago $formaPago
 * @property-read Sucursal $sucursal
 */
class FormaPagoSucursal extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'formas_pago_sucursales';

    protected $fillable = [
        'forma_pago_id',
        'sucursal_id',
        'activo',
        'ajuste_porcentaje',
        'factura_fiscal',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ajuste_porcentaje' => 'decimal:2',
        'factura_fiscal' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Forma de pago asociada
     */
    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    /**
     * Sucursal asociada
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo registros activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Por sucursal
     */
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Scope: Por forma de pago
     */
    public function scopePorFormaPago($query, int $formaPagoId)
    {
        return $query->where('forma_pago_id', $formaPagoId);
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Activa esta forma de pago en la sucursal
     */
    public function activar(): bool
    {
        $this->activo = true;
        return $this->save();
    }

    /**
     * Desactiva esta forma de pago en la sucursal
     */
    public function desactivar(): bool
    {
        $this->activo = false;
        return $this->save();
    }

    /**
     * Alterna el estado activo/inactivo
     */
    public function toggle(): bool
    {
        $this->activo = !$this->activo;
        return $this->save();
    }

    /**
     * Obtiene el ajuste efectivo (el de la sucursal o el de la forma de pago si es null)
     */
    public function getAjusteEfectivo(): float
    {
        if ($this->ajuste_porcentaje !== null) {
            return (float) $this->ajuste_porcentaje;
        }

        return (float) ($this->formaPago->ajuste_porcentaje ?? 0);
    }

    /**
     * Verifica si tiene un ajuste específico para esta sucursal
     */
    public function tieneAjusteEspecifico(): bool
    {
        return $this->ajuste_porcentaje !== null;
    }

    /**
     * Obtiene el valor efectivo de factura_fiscal (el de la sucursal o el de la forma de pago si es null)
     */
    public function getFacturaFiscalEfectivo(): bool
    {
        if ($this->factura_fiscal !== null) {
            return (bool) $this->factura_fiscal;
        }

        return (bool) ($this->formaPago->factura_fiscal ?? false);
    }

    /**
     * Verifica si tiene una configuración de factura_fiscal específica para esta sucursal
     */
    public function tieneFacturaFiscalEspecifica(): bool
    {
        return $this->factura_fiscal !== null;
    }
}
