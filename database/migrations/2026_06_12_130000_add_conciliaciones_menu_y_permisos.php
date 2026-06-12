<?php

use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de cuenta (Fase 4): menú + permiso funcional.
 *
 * - Menu item "Conciliaciones" bajo Bancos (el permiso de acceso
 *   `menu.conciliaciones-cuenta` lo crea MenuItemObserver al insertar).
 * - Permiso funcional `conciliaciones.aplicar` (aplicar/descartar corridas):
 *   ver el detalle lo controla el permiso de menú; tocar el ledger requiere
 *   este permiso explícito (RF-09).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 4).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'conciliaciones.aplicar',
            'etiqueta' => 'Aplicar conciliaciones de cuenta',
            'descripcion' => 'Permite aplicar o descartar una conciliacion de cuenta contra el proveedor de pago. Aplicar genera los movimientos de ajuste (comisiones, retiros, acreditaciones) en el saldo de la cuenta.',
            'grupo' => 'Bancos',
            'orden' => 1,
        ],
    ];

    public function up(): void
    {
        $this->crearMenuItem();
        $this->crearPermisosFuncionales();
    }

    private function crearMenuItem(): void
    {
        $conn = DB::connection('pymes');

        $bancosParent = $conn->table('menu_items')
            ->where('slug', 'bancos')
            ->whereNull('parent_id')
            ->first();

        if (! $bancosParent) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        if ($conn->table('menu_items')->where('slug', 'conciliaciones-cuenta')->exists()) {
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $bancosParent->id)
            ->max('orden');

        // Eloquent para disparar MenuItemObserver (crea permiso menu.conciliaciones-cuenta).
        MenuItem::create([
            'parent_id' => $bancosParent->id,
            'nombre' => 'Conciliaciones',
            'slug' => 'conciliaciones-cuenta',
            'icono' => 'heroicon-o-scale',
            'route_type' => 'route',
            'route_value' => 'bancos.conciliaciones',
            'orden' => $maxOrden + 1,
            'activo' => true,
        ]);
    }

    private function crearPermisosFuncionales(): void
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

        // Asignar a Administrador / Super Administrador en todos los tenants.
        $tablas = DB::connection('pymes_tenant')->select('SHOW TABLES');

        foreach ($tablas as $t) {
            $nombre = array_values((array) $t)[0];
            if (! preg_match('/^(\d{6}_)roles$/', $nombre, $m)) {
                continue;
            }

            $tablaRHP = $m[1].'role_has_permissions';

            try {
                $rolesAdmin = DB::connection('pymes_tenant')->table($nombre)
                    ->whereIn('name', ['Administrador', 'Super Administrador'])
                    ->pluck('id', 'name');

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

        // Eloquent para disparar MenuItemObserver (borra el permiso de menú).
        MenuItem::where('slug', 'conciliaciones-cuenta')->get()->each->delete();
        $conn->table('permissions')->where('name', 'menu.conciliaciones-cuenta')->delete();

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
