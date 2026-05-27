<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 (Integraciones de Pago): menu_item bajo Configuración.
 *
 * El permiso `menu.integraciones-pago` se crea automáticamente vía
 * MenuItemObserver al insertar el item con el modelo Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        $configParent = $conn->table('menu_items')
            ->where('slug', 'configuracion')
            ->whereNull('parent_id')
            ->first();

        if (! $configParent) {
            // En entorno de test las tablas de menú pueden no estar seeded.
            return;
        }

        if ($conn->table('menu_items')->where('slug', 'integraciones-pago')->exists()) {
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $configParent->id)
            ->max('orden');

        // Eloquent para disparar MenuItemObserver (crea permiso menu.integraciones-pago).
        MenuItem::create([
            'parent_id' => $configParent->id,
            'nombre' => 'Integraciones de Pago',
            'slug' => 'integraciones-pago',
            'icono' => 'heroicon-o-credit-card',
            'route_type' => 'route',
            'route_value' => 'configuracion.integraciones-pago',
            'orden' => $maxOrden + 1,
            'activo' => true,
        ]);
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        // Eloquent para disparar MenuItemObserver (borra el permiso).
        MenuItem::where('slug', 'integraciones-pago')->get()->each->delete();

        $conn->table('permissions')->where('name', 'menu.integraciones-pago')->delete();
    }
};
