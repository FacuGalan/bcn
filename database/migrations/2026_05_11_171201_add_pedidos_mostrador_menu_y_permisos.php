<?php

use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR2.A (Pedidos por Mostrador): menu_item bajo Ventas + permisos funcionales.
 *
 * Permisos funcionales (compartidos en pymes.permisos_funcionales):
 * - pedidos_mostrador.cobrar: registrar pagos al alta o posteriores.
 * - pedidos_mostrador.convertir_venta: disparar conversion a Venta.
 * - pedidos_mostrador.resetear_numeracion: resetear contador por sucursal.
 * - pedidos_mostrador.cancelar: cancelar pedido (con motivo + contraasientos).
 *
 * El permiso de acceso al modulo (menu.pedidos-mostrador) lo genera
 * automaticamente ProvisionComercioCommand al iterar menu_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        // ── Menu Item bajo Ventas ──
        $ventasParent = $conn->table('menu_items')
            ->where('slug', 'ventas')
            ->whereNull('parent_id')
            ->first();

        if (! $ventasParent) {
            // En entorno de test las tablas de menu pueden no estar seeded.
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $ventasParent->id)
            ->max('orden');

        if (! $conn->table('menu_items')->where('slug', 'pedidos-mostrador')->exists()) {
            $conn->table('menu_items')->insert([
                'parent_id' => $ventasParent->id,
                'nombre' => 'Pedidos por Mostrador',
                'slug' => 'pedidos-mostrador',
                'icono' => 'heroicon-o-clipboard-document-list',
                'route_type' => 'route',
                'route_value' => 'pedidos.mostrador',
                'orden' => $maxOrden + 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Permisos Funcionales ──
        $permisos = [
            [
                'codigo' => 'pedidos_mostrador.cobrar',
                'etiqueta' => 'Cobrar pedido por mostrador',
                'descripcion' => 'Permite registrar pagos sobre un pedido por mostrador (al alta o posteriormente).',
                'orden' => 1,
            ],
            [
                'codigo' => 'pedidos_mostrador.convertir_venta',
                'etiqueta' => 'Convertir pedido en venta',
                'descripcion' => 'Permite ejecutar la conversion de pedido por mostrador en Venta (emite comprobante fiscal si aplica).',
                'orden' => 2,
            ],
            [
                'codigo' => 'pedidos_mostrador.resetear_numeracion',
                'etiqueta' => 'Resetear numeracion de pedidos',
                'descripcion' => 'Permite resetear el contador de numeracion de pedidos por mostrador en la sucursal.',
                'orden' => 3,
            ],
            [
                'codigo' => 'pedidos_mostrador.cancelar',
                'etiqueta' => 'Cancelar pedido por mostrador',
                'descripcion' => 'Permite cancelar un pedido (anula pagos + revierte stock) con motivo obligatorio.',
                'orden' => 4,
            ],
        ];

        foreach ($permisos as $permiso) {
            if (! $conn->table('permisos_funcionales')->where('codigo', $permiso['codigo'])->exists()) {
                $conn->table('permisos_funcionales')->insert([
                    'codigo' => $permiso['codigo'],
                    'etiqueta' => $permiso['etiqueta'],
                    'descripcion' => $permiso['descripcion'],
                    'grupo' => 'Pedidos por Mostrador',
                    'orden' => $permiso['orden'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        PermisoFuncional::syncAllToSpatie();
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        $conn->table('menu_items')->where('slug', 'pedidos-mostrador')->delete();

        $codigos = [
            'pedidos_mostrador.cobrar',
            'pedidos_mostrador.convertir_venta',
            'pedidos_mostrador.resetear_numeracion',
            'pedidos_mostrador.cancelar',
        ];

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();

        $names = array_map(fn ($c) => 'func.'.$c, $codigos);
        $conn->table('permissions')->whereIn('name', $names)->delete();
    }
};
