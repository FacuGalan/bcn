<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo Compra
 *
 * Representa una compra realizada en una sucursal.
 * Registra el crédito fiscal de IVA para reportes.
 *
 * @property int $id
 * @property int $sucursal_id
 * @property int $proveedor_id
 * @property int|null $caja_id
 * @property int $usuario_id
 * @property string $numero_comprobante
 * @property \Carbon\Carbon $fecha
 * @property string $tipo_comprobante
 * @property float $subtotal
 * @property float $total
 * @property float $total_iva
 * @property string $forma_pago
 * @property string $estado
 * @property float $saldo_pendiente En una compra: deuda pendiente de pago.
 *                                  En una NC (RF-B11 hardening-circuito-precios): monto REALMENTE
 *                                  aplicado contra el saldo de la compra origen al confirmarla (las
 *                                  consultas de deuda excluyen NCs vía sinNotasCredito()).
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Sucursal $sucursal
 * @property-read Proveedor $proveedor
 * @property-read Caja|null $caja
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|CompraDetalle[] $detalles
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class Compra extends Model
{
    // Estados D11: SOLO ciclo de vida — lo impago se deriva de saldo_pendiente.
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    // Tipos de comprobante (RF-01/RF-06/RF-21). Discriminan IVA: A y M (la M
    // se trata como la A para el crédito; sus retenciones quedan manuales v1).
    public const TIPO_FACTURA_A = 'factura_a';

    public const TIPO_FACTURA_B = 'factura_b';

    public const TIPO_FACTURA_C = 'factura_c';

    public const TIPO_FACTURA_M = 'factura_m';

    public const TIPO_NO_FISCAL = 'no_fiscal';

    public const TIPO_NC_A = 'nota_credito_a';

    public const TIPO_NC_B = 'nota_credito_b';

    public const TIPO_NC_C = 'nota_credito_c';

    public const TIPO_NC_NO_FISCAL = 'nota_credito_no_fiscal';

    public const TIPOS_DISCRIMINAN_IVA = [
        self::TIPO_FACTURA_A,
        self::TIPO_FACTURA_M,
        self::TIPO_NC_A,
    ];

    public const TIPOS_NO_FISCALES = [
        self::TIPO_NO_FISCAL,
        self::TIPO_NC_NO_FISCAL,
    ];

    protected $connection = 'pymes_tenant';

    protected $table = 'compras';

    protected $fillable = [
        'sucursal_id',
        'proveedor_id',
        'compra_origen_id',
        'cuit_id',
        'cuenta_compra_id',
        'caja_id',
        'usuario_id',
        'numero_comprobante',
        'numero_comprobante_proveedor',
        'fecha',
        'fecha_comprobante',
        'fecha_vencimiento',
        'tipo_comprobante',
        'es_servicio',
        'subtotal',
        'neto_gravado',
        'neto_no_gravado',
        'neto_exento',
        'descuento_global_porcentaje',
        'descuento_global_monto',
        'total',
        'total_iva',
        'forma_pago',
        'estado',
        'saldo_pendiente',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_comprobante' => 'date',
        'fecha_vencimiento' => 'date',
        'es_servicio' => 'boolean',
        'subtotal' => 'decimal:2',
        'neto_gravado' => 'decimal:2',
        'neto_no_gravado' => 'decimal:2',
        'neto_exento' => 'decimal:2',
        'descuento_global_porcentaje' => 'decimal:2',
        'descuento_global_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    /**
     * CUIT del comercio al que se imputa fiscalmente la compra (RF-05).
     */
    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class, 'cuit_id')->withTrashed();
    }

    /**
     * Percepciones/retenciones sufridas en la factura del proveedor (RF-05).
     */
    public function percepciones(): HasMany
    {
        return $this->hasMany(CompraPercepcion::class, 'compra_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'compra_id');
    }

    /**
     * Desglose de IVA por alícuota del comprobante (RF-14) — fuente canónica
     * del crédito fiscal y del Libro IVA Compras.
     */
    public function ivas(): HasMany
    {
        return $this->hasMany(CompraIva::class, 'compra_id');
    }

    /**
     * Conceptos de pie de factura (RF-15): flete, impuestos internos, etc.
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(CompraConcepto::class, 'compra_id');
    }

    /**
     * Cuenta de compra para reportes de gastos (RF-22).
     */
    public function cuentaCompra(): BelongsTo
    {
        return $this->belongsTo(CuentaCompra::class, 'cuenta_compra_id');
    }

    /**
     * Compra original de una nota de crédito (RF-21); NULL en compras
     * normales y NC sueltas.
     */
    public function compraOrigen(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_origen_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(Compra::class, 'compra_origen_id');
    }

    // Scopes
    public function scopeBorradores($query)
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', self::ESTADO_COMPLETADA);
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', self::ESTADO_CANCELADA);
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', '!=', self::ESTADO_CANCELADA);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorProveedor($query, int $proveedorId)
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    public function scopePorFecha($query, $desde = null, $hasta = null)
    {
        if ($desde) {
            $query->where('fecha', '>=', $desde);
        }

        if ($hasta) {
            $query->where('fecha', '<=', $hasta);
        }

        return $query;
    }

    public function scopeConSaldoPendiente($query)
    {
        return $query->where('saldo_pendiente', '>', 0);
    }

    /**
     * RF-B11: una NC nunca es deuda a pagar (su saldo_pendiente guarda el
     * monto aplicado contra la origen) — toda consulta de deuda la excluye.
     */
    public function scopeSinNotasCredito($query)
    {
        return $query->where(fn ($q) => $q->whereNull('tipo_comprobante')
            ->orWhere('tipo_comprobante', 'not like', 'nota_credito%'));
    }

    public function scopeCtaCte($query)
    {
        return $query->where('forma_pago', 'cta_cte');
    }

    // Métodos auxiliares

    /**
     * Verifica si la compra está completada
     */
    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Verifica si la compra es un borrador (D11: sin efectos).
     */
    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /**
     * ¿Es una nota de crédito de proveedor? (RF-21)
     */
    public function esNotaCredito(): bool
    {
        return $this->tipo_comprobante !== null
            && str_starts_with($this->tipo_comprobante, 'nota_credito');
    }

    /**
     * ¿Es una factura de servicio? (D23: sin grilla de artículos ni efectos
     * de stock/costos/repricing — el detalle son los conceptos)
     */
    public function esServicio(): bool
    {
        return (bool) $this->es_servicio;
    }

    /**
     * ¿El comprobante es fiscal? (D15: el toggle no fiscal desactiva todo el
     * circuito de impuestos)
     */
    public function esFiscal(): bool
    {
        return $this->tipo_comprobante !== null
            && ! in_array($this->tipo_comprobante, self::TIPOS_NO_FISCALES, true);
    }

    /**
     * ¿El comprobante discrimina IVA? (RF-01: define la base del precio
     * cargado y si puede haber crédito fiscal)
     */
    public function discriminaIva(): bool
    {
        return in_array($this->tipo_comprobante, self::TIPOS_DISCRIMINAN_IVA, true);
    }

    /**
     * Verifica si la compra está cancelada
     */
    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Verifica si tiene saldo pendiente
     */
    public function tieneSaldoPendiente(): bool
    {
        return $this->saldo_pendiente > 0;
    }

    /**
     * Verifica si es compra en cuenta corriente
     */
    public function esCtaCte(): bool
    {
        return $this->forma_pago === 'cta_cte';
    }

    /**
     * Calcula el total de la compra basado en los detalles
     */
    public function calcularTotales(): array
    {
        $subtotal = 0;
        $totalIva = 0;

        foreach ($this->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
            $totalIva += $detalle->iva_monto;
        }

        $total = $subtotal + $totalIva;

        return [
            'subtotal' => round($subtotal, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Actualiza los totales de la compra
     */
    public function actualizarTotales(): bool
    {
        $totales = $this->calcularTotales();

        $this->subtotal = $totales['subtotal'];
        $this->total_iva = $totales['total_iva'];
        $this->total = $totales['total'];

        if (! $this->esCtaCte()) {
            $this->saldo_pendiente = 0;
        } else {
            $this->saldo_pendiente = $this->total;
        }

        return $this->save();
    }

    /**
     * Registra un pago
     */
    public function registrarPago(float $monto): bool
    {
        if ($monto <= 0 || $monto > $this->saldo_pendiente) {
            return false;
        }

        $this->saldo_pendiente -= $monto;

        if ($this->saldo_pendiente == 0 && $this->estado === 'pendiente') {
            $this->estado = 'completada';
        }

        return $this->save();
    }

    /**
     * Cancela la compra
     */
    public function cancelar(): bool
    {
        if ($this->estaCancelada()) {
            return false;
        }

        $this->estado = 'cancelada';

        return $this->save();
    }

    /**
     * Verifica si es una transferencia interna entre sucursales
     */
    public function esTransferenciaInterna(): bool
    {
        return $this->proveedor && $this->proveedor->esSucursalInterna();
    }
}
