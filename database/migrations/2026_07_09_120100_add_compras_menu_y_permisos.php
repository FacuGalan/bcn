<?php

use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compras → Costos → Precios (Fase 1, RF-20): menú + permisos.
 *
 * - Grupo de menú PADRE nuevo "Compras" (orden 5, entre Stock y Clientes;
 *   los padres con orden >= 5 se corren +1) con 4 hijos:
 *   Listado de Compras / Proveedores / Pagos a Proveedores / Reportes.
 *   TODOS nacen INACTIVOS: el componente actual de compras se reescribe en la
 *   Fase 6 y las otras pantallas llegan en Fases 5/8 — cada fase activa su
 *   ítem. Los permisos menu.{slug} los crea MenuItemObserver al insertar y
 *   ProvisionComercioCommand los toma de MenuItem::all() (incluye inactivos).
 * - Permisos funcionales grupo "Compras" (crear/confirmar/cancelar/pagar/
 *   pagar_avanzado/revisar_precios) y grupo "Costos" (ver/editar — dato
 *   sensible). El acceso a pantallas va por menu.{slug} (convención del
 *   proyecto; el spec decía middleware pero el proyecto no usa permission:
 *   en rutas — se autoriza en componentes).
 * - Asignación default: Administrador / Super Administrador en todos los
 *   tenants (patrón delivery); el resto de los roles se asigna por comercio
 *   desde Roles y Permisos.
 *
 * Ref: .claude/specs/compras-costos-precios.md (RF-20, D14, D20, D22).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'compras.crear',
            'etiqueta' => 'Cargar compras (borradores)',
            'descripcion' => 'Permite cargar y editar borradores de compra. Confirmar requiere el permiso especifico.',
            'grupo' => 'Compras',
            'orden' => 1,
        ],
        [
            'codigo' => 'compras.confirmar',
            'etiqueta' => 'Confirmar compras',
            'descripcion' => 'Permite confirmar una compra: mueve stock, actualiza costos, registra credito fiscal y deuda con el proveedor.',
            'grupo' => 'Compras',
            'orden' => 2,
        ],
        [
            'codigo' => 'compras.cancelar',
            'etiqueta' => 'Cancelar compras',
            'descripcion' => 'Permite cancelar una compra confirmada (revierte stock, costos, credito fiscal y cuenta corriente por contraasiento).',
            'grupo' => 'Compras',
            'orden' => 3,
        ],
        [
            'codigo' => 'compras.pagar',
            'etiqueta' => 'Pagar a proveedores',
            'descripcion' => 'Permite registrar ordenes de pago a proveedores desde la caja activa (pago al alta de la compra o posterior).',
            'grupo' => 'Compras',
            'orden' => 4,
        ],
        [
            'codigo' => 'compras.pagar_avanzado',
            'etiqueta' => 'Pagar desde otros origenes',
            'descripcion' => 'Permite pagar a proveedores desde otra caja, efectivo de Tesoreria o cuenta de empresa (sin este permiso, solo la caja activa).',
            'grupo' => 'Compras',
            'orden' => 5,
        ],
        [
            'codigo' => 'compras.revisar_precios',
            'etiqueta' => 'Revisar y aplicar precios post-compra',
            'descripcion' => 'Permite aplicar la revision de precios posterior a una compra y el repricing automatico por utilidad.',
            'grupo' => 'Compras',
            'orden' => 6,
        ],
        [
            'codigo' => 'costos.ver',
            'etiqueta' => 'Ver costos y margenes',
            'descripcion' => 'Permite ver costos de articulos, margenes y utilidad en todas las pantallas. Dato sensible: sin este permiso no se muestran columnas ni modales de costo.',
            'grupo' => 'Costos',
            'orden' => 1,
        ],
        [
            'codigo' => 'costos.editar',
            'etiqueta' => 'Editar costos y utilidad',
            'descripcion' => 'Permite editar el costo manual o de reposicion de un articulo y la utilidad objetivo (comercio, categoria o articulo).',
            'grupo' => 'Costos',
            'orden' => 2,
        ],
    ];

    private array $menuItems = [
        [
            'nombre' => 'Listado de Compras',
            'slug' => 'listado-compras',
            'icono' => 'heroicon-o-shopping-bag',
            'route_value' => 'compras.index',
        ],
        [
            'nombre' => 'Proveedores',
            'slug' => 'proveedores',
            'icono' => 'heroicon-o-building-storefront',
            'route_value' => 'compras.proveedores',
        ],
        [
            'nombre' => 'Pagos a Proveedores',
            'slug' => 'pagos-proveedores',
            'icono' => 'heroicon-o-banknotes',
            'route_value' => 'compras.pagos-proveedores',
        ],
        [
            'nombre' => 'Reportes',
            'slug' => 'reportes-compras',
            'icono' => 'heroicon-o-chart-bar',
            'route_value' => 'compras.reportes',
        ],
    ];

    public function up(): void
    {
        $parentId = $this->crearGrupoCompras();

        if ($parentId !== null) {
            $this->crearHijos($parentId);
        }

        $this->crearPermisosFuncionales();
        $this->asignarARolesAdmin();
    }

    private function crearGrupoCompras(): ?int
    {
        $conn = DB::connection('pymes');

        $existente = $conn->table('menu_items')
            ->where('slug', 'compras')
            ->whereNull('parent_id')
            ->first();

        if ($existente) {
            return (int) $existente->id;
        }

        // En entorno de test las tablas de menú pueden no estar seeded.
        if (! $conn->table('menu_items')->whereNull('parent_id')->exists()) {
            return null;
        }

        // Compras va entre Stock (4) y Clientes (5): correr +1 los padres >= 5.
        $conn->table('menu_items')
            ->whereNull('parent_id')
            ->where('orden', '>=', 5)
            ->increment('orden');

        // Eloquent para disparar MenuItemObserver (crea permiso menu.compras).
        $item = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Compras',
            'slug' => 'compras',
            'icono' => 'heroicon-o-shopping-bag',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 5,
            'activo' => false,
        ]);

        return (int) $item->id;
    }

    private function crearHijos(int $parentId): void
    {
        $conn = DB::connection('pymes');
        $orden = 0;

        foreach ($this->menuItems as $item) {
            $orden++;

            if ($conn->table('menu_items')->where('slug', $item['slug'])->exists()) {
                continue;
            }

            MenuItem::create([
                'parent_id' => $parentId,
                'nombre' => $item['nombre'],
                'slug' => $item['slug'],
                'icono' => $item['icono'],
                'route_type' => 'route',
                'route_value' => $item['route_value'],
                'orden' => $orden,
                'activo' => false,
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
    }

    private function asignarARolesAdmin(): void
    {
        $conn = DB::connection('pymes');

        $names = array_map(fn ($p) => PermisoFuncional::PERMISSION_PREFIX.$p['codigo'], $this->permisos);
        $names[] = 'menu.compras';
        foreach ($this->menuItems as $item) {
            $names[] = 'menu.'.$item['slug'];
        }

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

        $slugs = array_merge(array_column($this->menuItems, 'slug'), ['compras']);

        // Eloquent para disparar MenuItemObserver (borra el permiso de menú).
        foreach ($slugs as $slug) {
            MenuItem::where('slug', $slug)->get()->each->delete();
            $conn->table('permissions')->where('name', 'menu.'.$slug)->delete();
        }

        // Devolver el orden de los padres corridos.
        $conn->table('menu_items')
            ->whereNull('parent_id')
            ->where('orden', '>', 5)
            ->decrement('orden');

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
