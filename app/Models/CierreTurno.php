<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo CierreTurno
 *
 * Representa un cierre de turno, ya sea individual (una caja) o grupal (varias cajas).
 * Contiene los totales consolidados y la referencia al grupo si aplica.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int|null $grupo_cierre_id
 * @property int $usuario_id
 * @property string $tipo - 'individual' o 'grupo'
 * @property \Carbon\Carbon|null $fecha_apertura
 * @property \Carbon\Carbon $fecha_cierre
 * @property float $total_saldo_inicial
 * @property float $total_saldo_final
 * @property float $total_ingresos
 * @property float $total_egresos
 * @property float $total_diferencia
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read GrupoCierre|null $grupoCierre
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|CierreTurnoCaja[] $detalleCajas
 * @property-read \Illuminate\Database\Eloquent\Collection|MovimientoCaja[] $movimientos
 * @property-read \Illuminate\Database\Eloquent\Collection|Venta[] $ventas
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaPago[] $ventaPagos
 * @property-read \Illuminate\Database\Eloquent\Collection|Cobro[] $cobros
 * @property-read \Illuminate\Database\Eloquent\Collection|CobroPago[] $cobroPagos
 */
class CierreTurno extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cierres_turno';

    protected $fillable = [
        'sucursal_id',
        'grupo_cierre_id',
        'usuario_id',
        'tipo',
        'fecha_apertura',
        'fecha_cierre',
        'total_saldo_inicial',
        'total_saldo_final',
        'total_ingresos',
        'total_egresos',
        'total_diferencia',
        'observaciones',
        'revertido',
        'fecha_reversion',
        'usuario_reversion_id',
        'motivo_reversion',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'revertido' => 'boolean',
        'fecha_reversion' => 'datetime',
        'total_saldo_inicial' => 'decimal:2',
        'total_saldo_final' => 'decimal:2',
        'total_ingresos' => 'decimal:2',
        'total_egresos' => 'decimal:2',
        'total_diferencia' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function grupoCierre(): BelongsTo
    {
        return $this->belongsTo(GrupoCierre::class, 'grupo_cierre_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function usuarioReversion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reversion_id');
    }

    /**
     * Detalle de cada caja que participó en el cierre
     */
    public function detalleCajas(): HasMany
    {
        return $this->hasMany(CierreTurnoCaja::class, 'cierre_turno_id');
    }

    /**
     * Movimientos incluidos en este cierre
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'cierre_turno_id');
    }

    /**
     * Ventas registradas en este cierre
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cierre_turno_id');
    }

    /**
     * Pagos de ventas procesados en este cierre
     */
    public function ventaPagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'cierre_turno_id');
    }

    /**
     * Cobros registrados en este cierre
     */
    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'cierre_turno_id');
    }

    /**
     * Pagos de cobros procesados en este cierre
     */
    public function cobroPagos(): HasMany
    {
        return $this->hasMany(CobroPago::class, 'cierre_turno_id');
    }

    /**
     * Rendiciones de fondo asociadas a este cierre
     */
    public function rendiciones(): HasMany
    {
        return $this->hasMany(RendicionFondo::class, 'cierre_turno_id');
    }

    // ==================== SCOPES ====================

    public function scopeIndividuales($query)
    {
        return $query->where('tipo', 'individual');
    }

    public function scopeGrupales($query)
    {
        return $query->where('tipo', 'grupo');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorUsuario($query, int $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeEntreFechas($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('fecha_cierre', '>=', $desde);
        }
        if ($hasta) {
            $query->where('fecha_cierre', '<=', $hasta);
        }
        return $query;
    }

    public function scopeRecientes($query, int $limite = 10)
    {
        return $query->orderBy('fecha_cierre', 'desc')->limit($limite);
    }

    public function scopeNoRevertidos($query)
    {
        return $query->where('revertido', false);
    }

    // ==================== MÉTODOS ====================

    /**
     * Verifica si es un cierre individual
     */
    public function esIndividual(): bool
    {
        return $this->tipo === 'individual';
    }

    /**
     * Verifica si es un cierre grupal
     */
    public function esGrupal(): bool
    {
        return $this->tipo === 'grupo';
    }

    /**
     * Verifica si el cierre fue revertido
     */
    public function estaRevertido(): bool
    {
        return $this->revertido === true;
    }

    /**
     * Marca el cierre como revertido
     */
    public function marcarComoRevertido(int $usuarioId, ?string $motivo = null): bool
    {
        $this->revertido = true;
        $this->fecha_reversion = now();
        $this->usuario_reversion_id = $usuarioId;
        $this->motivo_reversion = $motivo;

        return $this->save();
    }

    /**
     * Cantidad de cajas en el cierre
     */
    public function getCantidadCajasAttribute(): int
    {
        return $this->detalleCajas()->count();
    }

    /**
     * Verifica si hay diferencia (faltante o sobrante)
     */
    public function tieneDiferencia(): bool
    {
        return $this->total_diferencia != 0;
    }

    /**
     * Verifica si hay faltante
     */
    public function tieneFaltante(): bool
    {
        return $this->total_diferencia < 0;
    }

    /**
     * Verifica si hay sobrante
     */
    public function tieneSobrante(): bool
    {
        return $this->total_diferencia > 0;
    }

    /**
     * Obtiene el nombre descriptivo del cierre
     */
    public function getNombreDescriptivoAttribute(): string
    {
        if ($this->esGrupal() && $this->grupoCierre) {
            return $this->grupoCierre->nombre ?? "Grupo #{$this->grupo_cierre_id}";
        }

        $primeraCaja = $this->detalleCajas()->first();
        return $primeraCaja ? $primeraCaja->caja_nombre : "Cierre #{$this->id}";
    }

    /**
     * Calcula la duración del turno en horas
     */
    public function getDuracionHorasAttribute(): ?float
    {
        if (!$this->fecha_apertura) {
            return null;
        }

        return round($this->fecha_apertura->diffInMinutes($this->fecha_cierre) / 60, 2);
    }

    /**
     * Obtiene los IDs de las cajas incluidas en el cierre
     */
    public function getCajasIdsAttribute(): array
    {
        return $this->detalleCajas()->pluck('caja_id')->toArray();
    }
}
