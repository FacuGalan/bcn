<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo Venta
 *
 * Representa una venta realizada en una sucursal.
 *
 * @property int $id
 * @property string $numero
 * @property int $sucursal_id
 * @property int|null $cliente_id
 * @property int|null $caja_id
 * @property int|null $canal_venta_id
 * @property int|null $forma_venta_id
 * @property int|null $lista_precio_id
 * @property int|null $punto_venta_id
 * @property int|null $forma_pago_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $fecha
 * @property float $subtotal
 * @property float $iva
 * @property float $descuento
 * @property float $total
 * @property float $ajuste_forma_pago
 * @property float $total_final
 * @property string $estado
 * @property bool $es_cuenta_corriente
 * @property float $saldo_pendiente_cache
 * @property \Carbon\Carbon|null $fecha_vencimiento
 * @property float $monto_fiscal_cache
 * @property float $monto_no_fiscal_cache
 * @property int|null $anulado_por_usuario_id
 * @property \Carbon\Carbon|null $anulado_at
 * @property string|null $motivo_anulacion
 * @property string|null $observaciones
 * @property int|null $cierre_turno_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Sucursal $sucursal
 * @property-read CierreTurno|null $cierreTurno
 * @property-read Cliente|null $cliente
 * @property-read Caja|null $caja
 * @property-read FormaPago|null $formaPago
 * @property-read User $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $detalles
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaPago[] $pagos
 * @property-read MovimientoCaja|null $movimientoCaja
 */
class Venta extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'ventas';

    protected $fillable = [
        'numero',
        'sucursal_id',
        'cliente_id',
        'caja_id',
        'canal_venta_id',
        'forma_venta_id',
        'lista_precio_id',
        'punto_venta_id',
        'forma_pago_id',        // FK a formas_pago (para mixtas el detalle está en venta_pagos)
        'usuario_id',
        'fecha',
        'subtotal',
        'iva',
        'descuento',
        'total',
        'ajuste_forma_pago',      // Suma de ajustes (recargos/descuentos) de formas de pago
        'total_final',
        // Invitaciones (cortesias)
        'es_invitacion_total',
        'invitacion_motivo',
        'invitado_por_usuario_id',
        'invitado_at',
        'total_invitado',
        'estado',
        'es_cuenta_corriente',
        'saldo_pendiente_cache',
        'fecha_vencimiento',
        'monto_fiscal_cache',
        'monto_no_fiscal_cache',
        'anulado_por_usuario_id',
        'anulado_at',
        'motivo_anulacion',
        'observaciones',
        // Campos de descuento general
        'descuento_general_tipo',
        'descuento_general_valor',
        'descuento_general_monto',
        'descuento_general_aplicado_por',  // Auditoria: user.id que aplico el descuento general
        // Campos de cupones y puntos
        'cupon_id',
        'monto_cupon',
        'puntos_ganados',
        'puntos_usados',
        'puntos_canjeados_pago',       // Puntos usados como medio de pago en la venta
        'puntos_canjeados_articulos',  // Puntos usados en canje directo de articulos en la venta
        'puntos_usados_monto',         // Monto en pesos del canje de puntos como pago (canje monto)
        'articulos_canjeados_monto',   // Monto en pesos de artículos pagados directamente con puntos (canje artículo)
        'cierre_turno_id',
        // Snapshots para reconstruir la venta aunque cliente/cupón cambien o se borren
        'cliente_nombre_snapshot',
        'cliente_cuit_snapshot',
        'cliente_condicion_iva_snapshot',
        'cupon_codigo_snapshot',
        'cupon_descripcion_snapshot',
        // Origen polimórfico (D20): morph al pedido que generó la venta
        // ('PedidoMostrador'/'PedidoDelivery' vía morphMap; NULL = venta directa POS)
        'origen_type',
        'origen_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'anulado_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'ajuste_forma_pago' => 'decimal:2',
        'total_final' => 'decimal:2',
        'es_invitacion_total' => 'boolean',
        'invitado_at' => 'datetime',
        'total_invitado' => 'decimal:2',
        'saldo_pendiente_cache' => 'decimal:2',
        'monto_fiscal_cache' => 'decimal:2',
        'monto_no_fiscal_cache' => 'decimal:2',
        'es_cuenta_corriente' => 'boolean',
        'descuento_general_valor' => 'decimal:2',
        'descuento_general_monto' => 'decimal:2',
        'monto_cupon' => 'decimal:2',
        'puntos_ganados' => 'integer',
        'puntos_usados' => 'integer',
        'puntos_canjeados_pago' => 'integer',
        'puntos_canjeados_articulos' => 'integer',
        'puntos_usados_monto' => 'decimal:2',
        'articulos_canjeados_monto' => 'decimal:2',
    ];

    // Relaciones

    /**
     * Pedido que originó esta venta (D20): PedidoMostrador o PedidoDelivery
     * vía morphMap. NULL = venta directa de POS. Lo setean las conversiones
     * (convertirEnVenta de ambos services).
     */
    public function origen(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('origen');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id')->withTrashed();
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function canalVenta(): BelongsTo
    {
        return $this->belongsTo(CanalVenta::class, 'canal_venta_id');
    }

    public function formaVenta(): BelongsTo
    {
        return $this->belongsTo(FormaVenta::class, 'forma_venta_id');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_id');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_usuario_id');
    }

    /**
     * Usuario que autorizó la cortesía total (cuando es_invitacion_total=true).
     * Para invitaciones parciales, ver la relación equivalente en VentaDetalle.
     */
    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por_usuario_id');
    }

    public function cierreTurno(): BelongsTo
    {
        return $this->belongsTo(CierreTurno::class, 'cierre_turno_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    public function promociones(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id');
    }

    public function promocionesEspeciales(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id')
            ->where('tipo_promocion', 'promocion_especial');
    }

    public function promocionesComunes(): HasMany
    {
        return $this->hasMany(VentaPromocion::class, 'venta_id')
            ->where('tipo_promocion', 'promocion');
    }

    public function comprobantesFiscales(): BelongsToMany
    {
        return $this->belongsToMany(ComprobanteFiscal::class, 'comprobante_fiscal_ventas')
            ->withPivot(['monto', 'es_anulacion', 'created_at']);
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function movimientosPuntos(): HasMany
    {
        return $this->hasMany(MovimientoPunto::class);
    }

    // Scopes
    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'cancelada');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
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
        return $query->where('saldo_pendiente_cache', '>', 0);
    }

    public function scopeCtaCte($query)
    {
        return $query->where('es_cuenta_corriente', true);
    }

    // Métodos auxiliares

    /**
     * Verifica si la venta está completada
     */
    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Verifica si la venta está pendiente
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Verifica si la venta está cancelada
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
        return $this->saldo_pendiente_cache > 0;
    }

    /**
     * Indica si la venta tiene algún pago cobrado por una integración de pago
     * (QR MercadoPago) ya confirmada en el proveedor. Mientras no exista refund
     * real, una venta así no puede anularse ni modificarse: la plata ya entró.
     */
    public function tieneIntegracionPagoConfirmada(): bool
    {
        return $this->pagos()
            ->whereNotNull('integracion_pago_transaccion_id')
            ->whereHas('integracionTransaccion', function ($q) {
                $q->confirmadas();
            })
            ->exists();
    }

    /**
     * Verifica si es venta en cuenta corriente
     */
    public function esCtaCte(): bool
    {
        return $this->es_cuenta_corriente;
    }

    /**
     * Calcula el total de la venta basado en los detalles
     */
    public function calcularTotales(): array
    {
        $subtotal = 0;
        $totalIva = 0;

        foreach ($this->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
            $totalIva += $detalle->iva_monto ?? 0;
        }

        $total = $subtotal + $totalIva - $this->descuento;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($this->descuento, 2),
            'iva' => round($totalIva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Actualiza los totales de la venta
     */
    public function actualizarTotales(): bool
    {
        $totales = $this->calcularTotales();

        $this->subtotal = $totales['subtotal'];
        $this->iva = $totales['iva'];
        $this->total = $totales['total'];

        if (! $this->esCtaCte()) {
            $this->saldo_pendiente_cache = 0;
        } else {
            $this->saldo_pendiente_cache = $this->total;
        }

        return $this->save();
    }

    /**
     * Registra un pago
     */
    public function registrarPago(float $monto): bool
    {
        if ($monto <= 0 || $monto > $this->saldo_pendiente_cache) {
            return false;
        }

        $this->saldo_pendiente_cache -= $monto;

        if ($this->saldo_pendiente_cache == 0 && $this->estado === 'pendiente') {
            $this->estado = 'completada';
        }

        return $this->save();
    }

    /**
     * Cancela la venta
     */
    public function cancelar(): bool
    {
        if ($this->estaCancelada()) {
            return false;
        }

        $this->estado = 'cancelada';

        return $this->save();
    }
}
