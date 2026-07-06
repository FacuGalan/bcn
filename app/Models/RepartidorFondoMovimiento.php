<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo RepartidorFondoMovimiento (RF-09, append-only)
 *
 * Movimiento del fondo del repartidor. APPEND-ONLY: nunca se actualiza ni
 * borra — correcciones via movimiento de tipo `ajuste` con signo inverso
 * (patron MovimientoStock/MovimientoCuentaCorriente). `monto` lleva signo:
 * positivo entra al fondo (entrega inicial, refuerzo, cobro de pedido),
 * negativo sale (vuelto dado, liquidacion de envios de terceros, rendicion).
 */
class RepartidorFondoMovimiento extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'repartidor_fondo_movimientos';

    public const UPDATED_AT = null;

    public const TIPO_ENTREGA_INICIAL = 'entrega_inicial';

    public const TIPO_REFUERZO = 'refuerzo';

    public const TIPO_COBRO_PEDIDO = 'cobro_pedido';

    public const TIPO_VUELTO = 'vuelto';

    public const TIPO_LIQUIDACION_ENVIOS = 'liquidacion_envios';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_RENDICION = 'rendicion';

    public const TIPO_AJUSTE = 'ajuste';

    public const TIPOS = [
        self::TIPO_ENTREGA_INICIAL => 'Entrega inicial',
        self::TIPO_REFUERZO => 'Refuerzo',
        self::TIPO_COBRO_PEDIDO => 'Cobro de pedido',
        self::TIPO_VUELTO => 'Vuelto',
        self::TIPO_LIQUIDACION_ENVIOS => 'Liquidación de envíos',
        self::TIPO_DEVOLUCION => 'Devolución a caja',
        self::TIPO_RENDICION => 'Rendición',
        self::TIPO_AJUSTE => 'Ajuste',
    ];

    protected $fillable = [
        'fondo_id',
        'tipo',
        'monto',
        'pedido_id',
        'movimiento_caja_id',
        'usuario_id',
        'detalle',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function fondo(): BelongsTo
    {
        return $this->belongsTo(RepartidorFondo::class, 'fondo_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoDelivery::class, 'pedido_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopePorFondo(Builder $query, int $fondoId): Builder
    {
        return $query->where('fondo_id', $fondoId);
    }
}
