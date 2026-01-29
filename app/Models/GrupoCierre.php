<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo GrupoCierre
 *
 * Representa un grupo de cajas que comparten el cierre de turno.
 * Cuando se cierra el turno de una caja del grupo, se cierran todas
 * las cajas del grupo y los movimientos se consolidan en el reporte.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property string|null $nombre
 * @property bool $fondo_comun Si es true, todas las cajas comparten un fondo común
 * @property float $saldo_fondo_comun Saldo del fondo común del grupo
 * @property int|null $tesoreria_id Tesorería asociada al grupo
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read Tesoreria|null $tesoreria
 * @property-read \Illuminate\Database\Eloquent\Collection|Caja[] $cajas
 */
class GrupoCierre extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'grupos_cierre';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'fondo_comun',
        'saldo_fondo_comun',
        'tesoreria_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fondo_comun' => 'boolean',
        'saldo_fondo_comun' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================

    /**
     * Sucursal a la que pertenece el grupo
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Tesorería asociada al grupo
     */
    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    /**
     * Cajas que pertenecen a este grupo de cierre
     */
    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class, 'grupo_cierre_id');
    }

    // ==================== SCOPES ====================

    /**
     * Solo grupos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Grupos de una sucursal específica
     */
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================== MÉTODOS ====================

    /**
     * Obtiene el nombre del grupo o genera uno por defecto
     */
    public function getNombreODefaultAttribute(): string
    {
        return $this->nombre ?? "Grupo #{$this->id}";
    }

    /**
     * Cantidad de cajas en el grupo
     */
    public function getCantidadCajasAttribute(): int
    {
        return $this->cajas()->count();
    }

    /**
     * Verifica si todas las cajas del grupo están cerradas
     */
    public function todasCerradas(): bool
    {
        return $this->cajas()->where('estado', '!=', 'cerrada')->doesntExist();
    }

    /**
     * Verifica si alguna caja del grupo está abierta
     */
    public function tieneAlgunaAbierta(): bool
    {
        return $this->cajas()->where('estado', 'abierta')->exists();
    }

    /**
     * Obtiene las cajas activas del grupo
     */
    public function cajasActivas()
    {
        return $this->cajas()->where('activo', true);
    }

    /**
     * Calcula el saldo total de todas las cajas del grupo
     */
    public function getSaldoTotalAttribute(): float
    {
        return $this->cajas()->sum('saldo_actual');
    }

    // ==================== MÉTODOS DE FONDO COMÚN ====================

    /**
     * Verifica si el grupo usa fondo común
     */
    public function usaFondoComun(): bool
    {
        return $this->fondo_comun === true;
    }

    /**
     * Obtiene el saldo disponible del fondo común
     */
    public function getSaldoFondoDisponible(): float
    {
        return $this->saldo_fondo_comun ?? 0;
    }

    /**
     * Provisiona fondo a una caja desde el fondo común
     */
    public function provisionarACaja(float $monto): bool
    {
        if (!$this->usaFondoComun()) {
            return false;
        }

        if ($this->saldo_fondo_comun < $monto) {
            return false;
        }

        $this->saldo_fondo_comun -= $monto;
        return $this->save();
    }

    /**
     * Recibe fondo de una caja al fondo común
     */
    public function recibirDeCaja(float $monto): bool
    {
        if (!$this->usaFondoComun()) {
            return false;
        }

        $this->saldo_fondo_comun += $monto;
        return $this->save();
    }

    /**
     * Establece el fondo común inicial
     */
    public function establecerFondoComun(float $monto): bool
    {
        $this->saldo_fondo_comun = $monto;
        return $this->save();
    }
}
