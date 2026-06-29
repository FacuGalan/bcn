<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Multi-PWA Clase B — Fase 3b: numeración de display (turno) para pedidos.
 *
 * Separa el número visible (monitor/comanda/kanban) del correlativo permanente:
 *  - `pedidos_mostrador.numero_display`: número amigable reseteable (nullable;
 *    null = la sucursal no usa numeración de display → cae al `numero` permanente).
 *  - `sucursales`: toggle de monitor + config de numeración (modo diario con hora
 *    de corte / manual) + contador y jornada del display.
 *
 * Itera todos los comercios con SQL raw + prefijo + try/catch por comercio.
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-03b).
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
                    ADD COLUMN `usa_llamador` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si usa el monitor llamador de pedidos' AFTER `usa_beepers`,
                    ADD COLUMN `usa_numeracion_display` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si usa numeracion de display aparte del correlativo permanente' AFTER `usa_llamador`,
                    ADD COLUMN `numeracion_display_modo` enum('diario','manual') NOT NULL DEFAULT 'diario' AFTER `usa_numeracion_display`,
                    ADD COLUMN `numeracion_display_horas` json DEFAULT NULL COMMENT 'Horas de reset diario (lista 0-23), ej [6,18]; null => [6]' AFTER `numeracion_display_modo`,
                    ADD COLUMN `pedido_display_ultimo_numero` int unsigned NOT NULL DEFAULT 0 AFTER `numeracion_display_horas`,
                    ADD COLUMN `pedido_display_segmento_at` datetime DEFAULT NULL COMMENT 'Inicio del segmento (turno) del contador display actual' AFTER `pedido_display_ultimo_numero`
                ");
            } catch (\Exception $e) {
                // columnas ya presentes → seguir
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    ADD COLUMN `numero_display` int unsigned DEFAULT NULL COMMENT 'Numero amigable mostrado (monitor/comanda/kanban)' AFTER `numero`
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
                    DROP COLUMN `usa_llamador`,
                    DROP COLUMN `usa_numeracion_display`,
                    DROP COLUMN `numeracion_display_modo`,
                    DROP COLUMN `numeracion_display_horas`,
                    DROP COLUMN `pedido_display_ultimo_numero`,
                    DROP COLUMN `pedido_display_segmento_at`
                ");
            } catch (\Exception $e) {
                // ignorar
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    DROP COLUMN `numero_display`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
