<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo PedidoMostradorDetalle
 *
 * Linea de detalle de un pedido por mostrador. Espejo de VentaDetalle.
 */
class PedidoMostradorDetalle extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pedidos_mostrador_detalle';

    protected $fillable = [
        'pedido_mostrador_id',
        'articulo_id',
        'es_concepto',
        'concepto_descripcion',
        'concepto_categoria_id',
        'tipo_iva_id',
        'lista_precio_id',
        'cantidad',
        'precio_unitario',
        'precio_sin_iva',
        'descuento',
        'precio_lista',
        'precio_opcionales',
        'subtotal',
        'ajuste_manual_tipo',
        'ajuste_manual_valor',
        'ajuste_manual_origen',
        'ajuste_manual_aplicado_por',
        'precio_sin_ajuste_manual',
        'pagado_con_puntos',
        'puntos_usados',
        'iva_porcentaje',
        'iva_monto',
        'descuento_porcentaje',
        'descuento_monto',
        'descuento_promocion',
        'descuento_promocion_especial',
        'descuento_cupon',
        'descuento_lista',
        'tiene_promocion',
        'total',
    ];

    protected $casts = [
        'es_concepto' => 'boolean',
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'precio_sin_iva' => 'decimal:2',
        'descuento' => 'decimal:2',
        'precio_lista' => 'decimal:2',
        'precio_opcionales' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'ajuste_manual_valor' => 'decimal:2',
        'precio_sin_ajuste_manual' => 'decimal:2',
        'pagado_con_puntos' => 'boolean',
        'puntos_usados' => 'integer',
        'iva_porcentaje' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'descuento_promocion' => 'decimal:2',
        'descuento_promocion_especial' => 'decimal:2',
        'descuento_cupon' => 'decimal:2',
        'descuento_lista' => 'decimal:2',
        'tiene_promocion' => 'boolean',
        'total' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoMostrador::class, 'pedido_mostrador_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'tipo_iva_id');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function opcionales(): HasMany
    {
        return $this->hasMany(PedidoMostradorDetalleOpcional::class, 'pedido_mostrador_detalle_id');
    }

    public function promocionesAplicadas(): HasMany
    {
        return $this->hasMany(PedidoMostradorDetallePromocion::class, 'pedido_mostrador_detalle_id');
    }
}
