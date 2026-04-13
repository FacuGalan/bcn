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
                    ALTER TABLE `{$prefix}promociones_especiales`
                    ADD COLUMN `formas_pago_ids` TEXT DEFAULT NULL AFTER `forma_pago_id`,
                    ADD COLUMN `nxm_articulos_ids` TEXT DEFAULT NULL AFTER `nxm_articulo_id`,
                    ADD COLUMN `nxm_categorias_ids` TEXT DEFAULT NULL AFTER `nxm_categoria_id`
                ");
            } catch (\Exception $e) {
                continue;
            }

            // Migrar datos existentes de columnas singulares a arrays
            try {
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}promociones_especiales`
                    SET `formas_pago_ids` = CONCAT('[', `forma_pago_id`, ']')
                    WHERE `forma_pago_id` IS NOT NULL AND `formas_pago_ids` IS NULL
                ");
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}promociones_especiales`
                    SET `nxm_articulos_ids` = CONCAT('[', `nxm_articulo_id`, ']')
                    WHERE `nxm_articulo_id` IS NOT NULL AND `nxm_articulos_ids` IS NULL
                ");
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}promociones_especiales`
                    SET `nxm_categorias_ids` = CONCAT('[', `nxm_categoria_id`, ']')
                    WHERE `nxm_categoria_id` IS NOT NULL AND `nxm_categorias_ids` IS NULL
                ");
            } catch (\Exception $e) {
                // skip data migration errors
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
                    ALTER TABLE `{$prefix}promociones_especiales`
                    DROP COLUMN `formas_pago_ids`,
                    DROP COLUMN `nxm_articulos_ids`,
                    DROP COLUMN `nxm_categorias_ids`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
