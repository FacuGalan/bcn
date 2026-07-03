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
            Route::post('/envios/cotizar', [\App\Http\Controllers\Api\V1\CotizacionController::class, 'envio'])->name('tienda.envios.cotizar');
            Route::post('/carrito/cotizar', [\App\Http\Controllers\Api\V1\CotizacionController::class, 'carrito'])->name('tienda.carrito.cotizar');
            Route::post('/pedidos', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'store'])
                ->middleware('throttle:15,1')->name('tienda.pedidos.store');
            Route::get('/pedidos/{token}', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'show'])->name('tienda.pedidos.seguimiento');
            Route::post('/pedidos/{token}/cancelar', [\App\Http\Controllers\Api\V1\PedidoPublicoController::class, 'cancelar'])
                ->middleware('throttle:10,1')->name('tienda.pedidos.cancelar');
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
