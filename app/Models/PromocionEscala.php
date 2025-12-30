<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo PromocionEscala
 *
 * Define escalas de descuentos por cantidad para promociones de tipo
 * 'descuento_escalonado'.
 *
 * Permite configurar descuentos progresivos basados en cantidad:
 * - 1-5 unidades: 5% descuento
 * - 6-10 unidades: 10% descuento
 * - 11-20 unidades: 15% descuento
 * - 21+ unidades: 20% descuento
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property int $promocion_id ID de la promoción
 * @property int $cantidad_desde Cantidad desde la cual aplica esta escala
 * @property int|null $cantidad_hasta Cantidad hasta la cual aplica (null = sin límite)
 * @property float $descuento_porcentaje Porcentaje de descuento para esta escala
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Promocion $promocion
 */
class PromocionEscala extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'promociones_escalas';

    protected $fillable = [
        'promocion_id',
        'cantidad_desde',
        'cantidad_hasta',
        'tipo_descuento',
        'valor',
    ];

    protected $casts = [
        'cantidad_desde' => 'decimal:3',
        'cantidad_hasta' => 'decimal:3',
        'valor' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    /**
     * Promoción a la que pertenece esta escala
     */
    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Ordenar por cantidad desde (ascendente)
     */
    public function scopeOrdenadoPorCantidad($query)
    {
        return $query->orderBy('cantidad_desde');
    }

    /**
     * Scope: Que aplican a una cantidad específica
     */
    public function scopeParaCantidad($query, int $cantidad)
    {
        return $query->where('cantidad_desde', '<=', $cantidad)
                     ->where(function ($q) use ($cantidad) {
                         $q->whereNull('cantidad_hasta')
                           ->orWhere('cantidad_hasta', '>=', $cantidad);
                     });
    }

    /**
     * Scope: Sin límite superior
     */
    public function scopeSinLimiteSuperior($query)
    {
        return $query->whereNull('cantidad_hasta');
    }

    /**
     * Scope: Con límite superior definido
     */
    public function scopeConLimiteSuperior($query)
    {
        return $query->whereNotNull('cantidad_hasta');
    }

    // ==================== Métodos Auxiliares ====================

    /**
     * Verifica si esta escala aplica para una cantidad dada
     *
     * @param int $cantidad Cantidad a evaluar
     * @return bool
     */
    public function aplicaParaCantidad(int $cantidad): bool
    {
        if ($cantidad < $this->cantidad_desde) {
            return false;
        }

        if ($this->cantidad_hasta !== null && $cantidad > $this->cantidad_hasta) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el descuento para un monto y cantidad dados
     *
     * @param float $monto Monto sobre el cual calcular el descuento
     * @param int $cantidad Cantidad de artículos
     * @return array ['porcentaje' => float, 'monto' => float]
     */
    public function calcularDescuento(float $monto, int $cantidad): array
    {
        if (!$this->aplicaParaCantidad($cantidad)) {
            return [
                'porcentaje' => 0,
                'monto' => 0,
            ];
        }

        $montoDescuento = round($monto * ($this->descuento_porcentaje / 100), 2);

        return [
            'porcentaje' => $this->descuento_porcentaje,
            'monto' => $montoDescuento,
        ];
    }

    /**
     * Obtiene el rango de cantidades como string
     *
     * @return string Ejemplo: "1-5", "6-10", "21+"
     */
    public function obtenerRangoCantidades(): string
    {
        if ($this->cantidad_hasta === null) {
            return "{$this->cantidad_desde}+";
        }

        if ($this->cantidad_desde === $this->cantidad_hasta) {
            return (string) $this->cantidad_desde;
        }

        return "{$this->cantidad_desde}-{$this->cantidad_hasta}";
    }

    /**
     * Obtiene una descripción completa de esta escala
     *
     * @return string Ejemplo: "1-5 unidades: 5% OFF"
     */
    public function obtenerDescripcion(): string
    {
        $rango = $this->obtenerRangoCantidades();
        $unidades = $this->cantidad_hasta === 1 ? 'unidad' : 'unidades';

        return "{$rango} {$unidades}: {$this->descuento_porcentaje}% OFF";
    }

    /**
     * Verifica si es la escala con mayor descuento de la promoción
     */
    public function esMayorDescuento(): bool
    {
        $maxDescuento = $this->promocion->escalas()
                                        ->max('descuento_porcentaje');

        return $this->descuento_porcentaje >= $maxDescuento;
    }

    /**
     * Verifica si tiene límite superior
     */
    public function tieneLimiteSuperior(): bool
    {
        return $this->cantidad_hasta !== null;
    }

    /**
     * Obtiene la siguiente escala (con mayor cantidad_desde)
     */
    public function obtenerSiguienteEscala(): ?self
    {
        return $this->promocion->escalas()
                               ->where('cantidad_desde', '>', $this->cantidad_desde)
                               ->orderBy('cantidad_desde')
                               ->first();
    }

    /**
     * Obtiene la escala anterior (con menor cantidad_desde)
     */
    public function obtenerEscalaAnterior(): ?self
    {
        return $this->promocion->escalas()
                               ->where('cantidad_desde', '<', $this->cantidad_desde)
                               ->orderBy('cantidad_desde', 'desc')
                               ->first();
    }

    /**
     * Calcula cuántas unidades más se necesitan para la siguiente escala
     *
     * @param int $cantidadActual Cantidad actual
     * @return int|null Cantidad faltante o null si no hay siguiente escala
     */
    public function calcularUnidadesParaSiguienteEscala(int $cantidadActual): ?int
    {
        $siguienteEscala = $this->obtenerSiguienteEscala();

        if (!$siguienteEscala) {
            return null; // Ya está en la última escala
        }

        $faltante = $siguienteEscala->cantidad_desde - $cantidadActual;

        return max(0, $faltante);
    }
}
