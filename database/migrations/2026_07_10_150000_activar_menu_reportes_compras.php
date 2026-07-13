<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 8 (spec compras-costos-precios): activa el ítem de menú de Reportes
 * de Compras — la pantalla (RF-22) llega en esta fase. El ítem nació
 * INACTIVO en 2026_07_09_120100 (cada fase activa el suyo); con esto el
 * grupo Compras queda completo (Compras / Proveedores / Pagos / Reportes).
 *
 * Tabla compartida `menu_items` (conexión pymes, sin prefijo). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'reportes-compras')
            ->update(['activo' => true]);
    }

    public function down(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->where('slug', 'reportes-compras')
            ->update(['activo' => false]);
    }
};
