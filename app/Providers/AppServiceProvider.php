<?php

namespace App\Providers;

use App\Services\SessionManagerService;
use App\Services\TenantService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedor de servicios de la aplicación
 *
 * Registra y configura los servicios principales de la aplicación,
 * incluyendo el servicio de gestión multi-tenant.
 *
 * @package App\Providers
 * @author BCN Pymes
 * @version 1.0.0
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación
     *
     * Aquí se registran los servicios como singletons para que estén
     * disponibles en toda la aplicación.
     *
     * @return void
     */
    public function register(): void
    {
        // Registrar TenantService como singleton
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Registrar SessionManagerService como singleton
        $this->app->singleton(SessionManagerService::class, function ($app) {
            return new SessionManagerService();
        });
    }

    /**
     * Inicializa los servicios de la aplicación
     *
     * @return void
     */
    public function boot(): void
    {
        // Establecer longitud por defecto para strings en migraciones
        Schema::defaultStringLength(191);
    }
}
