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

            // 1. Crear tabla tesoreria_saldos_moneda
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}tesoreria_saldos_moneda` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `tesoreria_id` bigint(20) unsigned NOT NULL,
                        `moneda_id` bigint(20) unsigned NOT NULL,
                        `saldo_actual` decimal(14,2) NOT NULL DEFAULT 0.00,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}tesoreria_saldos_moneda_tesoreria_moneda_unique` (`tesoreria_id`, `moneda_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // Tabla ya existe, continuar
            }

            // 2. Agregar columnas a movimientos_tesoreria
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_tesoreria`
                    ADD COLUMN `moneda_id` bigint(20) unsigned DEFAULT NULL AFTER `observaciones`,
                    ADD COLUMN `monto_moneda_original` decimal(14,2) DEFAULT NULL AFTER `moneda_id`
                ");
            } catch (\Exception $e) {
                continue;
            }

            // 3. Agregar columna a rendicion_fondos
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}rendicion_fondos`
                    ADD COLUMN `desglose_monedas` json DEFAULT NULL AFTER `observaciones`
                ");
            } catch (\Exception $e) {
                // Columna ya existe
            }

            // 4. Agregar columnas a provision_fondos
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}provision_fondos`
                    ADD COLUMN `moneda_id` bigint(20) unsigned DEFAULT NULL AFTER `observaciones`,
                    ADD COLUMN `monto_moneda_original` decimal(14,2) DEFAULT NULL AFTER `moneda_id`
                ");
            } catch (\Exception $e) {
                // Columnas ya existen
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}tesoreria_saldos_moneda`");
            } catch (\Exception $e) {}

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}movimientos_tesoreria`
                    DROP COLUMN `monto_moneda_original`,
                    DROP COLUMN `moneda_id`
                ");
            } catch (\Exception $e) {}

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}rendicion_fondos`
                    DROP COLUMN `desglose_monedas`
                ");
            } catch (\Exception $e) {}

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}provision_fondos`
                    DROP COLUMN `monto_moneda_original`,
                    DROP COLUMN `moneda_id`
                ");
            } catch (\Exception $e) {}
        }
    }
};
