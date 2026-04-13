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

            // stock: cantidad_minima y cantidad_maxima a decimal(12,3)
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}stock`
                    MODIFY COLUMN `cantidad_minima` decimal(12,3) DEFAULT NULL,
                    MODIFY COLUMN `cantidad_maxima` decimal(12,3) DEFAULT NULL
                ");
            } catch (\Exception $e) {
                // skip
            }

            // movimientos_stock: entrada, salida, stock_resultante a decimal(12,3)
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_stock`
                    MODIFY COLUMN `entrada` decimal(12,3) NOT NULL DEFAULT 0.000,
                    MODIFY COLUMN `salida` decimal(12,3) NOT NULL DEFAULT 0.000,
                    MODIFY COLUMN `stock_resultante` decimal(12,3) NOT NULL DEFAULT 0.000
                ");
            } catch (\Exception $e) {
                // skip
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}stock`
                    MODIFY COLUMN `cantidad_minima` decimal(10,2) DEFAULT NULL,
                    MODIFY COLUMN `cantidad_maxima` decimal(10,2) DEFAULT NULL
                ");
            } catch (\Exception $e) {
                // skip
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_stock`
                    MODIFY COLUMN `entrada` decimal(10,2) NOT NULL DEFAULT 0.00,
                    MODIFY COLUMN `salida` decimal(10,2) NOT NULL DEFAULT 0.00,
                    MODIFY COLUMN `stock_resultante` decimal(10,2) NOT NULL DEFAULT 0.00
                ");
            } catch (\Exception $e) {
                // skip
            }
        }
    }
};
