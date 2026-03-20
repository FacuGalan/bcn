<?php

namespace App\Http\Middleware;

use App\Services\CajaService;
use App\Services\SucursalService;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
 * @author BCN Pymes
 *
 * @version 1.0.0
 */
class TenantMiddleware
{
    /**
     * Servicio de gestión de tenants
     */
    protected TenantService $tenantService;

    /**
     * Constructor del middleware
     *
     * @param  TenantService  $tenantService  Servicio de tenant
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
     * @param  \Illuminate\Http\Request  $request  Request actual
     * @param  \Closure  $next  Siguiente middleware
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar si hay un comercio activo en sesión
        if (! $this->tenantService->hasComercio()) {
            // Intentar auto-restaurar el comercio desde ultimo_comercio_id o comercio único
            if (! $this->tryAutoRestoreComercio($user)) {
                return redirect()->route('comercio.selector');
            }
        }

        $comercio = $this->tenantService->getComercio();

        // Verificar acceso al comercio (cachear resultado en el request)
        if (! $request->attributes->get('tenant_access_verified', false)) {
            if (! $user->hasAccessToComercio($comercio->id)) {
                $this->tenantService->clearComercio();

                return redirect()->route('comercio.selector')
                    ->with('error', 'No tienes acceso al comercio seleccionado.');
            }
            $request->attributes->set('tenant_access_verified', true);
        }

        return $next($request);
    }

    /**
     * Intenta restaurar automáticamente el comercio cuando la sesión se perdió
     * (ej: remember me re-autenticó al usuario pero la sesión expiró)
     *
     * Prioridad:
     * 1. ultimo_comercio_id del usuario (si tiene acceso)
     * 2. Comercio único (si el usuario solo tiene uno)
     *
     * @param  \App\Models\User  $user  Usuario autenticado
     * @return bool True si se restauró exitosamente
     */
    protected function tryAutoRestoreComercio($user): bool
    {
        // System admins siempre van al selector (necesitan buscar entre todos)
        if ($user->isSystemAdmin()) {
            return false;
        }

        $comercios = $user->comercios()->get();

        // Sin comercios asociados → al selector (mostrará error)
        if ($comercios->isEmpty()) {
            return false;
        }

        $comercio = null;

        // Prioridad 1: último comercio usado (si tiene acceso)
        if ($user->ultimo_comercio_id) {
            $comercio = $comercios->firstWhere('id', $user->ultimo_comercio_id);
        }

        // Prioridad 2: si solo tiene un comercio, usarlo directamente
        if (! $comercio && $comercios->count() === 1) {
            $comercio = $comercios->first();
        }

        // Si tiene múltiples comercios y no hay ultimo_comercio_id → selector
        if (! $comercio) {
            return false;
        }

        // Restaurar comercio, sucursal y caja
        $this->tenantService->setComercio($comercio);

        // Limpiar cachés estáticos que pueden haberse llenado con resultados vacíos
        // antes de que el comercio se restaurara (EnsureSucursalSelected corre antes)
        SucursalService::clearCache();
        CajaService::clearCache();

        $sucursales = SucursalService::getSucursalesDisponibles();
        if ($sucursales->isNotEmpty()) {
            Session::put('sucursal_id', $sucursales->first()->id);
            CajaService::establecerPrimeraCajaDisponible();
        }

        return true;
    }
}
