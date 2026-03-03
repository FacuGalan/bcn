<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_caja`
                    ADD COLUMN `moneda_id` bigint(20) unsigned DEFAULT NULL AFTER `cierre_turno_id`,
                    ADD COLUMN `tipo_cambio_id` bigint(20) unsigned DEFAULT NULL AFTER `moneda_id`,
                    ADD COLUMN `monto_moneda_original` decimal(14,2) DEFAULT NULL AFTER `tipo_cambio_id`
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
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_caja`
                    DROP COLUMN `monto_moneda_original`,
                    DROP COLUMN `tipo_cambio_id`,
                    DROP COLUMN `moneda_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
