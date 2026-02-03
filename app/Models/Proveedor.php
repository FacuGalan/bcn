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
 * VINCULACIÓN CLIENTE-PROVEEDOR:
 * - Un proveedor puede estar vinculado a un cliente (cliente_id)
 * - Esto permite tener cuentas corrientes unificadas donde el mismo
 *   ente es cliente y proveedor (ej: distribuidora que compra y vende)
 *
 * @property int $id
 * @property string|null $codigo
 * @property string $nombre
 * @property string|null $razon_social
 * @property string|null $nombre_fiscal
 * @property string|null $cuit
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property int|null $condicion_iva_id FK a condiciones_iva
 * @property bool $es_sucursal_interna
 * @property int|null $sucursal_id Sucursal vinculada si es interna
 * @property int|null $cliente_id Cliente vinculado para cuentas unificadas
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CondicionIva|null $condicionIva
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
        'razon_social',
        'nombre_fiscal',
        'cuit',
        'email',
        'telefono',
        'direccion',
        'condicion_iva_id',
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

    /**
     * Condición de IVA del proveedor
     * Relación cross-database a la tabla condiciones_iva en config
     */
    public function condicionIva(): BelongsTo
    {
        return $this->belongsTo(CondicionIva::class, 'condicion_iva_id');
    }

    /**
     * Sucursal vinculada (si es sucursal interna)
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Cliente vinculado (para cuentas corrientes unificadas)
     */
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

    public function scopeConClienteVinculado($query)
    {
        return $query->whereNotNull('cliente_id');
    }

    public function scopeSinClienteVinculado($query)
    {
        return $query->whereNull('cliente_id');
    }

    public function scopePorCondicionIva($query, int $condicionIvaId)
    {
        return $query->where('condicion_iva_id', $condicionIvaId);
    }

    public function scopePorCuit($query, string $cuit)
    {
        return $query->where('cuit', $cuit);
    }

    public function scopeResponsablesInscriptos($query)
    {
        return $query->whereHas('condicionIva', fn($q) => $q->where('codigo', CondicionIva::RESPONSABLE_INSCRIPTO));
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
        return $this->nombre_fiscal ?? $this->razon_social ?? $this->nombre;
    }

    /**
     * Verifica si el proveedor es Responsable Inscripto
     */
    public function esResponsableInscripto(): bool
    {
        return $this->condicionIva?->esResponsableInscripto() ?? false;
    }

    /**
     * Verifica si es Consumidor Final
     */
    public function esConsumidorFinal(): bool
    {
        return $this->condicionIva?->esConsumidorFinal() ?? true;
    }

    /**
     * Verifica si es Monotributista
     */
    public function esMonotributista(): bool
    {
        return $this->condicionIva?->esMonotributista() ?? false;
    }

    /**
     * Obtiene el código AFIP de la condición de IVA
     */
    public function getCodigoCondicionIvaAfip(): int
    {
        return $this->condicionIva?->codigo ?? CondicionIva::CONSUMIDOR_FINAL;
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
