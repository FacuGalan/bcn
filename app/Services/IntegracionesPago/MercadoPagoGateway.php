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

    public const MODO_POINT = 'point';

    public function modosSoportados(): array
    {
        return [
            self::MODO_QR_DINAMICO,
            self::MODO_QR_ESTATICO,
            self::MODO_POINT,
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
     * Formato: BCN{comercio_id}POS{caja_id}. Máximo 40 chars (límite MP).
     *
     * SIN guiones: el endpoint de POS exige `external_id` estrictamente
     * alfanumérico (HTTP 400 `invalid_external_id` si no). La palabra "POS"
     * actúa de separador, así que el id sigue siendo único e inequívoco.
     * (El endpoint de Store sí acepta guiones — por eso `externalIdStore` los usa.)
     */
    public static function externalIdPos(int $comercioId, int $cajaId): string
    {
        return "BCN{$comercioId}POS{$cajaId}";
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

        $payload = $this->buildStorePayload($sucursal, $externalId, $this->nombreComercio($comercioId));

        $response = $this->client($config)
            ->post(self::API_BASE.'/users/'.$config->user_id_externo.'/stores', $payload);

        // Recuperación idempotente: si MP responde que el external_id ya está
        // asignado (la store ya existe en esta cuenta — típico al re-sincronizar
        // o al compartir credenciales entre entornos), la adoptamos buscándola
        // por external_id y la actualizamos en vez de fallar.
        if ($this->esErrorExternalIdDuplicado($response)) {
            $existente = $this->buscarStorePorExternalId($config, $externalId);
            if ($existente !== null && ! empty($existente['id'])) {
                Log::info('MercadoPagoGateway::crearStore - external_id ya existía en MP, adoptando store existente', [
                    'sucursal_id' => $sucursal->id,
                    'external_id' => $externalId,
                    'mp_store_id' => $existente['id'],
                ]);

                $sucursal->mp_store_id = (string) $existente['id'];
                $sucursal->mp_store_external_id = $externalId;

                return $this->actualizarStore($config, $sucursal, $comercioId);
            }
        }

        $this->guardResponse($response, 'crearStore');

        Log::info('MercadoPagoGateway::crearStore OK', [
            'sucursal_id' => $sucursal->id,
            'mp_store_id' => $response->json('id'),
        ]);

        return $response->json();
    }

    /**
     * PUT /users/{user_id}/stores/{store_id} — actualiza datos de una store.
     *
     * NO se envía `external_id`: MP valida su unicidad incluso en el update y
     * lo rechaza por estar "ya asignado" a la propia store que se actualiza
     * (HTTP 400). La store ya queda identificada por el `store_id` de la URL.
     */
    public function actualizarStore(IntegracionPagoSucursal $config, Sucursal $sucursal, int $comercioId): array
    {
        $this->guardCoordenadas($sucursal);

        if (empty($sucursal->mp_store_id)) {
            throw new \RuntimeException(__('La sucursal no tiene un Store creado en Mercado Pago'));
        }

        $payload = $this->buildStorePayload($sucursal, null, $this->nombreComercio($comercioId));

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

        // Recuperación idempotente (mismo criterio que crearStore): si el POS ya
        // existe en esta cuenta MP, lo adoptamos por external_id y lo actualizamos.
        if ($this->esErrorExternalIdDuplicado($response)) {
            $existente = $this->buscarPosPorExternalId($config, $externalId);
            if ($existente !== null && ! empty($existente['id'])) {
                Log::info('MercadoPagoGateway::crearPos - external_id ya existía en MP, adoptando POS existente', [
                    'caja_id' => $caja->id,
                    'external_id' => $externalId,
                    'mp_pos_id' => $existente['id'],
                ]);

                $caja->mp_pos_id = (string) $existente['id'];
                $caja->mp_pos_external_id = $externalId;

                $actualizado = $this->actualizarPos($config, $caja, $sucursal, $rubro, $comercioId);

                // El PUT de POS no siempre devuelve el QR; conservamos el del
                // POS existente para no perder la URL del código.
                if (empty($actualizado['qr']) && ! empty($existente['qr'])) {
                    $actualizado['qr'] = $existente['qr'];
                }

                return $actualizado;
            }
        }

        $this->guardResponse($response, 'crearPos');

        Log::info('MercadoPagoGateway::crearPos OK', [
            'caja_id' => $caja->id,
            'mp_pos_id' => $response->json('id'),
        ]);

        return $response->json();
    }

    /**
     * PUT /pos/{pos_id} — actualiza datos del POS.
     *
     * NO se envía `external_id` (mismo motivo que `actualizarStore`): MP lo
     * rechaza por estar "ya asignado" al propio POS. Se identifica por la URL.
     */
    public function actualizarPos(IntegracionPagoSucursal $config, Caja $caja, Sucursal $sucursal, ?string $rubro, int $comercioId): array
    {
        if (empty($caja->mp_pos_id)) {
            throw new \RuntimeException(__('La caja no tiene un POS creado en Mercado Pago'));
        }

        $payload = $this->buildPosPayload($caja, $sucursal, null, $rubro);

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

    /**
     * Busca una Store de la cuenta por su external_id.
     * GET /users/{user_id}/stores/search?external_id=...
     *
     * @return array|null La store encontrada (con `id`, `external_id`, etc.) o null.
     */
    public function buscarStorePorExternalId(IntegracionPagoSucursal $config, string $externalId): ?array
    {
        $response = $this->client($config)
            ->get(self::API_BASE.'/users/'.$config->user_id_externo.'/stores/search', [
                'external_id' => $externalId,
            ]);

        if (! $response->successful()) {
            Log::warning('MercadoPagoGateway::buscarStorePorExternalId - búsqueda falló', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $this->matchPorExternalId($response->json('results') ?? [], $externalId);
    }

    /**
     * Busca un POS de la cuenta por su external_id.
     * GET /pos?external_id=... (el endpoint de POS NO tiene /search; el listado
     * filtra por query params y devuelve `results`).
     *
     * @return array|null El POS encontrado (con `id`, `qr`, etc.) o null.
     */
    public function buscarPosPorExternalId(IntegracionPagoSucursal $config, string $externalId): ?array
    {
        $response = $this->client($config)
            ->get(self::API_BASE.'/pos', [
                'external_id' => $externalId,
            ]);

        if (! $response->successful()) {
            Log::warning('MercadoPagoGateway::buscarPosPorExternalId - búsqueda falló', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $this->matchPorExternalId($response->json('results') ?? [], $externalId);
    }

    // ==================== Helpers internos ====================

    /**
     * Detecta que MP rechazó la creación porque el recurso ya existe en la
     * cuenta — disparador de la recuperación idempotente (adoptar el existente).
     *
     * MP no es consistente entre endpoints:
     *  - Store: HTTP 400 "external id '...' is already assigned to this user".
     *  - POS:   HTTP 409 "Point of sale with corresponding user and id exists"
     *           (error code `point_of_sale_exists`).
     *
     * Un 409 Conflict en una creación siempre significa "ya existe", así que lo
     * tratamos como duplicado directamente; para el 400 exigimos el texto
     * conocido para no confundirlo con un error de validación.
     */
    private function esErrorExternalIdDuplicado(\Illuminate\Http\Client\Response $response): bool
    {
        if ($response->status() === 409) {
            return true;
        }

        if ($response->status() !== 400) {
            return false;
        }

        $cuerpo = strtolower($response->body());

        return str_contains($cuerpo, 'already assigned')
            || str_contains($cuerpo, 'already exists');
    }

    /**
     * Devuelve el resultado cuyo external_id coincide exactamente; si ninguno
     * coincide, cae al primero de la lista (la búsqueda ya filtró por external_id).
     *
     * @param  array<int, array>  $resultados
     */
    private function matchPorExternalId(array $resultados, string $externalId): ?array
    {
        foreach ($resultados as $item) {
            if (($item['external_id'] ?? null) === $externalId) {
                return $item;
            }
        }

        return $resultados[0] ?? null;
    }

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
        if (empty($sucursal->localidad)) {
            throw new \RuntimeException(__('La sucursal no tiene localidad configurada (Mercado Pago la requiere)'));
        }
        if (empty($sucursal->provincia)) {
            throw new \RuntimeException(__('La sucursal no tiene provincia configurada (Mercado Pago la requiere)'));
        }
    }

    private function guardResponse(\Illuminate\Http\Client\Response $response, string $contexto): void
    {
        if ($response->successful()) {
            return;
        }

        // MP suele devolver el detalle en distintas claves segun el endpoint:
        // `message` (texto), `error` (codigo), `cause` (array con descripciones).
        $json = $response->json() ?? [];
        $partes = [];

        if (! empty($json['message'])) {
            $partes[] = $json['message'];
        }
        if (! empty($json['error']) && $json['error'] !== ($json['message'] ?? null)) {
            $partes[] = '['.$json['error'].']';
        }
        if (! empty($json['cause']) && is_array($json['cause'])) {
            foreach ($json['cause'] as $c) {
                if (is_array($c)) {
                    $causeDesc = $c['description'] ?? $c['message'] ?? json_encode($c);
                    $causeCode = isset($c['code']) ? " (code {$c['code']})" : '';
                    $partes[] = $causeDesc.$causeCode;
                } elseif (is_string($c)) {
                    $partes[] = $c;
                }
            }
        }

        $detalle = ! empty($partes) ? implode(' — ', $partes) : ($response->body() ?: __('Error desconocido'));

        Log::warning("MercadoPagoGateway::{$contexto} error", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException(__('Mercado Pago respondió error HTTP ').$response->status().': '.$detalle);
    }

    /**
     * Construye el payload para crear/actualizar una Store.
     *
     * `external_id` solo se incluye al crear (se pasa el valor); en updates se
     * pasa null y se omite, porque MP lo rechaza por colisión consigo mismo.
     *
     * `direccion` se intenta separar en street_name + street_number (regex
     * simple: lo último que parezca un número se considera altura). Si no
     * se detecta número, se manda 'S/N' como fallback.
     */
    private function buildStorePayload(Sucursal $sucursal, ?string $externalId, string $nombreComercio): array
    {
        [$streetName, $streetNumber] = $this->splitDireccion((string) $sucursal->direccion);

        $payload = [
            // Nombre que ve el pagador en Mercado Pago: el nombre público de la
            // sucursal si lo tiene; si no, el nombre del comercio (no el interno
            // de la sucursal ni el de la cuenta MP del integrador).
            'name' => $sucursal->nombre_publico ?: ($nombreComercio ?: $sucursal->nombre),
            'location' => [
                'street_name' => $streetName,
                'street_number' => $streetNumber,
                'city_name' => (string) $sucursal->localidad,
                'state_name' => (string) ($sucursal->provinciaNombre() ?? $sucursal->provincia),
                'latitude' => (float) $sucursal->latitud,
                'longitude' => (float) $sucursal->longitud,
                'reference' => $sucursal->direccion ?? '',
            ],
        ];

        if ($externalId !== null) {
            $payload['external_id'] = $externalId;
        }

        return $payload;
    }

    /**
     * Nombre comercial del comercio (tenant) para usarlo como nombre visible de
     * la Store en MP cuando la sucursal no tiene nombre público propio.
     */
    private function nombreComercio(int $comercioId): string
    {
        return (string) (\App\Models\Comercio::find($comercioId)?->nombre ?? '');
    }

    /**
     * Separa "Av. Corrientes 1234" en ["Av. Corrientes", "1234"].
     * Si no detecta número al final, devuelve ["dirección completa", "S/N"].
     *
     * @return array{0: string, 1: string}
     */
    private function splitDireccion(string $direccion): array
    {
        $direccion = trim($direccion);

        if ($direccion === '') {
            return ['', 'S/N'];
        }

        if (preg_match('/^(.+?)\s+(\d[\d\-\/]*)\s*$/u', $direccion, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$direccion, 'S/N'];
    }

    /**
     * Construye el payload para crear/actualizar un POS.
     *
     * `external_id` solo se incluye al crear; en updates se pasa null y se
     * omite (MP lo rechaza por colisión consigo mismo, igual que en Store).
     *
     * `category` solo se incluye si el comercio es gastronomía o estación
     * de servicio (los únicos rubros que MP acepta para QR Code).
     */
    private function buildPosPayload(Caja $caja, Sucursal $sucursal, ?string $externalId, ?string $rubro): array
    {
        $payload = [
            'name' => $caja->nombre,
            'fixed_amount' => true,
            'store_id' => (int) $sucursal->mp_store_id,
            'external_store_id' => $sucursal->mp_store_external_id,
        ];

        if ($externalId !== null) {
            $payload['external_id'] = $externalId;
        }

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

    // ==================== Cobro QR dinámico (Orders API) ====================

    /**
     * Inicia un cobro QR creando una Order en MP (POST /v1/orders).
     *
     * Orders API nueva: el modo del QR se define por `config.qr.mode`, mapeado
     * desde `transaccion->modo_usado`:
     *  - `qr_dinamico` → `dynamic`: genera un QR único por transacción
     *    (`type_response.qr_data`) que la app renderiza y muestra en pantalla.
     *  - `qr_estatico` → `static`: NO devuelve `qr_data`. La orden con monto se
     *    "encola" en el POS de la caja y el cliente escanea el QR FÍSICO impreso
     *    del POS (cuya imagen guardamos en `caja->mp_pos_qr_url` al sincronizar
     *    en Fase 3.5). Equivale al comportamiento legacy de Órdenes presenciales.
     *
     * En ambos modos la orden lleva `external_reference` y notifica por el mismo
     * tópico "Order" (Fase 6) → el matching del webhook por `external_id` sirve
     * para los dos sin distinción. Acá solo se crea la orden.
     *
     * @return array{qr_data: ?string, qr_image_url: ?string, external_reference: string, external_id: string, payload: array}
     */
    public function iniciarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array {
        // Point usa otra rama: terminal física (device), no POS/QR.
        if ($transaccion->modo_usado === self::MODO_POINT) {
            return $this->iniciarCobroPoint($config, $transaccion);
        }

        $caja = $transaccion->caja;

        if (! $caja || ! $caja->estaSincronizadaEnMp() || empty($caja->mp_pos_external_id)) {
            throw new \RuntimeException(__('La caja no tiene un punto de venta (POS) sincronizado en Mercado Pago. Sincronícela antes de cobrar con QR.'));
        }

        $modoQr = $this->mapearModoOrdersApi($transaccion->modo_usado);
        $externalReference = 'BCN-TX-'.$transaccion->id;
        $monto = number_format((float) $transaccion->monto, 2, '.', '');

        $payload = [
            'type' => 'qr',
            'external_reference' => $externalReference,
            'total_amount' => $monto,
            'config' => [
                'qr' => [
                    'external_pos_id' => $caja->mp_pos_external_id,
                    'mode' => $modoQr,
                ],
            ],
            'transactions' => [
                'payments' => [
                    ['amount' => $monto],
                ],
            ],
        ];

        // Idempotencia: misma transacción ⇒ mismo body ⇒ MP devuelve la order
        // original sin recrearla. Un reintento (RF-11) usa otra transacción.
        $response = $this->client($config)
            ->withHeaders(['X-Idempotency-Key' => 'order-'.$externalReference])
            ->post(self::API_BASE.'/v1/orders', $payload);

        $this->guardResponse($response, 'iniciarCobro');

        $data = $response->json();
        $qrData = $data['type_response']['qr_data'] ?? null;

        // En dinámico es obligatorio que MP devuelva la trama del QR; en estático
        // no aplica (se usa el QR impreso del POS), así que no se exige.
        if ($modoQr === 'dynamic' && empty($qrData)) {
            Log::warning('MercadoPagoGateway::iniciarCobro - respuesta sin qr_data', [
                'transaccion_id' => $transaccion->id,
                'order_id' => $data['id'] ?? null,
            ]);

            throw new \RuntimeException(__('Mercado Pago no devolvió el código QR de la orden'));
        }

        Log::info('MercadoPagoGateway::iniciarCobro OK', [
            'transaccion_id' => $transaccion->id,
            'order_id' => $data['id'] ?? null,
            'modo' => $modoQr,
        ]);

        return [
            'qr_data' => $qrData,
            // En estático, la app muestra la imagen del QR impreso del POS.
            'qr_image_url' => $modoQr === 'static' ? $caja->mp_pos_qr_url : null,
            'external_reference' => $externalReference,
            'external_id' => (string) ($data['id'] ?? ''),
            'payload' => $data,
        ];
    }

    /**
     * Mapea el `modo_usado` interno (espejo de `modos_disponibles`) al valor de
     * `config.qr.mode` de la Orders API. Default `dynamic` ante valores no
     * reconocidos (no rompe el cobro por una config inesperada).
     */
    private function mapearModoOrdersApi(?string $modoUsado): string
    {
        return match ($modoUsado) {
            self::MODO_QR_ESTATICO, 'static' => 'static',
            default => 'dynamic',
        };
    }

    // ==================== Cobro Point (Orders API, terminal física) ====================

    /**
     * Inicia un cobro en una terminal Point física creando una Order en MP
     * (POST /v1/orders con `type: "point"`). El sistema EMPUJA el monto a la
     * terminal asignada a la caja (`caja->mp_point_terminal_id`); el cliente paga
     * con tarjeta o con el QR que muestra el PROPIO aparato. No devuelve QR para
     * renderizar acá: `qr_data` queda null y el modal muestra "esperando en la
     * terminal".
     *
     * Parámetros específicos de Point (los arma el wiring del cobro en
     * `metadata['point']`): `default_type` (credit_card|debit_card|qr; ausente =
     * "Abierto", la terminal acepta todos) e `installments` (solo credit_card).
     *
     * @return array{qr_data: ?string, qr_image_url: ?string, external_reference: string, external_id: string, payload: array}
     */
    private function iniciarCobroPoint(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array {
        $caja = $transaccion->caja;

        if (! $caja || empty($caja->mp_point_terminal_id)) {
            throw new \RuntimeException(__('La caja no tiene una terminal Point de Mercado Pago asignada. Vincúlela antes de cobrar con Point.'));
        }

        $externalReference = 'BCN-TX-'.$transaccion->id;
        $monto = number_format((float) $transaccion->monto, 2, '.', '');

        // payment_method: solo se envía si la FP definió un default_type. "Abierto"
        // (null) ⇒ se omite y el cliente elige el medio en el aparato.
        $point = $transaccion->metadata['point'] ?? [];
        $defaultType = $point['default_type'] ?? null;
        $installments = $point['installments'] ?? null;

        $configPayload = [
            'point' => [
                'terminal_id' => $caja->mp_point_terminal_id,
                'print_on_terminal' => 'no_ticket',
            ],
        ];

        if (! empty($defaultType)) {
            $paymentMethod = ['default_type' => $defaultType];
            // Las cuotas solo aplican a crédito.
            if ($defaultType === 'credit_card' && ! empty($installments)) {
                $paymentMethod['default_installments'] = (int) $installments;
            }
            $configPayload['payment_method'] = $paymentMethod;
        }

        $payload = [
            'type' => 'point',
            'external_reference' => $externalReference,
            'expiration_time' => $this->expirationTimeIso($config),
            'transactions' => [
                'payments' => [
                    ['amount' => $monto],
                ],
            ],
            'config' => $configPayload,
        ];

        $response = $this->client($config)
            ->withHeaders(['X-Idempotency-Key' => 'order-'.$externalReference])
            ->post(self::API_BASE.'/v1/orders', $payload);

        $this->guardResponse($response, 'iniciarCobroPoint');

        $data = $response->json();

        Log::info('MercadoPagoGateway::iniciarCobroPoint OK', [
            'transaccion_id' => $transaccion->id,
            'order_id' => $data['id'] ?? null,
            'terminal_id' => $caja->mp_point_terminal_id,
            'default_type' => $defaultType ?? 'abierto',
        ]);

        return [
            'qr_data' => null,
            'qr_image_url' => null,
            'external_reference' => $externalReference,
            'external_id' => (string) ($data['id'] ?? ''),
            'payload' => $data,
        ];
    }

    /**
     * Construye el `expiration_time` ISO 8601 de la order a partir del timeout
     * configurado de la sucursal, acotado al rango que acepta MP: PT30S..PT3H.
     */
    private function expirationTimeIso(IntegracionPagoSucursal $config): string
    {
        $segundos = (int) ($config->timeout_segundos ?: 300);
        $segundos = max(30, min($segundos, 10800));

        return 'PT'.$segundos.'S';
    }

    // ==================== Terminales Point (Integration API) ====================

    /**
     * Lista las terminales (devices) Point asociadas a la cuenta MP de esta
     * config (`GET /terminals/v1/list`). Devuelve el array de terminales con
     * `id`, `pos_id`, `store_id`, `external_pos_id` y `operating_mode`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTerminales(IntegracionPagoSucursal $config): array
    {
        $response = $this->client($config)
            ->get(self::API_BASE.'/terminals/v1/list', ['limit' => 50]);

        $this->guardResponse($response, 'listarTerminales');

        return $response->json()['data']['terminals'] ?? [];
    }

    /**
     * Pone una terminal en modo integrado / PDV (`PATCH /terminals/v1/setup`),
     * requisito para poder empujarle cobros desde el sistema. Devuelve el
     * payload de respuesta de MP.
     *
     * @return array<string, mixed>
     */
    public function activarModoPDV(IntegracionPagoSucursal $config, string $terminalId): array
    {
        $response = $this->client($config)
            ->patch(self::API_BASE.'/terminals/v1/setup', [
                'terminals' => [
                    ['id' => $terminalId, 'operating_mode' => 'PDV'],
                ],
            ]);

        $this->guardResponse($response, 'activarModoPDV');

        return $response->json() ?? [];
    }

    /**
     * Consulta el estado de la order en MP (GET /v1/orders/{id}).
     * Fallback de polling; la confirmación primaria llega por webhook.
     *
     * @return array{estado: string, payload: array} estado normalizado
     */
    public function consultarEstado(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array {
        if (empty($transaccion->external_id)) {
            throw new \RuntimeException(__('La transacción no tiene un identificador de orden de Mercado Pago'));
        }

        $response = $this->client($config)
            ->get(self::API_BASE.'/v1/orders/'.$transaccion->external_id);

        $this->guardResponse($response, 'consultarEstado');

        $data = $response->json();

        return [
            'estado' => $this->normalizarEstadoOrder($data['status'] ?? ''),
            'payload' => $data,
        ];
    }

    /**
     * Cancela la order pendiente en MP (POST /v1/orders/{id}/cancel).
     * Devuelve true si MP confirma la cancelación.
     */
    public function cancelarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): bool {
        if (empty($transaccion->external_id)) {
            return false;
        }

        $headers = ['X-Idempotency-Key' => 'cancel-'.$transaccion->external_id];

        // Point: la order suele estar `at_terminal` (esperando en el aparato);
        // MP exige este header para permitir cancelarla en ese estado.
        if ($transaccion->modo_usado === self::MODO_POINT) {
            $headers['x-allow-cancelable-status'] = 'at_terminal';
        }

        $response = $this->client($config)
            ->withHeaders($headers)
            ->post(self::API_BASE.'/v1/orders/'.$transaccion->external_id.'/cancel');

        if ($response->successful()) {
            return true;
        }

        Log::warning('MercadoPagoGateway::cancelarCobro - MP no canceló la orden', [
            'transaccion_id' => $transaccion->id,
            'order_id' => $transaccion->external_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    /**
     * Normaliza el `status` de una Order de MP al vocabulario interno.
     * Estados MP: created | processed | canceled | expired | refunded.
     * Los pagos rechazados NO se exponen (la order permanece en 'created').
     */
    private function normalizarEstadoOrder(string $status): string
    {
        return match ($status) {
            'processed' => 'aprobado',
            'canceled' => 'cancelado',
            'expired' => 'expirado',
            default => 'pendiente', // created, refunded, vacío, etc.
        };
    }

    // ==================== Webhook (Fase 6) ====================

    /**
     * Parsea una notificación entrante de Mercado Pago (Orders API) a un formato
     * normalizado. NO consulta la API ni verifica firma (no tiene credenciales
     * acá): solo extrae lo necesario para resolver el tenant y la transacción.
     * El orquestador (MercadoPagoWebhookService) hace la resolución, la
     * verificación de firma y el re-chequeo del estado real.
     *
     * MP combina query string y body; el caller suele pasar el merge de ambos.
     * Tópico de interés: "order" (Orders API). El id de la order viene en
     * `data.id` (o en `resource` como URL/id).
     *
     * @return array{tipo: ?string, order_id: ?string, user_id_externo: ?string}
     */
    public function procesarWebhook(array $payload, array $headers): array
    {
        $tipo = $payload['type'] ?? $payload['topic'] ?? null;

        // id de la order: data.id (preferido), o el final de `resource` (URL/id).
        // OJO: PHP convierte `data.id` del query string en `data_id`.
        $orderId = $payload['data']['id'] ?? $payload['data.id'] ?? $payload['data_id'] ?? null;
        if (empty($orderId) && ! empty($payload['resource'])) {
            $resource = (string) $payload['resource'];
            $orderId = str_contains($resource, '/') ? substr($resource, strrpos($resource, '/') + 1) : $resource;
        }

        // collector (cuenta MP): puede venir como user_id (top-level) o anidado.
        $userId = $payload['user_id']
            ?? $payload['data']['collector_id']
            ?? $payload['collector_id']
            ?? null;

        return [
            'tipo' => $tipo !== null ? (string) $tipo : null,
            'order_id' => $orderId !== null ? (string) $orderId : null,
            'user_id_externo' => $userId !== null ? (string) $userId : null,
        ];
    }

    /**
     * Verifica la firma `x-signature` de una notificación de Mercado Pago.
     *
     * MP firma con HMAC-SHA256 sobre el manifest
     * `id:{dataId};request-id:{xRequestId};ts:{ts};` usando el secret del webhook
     * configurado en el panel de MP. El header `x-signature` trae `ts=...,v1=...`.
     *
     * @param  array<string, string>  $headers  headers normalizados a string
     */
    public function verificarFirma(string $secret, array $headers, string $dataId): bool
    {
        $xSignature = $headers['x-signature'] ?? null;
        $xRequestId = $headers['x-request-id'] ?? '';

        if (empty($xSignature)) {
            return false;
        }

        $ts = null;
        $v1 = null;
        foreach (explode(',', $xSignature) as $parte) {
            $kv = array_pad(explode('=', trim($parte), 2), 2, null);
            $clave = trim((string) $kv[0]);
            $valor = $kv[1] !== null ? trim((string) $kv[1]) : null;
            if ($clave === 'ts') {
                $ts = $valor;
            } elseif ($clave === 'v1') {
                $v1 = $valor;
            }
        }

        if (empty($ts) || empty($v1)) {
            return false;
        }

        // MP exige el data.id en minúsculas en el manifest.
        $manifest = 'id:'.strtolower($dataId).';request-id:'.$xRequestId.';ts:'.$ts.';';
        $calculado = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($calculado, $v1);
    }
}
