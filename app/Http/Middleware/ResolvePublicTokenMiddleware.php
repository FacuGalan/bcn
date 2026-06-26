<?php

namespace App\Http\Middleware;

use App\Services\PantallaPublicaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant de una pantalla pública Clase B a partir del `{token}` de la
 * URL, SIN sesión. Busca el token en el índice global config, configura la
 * conexión tenant y deja la sucursal + comercio disponibles en el request.
 *
 * Token inexistente → 404 genérico (sin distinguir "no existe" de "inválido",
 * para no habilitar enumeración).
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02).
 */
class ResolvePublicTokenMiddleware
{
    public function __construct(private PantallaPublicaService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        $resuelto = $token !== '' ? $this->service->resolverPorToken($token) : null;

        if (! $resuelto) {
            abort(404);
        }

        $request->attributes->set('pantalla_sucursal', $resuelto['sucursal']);
        $request->attributes->set('pantalla_comercio', $resuelto['comercio']);
        $request->attributes->set('pantalla_token', $token);

        return $next($request);
    }
}
