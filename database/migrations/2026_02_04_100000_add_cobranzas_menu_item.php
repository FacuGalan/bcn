<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener el ID del menú padre "Clientes"
        $clientesId = DB::table('menu_items')
            ->where('slug', 'clientes')
            ->whereNull('parent_id')
            ->value('id');

        if (!$clientesId) {
            // Si no existe el menú Clientes, no podemos agregar el hijo
            return;
        }

        // Verificar si ya existe el menú Cobranzas
        $cobranzasExists = DB::table('menu_items')
            ->where('slug', 'cobranzas')
            ->where('parent_id', $clientesId)
            ->exists();

        if (!$cobranzasExists) {
            // Crear el hijo "Cobranzas"
            DB::table('menu_items')->insert([
                'parent_id' => $clientesId,
                'nombre' => 'Cobranzas',
                'slug' => 'cobranzas',
                'icono' => 'heroicon-o-banknotes',
                'route_type' => 'route',
                'route_value' => 'clientes.cobranzas',
                'orden' => 2,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================
        // CREAR PERMISO EN TABLA COMPARTIDA
        // ============================================
        $permissionName = 'menu.cobranzas';

        // Verificar si ya existe el permiso
        $permissionExists = DB::connection('pymes')
            ->table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->exists();

        if (!$permissionExists) {
            $permissionId = DB::connection('pymes')
                ->table('permissions')
                ->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            $permissionId = DB::connection('pymes')
                ->table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->value('id');
        }

        // ============================================
        // ASIGNAR PERMISO A ROLES EN CADA COMERCIO
        // ============================================
        // Roles que deben tener acceso a Cobranzas
        $rolesConAcceso = ['Super Administrador', 'Administrador', 'Gerente'];

        // Obtener todos los comercios activos
        $comercios = DB::connection('config')
            ->table('comercios')
            ->select('id')
            ->get();

        foreach ($comercios as $comercio) {
            // Calcular prefijo del comercio (ej: 000001_)
            $prefix = str_pad((string) $comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            // Verificar que la tabla de roles existe para este comercio
            $rolesTable = $prefix . 'roles';
            $permissionsTable = $prefix . 'role_has_permissions';

            try {
                // Obtener IDs de los roles que deben tener acceso
                $roleIds = DB::connection('pymes')
                    ->table($rolesTable)
                    ->whereIn('name', $rolesConAcceso)
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    // Verificar si ya existe la asignación
                    $exists = DB::connection('pymes')
                        ->table($permissionsTable)
                        ->where('role_id', $roleId)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if (!$exists) {
                        DB::connection('pymes')
                            ->table($permissionsTable)
                            ->insert([
                                'role_id' => $roleId,
                                'permission_id' => $permissionId,
                            ]);
                    }
                }
            } catch (\Exception $e) {
                // Si falla para un comercio (ej: tablas no existen), continuar con el siguiente
                continue;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Obtener el ID del menú padre "Clientes"
        $clientesId = DB::table('menu_items')
            ->where('slug', 'clientes')
            ->whereNull('parent_id')
            ->value('id');

        if ($clientesId) {
            // Eliminar el menú Cobranzas
            DB::table('menu_items')
                ->where('slug', 'cobranzas')
                ->where('parent_id', $clientesId)
                ->delete();
        }

        // ============================================
        // ELIMINAR ASIGNACIONES DE PERMISO EN CADA COMERCIO
        // ============================================
        $permissionId = DB::connection('pymes')
            ->table('permissions')
            ->where('name', 'menu.cobranzas')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            // Obtener todos los comercios
            $comercios = DB::connection('config')
                ->table('comercios')
                ->select('id')
                ->get();

            foreach ($comercios as $comercio) {
                $prefix = str_pad((string) $comercio->id, 6, '0', STR_PAD_LEFT) . '_';
                $permissionsTable = $prefix . 'role_has_permissions';

                try {
                    DB::connection('pymes')
                        ->table($permissionsTable)
                        ->where('permission_id', $permissionId)
                        ->delete();
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Eliminar el permiso compartido
            DB::connection('pymes')
                ->table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
