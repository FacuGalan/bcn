<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo VentaDetalle
 *
 * Representa un ítem de una venta con su cálculo de IVA y descuentos.
 *
 * @property int $id
 * @property int $venta_id
 * @property int $articulo_id
 * @property int $tipo_iva_id
 * @property float $cantidad
 * @property float $precio_unitario
 * @property float $iva_porcentaje
 * @property float $precio_sin_iva
 * @property float $descuento
 * @property float $iva_monto
 * @property float $subtotal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Venta $venta
 * @property-read Articulo $articulo
 * @property-read TipoIva $tipoIva
 */
class VentaDetalle extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'ventas_detalle';

    protected $fillable = [
        'venta_id',
        'articulo_id',
        'tipo_iva_id',
        'lista_precio_id',        // Lista de precios usada para calcular el precio
        'cantidad',
        'precio_unitario',
        'precio_lista',           // Precio original de lista (antes de promociones)
        'iva_porcentaje',
        'precio_sin_iva',
        'descuento',
        'descuento_promocion',    // Descuento aplicado por promociones
        'tiene_promocion',        // Si tiene promoción aplicada
        'iva_monto',
        'subtotal',
        'total',                  // Total del ítem
        // Campos de ajuste manual de precio
        'ajuste_manual_tipo',
        'ajuste_manual_valor',
        'precio_sin_ajuste_manual',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_lista' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'precio_sin_iva' => 'decimal:2',
        'descuento' => 'decimal:2',
        'descuento_promocion' => 'decimal:2',
        'tiene_promocion' => 'boolean',
        'iva_monto' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        // Campos de ajuste manual
        'ajuste_manual_valor' => 'decimal:2',
        'precio_sin_ajuste_manual' => 'decimal:2',
    ];

    // Relaciones
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
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

    public function promocionesAplicadas(): HasMany
    {
        return $this->hasMany(VentaDetallePromocion::class, 'venta_detalle_id');
    }

    /**
     * Obtiene el nombre de la primera promoción aplicada
     */
    public function getNombrePromocionAttribute(): ?string
    {
        $promo = $this->promocionesAplicadas->first();
        return $promo?->descripcion_promocion;
    }

    // Métodos auxiliares

    /**
     * Calcula los importes del detalle
     */
    public function calcularImportes(): array
    {
        $precioConDescuento = $this->precio_unitario - $this->descuento;
        $subtotalSinIva = $precioConDescuento * $this->cantidad;

        // Calcular IVA
        if ($this->iva_porcentaje > 0) {
            $ivaMonto = $subtotalSinIva * ($this->iva_porcentaje / 100);
            $subtotal = $subtotalSinIva + $ivaMonto;
        } else {
            $ivaMonto = 0;
            $subtotal = $subtotalSinIva;
        }

        return [
            'precio_unitario' => round($this->precio_unitario, 2),
            'descuento' => round($this->descuento, 2),
            'precio_sin_iva' => round($precioConDescuento, 2),
            'cantidad' => $this->cantidad,
            'subtotal_sin_iva' => round($subtotalSinIva, 2),
            'iva_porcentaje' => $this->iva_porcentaje,
            'iva_monto' => round($ivaMonto, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * Actualiza los cálculos del detalle
     */
    public function actualizarCalculos(): bool
    {
        $importes = $this->calcularImportes();

        $this->precio_sin_iva = $importes['precio_sin_iva'];
        $this->iva_monto = $importes['iva_monto'];
        $this->subtotal = $importes['subtotal'];

        return $this->save();
    }

    /**
     * Obtiene el precio total del ítem (cantidad * precio)
     */
    public function obtenerTotal(): float
    {
        return round($this->subtotal, 2);
    }

    /**
     * Obtiene el precio unitario con IVA
     */
    public function obtenerPrecioUnitarioConIva(): float
    {
        return round($this->precio_unitario, 2);
    }

    /**
     * Obtiene el precio unitario sin IVA
     */
    public function obtenerPrecioUnitarioSinIva(): float
    {
        return round($this->precio_sin_iva, 2);
    }
}
