<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T14: configuración de tienda POR ARTÍCULO.
 *
 * 1. Tabla nueva `{prefix}articulo_imagenes_tienda`: galería de fotos
 *    específicas de la tienda (1:N por artículo, máx 5 validado en service).
 *    La imagen operativa (`articulos.imagen_path`) queda como fallback.
 * 2. Columna `{prefix}articulos.badges_tienda` JSON: array de badges
 *    [{"tipo":"sin_tacc"},{"tipo":"custom","texto":"..."}] (máx 4, validado
 *    en el panel; catálogo de tipos en Articulo::BADGES_TIENDA).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}articulo_imagenes_tienda` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `articulo_id` bigint unsigned NOT NULL,
                        `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `orden` int NOT NULL DEFAULT '0',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}idx_aimg_tienda_articulo` (`articulo_id`),
                        CONSTRAINT `{$prefix}fk_aimg_tienda_articulo` FOREIGN KEY (`articulo_id`)
                            REFERENCES `{$prefix}articulos` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                continue;
            }
        }

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos`
                    ADD COLUMN `badges_tienda` json DEFAULT NULL
                    COMMENT 'Badges de la tienda (RF-T14): tipos predefinidos + custom'
                    AFTER `destacado`
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}articulo_imagenes_tienda`");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos` DROP COLUMN `badges_tienda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
