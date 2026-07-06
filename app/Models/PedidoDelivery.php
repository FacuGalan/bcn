<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Modelo PedidoDelivery
 *
 * Pedido delivery / take-away: espejo de PedidoMostrador (mismo ciclo de vida
 * y carrito) más la dimensión logística — dirección de entrega georreferenciada,
 * costo de envío, repartidor/salida, estado `en_camino`, promesa de entrega —
 * y la de origen externo (tienda online / API: origen, consumidor global,
 * token de seguimiento público).
 *
 * Se convierte en Venta vía PedidoDeliveryService::convertirEnVenta(); la
 * venta resultante guarda el morph inverso en ventas.origen_type/origen_id
 * (morphMap 'PedidoDelivery', D20).
 *
 * Ver spec completo en .claude/specs/pedidos-delivery.md.
 *
 * @property int $id
 * @property string $tipo
 * @property string $estado_pedido
 * @property string $origen
 * @property int|null $repartidor_id
 * @property int|null $salida_id
 * @property float $costo_envio
 * @property string|null $token_seguimiento
 * @property int|null $venta_id
 */
class PedidoDelivery extends Model
{
    use \App\Models\Concerns\CalculaAlertaDemora;
    use SoftDeletes;

    protected $connection = 'pymes_tenant';

    protected $table = 'pedidos_delivery';

    public const TIPO_DELIVERY = 'delivery';

    public const TIPO_TAKE_AWAY = 'take_away';

    public const TIPOS = [
        self::TIPO_DELIVERY => 'Delivery',
        self::TIPO_TAKE_AWAY => 'Para llevar',
    ];

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_EN_PREPARACION = 'en_preparacion';

    public const ESTADO_LISTO = 'listo';

    public const ESTADO_EN_CAMINO = 'en_camino';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_FACTURADO = 'facturado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADOS = [
        self::ESTADO_BORRADOR => 'Borrador',
        self::ESTADO_CONFIRMADO => 'Confirmado',
        self::ESTADO_EN_PREPARACION => 'En preparación',
        self::ESTADO_LISTO => 'Listo',
        self::ESTADO_EN_CAMINO => 'En camino',
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

    public const ESTADO_COMANDA_NO = 'no_comandado';

    public const ESTADO_COMANDA_PARCIAL = 'parcial';

    public const ESTADO_COMANDA_TOTAL = 'comandado';

    public const ORIGEN_PANEL = 'panel';

    public const ORIGEN_TIENDA = 'tienda';

    public const ORIGEN_API = 'api';

    public const ORIGENES = [
        self::ORIGEN_PANEL => 'Panel',
        self::ORIGEN_TIENDA => 'Tienda',
        self::ORIGEN_API => 'API',
    ];

    /**
     * Transiciones de estado_pedido permitidas. El service valida contra este
     * mapa Y contra el tipo: `en_camino` es SOLO para delivery (take-away
     * salta listo → entregado); `en_camino → listo` es la vuelta fallida
     * (re-despacho, RF-08).
     */
    public const TRANSICIONES_PERMITIDAS = [
        self::ESTADO_BORRADOR => [self::ESTADO_CONFIRMADO, self::ESTADO_CANCELADO],
        self::ESTADO_CONFIRMADO => [self::ESTADO_EN_PREPARACION, self::ESTADO_LISTO, self::ESTADO_EN_CAMINO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_EN_PREPARACION => [self::ESTADO_LISTO, self::ESTADO_EN_CAMINO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_LISTO => [self::ESTADO_EN_CAMINO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO],
        self::ESTADO_EN_CAMINO => [self::ESTADO_ENTREGADO, self::ESTADO_LISTO, self::ESTADO_CANCELADO],
        self::ESTADO_ENTREGADO => [self::ESTADO_FACTURADO, self::ESTADO_CANCELADO],
        self::ESTADO_FACTURADO => [],
        self::ESTADO_CANCELADO => [],
    ];

    /**
     * Estados desde los que un pedido puede despacharse/armar salida (RF-08):
     * "listo" NO es paso obligado — el operador puede despachar directo desde
     * confirmado/en_preparacion (y con `usa_estado_listo` OFF la columna ni
     * aparece en el kanban). Al saltar, cambiarEstado backfillea listo_at.
     */
    public const ESTADOS_DESPACHABLES = [
        self::ESTADO_CONFIRMADO,
        self::ESTADO_EN_PREPARACION,
        self::ESTADO_LISTO,
    ];

    protected $fillable = [
        'numero',
        'numero_display',
        'identificador',
        'numero_beeper',
        'tipo',
        'sucursal_id',
        'cliente_id',
        'nombre_cliente_temporal',
        'telefono_cliente_temporal',
        'email_cliente_temporal',
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
        'direccion_entrega',
        'direccion_referencia',
        'localidad_entrega_id',
        'latitud',
        'longitud',
        'zona_id',
        'costo_envio',
        'costo_envio_manual',
        'costo_envio_usuario_id',
        'distancia_km',
        'fuera_de_alcance',
        'repartidor_id',
        'salida_id',
        'en_camino_at',
        'no_entregado_motivo',
        'hora_pactada_at',
        'lo_antes_posible',
        'programado_para',
        'datos_fiscales_snapshot',
        'origen',
        'origen_referencia',
        'consumidor_id',
        'token_seguimiento',
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
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'costo_envio' => 'decimal:2',
        'costo_envio_manual' => 'boolean',
        'distancia_km' => 'decimal:2',
        'fuera_de_alcance' => 'boolean',
        'en_camino_at' => 'datetime',
        'hora_pactada_at' => 'datetime',
        'lo_antes_posible' => 'boolean',
        'programado_para' => 'datetime',
        'datos_fiscales_snapshot' => 'array',
        'confirmado_at' => 'datetime',
        'en_preparacion_at' => 'datetime',
        'listo_at' => 'datetime',
        'entregado_at' => 'datetime',
        'cancelado_at' => 'datetime',
        'convertido_at' => 'datetime',
    ];

    /**
     * Hooks de creación:
     * - `orden_kanban = id` para pedidos nuevos (posición natural del Kanban,
     *   mismo patrón que mostrador).
     * - `token_seguimiento` ULID si no vino seteado: TODO pedido es trackeable
     *   públicamente por token (RF-11), no solo los de tienda.
     */
    protected static function booted(): void
    {
        static::creating(function (self $pedido) {
            if (empty($pedido->token_seguimiento)) {
                $pedido->token_seguimiento = (string) Str::ulid();
            }
        });

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

    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por_usuario_id');
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'repartidor_id');
    }

    /**
     * Salida ACTUAL del pedido. El historial completo de intentos (incluidos
     * re-despachos tras vueltas fallidas) vive en delivery_salida_pedidos.
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(DeliverySalida::class, 'salida_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(DeliveryZona::class, 'zona_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDeliveryDetalle::class, 'pedido_delivery_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoDeliveryPago::class, 'pedido_delivery_id');
    }

    public function promociones(): HasMany
    {
        return $this->hasMany(PedidoDeliveryPromocion::class, 'pedido_delivery_id');
    }

    public function salidasHistorial(): HasMany
    {
        return $this->hasMany(DeliverySalidaPedido::class, 'pedido_id');
    }

    /**
     * Ver PedidoMostrador::tieneIntegracionPagoConfirmada — mismo guard: un
     * pedido con pago de integración (QR MP) confirmado no puede cancelarse
     * mientras no exista refund real.
     */
    public function tieneIntegracionPagoConfirmada(): bool
    {
        return IntegracionPagoTransaccion::porCobrable($this->getMorphClass(), $this->id)
            ->confirmadas()
            ->exists();
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

    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorOrigen(Builder $query, string $origen): Builder
    {
        return $query->where('origen', $origen);
    }

    public function scopePorRepartidor(Builder $query, int $repartidorId): Builder
    {
        return $query->where('repartidor_id', $repartidorId);
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

    public function getEsDeliveryAttribute(): bool
    {
        return $this->tipo === self::TIPO_DELIVERY;
    }

    public function getEsTakeAwayAttribute(): bool
    {
        return $this->tipo === self::TIPO_TAKE_AWAY;
    }

    /**
     * Si el pedido nació en la tienda online (D20): cubre delivery Y take-away
     * originados ahí. Los integradores externos usan origen='api'.
     */
    public function getEsDeTiendaAttribute(): bool
    {
        return $this->origen === self::ORIGEN_TIENDA;
    }

    public function getEsExternoAttribute(): bool
    {
        return $this->origen !== self::ORIGEN_PANEL;
    }

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
     * Label dinámico del estado `listo` según tipo (RF-03): "Listo para
     * enviar" (delivery) / "Listo para retirar" (take-away). El resto de los
     * estados usa el label estándar.
     */
    public function getEstadoLabelAttribute(): string
    {
        if ($this->estado_pedido === self::ESTADO_LISTO) {
            return $this->es_delivery ? __('Listo para enviar') : __('Listo para retirar');
        }

        return __(self::ESTADOS[$this->estado_pedido] ?? $this->estado_pedido);
    }

    /**
     * Estado de comanda derivado de `detalles.comandado_at` (mismo patrón que
     * mostrador: accessor, NO cache).
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

    public function getEmailClienteFinalAttribute(): ?string
    {
        return $this->cliente?->email ?? $this->email_cliente_temporal;
    }

    /**
     * Número a mostrar cara-al-público (llamador, comanda, kanban): el de
     * display (turno, contador COMPARTIDO con mostrador) si existe, si no el
     * correlativo permanente propio de delivery.
     */
    public function getNumeroVisibleAttribute(): ?int
    {
        return $this->numero_display ?? $this->numero;
    }

    /**
     * Nombre para el monitor llamador público: SOLO el primer nombre (paridad
     * con PedidoMostrador::nombreLlamador).
     */
    public function nombreLlamador(): ?string
    {
        $nombre = trim((string) $this->nombre_cliente_final);

        if ($nombre === '') {
            return null;
        }

        return explode(' ', $nombre)[0];
    }

    public function getTotalPlanificadoAttribute(): float
    {
        return (float) $this->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)
            ->sum('monto_final');
    }

    public function getTotalCobradoAttribute(): float
    {
        return (float) $this->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)
            ->sum('monto_final');
    }
}
