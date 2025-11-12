<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Proveedor
 *
 * Representa un proveedor del comercio. Puede ser un proveedor externo
 * o una sucursal interna (para transferencias fiscales entre sucursales).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $nombre_fiscal
 * @property string|null $cuit_cuil
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property string $condicion_iva
 * @property bool $es_sucursal_interna
 * @property int|null $sucursal_id
 * @property int|null $cliente_id
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal|null $sucursal
 * @property-read Cliente|null $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection|Compra[] $compras
 */
class Proveedor extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'proveedores';

    protected $fillable = [
        'codigo',
        'nombre',
        'nombre_fiscal',
        'cuit_cuil',
        'direccion',
        'telefono',
        'email',
        'condicion_iva',
        'es_sucursal_interna',
        'sucursal_id',
        'cliente_id',
        'activo',
    ];

    protected $casts = [
        'es_sucursal_interna' => 'boolean',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeInternos($query)
    {
        return $query->where('es_sucursal_interna', true);
    }

    public function scopeExternos($query)
    {
        return $query->where('es_sucursal_interna', false);
    }

    public function scopePorCondicionIva($query, string $condicion)
    {
        return $query->where('condicion_iva', $condicion);
    }

    public function scopePorCuit($query, string $cuit)
    {
        return $query->where('cuit_cuil', $cuit);
    }

    public function scopeResponsablesInscriptos($query)
    {
        return $query->where('condicion_iva', 'responsable_inscripto');
    }

    // Métodos auxiliares

    /**
     * Verifica si el proveedor es una sucursal interna
     */
    public function esSucursalInterna(): bool
    {
        return $this->es_sucursal_interna;
    }

    /**
     * Obtiene la sucursal interna asociada (si existe)
     */
    public function obtenerSucursalInterna(): ?Sucursal
    {
        return $this->esSucursalInterna() ? $this->sucursal : null;
    }

    /**
     * Obtiene el cliente vinculado (si existe)
     */
    public function obtenerClienteVinculado(): ?Cliente
    {
        return $this->cliente;
    }

    /**
     * Verifica si tiene un cliente vinculado para reconciliación de saldos
     */
    public function tieneClienteVinculado(): bool
    {
        return !is_null($this->cliente_id);
    }

    /**
     * Obtiene el nombre fiscal o nombre regular
     */
    public function obtenerNombreFiscal(): string
    {
        return $this->nombre_fiscal ?? $this->nombre;
    }

    /**
     * Verifica si el proveedor es Responsable Inscripto
     */
    public function esResponsableInscripto(): bool
    {
        return $this->condicion_iva === 'responsable_inscripto';
    }

    /**
     * Obtiene el total de compras realizadas al proveedor
     */
    public function obtenerTotalCompras(): float
    {
        return $this->compras()
                    ->where('estado', 'completada')
                    ->sum('total');
    }

    /**
     * Obtiene el total de compras pendientes de pago
     */
    public function obtenerTotalPendiente(): float
    {
        return $this->compras()
                    ->whereIn('estado', ['pendiente', 'parcial'])
                    ->sum('saldo_pendiente');
    }
}
