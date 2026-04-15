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
                    ALTER TABLE `{$prefix}listas_precios`
                    ADD COLUMN `estatica` TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Si es true, los precios quedan congelados y no cambian aunque varíe el precio base del artículo'
                        AFTER `es_lista_base`,
                    ADD COLUMN `precios_congelados_at` TIMESTAMP NULL DEFAULT NULL
                        COMMENT 'Timestamp del último snapshot de precios para listas estáticas'
                        AFTER `activo`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}lista_precio_articulos`
                    ADD COLUMN `origen` ENUM('manual','snapshot') NOT NULL DEFAULT 'manual'
                        COMMENT 'manual: agregado en el wizard por el usuario. snapshot: auto-generado al congelar una lista estática'
                        AFTER `precio_base_original`
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
                    ALTER TABLE `{$prefix}lista_precio_articulos`
                    DROP COLUMN `origen`
                ");
            } catch (\Exception $e) {
                // continue
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}listas_precios`
                    DROP COLUMN `estatica`,
                    DROP COLUMN `precios_congelados_at`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
