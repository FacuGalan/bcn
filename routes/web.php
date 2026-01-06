<?php

use App\Livewire\ComercioSelector;
use App\Livewire\Configuracion\Usuarios;
use App\Livewire\Configuracion\RolesPermisos;
use App\Livewire\Configuracion\ConfiguracionEmpresa;
use App\Livewire\Configuracion\GestionarFormasPago;
use App\Livewire\Configuracion\FormasPagoSucursal;
use App\Livewire\Configuracion\ArticulosSucursal;
use App\Livewire\Configuracion\Precios\ListarPrecios;
use App\Livewire\Configuracion\Precios\WizardListaPrecio;
use App\Livewire\Configuracion\Promociones\ListarPromociones;
use App\Livewire\Configuracion\Promociones\WizardPromocion;
use App\Livewire\Configuracion\PromocionesEspeciales\ListarPromocionesEspeciales;
use App\Livewire\Configuracion\PromocionesEspeciales\WizardPromocionEspecial;
use App\Livewire\Configuracion\FormasPago\ListarFormasPago;
use App\Livewire\Configuracion\Impresoras;
use App\Livewire\Ventas\Ventas;
use App\Livewire\Ventas\NuevaVenta;
use App\Livewire\Compras\Compras;
use App\Livewire\Stock\StockInventario;
use App\Livewire\Cajas\GestionCajas;
use App\Livewire\Dashboard\DashboardSucursal;
use App\Livewire\Articulos\GestionarArticulos;
use App\Livewire\Articulos\GestionarCategorias;
use App\Livewire\Articulos\GestionarEtiquetas;
use App\Livewire\Articulos\AsignarEtiquetas;
use App\Livewire\Articulos\CambioMasivoPrecios;
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
    // ARTÍCULOS
    // =========================================

    Route::prefix('articulos')->name('articulos.')->group(function () {
        /**
         * Listado de Artículos
         * Gestión completa de productos y servicios
         */
        Route::get('/', GestionarArticulos::class)->name('gestionar');

        /**
         * Categorías
         * Gestión de categorías de artículos
         */
        Route::get('categorias', GestionarCategorias::class)->name('categorias');

        /**
         * Etiquetas
         * Gestión de grupos de etiquetas y sus valores
         */
        Route::get('etiquetas', GestionarEtiquetas::class)->name('etiquetas');

        /**
         * Asignar Etiquetas
         * Asignación masiva de etiquetas a artículos
         */
        Route::get('asignar-etiquetas', AsignarEtiquetas::class)->name('asignar-etiquetas');

        /**
         * Cambio Masivo de Precios
         * Actualización de precios en lote con filtros y vista previa
         */
        Route::get('cambio-masivo-precios', CambioMasivoPrecios::class)->name('cambio-masivo-precios');
    });

    // =========================================
    // CONFIGURACIÓN
    // =========================================

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        /**
         * Empresa
         * Configuración de datos de empresa, CUITs y sucursales
         */
        Route::get('empresa', ConfiguracionEmpresa::class)->name('empresa');

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

        /**
         * Formas de Pago
         * Configuración de formas de pago y planes de cuotas
         */
        Route::get('formas-pago', GestionarFormasPago::class)->name('formas-pago');
        Route::get('formas-pago-sucursal', FormasPagoSucursal::class)->name('formas-pago-sucursal');

        /**
         * Gestión de Formas de Pago y Cuotas (Sistema Dinámico)
         * Configuración de formas de pago con planes de cuotas
         */
        Route::get('gestionar-formas-pago', ListarFormasPago::class)->name('gestionar-formas-pago');

        /**
         * Listas de Precios
         * Gestión del sistema de precios dinámico
         */
        Route::get('precios', ListarPrecios::class)->name('precios');
        Route::get('precios/nuevo', WizardListaPrecio::class)->name('precios.nuevo');
        Route::get('precios/{id}/editar', WizardListaPrecio::class)->name('precios.editar');

        /**
         * Promociones
         * Gestión de promociones, descuentos y ofertas
         */
        Route::get('promociones', ListarPromociones::class)->name('promociones');
        Route::get('promociones/nueva', WizardPromocion::class)->name('promociones.nueva');
        Route::get('promociones/{id}/editar', WizardPromocion::class)->name('promociones.editar');

        /**
         * Promociones Especiales (NxM y Combos)
         * Gestión de promociones 2x1, 3x2, packs y combos
         */
        Route::get('promociones-especiales', ListarPromocionesEspeciales::class)->name('promociones-especiales');
        Route::get('promociones-especiales/nueva', WizardPromocionEspecial::class)->name('promociones-especiales.nueva');
        Route::get('promociones-especiales/{id}/editar', WizardPromocionEspecial::class)->name('promociones-especiales.editar');

        /**
         * Artículos por Sucursal
         * Configuración de disponibilidad de artículos por sucursal
         */
        Route::get('articulos-sucursal', ArticulosSucursal::class)->name('articulos-sucursal');

        /**
         * Impresoras
         * Configuración de impresoras por sucursal/caja
         */
        Route::get('impresoras', Impresoras::class)->name('impresoras');
    });
});

require __DIR__.'/auth.php';
