<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del detalle de una conciliación: un movimiento del reporte del
 * proveedor (o una fila hija de comisión, o una alerta solo_sistema).
 *
 * Las filas con movimiento propuesto (`accion=generar_movimiento`) guardan
 * tipo_movimiento + concepto_codigo; al aplicar la corrida se genera el
 * MovimientoCuentaEmpresa (origen polimórfico 'ConciliacionFila') y se liga
 * en movimiento_cuenta_empresa_id.
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (RF-05/RF-06).
 */
class ConciliacionFila extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'conciliacion_filas';

    protected $fillable = [
        'conciliacion_cuenta_id',
        'tipo',
        'clasificacion',
        'id_externo',
        'referencia',
        'fecha',
        'descripcion',
        'monto_bruto',
        'comision',
        'monto_neto',
        'accion',
        'tipo_movimiento',
        'concepto_codigo',
        'integracion_pago_transaccion_id',
        'movimiento_cuenta_empresa_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto_bruto' => 'decimal:2',
        'comision' => 'decimal:2',
        'monto_neto' => 'decimal:2',
    ];

    // Tipos normalizados (provider-agnostic).
    public const TIPO_COBRO = 'cobro';

    public const TIPO_COMISION = 'comision';

    public const TIPO_IMPUESTO = 'impuesto';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_CONTRACARGO = 'contracargo';

    public const TIPO_RETIRO = 'retiro';

    public const TIPO_RETIRO_CANCELADO = 'retiro_cancelado';

    public const TIPO_ACREDITACION = 'acreditacion';

    public const TIPO_AJUSTE_INICIAL = 'ajuste_inicial';

    public const TIPO_OTRO = 'otro';

    // Clasificaciones del match.
    public const CLASIFICACION_MATCHEADO = 'matcheado';

    public const CLASIFICACION_SOLO_PROVEEDOR = 'solo_proveedor';

    public const CLASIFICACION_SOLO_SISTEMA = 'solo_sistema';

    public const CLASIFICACION_YA_REGISTRADO = 'ya_registrado';

    // Acciones.
    public const ACCION_GENERAR_MOVIMIENTO = 'generar_movimiento';

    public const ACCION_IGNORAR = 'ignorar';

    public const ACCION_SIN_ACCION = 'sin_accion';

    // ==================== Relaciones ====================

    public function conciliacion(): BelongsTo
    {
        return $this->belongsTo(ConciliacionCuenta::class, 'conciliacion_cuenta_id');
    }

    public function transaccion(): BelongsTo
    {
        return $this->belongsTo(IntegracionPagoTransaccion::class, 'integracion_pago_transaccion_id');
    }

    public function movimientoGenerado(): BelongsTo
    {
        return $this->belongsTo(MovimientoCuentaEmpresa::class, 'movimiento_cuenta_empresa_id');
    }

    // ==================== Scopes ====================

    public function scopePropuestas($query)
    {
        return $query->where('accion', self::ACCION_GENERAR_MOVIMIENTO)
            ->whereNull('movimiento_cuenta_empresa_id');
    }

    public function scopeDeClasificacion($query, string $clasificacion)
    {
        return $query->where('clasificacion', $clasificacion);
    }

    // ==================== Helpers ====================

    /**
     * ¿La fila propone generar un movimiento (editable en la revisión)?
     */
    public function esPropuesta(): bool
    {
        return $this->tipo_movimiento !== null
            && $this->clasificacion !== self::CLASIFICACION_YA_REGISTRADO
            && $this->clasificacion !== self::CLASIFICACION_SOLO_SISTEMA;
    }
}
