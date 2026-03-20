<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pdo = DB::connection('pymes')->getPdo();

        // Shift existing menu items order to make room for Bancos at position 3
        DB::connection('pymes')->statement('
            UPDATE menu_items SET orden = orden + 1
            WHERE parent_id IS NULL AND orden >= 3
        ');

        // Create parent: Bancos
        DB::connection('pymes')->table('menu_items')->insert([
            'parent_id' => null,
            'nombre' => 'Bancos',
            'slug' => 'bancos',
            'icono' => 'heroicon-o-building-library',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 3,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bancosId = $pdo->lastInsertId();

        // Create children
        $children = [
            ['nombre' => 'Resumen', 'slug' => 'resumen-cuentas', 'icono' => 'heroicon-o-chart-bar', 'route_value' => 'bancos.resumen', 'orden' => 1],
            ['nombre' => 'Cuentas', 'slug' => 'cuentas-empresa', 'icono' => 'heroicon-o-credit-card', 'route_value' => 'bancos.cuentas', 'orden' => 2],
            ['nombre' => 'Movimientos', 'slug' => 'movimientos-cuenta', 'icono' => 'heroicon-o-arrows-right-left', 'route_value' => 'bancos.movimientos', 'orden' => 3],
            ['nombre' => 'Transferencias', 'slug' => 'transferencias-cuenta', 'icono' => 'heroicon-o-arrow-path', 'route_value' => 'bancos.transferencias', 'orden' => 4],
        ];

        foreach ($children as $child) {
            DB::connection('pymes')->table('menu_items')->insert([
                'parent_id' => $bancosId,
                'nombre' => $child['nombre'],
                'slug' => $child['slug'],
                'icono' => $child['icono'],
                'route_type' => 'route',
                'route_value' => $child['route_value'],
                'orden' => $child['orden'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create permissions in pymes.permissions
        $slugs = ['bancos', 'resumen-cuentas', 'cuentas-empresa', 'movimientos-cuenta', 'transferencias-cuenta'];
        foreach ($slugs as $slug) {
            $permName = 'menu.'.$slug;
            $exists = DB::connection('pymes')->table('permissions')->where('name', $permName)->exists();
            if (! $exists) {
                DB::connection('pymes')->table('permissions')->insert([
                    'name' => $permName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Assign permissions to roles per tenant
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // Get permission IDs
                $permisos = DB::connection('pymes')->table('permissions')
                    ->whereIn('name', array_map(fn ($s) => 'menu.'.$s, $slugs))
                    ->pluck('id', 'name');

                // Get role IDs
                $roles = DB::connection('pymes')->select("SELECT id, name FROM `{$prefix}roles`");
                $roleMap = collect($roles)->pluck('id', 'name');

                foreach ($roles as $role) {
                    $permisosParaRol = [];

                    if (in_array($role->name, ['Super Administrador', 'Administrador', 'Gerente'])) {
                        // All bancos permissions
                        $permisosParaRol = $permisos->values()->toArray();
                    } elseif ($role->name === 'Vendedor') {
                        // Only resumen
                        $permisosParaRol = $permisos->filter(fn ($id, $name) => in_array($name, ['menu.bancos', 'menu.resumen-cuentas']))->values()->toArray();
                    }

                    foreach ($permisosParaRol as $permId) {
                        $exists = DB::connection('pymes')->select("
                            SELECT 1 FROM `{$prefix}role_has_permissions`
                            WHERE role_id = ? AND permission_id = ? LIMIT 1
                        ", [$role->id, $permId]);

                        if (empty($exists)) {
                            DB::connection('pymes')->statement("
                                INSERT INTO `{$prefix}role_has_permissions` (permission_id, role_id)
                                VALUES (?, ?)
                            ", [$permId, $role->id]);
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
        // Remove permission assignments per tenant
        $comercios = DB::connection('config')->table('comercios')->get();
        $slugs = ['bancos', 'resumen-cuentas', 'cuentas-empresa', 'movimientos-cuenta', 'transferencias-cuenta'];
        $permisos = DB::connection('pymes')->table('permissions')
            ->whereIn('name', array_map(fn ($s) => 'menu.'.$s, $slugs))
            ->pluck('id');

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            try {
                foreach ($permisos as $permId) {
                    DB::connection('pymes')->statement("DELETE FROM `{$prefix}role_has_permissions` WHERE permission_id = ?", [$permId]);
                }
            } catch (\Exception $e) {
            }
        }

        // Remove permissions
        DB::connection('pymes')->table('permissions')
            ->whereIn('name', array_map(fn ($s) => 'menu.'.$s, $slugs))
            ->delete();

        // Remove menu items
        DB::connection('pymes')->table('menu_items')->where('slug', 'bancos')->delete();
        DB::connection('pymes')->table('menu_items')->whereIn('slug', ['resumen-cuentas', 'cuentas-empresa', 'movimientos-cuenta', 'transferencias-cuenta'])->delete();

        // Shift back
        DB::connection('pymes')->statement('
            UPDATE menu_items SET orden = orden - 1
            WHERE parent_id IS NULL AND orden > 3
        ');
    }
};
