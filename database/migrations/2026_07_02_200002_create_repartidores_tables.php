<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: repartidores + fondo de cambio de ciclo largo.
 *
 * - `repartidores` (RF-07, D3): entidad propia, tipo propio/tercero, flag
 *   envio_es_del_repartidor (el envío se liquida en la rendición, no es
 *   ingreso), user_id lógico a config.users para la app futura.
 * - `repartidor_sucursal`: sucursales habilitadas por repartidor.
 * - `repartidor_fondos` (RF-09, D4): fondo entregado desde una caja, de CICLO
 *   LARGO (puede quedar abierto entre salidas; se rinde cuando se decide).
 * - `repartidor_fondo_movimientos` (append-only, D13): entrega inicial,
 *   refuerzos, cobros en efectivo de la calle, vueltos, liquidación de envíos
 *   de terceros, rendición. El saldo teórico se calcula de los movimientos.
 *
 * Agrega además la FK pedidos_delivery.repartidor_id → repartidores.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-07/RF-09, D3/D4/D13).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}repartidores` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `nombre` varchar(150) NOT NULL,
                        `telefono` varchar(30) DEFAULT NULL,
                        `tipo` enum('propio','tercero') NOT NULL DEFAULT 'propio' COMMENT 'D3',
                        `envio_es_del_repartidor` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'true: el costo de envio cobrado se liquida al repartidor en la rendicion (no es ingreso del comercio)',
                        `user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico config.users (futura app de repartidores)',
                        `activo` tinyint(1) NOT NULL DEFAULT 1,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_rep_activo` (`activo`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}repartidor_sucursal` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `repartidor_id` bigint(20) unsigned NOT NULL,
                        `sucursal_id` bigint(20) unsigned NOT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}repartidor_sucursal_unique` (`repartidor_id`,`sucursal_id`),
                        KEY `{$prefix}repartidor_sucursal_sucursal_idx` (`sucursal_id`),
                        CONSTRAINT `{$prefix}fk_repsuc_repartidor` FOREIGN KEY (`repartidor_id`) REFERENCES `{$prefix}repartidores` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_repsuc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}repartidor_fondos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `repartidor_id` bigint(20) unsigned NOT NULL,
                        `sucursal_id` bigint(20) unsigned NOT NULL COMMENT 'Un fondo abierto por repartidor+sucursal',
                        `caja_origen_id` bigint(20) unsigned NOT NULL COMMENT 'Caja de la que salio el cambio (egreso)',
                        `estado` enum('abierto','rendido') NOT NULL DEFAULT 'abierto' COMMENT 'Ciclo largo: puede quedar abierto entre salidas (D4)',
                        `monto_inicial` decimal(12,2) NOT NULL,
                        `monto_rendido` decimal(12,2) DEFAULT NULL COMMENT 'Efectivo declarado al cerrar',
                        `diferencia` decimal(12,2) DEFAULT NULL COMMENT 'sobrante(+)/faltante(-) vs saldo teorico',
                        `caja_rendicion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Caja donde ingreso la rendicion',
                        `usuario_apertura_id` bigint(20) unsigned NOT NULL COMMENT 'FK logico config.users',
                        `usuario_cierre_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico config.users',
                        `abierto_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `rendido_at` timestamp NULL DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_rf_repartidor_estado` (`repartidor_id`,`estado`),
                        KEY `idx_rf_sucursal_estado` (`sucursal_id`,`estado`),
                        KEY `idx_rf_caja_origen` (`caja_origen_id`),
                        CONSTRAINT `{$prefix}fk_rf_repartidor` FOREIGN KEY (`repartidor_id`) REFERENCES `{$prefix}repartidores` (`id`),
                        CONSTRAINT `{$prefix}fk_rf_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                        CONSTRAINT `{$prefix}fk_rf_caja_origen` FOREIGN KEY (`caja_origen_id`) REFERENCES `{$prefix}cajas` (`id`),
                        CONSTRAINT `{$prefix}fk_rf_caja_rendicion` FOREIGN KEY (`caja_rendicion_id`) REFERENCES `{$prefix}cajas` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}repartidor_fondo_movimientos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `fondo_id` bigint(20) unsigned NOT NULL,
                        `tipo` enum('entrega_inicial','refuerzo','cobro_pedido','vuelto','liquidacion_envios','rendicion','ajuste') NOT NULL,
                        `monto` decimal(12,2) NOT NULL COMMENT 'Con signo segun tipo (append-only, sin updates)',
                        `pedido_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK pedidos_delivery (cobros/vueltos)',
                        `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Egreso/ingreso de caja vinculado (apertura/refuerzo/rendicion)',
                        `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'FK logico config.users',
                        `detalle` varchar(255) DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_rfm_fondo` (`fondo_id`),
                        KEY `idx_rfm_tipo` (`tipo`),
                        KEY `idx_rfm_pedido` (`pedido_id`),
                        CONSTRAINT `{$prefix}fk_rfm_fondo` FOREIGN KEY (`fondo_id`) REFERENCES `{$prefix}repartidor_fondos` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_rfm_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `{$prefix}pedidos_delivery` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_rfm_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `{$prefix}movimientos_caja` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    ADD CONSTRAINT `{$prefix}fk_pd_repartidor` FOREIGN KEY (`repartidor_id`) REFERENCES `{$prefix}repartidores` (`id`) ON DELETE SET NULL
                ");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_delivery_pagos`
                    ADD CONSTRAINT `{$prefix}fk_pdpago_fondo` FOREIGN KEY (`repartidor_fondo_id`) REFERENCES `{$prefix}repartidor_fondos` (`id`) ON DELETE SET NULL
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
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}pedidos_delivery_pagos` DROP FOREIGN KEY `{$prefix}fk_pdpago_fondo`");
            } catch (\Exception $e) {
                // ignorar
            }

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}pedidos_delivery` DROP FOREIGN KEY `{$prefix}fk_pd_repartidor`");
            } catch (\Exception $e) {
                // ignorar
            }

            foreach ([
                'repartidor_fondo_movimientos',
                'repartidor_fondos',
                'repartidor_sucursal',
                'repartidores',
            ] as $tabla) {
                try {
                    DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}{$tabla}`");
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
};
