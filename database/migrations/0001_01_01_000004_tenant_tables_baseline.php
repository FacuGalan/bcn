<?php

use Illuminate\Database\Migrations\Migration;

/**
 * ═══════════════════════════════════════════════════════════════════
 * BASELINE: Tablas Tenant (Multi-Comercio)
 * ═══════════════════════════════════════════════════════════════════
 *
 * Las tablas tenant NO se crean con migraciones Laravel.
 * Se crean mediante el comando: php artisan comercio:provision
 *
 * El template SQL se encuentra en: database/sql/tenant_tables.sql
 * Contiene 85 tablas + 2 vistas con placeholder {{PREFIX}}.
 *
 * Esta migración es un MARCADOR que indica que todas las tablas
 * tenant hasta esta fecha ya existen.
 *
 * ═══════════════════════════════════════════════════════════════════
 * WORKFLOW PARA CAMBIOS FUTUROS EN TABLAS TENANT
 * ═══════════════════════════════════════════════════════════════════
 *
 * Cuando necesites agregar/modificar columnas o tablas tenant:
 *
 * 1. CREAR MIGRACIÓN que itere sobre todos los comercios:
 *
 *    $comercios = DB::connection('config')->table('comercios')->get();
 *    foreach ($comercios as $comercio) {
 *        $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';
 *        DB::connection('pymes')->statement("ALTER TABLE `{$prefix}tabla` ...");
 *    }
 *
 * 2. EJECUTAR: php artisan migrate
 *
 * 3. REGENERAR tenant_tables.sql desde comercio 1:
 *    php artisan tenant:dump-sql (o script manual)
 *    Esto asegura que los nuevos comercios ya tengan la estructura actualizada.
 *
 * ═══════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        // Marcador — las tablas tenant se crean con comercio:provision
    }

    public function down(): void
    {
        // Las tablas tenant se eliminan manualmente por comercio
    }
};
