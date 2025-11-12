<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Articulo
 *
 * Representa un artículo en el catálogo maestro del comercio.
 * Los artículos pueden tener diferentes precios e IVA, y pueden estar
 * disponibles de forma selectiva en diferentes sucursales.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $categoria
 * @property string|null $marca
 * @property string $unidad_medida
 * @property bool $es_servicio
 * @property bool $controla_stock
 * @property bool $activo
 * @property int $tipo_iva_id
 * @property bool $precio_iva_incluido
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read TipoIva $tipoIva
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|Stock[] $stocks
 * @property-read \Illuminate\Database\Eloquent\Collection|Precio[] $precios
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $ventasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|CompraDetalle[] $comprasDetalle
 */
class Articulo extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'articulos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'categoria',
        'marca',
        'unidad_medida',
        'es_servicio',
        'controla_stock',
        'activo',
        'tipo_iva_id',
        'precio_iva_incluido',
    ];

    protected $casts = [
        'es_servicio' => 'boolean',
        'controla_stock' => 'boolean',
        'activo' => 'boolean',
        'precio_iva_incluido' => 'boolean',
    ];

    // Relaciones
    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'tipo_iva_id');
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'articulos_sucursales', 'articulo_id', 'sucursal_id')
                    ->withPivot('activo')
                    ->withTimestamps();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'articulo_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'articulo_id');
    }

    public function ventasDetalle(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'articulo_id');
    }

    public function comprasDetalle(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'articulo_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'like', "%{$nombre}%");
    }

    public function scopePorCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopeConStock($query)
    {
        return $query->where('controla_stock', true);
    }

    public function scopeServicios($query)
    {
        return $query->where('es_servicio', true);
    }

    // Métodos auxiliares

    /**
     * Verifica si el artículo está disponible en una sucursal
     */
    public function estaDisponibleEnSucursal(int $sucursalId): bool
    {
        return $this->sucursales()
                    ->where('sucursal_id', $sucursalId)
                    ->wherePivot('activo', true)
                    ->exists();
    }

    /**
     * Obtiene el precio del artículo para una sucursal y tipo de precio específicos
     */
    public function obtenerPrecio(int $sucursalId, string $tipoPrecio = 'publico', bool $aplicarDescuento = true): ?Precio
    {
        $query = $this->precios()
                      ->where('sucursal_id', $sucursalId)
                      ->where('tipo_precio', $tipoPrecio)
                      ->where('activo', true)
                      ->where(function ($q) {
                          $q->whereNull('fecha_inicio')
                            ->orWhere('fecha_inicio', '<=', now());
                      })
                      ->where(function ($q) {
                          $q->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now());
                      });

        return $query->first();
    }

    /**
     * Calcula el precio final considerando IVA y descuento
     */
    public function calcularPrecioFinal(float $precioBase, float $descuentoPorcentaje = 0): array
    {
        $tipoIva = $this->tipoIva;

        // Precio sin descuento
        $precioSinDescuento = $precioBase;

        // Aplicar descuento
        $montoDescuento = $precioBase * ($descuentoPorcentaje / 100);
        $precioConDescuento = $precioBase - $montoDescuento;

        // Calcular precios con/sin IVA según configuración
        if ($this->precio_iva_incluido) {
            $precioConIva = $precioConDescuento;
            $precioSinIva = $tipoIva->obtenerPrecioSinIva($precioConDescuento, true);
        } else {
            $precioSinIva = $precioConDescuento;
            $precioConIva = $tipoIva->obtenerPrecioConIva($precioConDescuento, false);
        }

        $ivaMonto = $precioConIva - $precioSinIva;

        return [
            'precio_base' => round($precioBase, 2),
            'descuento_porcentaje' => $descuentoPorcentaje,
            'descuento_monto' => round($montoDescuento, 2),
            'precio_sin_iva' => round($precioSinIva, 2),
            'iva_porcentaje' => $tipoIva->porcentaje,
            'iva_monto' => round($ivaMonto, 2),
            'precio_final' => round($precioConIva, 2),
        ];
    }

    /**
     * Obtiene el stock disponible en una sucursal
     */
    public function getStockEnSucursal(int $sucursalId): ?Stock
    {
        return $this->stocks()->where('sucursal_id', $sucursalId)->first();
    }

    /**
     * Verifica si hay stock suficiente en una sucursal
     */
    public function tieneStockSuficiente(int $sucursalId, float $cantidad): bool
    {
        if (!$this->controla_stock) {
            return true; // Si no controla stock, siempre hay suficiente
        }

        $stock = $this->getStockEnSucursal($sucursalId);
        return $stock && $stock->cantidad >= $cantidad;
    }
}
