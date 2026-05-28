<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Fase 4: relación N:M FormaPago ↔ Integración.
 *
 * Crea la tabla pivote tenant `forma_pago_integraciones`. Una FormaPago puede
 * apuntar a varias integraciones (cada producto MP —QR, Point, Checkout— es una
 * integración distinta con su propio token), y por cada una define el modo
 * default y los modos que el cajero puede elegir al cobrar.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 4).
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
                    CREATE TABLE `{$prefix}forma_pago_integraciones` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `forma_pago_id` bigint(20) unsigned NOT NULL,
                        `integracion_pago_id` bigint(20) unsigned NOT NULL,
                        `modo_default` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Modo preseleccionado al cobrar: qr_dinamico, qr_estatico, ...',
                        `modos_permitidos` json DEFAULT NULL COMMENT 'Modos que el cajero puede elegir al cobrar (incluye el default)',
                        `es_principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Integración preseleccionada si la FP tiene varias',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `idx_fpi_forma_integracion` (`forma_pago_id`,`integracion_pago_id`),
                        KEY `idx_fpi_integracion` (`integracion_pago_id`),
                        CONSTRAINT `{$prefix}fk_fpi_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_fpi_integracion` FOREIGN KEY (`integracion_pago_id`) REFERENCES `{$prefix}integraciones_pago` (`id`) ON DELETE CASCADE
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}forma_pago_integraciones`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
