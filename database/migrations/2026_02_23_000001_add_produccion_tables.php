<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de Producción: tablas y columna de control de stock.
 *
 * - Agrega columna `control_stock_produccion` a sucursales
 * - Crea tablas: producciones, produccion_detalles, produccion_ingredientes
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // --- Columna en sucursales ---
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `control_stock_produccion` enum('no_controla','advierte','bloquea')
                    NOT NULL DEFAULT 'bloquea'
                    COMMENT 'Control de stock en producción'
                    AFTER `control_stock_venta`
                ");

                // --- Tabla producciones ---
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}producciones` (
                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                      `sucursal_id` bigint(20) unsigned NOT NULL,
                      `usuario_id` bigint(20) unsigned NOT NULL,
                      `fecha` date NOT NULL,
                      `estado` enum('confirmado','anulado') NOT NULL DEFAULT 'confirmado',
                      `observaciones` text NULL,
                      `anulado_por_usuario_id` bigint(20) unsigned NULL,
                      `fecha_anulacion` timestamp NULL,
                      `motivo_anulacion` text NULL,
                      `created_at` timestamp NULL,
                      `updated_at` timestamp NULL,
                      PRIMARY KEY (`id`),
                      KEY `idx_sucursal_fecha` (`sucursal_id`, `fecha`),
                      KEY `idx_estado` (`estado`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // --- Tabla produccion_detalles ---
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}produccion_detalles` (
                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                      `produccion_id` bigint(20) unsigned NOT NULL,
                      `articulo_id` bigint(20) unsigned NOT NULL,
                      `receta_id` bigint(20) unsigned NOT NULL,
                      `cantidad_producida` decimal(12,3) NOT NULL COMMENT 'Unidades producidas',
                      `cantidad_receta` decimal(12,3) NOT NULL COMMENT 'cantidad_producida de la receta usada',
                      `created_at` timestamp NULL,
                      `updated_at` timestamp NULL,
                      PRIMARY KEY (`id`),
                      KEY `idx_produccion` (`produccion_id`),
                      KEY `idx_articulo` (`articulo_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // --- Tabla produccion_ingredientes ---
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}produccion_ingredientes` (
                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                      `produccion_detalle_id` bigint(20) unsigned NOT NULL,
                      `articulo_id` bigint(20) unsigned NOT NULL,
                      `cantidad_receta` decimal(12,3) NOT NULL COMMENT 'Cantidad según receta',
                      `cantidad_real` decimal(12,3) NOT NULL COMMENT 'Cantidad realmente usada',
                      `created_at` timestamp NULL,
                      `updated_at` timestamp NULL,
                      PRIMARY KEY (`id`),
                      KEY `idx_detalle` (`produccion_detalle_id`),
                      KEY `idx_articulo` (`articulo_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Si falla para un comercio, continuar con el siguiente
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}produccion_ingredientes`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}produccion_detalles`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}producciones`");

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales` DROP COLUMN `control_stock_produccion`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
