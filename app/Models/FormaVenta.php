<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo FormaVenta
 *
 * Define las diferentes formas de venta disponibles en el sistema.
 * Ejemplos: Local (consumo en el lugar), Delivery (entrega a domicilio),
 * Take Away (para llevar), etc.
 *
 * Las formas de venta pueden afectar el precio de los artículos y
 * pueden tener promociones específicas asociadas.
 *
 * FASE 2 - Sistema de Listas de Precios
 *
 * @property int $id
 * @property string $nombre Nombre de la forma de venta (ej: "Local", "Delivery")
 * @property string|null $codigo Código único de la forma de venta
 * @property string|null $descripcion Descripción detallada
 * @property bool $activo Si la forma de venta está activa
 * @property int $orden Orden de visualización
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|ListaPrecioCondicion[] $listaPrecioCondiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $promocionesCondiciones
 */
class FormaVenta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'formas_venta';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Relaciones ====================

    /**
     * Condiciones de listas de precios que utilizan esta forma de venta
     */
    public function listaPrecioCondiciones(): HasMany
    {
        return $this->hasMany(ListaPrecioCondicion::class, 'forma_venta_id');
    }

    /**
     * Condiciones de promociones que aplican a esta forma de venta
     */
    public function promocionesCondiciones(): HasMany
    {
        return $this->hasMany(PromocionCondicion::class, 'forma_venta_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo formas de venta activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Buscar por código
     */
    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    /**
     * Scope: Ordenar por orden de visualización
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Verifica si esta forma de venta tiene listas de precios asociadas
     */
    public function tieneListasAsociadas(): bool
    {
        return $this->listaPrecioCondiciones()->exists();
    }

    /**
     * Cuenta cuántas listas de precios usan esta forma de venta
     */
    public function contarListasAsociadas(): int
    {
        return $this->listaPrecioCondiciones()
                    ->distinct('lista_precio_id')
                    ->count('lista_precio_id');
    }

    /**
     * Obtiene promociones activas para esta forma de venta
     */
    public function obtenerPromocionesActivas()
    {
        return $this->promocionesCondiciones()
                    ->whereHas('promocion', function ($query) {
                        $query->where('activo', true)
                              ->where(function ($q) {
                                  $q->whereNull('vigencia_desde')
                                    ->orWhere('vigencia_desde', '<=', now());
                              })
                              ->where(function ($q) {
                                  $q->whereNull('vigencia_hasta')
                                    ->orWhere('vigencia_hasta', '>=', now());
                              });
                    })
                    ->with('promocion')
                    ->get()
                    ->pluck('promocion')
                    ->unique('id');
    }
}
