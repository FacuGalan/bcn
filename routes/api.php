<?php

use App\Http\Controllers\Api\ImpresionController;
use App\Http\Controllers\IntegracionesPago\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas de impresión (requieren autenticación web)
Route::middleware(['web', 'auth'])->prefix('impresion')->group(function () {
    Route::get('/venta/{id}/ticket', [ImpresionController::class, 'ticketVenta']);
    Route::get('/factura/{id}', [ImpresionController::class, 'factura']);
    Route::get('/cierre-turno/{id}', [ImpresionController::class, 'cierreTurno']);
    Route::get('/recibo-cobro/{id}', [ImpresionController::class, 'reciboCobro']);
    Route::get('/prueba/{id}', [ImpresionController::class, 'prueba']);
    Route::get('/impresoras', [ImpresionController::class, 'listar']);
});

// Firma QZ Tray (público pero con CSRF)
Route::middleware(['web'])->post('/qz/sign', [ImpresionController::class, 'firmarMensaje']);

// Webhook global de Mercado Pago (integraciones de pago — Fase 6). Público,
// sin sesión ni CSRF: resuelve el tenant por el user_id MP del payload. La
// seguridad la dan la firma x-signature + el re-chequeo autenticado a la API.
Route::post('/integraciones/mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('integraciones.mercadopago.webhook');

/*
|--------------------------------------------------------------------------
| API v1 — Pedidos Delivery (RF-11, spec pedidos-delivery)
|--------------------------------------------------------------------------
| Tres audiencias:
| - Público por slug de tienda (throttled): catálogo, cotizaciones, alta de
|   pedido, seguimiento por token. `api.tenant` resuelve comercio+sucursal
|   por el slug (config.tiendas, D15).
| - Integración (auth:sanctum, token de COMERCIO con abilities): pedidos,
|   config, repartidores. Sucursal por header X-Sucursal-Id.
| - Consumidores (guard consumidores): lo usará el proyecto tienda.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Público por tienda (slug) ──
    Route::middleware(['api.tenant', 'throttle:60,1'])
        ->prefix('tiendas/{slug}')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\TiendaController::class, 'show'])->name('tienda.show');
            Route::get('/catalogo', [\App\Http\Controllers\Api\V1\TiendaController::class, 'catalogo'])->name('tienda.catalogo');
            Route::get('/franjas', [\App\Http\Controllers\Api\V1\TiendaController::class, 'franjas'])->name('tienda.franjas');
            Route::get('/encargos', [\App\Http\Controllers\Api\V1\TiendaController::class, 'encargos'])->name('tienda.encargos');
            // Puntos del consumidor logueado en ESTA tienda (RF-T8, Fase 3).
            Route::get('/puntos', [\App\Http\Controllers\Api\V1\TiendaController::class, 'puntos'])
                ->middleware(['auth:sanctum', 'api.consumidor'])->name('tienda.puntos');
            Route::post('/envios/cotizar', [\App\Http\Controllers\Api\V1\CotizacionController::class, 'envio'])->name('tienda.envios.cotizar');
            Route::post('/carrito/cotizar', [\App\Http\Controllers\Api\V1\CotizacionController::class, 'carrito'])->name('tienda.carrito.cotizar');
            Route::post('/pedidos', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'store'])
                ->middleware('throttle:15,1')->name('tienda.pedidos.store');
            Route::get('/pedidos/{token}', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'show'])->name('tienda.pedidos.seguimiento');
            Route::post('/pedidos/{token}/cancelar', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'cancelar'])
                ->middleware('throttle:10,1')->name('tienda.pedidos.cancelar');
        });

    // ── Consumidores (cuenta GLOBAL de la tienda online, RF-T1..T3) ──
    // Sin api.tenant: el consumidor es cross-comercio (BD config). Throttle
    // agresivo en los endpoints que mandan emails o prueban credenciales.
    // GOTCHA: los throttle inline comparten bucket por sha1(user|ip) — el
    // 3er parámetro (prefijo) separa los contadores; sin él, un throttle de
    // grupo + uno de ruta doble-incrementan el mismo contador.
    Route::prefix('consumidores')->name('consumidores.')->group(function () {
        Route::post('/registro', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'registro'])
            ->middleware('throttle:5,1,c-registro')->name('registro');
        Route::post('/login', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'login'])
            ->middleware('throttle:10,1,c-login')->name('login');
        Route::post('/verificar', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'verificar'])
            ->middleware('throttle:10,1,c-verificar')->name('verificar');
        Route::post('/recuperar', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'recuperar'])
            ->middleware('throttle:3,1,c-recuperar')->name('recuperar');
        Route::post('/restablecer', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'restablecer'])
            ->middleware('throttle:5,1,c-restablecer')->name('restablecer');

        Route::middleware(['auth:sanctum', 'api.consumidor', 'throttle:60,1,c-auth'])->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'me'])->name('me');
            Route::post('/reenviar-verificacion', [\App\Http\Controllers\Api\V1\Consumidores\AuthController::class, 'reenviarVerificacion'])
                ->middleware('throttle:3,1,c-reenviar')->name('reenviar-verificacion');

            Route::get('/direcciones', [\App\Http\Controllers\Api\V1\Consumidores\DireccionesController::class, 'index'])->name('direcciones.index');
            Route::post('/direcciones', [\App\Http\Controllers\Api\V1\Consumidores\DireccionesController::class, 'store'])->name('direcciones.store');
            Route::patch('/direcciones/{id}', [\App\Http\Controllers\Api\V1\Consumidores\DireccionesController::class, 'update'])->name('direcciones.update');
            Route::delete('/direcciones/{id}', [\App\Http\Controllers\Api\V1\Consumidores\DireccionesController::class, 'destroy'])->name('direcciones.destroy');

            Route::get('/pedidos', [\App\Http\Controllers\Api\V1\Consumidores\PedidosController::class, 'index'])
                ->middleware('throttle:30,1')->name('pedidos.index');
        });
    });

    // ── Marketplace público (RF-T4): landing global de tiendas ──
    Route::middleware('throttle:30,1,marketplace')->group(function () {
        Route::get('/tiendas', [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'tiendas'])->name('marketplace.tiendas');
        Route::get('/rubros', [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'rubros'])->name('marketplace.rubros');
    });

    // ── Integración (token Sanctum de comercio con abilities) ──
    Route::middleware(['auth:sanctum', 'api.tenant', 'throttle:120,1'])->group(function () {
        Route::get('/pedidos-delivery', [\App\Http\Controllers\Api\V1\Integracion\PedidosController::class, 'index'])
            ->middleware('ability:pedidos:read')->name('pedidos.index');
        Route::get('/pedidos-delivery/{id}', [\App\Http\Controllers\Api\V1\Integracion\PedidosController::class, 'show'])
            ->middleware('ability:pedidos:read')->name('pedidos.show');
        Route::post('/pedidos-delivery', [\App\Http\Controllers\Api\V1\Integracion\PedidosController::class, 'store'])
            ->middleware('ability:pedidos:write')->name('pedidos.store');
        Route::patch('/pedidos-delivery/{id}', [\App\Http\Controllers\Api\V1\Integracion\PedidosController::class, 'update'])
            ->middleware('ability:pedidos:write')->name('pedidos.update');
        Route::get('/delivery/config', [\App\Http\Controllers\Api\V1\Integracion\ConfigController::class, 'show'])
            ->middleware('ability:config:read')->name('config.show');
        Route::get('/repartidores', [\App\Http\Controllers\Api\V1\Integracion\ConfigController::class, 'repartidores'])
            ->middleware('ability:config:read')->name('repartidores.index');
    });
});
