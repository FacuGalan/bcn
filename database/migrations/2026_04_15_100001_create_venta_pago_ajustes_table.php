<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit log de operaciones de ajuste sobre pagos de ventas.
 * Un registro por operación atómica (cambio/agregado/eliminación de pago).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}venta_pago_ajustes` (
                        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        `venta_id` BIGINT(20) UNSIGNED NOT NULL,
                        `sucursal_id` BIGINT(20) UNSIGNED NOT NULL,
                        `tipo_operacion` ENUM('cambio_pago','agregar_pago','eliminar_pago') NOT NULL,
                        `venta_pago_anulado_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'NULL si es agregar_pago',
                        `venta_pago_nuevo_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'NULL si es eliminar_pago',
                        `forma_pago_anterior_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                        `forma_pago_nueva_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                        `monto_anterior` DECIMAL(12,2) NULL DEFAULT NULL,
                        `monto_nuevo` DECIMAL(12,2) NULL DEFAULT NULL,
                        `delta_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Diferencia en total_final de la venta',
                        `delta_fiscal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'true si cambió la condición fiscal',
                        `turno_original_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'cierre_turno_id del pago anulado',
                        `es_post_cierre` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'true si afectó turno cerrado',
                        `nc_emitida_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                        `fc_nueva_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                        `nc_emitida_flag` TINYINT(1) NOT NULL DEFAULT 0,
                        `fc_nueva_flag` TINYINT(1) NOT NULL DEFAULT 0,
                        `salteo_nc_autorizado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'true si el usuario saltó NC con permiso modificar_pagos_sin_nc',
                        `config_auto_al_operar` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'snapshot de sucursales.facturacion_fiscal_automatica',
                        `motivo` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
                        `descripcion_auto` TEXT COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción generada: Cambió Débito Visa 300 por Transferencia Galicia 300',
                        `usuario_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK a config.users - quién hizo el cambio',
                        `ip_origen` VARCHAR(45) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                        `user_agent` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                        `created_at` TIMESTAMP NULL DEFAULT NULL,
                        `updated_at` TIMESTAMP NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_vpa_venta` (`venta_id`),
                        KEY `idx_vpa_sucursal_fecha` (`sucursal_id`, `created_at`),
                        KEY `idx_vpa_post_cierre` (`es_post_cierre`, `created_at`),
                        KEY `idx_vpa_usuario` (`usuario_id`),
                        KEY `idx_vpa_tipo` (`tipo_operacion`),
                        KEY `idx_vpa_vp_anulado` (`venta_pago_anulado_id`),
                        KEY `idx_vpa_vp_nuevo` (`venta_pago_nuevo_id`),
                        KEY `idx_vpa_fp_anterior` (`forma_pago_anterior_id`),
                        KEY `idx_vpa_fp_nueva` (`forma_pago_nueva_id`),
                        KEY `idx_vpa_nc` (`nc_emitida_id`),
                        KEY `idx_vpa_fc` (`fc_nueva_id`),
                        KEY `idx_vpa_turno` (`turno_original_id`),
                        CONSTRAINT `{$prefix}fk_vpa_venta` FOREIGN KEY (`venta_id`)
                            REFERENCES `{$prefix}ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_vp_anulado` FOREIGN KEY (`venta_pago_anulado_id`)
                            REFERENCES `{$prefix}venta_pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_vp_nuevo` FOREIGN KEY (`venta_pago_nuevo_id`)
                            REFERENCES `{$prefix}venta_pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_fp_anterior` FOREIGN KEY (`forma_pago_anterior_id`)
                            REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_fp_nueva` FOREIGN KEY (`forma_pago_nueva_id`)
                            REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_nc` FOREIGN KEY (`nc_emitida_id`)
                            REFERENCES `{$prefix}comprobantes_fiscales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                        CONSTRAINT `{$prefix}fk_vpa_fc` FOREIGN KEY (`fc_nueva_id`)
                            REFERENCES `{$prefix}comprobantes_fiscales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}venta_pago_ajustes`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
