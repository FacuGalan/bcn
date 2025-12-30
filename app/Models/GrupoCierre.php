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
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|Caja[] $cajas
 */
class GrupoCierre extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'grupos_cierre';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
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
}
