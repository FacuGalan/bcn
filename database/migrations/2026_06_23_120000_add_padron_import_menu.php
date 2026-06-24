<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Importador de padrón ARBA/AGIP (sistema-impositivo Fase 10b, RF-14): ítem de menú.
 *
 * Agrega "Importar padrón" como hijo del menú "Fiscal" existente, apuntando a la
 * ruta `fiscal.padrones`. El MenuItemObserver crea el permiso de menú
 * `menu.fiscal-padrones` y lo asigna a los roles admin. El gate funcional reusa
 * `func.fiscal.configuracion` (ya creado en la Fase 7) — no se agrega permiso nuevo.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b).
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
            // El menú Fiscal (migración 2026_06_17_120000) debe existir primero.
            return;
        }

        if ($conn->table('menu_items')->where('slug', 'fiscal-padrones')->exists()) {
            return;
        }

        $orden = (int) $conn->table('menu_items')->where('parent_id', $padre->id)->max('orden');

        // Eloquent para disparar MenuItemObserver (crea permiso menu.fiscal-padrones).
        MenuItem::create([
            'parent_id' => $padre->id,
            'nombre' => 'Importar padrón',
            'slug' => 'fiscal-padrones',
            'icono' => 'heroicon-o-arrow-up-tray',
            'route_type' => 'route',
            'route_value' => 'fiscal.padrones',
            'orden' => $orden + 1,
            'activo' => true,
        ]);
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        if (! $conn->getSchemaBuilder()->hasTable('menu_items')) {
            return;
        }

        // Eloquent para disparar MenuItemObserver (borra el permiso de menú).
        MenuItem::where('slug', 'fiscal-padrones')->get()->each->delete();

        $conn->table('permissions')->where('name', 'menu.fiscal-padrones')->delete();
    }
};
