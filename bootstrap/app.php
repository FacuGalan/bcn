<?php

use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrar middleware de Tenant con alias
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            // Resuelve el tenant de pantallas públicas Clase B por token (sin sesión)
            'pantalla.token' => \App\Http\Middleware\ResolvePublicTokenMiddleware::class,
            // API v1: resuelve comercio+sucursal por slug de tienda o token de
            // integración Sanctum y configura TenantService (sin sesión, RF-11)
            'api.tenant' => \App\Http\Middleware\ApiTenantMiddleware::class,
            // Abilities de los tokens de integración Sanctum (RF-11)
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            // API v1 consumidores (RF-T1): el Bearer debe ser un Consumidor
            'api.consumidor' => \App\Http\Middleware\EnsureApiConsumidor::class,
        ]);

        // IMPORTANTE: Configurar el tenant en TODOS los requests web
        // Se ejecuta después del middleware de sesión para que la sesión esté disponible
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ConfigureTenantMiddleware::class,
            // Asegurar que el usuario siempre tenga una sucursal seleccionada
            \App\Http\Middleware\EnsureSucursalSelected::class,
        ]);

        // Excluir ruta de firma QZ Tray del CSRF (necesario para impresion silenciosa)
        $middleware->validateCsrfTokens(except: [
            'api/qz/sign',
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('precios:procesar-programados')->everyMinute();
        $schedule->command('integraciones-pago:expirar-pendientes')->everyMinute()->withoutOverlapping();
        $schedule->command('conciliaciones:procesar')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API v1: errores JSON uniformes {error: {code, message, details}}
        // para toda ruta api/* (RF-11). Los controllers lanzan excepciones de
        // dominio; acá se les da forma.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/v1/*')) {
                return null; // render por defecto fuera de la API v1
            }

            [$status, $code] = match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => [422, 'validacion'],
                $e instanceof \Illuminate\Auth\AuthenticationException => [401, 'no_autenticado'],
                // AuthorizationException lanzada en middleware llega acá ya
                // convertida en AccessDeniedHttpException: mismo código.
                $e instanceof \Illuminate\Auth\Access\AuthorizationException,
                $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException,
                $e instanceof \Laravel\Sanctum\Exceptions\MissingAbilityException => [403, 'sin_permiso'],
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => [404, 'no_encontrado'],
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => [$e->getStatusCode(), 'http_'.$e->getStatusCode()],
                // Los services lanzan \Exception "pelada" con mensaje para el
                // usuario (regla del proyecto) → 422 de negocio. Cualquier otra
                // excepción es un error interno y NO expone su mensaje.
                get_class($e) === \Exception::class => [422, 'operacion_invalida'],
                default => [500, 'error_interno'],
            };

            $mensaje = match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => __('Los datos enviados no son válidos'),
                $status === 500 => __('Error interno del servidor'),
                default => $e->getMessage() ?: __('Error al procesar la solicitud'),
            };

            if ($status === 500) {
                \Illuminate\Support\Facades\Log::error('Error interno API v1', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                ]);
            }

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $mensaje,
                    'details' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : null,
                ],
            ], $status);
        });
    })->create();
