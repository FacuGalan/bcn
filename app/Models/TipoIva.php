<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo TipoIva
 *
 * Representa los tipos de IVA según códigos de AFIP.
 *
 * @property int $id
 * @property int $codigo Código AFIP (3=Exento, 4=10.5%, 5=21%)
 * @property string $nombre Descripción del tipo de IVA
 * @property float $porcentaje Porcentaje de IVA
 * @property bool $activo Si está activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Articulo[] $articulos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\VentaDetalle[] $ventasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CompraDetalle[] $comprasDetalle
 */
class TipoIva extends Model
{
    /**
     * Conexión a base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'tipos_iva';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'porcentaje',
        'activo',
    ];

    /**
     * Conversión de tipos
     */
    protected $casts = [
        'codigo' => 'integer',
        'porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    /**
     * Relación: Artículos con este tipo de IVA
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class, 'tipo_iva_id');
    }

    /**
     * Relación: Ventas detalle con este tipo de IVA
     */
    public function ventasDetalle(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'tipo_iva_id');
    }

    /**
     * Relación: Compras detalle con este tipo de IVA
     */
    public function comprasDetalle(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'tipo_iva_id');
    }

    /**
     * Scope: Solo tipos de IVA activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Buscar por código
     */
    public function scopePorCodigo($query, int $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    /**
     * Calcula el monto de IVA para un precio dado
     *
     * @param float $precio
     * @param bool $precioIncluyeIva
     * @return float
     */
    public function calcularIva(float $precio, bool $precioIncluyeIva = true): float
    {
        if ($this->porcentaje == 0) {
            return 0;
        }

        if ($precioIncluyeIva) {
            // IVA incluido: precio / (1 + porcentaje/100) * porcentaje/100
            return $precio - ($precio / (1 + $this->porcentaje / 100));
        } else {
            // IVA no incluido: precio * porcentaje/100
            return $precio * ($this->porcentaje / 100);
        }
    }

    /**
     * Obtiene el precio sin IVA
     *
     * @param float $precio
     * @param bool $precioIncluyeIva
     * @return float
     */
    public function obtenerPrecioSinIva(float $precio, bool $precioIncluyeIva = true): float
    {
        if ($this->porcentaje == 0 || !$precioIncluyeIva) {
            return $precio;
        }

        return $precio / (1 + $this->porcentaje / 100);
    }

    /**
     * Obtiene el precio con IVA
     *
     * @param float $precio
     * @param bool $precioIncluyeIva
     * @return float
     */
    public function obtenerPrecioConIva(float $precio, bool $precioIncluyeIva = false): float
    {
        if ($this->porcentaje == 0 || $precioIncluyeIva) {
            return $precio;
        }

        return $precio * (1 + $this->porcentaje / 100);
    }
}
