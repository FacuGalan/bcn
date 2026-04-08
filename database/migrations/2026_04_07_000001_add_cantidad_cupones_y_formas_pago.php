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

            // Agregar cantidad al pivot cupon_articulos
            try {
                $columnExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}cupon_articulos'
                    AND COLUMN_NAME = 'cantidad'
                ");

                if (empty($columnExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}cupon_articulos`
                        ADD COLUMN `cantidad` int unsigned DEFAULT NULL COMMENT 'Cantidad de unidades que cubre (NULL = todas)' AFTER `articulo_id`
                    ");
                }
            } catch (\Exception $e) {
                // Skip on error
            }

            // Crear tabla cupon_formas_pago
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}cupon_formas_pago` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cupon_id` bigint unsigned NOT NULL,
                        `forma_pago_id` bigint unsigned NOT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_cupon_forma` (`cupon_id`, `forma_pago_id`),
                        KEY `idx_cfp_forma_pago` (`forma_pago_id`),
                        CONSTRAINT `{$prefix}fk_cfp_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `{$prefix}cupones` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cfp_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE CASCADE
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cupon_formas_pago`");
            } catch (\Exception $e) {
                // Skip on error
            }

            try {
                $columnExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}cupon_articulos'
                    AND COLUMN_NAME = 'cantidad'
                ");

                if (! empty($columnExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}cupon_articulos` DROP COLUMN `cantidad`
                    ");
                }
            } catch (\Exception $e) {
                // Skip on error
            }
        }
    }
};
