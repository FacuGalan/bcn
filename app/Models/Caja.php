<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'saldo_inicial' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
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
}
