<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentaPago
 *
 * Representa un pago individual dentro de una venta.
 * Permite desglosar ventas con múltiples formas de pago.
 *
 * @property int $id
 * @property int $venta_id
 * @property int $forma_pago_id
 * @property int|null $concepto_pago_id
 * @property float $monto_base
 * @property float $ajuste_porcentaje
 * @property float $monto_ajuste
 * @property float $monto_final
 * @property float|null $monto_recibido
 * @property float|null $vuelto
 * @property int|null $cuotas
 * @property float|null $recargo_cuotas_porcentaje
 * @property float|null $recargo_cuotas_monto
 * @property float|null $monto_cuota
 * @property string|null $referencia
 * @property string|null $observaciones
 *
 * @property int|null $cierre_turno_id
 *
 * @property-read Venta $venta
 * @property-read FormaPago $formaPago
 * @property-read ConceptoPago|null $conceptoPago
 * @property-read CierreTurno|null $cierreTurno
 */
class VentaPago extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'forma_pago_id',
        'concepto_pago_id',
        'monto_base',
        'ajuste_porcentaje',
        'monto_ajuste',
        'monto_final',
        'monto_recibido',
        'vuelto',
        'cuotas',
        'recargo_cuotas_porcentaje',
        'recargo_cuotas_monto',
        'monto_cuota',
        'referencia',
        'observaciones',
        'es_cuenta_corriente',
        'afecta_caja',
        'estado',
        'movimiento_caja_id',
        'comprobante_fiscal_id',
        'monto_facturado',
        'anulado_por_usuario_id',
        'anulado_at',
        'motivo_anulacion',
        'cierre_turno_id',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'ajuste_porcentaje' => 'decimal:2',
        'monto_ajuste' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'cuotas' => 'integer',
        'recargo_cuotas_porcentaje' => 'decimal:2',
        'recargo_cuotas_monto' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'monto_facturado' => 'decimal:2',
        'es_cuenta_corriente' => 'boolean',
        'afecta_caja' => 'boolean',
        'anulado_at' => 'datetime',
    ];

    // =========================================
    // RELACIONES
    // =========================================

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function conceptoPago(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_pago_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    public function comprobanteFiscal(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'comprobante_fiscal_id');
    }

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
    }

    // =========================================
    // SCOPES
    // =========================================

    public function scopePorVenta($query, int $ventaId)
    {
        return $query->where('venta_id', $ventaId);
    }

    public function scopePorFormaPago($query, int $formaPagoId)
    {
        return $query->where('forma_pago_id', $formaPagoId);
    }

    public function scopePorConcepto($query, int $conceptoId)
    {
        return $query->where('concepto_pago_id', $conceptoId);
    }

    public function scopeConCuotas($query)
    {
        return $query->whereNotNull('cuotas')->where('cuotas', '>', 1);
    }

    public function scopeEfectivo($query)
    {
        return $query->whereHas('conceptoPago', function ($q) {
            $q->where('codigo', ConceptoPago::EFECTIVO);
        });
    }

    public function scopeFacturados($query)
    {
        return $query->whereNotNull('comprobante_fiscal_id');
    }

    public function scopeNoFacturados($query)
    {
        return $query->whereNull('comprobante_fiscal_id');
    }

    // =========================================
    // MÉTODOS AUXILIARES
    // =========================================

    /**
     * Verifica si el pago está facturado fiscalmente
     */
    public function estaFacturado(): bool
    {
        return $this->comprobante_fiscal_id !== null;
    }

    /**
     * Verifica si el pago tiene cuotas
     */
    public function tieneCuotas(): bool
    {
        return $this->cuotas && $this->cuotas > 1;
    }

    /**
     * Verifica si el pago tiene ajuste
     */
    public function tieneAjuste(): bool
    {
        return $this->ajuste_porcentaje != 0;
    }

    /**
     * Verifica si es un recargo
     */
    public function esRecargo(): bool
    {
        return $this->ajuste_porcentaje > 0;
    }

    /**
     * Verifica si es un descuento
     */
    public function esDescuento(): bool
    {
        return $this->ajuste_porcentaje < 0;
    }

    /**
     * Verifica si tiene vuelto
     */
    public function tieneVuelto(): bool
    {
        return $this->vuelto && $this->vuelto > 0;
    }

    /**
     * Obtiene el nombre de la forma de pago
     */
    public function getNombreFormaPagoAttribute(): string
    {
        return $this->formaPago?->nombre ?? 'Desconocido';
    }

    /**
     * Obtiene el nombre del concepto
     */
    public function getNombreConceptoAttribute(): string
    {
        return $this->conceptoPago?->nombre ?? $this->formaPago?->conceptoPago?->nombre ?? '';
    }

    /**
     * Obtiene una descripción del ajuste
     */
    public function getDescripcionAjusteAttribute(): string
    {
        if ($this->ajuste_porcentaje == 0) {
            return 'Sin ajuste';
        }

        $tipo = $this->esRecargo() ? 'Recargo' : 'Descuento';
        return "{$tipo} " . abs($this->ajuste_porcentaje) . '%';
    }

    /**
     * Obtiene una descripción de las cuotas
     */
    public function getDescripcionCuotasAttribute(): string
    {
        if (!$this->tieneCuotas()) {
            return '1 pago';
        }

        $desc = "{$this->cuotas} cuotas de $" . number_format($this->monto_cuota, 2, ',', '.');

        if ($this->recargo_cuotas_porcentaje > 0) {
            $desc .= " (+{$this->recargo_cuotas_porcentaje}%)";
        }

        return $desc;
    }

    /**
     * Calcula el monto final con ajuste
     */
    public static function calcularMontoConAjuste(float $montoBase, float $ajustePorcentaje): array
    {
        $montoAjuste = round($montoBase * ($ajustePorcentaje / 100), 2);
        $montoFinal = round($montoBase + $montoAjuste, 2);

        return [
            'monto_base' => $montoBase,
            'ajuste_porcentaje' => $ajustePorcentaje,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
        ];
    }

    /**
     * Calcula el monto con cuotas
     */
    public static function calcularMontoConCuotas(float $montoBase, int $cuotas, float $recargoPorcentaje): array
    {
        $recargoMonto = round($montoBase * ($recargoPorcentaje / 100), 2);
        $montoTotal = $montoBase + $recargoMonto;
        $montoCuota = round($montoTotal / $cuotas, 2);

        return [
            'cuotas' => $cuotas,
            'recargo_cuotas_porcentaje' => $recargoPorcentaje,
            'recargo_cuotas_monto' => $recargoMonto,
            'monto_cuota' => $montoCuota,
            'monto_total' => $montoTotal,
        ];
    }
}
