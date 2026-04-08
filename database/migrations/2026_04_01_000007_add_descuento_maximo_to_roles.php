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
                    ALTER TABLE `{$prefix}roles`
                    ADD COLUMN `descuento_maximo_porcentaje` decimal(5,2) DEFAULT NULL COMMENT 'Tope de descuento % permitido para el rol (NULL = sin tope)'
                    AFTER `guard_name`
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
                    ALTER TABLE `{$prefix}roles`
                    DROP COLUMN `descuento_maximo_porcentaje`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
