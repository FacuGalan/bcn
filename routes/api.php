<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImpresionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas de impresión (requieren autenticación web)
Route::middleware(['web', 'auth'])->prefix('impresion')->group(function () {
    Route::get('/venta/{id}/ticket', [ImpresionController::class, 'ticketVenta']);
    Route::get('/factura/{id}', [ImpresionController::class, 'factura']);
    Route::get('/prueba/{id}', [ImpresionController::class, 'prueba']);
    Route::get('/impresoras', [ImpresionController::class, 'listar']);
});

// Firma QZ Tray (público pero con CSRF)
Route::middleware(['web'])->post('/qz/sign', [ImpresionController::class, 'firmarMensaje']);
