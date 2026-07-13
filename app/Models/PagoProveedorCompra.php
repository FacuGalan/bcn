<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplicación de una orden de pago a una compra (análogo de CobroVenta):
 * snapshot de saldos para auditoría.
 */
class PagoProveedorCompra extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'pago_proveedor_compras';

    protected $fillable = [
        'pago_proveedor_id',
        'compra_id',
        'monto_aplicado',
        'saldo_anterior',
        'saldo_posterior',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    public function pagoProveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedor::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }
}
