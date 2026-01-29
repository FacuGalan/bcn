<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de gestión de Tenant (Comercio activo)
 *
 * Este middleware se ejecuta en cada request para:
 * - Verificar que el usuario autenticado tiene un comercio activo en sesión
 * - Validar que el usuario tiene acceso al comercio activo
 * - Configurar la conexión dinámica con el prefijo del comercio
 * - Redireccionar al selector de comercio si no hay comercio activo
 *
 * @package App\Http\Middleware
 * @author BCN Pymes
 * @version 1.0.0
 */
class TenantMiddleware
{
    /**
     * Servicio de gestión de tenants
     *
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * Constructor del middleware
     *
     * @param TenantService $tenantService Servicio de tenant
     */
    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Maneja una solicitud entrante
     *
     * Verifica que:
     * 1. El usuario esté autenticado
     * 2. Tenga un comercio activo en sesión
     * 3. Tenga acceso al comercio activo
     * 4. Configura la conexión con el prefijo del comercio
     *
     * @param \Illuminate\Http\Request $request Request actual
     * @param \Closure $next Siguiente middleware
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar si hay un comercio activo en sesión
        if (!$this->tenantService->hasComercio()) {
            // Si el usuario no tiene comercio activo, redirigir al selector
            return redirect()->route('comercio.selector');
        }

        $comercio = $this->tenantService->getComercio();

        // Verificar que el usuario tenga acceso al comercio activo
        if (!$user->hasAccessToComercio($comercio->id)) {
            // Si no tiene acceso, limpiar comercio y redirigir al selector
            $this->tenantService->clearComercio();
            return redirect()->route('comercio.selector')
                ->with('error', 'No tienes acceso al comercio seleccionado.');
        }

        // La conexión ya está configurada por TenantService::setComercio()
        // pero la reconfiguramos por si se perdió en el request
        $this->tenantService->setComercio($comercio);

        return $next($request);
    }
}
