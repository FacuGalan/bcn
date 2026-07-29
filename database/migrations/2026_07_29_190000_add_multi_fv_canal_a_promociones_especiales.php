<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T28 (ronda crossmap 2): forma de venta y canal de venta MÚLTIPLES en
 * promociones especiales. Mismo patrón que formas_pago_ids (migración
 * 2026_04_13_200000): columna plural TEXT (JSON) junto al singular, backfill
 * serializando el singular, y el singular SIGUE VIVO (doble escritura desde
 * el wizard: singular = primer elemento; lectura con fallback `??`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            foreach ([
                "ALTER TABLE `{$prefix}promociones_especiales` ADD COLUMN `formas_venta_ids` TEXT DEFAULT NULL AFTER `forma_venta_id`",
                "ALTER TABLE `{$prefix}promociones_especiales` ADD COLUMN `canales_venta_ids` TEXT DEFAULT NULL AFTER `canal_venta_id`",
                // Backfill: el singular existente pasa a lista de un elemento.
                "UPDATE `{$prefix}promociones_especiales` SET `formas_venta_ids` = CONCAT('[', `forma_venta_id`, ']') WHERE `forma_venta_id` IS NOT NULL AND `formas_venta_ids` IS NULL",
                "UPDATE `{$prefix}promociones_especiales` SET `canales_venta_ids` = CONCAT('[', `canal_venta_id`, ']') WHERE `canal_venta_id` IS NOT NULL AND `canales_venta_ids` IS NULL",
            ] as $sql) {
                try {
                    DB::connection('pymes')->statement($sql);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            foreach ([
                "ALTER TABLE `{$prefix}promociones_especiales` DROP COLUMN `formas_venta_ids`",
                "ALTER TABLE `{$prefix}promociones_especiales` DROP COLUMN `canales_venta_ids`",
            ] as $sql) {
                try {
                    DB::connection('pymes')->statement($sql);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
};
