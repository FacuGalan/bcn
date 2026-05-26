<?php

namespace App\Models;

use App\Services\IntegracionesPago\Contracts\IntegracionPagoGatewayContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de integraciones de pago disponibles en el sistema.
 *
 * Cada fila representa un proveedor (MercadoPago en MVP, futuros: MODO,
 * Cuenta DNI, PayPal, etc.) con sus modos soportados y la clase Gateway
 * que implementa la integración técnica.
 *
 * Tabla semilla: no se edita por UI. Agregar proveedor = migración + Gateway.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property array $modos_disponibles
 * @property string $gateway_class
 * @property bool $activo
 * @property int $orden
 */
class IntegracionPago extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'integraciones_pago';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'modos_disponibles',
        'gateway_class',
        'activo',
        'orden',
    ];

    protected $casts = [
        'modos_disponibles' => 'array',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public const CODIGO_MERCADOPAGO = 'mercadopago';

    // ==================== Relaciones ====================

    public function sucursales(): HasMany
    {
        return $this->hasMany(IntegracionPagoSucursal::class, 'integracion_pago_id');
    }

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // ==================== Helpers ====================

    /**
     * Instancia el Gateway PHP que implementa esta integración.
     * Ej: 'App\Services\IntegracionesPago\MercadoPagoGateway' → new MercadoPagoGateway().
     *
     * @throws \RuntimeException si la clase no existe o no implementa el contrato
     */
    public function getGatewayInstance(): IntegracionPagoGatewayContract
    {
        if (! class_exists($this->gateway_class)) {
            throw new \RuntimeException("Gateway class no existe: {$this->gateway_class}");
        }

        $instance = app($this->gateway_class);

        if (! $instance instanceof IntegracionPagoGatewayContract) {
            throw new \RuntimeException("Gateway no implementa IntegracionPagoGatewayContract: {$this->gateway_class}");
        }

        return $instance;
    }

    public function soportaModo(string $modo): bool
    {
        return in_array($modo, $this->modos_disponibles ?? [], true);
    }
}
