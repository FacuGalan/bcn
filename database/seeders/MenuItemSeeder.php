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

        // 2. ARTÍCULOS (padre con hijos)
        $articulos = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Artículos',
            'slug' => 'articulos',
            'icono' => 'heroicon-o-cube',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 2,
            'activo' => true,
        ]);

        // Hijos de Artículos
        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Nuevo Artículo',
            'slug' => 'nuevo-articulo',
            'icono' => 'heroicon-o-plus-circle',
            'route_type' => 'route',
            'route_value' => 'articulos.create',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Listado de Artículos',
            'slug' => 'listado-articulos',
            'icono' => 'heroicon-o-list-bullet',
            'route_type' => 'route',
            'route_value' => 'articulos.index',
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $articulos->id,
            'nombre' => 'Categorías',
            'slug' => 'categorias',
            'icono' => 'heroicon-o-tag',
            'route_type' => 'route',
            'route_value' => 'articulos.categorias',
            'orden' => 3,
            'activo' => true,
        ]);

        // 3. CONFIGURACIÓN (padre con hijos)
        $configuracion = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Configuración',
            'slug' => 'configuracion',
            'icono' => 'heroicon-o-cog-6-tooth',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 3,
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
    }
}
