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
                    CREATE TABLE `{$prefix}configuracion_puntos` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `activo` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Programa habilitado globalmente',
                        `modo_acumulacion` enum('global','por_sucursal') NOT NULL DEFAULT 'global' COMMENT 'Modo de saldo de puntos',
                        `monto_por_punto` decimal(12,2) NOT NULL DEFAULT 100.00 COMMENT 'Cuántos $ para ganar 1 punto',
                        `valor_punto_canje` decimal(12,2) NOT NULL DEFAULT 50.00 COMMENT 'Cuánto vale 1 punto en $ al canjear',
                        `minimo_canje` int unsigned NOT NULL DEFAULT 10 COMMENT 'Mínimo puntos para habilitar canje',
                        `redondeo` enum('floor','round','ceil') NOT NULL DEFAULT 'floor' COMMENT 'Redondeo de puntos fraccionarios',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Table may already exist
            }

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}configuracion_puntos_sucursales` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `sucursal_id` bigint unsigned NOT NULL,
                        `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Puntos activos en esta sucursal',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_sucursal` (`sucursal_id`),
                        CONSTRAINT `{$prefix}fk_config_puntos_suc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`) ON DELETE CASCADE
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}configuracion_puntos_sucursales`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}configuracion_puntos`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
