<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renombrar menu item "Tipos de Cambio" → "Monedas"
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'tipos-cambio')
            ->update([
                'nombre' => 'Monedas',
                'slug' => 'monedas',
                'route_value' => 'configuracion.monedas',
                'icono' => 'heroicon-o-currency-dollar',
            ]);

        // 2. Actualizar permiso menu.tipos-cambio → menu.monedas
        DB::connection('pymes')->table('permissions')
            ->where('name', 'menu.tipos-cambio')
            ->where('guard_name', 'web')
            ->update(['name' => 'menu.monedas']);

        // 3. Eliminar menu item "Parámetros" y su permiso
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'parametros')
            ->delete();

        // Obtener ID del permiso para limpiar asignaciones en todos los tenants
        $permParametros = DB::connection('pymes')->table('permissions')
            ->where('name', 'menu.parametros')
            ->where('guard_name', 'web')
            ->first();

        if ($permParametros) {
            // Limpiar role_has_permissions en todos los tenants
            $comercios = DB::connection('config')->table('comercios')->get();
            foreach ($comercios as $comercio) {
                $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';
                try {
                    DB::connection('pymes')->statement("
                        DELETE FROM `{$prefix}role_has_permissions`
                        WHERE `permission_id` = ?
                    ", [$permParametros->id]);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Eliminar permiso
            DB::connection('pymes')->table('permissions')
                ->where('id', $permParametros->id)
                ->delete();
        }

        // 4. Reordenar items de Configuración: Impresoras→4, Formas de Pago→5, Monedas→6
        $configParent = DB::connection('pymes')->table('menu_items')
            ->where('slug', 'configuracion')
            ->first();

        if ($configParent) {
            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'impresoras')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 4]);

            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'formas-pago')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 5]);

            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'monedas')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 6]);
        }
    }

    public function down(): void
    {
        // Revertir renombrado de menu item
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'monedas')
            ->update([
                'nombre' => 'Tipos de Cambio',
                'slug' => 'tipos-cambio',
                'route_value' => 'configuracion.tipos-cambio',
                'icono' => 'heroicon-o-arrows-right-left',
            ]);

        // Revertir permiso
        DB::connection('pymes')->table('permissions')
            ->where('name', 'menu.monedas')
            ->where('guard_name', 'web')
            ->update(['name' => 'menu.tipos-cambio']);

        // Restaurar Parámetros
        $configParent = DB::connection('pymes')->table('menu_items')
            ->where('slug', 'configuracion')
            ->first();

        if ($configParent) {
            DB::connection('pymes')->table('menu_items')->insert([
                'parent_id' => $configParent->id,
                'nombre' => 'Parámetros',
                'slug' => 'parametros',
                'icono' => 'heroicon-o-adjustments-horizontal',
                'route_type' => 'route',
                'route_value' => 'configuracion.parametros',
                'orden' => 4,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reordenar
            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'impresoras')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 5]);

            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'formas-pago')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 6]);

            DB::connection('pymes')->table('menu_items')
                ->where('slug', 'tipos-cambio')
                ->where('parent_id', $configParent->id)
                ->update(['orden' => 7]);
        }
    }
};
