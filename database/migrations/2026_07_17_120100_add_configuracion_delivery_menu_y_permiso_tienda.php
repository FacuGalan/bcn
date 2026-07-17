<?php

use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T10 (spec tienda-online): la configuración de Delivery/Take Away pasa a
 * ser un ítem propio del menú Configuración (antes solo se llegaba por un
 * link interno de la pantalla de pedidos delivery).
 *
 * - Menu item "Delivery / Take Away" bajo Configuración (el permiso
 *   `menu.configuracion-delivery` lo crea MenuItemObserver al insertar).
 * - Permiso funcional `func.tienda.config`: crear/editar la tienda online de
 *   la sucursal (apartado nuevo del componente; el resto sigue bajo
 *   `func.pedidos_delivery.config`).
 * - Ambos asignados a Administrador / Super Administrador en todos los
 *   tenants (los comercios nuevos los reciben por ProvisionComercioCommand,
 *   que otorga todos los permisos a los roles admin).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'tienda.config',
            'etiqueta' => 'Configurar la tienda online',
            'descripcion' => 'Permite crear la tienda online de la sucursal y modificar su configuracion (slug, habilitada, analytics, tema visual).',
            'grupo' => 'Tienda Online',
            'orden' => 1,
        ],
    ];

    private array $menuItems = [
        [
            'nombre' => 'Delivery / Take Away',
            'slug' => 'configuracion-delivery',
            'icono' => 'heroicon-o-truck',
            'route_value' => 'configuracion.delivery',
        ],
    ];

    public function up(): void
    {
        $this->crearMenuItems();
        $this->crearPermisosFuncionales();
    }

    private function crearMenuItems(): void
    {
        $conn = DB::connection('pymes');

        $configParent = $conn->table('menu_items')
            ->where('slug', 'configuracion')
            ->whereNull('parent_id')
            ->first();

        if (! $configParent) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $configParent->id)
            ->max('orden');

        foreach ($this->menuItems as $item) {
            if ($conn->table('menu_items')->where('slug', $item['slug'])->exists()) {
                continue;
            }

            // Eloquent para disparar MenuItemObserver (crea permiso menu.{slug}).
            MenuItem::create([
                'parent_id' => $configParent->id,
                'nombre' => $item['nombre'],
                'slug' => $item['slug'],
                'icono' => $item['icono'],
                'route_type' => 'route',
                'route_value' => $item['route_value'],
                'orden' => ++$maxOrden,
                'activo' => true,
            ]);
        }
    }

    private function crearPermisosFuncionales(): void
    {
        $conn = DB::connection('pymes');

        foreach ($this->permisos as $permiso) {
            if (! $conn->table('permisos_funcionales')->where('codigo', $permiso['codigo'])->exists()) {
                $conn->table('permisos_funcionales')->insert([
                    'codigo' => $permiso['codigo'],
                    'etiqueta' => $permiso['etiqueta'],
                    'descripcion' => $permiso['descripcion'],
                    'grupo' => $permiso['grupo'],
                    'orden' => $permiso['orden'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        PermisoFuncional::syncAllToSpatie();

        $names = array_map(fn ($p) => PermisoFuncional::PERMISSION_PREFIX.$p['codigo'], $this->permisos);
        foreach ($this->menuItems as $item) {
            $names[] = 'menu.'.$item['slug'];
        }

        $permisosSpatie = $conn->table('permissions')
            ->whereIn('name', $names)
            ->get(['id', 'name']);

        if ($permisosSpatie->isEmpty()) {
            return;
        }

        // Asignar a Administrador / Super Administrador en todos los tenants.
        $tablas = DB::connection('pymes_tenant')->select('SHOW TABLES');

        foreach ($tablas as $t) {
            $nombre = array_values((array) $t)[0];
            if (! preg_match('/^(\d{6}_)roles$/', $nombre, $m)) {
                continue;
            }

            $tablaRHP = $m[1].'role_has_permissions';

            try {
                $rolesAdmin = DB::connection('pymes_tenant')->table($nombre)
                    ->whereIn('name', ['Administrador', 'Super Administrador'])
                    ->pluck('id', 'name');

                foreach ($rolesAdmin as $rolId) {
                    foreach ($permisosSpatie as $perm) {
                        $existe = DB::connection('pymes_tenant')->table($tablaRHP)
                            ->where('role_id', $rolId)
                            ->where('permission_id', $perm->id)
                            ->exists();

                        if (! $existe) {
                            DB::connection('pymes_tenant')->table($tablaRHP)->insert([
                                'role_id' => $rolId,
                                'permission_id' => $perm->id,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        // Eloquent para disparar MenuItemObserver (borra el permiso de menú).
        foreach ($this->menuItems as $item) {
            MenuItem::where('slug', $item['slug'])->get()->each->delete();
            $conn->table('permissions')->where('name', 'menu.'.$item['slug'])->delete();
        }

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
