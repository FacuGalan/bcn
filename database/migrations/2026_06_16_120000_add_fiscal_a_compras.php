<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 6): capa fiscal de compras.
 *
 * (1) `compras.cuit_id`: atribución fiscal — qué CUIT del comercio realizó la
 *     compra. Es la llave de la posición fiscal (el IVA crédito y las
 *     percepciones sufridas se imputan a ese CUIT). Nullable: una compra sin
 *     CUIT asignado sigue funcionando, solo no alimenta el ledger fiscal.
 * (2) `compra_percepciones`: desglose de percepciones/retenciones sufridas en
 *     la factura del proveedor (IIBB/IVA), paralelo a comprobante_fiscal_tributos.
 *
 * Capa desacoplada del módulo de compras (UI/stock/formato vienen después):
 * define la estructura para que el crédito fiscal fluya a la posición.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-05, Fase 6).
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
                    ALTER TABLE `{$prefix}compras`
                    ADD COLUMN `cuit_id` bigint(20) unsigned DEFAULT NULL COMMENT 'CUIT del comercio que realizó la compra (atribución fiscal)' AFTER `proveedor_id`,
                    ADD CONSTRAINT `{$prefix}fk_compras_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$prefix}cuits` (`id`) ON DELETE SET NULL
                ");
            } catch (\Exception $e) {
                // No-op por comercio (columna ya existe / tabla ausente).
            }

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}compra_percepciones` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `compra_id` bigint(20) unsigned NOT NULL,
                        `impuesto_id` bigint(20) unsigned NOT NULL,
                        `base_imponible` decimal(14,2) DEFAULT NULL,
                        `alicuota` decimal(6,4) DEFAULT NULL,
                        `monto` decimal(14,2) NOT NULL,
                        `certificado_numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}idx_cperc_compra` (`compra_id`),
                        CONSTRAINT `{$prefix}fk_cperc_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$prefix}compras` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_cperc_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}compra_percepciones`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}compras` DROP FOREIGN KEY `{$prefix}fk_compras_cuit`");
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}compras` DROP COLUMN `cuit_id`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }
};
