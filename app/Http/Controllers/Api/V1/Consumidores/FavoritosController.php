<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Models\ConsumidorFavorito;
use App\Models\Tienda;
use App\Services\Pedidos\MarketplaceTiendasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favoritos de comercios del consumidor (RF-T41, ronda cuenta consumidor).
 *
 * El favorito referencia la TIENDA por slug (lo que el consumidor visita).
 * PUT/DELETE son idempotentes: marcar dos veces no duplica (unique en BD),
 * desmarcar lo inexistente responde ok — la UI togglea sin miedo a carreras.
 */
class FavoritosController extends Controller
{
    /**
     * GET /v1/consumidores/favoritos — cards de las tiendas favoritas
     * (shape del marketplace, sin ubicación). Orden: más reciente primero.
     */
    public function index(Request $request, MarketplaceTiendasService $marketplace): JsonResponse
    {
        $tiendas = ConsumidorFavorito::where('consumidor_id', $request->user()->id)
            ->orderByDesc('id')
            ->with('tienda.comercio:id,nombre,rubro_id')
            ->get()
            ->pluck('tienda')
            ->filter(); // tienda borrada con el favorito huérfano: no romper

        return response()->json(['data' => $marketplace->cardsBasicas($tiendas)]);
    }

    /**
     * PUT /v1/consumidores/favoritos/{slug} — marcar (idempotente).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $tienda = Tienda::where('slug', $slug)->firstOrFail();

        ConsumidorFavorito::firstOrCreate([
            'consumidor_id' => $request->user()->id,
            'tienda_id' => $tienda->id,
        ]);

        return response()->json(['data' => ['ok' => true, 'favorito' => true]]);
    }

    /**
     * DELETE /v1/consumidores/favoritos/{slug} — desmarcar (idempotente).
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $tienda = Tienda::where('slug', $slug)->firstOrFail();

        ConsumidorFavorito::where('consumidor_id', $request->user()->id)
            ->where('tienda_id', $tienda->id)
            ->delete();

        return response()->json(['data' => ['ok' => true, 'favorito' => false]]);
    }
}
