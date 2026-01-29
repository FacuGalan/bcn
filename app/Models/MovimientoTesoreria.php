<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo MovimientoTesoreria
 *
 * Registra cada movimiento de dinero en la tesorería con trazabilidad completa
 * incluyendo saldo anterior y posterior para auditoría.
 *
 * @property int $id
 * @property int $tesoreria_id
 * @property string $tipo (ingreso|egreso)
 * @property string $concepto
 * @property float $monto
 * @property float $saldo_anterior
 * @property float $saldo_posterior
 * @property int $usuario_id
 * @property string|null $referencia_tipo
 * @property int|null $referencia_id
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Tesoreria $tesoreria
 * @property-read User $usuario
 */
class MovimientoTesoreria extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'movimientos_tesoreria';

    protected $fillable = [
        'tesoreria_id',
        'tipo',
        'concepto',
        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'usuario_id',
        'referencia_tipo',
        'referencia_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    // Tipos de referencia comunes
    public const REFERENCIA_PROVISION = 'provision_fondo';
    public const REFERENCIA_RENDICION = 'rendicion_fondo';
    public const REFERENCIA_DEPOSITO = 'deposito_bancario';
    public const REFERENCIA_ARQUEO = 'arqueo';
    public const REFERENCIA_AJUSTE = 'ajuste';

    // ==================== RELACIONES ====================

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ==================== SCOPES ====================

    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorReferencia($query, string $referenciaTipo, ?int $referenciaId = null)
    {
        $query->where('referencia_tipo', $referenciaTipo);
        if ($referenciaId !== null) {
            $query->where('referencia_id', $referenciaId);
        }
        return $query;
    }

    public function scopeDelPeriodo($query, \Carbon\Carbon $desde, \Carbon\Carbon $hasta)
    {
        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    public function scopeDeHoy($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ==================== ACCESSORS ====================

    public function getEsIngresoAttribute(): bool
    {
        return $this->tipo === 'ingreso';
    }

    public function getEsEgresoAttribute(): bool
    {
        return $this->tipo === 'egreso';
    }

    public function getMontoFormateadoAttribute(): string
    {
        $signo = $this->es_ingreso ? '+' : '-';
        return $signo . '$' . number_format($this->monto, 2, ',', '.');
    }

    // ==================== MÉTODOS DE FÁBRICA ====================

    /**
     * Crea un movimiento de provisión de fondo a caja
     */
    public static function crearProvision(Tesoreria $tesoreria, float $monto, int $usuarioId, int $provisionFondoId): self
    {
        $saldoAnterior = $tesoreria->saldo_actual;
        $tesoreria->saldo_actual -= $monto;
        $tesoreria->save();

        return self::create([
            'tesoreria_id' => $tesoreria->id,
            'tipo' => 'egreso',
            'concepto' => 'Provisión de fondo a caja',
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $tesoreria->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REFERENCIA_PROVISION,
            'referencia_id' => $provisionFondoId,
        ]);
    }

    /**
     * Crea un movimiento de rendición de caja
     */
    public static function crearRendicion(Tesoreria $tesoreria, float $monto, int $usuarioId, int $rendicionFondoId): self
    {
        $saldoAnterior = $tesoreria->saldo_actual;
        $tesoreria->saldo_actual += $monto;
        $tesoreria->save();

        return self::create([
            'tesoreria_id' => $tesoreria->id,
            'tipo' => 'ingreso',
            'concepto' => 'Rendición de caja',
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $tesoreria->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REFERENCIA_RENDICION,
            'referencia_id' => $rendicionFondoId,
        ]);
    }

    /**
     * Crea un movimiento de depósito bancario
     */
    public static function crearDeposito(Tesoreria $tesoreria, float $monto, int $usuarioId, int $depositoId): self
    {
        $saldoAnterior = $tesoreria->saldo_actual;
        $tesoreria->saldo_actual -= $monto;
        $tesoreria->save();

        return self::create([
            'tesoreria_id' => $tesoreria->id,
            'tipo' => 'egreso',
            'concepto' => 'Depósito bancario',
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $tesoreria->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REFERENCIA_DEPOSITO,
            'referencia_id' => $depositoId,
        ]);
    }

    /**
     * Crea un movimiento de ajuste por arqueo
     */
    public static function crearAjusteArqueo(Tesoreria $tesoreria, float $diferencia, int $usuarioId, int $arqueoId): self
    {
        $saldoAnterior = $tesoreria->saldo_actual;
        $tesoreria->saldo_actual += $diferencia;
        $tesoreria->save();

        $tipo = $diferencia >= 0 ? 'ingreso' : 'egreso';
        $concepto = $diferencia >= 0 ? 'Ajuste por sobrante en arqueo' : 'Ajuste por faltante en arqueo';

        return self::create([
            'tesoreria_id' => $tesoreria->id,
            'tipo' => $tipo,
            'concepto' => $concepto,
            'monto' => abs($diferencia),
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $tesoreria->saldo_actual,
            'usuario_id' => $usuarioId,
            'referencia_tipo' => self::REFERENCIA_ARQUEO,
            'referencia_id' => $arqueoId,
        ]);
    }
}
