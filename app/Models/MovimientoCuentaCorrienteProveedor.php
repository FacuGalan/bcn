<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger de cuenta corriente de PROVEEDORES (RF-18, D12) — espejo de
 * MovimientoCuentaCorriente (clientes) con semántica contable de PASIVO:
 *
 *   HABER = aumenta la deuda con el proveedor (compra)
 *   DEBE  = la reduce (pago, NC del proveedor)
 *   saldo = Σhaber − Σdebe (sobre movimientos activos, on-the-fly)
 *
 * saldo_favor_* rastrea el saldo a favor NUESTRO con el proveedor
 * (anticipos/excedentes pagados de más): haber lo genera, debe lo consume.
 *
 * Append-only: las anulaciones crean contraasientos; AMBOS quedan activos y
 * se cancelan matemáticamente (patrón exacto de clientes).
 */
class MovimientoCuentaCorrienteProveedor extends Model
{
    public const TIPO_COMPRA = 'compra';

    public const TIPO_PAGO = 'pago';

    public const TIPO_ANTICIPO = 'anticipo';

    public const TIPO_USO_SALDO_FAVOR = 'uso_saldo_favor';

    public const TIPO_NOTA_CREDITO = 'nota_credito';

    public const TIPO_DEVOLUCION_SALDO = 'devolucion_saldo';

    public const TIPO_ANULACION_COMPRA = 'anulacion_compra';

    public const TIPO_ANULACION_PAGO = 'anulacion_pago';

    public const TIPO_AJUSTE_DEBITO = 'ajuste_debito';

    public const TIPO_AJUSTE_CREDITO = 'ajuste_credito';

    public const DOC_COMPRA = 'compra';

    public const DOC_PAGO = 'pago';

    public const DOC_PAGO_COMPRA = 'pago_compra';

    public const DOC_AJUSTE = 'ajuste';

    protected $connection = 'pymes_tenant';

    protected $table = 'movimientos_cuenta_corriente_proveedor';

    protected $fillable = [
        'proveedor_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'debe',
        'haber',
        'saldo_favor_debe',
        'saldo_favor_haber',
        'documento_tipo',
        'documento_id',
        'compra_id',
        'pago_proveedor_id',
        'concepto',
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

    // ==================== RELACIONES ====================

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function pagoProveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedor::class);
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    // ==================== SALDOS (on-the-fly, nunca por fila) ====================

    /**
     * Deuda NUESTRA con el proveedor en una sucursal (pasivo: haber − debe).
     */
    public static function calcularSaldoDeuda(int $proveedorId, int $sucursalId): float
    {
        return (float) static::where('proveedor_id', $proveedorId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(haber), 0) - COALESCE(SUM(debe), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    public static function calcularSaldoDeudaGlobal(int $proveedorId): float
    {
        return (float) static::where('proveedor_id', $proveedorId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(haber), 0) - COALESCE(SUM(debe), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    /**
     * Saldo a favor NUESTRO con el proveedor (global, como en clientes).
     */
    public static function calcularSaldoFavor(int $proveedorId): float
    {
        return (float) static::where('proveedor_id', $proveedorId)
            ->where('estado', 'activo')
            ->selectRaw('COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo')
            ->value('saldo') ?? 0;
    }

    public static function obtenerSaldos(int $proveedorId, int $sucursalId): array
    {
        $resultado = static::where('proveedor_id', $proveedorId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->selectRaw('
                COALESCE(SUM(haber), 0) - COALESCE(SUM(debe), 0) as saldo_deuda,
                COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
            ')
            ->first();

        return [
            'saldo_deuda' => (float) ($resultado->saldo_deuda ?? 0),
            'saldo_favor' => (float) ($resultado->saldo_favor ?? 0),
        ];
    }

    public static function obtenerSaldosGlobales(int $proveedorId): array
    {
        $resultado = static::where('proveedor_id', $proveedorId)
            ->where('estado', 'activo')
            ->selectRaw('
                COALESCE(SUM(haber), 0) - COALESCE(SUM(debe), 0) as saldo_deuda,
                COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
            ')
            ->first();

        return [
            'saldo_deuda' => (float) ($resultado->saldo_deuda ?? 0),
            'saldo_favor' => (float) ($resultado->saldo_favor ?? 0),
        ];
    }

    // ==================== FACTORIES ====================

    /**
     * HABER por el total de la compra confirmada (aumenta nuestra deuda).
     */
    public static function crearMovimientoCompra(Compra $compra, int $usuarioId): self
    {
        return static::create([
            'proveedor_id' => $compra->proveedor_id,
            'sucursal_id' => $compra->sucursal_id,
            'fecha' => $compra->fecha_comprobante ?? $compra->fecha,
            'tipo' => self::TIPO_COMPRA,
            'debe' => 0,
            'haber' => $compra->total,
            'documento_tipo' => self::DOC_COMPRA,
            'documento_id' => $compra->id,
            'compra_id' => $compra->id,
            'concepto' => __('Compra :numero', ['numero' => $compra->numero_comprobante])
                .($compra->numero_comprobante_proveedor ? " ({$compra->numero_comprobante_proveedor})" : ''),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * DEBE por un pago aplicado a una compra (reduce nuestra deuda).
     */
    public static function crearMovimientoPago(PagoProveedor $pago, PagoProveedorCompra $aplicacion, int $usuarioId): self
    {
        return static::create([
            'proveedor_id' => $pago->proveedor_id,
            'sucursal_id' => $pago->sucursal_id,
            'fecha' => $pago->fecha,
            'tipo' => self::TIPO_PAGO,
            'debe' => $aplicacion->monto_aplicado,
            'haber' => 0,
            'documento_tipo' => self::DOC_PAGO_COMPRA,
            'documento_id' => $aplicacion->id,
            'compra_id' => $aplicacion->compra_id,
            'pago_proveedor_id' => $pago->id,
            'concepto' => __('Pago aplicado a compra — OP :numero', ['numero' => $pago->numero]),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Anticipo/excedente pagado: genera saldo a favor NUESTRO.
     */
    public static function crearMovimientoAnticipo(PagoProveedor $pago, float $monto, int $usuarioId): self
    {
        return static::create([
            'proveedor_id' => $pago->proveedor_id,
            'sucursal_id' => $pago->sucursal_id,
            'fecha' => $pago->fecha,
            'tipo' => self::TIPO_ANTICIPO,
            'saldo_favor_haber' => $monto,
            'documento_tipo' => self::DOC_PAGO,
            'documento_id' => $pago->id,
            'pago_proveedor_id' => $pago->id,
            'concepto' => __('Anticipo a proveedor — OP :numero', ['numero' => $pago->numero]),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Uso de saldo a favor: SOLO consume el saldo (saldo_favor_debe) — la
     * reducción de deuda ya viaja en el DEBE de las aplicaciones por compra,
     * que incluyen los fondos del saldo a favor (si además pusiera debe acá,
     * la deuda bajaría dos veces).
     */
    public static function crearMovimientoUsoSaldoFavor(PagoProveedor $pago, float $monto, int $usuarioId): self
    {
        return static::create([
            'proveedor_id' => $pago->proveedor_id,
            'sucursal_id' => $pago->sucursal_id,
            'fecha' => $pago->fecha,
            'tipo' => self::TIPO_USO_SALDO_FAVOR,
            'debe' => 0,
            'saldo_favor_debe' => $monto,
            'documento_tipo' => self::DOC_PAGO,
            'documento_id' => $pago->id,
            'pago_proveedor_id' => $pago->id,
            'concepto' => __('Saldo a favor aplicado — OP :numero', ['numero' => $pago->numero]),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * NC del proveedor (RF-21): DEBE por lo aplicado contra la compra origen
     * + saldo a favor por el excedente (o NC suelta).
     */
    public static function crearMovimientoNotaCredito(Compra $nc, float $montoAplicado, float $montoAFavor, int $usuarioId): self
    {
        return static::create([
            'proveedor_id' => $nc->proveedor_id,
            'sucursal_id' => $nc->sucursal_id,
            'fecha' => $nc->fecha_comprobante ?? $nc->fecha,
            'tipo' => self::TIPO_NOTA_CREDITO,
            'debe' => $montoAplicado,
            'saldo_favor_haber' => $montoAFavor,
            'documento_tipo' => self::DOC_COMPRA,
            'documento_id' => $nc->id,
            'compra_id' => $nc->id,
            'concepto' => __('Nota de crédito :numero', ['numero' => $nc->numero_comprobante])
                .($nc->numero_comprobante_proveedor ? " ({$nc->numero_comprobante_proveedor})" : ''),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Contraasiento: invierte los montos; AMBOS quedan activos y se cancelan
     * matemáticamente (patrón clientes — nunca se pisa el original).
     */
    public static function crearContraasiento(self $original, string $motivo, int $usuarioId): self
    {
        $tipoAnulacion = match ($original->tipo) {
            self::TIPO_COMPRA, self::TIPO_NOTA_CREDITO => self::TIPO_ANULACION_COMPRA,
            self::TIPO_PAGO, self::TIPO_ANTICIPO, self::TIPO_USO_SALDO_FAVOR => self::TIPO_ANULACION_PAGO,
            default => self::TIPO_AJUSTE_DEBITO,
        };

        $contraasiento = static::create([
            'proveedor_id' => $original->proveedor_id,
            'sucursal_id' => $original->sucursal_id,
            'fecha' => now()->toDateString(),
            'tipo' => $tipoAnulacion,
            'debe' => $original->haber,
            'haber' => $original->debe,
            'saldo_favor_debe' => $original->saldo_favor_haber,
            'saldo_favor_haber' => $original->saldo_favor_debe,
            'documento_tipo' => $original->documento_tipo,
            'documento_id' => $original->documento_id,
            'compra_id' => $original->compra_id,
            'pago_proveedor_id' => $original->pago_proveedor_id,
            'concepto' => __('Anulación').": {$original->concepto}",
            'observaciones' => $motivo,
            'estado' => 'activo',
            'usuario_id' => $usuarioId,
        ]);

        $original->update(['anulado_por_movimiento_id' => $contraasiento->id]);

        return $contraasiento;
    }
}
