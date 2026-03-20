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
                    ALTER TABLE `{$prefix}categorias`
                    ADD COLUMN `prefijo` varchar(10) DEFAULT NULL COMMENT 'Prefijo para código automático de artículos'
                    AFTER `codigo`
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
                    ALTER TABLE `{$prefix}categorias`
                    DROP COLUMN `prefijo`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
