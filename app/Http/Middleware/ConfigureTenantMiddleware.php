<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use App\Services\SucursalService;
use App\Services\CajaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para configurar automáticamente la conexión del tenant
 *
 * Este middleware se ejecuta en TODOS los requests web (después del middleware de sesión)
 * y configura el prefijo de la conexión pymes_tenant si hay un comercio activo.
 *
 * Es crucial para aplicaciones SPA donde los requests AJAX necesitan mantener
 * la configuración del tenant.
 *
 * @package App\Http\Middleware
 * @version 1.0.0
 */
class ConfigureTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // OPTIMIZACIÓN: Solo configurar UNA VEZ por request usando atributos del request
        if (!$request->attributes->get('tenant_configured', false)) {
            $tenantService = app(TenantService::class);

            // Solo configurar si hay un comercio en sesión
            if ($tenantService->hasComercio()) {
                $comercio = $tenantService->getComercio();

                if ($comercio) {
                    // Reconfigurar la conexión con el prefijo del comercio
                    // Esto es necesario porque la conexión se resetea entre requests
                    $tenantService->setComercio($comercio);

                    // Auto-seleccionar caja si no hay una activa
                    $this->autoSeleccionarCaja();
                }
            }

            // Marcar como configurado en este request
            $request->attributes->set('tenant_configured', true);
        }

        return $next($request);
    }

    /**
     * Auto-selecciona la primera caja disponible si no hay una activa
     *
     * Se ejecuta en cada request para asegurar que siempre haya una caja seleccionada
     * si el usuario tiene cajas asignadas.
     *
     * @return void
     */
    protected function autoSeleccionarCaja(): void
    {
        // Solo si el usuario está autenticado
        if (!auth()->check()) {
            return;
        }

        // Solo si hay una sucursal activa
        if (!SucursalService::getSucursalActiva()) {
            return;
        }

        // Si ya hay una caja activa, verificar que sea válida
        $cajaActiva = CajaService::getCajaActiva();

        if ($cajaActiva) {
            // Verificar que siga teniendo acceso y que pertenezca a la sucursal actual
            if (CajaService::tieneAccesoACaja($cajaActiva)) {
                return; // Ya tiene una caja válida
            }
        }

        // No hay caja activa o no es válida, establecer la primera disponible
        CajaService::establecerPrimeraCajaDisponible();
    }
}
