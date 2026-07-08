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
        ]);

        $sucursal = $request->attributes->get('api_sucursal');

        // Consumidor logueado con cliente materializado (D11): la cotización
        // del checkout usa SU cliente (precios especiales/promos por cliente)
        // — el mismo que usará el POST del pedido, para que checkout y pedido
        // muestren el MISMO total. Solo mapping existente: cotizar nunca crea
        // clientes.
        $clienteId = null;
        $consumidor = $request->user('sanctum');
        if ($consumidor instanceof \App\Models\Consumidor) {
            $clienteId = $consumidor->clienteIdEn((int) $sucursal->comercio_id);
            if ($clienteId && ! \App\Models\Cliente::find($clienteId)) {
                $clienteId = null;
            }
        }

        $resultado = $cotizador->cotizar(
            $sucursal,
            $datos['tipo'],
            $datos['items'],
            $datos['cupon_codigo'] ?? null,
            $clienteId,
        );

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
                'desglose_iva' => $resultado['desglose_iva'] ?? null,
                'nota' => __('El costo de envío se cotiza aparte y se suma al confirmar el pedido'),
            ],
        ]);
    }
}
