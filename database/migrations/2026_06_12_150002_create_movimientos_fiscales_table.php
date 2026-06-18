<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): ledger fiscal (RF-03).
 *
 * Registro estructurado de cada impuesto sufrido o aplicado. Append-only:
 * anulación SOLO por contraasiento (movimiento_anulado_id linkea el inverso
 * al original), mismo patrón que el resto de los ledgers del sistema.
 * El origen es polimórfico con string plano (ComprobanteFiscal/Compra/
 * ConciliacionFila/NULL=manual), sin morphMap.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-03, Fase 1).
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
                    CREATE TABLE IF NOT EXISTS `{$prefix}movimientos_fiscales` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `cuit_id` bigint(20) unsigned NOT NULL,
                        `sucursal_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Jurisdicción de la operación',
                        `impuesto_id` bigint(20) unsigned NOT NULL,
                        `sentido` enum('sufrido','aplicado') COLLATE utf8mb4_unicode_ci NOT NULL,
                        `naturaleza` enum('percepcion','retencion','debito_fiscal','credito_fiscal','tributo') COLLATE utf8mb4_unicode_ci NOT NULL,
                        `fecha` date NOT NULL,
                        `periodo_fiscal` char(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM, calculado al registrar',
                        `base_imponible` decimal(14,2) DEFAULT NULL,
                        `alicuota` decimal(6,4) DEFAULT NULL,
                        `monto` decimal(14,2) NOT NULL COMMENT 'Siempre positivo - el signo lo da naturaleza+sentido',
                        `certificado_numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Constancia de retención',
                        `origen_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ComprobanteFiscal/Compra/ConciliacionFila/NULL=manual',
                        `origen_id` bigint(20) unsigned DEFAULT NULL,
                        `movimiento_anulado_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Contraasiento: apunta al movimiento que anula',
                        `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
                        `observaciones` text COLLATE utf8mb4_unicode_ci,
                        `usuario_id` bigint(20) unsigned DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}idx_movfis_cuit_periodo` (`cuit_id`,`periodo_fiscal`),
                        KEY `{$prefix}idx_movfis_origen` (`origen_tipo`,`origen_id`),
                        KEY `{$prefix}idx_movfis_impuesto` (`impuesto_id`),
                        CONSTRAINT `{$prefix}fk_movfis_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$prefix}cuits` (`id`),
                        CONSTRAINT `{$prefix}fk_movfis_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`),
                        CONSTRAINT `{$prefix}fk_movfis_anulado` FOREIGN KEY (`movimiento_anulado_id`) REFERENCES `{$prefix}movimientos_fiscales` (`id`)
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}movimientos_fiscales`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
