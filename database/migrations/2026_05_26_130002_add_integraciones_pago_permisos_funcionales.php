<?php

use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — permisos funcionales (Fase 1).
 *
 * Crea 4 permisos en `pymes.permisos_funcionales`, los sincroniza a
 * Spatie (`pymes.permissions`) y los asigna a Administrador y
 * Super Administrador en todos los tenants existentes (idempotente).
 *
 * Sigue el patrón de 2026_05_19_120002_add_invitacion_permisos_funcionales.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md ("Permisos nuevos").
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'integraciones_pago.administrar',
            'etiqueta' => 'Administrar integraciones de pago',
            'descripcion' => 'Configurar integraciones de pago (MercadoPago, etc.) por sucursal: credenciales, modo test/produccion, timeout.',
            'grupo' => 'Integraciones de Pago',
            'orden' => 1,
        ],
        [
            'codigo' => 'integraciones_pago.ver_transacciones',
            'etiqueta' => 'Ver transacciones de integraciones de pago',
            'descripcion' => 'Acceso al historial y detalle de cobros realizados via integraciones externas.',
            'grupo' => 'Integraciones de Pago',
            'orden' => 2,
        ],
        [
            'codigo' => 'integraciones_pago.confirmar_manual',
            'etiqueta' => 'Confirmar manualmente cobro de integracion',
            'descripcion' => 'Permite confirmar manualmente que un cobro via integracion fue recibido, cuando el sistema no lo detecta automaticamente. Queda auditado.',
            'grupo' => 'Integraciones de Pago',
            'orden' => 3,
        ],
        [
            'codigo' => 'integraciones_pago.cancelar',
            'etiqueta' => 'Cancelar cobro de integracion',
            'descripcion' => 'Permite cancelar una transaccion de cobro pendiente antes de que el cliente pague.',
            'grupo' => 'Integraciones de Pago',
            'orden' => 4,
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
