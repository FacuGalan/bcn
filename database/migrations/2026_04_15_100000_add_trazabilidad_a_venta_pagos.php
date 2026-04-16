<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    ADD COLUMN `venta_pago_reemplazado_id` BIGINT UNSIGNED NULL DEFAULT NULL
                        COMMENT 'FK al venta_pago que este reemplaza (cambio de pago)'
                        AFTER `motivo_anulacion`,
                    ADD COLUMN `operacion_origen` ENUM('venta_original','cambio_pago','pago_agregado','anulacion_sin_reemplazo') NOT NULL DEFAULT 'venta_original'
                        COMMENT 'Origen del registro: alta de venta o ajuste posterior'
                        AFTER `venta_pago_reemplazado_id`,
                    ADD COLUMN `creado_por_usuario_id` BIGINT UNSIGNED NULL DEFAULT NULL
                        COMMENT 'FK a config.users - usuario que creó este registro (si distinto del de la venta)'
                        AFTER `operacion_origen`,
                    ADD COLUMN `nota_credito_generada_id` BIGINT UNSIGNED NULL DEFAULT NULL
                        COMMENT 'FK a comprobantes_fiscales - NC disparada por la anulación de este pago'
                        AFTER `creado_por_usuario_id`,
                    ADD COLUMN `comprobante_fiscal_nuevo_id` BIGINT UNSIGNED NULL DEFAULT NULL
                        COMMENT 'FK a comprobantes_fiscales - FC nueva emitida al reemplazar este pago'
                        AFTER `nota_credito_generada_id`,
                    ADD COLUMN `datos_snapshot_json` LONGTEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
                        COMMENT 'Snapshot JSON del pago al momento de anularse (forense)'
                        AFTER `comprobante_fiscal_nuevo_id`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    ADD INDEX `idx_vp_reemplazado` (`venta_pago_reemplazado_id`),
                    ADD INDEX `idx_vp_operacion_origen` (`operacion_origen`),
                    ADD INDEX `idx_vp_creado_por_usuario` (`creado_por_usuario_id`),
                    ADD CONSTRAINT `{$prefix}fk_vp_reemplazado` FOREIGN KEY (`venta_pago_reemplazado_id`)
                        REFERENCES `{$prefix}venta_pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    ADD CONSTRAINT `{$prefix}fk_vp_nc_generada` FOREIGN KEY (`nota_credito_generada_id`)
                        REFERENCES `{$prefix}comprobantes_fiscales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    ADD CONSTRAINT `{$prefix}fk_vp_fc_nuevo` FOREIGN KEY (`comprobante_fiscal_nuevo_id`)
                        REFERENCES `{$prefix}comprobantes_fiscales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP FOREIGN KEY `{$prefix}fk_vp_reemplazado`,
                    DROP FOREIGN KEY `{$prefix}fk_vp_nc_generada`,
                    DROP FOREIGN KEY `{$prefix}fk_vp_fc_nuevo`
                ");
            } catch (\Exception $e) {
                // continue
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP INDEX `idx_vp_reemplazado`,
                    DROP INDEX `idx_vp_operacion_origen`,
                    DROP INDEX `idx_vp_creado_por_usuario`
                ");
            } catch (\Exception $e) {
                // continue
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP COLUMN `datos_snapshot_json`,
                    DROP COLUMN `comprobante_fiscal_nuevo_id`,
                    DROP COLUMN `nota_credito_generada_id`,
                    DROP COLUMN `creado_por_usuario_id`,
                    DROP COLUMN `operacion_origen`,
                    DROP COLUMN `venta_pago_reemplazado_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
