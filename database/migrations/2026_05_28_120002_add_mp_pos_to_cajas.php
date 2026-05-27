<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3.5 (Integraciones de Pago): IDs y URLs de POS de Mercado Pago,
 * agregadas a la tabla `cajas` tenant.
 *
 * - mp_pos_id: ID numérico devuelto por MP al crear el POS.
 * - mp_pos_external_id: external_id alfanumérico (formato BCN-{c}-{caja_id}).
 * - mp_pos_qr_url: URL del PNG del QR estático (campo `qr.image` de MP).
 * - mp_pos_qr_pdf_url: URL del PDF imprimible (campo `qr.template_document`).
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
                    ALTER TABLE `{$prefix}cajas`
                    ADD COLUMN `mp_pos_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD COLUMN `mp_pos_external_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD COLUMN `mp_pos_qr_url` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD COLUMN `mp_pos_qr_pdf_url` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    ADD UNIQUE INDEX `uniq_cajas_mp_pos_external_id` (`mp_pos_external_id`),
                    ADD INDEX `idx_cajas_mp_pos_id` (`mp_pos_id`)
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
                    ALTER TABLE `{$prefix}cajas`
                    DROP INDEX `idx_cajas_mp_pos_id`,
                    DROP INDEX `uniq_cajas_mp_pos_external_id`,
                    DROP COLUMN `mp_pos_qr_pdf_url`,
                    DROP COLUMN `mp_pos_qr_url`,
                    DROP COLUMN `mp_pos_external_id`,
                    DROP COLUMN `mp_pos_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
