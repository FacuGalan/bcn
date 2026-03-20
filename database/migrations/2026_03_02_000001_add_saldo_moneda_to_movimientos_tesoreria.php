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
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_tesoreria`
                    ADD COLUMN `saldo_anterior_moneda` decimal(14,2) DEFAULT NULL AFTER `monto_moneda_original`,
                    ADD COLUMN `saldo_posterior_moneda` decimal(14,2) DEFAULT NULL AFTER `saldo_anterior_moneda`
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
                    ALTER TABLE `{$prefix}movimientos_tesoreria`
                    DROP COLUMN `saldo_anterior_moneda`,
                    DROP COLUMN `saldo_posterior_moneda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
