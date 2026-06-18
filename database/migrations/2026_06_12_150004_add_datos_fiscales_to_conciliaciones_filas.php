<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): datos fiscales en las filas de conciliación
 * (RF-06).
 *
 * - datos_extra: fila cruda del CSV del proveedor (JSON) — trazabilidad y a
 *   prueba de columnas futuras (TAX_DETAIL, TAXES_DISAGGREGATED...).
 * - impuesto_id: desglose del impuesto identificado a partir de TAX_DETAIL.
 * - alerta_validacion: texto esperado-vs-real cuando lo descontado difiere
 *   de la config del CUIT (D4).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-06, Fase 1).
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
                    ALTER TABLE `{$prefix}conciliacion_filas`
                    ADD COLUMN `datos_extra` json DEFAULT NULL COMMENT 'Fila cruda del reporte del proveedor' AFTER `descripcion`,
                    ADD COLUMN `impuesto_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Impuesto identificado (desglose fiscal)' AFTER `concepto_codigo`,
                    ADD COLUMN `alerta_validacion` varchar(255) DEFAULT NULL COMMENT 'Alerta esperado-vs-real contra la config del CUIT' AFTER `impuesto_id`,
                    ADD CONSTRAINT `{$prefix}fk_concf_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$prefix}impuestos` (`id`)
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
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}conciliacion_filas`
                    DROP FOREIGN KEY `{$prefix}fk_concf_impuesto`
                ");
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}conciliacion_filas`
                    DROP COLUMN `datos_extra`,
                    DROP COLUMN `impuesto_id`,
                    DROP COLUMN `alerta_validacion`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
