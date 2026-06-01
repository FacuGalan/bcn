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
