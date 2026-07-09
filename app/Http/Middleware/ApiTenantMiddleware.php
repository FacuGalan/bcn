<?php

namespace App\Http\Middleware;

use App\Models\Comercio;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolución de tenant para la API v1 (RF-11, pedidos-delivery) — SIN sesión.
 *
 * Dos caminos:
 * - **Rutas públicas de tienda** (`/v1/tiendas/{slug}/...`): el slug identifica
 *   comercio+sucursal en el registro global `config.tiendas` (D15). Tienda
 *   inexistente o deshabilitada → 404 genérico (sin habilitar enumeración).
 * - **Rutas de integración** (`auth:sanctum`): el token pertenece a un
 *   COMERCIO (tokenable). La sucursal se pasa por header `X-Sucursal-Id` o
 *   query `sucursal_id`; si falta, se usa la principal.
 *
 * En ambos casos configura la conexión tenant vía
 * TenantService::usarComercioParaProceso (no toca Session ni Auth) y deja en
 * el request: `api_comercio`, `api_sucursal` (modelo tenant) y `api_tienda`
 * (solo camino público).
 */
class ApiTenantMiddleware
{
    public function __construct(private TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if ($slug !== null) {
            return $this->resolverPorSlug($request, $next, (string) $slug);
        }

        return $this->resolverPorToken($request, $next);
    }

    private function resolverPorSlug(Request $request, Closure $next, string $slug): Response
    {
        $tienda = Tienda::porSlug($slug)->habilitadas()->first();

        if (! $tienda) {
            return response()->json([
                'error' => ['code' => 'tienda_no_encontrada', 'message' => 'Tienda no encontrada'],
            ], 404);
        }

        $comercio = $this->tenantService->usarComercioParaProceso($tienda->comercio_id);

        $sucursal = Sucursal::find($tienda->sucursal_id);
        if (! $sucursal || ! $sucursal->activa || ! $sucursal->usa_delivery) {
            return response()->json([
                'error' => ['code' => 'tienda_no_disponible', 'message' => 'La tienda no está disponible'],
            ], 404);
        }

        $request->attributes->set('api_comercio', $comercio);
        $request->attributes->set('api_sucursal', $sucursal);
        $request->attributes->set('api_tienda', $tienda);

        return $next($request);
    }

    private function resolverPorToken(Request $request, Closure $next): Response
    {
        $tokenable = $request->user();

        if (! $tokenable instanceof Comercio) {
            return response()->json([
                'error' => ['code' => 'no_autorizado', 'message' => 'Token de integración inválido'],
            ], 401);
        }

        $this->tenantService->usarComercioParaProceso($tokenable);

        $sucursalId = $request->header('X-Sucursal-Id') ?? $request->query('sucursal_id');

        $sucursal = $sucursalId !== null
            ? Sucursal::find((int) $sucursalId)
            : Sucursal::where('es_principal', true)->first() ?? Sucursal::activas()->first();

        if (! $sucursal || ! $sucursal->activa) {
            return response()->json([
                'error' => ['code' => 'sucursal_invalida', 'message' => 'Sucursal inexistente o inactiva (usar header X-Sucursal-Id)'],
            ], 422);
        }

        $request->attributes->set('api_comercio', $tokenable);
        $request->attributes->set('api_sucursal', $sucursal);

        return $next($request);
    }
}
