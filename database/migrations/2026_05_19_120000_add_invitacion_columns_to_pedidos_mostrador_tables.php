<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feature "invitaciones" (cortesias) en Pedidos por Mostrador.
 *
 * Agrega columnas de invitacion a `pedidos_mostrador` (cabecera) y
 * `pedidos_mostrador_detalle` (lineas) para permitir marcar un pedido completo
 * o renglones individuales como cortesia (precio cobrable $0 con motivo,
 * usuario y fecha registrados para reportes).
 *
 * Espec: .claude/specs/invitaciones-pedidos-ventas.md (Fase 1).
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
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    ADD COLUMN `es_invitacion_total` tinyint(1) NOT NULL DEFAULT '0' AFTER `total_final`,
                    ADD COLUMN `invitacion_motivo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `es_invitacion_total`,
                    ADD COLUMN `invitado_por_usuario_id` bigint(20) unsigned DEFAULT NULL AFTER `invitacion_motivo`,
                    ADD COLUMN `invitado_at` timestamp NULL DEFAULT NULL AFTER `invitado_por_usuario_id`,
                    ADD COLUMN `total_invitado` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `invitado_at`,
                    ADD INDEX `idx_pm_es_invitacion_total` (`es_invitacion_total`, `fecha`)
                ");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador_detalle`
                    ADD COLUMN `es_invitacion` tinyint(1) NOT NULL DEFAULT '0' AFTER `total`,
                    ADD COLUMN `invitacion_motivo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `es_invitacion`,
                    ADD COLUMN `invitado_por_usuario_id` bigint(20) unsigned DEFAULT NULL AFTER `invitacion_motivo`,
                    ADD COLUMN `invitado_at` timestamp NULL DEFAULT NULL AFTER `invitado_por_usuario_id`,
                    ADD COLUMN `monto_invitado` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `invitado_at`,
                    ADD COLUMN `precio_unitario_original` decimal(12,2) DEFAULT NULL AFTER `monto_invitado`,
                    ADD INDEX `idx_pmd_es_invitacion` (`es_invitacion`)
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
                    ALTER TABLE `{$prefix}pedidos_mostrador_detalle`
                    DROP INDEX `idx_pmd_es_invitacion`,
                    DROP COLUMN `precio_unitario_original`,
                    DROP COLUMN `monto_invitado`,
                    DROP COLUMN `invitado_at`,
                    DROP COLUMN `invitado_por_usuario_id`,
                    DROP COLUMN `invitacion_motivo`,
                    DROP COLUMN `es_invitacion`
                ");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    DROP INDEX `idx_pm_es_invitacion_total`,
                    DROP COLUMN `total_invitado`,
                    DROP COLUMN `invitado_at`,
                    DROP COLUMN `invitado_por_usuario_id`,
                    DROP COLUMN `invitacion_motivo`,
                    DROP COLUMN `es_invitacion_total`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
