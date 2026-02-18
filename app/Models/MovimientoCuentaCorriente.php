<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo MovimientoCuentaCorriente
 *
 * Representa un movimiento en la cuenta corriente de un cliente.
 * Esta tabla unifica todos los movimientos de deuda y saldo a favor:
 * - Ventas a cuenta corriente (generan deuda)
 * - Cobros (disminuyen deuda)
 * - Anticipos (generan saldo a favor)
 * - Uso de saldo a favor (consume saldo a favor)
 * - Anulaciones (contraasientos)
 * - Ajustes manuales
 *
 * IMPORTANTE: El saldo NO se guarda en cada movimiento, se CALCULA sumando
 * todos los movimientos activos. Esto evita problemas de concurrencia.
 *
 * Saldo deudor = SUM(debe) - SUM(haber) de movimientos activos
 * Saldo a favor = SUM(saldo_favor_haber) - SUM(saldo_favor_debe) de movimientos activos
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $sucursal_id
 * @property \Carbon\Carbon $fecha
 * @property string $tipo
 * @property float $debe
 * @property float $haber
 * @property float $saldo_favor_debe
 * @property float $saldo_favor_haber
 * @property string $documento_tipo
 * @property int $documento_id
 * @property int|null $venta_id
 * @property int|null $venta_pago_id
 * @property int|null $cobro_id
 * @property string $concepto
 * @property string|null $observaciones
 * @property string $estado
 * @property int|null $anulado_por_movimiento_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MovimientoCuentaCorriente extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'movimientos_cuenta_corriente';

    // Tipos de movimiento
    public const TIPO_VENTA = 'venta';
    public const TIPO_COBRO = 'cobro';
    public const TIPO_ANTICIPO = 'anticipo';
    public const TIPO_USO_SALDO_FAVOR = 'uso_saldo_favor';
    public const TIPO_DEVOLUCION_SALDO = 'devolucion_saldo';
    public const TIPO_ANULACION_VENTA = 'anulacion_venta';
    public const TIPO_ANULACION_COBRO = 'anulacion_cobro';
    public const TIPO_NOTA_CREDITO = 'nota_credito';
    public const TIPO_AJUSTE_DEBITO = 'ajuste_debito';
    public const TIPO_AJUSTE_CREDITO = 'ajuste_credito';

    // Tipos de documento
    public const DOC_VENTA = 'venta';
    public const DOC_VENTA_PAGO = 'venta_pago';
    public const DOC_COBRO = 'cobro';
    public const DOC_COBRO_VENTA = 'cobro_venta';
    public const DOC_COBRO_PAGO = 'cobro_pago';
    public const DOC_NOTA_CREDITO = 'nota_credito';
    public const DOC_AJUSTE = 'ajuste';

    protected $fillable = [
        'cliente_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'debe',
        'haber',
        'saldo_favor_debe',
        'saldo_favor_haber',
        'documento_tipo',
        'documento_id',
        'venta_id',
        'venta_pago_id',
        'cobro_id',
        'concepto',
        'descripcion_comprobantes',
        'observaciones',
        'estado',
        'anulado_por_movimiento_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
        'saldo_favor_debe' => 'decimal:2',
        'saldo_favor_haber' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaPago(): BelongsTo
    {
        return $this->belongsTo(VentaPago::class);
    }

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Movimiento que anuló este (contraasiento)
     */
    public function anuladoPorMovimiento(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anulado_por_movimiento_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeAnulados($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopeDelCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopeDeLaSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeDeDeuda($query)
    {
        return $query->where(function ($q) {
            $q->where('debe', '>', 0)->orWhere('haber', '>', 0);
        });
    }

    public function scopeDeSaldoFavor($query)
    {
        return $query->where(function ($q) {
            $q->where('saldo_favor_debe', '>', 0)->orWhere('saldo_favor_haber', '>', 0);
        });
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        if ($desde) {
            $query->where('fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->where('fecha', '<=', $hasta);
        }
        return $query;
    }

    // ==================== Métodos de instancia ====================

    public function esActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function esAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    public function esDebito(): bool
    {
        return $this->debe > 0;
    }

    public function esCredito(): bool
    {
        return $this->haber > 0;
    }

    public function generaSaldoFavor(): bool
    {
        return $this->saldo_favor_haber > 0;
    }

    public function usaSaldoFavor(): bool
    {
        return $this->saldo_favor_debe > 0;
    }

    /**
     * Obtiene el efecto neto en la deuda (+aumenta, -disminuye)
     */
    public function getEfectoDeudaAttribute(): float
    {
        return (float) $this->debe - (float) $this->haber;
    }

    /**
     * Obtiene el efecto neto en el saldo a favor (+aumenta, -disminuye)
     */
    public function getEfectoSaldoFavorAttribute(): float
    {
        return (float) $this->saldo_favor_haber - (float) $this->saldo_favor_debe;
    }

    // ==================== Métodos estáticos de cálculo ====================

    /**
     * Calcula el saldo deudor de un cliente en una sucursal
     */
    public static function calcularSaldoDeudor(int $clienteId, int $sucursalId): float
    {
        return (float) static::where('cliente_id', $clienteId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    /**
     * Calcula el saldo a favor de un cliente (global, no por sucursal)
     */
    public static function calcularSaldoFavor(int $clienteId): float
    {
        return (float) static::where('cliente_id', $clienteId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    /**
     * Calcula el saldo deudor global de un cliente (todas las sucursales)
     */
    public static function calcularSaldoDeudorGlobal(int $clienteId): float
    {
        return (float) static::where('cliente_id', $clienteId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    /**
     * Obtiene los saldos de un cliente en una sucursal
     */
    public static function obtenerSaldos(int $clienteId, int $sucursalId): array
    {
        $resultado = static::where('cliente_id', $clienteId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->selectRaw('
                COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor,
                COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
            ')
            ->first();

        return [
            'saldo_deudor' => (float) ($resultado->saldo_deudor ?? 0),
            'saldo_favor' => (float) ($resultado->saldo_favor ?? 0),
        ];
    }

    /**
     * Obtiene los saldos globales de un cliente (todas las sucursales)
     */
    public static function obtenerSaldosGlobales(int $clienteId): array
    {
        $resultado = static::where('cliente_id', $clienteId)
            ->where('estado', 'activo')
            ->selectRaw('
                COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor,
                COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
            ')
            ->first();

        return [
            'saldo_deudor' => (float) ($resultado->saldo_deudor ?? 0),
            'saldo_favor' => (float) ($resultado->saldo_favor ?? 0),
        ];
    }

    // ==================== Métodos de creación ====================

    /**
     * Crea un movimiento de venta a cuenta corriente
     */
    public static function crearMovimientoVenta(
        VentaPago $ventaPago,
        int $usuarioId
    ): self {
        $venta = $ventaPago->venta;

        return static::create([
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'fecha' => $venta->fecha,
            'tipo' => self::TIPO_VENTA,
            'debe' => $ventaPago->monto_final,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => 0,
            'documento_tipo' => self::DOC_VENTA_PAGO,
            'documento_id' => $ventaPago->id,
            'venta_id' => $venta->id,
            'venta_pago_id' => $ventaPago->id,
            'cobro_id' => null,
            'concepto' => "Venta #{$venta->numero} - Cuenta Corriente",
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de cobro aplicado a deuda
     */
    public static function crearMovimientoCobro(
        Cobro $cobro,
        CobroVenta $cobroVenta,
        int $usuarioId
    ): self {
        $venta = $cobroVenta->venta;

        // Construir descripcion_comprobantes
        $comprobantes = ["Recibo {$cobro->numero_recibo}"];
        if ($venta) {
            $comprobantes[] = "Ticket {$venta->numero}";
        }

        return static::create([
            'cliente_id' => $cobro->cliente_id,
            'sucursal_id' => $cobro->sucursal_id,
            'fecha' => $cobro->fecha,
            'tipo' => self::TIPO_COBRO,
            'debe' => 0,
            'haber' => $cobroVenta->monto_aplicado,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => 0,
            'documento_tipo' => self::DOC_COBRO_VENTA,
            'documento_id' => $cobroVenta->id,
            'venta_id' => $cobroVenta->venta_id,
            'venta_pago_id' => $cobroVenta->venta_pago_id,
            'cobro_id' => $cobro->id,
            'concepto' => 'Cobro aplicado a venta',
            'descripcion_comprobantes' => implode(' | ', $comprobantes),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de anticipo (genera saldo a favor)
     */
    public static function crearMovimientoAnticipo(
        Cobro $cobro,
        float $monto,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $cobro->cliente_id,
            'sucursal_id' => $cobro->sucursal_id,
            'fecha' => $cobro->fecha,
            'tipo' => self::TIPO_ANTICIPO,
            'debe' => 0,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => $monto,
            'documento_tipo' => self::DOC_COBRO,
            'documento_id' => $cobro->id,
            'venta_id' => null,
            'venta_pago_id' => null,
            'cobro_id' => $cobro->id,
            'concepto' => 'Anticipo recibido',
            'descripcion_comprobantes' => "Recibo {$cobro->numero_recibo}",
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de uso de saldo a favor
     */
    public static function crearMovimientoUsoSaldoFavor(
        Cobro $cobro,
        float $monto,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $cobro->cliente_id,
            'sucursal_id' => $cobro->sucursal_id,
            'fecha' => $cobro->fecha,
            'tipo' => self::TIPO_USO_SALDO_FAVOR,
            'debe' => 0,
            'haber' => $monto, // Disminuye deuda
            'saldo_favor_debe' => $monto, // Consume saldo a favor
            'saldo_favor_haber' => 0,
            'documento_tipo' => self::DOC_COBRO,
            'documento_id' => $cobro->id,
            'venta_id' => null,
            'venta_pago_id' => null,
            'cobro_id' => $cobro->id,
            'concepto' => 'Saldo a favor aplicado',
            'descripcion_comprobantes' => "Recibo {$cobro->numero_recibo}",
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un movimiento de excedente de pago (genera saldo a favor)
     */
    public static function crearMovimientoExcedente(
        Cobro $cobro,
        float $monto,
        int $usuarioId
    ): self {
        return static::create([
            'cliente_id' => $cobro->cliente_id,
            'sucursal_id' => $cobro->sucursal_id,
            'fecha' => $cobro->fecha,
            'tipo' => self::TIPO_ANTICIPO, // Mismo tipo que anticipo
            'debe' => 0,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => $monto,
            'documento_tipo' => self::DOC_COBRO,
            'documento_id' => $cobro->id,
            'venta_id' => null,
            'venta_pago_id' => null,
            'cobro_id' => $cobro->id,
            'concepto' => 'Excedente de pago',
            'descripcion_comprobantes' => "Recibo {$cobro->numero_recibo}",
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Crea un contraasiento para anular un movimiento
     *
     * IMPORTANTE: El movimiento original NO se marca como 'anulado' porque
     * queremos que tanto el original como el contraasiento permanezcan activos
     * y se cancelen matemáticamente entre sí. Esto mantiene la trazabilidad
     * completa y el balance correcto.
     *
     * Original: debe=100, haber=0 → efecto +100
     * Contraasiento: debe=0, haber=100 → efecto -100
     * Suma: 0 (se cancelan)
     */
    public static function crearContraasiento(
        self $movimientoOriginal,
        string $motivo,
        int $usuarioId
    ): self {
        // Determinar el tipo de anulación según el tipo original
        $tipoAnulacion = match ($movimientoOriginal->tipo) {
            self::TIPO_VENTA => self::TIPO_ANULACION_VENTA,
            self::TIPO_COBRO, self::TIPO_ANTICIPO, self::TIPO_USO_SALDO_FAVOR => self::TIPO_ANULACION_COBRO,
            default => self::TIPO_AJUSTE_CREDITO,
        };

        // El contraasiento invierte los montos para cancelar el efecto del original
        $contraasiento = static::create([
            'cliente_id' => $movimientoOriginal->cliente_id,
            'sucursal_id' => $movimientoOriginal->sucursal_id,
            'fecha' => now()->toDateString(),
            'tipo' => $tipoAnulacion,
            'debe' => $movimientoOriginal->haber, // Invertido
            'haber' => $movimientoOriginal->debe, // Invertido
            'saldo_favor_debe' => $movimientoOriginal->saldo_favor_haber, // Invertido
            'saldo_favor_haber' => $movimientoOriginal->saldo_favor_debe, // Invertido
            'documento_tipo' => $movimientoOriginal->documento_tipo,
            'documento_id' => $movimientoOriginal->documento_id,
            'venta_id' => $movimientoOriginal->venta_id,
            'venta_pago_id' => $movimientoOriginal->venta_pago_id,
            'cobro_id' => $movimientoOriginal->cobro_id,
            'concepto' => "Anulación: {$movimientoOriginal->concepto}",
            'observaciones' => $motivo,
            'usuario_id' => $usuarioId,
        ]);

        // Vincular el original con su contraasiento (para trazabilidad)
        // pero NO cambiar el estado - ambos deben permanecer 'activo'
        $movimientoOriginal->update([
            'anulado_por_movimiento_id' => $contraasiento->id,
        ]);

        return $contraasiento;
    }
}
