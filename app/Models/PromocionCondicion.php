<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PromocionCondicion
 *
 * Define las condiciones que deben cumplirse para que una promoción aplique.
 * Una promoción puede tener múltiples condiciones, y TODAS deben cumplirse (AND).
 *
 * TIPOS DE CONDICIÓN:
 * - por_articulo: Aplica a artículo(s) específico(s)
 * - por_categoria: Aplica a categoría(s) completa(s)
 * - por_forma_pago: Aplica a forma(s) de pago específica(s)
 * - por_forma_venta: Aplica a forma(s) de venta específica(s)
 * - por_canal: Aplica a canal(es) de venta específico(s)
 * - por_cantidad: Aplica a partir de una cantidad mínima
 * - por_total_compra: Aplica a partir de un monto mínimo de compra
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property int $promocion_id ID de la promoción
 * @property string $tipo_condicion Tipo de condición (enum)
 * @property int|null $articulo_id ID del artículo (si aplica)
 * @property int|null $categoria_id ID de la categoría (si aplica)
 * @property int|null $forma_pago_id ID de la forma de pago (si aplica)
 * @property int|null $forma_venta_id ID de la forma de venta (si aplica)
 * @property int|null $canal_venta_id ID del canal de venta (si aplica)
 * @property int|null $cantidad_minima Cantidad mínima requerida (si aplica)
 * @property float|null $monto_minimo Monto mínimo requerido (si aplica)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Promocion $promocion
 * @property-read Articulo|null $articulo
 * @property-read Categoria|null $categoria
 * @property-read FormaPago|null $formaPago
 * @property-read FormaVenta|null $formaVenta
 * @property-read CanalVenta|null $canalVenta
 */
class PromocionCondicion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'promociones_condiciones';

    protected $fillable = [
        'promocion_id',
        'tipo_condicion',
        'articulo_id',
        'categoria_id',
        'forma_pago_id',
        'forma_venta_id',
        'canal_venta_id',
        'cantidad_minima',
        'monto_minimo',
    ];

    protected $casts = [
        'cantidad_minima' => 'integer',
        'monto_minimo' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    /**
     * Promoción a la que pertenece esta condición
     */
    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    /**
     * Artículo relacionado (si aplica)
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    /**
     * Categoría relacionada (si aplica)
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * Forma de pago relacionada (si aplica)
     */
    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    /**
     * Forma de venta relacionada (si aplica)
     */
    public function formaVenta(): BelongsTo
    {
        return $this->belongsTo(FormaVenta::class, 'forma_venta_id');
    }

    /**
     * Canal de venta relacionado (si aplica)
     */
    public function canalVenta(): BelongsTo
    {
        return $this->belongsTo(CanalVenta::class, 'canal_venta_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Por tipo de condición
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_condicion', $tipo);
    }

    /**
     * Scope: Condiciones por artículo
     */
    public function scopePorArticulo($query)
    {
        return $query->where('tipo_condicion', 'por_articulo');
    }

    /**
     * Scope: Condiciones por categoría
     */
    public function scopePorCategoria($query)
    {
        return $query->where('tipo_condicion', 'por_categoria');
    }

    /**
     * Scope: Condiciones por forma de pago
     */
    public function scopePorFormaPago($query)
    {
        return $query->where('tipo_condicion', 'por_forma_pago');
    }

    /**
     * Scope: Condiciones por cantidad
     */
    public function scopePorCantidad($query)
    {
        return $query->where('tipo_condicion', 'por_cantidad');
    }

    /**
     * Scope: Condiciones por total de compra
     */
    public function scopePorTotalCompra($query)
    {
        return $query->where('tipo_condicion', 'por_total_compra');
    }

    // ==================== Métodos de Validación ====================

    /**
     * Evalúa si esta condición se cumple para un contexto dado
     *
     * @param array $contexto Contexto de evaluación con claves:
     *   - articulo_id: int
     *   - categoria_id: int
     *   - forma_pago_id: int
     *   - forma_venta_id: int
     *   - canal_venta_id: int
     *   - cantidad: int
     *   - total: float
     * @return bool
     */
    public function seCumple(array $contexto): bool
    {
        switch ($this->tipo_condicion) {
            case 'por_articulo':
                return isset($contexto['articulo_id'])
                    && $contexto['articulo_id'] == $this->articulo_id;

            case 'por_categoria':
                return isset($contexto['categoria_id'])
                    && $contexto['categoria_id'] == $this->categoria_id;

            case 'por_forma_pago':
                return isset($contexto['forma_pago_id'])
                    && $contexto['forma_pago_id'] == $this->forma_pago_id;

            case 'por_forma_venta':
                return isset($contexto['forma_venta_id'])
                    && $contexto['forma_venta_id'] == $this->forma_venta_id;

            case 'por_canal':
                return isset($contexto['canal_venta_id'])
                    && $contexto['canal_venta_id'] == $this->canal_venta_id;

            case 'por_cantidad':
                return isset($contexto['cantidad'])
                    && $contexto['cantidad'] >= ($this->cantidad_minima ?? 0);

            case 'por_total_compra':
                return isset($contexto['total'])
                    && $contexto['total'] >= ($this->monto_minimo ?? 0);

            default:
                return false;
        }
    }

    /**
     * Obtiene una descripción legible de esta condición
     */
    public function obtenerDescripcion(): string
    {
        switch ($this->tipo_condicion) {
            case 'por_articulo':
                return "Artículo: " . ($this->articulo->nombre ?? "ID {$this->articulo_id}");

            case 'por_categoria':
                return "Categoría: " . ($this->categoria->nombre ?? "ID {$this->categoria_id}");

            case 'por_forma_pago':
                return "Forma de pago: " . ($this->formaPago->nombre ?? "ID {$this->forma_pago_id}");

            case 'por_forma_venta':
                return "Forma de venta: " . ($this->formaVenta->nombre ?? "ID {$this->forma_venta_id}");

            case 'por_canal':
                return "Canal: " . ($this->canalVenta->nombre ?? "ID {$this->canal_venta_id}");

            case 'por_cantidad':
                return "Cantidad mínima: {$this->cantidad_minima}";

            case 'por_total_compra':
                return "Total mínimo: $" . number_format($this->monto_minimo, 2);

            default:
                return "Condición desconocida";
        }
    }

    /**
     * Obtiene el valor asociado a esta condición según su tipo
     */
    public function obtenerValorAsociado()
    {
        switch ($this->tipo_condicion) {
            case 'por_articulo':
                return $this->articulo_id;
            case 'por_categoria':
                return $this->categoria_id;
            case 'por_forma_pago':
                return $this->forma_pago_id;
            case 'por_forma_venta':
                return $this->forma_venta_id;
            case 'por_canal':
                return $this->canal_venta_id;
            case 'por_cantidad':
                return $this->cantidad_minima;
            case 'por_total_compra':
                return $this->monto_minimo;
            default:
                return null;
        }
    }

    /**
     * Obtiene el nombre del campo asociado al tipo de condición
     */
    public function obtenerNombreCampoAsociado(): string
    {
        $mapa = [
            'por_articulo' => 'articulo_id',
            'por_categoria' => 'categoria_id',
            'por_forma_pago' => 'forma_pago_id',
            'por_forma_venta' => 'forma_venta_id',
            'por_canal' => 'canal_venta_id',
            'por_cantidad' => 'cantidad_minima',
            'por_total_compra' => 'monto_minimo',
        ];

        return $mapa[$this->tipo_condicion] ?? '';
    }
}
