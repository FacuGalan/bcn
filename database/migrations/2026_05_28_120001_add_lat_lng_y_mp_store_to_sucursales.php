<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3.5 (Integraciones de Pago): coordenadas geográficas + IDs de
 * Store en Mercado Pago, agregadas a la tabla `sucursales` tenant.
 *
 * - latitud/longitud: obligatorias en la API de stores MP. Se cargan
 *   manualmente al sincronizar (Google Maps en una fase futura). También
 *   serán útiles para la tienda online futura.
 * - mp_store_id: ID numérico devuelto por MP al crear la store. Usado para
 *   actualizar/eliminar.
 * - mp_store_external_id: external_id alfanumérico (formato BCN-{c}-{s})
 *   que se manda como external_id y external_store_id al crear POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `latitud` decimal(10,7) DEFAULT NULL AFTER `direccion`,
                    ADD COLUMN `longitud` decimal(10,7) DEFAULT NULL AFTER `latitud`,
                    ADD COLUMN `mp_store_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD COLUMN `mp_store_external_id` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD UNIQUE INDEX `uniq_sucursales_mp_store_external_id` (`mp_store_external_id`),
                    ADD INDEX `idx_sucursales_mp_store_id` (`mp_store_id`)
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
                    ALTER TABLE `{$prefix}sucursales`
                    DROP INDEX `idx_sucursales_mp_store_id`,
                    DROP INDEX `uniq_sucursales_mp_store_external_id`,
                    DROP COLUMN `mp_store_external_id`,
                    DROP COLUMN `mp_store_id`,
                    DROP COLUMN `longitud`,
                    DROP COLUMN `latitud`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
