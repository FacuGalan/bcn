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
        // Actualizar el orden de Configuración a 5 para hacer espacio para Clientes
        DB::table('menu_items')
            ->where('slug', 'configuracion')
            ->whereNull('parent_id')
            ->update(['orden' => 5]);

        // Verificar si ya existe el menú Clientes
        $clientesExists = DB::table('menu_items')
            ->where('slug', 'clientes')
            ->whereNull('parent_id')
            ->exists();

        if (!$clientesExists) {
            // Crear el menú padre "Clientes"
            $clientesId = DB::table('menu_items')->insertGetId([
                'parent_id' => null,
                'nombre' => 'Clientes',
                'slug' => 'clientes',
                'icono' => 'heroicon-o-user-group',
                'route_type' => 'none',
                'route_value' => null,
                'orden' => 4,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear el hijo "Listado de Clientes"
            DB::table('menu_items')->insert([
                'parent_id' => $clientesId,
                'nombre' => 'Listado de Clientes',
                'slug' => 'listado-clientes',
                'icono' => 'heroicon-o-list-bullet',
                'route_type' => 'route',
                'route_value' => 'clientes.index',
                'orden' => 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Obtener el ID del menú Clientes
        $clientesId = DB::table('menu_items')
            ->where('slug', 'clientes')
            ->whereNull('parent_id')
            ->value('id');

        if ($clientesId) {
            // Eliminar los hijos primero
            DB::table('menu_items')
                ->where('parent_id', $clientesId)
                ->delete();

            // Eliminar el padre
            DB::table('menu_items')
                ->where('id', $clientesId)
                ->delete();
        }

        // Restaurar el orden de Configuración a 4
        DB::table('menu_items')
            ->where('slug', 'configuracion')
            ->whereNull('parent_id')
            ->update(['orden' => 4]);
    }
};
