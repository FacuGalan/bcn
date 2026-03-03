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
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    ADD COLUMN `precio_base` decimal(12,2) DEFAULT NULL
                    AFTER `vendible`
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
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    DROP COLUMN `precio_base`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
