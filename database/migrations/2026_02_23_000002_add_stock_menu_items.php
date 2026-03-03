<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el menú "Stock" con sus hijos (Inventario, Movimientos, Inventario General, Recetas, Producción)
 * y mueve "Recetas" del menú de Artículos al de Stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        // 1. Eliminar/mover "Recetas" del menú de Artículos ANTES de crear el nuevo
        //    (slug 'recetas' tiene UNIQUE constraint global)
        $articulosParent = $conn->table('menu_items')->where('slug', 'articulos')->whereNull('parent_id')->first();
        if ($articulosParent) {
            $conn->table('menu_items')
                ->where('parent_id', $articulosParent->id)
                ->where('slug', 'recetas')
                ->delete();
        }

        // 2. Buscar o crear padre "Stock"
        $stock = $conn->table('menu_items')->where('slug', 'stock')->whereNull('parent_id')->first();

        if (!$stock) {
            $stockId = $conn->table('menu_items')->insertGetId([
                'parent_id' => null,
                'nombre' => 'Stock',
                'slug' => 'stock',
                'icono' => 'heroicon-o-archive-box',
                'route_type' => 'none',
                'route_value' => null,
                'orden' => 4,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $stockId = $stock->id;
        }

        // 3. Insertar hijos de Stock (si no existen por slug global)
        $hijos = [
            ['nombre' => 'Inventario', 'slug' => 'inventario', 'icono' => 'heroicon-o-clipboard-document-list', 'route_value' => 'stock.index', 'orden' => 1],
            ['nombre' => 'Movimientos', 'slug' => 'movimientos-stock', 'icono' => 'heroicon-o-arrows-right-left', 'route_value' => 'stock.movimientos', 'orden' => 2],
            ['nombre' => 'Inventario General', 'slug' => 'inventario-general', 'icono' => 'heroicon-o-table-cells', 'route_value' => 'stock.inventario-general', 'orden' => 3],
            ['nombre' => 'Recetas', 'slug' => 'recetas', 'icono' => 'heroicon-o-beaker', 'route_value' => 'stock.recetas', 'orden' => 4],
            ['nombre' => 'Producción', 'slug' => 'produccion', 'icono' => 'heroicon-o-cog', 'route_value' => 'stock.produccion', 'orden' => 5],
        ];

        foreach ($hijos as $hijo) {
            $existe = $conn->table('menu_items')->where('slug', $hijo['slug'])->exists();

            if (!$existe) {
                $conn->table('menu_items')->insert([
                    'parent_id' => $stockId,
                    'nombre' => $hijo['nombre'],
                    'slug' => $hijo['slug'],
                    'icono' => $hijo['icono'],
                    'route_type' => 'route',
                    'route_value' => $hijo['route_value'],
                    'orden' => $hijo['orden'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Si existe por slug, reasignar al padre Stock y actualizar ruta
                $conn->table('menu_items')->where('slug', $hijo['slug'])->update([
                    'parent_id' => $stockId,
                    'route_value' => $hijo['route_value'],
                    'orden' => $hijo['orden'],
                    'updated_at' => now(),
                ]);
            }
        }

        // 4. Actualizar orden de Clientes y Configuración
        $conn->table('menu_items')->where('slug', 'clientes')->whereNull('parent_id')->update(['orden' => 5]);
        $conn->table('menu_items')->where('slug', 'configuracion')->whereNull('parent_id')->update(['orden' => 6]);
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        // Eliminar hijos de Stock
        $stock = $conn->table('menu_items')->where('slug', 'stock')->whereNull('parent_id')->first();
        if ($stock) {
            $conn->table('menu_items')->where('parent_id', $stock->id)->delete();
            $conn->table('menu_items')->where('id', $stock->id)->delete();
        }

        // Restaurar orden
        $conn->table('menu_items')->where('slug', 'clientes')->whereNull('parent_id')->update(['orden' => 4]);
        $conn->table('menu_items')->where('slug', 'configuracion')->whereNull('parent_id')->update(['orden' => 5]);
    }
};
