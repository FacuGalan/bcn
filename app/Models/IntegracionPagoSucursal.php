<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Configuración de una integración de pago en una sucursal específica.
 *
 * Cada sucursal puede tener su propia cuenta del proveedor (ej: una cuenta MP
 * por sucursal de una franquicia). Las credenciales se guardan ENCRIPTADAS
 * at-rest usando el cast 'encrypted' de Laravel (usa APP_KEY).
 *
 * Se guardan AMBOS sets (prod + test); el campo `modo` define cuál usar.
 *
 * Hooks: al guardar/eliminar, sincroniza `mercadopago_collector_index` en
 * DB config para que el webhook global pueda resolver multi-tenant sin
 * escanear N tenants.
 *
 * @property int $id
 * @property int $integracion_pago_id
 * @property int $sucursal_id
 * @property string $modo 'test' | 'produccion'
 * @property string|null $access_token_produccion
 * @property string|null $access_token_test
 * @property string|null $public_key_produccion
 * @property string|null $public_key_test
 * @property string|null $user_id_externo
 * @property string|null $webhook_secret
 * @property array|null $config_adicional
 * @property int $timeout_segundos
 * @property bool $activo
 */
class IntegracionPagoSucursal extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'integraciones_pago_sucursales';

    protected $fillable = [
        'integracion_pago_id',
        'sucursal_id',
        'modo',
        'access_token_produccion',
        'access_token_test',
        'public_key_produccion',
        'public_key_test',
        'user_id_externo',
        'webhook_secret',
        'config_adicional',
        'timeout_segundos',
        'activo',
    ];

    protected $casts = [
        'access_token_produccion' => 'encrypted',
        'access_token_test' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'config_adicional' => 'array',
        'timeout_segundos' => 'integer',
        'activo' => 'boolean',
    ];

    // Defaults — alineados con los DEFAULT de la migración. Necesarios para
    // que los hooks `saved` lean valores correctos cuando se crea sin pasar
    // explícitamente estos campos (Eloquent no refresca desde DB tras insert).
    protected $attributes = [
        'modo' => 'test',
        'timeout_segundos' => 300,
        'activo' => true,
    ];

    public const MODO_TEST = 'test';

    public const MODO_PRODUCCION = 'produccion';

    // ==================== Relaciones ====================

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(IntegracionPago::class, 'integracion_pago_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(IntegracionPagoTransaccion::class, 'integracion_pago_sucursal_id');
    }

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorIntegracion($query, int $integracionId)
    {
        return $query->where('integracion_pago_id', $integracionId);
    }

    public function scopeEnProduccion($query)
    {
        return $query->where('modo', self::MODO_PRODUCCION);
    }

    public function scopeEnTest($query)
    {
        return $query->where('modo', self::MODO_TEST);
    }

    // ==================== Helpers ====================

    /**
     * Devuelve el access_token correspondiente al modo activo (prod o test).
     */
    public function getAccessTokenActivo(): ?string
    {
        return $this->modo === self::MODO_PRODUCCION
            ? $this->access_token_produccion
            : $this->access_token_test;
    }

    /**
     * Devuelve la public_key correspondiente al modo activo.
     */
    public function getPublicKeyActivo(): ?string
    {
        return $this->modo === self::MODO_PRODUCCION
            ? $this->public_key_produccion
            : $this->public_key_test;
    }

    public function estaConfigurada(): bool
    {
        return ! empty($this->getAccessTokenActivo());
    }

    public function esProduccion(): bool
    {
        return $this->modo === self::MODO_PRODUCCION;
    }

    // ==================== Hooks ====================

    /**
     * Sincronización con `mercadopago_collector_index` (DB config) al guardar
     * o eliminar. Solo aplica si la integración es MercadoPago.
     *
     * Resuelve `comercio_id` vía TenantService (contexto activo). En CLI sin
     * sesión o tests sin trait WithTenant, no-op silenciosamente.
     */
    protected static function booted(): void
    {
        // Antes de guardar: si cambió user_id_externo o modo, borrar el registro
        // viejo del índice para evitar huérfanos.
        static::updating(function (self $config): void {
            $userIdCambio = $config->isDirty('user_id_externo');
            $modoCambio = $config->isDirty('modo');

            if ($userIdCambio || $modoCambio) {
                $userIdViejo = $config->getOriginal('user_id_externo');
                $modoViejo = $config->getOriginal('modo');
                $config->removerDelIndiceColectorPorClave($userIdViejo, $modoViejo);
            }
        });

        static::saved(function (self $config): void {
            $config->sincronizarIndiceColector();
        });

        static::deleted(function (self $config): void {
            $config->removerDelIndiceColector();
        });
    }

    /**
     * Inserta o actualiza la fila del índice global si la integración es MP
     * y hay user_id_externo cargado.
     *
     * Resuelve `comercio_id` desde TenantService (contexto actual). Si no hay
     * tenant resuelto (CLI sin sesión, tests sin trait WithTenant), no-op.
     */
    public function sincronizarIndiceColector(): void
    {
        if (! $this->user_id_externo) {
            return;
        }

        $integracion = $this->integracion()->first();
        if (! $integracion || $integracion->codigo !== IntegracionPago::CODIGO_MERCADOPAGO_QR) {
            return;
        }

        $comercioId = $this->resolverComercioIdDesdeContexto();
        if (! $comercioId) {
            return;
        }

        $now = now();
        $table = DB::connection('config')->table('mercadopago_collector_index');
        $existente = $table
            ->where('user_id_externo', $this->user_id_externo)
            ->where('modo', $this->modo)
            ->first();

        $datos = [
            'comercio_id' => $comercioId,
            'sucursal_id' => $this->sucursal_id,
            'integracion_pago_sucursal_id' => $this->id,
            'activo' => (bool) $this->activo,
            'updated_at' => $now,
        ];

        if ($existente) {
            $table->where('id', $existente->id)->update($datos);
        } else {
            $table->insert(array_merge($datos, [
                'user_id_externo' => $this->user_id_externo,
                'modo' => $this->modo,
                'created_at' => $now,
            ]));
        }
    }

    public function removerDelIndiceColector(): void
    {
        $this->removerDelIndiceColectorPorClave($this->user_id_externo, $this->modo);
    }

    /**
     * Borra una fila del índice por (user_id_externo, modo) específicos.
     * Útil cuando cambia user_id o modo y necesitamos limpiar el registro viejo
     * antes de insertar el nuevo.
     */
    protected function removerDelIndiceColectorPorClave(?string $userIdExterno, ?string $modo): void
    {
        if (! $userIdExterno || ! $modo) {
            return;
        }

        DB::connection('config')->table('mercadopago_collector_index')
            ->where('user_id_externo', $userIdExterno)
            ->where('modo', $modo)
            ->delete();
    }

    /**
     * Resuelve el comercio_id activo desde el TenantService.
     * Si no hay tenant (CLI sin sesión), devuelve null.
     */
    protected function resolverComercioIdDesdeContexto(): ?int
    {
        try {
            $tenantService = app(\App\Services\TenantService::class);
            $comercio = $tenantService->getComercio();

            return $comercio?->id;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
