<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: configuración de delivery por sucursal (RF-05).
 *
 * - `usa_delivery`: habilita el módulo en la sucursal.
 * - `config_delivery` (JSON, DEFAULTS mergeados en el modelo, patrón
 *   config_llamador): georref, radio/costos, aceptación de externos,
 *   calendario, modo de promesa, programados (keys creadas desde el día 1;
 *   franjas/programados se implementan en Fase 8 — D22).
 * - `pedido_delivery_ultimo_numero`: numeración PROPIA de delivery por
 *   sucursal. `numero_display` COMPARTE el contador existente
 *   `pedido_display_ultimo_numero` (llamador sin colisiones con mostrador).
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-05, D5/D14/D16/D22).
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
                    ADD COLUMN `usa_delivery` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si la sucursal usa el modulo de pedidos delivery/take-away' AFTER `usa_consultor_precios`,
                    ADD COLUMN `config_delivery` json DEFAULT NULL COMMENT 'Config de delivery (RF-05): DEFAULTS mergeados en el modelo' AFTER `usa_delivery`,
                    ADD COLUMN `pedido_delivery_ultimo_numero` int unsigned NOT NULL DEFAULT 0 COMMENT 'Contador correlativo de pedidos delivery (reset manual con permiso)' AFTER `pedido_mostrador_ultimo_numero`
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
                    DROP COLUMN `usa_delivery`,
                    DROP COLUMN `config_delivery`,
                    DROP COLUMN `pedido_delivery_ultimo_numero`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
