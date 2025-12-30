<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Modelo para Items del Menú
 *
 * Representa un item del menú de navegación con soporte para estructura jerárquica.
 * Cada item puede ser padre (agrupa otros items) o hijo (navega a una ruta/componente).
 *
 * Estructura:
 * - Padre (route_type=none): Solo despliega hijos, no navega
 * - Hijo (route_type=route|component): Navega a una ruta o renderiza componente
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $icono
 * @property string $route_type
 * @property string|null $route_value
 * @property int $orden
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read MenuItem|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|MenuItem[] $children
 * @property-read int $children_count
 * @property-read int $nivel
 * @property-read bool $is_parent
 *
 * @package App\Models
 * @version 1.0.0
 */
class MenuItem extends Model
{
    /**
     * La conexión de base de datos a usar
     * Se usa 'pymes' para tablas compartidas (sin prefijo)
     *
     * @var string
     */
    protected $connection = 'pymes';

    /**
     * Los atributos que se pueden asignar masivamente
     *
     * @var array<string>
     */
    protected $fillable = [
        'parent_id',
        'nombre',
        'slug',
        'icono',
        'route_type',
        'route_value',
        'orden',
        'activo',
    ];

    /**
     * Los atributos que deben ser casteados
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Relación: Item padre
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Relación: Items hijos
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('activo', true)
            ->orderBy('orden');
    }

    /**
     * Relación: Todos los items hijos (incluyendo inactivos)
     *
     * @return HasMany
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->orderBy('orden');
    }

    /**
     * Scope: Solo items raíz (sin padre)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')
            ->where('activo', true)
            ->orderBy('orden');
    }

    /**
     * Scope: Solo items activos
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Verifica si el item es un padre (tiene hijos)
     *
     * @return bool
     */
    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Verifica si el item es una hoja (no tiene hijos)
     *
     * @return bool
     */
    public function isLeaf(): bool
    {
        return !$this->isParent();
    }

    /**
     * Verifica si el item es raíz (no tiene padre)
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Obtiene el nivel de profundidad del item en el árbol
     * Nivel 0 = raíz, Nivel 1 = hijo de raíz, etc.
     *
     * @return int
     */
    public function getNivel(): int
    {
        $nivel = 0;
        $current = $this;

        while ($current->parent_id) {
            $nivel++;
            $current = $current->parent;
        }

        return $nivel;
    }

    /**
     * Obtiene la ruta completa del slug (incluyendo ancestros)
     * Ejemplo: "ventas" → "ventas", "nueva-venta" → "ventas.nueva-venta"
     *
     * @return string
     */
    public function getFullSlug(): string
    {
        $slugs = [$this->slug];
        $current = $this;

        while ($current->parent_id) {
            $current = $current->parent;
            array_unshift($slugs, $current->slug);
        }

        return implode('.', $slugs);
    }

    /**
     * Obtiene todos los ancestros del item (padres, abuelos, etc.)
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $current = $this;

        while ($current->parent_id) {
            $current = $current->parent;
            $ancestors->prepend($current);
        }

        return $ancestors;
    }

    /**
     * Obtiene todos los descendientes del item (hijos, nietos, etc.)
     * Usa recursión para obtener toda la rama
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Genera el permiso asociado a este item del menú
     * Formato: menu.{slug}
     *
     * @return string
     */
    public function getPermissionName(): string
    {
        return 'menu.' . $this->slug;
    }

    /**
     * Obtiene la URL final según el tipo de ruta
     * Si la ruta no existe, retorna '#' para mantener el menú funcional
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return match ($this->route_type) {
            'route' => $this->route_value
                ? (Route::has($this->route_value) ? route($this->route_value) : '#')
                : null,
            'component' => $this->route_value ? url('/') . '?component=' . $this->route_value : null,
            'none' => null,
            default => null,
        };
    }

    /**
     * Verifica si el item actual coincide con la ruta actual
     * Solo verifica si la ruta existe para evitar errores
     *
     * @return bool
     */
    public function isCurrentRoute(): bool
    {
        if ($this->route_type !== 'route' || !$this->route_value) {
            return false;
        }

        // Verificar que la ruta exista antes de compararla
        if (!Route::has($this->route_value)) {
            return false;
        }

        // Verificar coincidencia exacta primero
        if (request()->routeIs($this->route_value)) {
            return true;
        }

        // Verificar coincidencia con subrutas (ej: promociones.nueva, promociones.editar)
        // Usamos un punto como separador para evitar falsos positivos
        // Esto evita que "promociones" coincida con "promociones-especiales"
        if (request()->routeIs($this->route_value . '.*')) {
            return true;
        }

        // Rutas relacionadas: ciertas rutas de configuración pertenecen a sus módulos padre
        $relatedRoutes = [
            'articulos.gestionar' => [
                'configuracion.articulos-sucursal',
                'articulos.cambio-masivo-precios',
            ],
            'articulos.etiquetas' => [
                'articulos.asignar-etiquetas',
            ],
            'configuracion.formas-pago' => [
                'configuracion.formas-pago-sucursal',
            ],
        ];

        // Si esta ruta tiene rutas relacionadas, verificar si estamos en alguna de ellas
        if (isset($relatedRoutes[$this->route_value])) {
            foreach ($relatedRoutes[$this->route_value] as $relatedRoute) {
                if (request()->routeIs($relatedRoute) || request()->routeIs($relatedRoute . '.*')) {
                    return true;
                }
            }
        }

        return false;
    }
}
