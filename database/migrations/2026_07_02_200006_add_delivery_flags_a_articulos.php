<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: disponibilidad por canal (RF-16) + presentación
 * en tienda a nivel artículo (RF-17, D21).
 *
 * - `disponible_delivery` / `disponible_take_away`: el catálogo API/tienda
 *   filtra por tipo de pedido; el panel advierte (no bloquea).
 * - `permite_programado`: validación de pedidos programados (lógica Fase 8).
 * - `orden` / `destacado`: presentación del catálogo público.
 * - `permite_venta_sin_stock`: agotado ⇒ visible pero NO pedible en la tienda,
 *   salvo este flag.
 *
 * Import/export de artículos: estas columnas van AL FINAL (columnas por letra
 * fija — insertarlas en el medio rompería planillas viejas).
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-16/RF-17, D16/D21).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos`
                    ADD COLUMN `disponible_delivery` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Disponible para pedidos delivery (RF-16)' AFTER `puntos_canje`,
                    ADD COLUMN `disponible_take_away` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Disponible para pedidos take-away (RF-16)' AFTER `disponible_delivery`,
                    ADD COLUMN `permite_programado` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Puede incluirse en pedidos programados (RF-15/Fase 8)' AFTER `disponible_take_away`,
                    ADD COLUMN `orden` int(11) NOT NULL DEFAULT 0 COMMENT 'Orden de presentacion en catalogo tienda (RF-17)' AFTER `permite_programado`,
                    ADD COLUMN `destacado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Destacado en catalogo tienda (RF-17)' AFTER `orden`,
                    ADD COLUMN `permite_venta_sin_stock` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'La tienda permite pedirlo agotado (RF-17)' AFTER `destacado`
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
                    ALTER TABLE `{$prefix}articulos`
                    DROP COLUMN `disponible_delivery`,
                    DROP COLUMN `disponible_take_away`,
                    DROP COLUMN `permite_programado`,
                    DROP COLUMN `orden`,
                    DROP COLUMN `destacado`,
                    DROP COLUMN `permite_venta_sin_stock`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
