<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo Sucursal
 *
 * Representa una sucursal de un comercio.
 *
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property bool $es_principal
 * @property int|null $datos_fiscales_id
 * @property bool $activa
 * @property array|null $configuracion
 */
class Sucursal extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'codigo', 'direccion', 'telefono', 'email',
        'es_principal', 'datos_fiscales_id', 'activa', 'configuracion',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activa' => 'boolean',
        'configuracion' => 'array',
    ];

    // Relaciones
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'sucursal_id');
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class, 'sucursal_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'sucursal_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'sucursal_id');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'articulos_sucursales', 'sucursal_id', 'articulo_id')
                    ->withPivot('activo')
                    ->withTimestamps();
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'clientes_sucursales', 'sucursal_id', 'cliente_id')
                    ->withPivot('lista_precio_id', 'descuento_porcentaje', 'limite_credito', 'saldo_actual', 'activo')
                    ->withTimestamps();
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }

    // Métodos auxiliares
    public function tieneArticulo(int $articuloId): bool
    {
        return $this->articulos()->where('articulo_id', $articuloId)->wherePivot('activo', true)->exists();
    }

    public function getStock(int $articuloId): ?Stock
    {
        return $this->stocks()->where('articulo_id', $articuloId)->first();
    }

    public function tieneStockDisponible(int $articuloId, float $cantidad): bool
    {
        $stock = $this->getStock($articuloId);
        return $stock && $stock->cantidad >= $cantidad;
    }
}
