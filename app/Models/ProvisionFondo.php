<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ProvisionFondo
 *
 * Registra las entregas de efectivo de tesorería a cajas (apertura de caja).
 * Proporciona trazabilidad completa del movimiento de dinero.
 *
 * @property int $id
 * @property int $tesoreria_id
 * @property int $caja_id
 * @property float $monto
 * @property int $usuario_entrega_id Usuario que entrega desde tesorería
 * @property int|null $usuario_recibe_id Usuario que recibe en caja
 * @property \Carbon\Carbon $fecha
 * @property string $estado
 * @property int|null $movimiento_tesoreria_id
 * @property int|null $movimiento_caja_id
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Tesoreria $tesoreria
 * @property-read Caja $caja
 * @property-read User $usuarioEntrega
 * @property-read User|null $usuarioRecibe
 * @property-read MovimientoTesoreria|null $movimientoTesoreria
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class ProvisionFondo extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'provision_fondos';

    protected $fillable = [
        'tesoreria_id',
        'caja_id',
        'monto',
        'usuario_entrega_id',
        'usuario_recibe_id',
        'fecha',
        'estado',
        'movimiento_tesoreria_id',
        'movimiento_caja_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    // Estados
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_CONFIRMADO => 'Confirmado',
        self::ESTADO_CANCELADO => 'Cancelado',
    ];

    // ==================== RELACIONES ====================

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function usuarioEntrega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_entrega_id');
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }

    public function movimientoTesoreria(): BelongsTo
    {
        return $this->belongsTo(MovimientoTesoreria::class, 'movimiento_tesoreria_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeConfirmados($query)
    {
        return $query->where('estado', self::ESTADO_CONFIRMADO);
    }

    public function scopeDelPeriodo($query, \Carbon\Carbon $desde, \Carbon\Carbon $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    public function scopeDeHoy($query)
    {
        return $query->whereDate('fecha', today());
    }

    public function scopePorTesoreria($query, int $tesoreriaId)
    {
        return $query->where('tesoreria_id', $tesoreriaId);
    }

    public function scopePorCaja($query, int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    // ==================== ACCESSORS ====================

    public function getEstaPendienteAttribute(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function getEstaConfirmadoAttribute(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }

    public function getEstaCanceladoAttribute(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    // ==================== MÉTODOS ====================

    /**
     * Confirma la recepción del fondo en la caja
     */
    public function confirmarRecepcion(int $usuarioRecibeId): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->usuario_recibe_id = $usuarioRecibeId;
        $this->estado = self::ESTADO_CONFIRMADO;
        $this->save();

        return true;
    }

    /**
     * Cancela la provisión de fondo
     */
    public function cancelar(): bool
    {
        if ($this->esta_confirmado) {
            return false; // No se puede cancelar si ya fue confirmado
        }

        $this->estado = self::ESTADO_CANCELADO;
        $this->save();

        // Si ya se había hecho el egreso de tesorería, revertirlo
        if ($this->movimiento_tesoreria_id) {
            $this->tesoreria->saldo_actual += $this->monto;
            $this->tesoreria->save();
        }

        return true;
    }

    /**
     * Obtiene un resumen de la provisión
     */
    public function getResumenAttribute(): array
    {
        return [
            'id' => $this->id,
            'tesoreria' => $this->tesoreria->nombre,
            'caja' => $this->caja->nombre,
            'monto' => $this->monto,
            'usuario_entrega' => $this->usuarioEntrega->name ?? 'N/A',
            'usuario_recibe' => $this->usuarioRecibe->name ?? 'Pendiente',
            'fecha' => $this->fecha->format('d/m/Y H:i'),
            'estado' => $this->estado_label,
        ];
    }
}
