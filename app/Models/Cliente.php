<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 * VINCULACIÓN CLIENTE-PROVEEDOR:
 * - Un cliente puede estar vinculado a un proveedor (relación inversa)
 * - Esto permite tener cuentas corrientes unificadas en el futuro
 * - Un proveedor tiene cliente_id que apunta a este cliente
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $razon_social
 * @property string|null $cuit
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $direccion
 * @property int|null $condicion_iva_id FK a condiciones_iva
 * @property int|null $lista_precio_id Lista de precios asignada al cliente
 * @property bool $activo
 * @property bool $tiene_cuenta_corriente Si puede comprar a crédito
 * @property float $limite_credito Límite máximo de crédito (0 = sin límite)
 * @property int $dias_credito Días de crédito por defecto
 * @property float $tasa_interes_mensual Tasa de interés mensual por mora (%)
 * @property float $saldo_deudor_cache Cache de deuda del cliente
 * @property float $saldo_a_favor_cache Cache de saldo a favor
 * @property \Carbon\Carbon|null $ultimo_movimiento_cc_at Último movimiento en cuenta corriente
 * @property bool $bloqueado_por_mora Si está bloqueado por mora
 * @property int $dias_mora_max Máximos días de mora actual
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CondicionIva|null $condicionIva
 * @property-read ListaPrecio|null $listaPrecio
 * @property-read Proveedor|null $proveedor Proveedor vinculado
 * @property-read \Illuminate\Database\Eloquent\Collection|Sucursal[] $sucursales
 * @property-read \Illuminate\Database\Eloquent\Collection|Venta[] $ventas
 */
class Cliente extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'razon_social',
        'cuit',
        'email',
        'telefono',
        'direccion',
        'condicion_iva_id',
        'lista_precio_id',
        'activo',
        'tiene_cuenta_corriente',
        'limite_credito',
        'dias_credito',
        'tasa_interes_mensual',
        'bloqueado_por_mora',
        'dias_mora_max',
        // Campos de cache de cuenta corriente
        'saldo_deudor_cache',
        'saldo_a_favor_cache',
        'ultimo_movimiento_cc_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiene_cuenta_corriente' => 'boolean',
        'limite_credito' => 'decimal:2',
        'tasa_interes_mensual' => 'decimal:2',
        'saldo_deudor_cache' => 'decimal:2',
        'saldo_a_favor_cache' => 'decimal:2',
        'bloqueado_por_mora' => 'boolean',
        'ultimo_movimiento_cc_at' => 'datetime',
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

    /**
     * Movimientos de cuenta corriente del cliente (unificados)
     */
    public function movimientosCuentaCorriente(): HasMany
    {
        return $this->hasMany(MovimientoCuentaCorriente::class, 'cliente_id');
    }

    /**
     * Cobros realizados al cliente
     */
    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'cliente_id');
    }

    /**
     * Proveedor vinculado a este cliente
     * Permite tener un proveedor y cliente unificados (misma entidad)
     */
    public function proveedor(): HasOne
    {
        return $this->hasOne(Proveedor::class, 'cliente_id');
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
     * Si el pivot no tiene límite configurado, usa el límite global del cliente
     * Retorna null si ambos son 0 (sin límite = crédito ilimitado)
     */
    public function obtenerLimiteCreditoEnSucursal(int $sucursalId): ?float
    {
        $pivot = $this->sucursales()
                      ->where('sucursal_id', $sucursalId)
                      ->first();

        // Primero intentar obtener límite específico de la sucursal
        $limiteSucursal = $pivot ? (float) $pivot->pivot->limite_credito : 0;

        // Si la sucursal tiene límite > 0, usarlo
        if ($limiteSucursal > 0) {
            return $limiteSucursal;
        }

        // Si no, usar el límite global del cliente
        $limiteGlobal = (float) $this->limite_credito;

        // Si el límite global es 0, retornar null (sin límite = ilimitado)
        return $limiteGlobal > 0 ? $limiteGlobal : null;
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
     * Obtiene el nombre fiscal (razón social) o nombre regular
     */
    public function obtenerNombreFiscal(): string
    {
        return $this->razon_social ?? $this->nombre;
    }

    /**
     * Verifica si tiene un proveedor vinculado
     */
    public function tieneProveedorVinculado(): bool
    {
        return $this->proveedor()->exists();
    }

    /**
     * Obtiene el proveedor vinculado
     */
    public function obtenerProveedorVinculado(): ?Proveedor
    {
        return $this->proveedor;
    }

    /**
     * Verifica si puede operar a crédito (tiene cuenta corriente y no está bloqueado)
     */
    public function puedeOperarACredito(): bool
    {
        return $this->tiene_cuenta_corriente && !$this->bloqueado_por_mora;
    }

    /**
     * Obtiene el crédito disponible global
     */
    public function obtenerCreditoDisponible(): ?float
    {
        if (!$this->tiene_cuenta_corriente) {
            return null;
        }

        if ($this->limite_credito <= 0) {
            return null; // Sin límite
        }

        return max(0, $this->limite_credito - $this->saldo_deudor_cache);
    }

    /**
     * Verifica si tiene disponibilidad de crédito para un monto
     */
    public function tieneDisponibilidadCreditoGlobal(float $monto): bool
    {
        if (!$this->tiene_cuenta_corriente) {
            return false;
        }

        if ($this->bloqueado_por_mora) {
            return false;
        }

        $creditoDisponible = $this->obtenerCreditoDisponible();

        // Si no tiene límite configurado, siempre tiene disponibilidad
        if (is_null($creditoDisponible)) {
            return true;
        }

        return $creditoDisponible >= $monto;
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
