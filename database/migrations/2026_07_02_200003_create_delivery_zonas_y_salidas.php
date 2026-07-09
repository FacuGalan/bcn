<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: zonas de entrega + salidas de reparto.
 *
 * - `delivery_zonas` (RF-05): v1 zona = círculo (centro lat/lng + radio_km);
 *   `poligono` JSON queda RESERVADO para zonas dibujadas a futuro. Costo
 *   propio que pisa el cálculo por km, rangos horarios de actividad y orden
 *   de prioridad de match.
 * - `delivery_salidas` (RF-08): agrupa pedidos listos de un repartidor;
 *   registrarla pasa todos a en_camino.
 * - `delivery_salida_pedidos` (append-only): historial completo de intentos
 *   (re-despachos incluidos) con resultado y motivo por pedido.
 *
 * Agrega las FK pedidos_delivery.zona_id → delivery_zonas y
 * pedidos_delivery.salida_id → delivery_salidas (salida ACTUAL).
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-05/RF-06/RF-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}delivery_zonas` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `sucursal_id` bigint(20) unsigned NOT NULL,
                        `nombre` varchar(100) NOT NULL,
                        `centro_lat` decimal(10,7) NOT NULL COMMENT 'Centro del radio (picker Maps)',
                        `centro_lng` decimal(10,7) NOT NULL,
                        `radio_km` decimal(8,2) NOT NULL COMMENT 'v1: zona = circulo',
                        `poligono` json DEFAULT NULL COMMENT 'RESERVADO: zona dibujada futura',
                        `costo_envio` decimal(12,2) NOT NULL COMMENT 'Pisa el calculo por km',
                        `rangos_horarios` json DEFAULT NULL COMMENT '[{dias:[1..7], desde:19:00, hasta:23:30}], NULL = siempre activa',
                        `orden` int(11) NOT NULL DEFAULT 0 COMMENT 'Prioridad de match',
                        `activo` tinyint(1) NOT NULL DEFAULT 1,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_dz_sucursal_activo` (`sucursal_id`,`activo`,`orden`),
                        CONSTRAINT `{$prefix}fk_dz_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}delivery_salidas` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `sucursal_id` bigint(20) unsigned NOT NULL,
                        `repartidor_id` bigint(20) unsigned NOT NULL,
                        `estado` enum('armando','en_camino','finalizada') NOT NULL DEFAULT 'armando',
                        `salida_at` timestamp NULL DEFAULT NULL,
                        `vuelta_at` timestamp NULL DEFAULT NULL,
                        `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'FK logico config.users (quien la registro)',
                        `observaciones` varchar(255) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_ds_sucursal_estado` (`sucursal_id`,`estado`),
                        KEY `idx_ds_repartidor_estado` (`repartidor_id`,`estado`),
                        CONSTRAINT `{$prefix}fk_ds_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                        CONSTRAINT `{$prefix}fk_ds_repartidor` FOREIGN KEY (`repartidor_id`) REFERENCES `{$prefix}repartidores` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}delivery_salida_pedidos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `salida_id` bigint(20) unsigned NOT NULL,
                        `pedido_id` bigint(20) unsigned NOT NULL,
                        `resultado` enum('pendiente','entregado','no_entregado') NOT NULL DEFAULT 'pendiente',
                        `motivo` varchar(255) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_dsp_salida` (`salida_id`),
                        KEY `idx_dsp_pedido` (`pedido_id`),
                        CONSTRAINT `{$prefix}fk_dsp_salida` FOREIGN KEY (`salida_id`) REFERENCES `{$prefix}delivery_salidas` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_dsp_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `{$prefix}pedidos_delivery` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    ADD CONSTRAINT `{$prefix}fk_pd_zona` FOREIGN KEY (`zona_id`) REFERENCES `{$prefix}delivery_zonas` (`id`) ON DELETE SET NULL,
                    ADD CONSTRAINT `{$prefix}fk_pd_salida` FOREIGN KEY (`salida_id`) REFERENCES `{$prefix}delivery_salidas` (`id`) ON DELETE SET NULL
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
                    DROP FOREIGN KEY `{$prefix}fk_pd_zona`,
                    DROP FOREIGN KEY `{$prefix}fk_pd_salida`
                ");
            } catch (\Exception $e) {
                // ignorar
            }

            foreach (['delivery_salida_pedidos', 'delivery_salidas', 'delivery_zonas'] as $tabla) {
                try {
                    DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}{$tabla}`");
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
};
