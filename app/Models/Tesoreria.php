<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Tesoreria
 *
 * Representa la caja fuerte de una sucursal que centraliza el manejo de efectivo.
 * Permite trazabilidad completa del dinero entre tesorería y cajas.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property string $nombre
 * @property float $saldo_actual
 * @property float|null $saldo_minimo
 * @property float|null $saldo_maximo
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|MovimientoTesoreria[] $movimientos
 * @property-read \Illuminate\Database\Eloquent\Collection|ProvisionFondo[] $provisiones
 * @property-read \Illuminate\Database\Eloquent\Collection|RendicionFondo[] $rendiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|DepositoBancario[] $depositos
 * @property-read \Illuminate\Database\Eloquent\Collection|ArqueoTesoreria[] $arqueos
 */
class Tesoreria extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'tesorerias';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'saldo_actual',
        'saldo_minimo',
        'saldo_maximo',
        'activo',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'saldo_minimo' => 'decimal:2',
        'saldo_maximo' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ==================== RELACIONES ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoTesoreria::class, 'tesoreria_id');
    }

    public function provisiones(): HasMany
    {
        return $this->hasMany(ProvisionFondo::class, 'tesoreria_id');
    }

    public function rendiciones(): HasMany
    {
        return $this->hasMany(RendicionFondo::class, 'tesoreria_id');
    }

    public function depositos(): HasMany
    {
        return $this->hasMany(DepositoBancario::class, 'tesoreria_id');
    }

    public function arqueos(): HasMany
    {
        return $this->hasMany(ArqueoTesoreria::class, 'tesoreria_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(GrupoCierre::class, 'tesoreria_id');
    }

    public function saldosMoneda(): HasMany
    {
        return $this->hasMany(TesoreriaSaldoMoneda::class, 'tesoreria_id');
    }

    // ==================== SCOPES ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================== MÉTODOS DE SALDO ====================

    /**
     * Registra un ingreso en la tesorería
     */
    public function ingreso(float $monto, string $concepto, int $usuarioId, ?string $referenciaTipo = null, ?int $referenciaId = null, ?string $observaciones = null): MovimientoTesoreria
    {
        $saldoAnterior = $this->saldo_actual;
        $this->saldo_actual += $monto;
        $this->save();

        return MovimientoTesoreria::create([
            'tesoreria_id' => $this->id,
            'tipo' => 'ingreso',
            'concepto' => $concepto,
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $this->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
        ]);
    }

    /**
     * Registra un egreso en la tesorería
     */
    public function egreso(float $monto, string $concepto, int $usuarioId, ?string $referenciaTipo = null, ?int $referenciaId = null, ?string $observaciones = null): MovimientoTesoreria
    {
        $saldoAnterior = $this->saldo_actual;
        $this->saldo_actual -= $monto;
        $this->save();

        return MovimientoTesoreria::create([
            'tesoreria_id' => $this->id,
            'tipo' => 'egreso',
            'concepto' => $concepto,
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $this->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
        ]);
    }

    /**
     * Verifica si tiene saldo suficiente
     */
    public function tieneSaldoSuficiente(float $monto): bool
    {
        return $this->saldo_actual >= $monto;
    }

    /**
     * Verifica si está por debajo del saldo mínimo
     */
    public function estaBajoMinimo(): bool
    {
        if ($this->saldo_minimo === null) {
            return false;
        }

        return $this->saldo_actual < $this->saldo_minimo;
    }

    /**
     * Verifica si está por encima del saldo máximo
     */
    public function estaSobreMaximo(): bool
    {
        if ($this->saldo_maximo === null) {
            return false;
        }

        return $this->saldo_actual > $this->saldo_maximo;
    }

    /**
     * Obtiene el monto sugerido para depositar al banco
     */
    public function montoSugeridoDeposito(): float
    {
        if ($this->saldo_maximo === null) {
            return 0;
        }

        if ($this->saldo_actual > $this->saldo_maximo) {
            return $this->saldo_actual - $this->saldo_maximo;
        }

        return 0;
    }

    // ==================== MÉTODOS DE CONSULTA ====================

    /**
     * Obtiene movimientos de un período
     */
    public function movimientosDelPeriodo(\Carbon\Carbon $desde, \Carbon\Carbon $hasta)
    {
        return $this->movimientos()
            ->whereBetween('created_at', [$desde, $hasta])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtiene el total de ingresos de un período
     */
    public function totalIngresosDelPeriodo(\Carbon\Carbon $desde, \Carbon\Carbon $hasta): float
    {
        return $this->movimientos()
            ->where('tipo', 'ingreso')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('monto');
    }

    /**
     * Obtiene el total de egresos de un período
     */
    public function totalEgresosDelPeriodo(\Carbon\Carbon $desde, \Carbon\Carbon $hasta): float
    {
        return $this->movimientos()
            ->where('tipo', 'egreso')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('monto');
    }

    /**
     * Último arqueo realizado
     */
    public function ultimoArqueo(): ?ArqueoTesoreria
    {
        return $this->arqueos()->latest()->first();
    }

    // ==================== MÉTODOS DE SALDO MULTI-MONEDA ====================

    /**
     * Registra un ingreso en moneda extranjera (saldo independiente, NO toca saldo_actual)
     */
    public function ingresoMonedaExtranjera(
        float $montoOriginal,
        string $concepto,
        int $usuarioId,
        int $monedaId,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?string $observaciones = null
    ): MovimientoTesoreria {
        // Actualizar saldo de la moneda
        $saldoMoneda = TesoreriaSaldoMoneda::obtenerOCrear($this->id, $monedaId);
        $saldoAnteriorMoneda = $saldoMoneda->saldo_actual;
        $saldoMoneda->saldo_actual += $montoOriginal;
        $saldoMoneda->save();

        return MovimientoTesoreria::create([
            'tesoreria_id' => $this->id,
            'tipo' => 'ingreso',
            'concepto' => $concepto,
            'monto' => 0, // No se convierte, saldo independiente
            'saldo_anterior' => $this->saldo_actual, // saldo ARS no cambia
            'saldo_posterior' => $this->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
            'moneda_id' => $monedaId,
            'monto_moneda_original' => $montoOriginal,
            'saldo_anterior_moneda' => $saldoAnteriorMoneda,
            'saldo_posterior_moneda' => $saldoMoneda->saldo_actual,
        ]);
    }

    /**
     * Registra un egreso en moneda extranjera (saldo independiente, NO toca saldo_actual)
     */
    public function egresoMonedaExtranjera(
        float $montoOriginal,
        string $concepto,
        int $usuarioId,
        int $monedaId,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?string $observaciones = null
    ): MovimientoTesoreria {
        $saldoMoneda = TesoreriaSaldoMoneda::obtenerOCrear($this->id, $monedaId);
        $saldoAnteriorMoneda = $saldoMoneda->saldo_actual;
        $saldoMoneda->saldo_actual -= $montoOriginal;
        $saldoMoneda->save();

        return MovimientoTesoreria::create([
            'tesoreria_id' => $this->id,
            'tipo' => 'egreso',
            'concepto' => $concepto,
            'monto' => 0,
            'saldo_anterior' => $this->saldo_actual,
            'saldo_posterior' => $this->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'observaciones' => $observaciones,
            'moneda_id' => $monedaId,
            'monto_moneda_original' => $montoOriginal,
            'saldo_anterior_moneda' => $saldoAnteriorMoneda,
            'saldo_posterior_moneda' => $saldoMoneda->saldo_actual,
        ]);
    }

    /**
     * Obtiene saldos de todas las monedas extranjeras con saldo != 0
     */
    public function getSaldosTodasMonedas(): array
    {
        return $this->saldosMoneda()
            ->where('saldo_actual', '!=', 0)
            ->with('moneda')
            ->get()
            ->mapWithKeys(function ($saldo) {
                return [$saldo->moneda->codigo => [
                    'id' => $saldo->moneda_id,
                    'codigo' => $saldo->moneda->codigo,
                    'simbolo' => $saldo->moneda->simbolo,
                    'nombre' => $saldo->moneda->nombre,
                    'saldo' => (float) $saldo->saldo_actual,
                ]];
            })
            ->toArray();
    }

    /**
     * Verifica si tiene saldo suficiente en una moneda extranjera
     */
    public function tieneSaldoSuficienteMoneda(float $monto, int $monedaId): bool
    {
        $saldoMoneda = TesoreriaSaldoMoneda::where('tesoreria_id', $this->id)
            ->where('moneda_id', $monedaId)
            ->first();

        return $saldoMoneda && $saldoMoneda->saldo_actual >= $monto;
    }
}
