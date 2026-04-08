<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        $configMenu = $conn->table('menu_items')->where('slug', 'configuracion')->first();
        if (! $configMenu) {
            return;
        }

        $puntosMenu = $conn->table('menu_items')->where('slug', 'programa-puntos')->first();
        if (! $puntosMenu) {
            return;
        }

        // Obtener el máximo orden dentro de Configuración
        $maxOrden = $conn->table('menu_items')
            ->where('parent_id', $configMenu->id)
            ->max('orden') ?? 0;

        // Mover al menú Configuración
        $conn->table('menu_items')
            ->where('id', $puntosMenu->id)
            ->update([
                'parent_id' => $configMenu->id,
                'orden' => $maxOrden + 1,
                'nombre' => 'Puntos de Fidelización',
                'route_value' => 'configuracion.puntos',
            ]);
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        $ventasMenu = $conn->table('menu_items')->where('slug', 'ventas')->first();
        if (! $ventasMenu) {
            return;
        }

        $puntosMenu = $conn->table('menu_items')->where('slug', 'programa-puntos')->first();
        if (! $puntosMenu) {
            return;
        }

        $maxOrden = $conn->table('menu_items')
            ->where('parent_id', $ventasMenu->id)
            ->max('orden') ?? 0;

        $conn->table('menu_items')
            ->where('id', $puntosMenu->id)
            ->update([
                'parent_id' => $ventasMenu->id,
                'orden' => $maxOrden + 1,
                'nombre' => 'Programa de Puntos',
                'route_value' => 'ventas.puntos',
            ]);
    }
};
