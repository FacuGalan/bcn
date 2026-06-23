<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 10a, RF-13): perfil fiscal del cliente.
 *
 * Espejo de `cuit_impuesto_configs` pero por CLIENTE y con semántica de SUJETO
 * PERCIBIDO (no de agente). Gobierna la percepción de IIBB que se le aplica a
 * cada cliente: exención, alícuota por sujeto (override manual o del padrón),
 * N° de inscripción/constancia y vigencia.
 *
 * Diferencias con `cuit_impuesto_configs`:
 *  - se quitan `es_agente_percepcion`/`es_agente_retencion` (el cliente no es
 *    agente en nuestro sistema);
 *  - se agrega `exento` (marca explícita de no-percibir, para exentos / con
 *    certificado de exclusión);
 *  - se agrega `datos_extra` (json) para conservar la fila cruda del padrón
 *    (trazabilidad, sólo origen padrón — Fase 10b).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-13, Fase 10a).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}cliente_impuesto_configs` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `cliente_id` bigint(20) unsigned NOT NULL,
                        `impuesto_id` bigint(20) unsigned NOT NULL,
                        `exento` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si true, NO se le percibe este impuesto',
                        `alicuota` decimal(6,4) DEFAULT NULL COMMENT '% a percibir (override del fijo del agente)',
                        `alicuota_minimo_base` decimal(12,2) DEFAULT NULL COMMENT 'Umbral de base imponible',
                        `numero_padron` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'N de inscripcion/constancia del sujeto',
                        `origen_alicuota` enum('manual','padron') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
                        `vigente_desde` date DEFAULT NULL,
                        `vigente_hasta` date DEFAULT NULL,
                        `datos_extra` json DEFAULT NULL COMMENT 'Fila cruda del padron (trazabilidad, solo origen padron)',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}uq_cliimp_cli_imp_desde` (`cliente_id`,`impuesto_id`,`vigente_desde`),
                        KEY `{$prefix}idx_cliimp_cliente` (`cliente_id`),
                        CONSTRAINT `{$prefix}fk_cliimp_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cliimp_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`)
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}cliente_impuesto_configs`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
