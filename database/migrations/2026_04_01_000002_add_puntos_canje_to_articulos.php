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
                    ALTER TABLE `{$prefix}articulos`
                    ADD COLUMN `puntos_canje` int unsigned DEFAULT NULL COMMENT 'Puntos necesarios para canjear (NULL = no canjeable)'
                    AFTER `precio_base`
                ");
            } catch (\Exception $e) {
                // Column may already exist
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    ADD COLUMN `puntos_canje` int unsigned DEFAULT NULL COMMENT 'Override de puntos_canje por sucursal'
                    AFTER `precio_base`
                ");
            } catch (\Exception $e) {
                // Column may already exist
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
                    ALTER TABLE `{$prefix}articulos`
                    DROP COLUMN `puntos_canje`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    DROP COLUMN `puntos_canje`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
