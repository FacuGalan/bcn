<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo PedidoMostrador
 *
 * Representa un pedido por mostrador: documento operativo con su propio ciclo
 * de vida (estado_pedido + estado_pago) que eventualmente se convierte en Venta
 * via PedidoMostradorService::convertirEnVenta(). Hasta entonces no emite
 * comprobante fiscal y los pagos no van a venta_pagos.
 *
 * Ver spec completo en .claude/specs/pedidos-mostrador.md.
 *
 * @property int $id
 * @property int|null $numero
 * @property string|null $identificador
 * @property string|null $numero_beeper
 * @property int $sucursal_id
 * @property int|null $cliente_id
 * @property string|null $nombre_cliente_temporal
 * @property string|null $telefono_cliente_temporal
 * @property int|null $caja_id
 * @property int|null $canal_venta_id
 * @property int|null $forma_venta_id
 * @property int|null $lista_precio_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $fecha
 * @property string $estado_pedido
 * @property string $estado_pago
 * @property float $subtotal
 * @property float $iva
 * @property float $descuento
 * @property float $total
 * @property float $ajuste_forma_pago
 * @property float $total_final
 * @property int|null $venta_id
 * @property \Carbon\Carbon|null $convertido_at
 */
class PedidoMostrador extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';

    protected $table = 'pedidos_mostrador';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_EN_PREPARACION = 'en_preparacion';

    public const ESTADO_LISTO = 'listo';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_FACTURADO = 'facturado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADOS = [
        self::ESTADO_BORRADOR => 'Borrador',
        self::ESTADO_CONFIRMADO => 'Confirmado',
        self::ESTADO_EN_PREPARACION => 'En preparación',
        self::ESTADO_LISTO => 'Listo',
        self::ESTADO_ENTREGADO => 'Entregado',
        self::ESTADO_FACTURADO => 'Facturado',
        self::ESTADO_CANCELADO => 'Cancelado',
    ];

    public const ESTADO_PAGO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGO_PARCIAL = 'parcial';

    public const ESTADO_PAGO_PAGADO = 'pagado';

    public const ESTADOS_PAGO = [
        self::ESTADO_PAGO_PENDIENTE => 'Pendiente',
        self::ESTADO_PAGO_PARCIAL => 'Parcial',
        self::ESTADO_PAGO_PAGADO => 'Pagado',
    ];

    /**
     * Estado de comanda derivado de los detalles. No se persiste en BD: se
     * calcula desde `detalles.comandado_at`. Ver accessor `estado_comanda`.
     */
    public const ESTADO_COMANDA_NO = 'no_comandado';

    public const ESTADO_COMANDA_PARCIAL = 'parcial';

    public const ESTADO_COMANDA_TOTAL = 'comandado';

    /**
     * Transiciones de estado_pedido permitidas. Servicio valida contra este mapa.
     */
    public const TRANSICIONES_PERMITIDAS = [
        self::ESTADO_BORRADOR => [self::ESTADO_CONFIRMADO, self::ESTADO_CANCELADO],
        self::ESTADO_CONFIRMADO => [self::ESTADO_EN_PREPARACION, self::ESTADO_LISTO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_EN_PREPARACION => [self::ESTADO_LISTO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_LISTO => [self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_ENTREGADO => [self::ESTADO_FACTURADO, self::ESTADO_CANCELADO],
        self::ESTADO_FACTURADO => [],
        self::ESTADO_CANCELADO => [],
    ];

    protected $fillable = [
        'numero',
        'identificador',
        'numero_beeper',
        'sucursal_id',
        'cliente_id',
        'nombre_cliente_temporal',
        'telefono_cliente_temporal',
        'caja_id',
        'canal_venta_id',
        'forma_venta_id',
        'lista_precio_id',
        'usuario_id',
        'fecha',
        'estado_pedido',
        'estado_pago',
        'subtotal',
        'iva',
        'descuento',
        'total',
        'ajuste_forma_pago',
        'total_final',
        'es_invitacion_total',
        'invitacion_motivo',
        'invitado_por_usuario_id',
        'invitado_at',
        'total_invitado',
        'descuento_general_tipo',
        'descuento_general_valor',
        'descuento_general_monto',
        'descuento_general_aplicado_por',
        'cupon_id',
        'cupon_codigo_snapshot',
        'cupon_descripcion_snapshot',
        'monto_cupon',
        'puntos_ganados',
        'puntos_usados',
        'puntos_canjeados_pago',
        'puntos_canjeados_articulos',
        'puntos_usados_monto',
        'articulos_canjeados_monto',
        'observaciones',
        'orden_kanban',
        'motivo_cancelacion',
        'confirmado_at',
        'en_preparacion_at',
        'listo_at',
        'entregado_at',
        'cancelado_at',
        'cancelado_por_usuario_id',
        'venta_id',
        'convertido_at',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'ajuste_forma_pago' => 'decimal:2',
        'total_final' => 'decimal:2',
        'es_invitacion_total' => 'boolean',
        'invitado_at' => 'datetime',
        'total_invitado' => 'decimal:2',
        'descuento_general_valor' => 'decimal:2',
        'descuento_general_monto' => 'decimal:2',
        'monto_cupon' => 'decimal:2',
        'puntos_ganados' => 'integer',
        'puntos_usados' => 'integer',
        'puntos_canjeados_pago' => 'integer',
        'puntos_canjeados_articulos' => 'integer',
        'orden_kanban' => 'integer',
        'puntos_usados_monto' => 'decimal:2',
        'articulos_canjeados_monto' => 'decimal:2',
        'confirmado_at' => 'datetime',
        'en_preparacion_at' => 'datetime',
        'listo_at' => 'datetime',
        'entregado_at' => 'datetime',
        'cancelado_at' => 'datetime',
        'convertido_at' => 'datetime',
    ];

    /**
     * Hook que inicializa `orden_kanban = id` para pedidos nuevos cuyo orden
     * aun no fue seteado explicitamente. Asi cada pedido nace en su posicion
     * "natural" del Kanban (id DESC). Si el usuario despues reordena dentro
     * de una columna, el service::reordenarColumna pisa este valor.
     */
    protected static function booted(): void
    {
        static::created(function (self $pedido) {
            if ((int) $pedido->orden_kanban === 0) {
                $pedido->orden_kanban = $pedido->id;
                $pedido->saveQuietly();
            }
        });
    }

    // ==================== RELACIONES ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class, 'cupon_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function canceladoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_usuario_id');
    }

    /**
     * Usuario que autorizó la cortesía total del pedido (es_invitacion_total=true).
     * Para invitaciones parciales, ver la relación equivalente en PedidoMostradorDetalle.
     */
    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por_usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoMostradorDetalle::class, 'pedido_mostrador_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoMostradorPago::class, 'pedido_mostrador_id');
    }

    public function promociones(): HasMany
    {
        return $this->hasMany(PedidoMostradorPromocion::class, 'pedido_mostrador_id');
    }

    // ==================== SCOPES ====================

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado_pedido', $estado);
    }

    public function scopePorEstadoPago(Builder $query, string $estadoPago): Builder
    {
        return $query->where('estado_pago', $estadoPago);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNotIn('estado_pedido', [self::ESTADO_FACTURADO, self::ESTADO_CANCELADO]);
    }

    public function scopeHoy(Builder $query): Builder
    {
        return $query->whereDate('fecha', today());
    }

    // ==================== ACCESSORS ====================

    public function getEsBorradorAttribute(): bool
    {
        return $this->estado_pedido === self::ESTADO_BORRADOR;
    }

    public function getEstaFacturadoAttribute(): bool
    {
        return $this->estado_pedido === self::ESTADO_FACTURADO;
    }

    public function getEstaCanceladoAttribute(): bool
    {
        return $this->estado_pedido === self::ESTADO_CANCELADO;
    }

    /**
     * Estado de comanda derivado de `detalles.comandado_at`. Retorna:
     * - `no_comandado` si todos los detalles están sin comandar.
     * - `parcial` si hay mezcla (algunos comandados, otros no).
     * - `comandado` si todos los detalles están comandados.
     * - `no_comandado` si el pedido no tiene detalles (fallback seguro).
     */
    public function getEstadoComandaAttribute(): string
    {
        $detalles = $this->relationLoaded('detalles') ? $this->detalles : $this->detalles()->get();

        if ($detalles->isEmpty()) {
            return self::ESTADO_COMANDA_NO;
        }

        $comandados = $detalles->whereNotNull('comandado_at')->count();

        if ($comandados === 0) {
            return self::ESTADO_COMANDA_NO;
        }

        if ($comandados === $detalles->count()) {
            return self::ESTADO_COMANDA_TOTAL;
        }

        return self::ESTADO_COMANDA_PARCIAL;
    }

    public function getEsClienteTemporalAttribute(): bool
    {
        return $this->cliente_id === null && ! empty($this->nombre_cliente_temporal);
    }

    public function getNombreClienteFinalAttribute(): ?string
    {
        return $this->cliente?->nombre ?? $this->nombre_cliente_temporal;
    }

    public function getTelefonoClienteFinalAttribute(): ?string
    {
        return $this->cliente?->telefono ?? $this->telefono_cliente_temporal;
    }

    /**
     * Suma de monto_final de pagos en estado planificado (configurados pero
     * todavía no cobrados). Útil para la UI: "Tiene $1500 planificado en
     * efectivo + tarjeta". No cuenta para estado_pago — eso se calcula sólo
     * sobre activos.
     */
    public function getTotalPlanificadoAttribute(): float
    {
        return (float) $this->pagos()
            ->where('estado', PedidoMostradorPago::ESTADO_PLANIFICADO)
            ->sum('monto_final');
    }

    /**
     * Suma de monto_final de pagos en estado activo (cobrados realmente).
     * Espeja el cálculo interno de recalcularEstadoPago para que la UI tenga
     * un único punto de verdad.
     */
    public function getTotalCobradoAttribute(): float
    {
        return (float) $this->pagos()
            ->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)
            ->sum('monto_final');
    }
}
