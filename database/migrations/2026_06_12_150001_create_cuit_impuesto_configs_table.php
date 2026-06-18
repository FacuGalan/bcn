<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): configuración impositiva por CUIT (RF-02).
 *
 * Por cada CUIT del comercio: en qué impuestos está inscripto, alícuota
 * aplicable (origen manual; padrón provincial = fase futura, D3), si actúa
 * como agente de percepción/retención, y vigencia. La condición de IVA vive
 * en cuits.condicion_iva_id (existente).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-02, Fase 1).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}cuit_impuesto_configs` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `cuit_id` bigint(20) unsigned NOT NULL,
                        `impuesto_id` bigint(20) unsigned NOT NULL,
                        `inscripto` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Alcanzado por este impuesto',
                        `numero_inscripcion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `es_agente_percepcion` tinyint(1) NOT NULL DEFAULT '0',
                        `es_agente_retencion` tinyint(1) NOT NULL DEFAULT '0',
                        `alicuota` decimal(6,4) DEFAULT NULL COMMENT '% aplicable (que aplica o sufre)',
                        `alicuota_minimo_base` decimal(12,2) DEFAULT NULL COMMENT 'Base mínima para aplicar',
                        `origen_alicuota` enum('manual','padron') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT 'padron = integración futura ARBA/AGIP',
                        `vigente_desde` date DEFAULT NULL,
                        `vigente_hasta` date DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}uq_cuitimp_cuit_imp_desde` (`cuit_id`,`impuesto_id`,`vigente_desde`),
                        KEY `{$prefix}idx_cuitimp_cuit` (`cuit_id`),
                        CONSTRAINT `{$prefix}fk_cuitimp_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$prefix}cuits` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cuitimp_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`)
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cuit_impuesto_configs`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
