<?php

namespace App\Providers;

use App\Models\MenuItem;
use App\Observers\MenuItemObserver;
use App\Services\SessionManagerService;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;

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

        // Mapear nombres cortos de morph types usados en recetas
        Relation::morphMap([
            'Articulo' => \App\Models\Articulo::class,
            'Opcional' => \App\Models\Opcional::class,
        ]);

        // Registrar observers
        MenuItem::observe(MenuItemObserver::class);

        // Registrar directivas Blade para formateo de números
        $this->registrarDirectivasFormateo();
    }

    /**
     * Registra las directivas Blade para formateo de números y precios
     *
     * @return void
     */
    private function registrarDirectivasFormateo(): void
    {
        // @precio($valor) - Formatea como precio: 1.234,56
        Blade::directive('precio', function ($expression) {
            return "<?php echo formato_precio($expression); ?>";
        });

        // @precioSigno($valor) - Formatea como precio con $: $ 1.234,56
        Blade::directive('precioSigno', function ($expression) {
            return "<?php echo formato_precio($expression, 2, true); ?>";
        });

        // @numero($valor) - Formatea número: 1.234,56
        Blade::directive('numero', function ($expression) {
            return "<?php echo formato_numero($expression); ?>";
        });

        // @porcentaje($valor) - Formatea porcentaje: 15,50%
        Blade::directive('porcentaje', function ($expression) {
            return "<?php echo formato_porcentaje($expression); ?>";
        });

        // @cantidad($valor) - Formatea cantidad (enteros sin decimales): 100 o 10,50
        Blade::directive('cantidad', function ($expression) {
            return "<?php echo formato_cantidad($expression); ?>";
        });
    }
}
