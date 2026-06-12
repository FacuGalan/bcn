<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger fiscal (RF-03 sistema-impositivo): cada impuesto sufrido o aplicado.
 *
 * Append-only: NUNCA se edita ni borra — la anulación genera un contraasiento
 * (movimiento nuevo con movimiento_anulado_id apuntando al original, y el
 * original pasa a estado anulado). El origen es polimórfico con string plano
 * (ComprobanteFiscal/Compra/ConciliacionFila/NULL=manual), SIN morphMap.
 * Escribe únicamente ImpuestoService.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-03).
 */
class MovimientoFiscal extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'movimientos_fiscales';

    protected $fillable = [
        'cuit_id',
        'sucursal_id',
        'impuesto_id',
        'sentido',
        'naturaleza',
        'fecha',
        'periodo_fiscal',
        'base_imponible',
        'alicuota',
        'monto',
        'certificado_numero',
        'origen_tipo',
        'origen_id',
        'movimiento_anulado_id',
        'estado',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'base_imponible' => 'decimal:2',
        'alicuota' => 'decimal:4',
        'monto' => 'decimal:2',
    ];

    // Sentidos.
    public const SENTIDO_SUFRIDO = 'sufrido';

    public const SENTIDO_APLICADO = 'aplicado';

    // Naturalezas.
    public const NATURALEZA_PERCEPCION = 'percepcion';

    public const NATURALEZA_RETENCION = 'retencion';

    public const NATURALEZA_DEBITO_FISCAL = 'debito_fiscal';

    public const NATURALEZA_CREDITO_FISCAL = 'credito_fiscal';

    public const NATURALEZA_TRIBUTO = 'tributo';

    // Estados.
    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_ANULADO = 'anulado';

    // ==================
    // RELACIONES
    // ==================

    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class)->withTrashed();
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function movimientoAnulado(): BelongsTo
    {
        return $this->belongsTo(MovimientoFiscal::class, 'movimiento_anulado_id');
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeActivos($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopeDeCuit($query, int $cuitId)
    {
        return $query->where('cuit_id', $cuitId);
    }

    public function scopeDePeriodo($query, string $periodoFiscal)
    {
        return $query->where('periodo_fiscal', $periodoFiscal);
    }

    public function scopeSufridos($query)
    {
        return $query->where('sentido', self::SENTIDO_SUFRIDO);
    }

    public function scopeAplicados($query)
    {
        return $query->where('sentido', self::SENTIDO_APLICADO);
    }

    // ==================
    // HELPERS
    // ==================

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function esContraasiento(): bool
    {
        return $this->movimiento_anulado_id !== null;
    }
}
