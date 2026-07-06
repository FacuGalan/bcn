<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alertas de pedidos demorados (delivery + mostrador, config COMPARTIDA).
 *
 * Dos umbrales en minutos por sucursal (0 = alerta deshabilitada):
 *  - Pedidos SIN promesa (ASAP / manual sin hora / mostrador): la card pasa a
 *    amarillo/rojo cuando la edad desde la confirmación supera el umbral.
 *  - Pedidos CON promesa (hora_pactada_at): amarillo `alerta_amarilla` minutos
 *    ANTES de vencer; rojo al vencer.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `pedido_alerta_amarilla_min` int unsigned NOT NULL DEFAULT 15 COMMENT 'Minutos para alerta amarilla de pedido demorado (0 = off, compartida delivery/mostrador)' AFTER `pedido_conversion_automatica_al_entregar`,
                    ADD COLUMN `pedido_alerta_roja_min` int unsigned NOT NULL DEFAULT 30 COMMENT 'Minutos para alerta roja de pedido demorado (0 = off, compartida delivery/mostrador)' AFTER `pedido_alerta_amarilla_min`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    DROP COLUMN `pedido_alerta_amarilla_min`,
                    DROP COLUMN `pedido_alerta_roja_min`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
