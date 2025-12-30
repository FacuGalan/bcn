<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Caja
 *
 * Representa una caja registradora o punto de cobro en una sucursal.
 * Puede ser de tipo efectivo, banco, tarjeta o cheque.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property string $nombre
 * @property string $tipo
 * @property float $saldo_actual
 * @property float $saldo_inicial
 * @property \Carbon\Carbon|null $fecha_apertura
 * @property \Carbon\Carbon|null $fecha_cierre
 * @property string $estado
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|MovimientoCaja[] $movimientos
 * @property-read \Illuminate\Database\Eloquent\Collection|Venta[] $ventas
 * @property-read \Illuminate\Database\Eloquent\Collection|Compra[] $compras
 */
class Caja extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cajas';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'tipo',
        'saldo_actual',
        'saldo_inicial',
        'fecha_apertura',
        'fecha_cierre',
        'estado',
        'activo',
        'limite_efectivo',
        'modo_carga_inicial',
        'monto_fijo_inicial',
        'grupo_cierre_id',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'saldo_inicial' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'activo' => 'boolean',
        'limite_efectivo' => 'decimal:2',
        'monto_fijo_inicial' => 'decimal:2',
    ];

    /**
     * Opciones para modo de carga inicial
     */
    public const MODOS_CARGA_INICIAL = [
        'manual' => 'Manual',
        'ultimo_cierre' => 'Automático (último cierre)',
        'monto_fijo' => 'Automático (monto fijo)',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Grupo de cierre al que pertenece la caja (si comparte cierre)
     */
    public function grupoCierre(): BelongsTo
    {
        return $this->belongsTo(GrupoCierre::class, 'grupo_cierre_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'caja_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'caja_id');
    }

    public function puntosVenta(): BelongsToMany
    {
        return $this->belongsToMany(PuntoVenta::class, 'punto_venta_caja')
            ->withPivot('es_defecto')
            ->withTimestamps();
    }

    /**
     * Historial de cierres de turno donde participó esta caja
     */
    public function cierresTurno(): HasMany
    {
        return $this->hasMany(CierreTurnoCaja::class, 'caja_id');
    }

    /**
     * Obtiene el punto de venta por defecto de la caja
     */
    public function puntoVentaDefecto()
    {
        return $this->puntosVenta()->wherePivot('es_defecto', true)->first();
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeAbiertas($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeCerradas($query)
    {
        return $query->where('estado', 'cerrada');
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeEfectivo($query)
    {
        return $query->where('tipo', 'efectivo');
    }

    public function scopeBanco($query)
    {
        return $query->where('tipo', 'banco');
    }

    /**
     * Cajas que cierran de forma individual (sin grupo)
     */
    public function scopeSinGrupo($query)
    {
        return $query->whereNull('grupo_cierre_id');
    }

    /**
     * Cajas que pertenecen a un grupo de cierre
     */
    public function scopeConGrupo($query)
    {
        return $query->whereNotNull('grupo_cierre_id');
    }

    // Métodos auxiliares

    /**
     * Verifica si la caja está abierta
     */
    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    /**
     * Verifica si la caja está cerrada
     */
    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    /**
     * Abre la caja con un saldo inicial
     */
    public function abrir(float $saldoInicial = 0): bool
    {
        if ($this->estaAbierta()) {
            return false; // Ya está abierta
        }

        $this->saldo_inicial = $saldoInicial;
        $this->saldo_actual = $saldoInicial;
        $this->fecha_apertura = now();
        $this->fecha_cierre = null;
        $this->estado = 'abierta';

        return $this->save();
    }

    /**
     * Cierra la caja
     */
    public function cerrar(): bool
    {
        if ($this->estaCerrada()) {
            return false; // Ya está cerrada
        }

        $this->fecha_cierre = now();
        $this->estado = 'cerrada';

        return $this->save();
    }

    /**
     * Ajusta el saldo de la caja
     *
     * @param float $monto Positivo aumenta, negativo disminuye
     * @return bool
     */
    public function ajustarSaldo(float $monto): bool
    {
        $this->saldo_actual += $monto;

        // Para tipo efectivo, no permitir saldo negativo
        if ($this->tipo === 'efectivo' && $this->saldo_actual < 0) {
            return false;
        }

        return $this->save();
    }

    /**
     * Aumenta el saldo
     */
    public function aumentarSaldo(float $monto): bool
    {
        return $this->ajustarSaldo(abs($monto));
    }

    /**
     * Disminuye el saldo
     */
    public function disminuirSaldo(float $monto): bool
    {
        return $this->ajustarSaldo(-abs($monto));
    }

    /**
     * Obtiene el saldo disponible
     */
    public function obtenerSaldoDisponible(): float
    {
        return max(0, $this->saldo_actual);
    }

    /**
     * Verifica si tiene saldo suficiente
     */
    public function tieneSaldoSuficiente(float $monto): bool
    {
        // Solo cajas de efectivo verifican saldo
        if ($this->tipo !== 'efectivo') {
            return true;
        }

        return $this->saldo_actual >= $monto;
    }

    /**
     * Calcula la diferencia entre saldo actual y movimientos registrados
     */
    public function calcularDiferencia(): float
    {
        if (!$this->estaAbierta()) {
            return 0;
        }

        $ingresos = $this->movimientos()
                         ->where('tipo_movimiento', 'ingreso')
                         ->sum('monto');

        $egresos = $this->movimientos()
                        ->where('tipo_movimiento', 'egreso')
                        ->sum('monto');

        $saldoCalculado = $this->saldo_inicial + $ingresos - $egresos;

        return $this->saldo_actual - $saldoCalculado;
    }

    /**
     * Obtiene el total de ingresos desde la apertura
     */
    public function obtenerTotalIngresos(): float
    {
        return $this->movimientos()
                    ->where('tipo_movimiento', 'ingreso')
                    ->sum('monto');
    }

    /**
     * Obtiene el total de egresos desde la apertura
     */
    public function obtenerTotalEgresos(): float
    {
        return $this->movimientos()
                    ->where('tipo_movimiento', 'egreso')
                    ->sum('monto');
    }

    // ==================== MÉTODOS DE GRUPO DE CIERRE ====================

    /**
     * Verifica si la caja comparte cierre con otras cajas
     */
    public function comparteCierre(): bool
    {
        return $this->grupo_cierre_id !== null;
    }

    /**
     * Verifica si la caja cierra de forma individual
     */
    public function cierraIndividual(): bool
    {
        return $this->grupo_cierre_id === null;
    }

    /**
     * Obtiene las otras cajas del mismo grupo de cierre
     */
    public function cajasDelMismoGrupo()
    {
        if (!$this->comparteCierre()) {
            return collect();
        }

        return static::where('grupo_cierre_id', $this->grupo_cierre_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Obtiene todas las cajas del grupo (incluyendo esta)
     */
    public function todasLasCajasDelGrupo()
    {
        if (!$this->comparteCierre()) {
            return collect([$this]);
        }

        return static::where('grupo_cierre_id', $this->grupo_cierre_id)->get();
    }

    /**
     * Calcula el saldo total del grupo de cierre
     */
    public function getSaldoGrupoAttribute(): float
    {
        if (!$this->comparteCierre()) {
            return $this->saldo_actual;
        }

        return static::where('grupo_cierre_id', $this->grupo_cierre_id)
            ->sum('saldo_actual');
    }
}
