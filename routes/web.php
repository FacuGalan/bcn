<?php

use App\Livewire\ComercioSelector;
use App\Livewire\Configuracion\Usuarios;
use App\Livewire\Configuracion\RolesPermisos;
use App\Livewire\Ventas\Ventas;
use App\Livewire\Ventas\NuevaVenta;
use App\Livewire\Compras\Compras;
use App\Livewire\Stock\StockInventario;
use App\Livewire\Cajas\GestionCajas;
use App\Livewire\Dashboard\DashboardSucursal;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

/**
 * Ruta del selector de comercio
 * Accesible solo para usuarios autenticados sin comercio activo
 */
Route::get('/comercio/selector', ComercioSelector::class)
    ->middleware(['auth'])
    ->name('comercio.selector');

/**
 * Rutas protegidas con middleware tenant
 * Requieren autenticación y comercio activo
 */
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {

    // Dashboard principal (redirige al dashboard de sucursal)
    Route::get('dashboard', DashboardSucursal::class)->name('dashboard');

    // Perfil de usuario
    Route::view('profile', 'profile')->name('profile');

    // =========================================
    // MÓDULOS OPERATIVOS
    // =========================================

    /**
     * Ventas / POS (Point of Sale)
     * Gestión de ventas, punto de venta, facturación
     */
    Route::get('ventas', Ventas::class)->name('ventas.index');
    Route::get('ventas/nueva', NuevaVenta::class)->name('ventas.create');

    /**
     * Compras
     * Gestión de compras, proveedores, pagos
     */
    Route::get('compras', Compras::class)->name('compras.index');

    /**
     * Stock / Inventario
     * Control de stock, ajustes, inventario físico
     */
    Route::get('stock', StockInventario::class)->name('stock.index');

    /**
     * Cajas
     * Gestión de cajas, apertura, cierre, movimientos
     */
    Route::get('cajas', GestionCajas::class)->name('cajas.index');

    // =========================================
    // CONFIGURACIÓN
    // =========================================

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        /**
         * Usuarios
         * Gestión de usuarios del comercio
         */
        Route::get('usuarios', Usuarios::class)->name('usuarios');

        /**
         * Roles y Permisos
         * Configuración de roles y permisos del sistema
         */
        Route::get('roles', RolesPermisos::class)->name('roles');
    });
});

require __DIR__.'/auth.php';
