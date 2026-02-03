<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener el ID del menú padre "Configuración"
        $configuracionId = DB::table('menu_items')
            ->where('slug', 'configuracion')
            ->whereNull('parent_id')
            ->value('id');

        if ($configuracionId) {
            // Verificar que no exista ya el item
            $exists = DB::table('menu_items')
                ->where('slug', 'formas-pago')
                ->where('parent_id', $configuracionId)
                ->exists();

            if (!$exists) {
                DB::table('menu_items')->insert([
                    'parent_id' => $configuracionId,
                    'nombre' => 'Formas de Pago',
                    'slug' => 'formas-pago',
                    'icono' => 'heroicon-o-credit-card',
                    'route_type' => 'route',
                    'route_value' => 'configuracion.formas-pago',
                    'orden' => 6,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('slug', 'formas-pago')
            ->where('route_value', 'configuracion.formas-pago')
            ->delete();
    }
};
