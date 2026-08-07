<?php

namespace App\Services\IntegracionesPago;

use App\Events\IntegracionesPago\IntegracionPagoActualizado;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use App\Services\TenantService;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la recepción del webhook único y global de Mercado Pago (Fase 6).
 *
 * El endpoint es público y stateless (`POST /api/integraciones/mercadopago/webhook`).
 * Esta clase resuelve a qué tenant pertenece la notificación SIN escanear las N
 * DBs tenant: usa el índice global `mercadopago_collector_index` (DB config) que
 * mapea `user_id` MP → comercio + sucursal. Una vez resuelto el tenant:
 *  1. Configura la conexión tenant (sin sesión, vía TenantService).
 *  2. Verifica la firma `x-signature` con el secret de la sucursal (si está).
 *  3. Ubica la transacción por el id de la order.
 *  4. RE-CONSULTA el estado real a MP (no confía solo en el payload entrante).
 *  5. Si está aprobado, confirma el cobro server-side (idempotente) — esto
 *     registra el pago aunque el cajero haya cerrado el navegador.
 *  6. Broadcastea por Reverb para que el modal que espera reaccione al instante.
 *
 * El webhook NO materializa la venta/pedido (no tiene el carrito): solo confirma
 * la transacción. El frontend, al recibir el broadcast, re-consulta y materializa
 * su cobrable (modelo "cobro primero, cobrable después").
 *
 * Devuelve SIEMPRE un resultado manejable (no lanza): el controller responde 200
 * para que MP no reintente indefinidamente, salvo firma inválida (401).
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 6 — RF-08/RF-14/RF-18).
 */
class MercadoPagoWebhookService
{
    public function __construct(
        private readonly CobroIntegracionService $cobroService,
    ) {}

    /**
     * Procesa la notificación. `$datos` es el merge de query string + body de MP;
     * `$headers` los headers normalizados a string (x-signature, x-request-id).
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $headers
     * @return array{status: string, estado?: string, transaccion_id?: int, motivo?: string}
     */
    public function procesar(array $datos, array $headers): array
    {
        $gateway = new MercadoPagoGateway;
        $parsed = $gateway->procesarWebhook($datos, $headers);

        $userId = $parsed['user_id_externo'];
        $orderId = $parsed['order_id'];

        // Notificaciones que no son de orders (o incompletas) se ignoran sin ruido.
        if (empty($userId) || empty($orderId)) {
            return ['status' => 'ignored', 'motivo' => 'payload sin user_id u order_id'];
        }

        // Resolución multi-tenant por el índice global (DB config), sin escanear tenants.
        $index = MercadoPagoCollectorIndex::activos()
            ->where('user_id_externo', $userId)
            ->first();

        if (! $index) {
            Log::info('MP webhook: sin match de collector', ['user_id' => $userId, 'order_id' => $orderId]);

            return ['status' => 'sin_match', 'motivo' => 'user_id no registrado'];
        }

        // Configurar la conexión tenant (sin sesión: ruta api). El índice resuelve
        // SOLO el comercio (DB tenant). La sucursal se deduce de la propia
        // transacción, no del índice: así una cuenta MP compartida por varias
        // sucursales del comercio enruta bien (el índice apunta a una cualquiera).
        app(TenantService::class)->usarComercioParaProceso($index->comercio_id);

        // Checkout Pro (RF-T78): topic `payment` — el id es un PAGO, no una
        // order; la transacción se resuelve re-consultando el pago (su
        // external_reference). Rama propia con hook de pedidos de tienda.
        if ($parsed['tipo'] === 'payment') {
            return $this->procesarTopicPayment($gateway, $datos, $headers, $orderId, $userId, $index->comercio_id);
        }

        $transaccion = IntegracionPagoTransaccion::where('external_id', $orderId)->first();
        if (! $transaccion) {
            Log::info('MP webhook: transacción no encontrada para la order', ['order_id' => $orderId]);

            return ['status' => 'sin_match', 'motivo' => 'transacción no encontrada'];
        }

        // QR de monto libre: la imagen "Cobrar" es un QR estático genérico de la
        // cuenta (no lleva el external_id de NUESTRA order), así que el pago real
        // nunca matchea esta transacción por order_id. La confirmación es SOLO
        // manual (el cajero ve la acreditación en su app MP y confirma). Ignoramos
        // sin re-consultar ni confirmar para no acreditar contra el cobro equivocado.
        if ($transaccion->modo_usado === IntegracionPagoTransaccion::MODO_QR_LIBRE) {
            Log::info('MP webhook: transacción qr_libre ignorada (confirmación manual)', [
                'transaccion_id' => $transaccion->id,
                'order_id' => $orderId,
            ]);

            return ['status' => 'ignored', 'motivo' => 'qr_libre se confirma manualmente', 'transaccion_id' => $transaccion->id];
        }

        // Config de la SUCURSAL real de la transacción (su propia app/credenciales).
        // Verificación de firma con su secret: si está configurado, es obligatoria;
        // sin secret se omite (se confía en el re-chequeo autenticado a la API).
        $config = $transaccion->integracionSucursal;
        if ($config && ! empty($config->webhook_secret)
            && ! $gateway->verificarFirma($config->webhook_secret, $headers, $orderId)) {
            Log::warning('MP webhook: firma inválida', ['order_id' => $orderId, 'comercio_id' => $index->comercio_id]);

            return ['status' => 'firma_invalida'];
        }

        // Auditoría: queda registro de la notificación recibida.
        $this->cobroService->registrarEventoWebhook($transaccion, $datos);

        // Re-consultar el estado REAL a MP (autenticado con nuestro token): no
        // confiamos solo en el payload entrante. Si la red falla, devolvemos ok
        // igual (MP reintentará; el polling del cajero también cubre el hueco).
        try {
            $estado = $this->cobroService->consultarEstado($transaccion);
        } catch (\Throwable $e) {
            Log::warning('MP webhook: no se pudo consultar el estado de la order', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'error_consulta', 'transaccion_id' => $transaccion->id];
        }

        // Confirmación server-side (idempotente): registra el pago aunque el
        // navegador del cajero ya no esté escuchando. La asociación del cobrable
        // la hace el frontend al re-consultar (o queda para reconciliación).
        if ($estado === 'aprobado') {
            $this->cobroService->confirmarCobro($transaccion);
        }

        // Aviso en tiempo real: el modal que espera re-consulta y actúa.
        IntegracionPagoActualizado::dispatch($index->comercio_id, $transaccion->id, $estado);

        return [
            'status' => 'ok',
            'estado' => $estado,
            'transaccion_id' => $transaccion->id,
        ];
    }

    /**
     * Topic `payment` (Checkout Pro, RF-T78). El payload solo trae el id del
     * pago: se re-consulta AUTENTICADO a MP (eso ya es el re-chequeo — nunca
     * se confía en el payload entrante), se resuelve la tx por su
     * external_reference (BCN-TX-{id}) y, si está aprobado:
     *  1. Se persiste el payment_id en la tx (sin él no hay refund RF-T82).
     *  2. confirmarCobro (idempotente) — registra CuentaEmpresa (cobro +
     *     propina discriminados).
     *  3. Hook de tienda: PedidoPagoOnlineService::procesarAcreditacion —
     *     materializa el pago del pedido y lo saca de "esperando pago".
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $headers
     * @return array{status: string, estado?: string, transaccion_id?: int, motivo?: string}
     */
    protected function procesarTopicPayment(
        MercadoPagoGateway $gateway,
        array $datos,
        array $headers,
        string $paymentId,
        string $userId,
        int $comercioId,
    ): array {
        // Credenciales para re-consultar el pago: cualquier config activa de la
        // cuenta MP notificante sirve (mismo access_token de la cuenta).
        $configConsulta = \App\Models\IntegracionPagoSucursal::activas()
            ->where('user_id_externo', $userId)
            ->get()
            ->first(fn ($c) => $c->estaConfigurada());

        if (! $configConsulta) {
            return ['status' => 'sin_match', 'motivo' => 'sin config para consultar el pago'];
        }

        try {
            $pago = $gateway->obtenerPago($configConsulta, $paymentId);
        } catch (\Throwable $e) {
            Log::warning('MP webhook payment: no se pudo consultar el pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'error_consulta'];
        }

        $externalReference = (string) ($pago['external_reference'] ?? '');
        if (! preg_match('/^BCN-TX-(\d+)$/', $externalReference, $m)) {
            return ['status' => 'ignored', 'motivo' => 'pago sin external_reference BCN'];
        }

        $transaccion = IntegracionPagoTransaccion::find((int) $m[1]);
        if (! $transaccion || ! $transaccion->esCheckoutOnline()) {
            // Los pagos de orders QR/Point notifican por su propio topic.
            return ['status' => 'ignored', 'motivo' => 'transacción no es de checkout'];
        }

        // Firma con el secret de la config REAL de la tx (misma regla que orders).
        $config = $transaccion->integracionSucursal;
        if ($config && ! empty($config->webhook_secret)
            && ! $gateway->verificarFirma($config->webhook_secret, $headers, $paymentId)) {
            Log::warning('MP webhook payment: firma inválida', ['payment_id' => $paymentId, 'comercio_id' => $comercioId]);

            return ['status' => 'firma_invalida'];
        }

        $this->cobroService->registrarEventoWebhook($transaccion, $datos);

        $estado = match ($pago['status'] ?? '') {
            'approved' => 'aprobado',
            'rejected' => 'fallido',
            'cancelled' => 'cancelado',
            'refunded', 'charged_back' => 'devuelto',
            default => 'pendiente',
        };

        if ($estado === 'aprobado') {
            // payment_id ANTES de confirmar: el refund (RF-T82) lo necesita y
            // la confirmación dispara los movimientos de CuentaEmpresa.
            $metadata = $transaccion->metadata ?? [];
            $metadata['checkout'] = array_merge($metadata['checkout'] ?? [], ['payment_id' => (string) $paymentId]);
            $transaccion->metadata = $metadata;
            $transaccion->save();

            $this->cobroService->confirmarCobro($transaccion, null, $pago);

            // Hook de tienda: el cobrable YA existe (pedido borrador esperando
            // pago) — materializar y transicionar. Best-effort: un fallo acá no
            // pierde el cobro confirmado (queda para re-proceso/reconciliación).
            try {
                app(\App\Services\Pedidos\PedidoPagoOnlineService::class)
                    ->procesarAcreditacion($transaccion->fresh());
            } catch (\Throwable $e) {
                Log::error('MP webhook payment: falló el hook de acreditación del pedido', [
                    'transaccion_id' => $transaccion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // El aviso en vivo nunca puede tirar el webhook: el cobro YA está
        // registrado — un 500 acá solo provoca reintentos de MP (validado en
        // vivo 2026-08-06 con Reverb caído en dev).
        try {
            IntegracionPagoActualizado::dispatch($comercioId, $transaccion->id, $estado);
        } catch (\Throwable $e) {
            Log::warning('MP webhook payment: no se pudo broadcastear la actualización', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'status' => 'ok',
            'estado' => $estado,
            'transaccion_id' => $transaccion->id,
        ];
    }
}
