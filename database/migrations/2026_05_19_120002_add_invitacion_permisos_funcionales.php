<?php

use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feature "invitaciones" (cortesias) - permisos funcionales.
 *
 * Crea 4 permisos en `pymes.permisos_funcionales`, los sincroniza a Spatie
 * `pymes.permissions` y los asigna a los roles "Administrador" y
 * "Super Administrador" en todos los tenants existentes (idempotente, sigue
 * el patron de assign_func_pedidos_mostrador_permissions_to_admin_roles).
 *
 * Espec: .claude/specs/invitaciones-pedidos-ventas.md (Fase 1).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'pedidos_mostrador.invitar_pedido',
            'etiqueta' => 'Invitar pedido completo (Mostrador)',
            'descripcion' => 'Permite marcar un pedido por mostrador completo como cortesia (precio cobrable $0 con motivo registrado).',
            'grupo' => 'Pedidos por Mostrador',
            'orden' => 5,
        ],
        [
            'codigo' => 'pedidos_mostrador.invitar_renglon',
            'etiqueta' => 'Invitar renglon de pedido (Mostrador)',
            'descripcion' => 'Permite marcar renglones individuales de un pedido por mostrador como cortesia.',
            'grupo' => 'Pedidos por Mostrador',
            'orden' => 6,
        ],
        [
            'codigo' => 'ventas.invitar_venta',
            'etiqueta' => 'Invitar venta completa',
            'descripcion' => 'Permite marcar una venta completa como cortesia (precio cobrable $0 con motivo registrado).',
            'grupo' => 'Ventas',
            'orden' => 20,
        ],
        [
            'codigo' => 'ventas.invitar_renglon',
            'etiqueta' => 'Invitar renglon de venta',
            'descripcion' => 'Permite marcar renglones individuales de una venta como cortesia.',
            'grupo' => 'Ventas',
            'orden' => 21,
        ],
    ];

    public function up(): void
    {
        $conn = DB::connection('pymes');

        foreach ($this->permisos as $permiso) {
            if (! $conn->table('permisos_funcionales')->where('codigo', $permiso['codigo'])->exists()) {
                $conn->table('permisos_funcionales')->insert([
                    'codigo' => $permiso['codigo'],
                    'etiqueta' => $permiso['etiqueta'],
                    'descripcion' => $permiso['descripcion'],
                    'grupo' => $permiso['grupo'],
                    'orden' => $permiso['orden'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        PermisoFuncional::syncAllToSpatie();

        $names = array_map(fn ($p) => PermisoFuncional::PERMISSION_PREFIX.$p['codigo'], $this->permisos);

        $permisosSpatie = $conn->table('permissions')
            ->whereIn('name', $names)
            ->get(['id', 'name']);

        if ($permisosSpatie->isEmpty()) {
            return;
        }

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
                    foreach ($permisosSpatie as $perm) {
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
                continue;
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
