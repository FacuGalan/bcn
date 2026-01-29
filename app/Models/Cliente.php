<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Cliente
 *
 * Representa un cliente del comercio. Los clientes se gestionan de forma
 * centralizada y pueden tener configuraciones específicas por sucursal
 * (listas de precios, descuentos, límites de crédito).
 *
 * SISTEMA DE LISTAS DE PRECIOS:
 * - Un cliente puede tener una lista de precios global asignada (lista_precio_id)
 * - Esta lista se usa como predeterminada al venderle al cliente
 * - El vendedor puede seleccionar manualmente otra lista que pisa la del cliente
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $nombre_fiscal
 * @property string|null $cuit_cuil
 * @property string $tipo_doc
 * @property string|null $numero_doc
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property int|null $condicion_iva_id FK a condiciones_iva
 * @property int|null $lista_precio_id Lista de precios asignada al cliente
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CondicionIva|null $condicionIva
 * @property-read ListaPrecio|null $listaPrecio
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|Venta[] $ventas
 */
class Cliente extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'direccion',
        'cuit',
        'tipo_cliente',
        'condicion_iva_id',
        'lista_precio_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones

    /**
     * Condición de IVA del cliente
     * Relación cross-database a la tabla condiciones_iva en config
     */
    public function condicionIva(): BelongsTo
    {
        return $this->belongsTo(CondicionIva::class, 'condicion_iva_id');
    }

    /**
     * Lista de precios asignada al cliente (global)
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'clientes_sucursales', 'cliente_id', 'sucursal_id')
                    ->withPivot('lista_precio_id', 'descuento_porcentaje', 'limite_credito', 'saldo_actual', 'activo')
                    ->withTimestamps();
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorCondicionIva($query, int $condicionIvaId)
    {
        return $query->where('condicion_iva_id', $condicionIvaId);
    }

    public function scopePorCodigoCondicionIva($query, int $codigoAfip)
    {
        $condicion = CondicionIva::where('codigo', $codigoAfip)->first();
        return $query->where('condicion_iva_id', $condicion?->id);
    }

    public function scopePorDocumento($query, string $tipo, string $numero)
    {
        return $query->where('tipo_doc', $tipo)
                     ->where('numero_doc', $numero);
    }

    public function scopePorCuit($query, string $cuit)
    {
        return $query->where('cuit_cuil', $cuit);
    }

    public function scopeConDeuda($query)
    {
        return $query->whereHas('sucursales', function ($q) {
            $q->where('saldo_actual', '>', 0);
        });
    }

    public function scopeResponsablesInscriptos($query)
    {
        return $query->whereHas('condicionIva', fn($q) => $q->where('codigo', CondicionIva::RESPONSABLE_INSCRIPTO));
    }

    public function scopeConsumidoresFinales($query)
    {
        return $query->whereHas('condicionIva', fn($q) => $q->where('codigo', CondicionIva::CONSUMIDOR_FINAL));
    }

    public function scopeMonotributistas($query)
    {
        return $query->whereHas('condicionIva', fn($q) => $q->whereIn('codigo', [
            CondicionIva::RESPONSABLE_MONOTRIBUTO,
            CondicionIva::MONOTRIBUTISTA_SOCIAL,
        ]));
    }

    public function scopeExentos($query)
    {
        return $query->whereHas('condicionIva', fn($q) => $q->where('codigo', CondicionIva::SUJETO_EXENTO));
    }

    // Métodos auxiliares

    /**
     * Verifica si el cliente está activo en una sucursal específica
     */
    public function estaActivoEnSucursal(int $sucursalId): bool
    {
        return $this->sucursales()
                    ->where('sucursal_id', $sucursalId)
                    ->wherePivot('activo', true)
                    ->exists();
    }

    /**
     * Obtiene el saldo actual del cliente en una sucursal
     */
    public function obtenerSaldoEnSucursal(int $sucursalId): float
    {
        $pivot = $this->sucursales()
                      ->where('sucursal_id', $sucursalId)
                      ->first();

        return $pivot ? (float) $pivot->pivot->saldo_actual : 0;
    }

    /**
     * Obtiene el límite de crédito en una sucursal
     */
    public function obtenerLimiteCreditoEnSucursal(int $sucursalId): ?float
    {
        $pivot = $this->sucursales()
                      ->where('sucursal_id', $sucursalId)
                      ->first();

        return $pivot && $pivot->pivot->limite_credito ? (float) $pivot->pivot->limite_credito : null;
    }

    /**
     * Obtiene el crédito disponible en una sucursal
     */
    public function obtenerCreditoDisponibleEnSucursal(int $sucursalId): ?float
    {
        $limite = $this->obtenerLimiteCreditoEnSucursal($sucursalId);

        if (is_null($limite)) {
            return null; // Sin límite de crédito configurado
        }

        $saldo = $this->obtenerSaldoEnSucursal($sucursalId);

        return max(0, $limite - $saldo);
    }

    /**
     * Verifica si tiene disponibilidad de crédito para un monto en una sucursal
     */
    public function tieneDisponibilidadCredito(float $monto, int $sucursalId): bool
    {
        $creditoDisponible = $this->obtenerCreditoDisponibleEnSucursal($sucursalId);

        // Si no tiene límite de crédito, siempre tiene disponibilidad
        if (is_null($creditoDisponible)) {
            return true;
        }

        return $creditoDisponible >= $monto;
    }

    /**
     * Ajusta el saldo del cliente en una sucursal
     *
     * @param int $sucursalId
     * @param float $monto Positivo aumenta deuda, negativo disminuye
     * @return bool
     */
    public function ajustarSaldoEnSucursal(int $sucursalId, float $monto): bool
    {
        $saldoActual = $this->obtenerSaldoEnSucursal($sucursalId);
        $nuevoSaldo = max(0, $saldoActual + $monto);

        return $this->sucursales()
                    ->wherePivot('sucursal_id', $sucursalId)
                    ->updateExistingPivot($sucursalId, [
                        'saldo_actual' => $nuevoSaldo
                    ]) > 0;
    }

    /**
     * Obtiene el descuento porcentaje del cliente en una sucursal
     */
    public function obtenerDescuentoEnSucursal(int $sucursalId): float
    {
        $pivot = $this->sucursales()
                      ->where('sucursal_id', $sucursalId)
                      ->first();

        return $pivot ? (float) $pivot->pivot->descuento_porcentaje : 0;
    }

    /**
     * Obtiene el ID de la lista de precios del cliente en una sucursal
     */
    public function obtenerListaPrecioEnSucursal(int $sucursalId): ?int
    {
        $pivot = $this->sucursales()
                      ->where('sucursal_id', $sucursalId)
                      ->first();

        return $pivot && $pivot->pivot->lista_precio_id ? (int) $pivot->pivot->lista_precio_id : null;
    }

    /**
     * Obtiene el nombre fiscal o nombre regular
     */
    public function obtenerNombreFiscal(): string
    {
        return $this->nombre_fiscal ?? $this->nombre;
    }

    /**
     * Verifica si requiere factura A (Responsable Inscripto)
     */
    public function requiereFacturaA(): bool
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
     * Verifica si es Exento
     */
    public function esExento(): bool
    {
        return $this->condicionIva?->esExento() ?? false;
    }

    /**
     * Verifica si requiere CUIT para facturación
     */
    public function requiereCuit(): bool
    {
        return $this->condicionIva?->requiereCuit() ?? false;
    }

    /**
     * Obtiene el código AFIP de la condición de IVA
     */
    public function getCodigoCondicionIvaAfip(): int
    {
        return $this->condicionIva?->codigo ?? CondicionIva::CONSUMIDOR_FINAL;
    }
}
