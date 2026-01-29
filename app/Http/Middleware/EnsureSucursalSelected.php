<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SucursalService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureSucursalSelected
 *
 * Garantiza que el usuario autenticado siempre tenga una sucursal activa en la sesión.
 * Si no existe sucursal_id en sesión, obtiene las sucursales disponibles del usuario
 * y selecciona la primera (priorizando la principal).
 *
 * FASE 4 - Sistema Multi-Sucursal
 */
class EnsureSucursalSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar a usuarios autenticados
        if (Auth::check()) {
            // Validar y establecer sucursal activa si es necesario
            SucursalService::validarYEstablecerSucursalActiva();
        }

        return $next($request);
    }
}
