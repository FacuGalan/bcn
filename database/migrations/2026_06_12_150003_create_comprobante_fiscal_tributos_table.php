<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): desglose de tributos por comprobante (RF-04).
 *
 * Paralelo a comprobante_fiscal_iva pero para tributos no-IVA (percepciones
 * IIBB, etc.). El total alimenta el campo comprobantes_fiscales.tributos
 * existente (hoy hardcodeado en 0) y viaja a ARCA en el array Tributos.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-04, Fase 1).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}comprobante_fiscal_tributos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `comprobante_fiscal_id` bigint(20) unsigned NOT NULL,
                        `impuesto_id` bigint(20) unsigned NOT NULL,
                        `base_imponible` decimal(14,2) NOT NULL,
                        `alicuota` decimal(6,4) NOT NULL,
                        `monto` decimal(14,2) NOT NULL,
                        `codigo_arca` smallint DEFAULT NULL COMMENT 'Código de tributo del WS de ARCA',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}idx_cft_comprobante` (`comprobante_fiscal_id`),
                        CONSTRAINT `{$prefix}fk_cft_comprobante` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `{$prefix}comprobantes_fiscales` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cft_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`)
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}comprobante_fiscal_tributos`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
