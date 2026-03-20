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
                    CREATE TABLE IF NOT EXISTS `{$prefix}historial_precios` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `articulo_id` bigint unsigned NOT NULL,
                        `sucursal_id` bigint unsigned DEFAULT NULL COMMENT 'NULL = cambio genérico',
                        `precio_anterior` decimal(12,2) NOT NULL,
                        `precio_nuevo` decimal(12,2) NOT NULL,
                        `usuario_id` bigint unsigned NOT NULL,
                        `origen` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'articulo_crear, articulo_editar, sucursal_override, sucursal_restablecer, masivo_global, masivo_sucursal',
                        `porcentaje_cambio` decimal(8,2) DEFAULT NULL,
                        `detalle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_hp_articulo_fecha` (`articulo_id`, `created_at`),
                        KEY `idx_hp_usuario` (`usuario_id`),
                        KEY `idx_hp_origen` (`origen`),
                        CONSTRAINT `{$prefix}historial_precios_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{$prefix}articulos` (`id`) ON DELETE CASCADE
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}historial_precios`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
