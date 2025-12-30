<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Agregar condicion_iva a clientes
 *
 * Agrega el campo condicion_iva para manejo fiscal de clientes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prefix = DB::connection('pymes_tenant')->getTablePrefix();
        $tableName = $prefix . 'clientes';

        // Verificar si la columna existe
        $columns = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva'");

        if (empty($columns)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` ADD COLUMN `condicion_iva` VARCHAR(50) NOT NULL DEFAULT 'consumidor_final' COMMENT 'Condición IVA: consumidor_final, monotributista, responsable_inscripto, exento'"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = DB::connection('pymes_tenant')->getTablePrefix();
        $tableName = $prefix . 'clientes';

        $columns = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva'");

        if (!empty($columns)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` DROP COLUMN `condicion_iva`"
            );
        }
    }
};
