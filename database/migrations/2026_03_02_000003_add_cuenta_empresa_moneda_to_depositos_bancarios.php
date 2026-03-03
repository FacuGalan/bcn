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
                    ALTER TABLE `{$prefix}depositos_bancarios`
                    ADD COLUMN `cuenta_empresa_id` bigint unsigned DEFAULT NULL AFTER `cuenta_bancaria_id`,
                    ADD COLUMN `moneda_id` bigint unsigned DEFAULT NULL AFTER `monto`
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
                    ALTER TABLE `{$prefix}depositos_bancarios`
                    DROP COLUMN `cuenta_empresa_id`,
                    DROP COLUMN `moneda_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
