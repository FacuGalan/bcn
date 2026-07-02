<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: origen polimórfico de la venta (D20).
 *
 * Hasta ahora la venta NO guardaba referencia al pedido de origen (solo
 * `pedido.venta_id`, unidireccional). `origen_type`/`origen_id` (morph vía
 * morphMap: 'PedidoMostrador'/'PedidoDelivery') lo setean TODAS las
 * conversiones — la de mostrador se actualiza en este mismo desarrollo — y
 * aplica a cualquier canal futuro (salón/mesas). NULL = venta directa POS.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (D20).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}ventas`
                    ADD COLUMN `origen_type` varchar(30) DEFAULT NULL COMMENT 'Morph al pedido de origen (PedidoMostrador/PedidoDelivery via morphMap). NULL = venta directa POS (D20)' AFTER `cierre_turno_id`,
                    ADD COLUMN `origen_id` bigint unsigned DEFAULT NULL AFTER `origen_type`,
                    ADD INDEX `idx_ventas_origen` (`origen_type`,`origen_id`)
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
                    ALTER TABLE `{$prefix}ventas`
                    DROP INDEX `idx_ventas_origen`,
                    DROP COLUMN `origen_type`,
                    DROP COLUMN `origen_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
