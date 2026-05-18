<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix retroactivo: asignar permisos func.pedidos_mostrador.* a los roles
 * "Administrador" y "Super Administrador" en todos los tenants existentes.
 *
 * Causa raiz: la migracion add_pedidos_mostrador_menu_y_permisos crea los
 * permisos en pymes.permisos_funcionales y sincroniza a pymes.permissions
 * via PermisoFuncional::syncAllToSpatie(), pero NUNCA los asigna a roles
 * existentes. Solo el MenuItemObserver hace eso para los permisos menu.*.
 *
 * Sintoma reportado: los botones Cobrar / Convertir / Cancelar no aparecian
 * en la lista de pedidos para usuarios Administrador (solo aparecian para
 * is_system_admin=1 que bypasea Spatie).
 *
 * Idempotente: solo inserta si la relacion role_has_permission no existe.
 * Aplica a TODOS los tenants vivos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $codigos = [
            'func.pedidos_mostrador.cobrar',
            'func.pedidos_mostrador.convertir_venta',
            'func.pedidos_mostrador.cancelar',
            'func.pedidos_mostrador.resetear_numeracion',
        ];

        $permisos = DB::connection('pymes')->table('permissions')
            ->whereIn('name', $codigos)
            ->get(['id', 'name']);

        if ($permisos->isEmpty()) {
            return;
        }

        // Buscar todas las tablas tenant `XXXXXX_roles` (no `model_has_roles`).
        $tablas = DB::connection('pymes_tenant')->select('SHOW TABLES');
        foreach ($tablas as $t) {
            $nombre = array_values((array) $t)[0];
            if (! preg_match('/^(\d{6}_)roles$/', $nombre, $m)) {
                continue;
            }

            $prefix = $m[1];
            $tablaRHP = $prefix.'role_has_permissions';

            try {
                $rolesAdmin = DB::connection('pymes_tenant')->table($nombre)
                    ->whereIn('name', ['Administrador', 'Super Administrador'])
                    ->pluck('id', 'name');

                if ($rolesAdmin->isEmpty()) {
                    continue;
                }

                foreach ($rolesAdmin as $rolId) {
                    foreach ($permisos as $perm) {
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
                // Tenant con tablas incompletas: skipear, no romper el resto.
                continue;
            }
        }
    }

    public function down(): void
    {
        // No revertir: las asignaciones son aditivas y safe.
    }
};
