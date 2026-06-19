<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-08 (sistema-impositivo): item de menú "Movimientos fiscales" bajo el padre
 * "Fiscal", apuntando a la ruta `fiscal.movimientos`. MenuItemObserver crea el
 * permiso de menú `menu.fiscal-movimientos` al insertarlo y lo asigna a los
 * roles admin.
 *
 * El permiso funcional `func.fiscal.movimientos` y el padre "Fiscal" ya existen
 * (migración 2026_06_17_120000_add_fiscal_menu_y_permisos), por eso acá solo se
 * agrega el item de menú nuevo.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-08, Fase 6 / Pantalla 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        if (! $conn->getSchemaBuilder()->hasTable('menu_items')) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        $padre = $conn->table('menu_items')
            ->where('slug', 'fiscal')
            ->whereNull('parent_id')
            ->first();

        if (! $padre) {
            return;
        }

        if ($conn->table('menu_items')->where('slug', 'fiscal-movimientos')->exists()) {
            return;
        }

        $orden = (int) $conn->table('menu_items')->where('parent_id', $padre->id)->max('orden');

        // Eloquent para disparar MenuItemObserver (crea permiso menu.fiscal-movimientos).
        MenuItem::create([
            'parent_id' => $padre->id,
            'nombre' => 'Movimientos fiscales',
            'slug' => 'fiscal-movimientos',
            'icono' => 'heroicon-o-document-text',
            'route_type' => 'route',
            'route_value' => 'fiscal.movimientos',
            'orden' => $orden + 1,
            'activo' => true,
        ]);
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        // Eloquent para disparar MenuItemObserver (borra el permiso de menú).
        MenuItem::where('slug', 'fiscal-movimientos')->get()->each->delete();
        $conn->table('permissions')->where('name', 'menu.fiscal-movimientos')->delete();
    }
};
