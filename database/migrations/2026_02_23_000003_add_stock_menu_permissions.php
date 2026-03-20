<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea permisos de menú para los items de Stock y Producción,
 * y los asigna a los roles Super Administrador y Administrador de cada comercio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pymes = DB::connection('pymes');
        $now = now();

        // 1. Obtener los slugs de todos los menu_items bajo "Stock" + el padre
        $stockParent = $pymes->table('menu_items')->where('slug', 'stock')->whereNull('parent_id')->first();
        if (! $stockParent) {
            return;
        }

        $slugs = $pymes->table('menu_items')
            ->where('id', $stockParent->id)
            ->orWhere('parent_id', $stockParent->id)
            ->pluck('slug')
            ->toArray();

        // 2. Crear permisos en tabla compartida permissions (si no existen)
        $permissionIds = [];
        foreach ($slugs as $slug) {
            $permName = 'menu.'.$slug;

            $perm = $pymes->table('permissions')
                ->where('name', $permName)
                ->where('guard_name', 'web')
                ->first();

            if (! $perm) {
                $permId = $pymes->table('permissions')->insertGetId([
                    'name' => $permName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $permId = $perm->id;
            }

            $permissionIds[] = $permId;
        }

        // 3. Asignar permisos a Super Administrador y Administrador de cada comercio
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $tenant = DB::connection('pymes');

                // Buscar roles Super Administrador y Administrador
                $roles = $tenant->table("{$prefix}roles")
                    ->whereIn('name', ['Super Administrador', 'Administrador'])
                    ->pluck('id')
                    ->toArray();

                foreach ($roles as $roleId) {
                    foreach ($permissionIds as $permId) {
                        // Evitar duplicados
                        $exists = $tenant->table("{$prefix}role_has_permissions")
                            ->where('permission_id', $permId)
                            ->where('role_id', $roleId)
                            ->exists();

                        if (! $exists) {
                            $tenant->table("{$prefix}role_has_permissions")->insert([
                                'permission_id' => $permId,
                                'role_id' => $roleId,
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $pymes = DB::connection('pymes');

        // Obtener IDs de permisos de Stock
        $permIds = $pymes->table('permissions')
            ->whereIn('name', [
                'menu.stock',
                'menu.inventario',
                'menu.movimientos-stock',
                'menu.inventario-general',
                'menu.recetas',
                'menu.produccion',
            ])
            ->pluck('id')
            ->toArray();

        if (empty($permIds)) {
            return;
        }

        // Eliminar asignaciones de cada comercio
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')
                    ->table("{$prefix}role_has_permissions")
                    ->whereIn('permission_id', $permIds)
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }

        // Eliminar permisos compartidos
        $pymes->table('permissions')->whereIn('id', $permIds)->delete();
    }
};
