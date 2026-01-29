<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo TransferenciaStock
 *
 * Representa una transferencia de stock entre sucursales.
 * Soporta transferencias simples o fiscales (con facturación).
 *
 * @property int $id
 * @property int $sucursal_origen_id
 * @property int $sucursal_destino_id
 * @property int $articulo_id
 * @property float $cantidad
 * @property string $tipo_transferencia
 * @property int|null $venta_id
 * @property int|null $compra_id
 * @property string $estado
 * @property int $usuario_solicita_id
 * @property int|null $usuario_aprueba_id
 * @property int|null $usuario_recibe_id
 * @property \Carbon\Carbon $fecha_solicitud
 * @property \Carbon\Carbon|null $fecha_aprobacion
 * @property \Carbon\Carbon|null $fecha_recepcion
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursalOrigen
 * @property-read Sucursal $sucursalDestino
 * @property-read Articulo $articulo
 * @property-read Venta|null $venta
 * @property-read Compra|null $compra
 * @property-read User $usuarioSolicita
 * @property-read User|null $usuarioAprueba
 * @property-read User|null $usuarioRecibe
 */
class TransferenciaStock extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'transferencias_stock';

    protected $fillable = [
        'sucursal_origen_id',
        'sucursal_destino_id',
        'articulo_id',
        'cantidad',
        'tipo_transferencia',
        'venta_id',
        'compra_id',
        'estado',
        'usuario_solicita_id',
        'usuario_aprueba_id',
        'usuario_recibe_id',
        'fecha_solicitud',
        'fecha_aprobacion',
        'fecha_recepcion',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    // Relaciones
    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function usuarioSolicita(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicita_id');
    }

    public function usuarioAprueba(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_aprueba_id');
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnTransito($query)
    {
        return $query->where('estado', 'en_transito');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'cancelada');
    }

    public function scopePorSucursalOrigen($query, int $sucursalId)
    {
        return $query->where('sucursal_origen_id', $sucursalId);
    }

    public function scopePorSucursalDestino($query, int $sucursalId)
    {
        return $query->where('sucursal_destino_id', $sucursalId);
    }

    public function scopeSimples($query)
    {
        return $query->where('tipo_transferencia', 'simple');
    }

    public function scopeFiscales($query)
    {
        return $query->where('tipo_transferencia', 'fiscal');
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
     * Verifica si la transferencia está en tránsito
     */
    public function estaEnTransito(): bool
    {
        return $this->estado === 'en_transito';
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
     * Verifica si es una transferencia fiscal
     */
    public function esFiscal(): bool
    {
        return $this->tipo_transferencia === 'fiscal';
    }

    /**
     * Verifica si es una transferencia simple
     */
    public function esSimple(): bool
    {
        return $this->tipo_transferencia === 'simple';
    }

    /**
     * Aprueba la transferencia y envía el stock
     */
    public function aprobar(int $usuarioId): bool
    {
        if (!$this->estaPendiente()) {
            return false;
        }

        $this->usuario_aprueba_id = $usuarioId;
        $this->fecha_aprobacion = now();
        $this->estado = 'en_transito';

        return $this->save();
    }

    /**
     * Recibe la transferencia y completa el proceso
     */
    public function recibir(int $usuarioId): bool
    {
        if (!$this->estaEnTransito()) {
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
}
