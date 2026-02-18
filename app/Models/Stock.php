<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Stock
 *
 * Representa el inventario de un artículo en una sucursal específica.
 * Cada artículo tiene un registro de stock independiente por sucursal.
 *
 * @property int $id
 * @property int $articulo_id
 * @property int $sucursal_id
 * @property float $cantidad
 * @property float|null $cantidad_minima
 * @property float|null $cantidad_maxima
 * @property \Carbon\Carbon|null $ultima_actualizacion
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Articulo $articulo
 * @property-read Sucursal $sucursal
 */
class Stock extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'stock';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'cantidad',
        'cantidad_minima',
        'cantidad_maxima',
        'ultima_actualizacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'cantidad_minima' => 'decimal:2',
        'cantidad_maxima' => 'decimal:2',
        'ultima_actualizacion' => 'datetime',
    ];

    // Relaciones
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id')->withTrashed();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class, 'articulo_id', 'articulo_id')
                     ->where('sucursal_id', $this->sucursal_id);
    }

    // Scopes
    public function scopeBajoMinimo($query)
    {
        return $query->whereNotNull('cantidad_minima')
                     ->whereColumn('cantidad', '<', 'cantidad_minima');
    }

    public function scopeSobreMaximo($query)
    {
        return $query->whereNotNull('cantidad_maxima')
                     ->whereColumn('cantidad', '>', 'cantidad_maxima');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorArticulo($query, int $articuloId)
    {
        return $query->where('articulo_id', $articuloId);
    }

    public function scopeConExistencia($query)
    {
        return $query->where('cantidad', '>', 0);
    }

    // Métodos auxiliares

    /**
     * Verifica si el stock está por debajo del mínimo
     */
    public function estaBajoMinimo(): bool
    {
        if (is_null($this->cantidad_minima)) {
            return false;
        }

        return $this->cantidad < $this->cantidad_minima;
    }

    /**
     * Verifica si el stock está por encima del máximo
     */
    public function estaSobreMaximo(): bool
    {
        if (is_null($this->cantidad_maxima)) {
            return false;
        }

        return $this->cantidad > $this->cantidad_maxima;
    }

    /**
     * Verifica si necesita reposición
     */
    public function necesitaReposicion(): bool
    {
        return $this->estaBajoMinimo();
    }

    /**
     * Ajusta el stock (aumenta o disminuye)
     *
     * @param float $cantidad Cantidad a ajustar (positivo aumenta, negativo disminuye)
     * @return bool True si el ajuste fue exitoso
     */
    public function ajustarStock(float $cantidad): bool
    {
        $nuevaCantidad = $this->cantidad + $cantidad;

        // No permitir stock negativo
        if ($nuevaCantidad < 0) {
            return false;
        }

        $this->cantidad = $nuevaCantidad;
        $this->ultima_actualizacion = now();

        return $this->save();
    }

    /**
     * Aumenta el stock
     */
    public function aumentar(float $cantidad): bool
    {
        return $this->ajustarStock(abs($cantidad));
    }

    /**
     * Disminuye el stock
     *
     * @param float $cantidad Cantidad a disminuir
     * @param bool $permitirNegativo Si true, permite que el stock quede negativo
     */
    public function disminuir(float $cantidad, bool $permitirNegativo = false): bool
    {
        if ($permitirNegativo) {
            $this->cantidad = $this->cantidad - abs($cantidad);
            $this->ultima_actualizacion = now();
            return $this->save();
        }

        return $this->ajustarStock(-abs($cantidad));
    }

    /**
     * Verifica si hay stock suficiente para una cantidad dada
     */
    public function haySuficiente(float $cantidad): bool
    {
        return $this->cantidad >= $cantidad;
    }

    /**
     * Obtiene la cantidad disponible para venta
     */
    public function getCantidadDisponible(): float
    {
        return max(0, $this->cantidad);
    }

    /**
     * Recalcula el stock desde los movimientos activos
     * Útil para reconciliar el cache (tabla stock) vs historial (movimientos_stock)
     */
    public function recalcularDesdeMovimientos(): float
    {
        $stockCalculado = MovimientoStock::calcularStock($this->articulo_id, $this->sucursal_id);

        $this->cantidad = $stockCalculado;
        $this->ultima_actualizacion = now();
        $this->save();

        return $stockCalculado;
    }
}
