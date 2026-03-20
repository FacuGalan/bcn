<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find Configuración parent
        $configuracion = DB::connection('pymes')->table('menu_items')
            ->where('slug', 'configuracion')
            ->first();

        if (! $configuracion) {
            return;
        }

        // Create child: Tipos de Cambio
        DB::connection('pymes')->table('menu_items')->insert([
            'parent_id' => $configuracion->id,
            'nombre' => 'Tipos de Cambio',
            'slug' => 'tipos-cambio',
            'icono' => 'heroicon-o-arrows-right-left',
            'route_type' => 'route',
            'route_value' => 'configuracion.tipos-cambio',
            'orden' => 7,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create permission
        $permName = 'menu.tipos-cambio';
        $exists = DB::connection('pymes')->table('permissions')->where('name', $permName)->exists();
        if (! $exists) {
            DB::connection('pymes')->table('permissions')->insert([
                'name' => $permName,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign permission to roles per tenant
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $permId = DB::connection('pymes')->table('permissions')
                    ->where('name', $permName)
                    ->value('id');

                if (! $permId) {
                    continue;
                }

                $roles = DB::connection('pymes')->select("SELECT id, name FROM `{$prefix}roles`");

                foreach ($roles as $role) {
                    if (in_array($role->name, ['Super Administrador', 'Administrador', 'Gerente'])) {
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
        $permName = 'menu.tipos-cambio';

        // Remove permission assignments per tenant
        $comercios = DB::connection('config')->table('comercios')->get();
        $permId = DB::connection('pymes')->table('permissions')->where('name', $permName)->value('id');

        if ($permId) {
            foreach ($comercios as $comercio) {
                $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
                try {
                    DB::connection('pymes')->statement("DELETE FROM `{$prefix}role_has_permissions` WHERE permission_id = ?", [$permId]);
                } catch (\Exception $e) {
                }
            }
        }

        // Remove permission
        DB::connection('pymes')->table('permissions')->where('name', $permName)->delete();

        // Remove menu item
        DB::connection('pymes')->table('menu_items')->where('slug', 'tipos-cambio')->delete();
    }
};
