<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Agregar columnas faltantes a sucursales
 *
 * PROBLEMA: La tabla sucursales fue creada sin las columnas:
 * - es_principal
 * - datos_fiscales_id
 * - configuracion
 *
 * SOLUCIÓN: Agregar estas columnas a todas las tablas sucursales con prefijo
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection('pymes');

        // Obtener todas las tablas sucursales con prefijo
        $tables = $db->select("SHOW TABLES LIKE '%_sucursales'");

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];

            // Solo procesar tablas que terminan exactamente en "_sucursales"
            if (!preg_match('/^\d{6}_sucursales$/', $tableName)) {
                continue;
            }

            // Verificar qué columnas existen
            $columns = $db->select("SHOW COLUMNS FROM `{$tableName}`");
            $columnNames = array_column($columns, 'Field');

            // Agregar es_principal si no existe
            if (!in_array('es_principal', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` ADD COLUMN `es_principal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Si es la sucursal principal/central' AFTER `email`");
            }

            // Agregar datos_fiscales_id si no existe
            if (!in_array('datos_fiscales_id', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` ADD COLUMN `datos_fiscales_id` BIGINT(20) UNSIGNED NULL COMMENT 'Si factura con datos propios' AFTER `es_principal`");
            }

            // Agregar configuracion si no existe
            if (!in_array('configuracion', $columnNames)) {
                $db->statement("ALTER TABLE `{$tableName}` ADD COLUMN `configuracion` TEXT NULL COMMENT 'Configuraciones específicas (JSON)' AFTER `datos_fiscales_id`");
            }

            // Marcar la primera sucursal como principal si no hay ninguna principal
            $principal = $db->selectOne("SELECT COUNT(*) as count FROM `{$tableName}` WHERE `es_principal` = 1");
            if ($principal->count == 0) {
                $db->statement("UPDATE `{$tableName}` SET `es_principal` = 1 WHERE `id` = 1");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $db = DB::connection('pymes');

        // Obtener todas las tablas sucursales con prefijo
        $tables = $db->select("SHOW TABLES LIKE '%_sucursales'");

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];

            // Solo procesar tablas que terminan exactamente en "_sucursales"
            if (!preg_match('/^\d{6}_sucursales$/', $tableName)) {
                continue;
            }

            // Eliminar columnas agregadas
            $db->statement("ALTER TABLE `{$tableName}` DROP COLUMN IF EXISTS `es_principal`");
            $db->statement("ALTER TABLE `{$tableName}` DROP COLUMN IF EXISTS `datos_fiscales_id`");
            $db->statement("ALTER TABLE `{$tableName}` DROP COLUMN IF EXISTS `configuracion`");
        }
    }
};
