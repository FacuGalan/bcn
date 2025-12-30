<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Categoria
 *
 * Representa una categoría para clasificar artículos.
 * Las categorías permiten organizar el catálogo de productos y aplicar
 * promociones o reglas de negocio a nivel de categoría.
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property string $nombre Nombre de la categoría
 * @property string|null $codigo Código único de la categoría
 * @property string|null $descripcion Descripción detallada
 * @property string|null $color Color para identificación visual (formato hex: #RRGGBB)
 * @property bool $activo Si la categoría está activa
 * @property int|null $tipo_iva_id Tipo de IVA por defecto para conceptos de esta categoría
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read TipoIva|null $tipoIva
 * @property-read \Illuminate\Database\Eloquent\Collection|Articulo[] $articulos
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $promocionesCondiciones
 */
class Categoria extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'color',
        'icono',
        'activo',
        'tipo_iva_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Tipo de IVA por defecto para conceptos de esta categoría
     */
    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'tipo_iva_id');
    }

    /**
     * Artículos que pertenecen a esta categoría
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class, 'categoria_id');
    }

    /**
     * Condiciones de promociones que aplican a esta categoría
     */
    public function promocionesCondiciones(): HasMany
    {
        return $this->hasMany(PromocionCondicion::class, 'categoria_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo categorías activas
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
     * Scope: Buscar por nombre (búsqueda parcial)
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'like', "%{$nombre}%");
    }

    /**
     * Scope: Ordenar por nombre
     */
    public function scopeOrdenadoPorNombre($query)
    {
        return $query->orderBy('nombre');
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Verifica si la categoría tiene artículos asociados
     */
    public function tieneArticulos(): bool
    {
        return $this->articulos()->exists();
    }

    /**
     * Cuenta la cantidad de artículos activos en esta categoría
     */
    public function contarArticulosActivos(): int
    {
        return $this->articulos()->where('activo', true)->count();
    }

    /**
     * Obtiene la información de IVA de la categoría
     * Si no tiene tipo_iva_id asignado, devuelve 21% (código 5) por defecto
     *
     * @return array ['codigo' => int, 'porcentaje' => float, 'nombre' => string]
     */
    public function obtenerInfoIva(): array
    {
        if ($this->tipo_iva_id && $this->tipoIva) {
            return [
                'codigo' => $this->tipoIva->codigo,
                'porcentaje' => (float) $this->tipoIva->porcentaje,
                'nombre' => $this->tipoIva->nombre,
            ];
        }

        // Por defecto: IVA 21% (código 5 de AFIP)
        return [
            'codigo' => 5,
            'porcentaje' => 21.0,
            'nombre' => 'IVA 21%',
        ];
    }

    /**
     * Obtiene todas las promociones activas que aplican a esta categoría
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
