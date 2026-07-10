<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 (spec compras-costos-precios): activa el ítem de menú del listado
 * de compras — la pantalla reescrita (listado + editor fullscreen) llega en
 * esta fase. El ítem nació INACTIVO en 2026_07_09_120100 (cada fase activa
 * el suyo); el padre `compras` y los hermanos ya están activos desde Fase 5.
 *
 * Tabla compartida `menu_items` (conexión pymes, sin prefijo). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'listado-compras')
            ->update(['activo' => true]);
    }

    public function down(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'listado-compras')
            ->update(['activo' => false]);
    }
};
