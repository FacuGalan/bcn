<?php

use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\EnsureSucursalSelected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrar middleware de Tenant con alias
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
        ]);

        // IMPORTANTE: Configurar el tenant en TODOS los requests web
        // Se ejecuta después del middleware de sesión para que la sesión esté disponible
        $middleware->web(append: [
            \App\Http\Middleware\ConfigureTenantMiddleware::class,
            // Asegurar que el usuario siempre tenga una sucursal seleccionada
            \App\Http\Middleware\EnsureSucursalSelected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
