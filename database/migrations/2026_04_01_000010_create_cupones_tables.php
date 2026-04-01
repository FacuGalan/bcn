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
                    CREATE TABLE `{$prefix}cupones` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `codigo` varchar(50) NOT NULL COMMENT 'Código único del cupón (CUP-XXXXXX)',
                        `tipo` enum('puntos','promocional') NOT NULL COMMENT 'Origen del cupón',
                        `cliente_id` bigint unsigned DEFAULT NULL COMMENT 'FK clientes (NOT NULL si tipo=puntos)',
                        `descripcion` varchar(255) DEFAULT NULL COMMENT 'Descripción del cupón',
                        `modo_descuento` enum('monto_fijo','porcentaje') NOT NULL COMMENT 'Tipo de descuento',
                        `valor_descuento` decimal(12,2) NOT NULL COMMENT 'Monto en $ o porcentaje',
                        `aplica_a` enum('total','articulos') NOT NULL DEFAULT 'total' COMMENT 'A qué aplica el descuento',
                        `uso_maximo` int unsigned NOT NULL DEFAULT 1 COMMENT '0 = ilimitado',
                        `uso_actual` int unsigned NOT NULL DEFAULT 0 COMMENT 'Contador de usos',
                        `fecha_vencimiento` date DEFAULT NULL COMMENT 'NULL = no vence',
                        `activo` tinyint(1) NOT NULL DEFAULT 1,
                        `puntos_consumidos` int unsigned NOT NULL DEFAULT 0 COMMENT 'Puntos que costó crear (tipo=puntos)',
                        `created_by_usuario_id` bigint unsigned NOT NULL COMMENT 'FK users (quien creó)',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_codigo` (`codigo`),
                        KEY `idx_cupones_tipo` (`tipo`),
                        KEY `idx_cupones_activo` (`activo`),
                        KEY `idx_cupones_cliente` (`cliente_id`),
                        KEY `idx_cupones_vencimiento` (`fecha_vencimiento`),
                        CONSTRAINT `{$prefix}fk_cupones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Table may already exist
            }

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}cupon_articulos` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cupon_id` bigint unsigned NOT NULL,
                        `articulo_id` bigint unsigned NOT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_cupon_articulo` (`cupon_id`, `articulo_id`),
                        KEY `idx_ca_articulo` (`articulo_id`),
                        CONSTRAINT `{$prefix}fk_ca_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `{$prefix}cupones` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_ca_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$prefix}articulos` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Table may already exist
            }

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}cupon_usos` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cupon_id` bigint unsigned NOT NULL,
                        `venta_id` bigint unsigned NOT NULL,
                        `cliente_id` bigint unsigned DEFAULT NULL COMMENT 'FK clientes (quien lo usó)',
                        `sucursal_id` bigint unsigned NOT NULL,
                        `monto_descontado` decimal(12,2) NOT NULL COMMENT 'Monto efectivo descontado',
                        `fecha` datetime NOT NULL,
                        `usuario_id` bigint unsigned NOT NULL COMMENT 'FK users (cajero)',
                        `created_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_cu_cupon` (`cupon_id`),
                        KEY `idx_cu_venta` (`venta_id`),
                        KEY `idx_cu_cliente` (`cliente_id`),
                        KEY `idx_cu_fecha` (`fecha`),
                        CONSTRAINT `{$prefix}fk_cu_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `{$prefix}cupones` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cu_venta` FOREIGN KEY (`venta_id`) REFERENCES `{$prefix}ventas` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cu_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_cu_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Table may already exist
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cupon_usos`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cupon_articulos`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cupones`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
