<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Backup de tabla precios antigua
 *
 * Renombra la tabla 'precios' existente a 'precios_old' para backup.
 * Esto permite migrar al nuevo sistema sin perder datos históricos.
 *
 * IMPORTANTE: Esta migración solo se ejecuta si existe la tabla 'precios'
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = 'pymes_tenant';

        // Solo renombrar si existe la tabla precios
        if (Schema::connection($connection)->hasTable('precios')) {
            // Renombrar usando SQL raw porque Schema no tiene rename directo
            $prefix = DB::connection($connection)->getTablePrefix();
            DB::connection($connection)->statement(
                "RENAME TABLE `{$prefix}precios` TO `{$prefix}precios_old`"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'pymes_tenant';

        // Restaurar nombre original si existe precios_old
        if (Schema::connection($connection)->hasTable('precios_old')) {
            $prefix = DB::connection($connection)->getTablePrefix();
            DB::connection($connection)->statement(
                "RENAME TABLE `{$prefix}precios_old` TO `{$prefix}precios`"
            );
        }
    }
};
