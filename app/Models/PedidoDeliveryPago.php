<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PedidoDeliveryPago
 *
 * Pago aplicado a un pedido delivery. Espejo de PedidoMostradorPago (mismos
 * estados planificado/activo/anulado y transiciones) MAS el circuito del
 * fondo del repartidor (D13):
 *
 * - `destino_fondo`: cobro contra entrega en EFECTIVO — al confirmarse el
 *   pago planificado NO se crea MovimientoCaja; el dinero vive en el fondo
 *   del repartidor (`repartidor_fondo_id`) hasta la rendicion, que ingresa
 *   UN neto a la caja receptora.
 * - Pagos no-efectivo en la puerta (QR/Point/transferencia) van por el
 *   circuito normal de caja y nunca tocan el fondo.
 * - `creado_por_usuario_id` es NULL en pagos online acreditados sin operador
 *   (tienda: webhook de MP acredita, `afecta_caja=0`).
 */
class PedidoDeliveryPago extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedidos_delivery_pagos';

    public const ESTADO_PLANIFICADO = 'planificado';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_ANULADO = 'anulado';

    public const OPERACION_VENTA_ORIGINAL = 'venta_original';

    public const OPERACION_CAMBIO_PAGO = 'cambio_pago';

    public const OPERACION_PAGO_AGREGADO = 'pago_agregado';

    public const OPERACION_ANULACION_SIN_REEMPLAZO = 'anulacion_sin_reemplazo';

    protected $fillable = [
        'pedido_delivery_id',
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
        'destino_fondo',
        'repartidor_fondo_id',
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
        'destino_fondo' => 'boolean',
        'anulado_at' => 'datetime',
        'monto_moneda_original' => 'decimal:2',
        'tipo_cambio_tasa' => 'decimal:6',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoDelivery::class, 'pedido_delivery_id');
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

    public function repartidorFondo(): BelongsTo
    {
        return $this->belongsTo(RepartidorFondo::class, 'repartidor_fondo_id');
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
        return $query->where('pedido_delivery_id', $pedidoId);
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
