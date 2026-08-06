<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispositivos recordados del consumidor (RF-T66/T74) — "Mis dispositivos"
 * de la cuenta. El selector NUNCA viaja en el listado (es media credencial):
 * la tienda identifica el dispositivo actual mandando su selector en el
 * header `X-Dispositivo`, y el core responde solo el flag `actual`.
 */
class DispositivosController extends Controller
{
    /**
     * GET /v1/consumidores/dispositivos — lista para "Mis dispositivos".
     */
    public function index(Request $request): JsonResponse
    {
        $selectorActual = (string) $request->header('X-Dispositivo', '');

        $dispositivos = $request->user()->dispositivos()
            ->orderByRaw('COALESCE(ultimo_uso_at, created_at) desc')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'ip_ultima' => $d->ip_ultima,
                'ultimo_uso_at' => $d->ultimo_uso_at?->toIso8601String(),
                'creado_el' => $d->created_at->toIso8601String(),
                'actual' => $selectorActual !== '' && hash_equals($d->selector, $selectorActual),
            ])
            ->values();

        return response()->json(['data' => $dispositivos]);
    }

    /**
     * DELETE /v1/consumidores/dispositivos/{id} — revoca UN dispositivo
     * (cierra la sesión recordada de ese navegador/webview).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $borrados = $request->user()->dispositivos()->where('id', $id)->delete();

        if ($borrados === 0) {
            return response()->json([
                'message' => __('Dispositivo no encontrado'),
                'codigo' => 'no_encontrado',
            ], 404);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * DELETE /v1/consumidores/dispositivos — "cerrar sesión en los demás
     * dispositivos": revoca todos menos el actual (header `X-Dispositivo`;
     * sin header revoca TODOS).
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $selectorActual = (string) $request->header('X-Dispositivo', '');

        $query = $request->user()->dispositivos();

        if ($selectorActual !== '') {
            $query->where('selector', '!=', $selectorActual);
        }

        $borrados = $query->delete();

        return response()->json(['data' => ['ok' => true, 'revocados' => $borrados]]);
    }
}
