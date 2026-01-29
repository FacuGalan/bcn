<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo CompraDetalle
 *
 * Representa un ítem de una compra con su cálculo de IVA.
 * Registra el crédito fiscal de IVA.
 *
 * @property int $id
 * @property int $compra_id
 * @property int $articulo_id
 * @property int $tipo_iva_id
 * @property float $cantidad
 * @property float $precio_unitario
 * @property float $iva_porcentaje
 * @property float $precio_sin_iva
 * @property float $iva_monto
 * @property float $subtotal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Compra $compra
 * @property-read Articulo $articulo
 * @property-read TipoIva $tipoIva
 */
class CompraDetalle extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'compras_detalle';

    protected $fillable = [
        'compra_id',
        'articulo_id',
        'tipo_iva_id',
        'cantidad',
        'precio_unitario',
        'iva_porcentaje',
        'precio_sin_iva',
        'iva_monto',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'precio_sin_iva' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relaciones
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'tipo_iva_id');
    }

    // Métodos auxiliares

    /**
     * Calcula los importes del detalle
     */
    public function calcularImportes(): array
    {
        $subtotalSinIva = $this->precio_sin_iva * $this->cantidad;

        // Calcular IVA
        if ($this->iva_porcentaje > 0) {
            $ivaMonto = $subtotalSinIva * ($this->iva_porcentaje / 100);
            $subtotal = $subtotalSinIva + $ivaMonto;
        } else {
            $ivaMonto = 0;
            $subtotal = $subtotalSinIva;
        }

        return [
            'precio_unitario' => round($this->precio_unitario, 2),
            'precio_sin_iva' => round($this->precio_sin_iva, 2),
            'cantidad' => $this->cantidad,
            'subtotal_sin_iva' => round($subtotalSinIva, 2),
            'iva_porcentaje' => $this->iva_porcentaje,
            'iva_monto' => round($ivaMonto, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * Actualiza los cálculos del detalle
     */
    public function actualizarCalculos(): bool
    {
        $importes = $this->calcularImportes();

        $this->iva_monto = $importes['iva_monto'];
        $this->subtotal = $importes['subtotal'];

        return $this->save();
    }

    /**
     * Obtiene el precio total del ítem (cantidad * precio)
     */
    public function obtenerTotal(): float
    {
        return round($this->subtotal, 2);
    }

    /**
     * Obtiene el crédito fiscal de IVA del ítem
     */
    public function obtenerCreditoFiscal(): float
    {
        return round($this->iva_monto, 2);
    }
}
