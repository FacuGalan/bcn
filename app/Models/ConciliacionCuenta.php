<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corrida de conciliación de una CuentaEmpresa contra el proveedor de pago.
 *
 * Máquina de estados: generando → pendiente_revision → aplicada | descartada.
 * `error` es terminal (se reintenta creando una corrida nueva). El comando
 * conciliaciones:procesar avanza las corridas `generando` (el reporte del
 * proveedor se genera de forma asíncrona).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (RF-03).
 */
class ConciliacionCuenta extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'conciliaciones_cuenta';

    protected $fillable = [
        'cuenta_empresa_id',
        'desde',
        'hasta',
        'estado',
        'origen',
        'solicitud_reporte',
        'archivo_reporte',
        'saldo_sistema',
        'total_matcheados',
        'total_solo_proveedor',
        'total_solo_sistema',
        'monto_propuesto_ingresos',
        'monto_propuesto_egresos',
        'error_mensaje',
        'usuario_id',
        'aplicada_por',
        'aplicada_en',
    ];

    protected $casts = [
        'desde' => 'date',
        'hasta' => 'date',
        'saldo_sistema' => 'decimal:2',
        'monto_propuesto_ingresos' => 'decimal:2',
        'monto_propuesto_egresos' => 'decimal:2',
        'aplicada_en' => 'datetime',
    ];

    // Estados.
    public const ESTADO_GENERANDO = 'generando';

    public const ESTADO_PENDIENTE_REVISION = 'pendiente_revision';

    public const ESTADO_APLICADA = 'aplicada';

    public const ESTADO_DESCARTADA = 'descartada';

    public const ESTADO_ERROR = 'error';

    // Orígenes.
    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PROGRAMADA = 'programada';

    // ==================== Relaciones ====================

    public function cuentaEmpresa(): BelongsTo
    {
        return $this->belongsTo(CuentaEmpresa::class, 'cuenta_empresa_id');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(ConciliacionFila::class, 'conciliacion_cuenta_id');
    }

    // ==================== Scopes ====================

    /**
     * Corridas vivas (generando o pendientes de revisión): solo puede haber
     * UNA por cuenta a la vez (RF-03).
     */
    public function scopeActivas($query)
    {
        return $query->whereIn('estado', [self::ESTADO_GENERANDO, self::ESTADO_PENDIENTE_REVISION]);
    }

    public function scopeGenerando($query)
    {
        return $query->where('estado', self::ESTADO_GENERANDO);
    }

    public function scopeDeCuenta($query, int $cuentaEmpresaId)
    {
        return $query->where('cuenta_empresa_id', $cuentaEmpresaId);
    }

    // ==================== Helpers ====================

    public function estaGenerando(): bool
    {
        return $this->estado === self::ESTADO_GENERANDO;
    }

    public function estaPendienteRevision(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE_REVISION;
    }

    public function estaAplicada(): bool
    {
        return $this->estado === self::ESTADO_APLICADA;
    }

    /**
     * ¿La corrida puede aplicarse/descartarse? Solo en revisión.
     */
    public function esEditable(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE_REVISION;
    }
}
