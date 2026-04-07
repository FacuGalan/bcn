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

            try {
                $columnExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}ventas_detalle'
                    AND COLUMN_NAME = 'descuento_cupon'
                ");

                if (empty($columnExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}ventas_detalle`
                        ADD COLUMN `descuento_cupon` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento aplicado por cupón en este item' AFTER `descuento_promocion`
                    ");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $columnExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}ventas_detalle'
                    AND COLUMN_NAME = 'descuento_cupon'
                ");

                if (! empty($columnExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}ventas_detalle` DROP COLUMN `descuento_cupon`
                    ");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
