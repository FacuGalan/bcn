<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Modelo Articulo
 *
 * Representa un artículo en el catálogo maestro del comercio.
 * Los artículos pueden tener diferentes precios e IVA, y pueden estar
 * disponibles de forma selectiva en diferentes sucursales.
 *
 * SISTEMA DE PRECIOS:
 * - precio_base: Precio base del artículo (en tabla articulos)
 * - Listas de precios: Ajustan el precio_base mediante porcentajes o precios fijos
 * - Promociones: Aplican descuentos/recargos adicionales
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $codigo_barras Código de barras (EAN, UPC, etc.)
 * @property string $nombre
 * @property string|null $descripcion
 * @property int|null $categoria_id ID de la categoría
 * @property string|null $categoria Categoría legacy (deprecado, usar categoria_id)
 * @property string|null $marca
 * @property string $unidad_medida
 * @property float $precio_base Precio base del artículo
 * @property bool $es_servicio
 * @property bool $controla_stock
 * @property bool $activo
 * @property int $tipo_iva_id
 * @property bool $precio_iva_incluido
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read TipoIva $tipoIva
 * @property-read Categoria|null $categoriaModel
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|Stock[] $stocks
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $ventasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|CompraDetalle[] $comprasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $promocionesCondiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|ListaPrecioArticulo[] $listaPrecioArticulos
 * @property-read \Illuminate\Database\Eloquent\Collection|Etiqueta[] $etiquetas
 */
class Articulo extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'articulos';

    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'descripcion',
        'categoria_id',
        'categoria', // Legacy - deprecado
        'unidad_medida',
        'precio_base',
        'es_servicio',
        'controla_stock',
        'activo',
        'tipo_iva_id',
        'precio_iva_incluido',
    ];

    protected $casts = [
        'precio_base' => 'decimal:2',
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

    /**
     * Categoría a la que pertenece (nuevo sistema de precios)
     * Nombre 'categoriaModel' para evitar conflicto con campo 'categoria' legacy
     */
    public function categoriaModel(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
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

    /**
     * Condiciones de promociones que aplican a este artículo
     */
    public function promocionesCondiciones(): HasMany
    {
        return $this->hasMany(PromocionCondicion::class, 'articulo_id');
    }

    /**
     * Registros en listas de precios donde participa este artículo
     */
    public function listaPrecioArticulos(): HasMany
    {
        return $this->hasMany(ListaPrecioArticulo::class, 'articulo_id');
    }

    /**
     * Etiquetas asignadas a este artículo
     */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'articulo_etiqueta', 'articulo_id', 'etiqueta_id')
                    ->withTimestamps();
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

    public function scopePorCodigoBarras($query, string $codigoBarras)
    {
        return $query->where('codigo_barras', $codigoBarras);
    }

    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'like', "%{$nombre}%");
    }

    public function scopePorCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope: Por ID de categoría (nuevo sistema)
     */
    public function scopePorCategoriaId($query, int $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
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

    // ==================== Métodos del Sistema de Listas de Precios ====================

    /**
     * Obtiene el precio del artículo según una lista de precios
     *
     * @param ListaPrecio $listaPrecio Lista de precios a aplicar
     * @return array ['precio' => float, 'ajuste_porcentaje' => float, 'origen' => string, 'precio_base' => float]
     */
    public function obtenerPrecioConLista(ListaPrecio $listaPrecio): array
    {
        return $listaPrecio->obtenerPrecioArticulo($this);
    }

    /**
     * Obtiene el precio del artículo para una sucursal y contexto específicos
     *
     * @param int $sucursalId ID de la sucursal
     * @param array $contexto Contexto de venta (forma_pago_id, forma_venta_id, canal_venta_id, etc.)
     * @param int|null $listaPrecioIdManual ID de lista seleccionada manualmente
     * @param int|null $clienteId ID del cliente
     * @return array ['precio' => float, 'lista_precio' => ListaPrecio|null, 'ajuste_porcentaje' => float, 'origen' => string]
     */
    public function obtenerPrecioParaSucursal(
        int $sucursalId,
        array $contexto = [],
        ?int $listaPrecioIdManual = null,
        ?int $clienteId = null
    ): array {
        // Buscar lista aplicable
        $listaPrecio = ListaPrecio::buscarListaAplicable(
            $sucursalId,
            $contexto,
            $listaPrecioIdManual,
            $clienteId
        );

        if ($listaPrecio) {
            $resultado = $listaPrecio->obtenerPrecioArticulo($this);
            $resultado['lista_precio'] = $listaPrecio;
            return $resultado;
        }

        // Si no hay lista, devolver precio base sin ajuste
        return [
            'precio' => (float) $this->precio_base,
            'precio_sin_redondeo' => (float) $this->precio_base,
            'ajuste_porcentaje' => 0,
            'origen' => 'articulo_sin_lista',
            'precio_base' => (float) $this->precio_base,
            'lista_precio' => null,
        ];
    }

    /**
     * Obtiene todas las promociones activas que aplican a este artículo
     *
     * @param int $sucursalId ID de la sucursal
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerPromocionesActivas(int $sucursalId)
    {
        return $this->promocionesCondiciones()
                    ->whereHas('promocion', function ($query) use ($sucursalId) {
                        $query->where('sucursal_id', $sucursalId)
                              ->where('activo', true)
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

    /**
     * Verifica si tiene categoría asignada
     */
    public function tieneCategoria(): bool
    {
        return $this->categoria_id !== null;
    }

    /**
     * Verifica si el artículo participa en alguna lista de precios
     */
    public function participaEnListasPrecios(): bool
    {
        return $this->listaPrecioArticulos()->exists();
    }

    /**
     * Obtiene las listas de precios donde participa este artículo
     */
    public function obtenerListasPreciosDondeParticipa(): \Illuminate\Database\Eloquent\Collection
    {
        return ListaPrecio::whereHas('articulos', function ($query) {
            $query->where('articulo_id', $this->id);
        })->orWhereHas('articulos', function ($query) {
            $query->where('categoria_id', $this->categoria_id);
        })->get();
    }

    // ==================== Métodos de Etiquetas ====================

    /**
     * Verifica si el artículo tiene etiquetas asignadas
     */
    public function tieneEtiquetas(): bool
    {
        return $this->etiquetas()->exists();
    }

    /**
     * Verifica si el artículo tiene una etiqueta específica
     */
    public function tieneEtiqueta(int $etiquetaId): bool
    {
        return $this->etiquetas()->where('etiqueta_id', $etiquetaId)->exists();
    }

    /**
     * Verifica si el artículo tiene alguna etiqueta de un grupo específico
     */
    public function tieneEtiquetaDelGrupo(int $grupoId): bool
    {
        return $this->etiquetas()->where('grupo_etiqueta_id', $grupoId)->exists();
    }

    /**
     * Obtiene las etiquetas del artículo agrupadas por grupo
     */
    public function etiquetasAgrupadasPorGrupo(): Collection
    {
        return $this->etiquetas()
                    ->with('grupo')
                    ->get()
                    ->groupBy('grupo_etiqueta_id');
    }

    /**
     * Scope: Filtrar artículos que tengan alguna de las etiquetas especificadas
     */
    public function scopeConEtiquetas($query, array $etiquetaIds)
    {
        return $query->whereHas('etiquetas', function ($q) use ($etiquetaIds) {
            $q->whereIn('etiqueta_id', $etiquetaIds);
        });
    }

    /**
     * Scope: Filtrar artículos que tengan alguna etiqueta de los grupos especificados
     */
    public function scopeConEtiquetasDeGrupos($query, array $grupoIds)
    {
        return $query->whereHas('etiquetas', function ($q) use ($grupoIds) {
            $q->whereIn('grupo_etiqueta_id', $grupoIds);
        });
    }
}
