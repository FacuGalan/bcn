<?php

use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery (Fase 5, RF-14 + seeds): menú + permisos + datos base.
 *
 * - Menu items "Pedidos Delivery" y "Repartidores" bajo Ventas (los permisos
 *   de acceso `menu.pedidos-delivery` / `menu.repartidores` los crea
 *   MenuItemObserver al insertar).
 * - Permisos funcionales `func.pedidos_delivery.*`: espejo de los de
 *   mostrador (cobrar, convertir_venta, cancelar, resetear_numeracion) +
 *   propios de delivery (repartidores, forzar_alcance, config). Asignados a
 *   Administrador / Super Administrador en todos los tenants.
 * - Seeds tenant para comercios EXISTENTES: formas de venta DELIVERY y
 *   TAKEAWAY + canal de venta TIENDA (el motor de precios/promos condiciona
 *   por forma de venta — sin estos seeds las listas y promociones delivery
 *   no aplican). Los comercios nuevos los reciben por
 *   ProvisionComercioCommand::seedFormasYCanalesVenta().
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-14, hallazgo forma_venta_id
 * automático por tipo).
 */
return new class extends Migration
{
    private array $permisos = [
        [
            'codigo' => 'pedidos_delivery.cobrar',
            'etiqueta' => 'Cobrar pedidos delivery',
            'descripcion' => 'Permite cobrar pedidos delivery/take-away: confirmar pagos planificados, abrir el cobro rapido y registrar pagos.',
            'grupo' => 'Pedidos Delivery',
            'orden' => 1,
        ],
        [
            'codigo' => 'pedidos_delivery.convertir_venta',
            'etiqueta' => 'Convertir pedidos delivery en venta',
            'descripcion' => 'Permite convertir un pedido delivery/take-away en venta (facturarlo).',
            'grupo' => 'Pedidos Delivery',
            'orden' => 2,
        ],
        [
            'codigo' => 'pedidos_delivery.cancelar',
            'etiqueta' => 'Cancelar pedidos delivery',
            'descripcion' => 'Permite cancelar pedidos delivery/take-away (contraasienta pagos y revierte stock).',
            'grupo' => 'Pedidos Delivery',
            'orden' => 3,
        ],
        [
            'codigo' => 'pedidos_delivery.resetear_numeracion',
            'etiqueta' => 'Resetear numeracion de pedidos delivery',
            'descripcion' => 'Permite reiniciar el contador de numeros de pedidos delivery de la sucursal.',
            'grupo' => 'Pedidos Delivery',
            'orden' => 4,
        ],
        [
            'codigo' => 'pedidos_delivery.repartidores',
            'etiqueta' => 'Gestionar repartidores, salidas y fondos',
            'descripcion' => 'Permite el ABM de repartidores, asignarlos a pedidos, armar salidas, registrar vueltas con cobros y abrir/reforzar/rendir fondos de repartidor.',
            'grupo' => 'Pedidos Delivery',
            'orden' => 5,
        ],
        [
            'codigo' => 'pedidos_delivery.forzar_alcance',
            'etiqueta' => 'Confirmar pedidos fuera de alcance',
            'descripcion' => 'Permite confirmar un pedido delivery cuya direccion esta fuera del alcance de entrega configurado.',
            'grupo' => 'Pedidos Delivery',
            'orden' => 6,
        ],
        [
            'codigo' => 'pedidos_delivery.config',
            'etiqueta' => 'Configurar delivery de la sucursal',
            'descripcion' => 'Permite modificar la configuracion de delivery de la sucursal (georreferenciacion, costos de envio, zonas, horarios, aceptacion de pedidos externos).',
            'grupo' => 'Pedidos Delivery',
            'orden' => 7,
        ],
    ];

    private array $menuItems = [
        [
            'nombre' => 'Pedidos Delivery',
            'slug' => 'pedidos-delivery',
            'icono' => 'heroicon-o-truck',
            'route_value' => 'pedidos.delivery',
        ],
        [
            'nombre' => 'Repartidores',
            'slug' => 'repartidores',
            'icono' => 'heroicon-o-user-group',
            'route_value' => 'pedidos.repartidores',
        ],
    ];

    public function up(): void
    {
        $this->crearMenuItems();
        $this->crearPermisosFuncionales();
        $this->seedFormasYCanalesVenta();
    }

    private function crearMenuItems(): void
    {
        $conn = DB::connection('pymes');

        $ventasParent = $conn->table('menu_items')
            ->where('slug', 'ventas')
            ->whereNull('parent_id')
            ->first();

        if (! $ventasParent) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $ventasParent->id)
            ->max('orden');

        foreach ($this->menuItems as $item) {
            if ($conn->table('menu_items')->where('slug', $item['slug'])->exists()) {
                continue;
            }

            // Eloquent para disparar MenuItemObserver (crea permiso menu.{slug}).
            MenuItem::create([
                'parent_id' => $ventasParent->id,
                'nombre' => $item['nombre'],
                'slug' => $item['slug'],
                'icono' => $item['icono'],
                'route_type' => 'route',
                'route_value' => $item['route_value'],
                'orden' => ++$maxOrden,
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

        // Menu + funcionales que hay que otorgar a los roles admin de cada tenant.
        $names = array_map(fn ($p) => PermisoFuncional::PERMISSION_PREFIX.$p['codigo'], $this->permisos);
        foreach ($this->menuItems as $item) {
            $names[] = 'menu.'.$item['slug'];
        }

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

    /**
     * Seed de datos base tenant para comercios EXISTENTES: formas de venta
     * DELIVERY/TAKEAWAY + canal TIENDA (idempotente por codigo).
     */
    private function seedFormasYCanalesVenta(): void
    {
        $tablas = DB::connection('pymes_tenant')->select('SHOW TABLES');
        $now = now()->format('Y-m-d H:i:s');

        foreach ($tablas as $t) {
            $nombre = array_values((array) $t)[0];
            if (! preg_match('/^(\d{6}_)formas_venta$/', $nombre, $m)) {
                continue;
            }

            $prefix = $m[1];

            try {
                $formas = [
                    ['nombre' => 'Delivery', 'codigo' => 'DELIVERY', 'descripcion' => 'Entrega a domicilio'],
                    ['nombre' => 'Take Away', 'codigo' => 'TAKEAWAY', 'descripcion' => 'Para llevar (retiro en el local)'],
                ];

                foreach ($formas as $forma) {
                    $existe = DB::connection('pymes_tenant')
                        ->table($nombre)
                        ->where('codigo', $forma['codigo'])
                        ->exists();

                    if (! $existe) {
                        DB::connection('pymes_tenant')->table($nombre)->insert([
                            'nombre' => $forma['nombre'],
                            'codigo' => $forma['codigo'],
                            'descripcion' => $forma['descripcion'],
                            'activo' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                $tablaCanales = $prefix.'canales_venta';
                $existeCanal = DB::connection('pymes_tenant')
                    ->table($tablaCanales)
                    ->where('codigo', 'TIENDA')
                    ->exists();

                if (! $existeCanal) {
                    DB::connection('pymes_tenant')->table($tablaCanales)->insert([
                        'nombre' => 'Tienda Online',
                        'codigo' => 'TIENDA',
                        'descripcion' => 'Pedidos entrados desde la tienda online / API',
                        'activo' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
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
        foreach ($this->menuItems as $item) {
            MenuItem::where('slug', $item['slug'])->get()->each->delete();
            $conn->table('permissions')->where('name', 'menu.'.$item['slug'])->delete();
        }

        $codigos = array_column($this->permisos, 'codigo');
        $names = array_map(fn ($c) => PermisoFuncional::PERMISSION_PREFIX.$c, $codigos);

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', $names)->delete();

        // Los seeds de formas/canales de venta NO se revierten: pueden estar
        // referenciados por pedidos/ventas ya creados.
    }
};
