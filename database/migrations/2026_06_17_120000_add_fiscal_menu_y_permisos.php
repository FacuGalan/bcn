<?php

use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo Fiscal (sistema-impositivo Fase 7, RF-10): menú + permisos funcionales.
 *
 * - Menú top-level "Fiscal" con dos hijos: "Posición fiscal" (fiscal.posicion)
 *   y "Libros IVA" (fiscal.libros). MenuItemObserver crea el permiso de menú
 *   `menu.{slug}` de cada item al insertarlo, y lo asigna a los roles admin.
 * - Permisos funcionales (`func.fiscal.*`): gate de cada acción/pantalla.
 *   Se crean los cuatro del módulo (RF-10) aunque la Fase 7 solo cablee
 *   posición y libros; `movimientos` (alta manual RF-08) y `configuracion`
 *   (config por CUIT) los consumen las fases pendientes.
 *
 * Admin-only por ahora (no se agrega a Gerente): seedRolesYPermisos del
 * ProvisionComercioCommand es data-driven (asigna TODOS los menús y funcionales
 * a Super Administrador/Administrador), así que los comercios nuevos quedan
 * provistos sin tocar el Command.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 7, RF-09/RF-10).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'fiscal.posicion',
            'etiqueta' => 'Ver posición fiscal',
            'descripcion' => 'Permite consultar la posición de IVA e IIBB por CUIT y período (débito, crédito, percepciones y retenciones a cuenta, saldo).',
            'grupo' => 'Fiscal',
            'orden' => 1,
        ],
        [
            'codigo' => 'fiscal.libros',
            'etiqueta' => 'Ver libros de IVA',
            'descripcion' => 'Permite consultar y exportar los subdiarios de IVA ventas y compras por CUIT y período.',
            'grupo' => 'Fiscal',
            'orden' => 2,
        ],
        [
            'codigo' => 'fiscal.movimientos',
            'etiqueta' => 'Gestionar movimientos fiscales',
            'descripcion' => 'Permite registrar manualmente y anular (por contraasiento) movimientos del ledger fiscal.',
            'grupo' => 'Fiscal',
            'orden' => 3,
        ],
        [
            'codigo' => 'fiscal.configuracion',
            'etiqueta' => 'Configurar impuestos por CUIT',
            'descripcion' => 'Permite administrar la configuración impositiva de cada CUIT (impuestos alcanzados, alícuotas, agente de percepción/retención).',
            'grupo' => 'Fiscal',
            'orden' => 4,
        ],
    ];

    public function up(): void
    {
        $this->crearMenu();
        $this->crearPermisosFuncionales();
    }

    private function crearMenu(): void
    {
        $conn = DB::connection('pymes');

        if (! $conn->getSchemaBuilder()->hasTable('menu_items')) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        // Padre top-level "Fiscal".
        $padre = $conn->table('menu_items')
            ->where('slug', 'fiscal')
            ->whereNull('parent_id')
            ->first();

        if (! $padre) {
            $maxOrdenRaiz = (int) $conn->table('menu_items')
                ->whereNull('parent_id')
                ->max('orden');

            // Eloquent para disparar MenuItemObserver (crea permiso menu.fiscal).
            $padre = MenuItem::create([
                'parent_id' => null,
                'nombre' => 'Fiscal',
                'slug' => 'fiscal',
                'icono' => 'heroicon-o-calculator',
                'route_type' => 'none',
                'route_value' => null,
                'orden' => $maxOrdenRaiz + 1,
                'activo' => true,
            ]);
        }

        $padreId = $padre->id;

        $hijos = [
            [
                'nombre' => 'Posición fiscal',
                'slug' => 'fiscal-posicion',
                'icono' => 'heroicon-o-scale',
                'route_value' => 'fiscal.posicion',
            ],
            [
                'nombre' => 'Libros IVA',
                'slug' => 'fiscal-libros',
                'icono' => 'heroicon-o-book-open',
                'route_value' => 'fiscal.libros',
            ],
        ];

        $orden = (int) $conn->table('menu_items')->where('parent_id', $padreId)->max('orden');

        foreach ($hijos as $hijo) {
            if ($conn->table('menu_items')->where('slug', $hijo['slug'])->exists()) {
                continue;
            }

            $orden++;

            // Eloquent para disparar MenuItemObserver (crea permiso menu.{slug}).
            MenuItem::create([
                'parent_id' => $padreId,
                'nombre' => $hijo['nombre'],
                'slug' => $hijo['slug'],
                'icono' => $hijo['icono'],
                'route_type' => 'route',
                'route_value' => $hijo['route_value'],
                'orden' => $orden,
                'activo' => true,
            ]);
        }
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

        // Eloquent para disparar MenuItemObserver (borra los permisos de menú).
        MenuItem::whereIn('slug', ['fiscal-posicion', 'fiscal-libros', 'fiscal'])->get()->each->delete();
        $conn->table('permissions')
            ->whereIn('name', ['menu.fiscal', 'menu.fiscal-posicion', 'menu.fiscal-libros'])
            ->delete();

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
