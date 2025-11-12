<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Renombrar columnas de stock para coincidir con el modelo
 *
 * PROBLEMA: La tabla stock fue creada con columnas 'minimo' y 'maximo',
 * pero el modelo Stock espera 'cantidad_minima' y 'cantidad_maxima'.
 *
 * SOLUCIÓN: Renombrar las columnas y agregar 'ultima_actualizacion'
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection('pymes');

        // Obtener todas las tablas stock con prefijo (exactamente %_stock, no transferencias_stock)
        $tables = $db->select("SHOW TABLES LIKE '%_stock'");

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];

            // Solo procesar tablas que terminan exactamente en "_stock"
            if (!preg_match('/^\d{6}_stock$/', $tableName)) {
                continue;
            }

            // Verificar qué columnas existen
            $columns = $db->select("SHOW COLUMNS FROM `{$tableName}`");
            $columnNames = array_column($columns, 'Field');

            // Renombrar columna 'minimo' si existe
            if (in_array('minimo', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` CHANGE `minimo` `cantidad_minima` DECIMAL(10,2) NULL");
            }

            // Renombrar columna 'maximo' si existe
            if (in_array('maximo', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` CHANGE `maximo` `cantidad_maxima` DECIMAL(10,2) NULL");
            }

            // Agregar columna ultima_actualizacion si no existe
            if (!in_array('ultima_actualizacion', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` ADD COLUMN `ultima_actualizacion` TIMESTAMP NULL AFTER `cantidad_maxima`");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $db = DB::connection('pymes');

        // Obtener todas las tablas stock con prefijo
        $tables = $db->select("SHOW TABLES LIKE '%_stock'");

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];

            // Solo procesar tablas que terminan exactamente en "_stock"
            if (!preg_match('/^\d{6}_stock$/', $tableName)) {
                continue;
            }

            // Revertir cambios
            $db->statement("ALTER TABLE `{$tableName}` CHANGE `cantidad_minima` `minimo` DECIMAL(12,3) NOT NULL DEFAULT 0.000");
            $db->statement("ALTER TABLE `{$tableName}` CHANGE `cantidad_maxima` `maximo` DECIMAL(12,3) NULL");
            $db->statement("ALTER TABLE `{$tableName}` DROP COLUMN IF EXISTS `ultima_actualizacion`");
        }
    }
};
