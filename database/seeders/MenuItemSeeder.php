<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para la estructura del menú del sistema
 *
 * Crea una estructura de menú compartida con módulos típicos de gestión:
 * - Dashboard
 * - Ventas (con submódulos)
 * - Artículos (con submódulos)
 * - Configuración (con submódulos)
 *
 * IMPORTANTE: Este seeder crea la estructura de menú compartida (sin prefijo)
 * que será la misma para todos los comercios. Los permisos se generarán
 * automáticamente desde estos items de menú.
 *
 * @package Database\Seeders
 * @version 2.0.0
 */
class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla si ya existe data
        MenuItem::query()->delete();

        // NOTA: Dashboard no está en el menú dinámico
        // Solo accesible desde el logo de la aplicación

        // 1. VENTAS (padre con hijos)
        $ventas = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Ventas',
            'slug' => 'ventas',
            'icono' => 'heroicon-o-shopping-cart',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 1,
            'activo' => true,
        ]);

        // Hijos de Ventas
        MenuItem::create([
            'parent_id' => $ventas->id,
            'nombre' => 'Nueva Venta',
            'slug' => 'nueva-venta',
            'icono' => 'heroicon-o-plus-circle',
            'route_type' => 'route',
            'route_value' => 'ventas.create',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $ventas->id,
            'nombre' => 'Listado de Ventas',
            'slug' => 'listado-ventas',
            'icono' => 'heroicon-o-list-bullet',
            'route_type' => 'route',
            'route_value' => 'ventas.index',
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $ventas->id,
            'nombre' => 'Reportes',
            'slug' => 'reportes-ventas',
            'icono' => 'heroicon-o-chart-bar',
            'route_type' => 'route',
            'route_value' => 'ventas.reportes',
            'orden' => 3,
            'activo' => true,
        ]);

        // 2. CAJAS (padre con hijos)
        $cajas = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Cajas',
            'slug' => 'cajas',
            'icono' => 'heroicon-o-calculator',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 2,
            'activo' => true,
        ]);

        // Hijos de Cajas
        MenuItem::create([
            'parent_id' => $cajas->id,
            'nombre' => 'Turno Actual',
            'slug' => 'turno-actual',
            'icono' => 'heroicon-o-clock',
            'route_type' => 'route',
            'route_value' => 'cajas.turno-actual',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $cajas->id,
            'nombre' => 'Movimientos Manuales',
            'slug' => 'movimientos-manuales',
            'icono' => 'heroicon-o-arrows-right-left',
            'route_type' => 'route',
            'route_value' => 'cajas.movimientos-manuales',
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $cajas->id,
            'nombre' => 'Historial de Turnos',
            'slug' => 'historial-turnos',
            'icono' => 'heroicon-o-document-text',
            'route_type' => 'route',
            'route_value' => 'cajas.historial-turnos',
            'orden' => 3,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $cajas->id,
            'nombre' => 'Tesorería',
            'slug' => 'tesoreria',
            'icono' => 'heroicon-o-banknotes',
            'route_type' => 'route',
            'route_value' => 'tesoreria.index',
            'orden' => 4,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $cajas->id,
            'nombre' => 'Reportes',
            'slug' => 'reportes-cajas',
            'icono' => 'heroicon-o-chart-bar',
            'route_type' => 'route',
            'route_value' => 'tesoreria.reportes',
            'orden' => 5,
            'activo' => true,
        ]);

        // 3. ARTÍCULOS (padre con hijos)
        $articulos = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Artículos',
            'slug' => 'articulos',
            'icono' => 'heroicon-o-cube',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 3,
            'activo' => true,
        ]);

        // Hijos de Artículos
        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Listado de Artículos',
            'slug' => 'listado-articulos',
            'icono' => 'heroicon-o-list-bullet',
            'route_type' => 'route',
            'route_value' => 'articulos.gestionar',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Categorías',
            'slug' => 'categorias',
            'icono' => 'heroicon-o-folder',
            'route_type' => 'route',
            'route_value' => 'articulos.categorias',
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Etiquetas',
            'slug' => 'etiquetas',
            'icono' => 'heroicon-o-tag',
            'route_type' => 'route',
            'route_value' => 'articulos.etiquetas',
            'orden' => 3,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Listas de Precios',
            'slug' => 'listas-precios',
            'icono' => 'heroicon-o-currency-dollar',
            'route_type' => 'route',
            'route_value' => 'configuracion.precios',
            'orden' => 4,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Promociones',
            'slug' => 'promociones',
            'icono' => 'heroicon-o-gift',
            'route_type' => 'route',
            'route_value' => 'configuracion.promociones',
            'orden' => 5,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Promociones Especiales',
            'slug' => 'promociones-especiales',
            'icono' => 'heroicon-o-star',
            'route_type' => 'route',
            'route_value' => 'configuracion.promociones-especiales',
            'orden' => 6,
            'activo' => true,
        ]);

        // 4. CLIENTES (padre con hijos)
        $clientes = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Clientes',
            'slug' => 'clientes',
            'icono' => 'heroicon-o-user-group',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 4,
            'activo' => true,
        ]);

        // Hijos de Clientes
        MenuItem::create([
            'parent_id' => $clientes->id,
            'nombre' => 'Listado de Clientes',
            'slug' => 'listado-clientes',
            'icono' => 'heroicon-o-list-bullet',
            'route_type' => 'route',
            'route_value' => 'clientes.index',
            'orden' => 1,
            'activo' => true,
        ]);

        // 5. CONFIGURACIÓN (padre con hijos)
        $configuracion = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Configuración',
            'slug' => 'configuracion',
            'icono' => 'heroicon-o-cog-6-tooth',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 5,
            'activo' => true,
        ]);

        // Hijos de Configuración
        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Usuarios',
            'slug' => 'usuarios',
            'icono' => 'heroicon-o-users',
            'route_type' => 'route',
            'route_value' => 'configuracion.usuarios',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Roles y Permisos',
            'slug' => 'roles-permisos',
            'icono' => 'heroicon-o-shield-check',
            'route_type' => 'route',
            'route_value' => 'configuracion.roles',
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Empresa',
            'slug' => 'empresa',
            'icono' => 'heroicon-o-building-office',
            'route_type' => 'route',
            'route_value' => 'configuracion.empresa',
            'orden' => 3,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Parámetros',
            'slug' => 'parametros',
            'icono' => 'heroicon-o-adjustments-horizontal',
            'route_type' => 'route',
            'route_value' => 'configuracion.parametros',
            'orden' => 4,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Impresoras',
            'slug' => 'impresoras',
            'icono' => 'heroicon-o-printer',
            'route_type' => 'route',
            'route_value' => 'configuracion.impresoras',
            'orden' => 5,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $configuracion->id,
            'nombre' => 'Formas de Pago',
            'slug' => 'formas-pago',
            'icono' => 'heroicon-o-credit-card',
            'route_type' => 'route',
            'route_value' => 'configuracion.formas-pago',
            'orden' => 6,
            'activo' => true,
        ]);
    }
}
