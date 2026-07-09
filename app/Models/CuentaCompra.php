<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de compra (RF-22, D22): agrupación de gestión para reportes de
 * gastos — NO es un plan de cuentas contable. Default por proveedor +
 * override por compra.
 */
class CuentaCompra extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cuentas_compra';

    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeActivas($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
