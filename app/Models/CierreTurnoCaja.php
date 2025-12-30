<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo CierreTurnoCaja
 *
 * Representa el detalle de una caja específica dentro de un cierre de turno.
 * Guarda los montos, diferencias y desgloses de esa caja en particular.
 *
 * @property int $id
 * @property int $cierre_turno_id
 * @property int $caja_id
 * @property string $caja_nombre
 * @property float $saldo_inicial
 * @property float $saldo_final
 * @property float $saldo_sistema
 * @property float $saldo_declarado
 * @property float $total_ingresos
 * @property float $total_egresos
 * @property float $diferencia
 * @property array|null $desglose_formas_pago
 * @property array|null $desglose_conceptos
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CierreTurno $cierreTurno
 * @property-read Caja $caja
 */
class CierreTurnoCaja extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'cierre_turno_cajas';

    protected $fillable = [
        'cierre_turno_id',
        'caja_id',
        'caja_nombre',
        'saldo_inicial',
        'saldo_final',
        'saldo_sistema',
        'saldo_declarado',
        'total_ingresos',
        'total_egresos',
        'diferencia',
        'desglose_formas_pago',
        'desglose_conceptos',
        'observaciones',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_final' => 'decimal:2',
        'saldo_sistema' => 'decimal:2',
        'saldo_declarado' => 'decimal:2',
        'total_ingresos' => 'decimal:2',
        'total_egresos' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'desglose_formas_pago' => 'array',
        'desglose_conceptos' => 'array',
    ];

    // ==================== RELACIONES ====================

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    // ==================== SCOPES ====================

    public function scopePorCaja($query, int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    public function scopeConDiferencia($query)
    {
        return $query->where('diferencia', '!=', 0);
    }

    public function scopeConFaltante($query)
    {
        return $query->where('diferencia', '<', 0);
    }

    public function scopeConSobrante($query)
    {
        return $query->where('diferencia', '>', 0);
    }

    // ==================== MÉTODOS ====================

    /**
     * Verifica si hay diferencia (faltante o sobrante)
     */
    public function tieneDiferencia(): bool
    {
        return $this->diferencia != 0;
    }

    /**
     * Verifica si hay faltante
     */
    public function tieneFaltante(): bool
    {
        return $this->diferencia < 0;
    }

    /**
     * Verifica si hay sobrante
     */
    public function tieneSobrante(): bool
    {
        return $this->diferencia > 0;
    }

    /**
     * Obtiene la diferencia en valor absoluto
     */
    public function getDiferenciaAbsolutaAttribute(): float
    {
        return abs($this->diferencia);
    }

    /**
     * Obtiene el tipo de diferencia como texto
     */
    public function getTipoDiferenciaAttribute(): ?string
    {
        if ($this->tieneFaltante()) {
            return 'faltante';
        }
        if ($this->tieneSobrante()) {
            return 'sobrante';
        }
        return null;
    }

    /**
     * Calcula el movimiento neto (ingresos - egresos)
     */
    public function getMovimientoNetoAttribute(): float
    {
        return $this->total_ingresos - $this->total_egresos;
    }

    /**
     * Obtiene el monto de una forma de pago específica
     */
    public function getMontoFormaPago(string $formaPago): float
    {
        $desglose = $this->desglose_formas_pago ?? [];
        return $desglose[$formaPago] ?? 0;
    }

    /**
     * Obtiene el monto de un concepto específico
     */
    public function getMontoConcepto(string $concepto): float
    {
        $desglose = $this->desglose_conceptos ?? [];
        return $desglose[$concepto] ?? 0;
    }

    /**
     * Genera el snapshot de una caja para el cierre
     */
    public static function crearDesdeCAja(Caja $caja, float $saldoDeclarado = null): array
    {
        $saldoSistema = $caja->saldo_actual;
        $saldoDeclarado = $saldoDeclarado ?? $saldoSistema;

        return [
            'caja_id' => $caja->id,
            'caja_nombre' => $caja->nombre,
            'saldo_inicial' => $caja->saldo_inicial,
            'saldo_final' => $saldoSistema,
            'saldo_sistema' => $saldoSistema,
            'saldo_declarado' => $saldoDeclarado,
            'total_ingresos' => $caja->obtenerTotalIngresos(),
            'total_egresos' => $caja->obtenerTotalEgresos(),
            'diferencia' => $saldoDeclarado - $saldoSistema,
        ];
    }
}
