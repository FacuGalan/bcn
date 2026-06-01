<?php

namespace App\Http\Controllers\IntegracionesPago;

use App\Http\Controllers\Controller;
use App\Services\IntegracionesPago\MercadoPagoWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint público y global del webhook de Mercado Pago (Fase 6).
 *
 * Ruta: `POST /api/integraciones/mercadopago/webhook` (sin auth ni CSRF: está
 * en el grupo `api`, no `web`). La resolución de a qué comercio pertenece la
 * notificación y todo el procesamiento vive en MercadoPagoWebhookService.
 *
 * Responde 200 en todos los casos manejables para que MP no reintente en bucle;
 * solo devuelve 401 si la firma es inválida (notificación potencialmente falsa).
 */
class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request, MercadoPagoWebhookService $service): JsonResponse
    {
        // MP manda datos en el body (JSON) y a veces en el query string. Se
        // mergea todo; el service/gateway sabe extraer order_id y user_id.
        $datos = array_merge($request->query(), $request->json()->all());

        $headers = [
            'x-signature' => (string) $request->header('x-signature', ''),
            'x-request-id' => (string) $request->header('x-request-id', ''),
        ];

        $resultado = $service->procesar($datos, $headers);

        if (($resultado['status'] ?? null) === 'firma_invalida') {
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        // 200 para todo lo demás (ok, sin_match, ignored, error_consulta): MP
        // considera entregada la notificación y no reintenta innecesariamente.
        return response()->json(['status' => $resultado['status'] ?? 'ok']);
    }
}
