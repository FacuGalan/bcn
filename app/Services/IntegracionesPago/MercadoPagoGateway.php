<?php

namespace App\Services\IntegracionesPago;

use App\Models\Caja;
use App\Models\Comercio;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\Contracts\IntegracionPagoGatewayContract;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementación del Gateway de Mercado Pago.
 *
 * Usa Laravel HTTP Client en vez del SDK oficial (mercadopago/dx-php) por:
 *  - simplicidad (los endpoints relevantes son REST plano);
 *  - testabilidad trivial con `Http::fake()`;
 *  - sin dependencia externa que MP rompe periódicamente.
 *
 * Fase 3 entrega `probarConexion` y `modosSoportados` funcionales. El resto
 * de los métodos del contrato son stubs que lanzan excepción hasta Fase 5.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 3 de 10).
 */
class MercadoPagoGateway implements IntegracionPagoGatewayContract
{
    private const API_BASE = 'https://api.mercadopago.com';

    private const TIMEOUT_DEFAULT = 15;

    public const MODO_QR_DINAMICO = 'qr_dinamico';

    public const MODO_QR_ESTATICO = 'qr_estatico';

    public function modosSoportados(): array
    {
        return [
            self::MODO_QR_DINAMICO,
            self::MODO_QR_ESTATICO,
        ];
    }

    /**
     * Verifica las credenciales contra `GET /users/me`.
     *
     * Si responde 200 y el `id` del payload coincide con `user_id_externo`
     * configurado, devuelve los datos de la cuenta para mostrar en UI. Si no,
     * lanza excepción con mensaje claro.
     *
     * @throws \RuntimeException con mensaje legible para el usuario
     */
    public function probarConexion(IntegracionPagoSucursal $config): array
    {
        $token = $config->getAccessTokenActivo();

        if (empty($token)) {
            throw new \RuntimeException(__('No hay Access Token cargado para el modo actual'));
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(self::TIMEOUT_DEFAULT)
                ->get(self::API_BASE.'/users/me');
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoGateway::probarConexion - error de red', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(__('No se pudo conectar con Mercado Pago').': '.$e->getMessage());
        }

        if ($response->status() === 401) {
            throw new \RuntimeException(__('Access Token inválido o vencido'));
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? __('Error desconocido');

            throw new \RuntimeException(__('Mercado Pago respondió error HTTP ').$response->status().': '.$msg);
        }

        $data = $response->json();
        $idDevuelto = (string) ($data['id'] ?? '');

        // Si la sucursal tiene user_id_externo configurado, debe coincidir.
        // Si no lo tiene, lo damos por OK (lo cargarán después).
        if (! empty($config->user_id_externo) && $idDevuelto !== (string) $config->user_id_externo) {
            throw new \RuntimeException(__(
                'El User ID configurado (:configurado) no coincide con el de la cuenta MP (:real). Verifique que las credenciales pertenecen a la cuenta correcta.',
                ['configurado' => $config->user_id_externo, 'real' => $idDevuelto]
            ));
        }

        Log::info('MercadoPagoGateway::probarConexion - OK', [
            'config_id' => $config->id,
            'mp_user_id' => $idDevuelto,
            'nickname' => $data['nickname'] ?? null,
        ]);

        return [
            'id' => $idDevuelto,
            'nickname' => $data['nickname'] ?? null,
            'email' => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'modo' => $config->modo,
        ];
    }

    // ==================== Stores ====================

    /**
     * Genera el external_id usado para identificar una Store de BCN en MP.
     * Formato: BCN-{comercio_id}-{sucursal_id}. Máximo 60 chars (límite MP).
     */
    public static function externalIdStore(int $comercioId, int $sucursalId): string
    {
        return "BCN-{$comercioId}-{$sucursalId}";
    }

    /**
     * Genera el external_id usado para identificar un POS de BCN en MP.
     * Formato: BCN-{comercio_id}-POS-{caja_id}. Máximo 40 chars (límite MP).
     */
    public static function externalIdPos(int $comercioId, int $cajaId): string
    {
        return "BCN-{$comercioId}-POS-{$cajaId}";
    }

    /**
     * POST /users/{user_id}/stores — crea una sucursal en MP.
     *
     * @return array Response completa de MP (incluye `id` de la store creada)
     *
     * @throws \RuntimeException si la respuesta no es 200/201
     */
    public function crearStore(IntegracionPagoSucursal $config, Sucursal $sucursal, int $comercioId): array
    {
        $this->guardCoordenadas($sucursal);

        $externalId = self::externalIdStore($comercioId, $sucursal->id);

        $payload = $this->buildStorePayload($sucursal, $externalId);

        $response = $this->client($config)
            ->post(self::API_BASE.'/users/'.$config->user_id_externo.'/stores', $payload);

        $this->guardResponse($response, 'crearStore');

        Log::info('MercadoPagoGateway::crearStore OK', [
            'sucursal_id' => $sucursal->id,
            'mp_store_id' => $response->json('id'),
        ]);

        return $response->json();
    }

    /**
     * PUT /users/{user_id}/stores/{store_id} — actualiza datos de una store.
     */
    public function actualizarStore(IntegracionPagoSucursal $config, Sucursal $sucursal, int $comercioId): array
    {
        $this->guardCoordenadas($sucursal);

        if (empty($sucursal->mp_store_id)) {
            throw new \RuntimeException(__('La sucursal no tiene un Store creado en Mercado Pago'));
        }

        $externalId = $sucursal->mp_store_external_id ?: self::externalIdStore($comercioId, $sucursal->id);
        $payload = $this->buildStorePayload($sucursal, $externalId);

        $response = $this->client($config)
            ->put(self::API_BASE.'/users/'.$config->user_id_externo.'/stores/'.$sucursal->mp_store_id, $payload);

        $this->guardResponse($response, 'actualizarStore');

        return $response->json();
    }

    /**
     * DELETE /users/{user_id}/stores/{store_id}
     */
    public function eliminarStore(IntegracionPagoSucursal $config, string $storeId): bool
    {
        $response = $this->client($config)
            ->delete(self::API_BASE.'/users/'.$config->user_id_externo.'/stores/'.$storeId);

        if ($response->status() === 404) {
            // Ya no existía: lo damos por borrado.
            return true;
        }

        $this->guardResponse($response, 'eliminarStore');

        return true;
    }

    // ==================== POS ====================

    /**
     * POST /pos — crea una caja (POS) en MP, asociada a una store.
     *
     * @return array Response completa de MP (incluye `id`, `qr.image`, etc.)
     */
    public function crearPos(IntegracionPagoSucursal $config, Caja $caja, Sucursal $sucursal, ?string $rubro, int $comercioId): array
    {
        if (empty($sucursal->mp_store_id) || empty($sucursal->mp_store_external_id)) {
            throw new \RuntimeException(__('La sucursal debe sincronizarse primero en Mercado Pago antes de crear cajas'));
        }

        $externalId = self::externalIdPos($comercioId, $caja->id);

        $payload = $this->buildPosPayload($caja, $sucursal, $externalId, $rubro);

        $response = $this->client($config)->post(self::API_BASE.'/pos', $payload);

        $this->guardResponse($response, 'crearPos');

        Log::info('MercadoPagoGateway::crearPos OK', [
            'caja_id' => $caja->id,
            'mp_pos_id' => $response->json('id'),
        ]);

        return $response->json();
    }

    /**
     * PUT /pos/{pos_id} — actualiza datos del POS.
     */
    public function actualizarPos(IntegracionPagoSucursal $config, Caja $caja, Sucursal $sucursal, ?string $rubro, int $comercioId): array
    {
        if (empty($caja->mp_pos_id)) {
            throw new \RuntimeException(__('La caja no tiene un POS creado en Mercado Pago'));
        }

        $externalId = $caja->mp_pos_external_id ?: self::externalIdPos($comercioId, $caja->id);
        $payload = $this->buildPosPayload($caja, $sucursal, $externalId, $rubro);

        $response = $this->client($config)->put(self::API_BASE.'/pos/'.$caja->mp_pos_id, $payload);

        $this->guardResponse($response, 'actualizarPos');

        return $response->json();
    }

    /**
     * DELETE /pos/{pos_id}
     */
    public function eliminarPos(IntegracionPagoSucursal $config, string $posId): bool
    {
        $response = $this->client($config)->delete(self::API_BASE.'/pos/'.$posId);

        if ($response->status() === 404) {
            return true;
        }

        $this->guardResponse($response, 'eliminarPos');

        return true;
    }

    // ==================== Helpers internos ====================

    private function client(IntegracionPagoSucursal $config): PendingRequest
    {
        $token = $config->getAccessTokenActivo();

        if (empty($token)) {
            throw new \RuntimeException(__('No hay Access Token cargado para el modo actual'));
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_DEFAULT);
    }

    private function guardCoordenadas(Sucursal $sucursal): void
    {
        if (! $sucursal->tieneCoordenadas()) {
            throw new \RuntimeException(__('La sucursal no tiene coordenadas (latitud/longitud) configuradas. Mercado Pago requiere coordenadas para crear la sucursal.'));
        }
        if (empty($sucursal->direccion)) {
            throw new \RuntimeException(__('La sucursal no tiene dirección configurada'));
        }
    }

    private function guardResponse(\Illuminate\Http\Client\Response $response, string $contexto): void
    {
        if ($response->successful()) {
            return;
        }

        $msg = $response->json('message') ?? $response->body() ?? __('Error desconocido');
        Log::warning("MercadoPagoGateway::{$contexto} error", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException(__('Mercado Pago respondió error HTTP ').$response->status().': '.$msg);
    }

    /**
     * Construye el payload para crear/actualizar una Store.
     * Parsea `direccion` como string libre — el usuario debe asegurar que
     * sea descriptiva (no la parseamos en piezas porque sería frágil).
     */
    private function buildStorePayload(Sucursal $sucursal, string $externalId): array
    {
        return [
            'name' => $sucursal->nombre_publico ?: $sucursal->nombre,
            'external_id' => $externalId,
            'location' => [
                'street_name' => (string) ($sucursal->direccion ?? ''),
                'street_number' => '0',
                'city_name' => '',
                'state_name' => '',
                'latitude' => (float) $sucursal->latitud,
                'longitude' => (float) $sucursal->longitud,
                'reference' => $sucursal->direccion ?? '',
            ],
        ];
    }

    /**
     * Construye el payload para crear/actualizar un POS.
     * `category` solo se incluye si el comercio es gastronomía o estación
     * de servicio (los únicos rubros que MP acepta para QR Code).
     */
    private function buildPosPayload(Caja $caja, Sucursal $sucursal, string $externalId, ?string $rubro): array
    {
        $payload = [
            'name' => $caja->nombre,
            'fixed_amount' => true,
            'store_id' => (int) $sucursal->mp_store_id,
            'external_store_id' => $sucursal->mp_store_external_id,
            'external_id' => $externalId,
        ];

        $category = $this->resolverCategory($rubro);
        if ($category !== null) {
            $payload['category'] = $category;
        }

        return $payload;
    }

    /**
     * Mapea el rubro del comercio al código MCC de MP.
     * Códigos según doc oficial — solo se aceptan estos dos para QR Code.
     */
    private function resolverCategory(?string $rubro): ?int
    {
        return match ($rubro) {
            Comercio::RUBRO_GASTRONOMIA => 621102,
            Comercio::RUBRO_ESTACION_SERVICIO => 443001,
            default => null,
        };
    }

    // ==================== Stubs Fase 5 ====================

    public function iniciarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array {
        throw new \BadMethodCallException('iniciarCobro será implementado en Fase 5');
    }

    public function consultarEstado(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array {
        throw new \BadMethodCallException('consultarEstado será implementado en Fase 5');
    }

    public function cancelarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): bool {
        throw new \BadMethodCallException('cancelarCobro será implementado en Fase 5');
    }

    public function procesarWebhook(array $payload, array $headers): array
    {
        throw new \BadMethodCallException('procesarWebhook será implementado en Fase 6');
    }
}
