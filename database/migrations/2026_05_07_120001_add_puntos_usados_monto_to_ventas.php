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
            $tabla = "{$prefix}ventas";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'puntos_usados_monto'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `puntos_usados_monto` decimal(12,2) NOT NULL DEFAULT '0.00'
                        COMMENT 'Monto en pesos que representan los puntos canjeados como pago en esta venta.'
                        AFTER `puntos_usados`
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_puntos_usados_monto falló para comercio {$comercio->id}: ".$e->getMessage());

                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'puntos_usados_monto'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}` DROP COLUMN `puntos_usados_monto`
                    ");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
