<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas_detalle";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'ajuste_manual_origen'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `ajuste_manual_origen` enum('manual','descuento_general') DEFAULT NULL
                        COMMENT 'Origen del ajuste manual: manual (usuario) o descuento_general (distribuido)'
                        AFTER `ajuste_manual_valor`
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_ajuste_manual_origen falló para comercio {$comercio->id}: ".$e->getMessage());

                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas_detalle";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'ajuste_manual_origen'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}` DROP COLUMN `ajuste_manual_origen`
                    ");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
