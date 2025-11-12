<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo TransferenciaEfectivo
 *
 * Representa una transferencia de efectivo entre cajas.
 * Puede ser entre cajas de la misma sucursal o de diferentes sucursales.
 *
 * @property int $id
 * @property int $caja_origen_id
 * @property int $caja_destino_id
 * @property float $monto
 * @property string $concepto
 * @property string $estado
 * @property int $usuario_solicita_id
 * @property int|null $usuario_autoriza_id
 * @property int|null $usuario_recibe_id
 * @property \Carbon\Carbon $fecha_solicitud
 * @property \Carbon\Carbon|null $fecha_autorizacion
 * @property \Carbon\Carbon|null $fecha_recepcion
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Caja $cajaOrigen
 * @property-read Caja $cajaDestino
 * @property-read User $usuarioSolicita
 * @property-read User|null $usuarioAutoriza
 * @property-read User|null $usuarioRecibe
 * @property-read \Illuminate\Database\Eloquent\Collection|MovimientoCaja[] $movimientos
 */
class TransferenciaEfectivo extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'transferencias_efectivo';

    protected $fillable = [
        'caja_origen_id',
        'caja_destino_id',
        'monto',
        'concepto',
        'estado',
        'usuario_solicita_id',
        'usuario_autoriza_id',
        'usuario_recibe_id',
        'fecha_solicitud',
        'fecha_autorizacion',
        'fecha_recepcion',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_autorizacion' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    // Relaciones
    public function cajaOrigen(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_origen_id');
    }

    public function cajaDestino(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_destino_id');
    }

    public function usuarioSolicita(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicita_id');
    }

    public function usuarioAutoriza(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_autoriza_id');
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'transferencia_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'cancelada');
    }

    public function scopePorCajaOrigen($query, int $cajaId)
    {
        return $query->where('caja_origen_id', $cajaId);
    }

    public function scopePorCajaDestino($query, int $cajaId)
    {
        return $query->where('caja_destino_id', $cajaId);
    }

    public function scopePorFecha($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('fecha_solicitud', '>=', $desde);
        }

        if ($hasta) {
            $query->where('fecha_solicitud', '<=', $hasta);
        }

        return $query;
    }

    // Métodos auxiliares

    /**
     * Verifica si la transferencia está pendiente
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Verifica si la transferencia está completada
     */
    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Verifica si la transferencia está cancelada
     */
    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Verifica si es una transferencia entre sucursales
     */
    public function esEntreSucursales(): bool
    {
        return $this->cajaOrigen->sucursal_id !== $this->cajaDestino->sucursal_id;
    }

    /**
     * Autoriza la transferencia
     */
    public function autorizar(int $usuarioId): bool
    {
        if (!$this->estaPendiente()) {
            return false;
        }

        $this->usuario_autoriza_id = $usuarioId;
        $this->fecha_autorizacion = now();

        return $this->save();
    }

    /**
     * Completa la transferencia
     */
    public function completar(int $usuarioId): bool
    {
        if (!$this->estaPendiente()) {
            return false;
        }

        $this->usuario_recibe_id = $usuarioId;
        $this->fecha_recepcion = now();
        $this->estado = 'completada';

        return $this->save();
    }

    /**
     * Cancela la transferencia
     */
    public function cancelar(): bool
    {
        if ($this->estaCompletada() || $this->estaCancelada()) {
            return false;
        }

        $this->estado = 'cancelada';
        return $this->save();
    }

    /**
     * Obtiene la sucursal origen
     */
    public function obtenerSucursalOrigen()
    {
        return $this->cajaOrigen->sucursal;
    }

    /**
     * Obtiene la sucursal destino
     */
    public function obtenerSucursalDestino()
    {
        return $this->cajaDestino->sucursal;
    }
}
