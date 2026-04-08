<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega menu_items "Programa de Puntos" y "Cupones" bajo Ventas,
 * y permisos funcionales para ajuste manual de puntos y descuento general.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        // ── Menu Items ──

        $ventasParent = $conn->table('menu_items')
            ->where('slug', 'ventas')
            ->whereNull('parent_id')
            ->first();

        if (! $ventasParent) {
            // En entorno de test las tablas de menú pueden no estar seeded
            return;
        }

        $maxOrden = (int) $conn->table('menu_items')
            ->where('parent_id', $ventasParent->id)
            ->max('orden');

        // Programa de Puntos
        if (! $conn->table('menu_items')->where('slug', 'programa-puntos')->exists()) {
            $conn->table('menu_items')->insert([
                'parent_id' => $ventasParent->id,
                'nombre' => 'Programa de Puntos',
                'slug' => 'programa-puntos',
                'icono' => 'heroicon-o-star',
                'route_type' => 'route',
                'route_value' => 'ventas.puntos',
                'orden' => $maxOrden + 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Cupones
        if (! $conn->table('menu_items')->where('slug', 'cupones')->exists()) {
            $conn->table('menu_items')->insert([
                'parent_id' => $ventasParent->id,
                'nombre' => 'Cupones',
                'slug' => 'cupones',
                'icono' => 'heroicon-o-ticket',
                'route_type' => 'route',
                'route_value' => 'ventas.cupones',
                'orden' => $maxOrden + 2,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Permisos Funcionales (tabla compartida pymes) ──

        // Ajuste manual de puntos
        if (! $conn->table('permisos_funcionales')->where('codigo', 'puntos_ajuste_manual')->exists()) {
            $conn->table('permisos_funcionales')->insert([
                'codigo' => 'puntos_ajuste_manual',
                'etiqueta' => 'Ajuste manual de puntos',
                'descripcion' => 'Permite sumar o restar puntos manualmente a un cliente',
                'grupo' => 'Puntos',
                'orden' => 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Descuento general en ventas
        if (! $conn->table('permisos_funcionales')->where('codigo', 'descuento_general')->exists()) {
            $conn->table('permisos_funcionales')->insert([
                'codigo' => 'descuento_general',
                'etiqueta' => 'Aplicar descuento general',
                'descripcion' => 'Permite aplicar descuento porcentual o fijo general a toda la venta',
                'grupo' => 'Ventas',
                'orden' => 7,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sincronizar permisos funcionales con Spatie
        \App\Models\PermisoFuncional::syncAllToSpatie();
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        $conn->table('menu_items')->whereIn('slug', ['programa-puntos', 'cupones'])->delete();
        $conn->table('permisos_funcionales')->whereIn('codigo', ['puntos_ajuste_manual', 'descuento_general'])->delete();
        $conn->table('permissions')->whereIn('name', ['func.puntos_ajuste_manual', 'func.descuento_general'])->delete();
    }
};
