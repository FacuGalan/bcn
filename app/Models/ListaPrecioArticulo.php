<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ListaPrecioArticulo
 *
 * Define qué artículos o categorías participan en una lista de precios
 * y cuál es su precio/ajuste específico.
 *
 * FUNCIONAMIENTO:
 * - Si articulo_id está definido: aplica a ese artículo específico
 * - Si categoria_id está definido: aplica a todos los artículos de esa categoría
 * - precio_fijo: Si se define, pisa completamente al precio base del artículo
 * - ajuste_porcentaje: Si se define, aplica sobre el precio base del artículo
 *
 * PRIORIDAD DE PRECIO:
 * 1. precio_fijo (si está definido)
 * 2. ajuste_porcentaje del detalle (si está definido)
 * 3. ajuste_porcentaje del encabezado de la lista
 *
 * FASE 2 - Sistema de Listas de Precios
 *
 * @property int $id
 * @property int $lista_precio_id
 * @property int|null $articulo_id
 * @property int|null $categoria_id
 * @property float|null $precio_fijo
 * @property float|null $ajuste_porcentaje
 * @property float|null $precio_base_original
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read ListaPrecio $listaPrecio
 * @property-read Articulo|null $articulo
 * @property-read Categoria|null $categoria
 */
class ListaPrecioArticulo extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'lista_precio_articulos';

    protected $fillable = [
        'lista_precio_id',
        'articulo_id',
        'categoria_id',
        'precio_fijo',
        'ajuste_porcentaje',
        'precio_base_original',
    ];

    protected $casts = [
        'precio_fijo' => 'decimal:2',
        'ajuste_porcentaje' => 'decimal:2',
        'precio_base_original' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    /**
     * Lista de precios a la que pertenece
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    /**
     * Artículo específico (si aplica)
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    /**
     * Categoría (si aplica a todos los artículos de la categoría)
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Por lista de precios
     */
    public function scopePorLista($query, int $listaPrecioId)
    {
        return $query->where('lista_precio_id', $listaPrecioId);
    }

    /**
     * Scope: Por artículo
     */
    public function scopePorArticulo($query, int $articuloId)
    {
        return $query->where('articulo_id', $articuloId);
    }

    /**
     * Scope: Por categoría
     */
    public function scopePorCategoria($query, int $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }

    /**
     * Scope: Solo registros con artículo específico
     */
    public function scopeConArticulo($query)
    {
        return $query->whereNotNull('articulo_id');
    }

    /**
     * Scope: Solo registros con categoría
     */
    public function scopeConCategoria($query)
    {
        return $query->whereNotNull('categoria_id');
    }

    /**
     * Scope: Solo registros con precio fijo
     */
    public function scopeConPrecioFijo($query)
    {
        return $query->whereNotNull('precio_fijo');
    }

    /**
     * Scope: Solo registros con ajuste porcentaje
     */
    public function scopeConAjuste($query)
    {
        return $query->whereNotNull('ajuste_porcentaje');
    }

    // ==================== Métodos de Utilidad ====================

    /**
     * Verifica si es un registro de artículo específico
     */
    public function esArticuloEspecifico(): bool
    {
        return $this->articulo_id !== null;
    }

    /**
     * Verifica si es un registro de categoría
     */
    public function esCategoria(): bool
    {
        return $this->categoria_id !== null;
    }

    /**
     * Verifica si tiene precio fijo definido
     */
    public function tienePrecioFijo(): bool
    {
        return $this->precio_fijo !== null;
    }

    /**
     * Verifica si tiene ajuste porcentaje definido
     */
    public function tieneAjuste(): bool
    {
        return $this->ajuste_porcentaje !== null;
    }

    /**
     * Obtiene el nombre del artículo o categoría
     */
    public function obtenerNombre(): string
    {
        if ($this->articulo_id) {
            return $this->articulo?->nombre ?? "Artículo #{$this->articulo_id}";
        }

        if ($this->categoria_id) {
            return $this->categoria?->nombre ?? "Categoría #{$this->categoria_id}";
        }

        return 'Sin definir';
    }

    /**
     * Obtiene el tipo de registro (artículo o categoría)
     */
    public function obtenerTipo(): string
    {
        if ($this->articulo_id) {
            return 'articulo';
        }

        if ($this->categoria_id) {
            return 'categoria';
        }

        return 'desconocido';
    }

    /**
     * Obtiene descripción del precio/ajuste configurado
     */
    public function obtenerDescripcionPrecio(): string
    {
        if ($this->precio_fijo !== null) {
            return "Precio fijo: \${$this->precio_fijo}";
        }

        if ($this->ajuste_porcentaje !== null) {
            $tipo = $this->ajuste_porcentaje >= 0 ? 'Recargo' : 'Descuento';
            return "{$tipo}: " . abs($this->ajuste_porcentaje) . '%';
        }

        return 'Usa ajuste del encabezado';
    }

    /**
     * Calcula el precio final para un artículo usando este registro
     *
     * @param float $precioBase Precio base del artículo
     * @param float $ajusteEncabezado Ajuste porcentaje del encabezado de la lista
     * @return array ['precio' => float, 'ajuste_porcentaje' => float, 'tipo' => string]
     */
    public function calcularPrecio(float $precioBase, float $ajusteEncabezado = 0): array
    {
        // 1. Si tiene precio fijo, usarlo directamente
        if ($this->precio_fijo !== null) {
            $ajusteCalculado = $precioBase > 0
                ? (($this->precio_fijo - $precioBase) / $precioBase) * 100
                : 0;

            return [
                'precio' => (float) $this->precio_fijo,
                'ajuste_porcentaje' => round($ajusteCalculado, 2),
                'tipo' => 'precio_fijo',
                'precio_base' => $precioBase,
            ];
        }

        // 2. Si tiene ajuste porcentaje, usarlo
        if ($this->ajuste_porcentaje !== null) {
            $ajuste = (float) $this->ajuste_porcentaje;
            $precio = $precioBase * (1 + ($ajuste / 100));

            return [
                'precio' => round($precio, 2),
                'ajuste_porcentaje' => $ajuste,
                'tipo' => 'ajuste_detalle',
                'precio_base' => $precioBase,
            ];
        }

        // 3. Usar ajuste del encabezado
        $precio = $precioBase * (1 + ($ajusteEncabezado / 100));

        return [
            'precio' => round($precio, 2),
            'ajuste_porcentaje' => $ajusteEncabezado,
            'tipo' => 'ajuste_encabezado',
            'precio_base' => $precioBase,
        ];
    }

    /**
     * Actualiza el precio fijo y recalcula el ajuste porcentaje
     */
    public function actualizarPrecioFijo(float $nuevoPrecio): bool
    {
        $precioBase = $this->precio_base_original ?? 0;

        $this->precio_fijo = $nuevoPrecio;

        if ($precioBase > 0) {
            $this->ajuste_porcentaje = round(
                (($nuevoPrecio - $precioBase) / $precioBase) * 100,
                2
            );
        }

        return $this->save();
    }

    /**
     * Actualiza el ajuste porcentaje y calcula el precio fijo resultante
     */
    public function actualizarAjustePorcentaje(float $nuevoAjuste, bool $guardarPrecioFijo = false): bool
    {
        $precioBase = $this->precio_base_original ?? 0;

        $this->ajuste_porcentaje = $nuevoAjuste;

        if ($guardarPrecioFijo && $precioBase > 0) {
            $this->precio_fijo = round($precioBase * (1 + ($nuevoAjuste / 100)), 2);
        } else {
            $this->precio_fijo = null;
        }

        return $this->save();
    }

    /**
     * Sincroniza el precio base original con el artículo actual
     */
    public function sincronizarPrecioBaseOriginal(): bool
    {
        if (!$this->articulo_id || !$this->articulo) {
            return false;
        }

        $this->precio_base_original = $this->articulo->precio_base;
        return $this->save();
    }

    // ==================== Métodos Estáticos ====================

    /**
     * Crea un registro para un artículo específico
     */
    public static function crearParaArticulo(
        int $listaPrecioId,
        int $articuloId,
        ?float $precioFijo = null,
        ?float $ajustePorcentaje = null
    ): self {
        $articulo = Articulo::find($articuloId);

        return self::create([
            'lista_precio_id' => $listaPrecioId,
            'articulo_id' => $articuloId,
            'categoria_id' => null,
            'precio_fijo' => $precioFijo,
            'ajuste_porcentaje' => $ajustePorcentaje,
            'precio_base_original' => $articulo?->precio_base,
        ]);
    }

    /**
     * Crea un registro para una categoría
     */
    public static function crearParaCategoria(
        int $listaPrecioId,
        int $categoriaId,
        ?float $ajustePorcentaje = null
    ): self {
        return self::create([
            'lista_precio_id' => $listaPrecioId,
            'articulo_id' => null,
            'categoria_id' => $categoriaId,
            'precio_fijo' => null, // Las categorías no tienen precio fijo
            'ajuste_porcentaje' => $ajustePorcentaje,
            'precio_base_original' => null,
        ]);
    }

    /**
     * Busca el registro más específico para un artículo en una lista
     *
     * @param int $listaPrecioId
     * @param int $articuloId
     * @param int|null $categoriaId Categoría del artículo
     * @return self|null
     */
    public static function buscarParaArticulo(int $listaPrecioId, int $articuloId, ?int $categoriaId = null): ?self
    {
        // Primero buscar por artículo específico
        $detalle = self::porLista($listaPrecioId)
            ->porArticulo($articuloId)
            ->first();

        if ($detalle) {
            return $detalle;
        }

        // Si no encuentra y tiene categoría, buscar por categoría
        if ($categoriaId) {
            return self::porLista($listaPrecioId)
                ->porCategoria($categoriaId)
                ->first();
        }

        return null;
    }
}
