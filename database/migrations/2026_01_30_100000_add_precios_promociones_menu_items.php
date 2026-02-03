<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega items de menú para Listas de Precios, Promociones y Promociones Especiales
 * como hijos del menú Artículos, y crea los permisos correspondientes.
 */
return new class extends Migration
{
    protected $connection = 'pymes';

    public function up(): void
    {
        // Obtener el ID del padre "Artículos"
        $articulosId = DB::connection($this->connection)
            ->table('menu_items')
            ->where('slug', 'articulos')
            ->value('id');

        if (!$articulosId) {
            return;
        }

        $now = now();

        // Insertar los nuevos items de menú
        $newItems = [
            [
                'parent_id' => $articulosId,
                'nombre' => 'Listas de Precios',
                'slug' => 'listas-precios',
                'icono' => 'heroicon-o-currency-dollar',
                'route_type' => 'route',
                'route_value' => 'configuracion.precios',
                'orden' => 4,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => $articulosId,
                'nombre' => 'Promociones',
                'slug' => 'promociones',
                'icono' => 'heroicon-o-gift',
                'route_type' => 'route',
                'route_value' => 'configuracion.promociones',
                'orden' => 5,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => $articulosId,
                'nombre' => 'Promociones Especiales',
                'slug' => 'promociones-especiales',
                'icono' => 'heroicon-o-star',
                'route_type' => 'route',
                'route_value' => 'configuracion.promociones-especiales',
                'orden' => 6,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($newItems as $item) {
            // Evitar duplicados
            $exists = DB::connection($this->connection)
                ->table('menu_items')
                ->where('slug', $item['slug'])
                ->exists();

            if (!$exists) {
                DB::connection($this->connection)
                    ->table('menu_items')
                    ->insert($item);
            }
        }

        // Crear los permisos correspondientes
        $newPermissions = [
            'menu.listas-precios',
            'menu.promociones',
            'menu.promociones-especiales',
        ];

        foreach ($newPermissions as $permName) {
            $exists = DB::connection($this->connection)
                ->table('permissions')
                ->where('name', $permName)
                ->where('guard_name', 'web')
                ->exists();

            if (!$exists) {
                DB::connection($this->connection)
                    ->table('permissions')
                    ->insert([
                        'name' => $permName,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
            }
        }

        // Asignar permisos al rol Super Administrador en todos los comercios
        // (aunque el Super Admin tiene bypass, se asignan por consistencia)
        $permissionIds = DB::connection($this->connection)
            ->table('permissions')
            ->whereIn('name', $newPermissions)
            ->pluck('id')
            ->toArray();

        // Obtener todos los roles "Super Administrador" y "Administrador" de todas las tablas de roles tenant
        // Como los roles están en tablas con prefijo de comercio, necesitamos buscar en todas
        // Buscar tablas de roles que NO sean model_has_roles ni role_has_permissions
        $allTables = DB::connection('pymes_tenant')
            ->select("SHOW TABLES LIKE '%_roles'");

        $rolesTables = collect($allTables)->filter(function ($tableObj) {
            $tableName = array_values((array)$tableObj)[0];
            return !str_contains($tableName, 'model_has_roles')
                && !str_contains($tableName, 'role_has_permissions');
        });

        foreach ($rolesTables as $tableObj) {
            $tableName = array_values((array)$tableObj)[0];

            $roles = DB::connection('pymes_tenant')
                ->table($tableName)
                ->whereIn('name', ['Super Administrador', 'Administrador', 'Gerente'])
                ->get();

            // Determinar el prefijo del comercio para la tabla pivot
            $prefix = str_replace('_roles', '', $tableName);
            $pivotTable = $prefix . '_role_has_permissions';

            foreach ($roles as $role) {
                foreach ($permissionIds as $permId) {
                    $exists = DB::connection('pymes_tenant')
                        ->table($pivotTable)
                        ->where('role_id', $role->id)
                        ->where('permission_id', $permId)
                        ->exists();

                    if (!$exists) {
                        DB::connection('pymes_tenant')
                            ->table($pivotTable)
                            ->insert([
                                'role_id' => $role->id,
                                'permission_id' => $permId,
                            ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = ['listas-precios', 'promociones', 'promociones-especiales'];
        $permNames = ['menu.listas-precios', 'menu.promociones', 'menu.promociones-especiales'];

        // Eliminar permisos de roles tenant
        $permissionIds = DB::connection($this->connection)
            ->table('permissions')
            ->whereIn('name', $permNames)
            ->pluck('id')
            ->toArray();

        if (!empty($permissionIds)) {
            $tables = DB::connection('pymes_tenant')
                ->select("SHOW TABLES LIKE '%_role_has_permissions'");

            foreach ($tables as $tableObj) {
                $tableName = array_values((array)$tableObj)[0];
                DB::connection('pymes_tenant')
                    ->table($tableName)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }

        // Eliminar permisos
        DB::connection($this->connection)
            ->table('permissions')
            ->whereIn('name', $permNames)
            ->delete();

        // Eliminar menu items
        DB::connection($this->connection)
            ->table('menu_items')
            ->whereIn('slug', $slugs)
            ->delete();
    }
};
