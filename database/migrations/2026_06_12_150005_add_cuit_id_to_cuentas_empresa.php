<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): vínculo cuenta de empresa ↔ CUIT (RF-07).
 *
 * Una cuenta vinculada a una integración de pago (ej. cuenta MP) pertenece a
 * un CUIT del comercio: los impuestos que el proveedor descuenta se imputan
 * fiscalmente a ese CUIT. Nullable: sin CUIT asignado, la conciliación
 * registra el ledger igual pero no genera movimientos fiscales (aviso en UI).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-07, Fase 1).
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
                    ALTER TABLE `{$prefix}cuentas_empresa`
                    ADD COLUMN `cuit_id` bigint(20) unsigned DEFAULT NULL COMMENT 'CUIT al que se imputan los impuestos de esta cuenta' AFTER `identificador_externo`,
                    ADD CONSTRAINT `{$prefix}fk_ctaemp_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$prefix}cuits` (`id`)
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
                    ALTER TABLE `{$prefix}cuentas_empresa`
                    DROP FOREIGN KEY `{$prefix}fk_ctaemp_cuit`
                ");
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}cuentas_empresa`
                    DROP COLUMN `cuit_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
