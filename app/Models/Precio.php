<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Precio
 *
 * Representa un precio de un artículo en una sucursal específica.
 * Soporta diferentes tipos de precio (público, mayorista, especial)
 * con vigencia temporal y descuentos.
 *
 * @property int $id
 * @property int $articulo_id
 * @property int $sucursal_id
 * @property string $tipo_precio
 * @property float $precio
 * @property float $descuento_porcentaje
 * @property \Carbon\Carbon|null $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Articulo $articulo
 * @property-read Sucursal $sucursal
 */
class Precio extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'precios';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'tipo_precio',
        'precio',
        'descuento_porcentaje',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigentes($query, ?\DateTime $fecha = null)
    {
        $fecha = $fecha ?? now();

        return $query->where('activo', true)
                     ->where(function ($q) use ($fecha) {
                         $q->whereNull('fecha_inicio')
                           ->orWhere('fecha_inicio', '<=', $fecha);
                     })
                     ->where(function ($q) use ($fecha) {
                         $q->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', $fecha);
                     });
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_precio', $tipo);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePublico($query)
    {
        return $query->where('tipo_precio', 'publico');
    }

    public function scopeMayorista($query)
    {
        return $query->where('tipo_precio', 'mayorista');
    }

    public function scopeEspecial($query)
    {
        return $query->where('tipo_precio', 'especial');
    }

    // Métodos auxiliares

    /**
     * Verifica si el precio está vigente en una fecha
     */
    public function estaVigente(?\DateTime $fecha = null): bool
    {
        if (!$this->activo) {
            return false;
        }

        $fecha = $fecha ?? now();

        // Verificar fecha de inicio
        if ($this->fecha_inicio && $this->fecha_inicio > $fecha) {
            return false;
        }

        // Verificar fecha de fin
        if ($this->fecha_fin && $this->fecha_fin < $fecha) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene el precio con descuento aplicado
     */
    public function obtenerPrecioConDescuento(): float
    {
        if ($this->descuento_porcentaje <= 0) {
            return $this->precio;
        }

        $descuento = $this->precio * ($this->descuento_porcentaje / 100);
        return round($this->precio - $descuento, 2);
    }

    /**
     * Obtiene el monto del descuento
     */
    public function obtenerMontoDescuento(): float
    {
        if ($this->descuento_porcentaje <= 0) {
            return 0;
        }

        return round($this->precio * ($this->descuento_porcentaje / 100), 2);
    }

    /**
     * Obtiene el precio final considerando IVA y descuento
     *
     * @return array Con desglose de precio, descuento, IVA
     */
    public function obtenerPrecioFinal(): array
    {
        $articulo = $this->articulo;
        $precioConDescuento = $this->obtenerPrecioConDescuento();

        return $articulo->calcularPrecioFinal($precioConDescuento, 0);
    }

    /**
     * Calcula el precio para una cantidad específica
     */
    public function calcularTotal(float $cantidad, float $descuentoAdicional = 0): array
    {
        $precioUnitario = $this->obtenerPrecioConDescuento();

        // Aplicar descuento adicional si existe
        if ($descuentoAdicional > 0) {
            $precioUnitario -= ($precioUnitario * ($descuentoAdicional / 100));
        }

        $subtotal = $precioUnitario * $cantidad;

        $articulo = $this->articulo;
        $tipoIva = $articulo->tipoIva;

        // Calcular IVA
        if ($articulo->precio_iva_incluido) {
            $subtotalSinIva = $tipoIva->obtenerPrecioSinIva($subtotal, true);
            $subtotalConIva = $subtotal;
        } else {
            $subtotalSinIva = $subtotal;
            $subtotalConIva = $tipoIva->obtenerPrecioConIva($subtotal, false);
        }

        $ivaMonto = $subtotalConIva - $subtotalSinIva;

        return [
            'cantidad' => $cantidad,
            'precio_unitario' => round($this->precio, 2),
            'descuento_porcentaje' => $this->descuento_porcentaje,
            'descuento_adicional' => $descuentoAdicional,
            'precio_con_descuento' => round($precioUnitario, 2),
            'subtotal_sin_iva' => round($subtotalSinIva, 2),
            'iva_porcentaje' => $tipoIva->porcentaje,
            'iva_monto' => round($ivaMonto, 2),
            'total' => round($subtotalConIva, 2),
        ];
    }
}
