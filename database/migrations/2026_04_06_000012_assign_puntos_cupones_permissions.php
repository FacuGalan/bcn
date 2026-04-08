<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea los permisos Spatie de menú para "Programa de Puntos" y "Cupones",
 * y asigna todos los permisos nuevos (menú + funcionales) al rol
 * Super Administrador y Administrador de comercios existentes.
 *
 * Necesario porque la migración 000011 creó los menu_items y permisos funcionales
 * pero no creó los permisos Spatie de menú ni los asignó a roles existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');
        $now = now();

        // ── 1. Crear permisos Spatie de menú si no existen ──
        $menuPermisos = ['menu.programa-puntos', 'menu.cupones'];
        foreach ($menuPermisos as $permName) {
            if (! $conn->table('permissions')->where('name', $permName)->where('guard_name', 'web')->exists()) {
                $conn->table('permissions')->insert([
                    'name' => $permName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ── 2. Obtener IDs de todos los permisos nuevos ──
        $allPermNames = array_merge($menuPermisos, ['func.descuento_general', 'func.puntos_ajuste_manual']);
        $permIds = $conn->table('permissions')
            ->whereIn('name', $allPermNames)
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->toArray();

        if (empty($permIds)) {
            return;
        }

        // ── 3. Asignar a roles Super Administrador y Administrador de cada comercio ──
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // Obtener IDs de roles Super Administrador y Administrador
                $roleIds = DB::connection('pymes')->select(
                    "SELECT id FROM `{$prefix}roles` WHERE name IN ('Super Administrador', 'Administrador')"
                );

                foreach ($roleIds as $role) {
                    foreach ($permIds as $permId) {
                        // Insertar solo si no existe
                        $exists = DB::connection('pymes')->select(
                            "SELECT 1 FROM `{$prefix}role_has_permissions` WHERE role_id = ? AND permission_id = ? LIMIT 1",
                            [$role->id, $permId]
                        );

                        if (empty($exists)) {
                            DB::connection('pymes')->insert(
                                "INSERT INTO `{$prefix}role_has_permissions` (role_id, permission_id) VALUES (?, ?)",
                                [$role->id, $permId]
                            );
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
        $conn = DB::connection('pymes');

        // Quitar permisos de menú (los funcionales los maneja la migración 000011)
        $permIds = $conn->table('permissions')
            ->whereIn('name', ['menu.programa-puntos', 'menu.cupones'])
            ->pluck('id')
            ->toArray();

        if (! empty($permIds)) {
            $comercios = DB::connection('config')->table('comercios')->get();

            foreach ($comercios as $comercio) {
                $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
                try {
                    DB::connection('pymes')->delete(
                        "DELETE FROM `{$prefix}role_has_permissions` WHERE permission_id IN (".implode(',', $permIds).')'
                    );
                } catch (\Exception $e) {
                    continue;
                }
            }

            $conn->table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
