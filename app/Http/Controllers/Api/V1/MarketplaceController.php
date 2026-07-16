<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Pedidos\MarketplaceTiendasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace público de tiendas (RF-T4, spec tienda-online): la landing
 * global de la tienda ("qué tiendas llegan a mi ubicación"). Sin tenant:
 * es cross-comercio por definición.
 */
class MarketplaceController extends Controller
{
    /**
     * GET /v1/tiendas?lat=&lng=&rubro_id= — tiendas habilitadas. Con lat/lng
     * excluye las que no llegan y ordena por distancia; sin coordenadas
     * lista todas (alcance desconocido, sin inventar).
     */
    public function tiendas(Request $request, MarketplaceTiendasService $marketplace): JsonResponse
    {
        $datos = $request->validate([
            'lat' => 'nullable|required_with:lng|numeric|between:-90,90',
            'lng' => 'nullable|required_with:lat|numeric|between:-180,180',
            'rubro_id' => 'nullable|integer',
        ]);

        return response()->json([
            'data' => $marketplace->listar(
                isset($datos['lat']) ? (float) $datos['lat'] : null,
                isset($datos['lng']) ? (float) $datos['lng'] : null,
                isset($datos['rubro_id']) ? (int) $datos['rubro_id'] : null,
            ),
        ]);
    }

    /**
     * GET /v1/rubros — catálogo global de rubros activos.
     */
    public function rubros(MarketplaceTiendasService $marketplace): JsonResponse
    {
        return response()->json(['data' => $marketplace->rubros()]);
    }
}
