<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Pedidos\CotizadorCarritoTienda;
use App\Services\Pedidos\DeliveryEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cotizaciones públicas de la tienda (RF-06/D12).
 */
class CotizacionController extends Controller
{
    /**
     * POST /v1/tiendas/{slug}/envios/cotizar — {latitud, longitud} → costo/alcance.
     * `hora_pactada` (opcional, datetime): evalúa las franjas de costo de la
     * zona para ese momento (más caro de noche, etc.); default ahora.
     */
    public function envio(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $datos = $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'hora_pactada' => 'nullable|date',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $cotizacion = $envioService->cotizar(
            $sucursal,
            (float) $datos['latitud'],
            (float) $datos['longitud'],
            cuando: isset($datos['hora_pactada']) ? \Illuminate\Support\Carbon::parse($datos['hora_pactada']) : null,
        );

        return response()->json([
            'data' => [
                'alcance' => $cotizacion->alcance,
                'pedible' => $cotizacion->esOk(),
                'costo_envio' => $cotizacion->costo !== null ? (float) $cotizacion->costo : null,
                'distancia_km' => $cotizacion->distanciaKm !== null ? (float) $cotizacion->distanciaKm : null,
                'zona' => $cotizacion->zona?->nombre,
                'demora_estimada_min' => $cotizacion->demoraEstimadaMin,
            ],
        ]);
    }

    /**
     * POST /v1/tiendas/{slug}/carrito/cotizar — cotización server-side del
     * carrito completo (D12): el contrato que la tienda muestra en el checkout.
     */
    public function carrito(Request $request, CotizadorCarritoTienda $cotizador): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => 'required|in:delivery,take_away',
            'items' => 'required|array|min:1|max:100',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'items.*.opcionales' => 'nullable|array',
            'items.*.opcionales.*.opcional_id' => 'required|integer',
            'items.*.opcionales.*.cantidad' => 'nullable|numeric|min:0.001',
            'cupon_codigo' => 'nullable|string|max:50',
            'forma_pago_id' => 'nullable|integer',
            'usar_puntos' => 'nullable|boolean',
            // Encargo (RF-T16): validar acá para que el checkout falle
            // TEMPRANO (slot vencido o artículo no apto) y no en el alta.
            'entrega.programado_para' => 'nullable|date',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');

        if (! empty($datos['entrega']['programado_para'])) {
            try {
                app(DeliveryEnvioService::class)->validarProgramado(
                    $sucursal,
                    \Illuminate\Support\Carbon::parse($datos['entrega']['programado_para']),
                    collect($datos['items'])->pluck('articulo_id')->map(fn ($id) => (int) $id)->all(),
                );
            } catch (\Exception $e) {
                abort(response()->json(['error' => [
                    'code' => 'encargo_invalido',
                    'message' => $e->getMessage(),
                    'details' => null,
                ]], 422));
            }
        }

        // Consumidor logueado con cliente materializado (D11): la cotización
        // del checkout usa SU cliente (precios especiales/promos por cliente)
        // — el mismo que usará el POST del pedido, para que checkout y pedido
        // muestren el MISMO total. Solo mapping existente: cotizar nunca crea
        // clientes.
        $clienteId = null;
        $consumidor = $request->user('sanctum');
        if ($consumidor instanceof \App\Models\Consumidor) {
            // El comercio sale de la TIENDA (config) — la sucursal es tenant
            // y no conoce su comercio (fix 2026-07-17: con ->comercio_id de
            // la sucursal el mapping nunca resolvía y el consumidor cotizaba
            // sin su cliente, es decir sin sus precios).
            $tienda = $request->attributes->get('api_tienda');
            $clienteId = $consumidor->clienteIdEn((int) $tienda->comercio_id);
            if ($clienteId && ! \App\Models\Cliente::find($clienteId)) {
                $clienteId = null;
            }
        }

        $formaPagoId = isset($datos['forma_pago_id']) ? (int) $datos['forma_pago_id'] : null;
        $resultado = $cotizador->cotizar(
            $sucursal,
            $datos['tipo'],
            $datos['items'],
            $datos['cupon_codigo'] ?? null,
            $clienteId,
            $formaPagoId,
        );

        $totalAPagar = (float) ($resultado['total_a_pagar'] ?? ($resultado['total_final'] ?? 0));

        // Puntos (RF-T9, Fase 3): el canje es un PAGO por el máximo (toggle)
        // — no toca precios ni total_final; resta del total_a_pagar. El
        // bloque viaja siempre que el programa esté activo para el cliente
        // (con `a_ganar`, aunque no canjee).
        $puntos = null;
        if ($clienteId) {
            $puntosTienda = app(\App\Services\Pedidos\PuntosTiendaService::class);
            $info = $puntosTienda->info($sucursal, $clienteId);
            if ($info['activo']) {
                $canje = ! empty($datos['usar_puntos'])
                    ? $puntosTienda->calcularCanjeMaximo($info, $totalAPagar)
                    : null;
                $puntos = $puntosTienda->bloqueContrato($info, $canje, $sucursal, $formaPagoId, $totalAPagar);
                $totalAPagar = round($totalAPagar - $puntos['monto'], 2);
            }
        }

        return response()->json([
            'data' => [
                'items' => $resultado['items'] ?? [],
                'subtotal' => (float) ($resultado['subtotal'] ?? 0),
                'iva' => (float) ($resultado['iva_total'] ?? 0),
                'descuento' => (float) ($resultado['descuento_total'] ?? 0),
                'total_final' => (float) ($resultado['total_final'] ?? 0),
                'promociones_aplicadas' => $resultado['promociones_comunes_aplicadas'] ?? [],
                'promociones_especiales_aplicadas' => $resultado['promociones_especiales_aplicadas'] ?? [],
                'cupon' => $resultado['cupon'],
                // FP declarada (opcional): descuento/recargo con los MISMOS
                // cálculos del panel. total_a_pagar = total_final + ajuste
                // (sin envío, que va aparte) − canje de puntos si lo hay.
                'forma_pago' => $resultado['forma_pago'] ?? null,
                'puntos' => $puntos,
                'total_a_pagar' => $totalAPagar,
                'desglose_iva' => $resultado['desglose_iva'] ?? null,
                'nota' => __('El costo de envío se cotiza aparte y se suma al confirmar el pedido'),
            ],
        ]);
    }
}
