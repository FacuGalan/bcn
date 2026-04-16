<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentaPagoAjuste
 *
 * Audit log de operaciones de ajuste sobre pagos de ventas.
 * Un registro por operación atómica (cambio/agregado/eliminación de pago).
 *
 * @property int $id
 * @property int $venta_id
 * @property int $sucursal_id
 * @property string $tipo_operacion
 * @property int|null $venta_pago_anulado_id
 * @property int|null $venta_pago_nuevo_id
 * @property int|null $forma_pago_anterior_id
 * @property int|null $forma_pago_nueva_id
 * @property float|null $monto_anterior
 * @property float|null $monto_nuevo
 * @property float $delta_total
 * @property bool $delta_fiscal
 * @property int|null $turno_original_id
 * @property bool $es_post_cierre
 * @property int|null $nc_emitida_id
 * @property int|null $fc_nueva_id
 * @property bool $nc_emitida_flag
 * @property bool $fc_nueva_flag
 * @property bool $salteo_nc_autorizado
 * @property bool $config_auto_al_operar
 * @property string $motivo
 * @property string $descripcion_auto
 * @property int $usuario_id
 * @property string|null $ip_origen
 * @property string|null $user_agent
 */
class VentaPagoAjuste extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'venta_pago_ajustes';

    public const TIPO_CAMBIO = 'cambio_pago';

    public const TIPO_AGREGAR = 'agregar_pago';

    public const TIPO_ELIMINAR = 'eliminar_pago';

    protected $fillable = [
        'venta_id',
        'sucursal_id',
        'tipo_operacion',
        'venta_pago_anulado_id',
        'venta_pago_nuevo_id',
        'forma_pago_anterior_id',
        'forma_pago_nueva_id',
        'monto_anterior',
        'monto_nuevo',
        'delta_total',
        'delta_fiscal',
        'turno_original_id',
        'es_post_cierre',
        'nc_emitida_id',
        'fc_nueva_id',
        'nc_emitida_flag',
        'fc_nueva_flag',
        'salteo_nc_autorizado',
        'config_auto_al_operar',
        'motivo',
        'descripcion_auto',
        'usuario_id',
        'ip_origen',
        'user_agent',
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'delta_total' => 'decimal:2',
        'delta_fiscal' => 'boolean',
        'es_post_cierre' => 'boolean',
        'nc_emitida_flag' => 'boolean',
        'fc_nueva_flag' => 'boolean',
        'salteo_nc_autorizado' => 'boolean',
        'config_auto_al_operar' => 'boolean',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function ventaPagoAnulado(): BelongsTo
    {
        return $this->belongsTo(VentaPago::class, 'venta_pago_anulado_id');
    }

    public function ventaPagoNuevo(): BelongsTo
    {
        return $this->belongsTo(VentaPago::class, 'venta_pago_nuevo_id');
    }

    public function formaPagoAnterior(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_anterior_id');
    }

    public function formaPagoNueva(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_nueva_id');
    }

    public function ncEmitida(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'nc_emitida_id');
    }

    public function fcNueva(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'fc_nueva_id');
    }

    public function turnoOriginal(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'turno_original_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ==================
    // SCOPES
    // ==================

    public function scopePostCierre($query)
    {
        return $query->where('es_post_cierre', true);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorRangoFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    public function scopePorUsuario($query, int $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopePorTipoOperacion($query, string $tipo)
    {
        return $query->where('tipo_operacion', $tipo);
    }

    // ==================
    // HELPERS
    // ==================

    public function esCambio(): bool
    {
        return $this->tipo_operacion === self::TIPO_CAMBIO;
    }

    public function esAgregar(): bool
    {
        return $this->tipo_operacion === self::TIPO_AGREGAR;
    }

    public function esEliminar(): bool
    {
        return $this->tipo_operacion === self::TIPO_ELIMINAR;
    }

    public function afectoTurnoCerrado(): bool
    {
        return $this->es_post_cierre === true;
    }
}
