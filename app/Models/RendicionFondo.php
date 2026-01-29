<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo RendicionFondo
 *
 * Registra las entregas de efectivo de cajas a tesorería (cierre de caja).
 * Incluye diferencias entre monto declarado, sistema y entregado.
 *
 * @property int $id
 * @property int $caja_id
 * @property int $tesoreria_id
 * @property float $monto_declarado Monto declarado por el cajero
 * @property float $monto_sistema Monto calculado por el sistema
 * @property float $monto_entregado Monto efectivamente entregado
 * @property float $diferencia Sobrante/faltante
 * @property int $usuario_entrega_id Cajero que entrega
 * @property int|null $usuario_recibe_id Usuario que recibe en tesorería
 * @property int|null $cierre_turno_id Cierre de turno asociado
 * @property \Carbon\Carbon $fecha
 * @property string $estado
 * @property \Carbon\Carbon|null $fecha_confirmacion
 * @property int|null $movimiento_tesoreria_id
 * @property int|null $movimiento_caja_id
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Caja $caja
 * @property-read Tesoreria $tesoreria
 * @property-read User $usuarioEntrega
 * @property-read User|null $usuarioRecibe
 * @property-read CierreTurno|null $cierreTurno
 * @property-read MovimientoTesoreria|null $movimientoTesoreria
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class RendicionFondo extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'rendicion_fondos';

    protected $fillable = [
        'caja_id',
        'tesoreria_id',
        'monto_declarado',
        'monto_sistema',
        'monto_entregado',
        'diferencia',
        'usuario_entrega_id',
        'usuario_recibe_id',
        'cierre_turno_id',
        'fecha',
        'estado',
        'fecha_confirmacion',
        'movimiento_tesoreria_id',
        'movimiento_caja_id',
        'observaciones',
    ];

    protected $casts = [
        'monto_declarado' => 'decimal:2',
        'monto_sistema' => 'decimal:2',
        'monto_entregado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'fecha' => 'datetime',
        'fecha_confirmacion' => 'datetime',
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

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function usuarioEntrega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_entrega_id');
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
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

    public function scopeConDiferencia($query)
    {
        return $query->where('diferencia', '!=', 0);
    }

    public function scopeConSobrante($query)
    {
        return $query->where('diferencia', '>', 0);
    }

    public function scopeConFaltante($query)
    {
        return $query->where('diferencia', '<', 0);
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

    public function getTieneSobranteAttribute(): bool
    {
        return $this->diferencia > 0;
    }

    public function getTieneFaltanteAttribute(): bool
    {
        return $this->diferencia < 0;
    }

    public function getEstaCuadradaAttribute(): bool
    {
        return $this->diferencia == 0;
    }

    public function getDiferenciaAbsolutaAttribute(): float
    {
        return abs($this->diferencia);
    }

    public function getTipoDiferenciaAttribute(): string
    {
        if ($this->diferencia > 0) return 'sobrante';
        if ($this->diferencia < 0) return 'faltante';
        return 'cuadrada';
    }

    // ==================== MÉTODOS ====================

    /**
     * Confirma la recepción del fondo en tesorería
     */
    public function confirmarRecepcion(int $usuarioRecibeId): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->usuario_recibe_id = $usuarioRecibeId;
        $this->estado = self::ESTADO_CONFIRMADO;
        $this->fecha_confirmacion = now();
        $this->save();

        return true;
    }

    /**
     * Cancela la rendición
     */
    public function cancelar(): bool
    {
        if ($this->esta_confirmado) {
            return false;
        }

        $this->estado = self::ESTADO_CANCELADO;
        $this->save();

        // Si ya se había hecho el ingreso a tesorería, revertirlo
        if ($this->movimiento_tesoreria_id) {
            $this->tesoreria->saldo_actual -= $this->monto_entregado;
            $this->tesoreria->save();
        }

        return true;
    }

    /**
     * Calcula la diferencia automáticamente
     */
    public function calcularDiferencia(): void
    {
        $this->diferencia = $this->monto_declarado - $this->monto_sistema;
    }

    /**
     * Obtiene un resumen de la rendición
     */
    public function getResumenAttribute(): array
    {
        return [
            'id' => $this->id,
            'caja' => $this->caja->nombre,
            'tesoreria' => $this->tesoreria->nombre,
            'monto_declarado' => $this->monto_declarado,
            'monto_sistema' => $this->monto_sistema,
            'monto_entregado' => $this->monto_entregado,
            'diferencia' => $this->diferencia,
            'tipo_diferencia' => $this->tipo_diferencia,
            'usuario_entrega' => $this->usuarioEntrega->name ?? 'N/A',
            'usuario_recibe' => $this->usuarioRecibe->name ?? 'Pendiente',
            'fecha' => $this->fecha->format('d/m/Y H:i'),
            'estado' => $this->estado_label,
        ];
    }
}
