<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 9, RF-11): domicilios fiscales por CUIT.
 *
 * (1) `cuit_domicilios`: cada CUIT declara N domicilios (espejo de AFIP). La
 *     jurisdicción de la operación sale del domicilio del punto de venta, no de
 *     la sucursal física. `localidad_id` es ref SOFT a `localidades` (config),
 *     sin FK cross-DB — mismo criterio que `cuits.localidad_id`.
 * (2) `puntos_venta.cuit_domicilio_id`: el domicilio declarado del PV (uno de
 *     los de su CUIT). ON DELETE SET NULL.
 * (3) `sucursales.localidad_id`: domicilio físico estructurado de la sucursal
 *     (ref soft), reemplaza la edición del texto libre `localidad`/`provincia`
 *     (que quedan de transición). Independiente de tener CUIT o integración MP.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-11, Fase 9).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}cuit_domicilios` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `cuit_id` bigint(20) unsigned NOT NULL,
                        `tipo` enum('fiscal','comercial','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fiscal',
                        `provincia` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ISO 3166-2 - jurisdiccion',
                        `localidad_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Ref soft a localidades (config)',
                        `direccion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `codigo_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Diferido - no usado en UI',
                        `latitud` decimal(10,7) DEFAULT NULL,
                        `longitud` decimal(10,7) DEFAULT NULL,
                        `es_principal` tinyint(1) NOT NULL DEFAULT 0,
                        `activo` tinyint(1) NOT NULL DEFAULT 1,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}idx_cdom_cuit` (`cuit_id`),
                        CONSTRAINT `{$prefix}fk_cdom_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$prefix}cuits` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // No-op por comercio.
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}puntos_venta`
                    ADD COLUMN `cuit_domicilio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Domicilio declarado del PV' AFTER `cuit_id`,
                    ADD CONSTRAINT `{$prefix}fk_pv_cdom` FOREIGN KEY (`cuit_domicilio_id`) REFERENCES `{$prefix}cuit_domicilios` (`id`) ON DELETE SET NULL
                ");
            } catch (\Exception $e) {
                // No-op por comercio.
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `localidad_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Ref soft a localidades (config)' AFTER `localidad`
                ");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}sucursales` DROP COLUMN `localidad_id`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}puntos_venta` DROP FOREIGN KEY `{$prefix}fk_pv_cdom`");
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}puntos_venta` DROP COLUMN `cuit_domicilio_id`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }

            try {
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cuit_domicilios`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }
};
