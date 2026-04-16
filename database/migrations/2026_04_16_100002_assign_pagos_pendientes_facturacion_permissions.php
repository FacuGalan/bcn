<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea el permiso Spatie de menú para "Pagos pendientes de facturar" y asigna
 * los permisos del feature (menú + funcionales) a los roles Super Administrador
 * y Administrador de comercios existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');
        $now = now();

        if (! $conn->table('permissions')->where('name', 'menu.pagos-pendientes-facturacion')->where('guard_name', 'web')->exists()) {
            $conn->table('permissions')->insert([
                'name' => 'menu.pagos-pendientes-facturacion',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $allPermNames = [
            'menu.pagos-pendientes-facturacion',
            'func.reintentar_facturacion',
            'func.ver_pagos_pendientes_facturacion',
        ];

        $permIds = $conn->table('permissions')
            ->whereIn('name', $allPermNames)
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->toArray();

        if (empty($permIds)) {
            return;
        }

        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $roleIds = DB::connection('pymes')->select(
                    "SELECT id FROM `{$prefix}roles` WHERE name IN ('Super Administrador', 'Administrador')"
                );

                foreach ($roleIds as $role) {
                    foreach ($permIds as $permId) {
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

        $permIds = $conn->table('permissions')
            ->where('name', 'menu.pagos-pendientes-facturacion')
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
