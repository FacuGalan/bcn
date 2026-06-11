<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Modo Point (Fase 1): terminal Point asignada a la caja.
 *
 * - mp_point_terminal_id: identificador de la terminal (device) Point de MP,
 *   formato `{tipo}__{serial}`. Se obtiene de `GET /terminals/v1/list` y se
 *   asocia 1:1 a la caja física. Point NO usa stores/POS (eso es exclusivo del
 *   producto QR); usa vinculación de devices.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago-point.md (Fase 1, RF-03).
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
                    ADD COLUMN `mp_point_terminal_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `mp_pos_qr_pdf_url`,
                    ADD INDEX `idx_cajas_mp_point_terminal_id` (`mp_point_terminal_id`)
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
                    DROP INDEX `idx_cajas_mp_point_terminal_id`,
                    DROP COLUMN `mp_point_terminal_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
