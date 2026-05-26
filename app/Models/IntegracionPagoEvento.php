<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de auditoría de una transacción de integración de pago.
 *
 * Append-only: cada cambio de estado, webhook recibido, error o acción
 * manual queda registrado para soporte y conciliación.
 *
 * Solo timestamp de created_at (no updated_at: los eventos no se editan).
 *
 * @property int $id
 * @property int|null $transaccion_id NULL si webhook sin match
 * @property int|null $integracion_pago_sucursal_id
 * @property string $evento
 * @property array|null $payload_externo
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 */
class IntegracionPagoEvento extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'integraciones_pago_eventos';

    public $timestamps = false;

    protected $fillable = [
        'transaccion_id',
        'integracion_pago_sucursal_id',
        'evento',
        'payload_externo',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'payload_externo' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Eventos posibles (no exhaustivo; documentado en spec RF-15).
    public const EVENTO_CREADO = 'creado';

    public const EVENTO_INICIADO_EN_GATEWAY = 'iniciado_en_gateway';

    public const EVENTO_WEBHOOK_RECIBIDO = 'webhook_recibido';

    public const EVENTO_CONFIRMADO = 'confirmado';

    public const EVENTO_CONFIRMADO_MANUAL = 'confirmado_manual';

    public const EVENTO_FALLIDO = 'fallido';

    public const EVENTO_EXPIRADO = 'expirado';

    public const EVENTO_CANCELADO = 'cancelado';

    public const EVENTO_SIN_MATCH = 'sin_match';

    public const EVENTO_ERROR = 'error';

    protected static function booted(): void
    {
        static::creating(function (self $evento): void {
            if (! $evento->created_at) {
                $evento->created_at = now();
            }
        });
    }

    // ==================== Relaciones ====================

    public function transaccion(): BelongsTo
    {
        return $this->belongsTo(IntegracionPagoTransaccion::class, 'transaccion_id');
    }

    public function integracionSucursal(): BelongsTo
    {
        return $this->belongsTo(IntegracionPagoSucursal::class, 'integracion_pago_sucursal_id');
    }

    // ==================== Scopes ====================

    public function scopePorEvento($query, string $evento)
    {
        return $query->where('evento', $evento);
    }

    public function scopePorTransaccion($query, int $transaccionId)
    {
        return $query->where('transaccion_id', $transaccionId);
    }
}
