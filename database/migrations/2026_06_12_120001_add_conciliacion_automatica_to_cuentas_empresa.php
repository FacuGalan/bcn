<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de cuenta (Fase 1): flag de conciliación automática diaria.
 *
 * Solo tiene efecto en cuentas con identificador_externo (cuentas de
 * proveedor de pago): el comando conciliaciones:procesar crea cada día una
 * corrida por el día anterior. La corrida queda pendiente_revision — NUNCA
 * se auto-aplica (RF-08).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 1, RF-08).
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
                    ADD COLUMN `conciliacion_automatica` tinyint(1) NOT NULL DEFAULT '0'
                        COMMENT 'Crear corrida de conciliación diaria automática (solo cuentas con identificador_externo)'
                        AFTER `identificador_externo`
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
                    DROP COLUMN `conciliacion_automatica`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
