<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 10a, RF-12): domicilio fiscal del cliente.
 *
 * El cliente sólo tenía `cuit` + `direccion` (texto libre). Para el match de
 * percepción de IIBB por jurisdicción del sujeto necesitamos el domicilio
 * estructurado: provincia (ISO 3166-2, define la jurisdicción destino) y
 * localidad (ref SOFT a `localidades` de config, sin FK cross-DB — mismo criterio
 * que `cuits.localidad_id` y `sucursales.localidad_id` de la Fase 9).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-12, Fase 10a).
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
                    ALTER TABLE `{$prefix}clientes`
                    ADD COLUMN `provincia` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ISO 3166-2 - jurisdiccion fiscal' AFTER `direccion`,
                    ADD COLUMN `localidad_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Ref soft a localidades (config)' AFTER `provincia`
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
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}clientes`
                    DROP COLUMN `localidad_id`,
                    DROP COLUMN `provincia`
                ");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }
};
