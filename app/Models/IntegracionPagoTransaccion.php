<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Transacción de cobro vía integración de pago externa.
 *
 * Polimórfica: el campo `cobrable` apunta al origen del cobro (Venta,
 * PedidoMostrador o futuros módulos: Cobro CC, Salón, Delivery, etc.).
 * Esto permite que TODOS los módulos del sistema consuman el mismo flujo
 * de cobro integrado sin duplicar tablas ni lógica.
 *
 * Ciclo de vida:
 *   pendiente → confirmado / confirmado_manual / fallido / expirado / cancelado / sin_match
 *
 * El job `integraciones-pago:expirar-pendientes` marca como expirado las
 * transacciones pendientes cuyo `expira_en` ya pasó.
 *
 * @property int $id
 * @property int $integracion_pago_sucursal_id
 * @property int $forma_pago_id
 * @property int $sucursal_id
 * @property int|null $caja_id
 * @property int $usuario_iniciador_id
 * @property string $modo_usado
 * @property float $monto
 * @property int|null $moneda_id
 * @property string|null $external_reference
 * @property string|null $external_id
 * @property string|null $qr_data
 * @property string|null $link_pago
 * @property string $estado
 * @property \Carbon\Carbon|null $expira_en
 * @property \Carbon\Carbon|null $confirmado_en
 * @property array|null $payload_respuesta
 * @property array|null $metadata
 * @property string $cobrable_type
 * @property int $cobrable_id
 */
class IntegracionPagoTransaccion extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'integraciones_pago_transacciones';

    protected $fillable = [
        'integracion_pago_sucursal_id',
        'forma_pago_id',
        'sucursal_id',
        'caja_id',
        'usuario_iniciador_id',
        'modo_usado',
        'monto',
        'moneda_id',
        'external_reference',
        'external_id',
        'qr_data',
        'link_pago',
        'estado',
        'expira_en',
        'confirmado_en',
        'payload_respuesta',
        'metadata',
        'cobrable_type',
        'cobrable_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'expira_en' => 'datetime',
        'confirmado_en' => 'datetime',
        'payload_respuesta' => 'array',
        'metadata' => 'array',
    ];

    // Estados.
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_CONFIRMADO_MANUAL = 'confirmado_manual';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_EXPIRADO = 'expirado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADO_SIN_MATCH = 'sin_match';

    // Modos (espejo de modos_disponibles del catálogo).
    public const MODO_QR_DINAMICO = 'qr_dinamico';

    public const MODO_QR_ESTATICO = 'qr_estatico';

    public const MODO_QR_LIBRE = 'qr_libre';

    // ==================== Relaciones ====================

    public function integracionSucursal(): BelongsTo
    {
        return $this->belongsTo(IntegracionPagoSucursal::class, 'integracion_pago_sucursal_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    /**
     * Origen polimórfico del cobro: Venta, PedidoMostrador, futuros cobrables.
     */
    public function cobrable(): MorphTo
    {
        return $this->morphTo();
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(IntegracionPagoEvento::class, 'transaccion_id');
    }

    // ==================== Scopes ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeConfirmadas($query)
    {
        return $query->whereIn('estado', [self::ESTADO_CONFIRMADO, self::ESTADO_CONFIRMADO_MANUAL]);
    }

    public function scopeExpiradas($query)
    {
        return $query->where('estado', self::ESTADO_EXPIRADO);
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE)
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', now());
    }

    public function scopePorCobrable($query, string $type, int $id)
    {
        return $query->where('cobrable_type', $type)->where('cobrable_id', $id);
    }

    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorIntegracionSucursal($query, int $integracionSucursalId)
    {
        return $query->where('integracion_pago_sucursal_id', $integracionSucursalId);
    }

    // ==================== Helpers de estado ====================

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function estaConfirmada(): bool
    {
        return in_array($this->estado, [self::ESTADO_CONFIRMADO, self::ESTADO_CONFIRMADO_MANUAL], true);
    }

    public function estaEnEstadoTerminal(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_CONFIRMADO,
            self::ESTADO_CONFIRMADO_MANUAL,
            self::ESTADO_FALLIDO,
            self::ESTADO_EXPIRADO,
            self::ESTADO_CANCELADO,
            self::ESTADO_SIN_MATCH,
        ], true);
    }

    public function estaVencida(): bool
    {
        return $this->estaPendiente()
            && $this->expira_en !== null
            && $this->expira_en->isPast();
    }
}
