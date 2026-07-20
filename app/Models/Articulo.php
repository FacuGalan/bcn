<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property bool $es_materia_prima
 * @property bool $activo
 * @property int $tipo_iva_id
 * @property bool $precio_iva_incluido
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read TipoIva $tipoIva
 * @property-read Categoria|null $categoriaModel
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|Stock[] $stocks
 * @property-read \Illuminate\Database\Eloquent\Collection|VentaDetalle[] $ventasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|CompraDetalle[] $comprasDetalle
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $promocionesCondiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|ListaPrecioArticulo[] $listaPrecioArticulos
 * @property-read \Illuminate\Database\Eloquent\Collection|Etiqueta[] $etiquetas
 * @property-read \Illuminate\Database\Eloquent\Collection|Receta[] $recetas
 * @property-read \Illuminate\Database\Eloquent\Collection|ArticuloGrupoOpcional[] $gruposOpcionales
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
        'puntos_canje',
        'es_materia_prima',
        'pesable',
        'imagen_path',
        'imagen_focal_x',
        'imagen_focal_y',
        'activo',
        'tipo_iva_id',
        'precio_iva_incluido',
        // Utilidad objetivo y repricing (spec compras-costos-precios RF-08/RF-11)
        'utilidad_porcentaje',
        'precio_administrado_por_utilidad',
        // Disponibilidad por canal (RF-16) + presentación en tienda (RF-17)
        'disponible_delivery',
        'disponible_take_away',
        'permite_programado',
        'orden',
        'destacado',
        'badges_tienda',
        'permite_venta_sin_stock',
    ];

    protected $casts = [
        'precio_base' => 'decimal:2',
        'puntos_canje' => 'integer',
        'es_materia_prima' => 'boolean',
        'pesable' => 'boolean',
        'imagen_focal_x' => 'decimal:2',
        'imagen_focal_y' => 'decimal:2',
        'activo' => 'boolean',
        'precio_iva_incluido' => 'boolean',
        'utilidad_porcentaje' => 'decimal:2',
        'precio_administrado_por_utilidad' => 'boolean',
        'disponible_delivery' => 'boolean',
        'disponible_take_away' => 'boolean',
        'permite_programado' => 'boolean',
        'orden' => 'integer',
        'destacado' => 'boolean',
        'badges_tienda' => 'array',
        'permite_venta_sin_stock' => 'boolean',
    ];

    /**
     * Tipos de badge de tienda predefinidos (RF-T14). El icono y color de
     * cada tipo los resuelve la TIENDA (espejo en bcn-tienda); el core solo
     * persiste y valida tipos. 'custom' (texto libre) se valida aparte.
     */
    public const BADGES_TIENDA = [
        'sin_tacc',
        'vegetariano',
        'vegano',
        'picante',
        'nuevo',
        'mas_vendido',
        'artesanal',
        'sin_azucar',
    ];

    public const MAX_BADGES_TIENDA = 4;

    public const MAX_BADGE_CUSTOM_LARGO = 30;

    // Relaciones
    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'tipo_iva_id');
    }

    /**
     * Costos vigentes por sucursal + consolidado (spec compras-costos RF-02).
     * Lectura con fallback sucursal→consolidado: CostoService.
     */
    public function costos(): HasMany
    {
        return $this->hasMany(ArticuloCosto::class, 'articulo_id');
    }

    public function historialCostos(): HasMany
    {
        return $this->hasMany(HistorialCosto::class, 'articulo_id');
    }

    /**
     * Proveedores del artículo: códigos, factor de conversión y descuentos
     * habituales (spec compras-costos RF-04).
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(ArticuloProveedor::class, 'articulo_id');
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
            ->withPivot('activo', 'modo_stock', 'vendible', 'visible_tienda')
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

    public function movimientosStock(): HasMany
    {
        return $this->hasMany(MovimientoStock::class, 'articulo_id');
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

    /**
     * Scope: Artículos que controlan stock en una sucursal (modo_stock != 'ninguno')
     */
    public function scopeConStockEnSucursal($query, int $sucursalId)
    {
        return $query->whereHas('sucursales', function ($q) use ($sucursalId) {
            $q->where('sucursal_id', $sucursalId)
                ->where('modo_stock', '!=', 'ninguno');
        });
    }

    public function scopeMateriaPrima($query)
    {
        return $query->where('es_materia_prima', true);
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
        $modoStock = $this->getModoStock($sucursalId);

        if ($modoStock === 'ninguno') {
            return true;
        }

        if ($modoStock === 'unitario') {
            $stock = $this->getStockEnSucursal($sucursalId);

            return $stock && $stock->cantidad >= $cantidad;
        }

        // modo 'receta': verificar stock de cada ingrediente
        $receta = Receta::resolver('Articulo', $this->id, $sucursalId);
        if (! $receta) {
            return true; // Sin receta definida, no se puede verificar
        }

        $cantidadMultiplier = $cantidad / (float) $receta->cantidad_producida;
        foreach ($receta->ingredientes as $ingrediente) {
            $stockIngrediente = Stock::where('articulo_id', $ingrediente->articulo_id)
                ->where('sucursal_id', $sucursalId)
                ->first();
            $necesario = (float) $ingrediente->cantidad * $cantidadMultiplier;
            if (! $stockIngrediente || $stockIngrediente->cantidad < $necesario) {
                return false;
            }
        }

        return true;
    }

    // ==================== Métodos del Sistema de Listas de Precios ====================

    /**
     * Obtiene el precio base efectivo para una sucursal (override o genérico)
     */
    public function obtenerPrecioBaseEfectivo(int $sucursalId): float
    {
        $override = \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $this->id)
            ->where('sucursal_id', $sucursalId)
            ->value('precio_base');

        return $override !== null ? (float) $override : (float) $this->precio_base;
    }

    /**
     * Obtiene el precio del artículo según una lista de precios
     *
     * @param  ListaPrecio  $listaPrecio  Lista de precios a aplicar
     * @return array ['precio' => float, 'ajuste_porcentaje' => float, 'origen' => string, 'precio_base' => float]
     */
    public function obtenerPrecioConLista(ListaPrecio $listaPrecio): array
    {
        return $listaPrecio->obtenerPrecioArticulo($this);
    }

    /**
     * Obtiene el precio del artículo para una sucursal y contexto específicos
     *
     * @param  int  $sucursalId  ID de la sucursal
     * @param  array  $contexto  Contexto de venta (forma_pago_id, forma_venta_id, canal_venta_id, etc.)
     * @param  int|null  $listaPrecioIdManual  ID de lista seleccionada manualmente
     * @param  int|null  $clienteId  ID del cliente
     * @return array ['precio' => float, 'lista_precio' => ListaPrecio|null, 'ajuste_porcentaje' => float, 'origen' => string]
     */
    public function obtenerPrecioParaSucursal(
        int $sucursalId,
        array $contexto = [],
        ?int $listaPrecioIdManual = null,
        ?int $clienteId = null
    ): array {
        $precioBaseEfectivo = $this->obtenerPrecioBaseEfectivo($sucursalId);

        // Buscar lista aplicable
        $listaPrecio = ListaPrecio::buscarListaAplicable(
            $sucursalId,
            $contexto,
            $listaPrecioIdManual,
            $clienteId
        );

        if ($listaPrecio) {
            $resultado = $listaPrecio->obtenerPrecioArticulo($this, $precioBaseEfectivo);
            $resultado['lista_precio'] = $listaPrecio;

            return $resultado;
        }

        // Si no hay lista, devolver precio base sin ajuste
        return [
            'precio' => $precioBaseEfectivo,
            'precio_sin_redondeo' => $precioBaseEfectivo,
            'ajuste_porcentaje' => 0,
            'origen' => 'articulo_sin_lista',
            'precio_base' => $precioBaseEfectivo,
            'lista_precio' => null,
        ];
    }

    /**
     * Obtiene todas las promociones activas que aplican a este artículo
     *
     * @param  int  $sucursalId  ID de la sucursal
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

    // ==================== Relaciones Recetas y Opcionales ====================

    /**
     * Recetas de este artículo (polimórfica).
     * Puede tener una default (sucursal_id=null) y overrides por sucursal.
     */
    public function recetas(): MorphMany
    {
        return $this->morphMany(Receta::class, 'recetable');
    }

    /**
     * Asignaciones de grupos opcionales a este artículo (todas las sucursales)
     */
    public function gruposOpcionales(): HasMany
    {
        return $this->hasMany(ArticuloGrupoOpcional::class, 'articulo_id');
    }

    /**
     * Resuelve la receta para una sucursal (override > default)
     */
    public function resolverReceta(int $sucursalId): ?Receta
    {
        return Receta::resolver('Articulo', $this->id, $sucursalId);
    }

    /**
     * Verifica si el artículo controla stock en una sucursal (modo_stock != 'ninguno')
     */
    public function controlaStock(int $sucursalId): bool
    {
        return $this->getModoStock($sucursalId) !== 'ninguno';
    }

    /**
     * Obtiene el modo_stock del artículo en una sucursal.
     * Usa query directa con try/catch para compatibilidad pre-migración.
     */
    public function getModoStock(int $sucursalId): string
    {
        try {
            $row = \Illuminate\Support\Facades\DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->where('articulo_id', $this->id)
                ->where('sucursal_id', $sucursalId)
                ->select('modo_stock')
                ->first();

            return $row ? ($row->modo_stock ?? 'ninguno') : 'ninguno';
        } catch (\Exception $e) {
            return 'ninguno';
        }
    }

    /**
     * Obtiene los grupos opcionales asignados para una sucursal (solo activos)
     */
    public function gruposOpcionalesEnSucursal(int $sucursalId)
    {
        return $this->gruposOpcionales()
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->with(['grupoOpcional', 'opciones.opcional'])
            ->orderBy('orden')
            ->get();
    }

    // ==================== IMAGEN ====================

    /**
     * Indica si el artículo tiene una imagen subida y el archivo aún existe
     * en el disk public.
     */
    public function hasImagen(): bool
    {
        return ! empty($this->imagen_path)
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->imagen_path);
    }

    /**
     * URL pública de la imagen como path relativo al host actual,
     * o null si no hay. Usar en blade en lugar de armar Storage::url()
     * a mano.
     *
     * Devolvemos PATH RELATIVO (`/storage/...`) en lugar de URL absoluta
     * porque `Storage::url()` arma con `APP_URL` y eso suele venir sin el
     * puerto correcto en dev (`http://localhost` vs `http://localhost:8000`).
     * El path relativo es portable: usa el host:puerto desde el que se
     * está sirviendo el HTML.
     */
    public function imagenUrl(): ?string
    {
        if (empty($this->imagen_path)) {
            return null;
        }

        return '/storage/'.ltrim($this->imagen_path, '/');
    }

    /**
     * Valor listo para CSS `object-position` (e.g. "30.50% 70.00%").
     * Default centro si no hay focal point seteado.
     */
    public function imagenFocalPosition(): string
    {
        $x = (float) ($this->imagen_focal_x ?? 50);
        $y = (float) ($this->imagen_focal_y ?? 50);

        return number_format($x, 2, '.', '').'% '.number_format($y, 2, '.', '').'%';
    }

    /**
     * Galería de fotos de TIENDA (RF-T14), ordenada. Independiente de la
     * imagen operativa (imagen_path), que actúa de fallback si está vacía.
     */
    public function imagenesTienda(): HasMany
    {
        return $this->hasMany(ArticuloImagenTienda::class, 'articulo_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    /**
     * Badges de tienda SANEADOS (RF-T14): descarta entradas con tipo fuera
     * del catálogo, custom sin texto y todo lo que exceda el máximo. Un JSON
     * viejo/corrupto nunca rompe el catálogo público.
     *
     * @return array<int, array{tipo: string, texto: string|null}>
     */
    public function badgesTienda(): array
    {
        $badges = [];

        foreach ((array) ($this->badges_tienda ?? []) as $badge) {
            if (! is_array($badge) || empty($badge['tipo'])) {
                continue;
            }

            $tipo = (string) $badge['tipo'];
            $texto = isset($badge['texto']) ? trim((string) $badge['texto']) : '';

            if ($tipo === 'custom') {
                if ($texto === '' || mb_strlen($texto) > self::MAX_BADGE_CUSTOM_LARGO) {
                    continue;
                }
                $badges[] = ['tipo' => 'custom', 'texto' => $texto];
            } elseif (in_array($tipo, self::BADGES_TIENDA, true)) {
                $badges[] = ['tipo' => $tipo, 'texto' => null];
            }

            if (count($badges) >= self::MAX_BADGES_TIENDA) {
                break;
            }
        }

        return $badges;
    }
}
