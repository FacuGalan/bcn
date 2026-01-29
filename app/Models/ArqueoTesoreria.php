<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ArqueoTesoreria
 *
 * Registra los arqueos periódicos de la tesorería para verificar
 * que el saldo físico coincida con el saldo del sistema.
 *
 * @property int $id
 * @property int $tesoreria_id
 * @property \Carbon\Carbon $fecha
 * @property float $saldo_sistema Saldo según sistema
 * @property float $saldo_contado Saldo contado físicamente
 * @property float $diferencia
 * @property int $usuario_id
 * @property int|null $supervisor_id Supervisor que autorizó
 * @property string $estado
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Tesoreria $tesoreria
 * @property-read User $usuario
 * @property-read User|null $supervisor
 */
class ArqueoTesoreria extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'arqueos_tesoreria';

    protected $fillable = [
        'tesoreria_id',
        'fecha',
        'saldo_sistema',
        'saldo_contado',
        'diferencia',
        'usuario_id',
        'supervisor_id',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'saldo_sistema' => 'decimal:2',
        'saldo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    // Estados
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_RECHAZADO = 'rechazado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_APROBADO => 'Aprobado',
        self::ESTADO_RECHAZADO => 'Rechazado',
    ];

    // ==================== RELACIONES ====================

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeAprobados($query)
    {
        return $query->where('estado', self::ESTADO_APROBADO);
    }

    public function scopeConDiferencia($query)
    {
        return $query->where('diferencia', '!=', 0);
    }

    public function scopeDelPeriodo($query, \Carbon\Carbon $desde, \Carbon\Carbon $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    // ==================== ACCESSORS ====================

    public function getEstaPendienteAttribute(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function getEstaAprobadoAttribute(): bool
    {
        return $this->estado === self::ESTADO_APROBADO;
    }

    public function getEstaRechazadoAttribute(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADO;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getTieneSobranteAttribute(): bool
    {
        return $this->diferencia > 0;
    }

    public function getTieneFaltanteAttribute(): bool
    {
        return $this->diferencia < 0;
    }

    public function getEstaCuadradoAttribute(): bool
    {
        return $this->diferencia == 0;
    }

    public function getTipoDiferenciaAttribute(): string
    {
        if ($this->diferencia > 0) return 'sobrante';
        if ($this->diferencia < 0) return 'faltante';
        return 'cuadrado';
    }

    // ==================== MÉTODOS ====================

    /**
     * Aprueba el arqueo (con o sin ajuste)
     */
    public function aprobar(int $supervisorId, bool $aplicarAjuste = false): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->supervisor_id = $supervisorId;
        $this->estado = self::ESTADO_APROBADO;
        $this->save();

        // Si se solicita ajuste y hay diferencia, crear movimiento de ajuste
        if ($aplicarAjuste && $this->diferencia != 0) {
            MovimientoTesoreria::crearAjusteArqueo(
                $this->tesoreria,
                $this->diferencia,
                $supervisorId,
                $this->id
            );
        }

        return true;
    }

    /**
     * Rechaza el arqueo
     */
    public function rechazar(int $supervisorId, string $motivo = null): bool
    {
        if (!$this->esta_pendiente) {
            return false;
        }

        $this->supervisor_id = $supervisorId;
        $this->estado = self::ESTADO_RECHAZADO;
        if ($motivo) {
            $this->observaciones = ($this->observaciones ? $this->observaciones . "\n" : '') . "Rechazado: " . $motivo;
        }
        $this->save();

        return true;
    }

    /**
     * Calcula la diferencia automáticamente
     */
    public function calcularDiferencia(): void
    {
        $this->diferencia = $this->saldo_contado - $this->saldo_sistema;
    }

    /**
     * Crea un nuevo arqueo
     */
    public static function realizar(Tesoreria $tesoreria, float $saldoContado, int $usuarioId, ?string $observaciones = null): self
    {
        $arqueo = new self([
            'tesoreria_id' => $tesoreria->id,
            'fecha' => now(),
            'saldo_sistema' => $tesoreria->saldo_actual,
            'saldo_contado' => $saldoContado,
            'diferencia' => $saldoContado - $tesoreria->saldo_actual,
            'usuario_id' => $usuarioId,
            'estado' => self::ESTADO_PENDIENTE,
            'observaciones' => $observaciones,
        ]);
        $arqueo->save();

        return $arqueo;
    }
}
