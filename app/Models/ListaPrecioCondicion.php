<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ListaPrecioCondicion
 *
 * Define las condiciones que deben cumplirse para que una lista de precios aplique.
 * Una lista puede tener MÚLTIPLES condiciones (AND lógico entre todas).
 *
 * TIPOS DE CONDICIÓN:
 * - por_forma_pago: Solo si se paga con forma de pago específica
 * - por_forma_venta: Solo para forma de venta específica (local/delivery/takeaway)
 * - por_canal: Solo para canal específico (POS/salón/web)
 * - por_total_compra: Se aplica si total de compra está en rango
 *
 * FASE 2 - Sistema de Listas de Precios
 *
 * @property int $id
 * @property int $lista_precio_id
 * @property string $tipo_condicion
 * @property int|null $forma_pago_id
 * @property int|null $forma_venta_id
 * @property int|null $canal_venta_id
 * @property float|null $monto_minimo
 * @property float|null $monto_maximo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read ListaPrecio $listaPrecio
 * @property-read FormaPago|null $formaPago
 * @property-read FormaVenta|null $formaVenta
 * @property-read CanalVenta|null $canalVenta
 */
class ListaPrecioCondicion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'lista_precio_condiciones';

    protected $fillable = [
        'lista_precio_id',
        'tipo_condicion',
        'forma_pago_id',
        'forma_venta_id',
        'canal_venta_id',
        'monto_minimo',
        'monto_maximo',
    ];

    protected $casts = [
        'monto_minimo' => 'decimal:2',
        'monto_maximo' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    /**
     * Lista de precios a la que pertenece esta condición
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    /**
     * Forma de pago asociada
     */
    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    /**
     * Forma de venta asociada
     */
    public function formaVenta(): BelongsTo
    {
        return $this->belongsTo(FormaVenta::class, 'forma_venta_id');
    }

    /**
     * Canal de venta asociado
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
     * Scope: Por lista de precios
     */
    public function scopePorLista($query, int $listaPrecioId)
    {
        return $query->where('lista_precio_id', $listaPrecioId);
    }

    // ==================== Métodos de Evaluación ====================

    /**
     * Evalúa si la condición se cumple dado un contexto de venta
     *
     * @param array $contexto Array con claves: forma_pago_id, forma_venta_id, canal_venta_id, total_compra
     * @return bool
     */
    public function evaluar(array $contexto): bool
    {
        return match ($this->tipo_condicion) {
            'por_forma_pago' => $this->evaluarFormaPago($contexto),
            'por_forma_venta' => $this->evaluarFormaVenta($contexto),
            'por_canal' => $this->evaluarCanalVenta($contexto),
            'por_total_compra' => $this->evaluarTotalCompra($contexto),
            default => false,
        };
    }

    /**
     * Evalúa condición por forma de pago
     */
    protected function evaluarFormaPago(array $contexto): bool
    {
        $formaPagoId = $contexto['forma_pago_id'] ?? null;

        if ($formaPagoId === null) {
            return false;
        }

        return $this->forma_pago_id === (int) $formaPagoId;
    }

    /**
     * Evalúa condición por forma de venta
     */
    protected function evaluarFormaVenta(array $contexto): bool
    {
        $formaVentaId = $contexto['forma_venta_id'] ?? null;

        if ($formaVentaId === null) {
            return false;
        }

        return $this->forma_venta_id === (int) $formaVentaId;
    }

    /**
     * Evalúa condición por canal de venta
     */
    protected function evaluarCanalVenta(array $contexto): bool
    {
        $canalVentaId = $contexto['canal_venta_id'] ?? null;

        if ($canalVentaId === null) {
            return false;
        }

        return $this->canal_venta_id === (int) $canalVentaId;
    }

    /**
     * Evalúa condición por total de compra
     */
    protected function evaluarTotalCompra(array $contexto): bool
    {
        $totalCompra = $contexto['total_compra'] ?? null;

        if ($totalCompra === null) {
            return false;
        }

        $total = (float) $totalCompra;

        if ($this->monto_minimo !== null && $total < $this->monto_minimo) {
            return false;
        }

        if ($this->monto_maximo !== null && $total > $this->monto_maximo) {
            return false;
        }

        return true;
    }

    // ==================== Métodos de Utilidad ====================

    /**
     * Obtiene descripción legible de la condición
     */
    public function obtenerDescripcion(): string
    {
        return match ($this->tipo_condicion) {
            'por_forma_pago' => $this->formaPago
                ? "Forma de pago: {$this->formaPago->nombre}"
                : 'Forma de pago específica',
            'por_forma_venta' => $this->formaVenta
                ? "Forma de venta: {$this->formaVenta->nombre}"
                : 'Forma de venta específica',
            'por_canal' => $this->canalVenta
                ? "Canal: {$this->canalVenta->nombre}"
                : 'Canal específico',
            'por_total_compra' => $this->obtenerDescripcionRangoMonto(),
            default => 'Condición desconocida',
        };
    }

    /**
     * Obtiene descripción del rango de monto
     */
    protected function obtenerDescripcionRangoMonto(): string
    {
        if ($this->monto_minimo !== null && $this->monto_maximo !== null) {
            return "Total entre \${$this->monto_minimo} y \${$this->monto_maximo}";
        }

        if ($this->monto_minimo !== null) {
            return "Total mínimo \${$this->monto_minimo}";
        }

        if ($this->monto_maximo !== null) {
            return "Total máximo \${$this->monto_maximo}";
        }

        return 'Sin restricción de monto';
    }

    /**
     * Verifica si esta condición es igual a otra
     */
    public function esIgualA(self $otra): bool
    {
        if ($this->tipo_condicion !== $otra->tipo_condicion) {
            return false;
        }

        return match ($this->tipo_condicion) {
            'por_forma_pago' => $this->forma_pago_id === $otra->forma_pago_id,
            'por_forma_venta' => $this->forma_venta_id === $otra->forma_venta_id,
            'por_canal' => $this->canal_venta_id === $otra->canal_venta_id,
            'por_total_compra' => $this->tieneRangoSuperpuesto($otra),
            default => false,
        };
    }

    /**
     * Verifica si el rango de monto se superpone con otra condición
     */
    protected function tieneRangoSuperpuesto(self $otra): bool
    {
        $minA = $this->monto_minimo ?? 0;
        $maxA = $this->monto_maximo ?? PHP_FLOAT_MAX;
        $minB = $otra->monto_minimo ?? 0;
        $maxB = $otra->monto_maximo ?? PHP_FLOAT_MAX;

        return $minA <= $maxB && $maxA >= $minB;
    }

    /**
     * Obtiene el valor de referencia para esta condición (para mostrar en UI)
     */
    public function obtenerValorReferencia(): ?string
    {
        return match ($this->tipo_condicion) {
            'por_forma_pago' => $this->formaPago?->nombre,
            'por_forma_venta' => $this->formaVenta?->nombre,
            'por_canal' => $this->canalVenta?->nombre,
            'por_total_compra' => $this->obtenerDescripcionRangoMonto(),
            default => null,
        };
    }
}
