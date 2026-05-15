<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PedidoMostradorPago
 *
 * Pago aplicado a un pedido por mostrador. Espejo de VentaPago SIN campos
 * fiscales (esos viven en venta_pagos despues de la conversion).
 *
 * Estados:
 * - planificado: pago configurado sin cobrar. NO afecta caja, NO cuenta para
 *   estado_pago del pedido. Pensado para flujos "configuro ahora, cobro
 *   después" (totem, mesero arma desglose y cliente paga al irse).
 * - activo: pago efectivamente cobrado, con MovimientoCaja asociado.
 * - anulado: contraasiento aplicado.
 *
 * Transiciones:
 * - planificado -> activo (PedidoMostradorService::confirmarPagoPlanificado)
 * - planificado -> DELETE directo (eliminarPagoPlanificado)
 * - activo -> anulado (anularPago, genera contraasiento en MovimientoCaja)
 *
 * @property int $id
 * @property int $pedido_mostrador_id
 * @property int $forma_pago_id
 * @property float $monto_base
 * @property float $monto_final
 * @property string $estado
 * @property int|null $venta_pago_id
 */
class PedidoMostradorPago extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedidos_mostrador_pagos';

    public const ESTADO_PLANIFICADO = 'planificado';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_ANULADO = 'anulado';

    /**
     * Valores del ENUM operacion_origen (paridad con venta_pagos).
     * Aunque "venta_original" suene raro en pedido, mantenemos los mismos
     * literales para que el mapeo a VentaPago al convertir sea trivial.
     */
    public const OPERACION_VENTA_ORIGINAL = 'venta_original';

    public const OPERACION_CAMBIO_PAGO = 'cambio_pago';

    public const OPERACION_PAGO_AGREGADO = 'pago_agregado';

    public const OPERACION_ANULACION_SIN_REEMPLAZO = 'anulacion_sin_reemplazo';

    protected $fillable = [
        'pedido_mostrador_id',
        'forma_pago_id',
        'concepto_pago_id',
        'monto_base',
        'ajuste_porcentaje',
        'monto_ajuste',
        'monto_final',
        'saldo_pendiente',
        'operacion_origen',
        'monto_recibido',
        'vuelto',
        'cuotas',
        'recargo_cuotas_porcentaje',
        'recargo_cuotas_monto',
        'monto_cuota',
        'referencia',
        'observaciones',
        'es_cuenta_corriente',
        'es_pago_puntos',
        'puntos_usados',
        'afecta_caja',
        'estado',
        'movimiento_caja_id',
        'anulado_por_usuario_id',
        'anulado_at',
        'motivo_anulacion',
        'creado_por_usuario_id',
        'cierre_turno_id',
        'moneda_id',
        'monto_moneda_original',
        'tipo_cambio_tasa',
        'tipo_cambio_id',
        'venta_pago_id',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'ajuste_porcentaje' => 'decimal:2',
        'monto_ajuste' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'cuotas' => 'integer',
        'recargo_cuotas_porcentaje' => 'decimal:2',
        'recargo_cuotas_monto' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'es_cuenta_corriente' => 'boolean',
        'es_pago_puntos' => 'boolean',
        'puntos_usados' => 'integer',
        'afecta_caja' => 'boolean',
        'anulado_at' => 'datetime',
        'monto_moneda_original' => 'decimal:2',
        'tipo_cambio_tasa' => 'decimal:6',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoMostrador::class, 'pedido_mostrador_id');
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

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function ventaPago(): BelongsTo
    {
        return $this->belongsTo(VentaPago::class, 'venta_pago_id');
    }

    // ==================== SCOPES ====================

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopePlanificados(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_PLANIFICADO);
    }

    public function scopePorPedido(Builder $query, int $pedidoId): Builder
    {
        return $query->where('pedido_mostrador_id', $pedidoId);
    }

    public function esPlanificado(): bool
    {
        return $this->estado === self::ESTADO_PLANIFICADO;
    }

    public function esActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }
}
