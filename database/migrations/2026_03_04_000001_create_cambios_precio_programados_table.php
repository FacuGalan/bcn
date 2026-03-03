<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}cambios_precio_programados` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `usuario_id` bigint unsigned NOT NULL,
                        `fecha_programada` datetime NOT NULL,
                        `estado` enum('pendiente','procesado','cancelado','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
                        `alcance_precio` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
                        `sucursal_id` bigint unsigned DEFAULT NULL,
                        `tipo_ajuste` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `tipo_valor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `valor_ajuste` decimal(12,2) NOT NULL,
                        `tipo_redondeo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_redondeo',
                        `total_articulos` int unsigned NOT NULL DEFAULT 0,
                        `articulos_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `resultado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `procesado_at` datetime DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_cpp_estado_fecha` (`estado`, `fecha_programada`),
                        KEY `idx_cpp_usuario` (`usuario_id`)
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
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cambios_precio_programados`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
