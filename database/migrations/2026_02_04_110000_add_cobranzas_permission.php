<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea el permiso menu.cobranzas y lo asigna a los roles apropiados
     * en cada comercio (Super Administrador, Administrador, Gerente).
     */
    public function up(): void
    {
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

            // Nombres de tablas con prefijo
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
