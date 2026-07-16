<?php

namespace App\Http\Middleware;

use App\Models\Consumidor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1 consumidores (RF-T1): exige que el Bearer autenticado por
 * auth:sanctum sea un CONSUMIDOR de la tienda. Un token de integración de
 * comercio (u otro tokenable) acá es un tipo de credencial equivocado →
 * 403 sin_permiso (el formato JSON lo da el handler de bootstrap/app.php).
 */
class EnsureApiConsumidor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Consumidor) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                __('Esta operación requiere una cuenta de consumidor')
            );
        }

        return $next($request);
    }
}
