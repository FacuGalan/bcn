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

            // venta_pagos
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    ADD COLUMN `monto_moneda_original` decimal(14,2) DEFAULT NULL AFTER `moneda_id`,
                    ADD COLUMN `tipo_cambio_tasa` decimal(14,6) DEFAULT NULL AFTER `monto_moneda_original`
                ");
            } catch (\Exception $e) {
                continue;
            }

            // cobro_pagos
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}cobro_pagos`
                    ADD COLUMN `monto_moneda_original` decimal(14,2) DEFAULT NULL AFTER `moneda_id`,
                    ADD COLUMN `tipo_cambio_tasa` decimal(14,6) DEFAULT NULL AFTER `monto_moneda_original`
                ");
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
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP COLUMN `monto_moneda_original`,
                    DROP COLUMN `tipo_cambio_tasa`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}cobro_pagos`
                    DROP COLUMN `monto_moneda_original`,
                    DROP COLUMN `tipo_cambio_tasa`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
