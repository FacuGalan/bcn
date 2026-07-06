<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — promesa "Lo antes posible" (RF-15, modo franjas).
 *
 * Cuando el comercio acepta "Lo antes posible", el pedido queda SIN hora
 * pactada (hora_pactada_at NULL) pero debe distinguirse de un pedido sin
 * promesa: este flag lo marca explícitamente para panel/kanban/tienda.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    ADD COLUMN `lo_antes_posible` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Promesa ASAP del modo franjas (hora_pactada_at queda NULL)' AFTER `hora_pactada_at`
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
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    DROP COLUMN `lo_antes_posible`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
