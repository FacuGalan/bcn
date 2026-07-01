<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (revisión Fable): monto mínimo de percepción por régimen.
 *
 * Varios regímenes (ej. percepción de IVA RG 2408) no practican la percepción
 * si el IMPORTE RESULTANTE no supera un mínimo — distinto del umbral de base
 * imponible (`alicuota_minimo_base`) que ya existía. Config del AGENTE.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Revisión pendiente / pasada de Fable).
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
                    ALTER TABLE `{$prefix}cuit_impuesto_configs`
                    ADD COLUMN `monto_minimo_percepcion` decimal(15,2) DEFAULT NULL COMMENT 'Monto minimo de percepcion del regimen: si el importe resultante no lo alcanza, no se practica' AFTER `alicuota_minimo_base`
                ");
            } catch (\Exception $e) {
                // No-op por comercio (columna ya existe).
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}cuit_impuesto_configs` DROP COLUMN `monto_minimo_percepcion`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }
};
