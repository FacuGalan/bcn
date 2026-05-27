<?php

namespace App\Services\IntegracionesPago;

use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Services\IntegracionesPago\Contracts\IntegracionPagoGatewayContract;
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
