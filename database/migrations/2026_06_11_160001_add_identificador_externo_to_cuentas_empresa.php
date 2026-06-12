<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo CuentaEmpresa ↔ Integraciones de Pago (Fase 1): identidad de la
 * cuenta en el proveedor externo.
 *
 * - identificador_externo: id de la cuenta en el proveedor (para Mercado Pago
 *   es el user_id de la cuenta, el mismo `user_id_externo` de la config de la
 *   integración). El match cuenta↔proveedor es (subtipo, identificador_externo).
 * - UNIQUE (subtipo, identificador_externo): refuerza a nivel BD la
 *   idempotencia del findOrCreate (D5/D10 del spec). Las cuentas manuales
 *   tienen identificador NULL y MySQL admite múltiples NULL en índices únicos.
 *
 * Ref: .claude/specs/vinculo-cuenta-empresa-integraciones.md (Fase 1, RF-02).
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
                    ADD COLUMN `identificador_externo` varchar(100) DEFAULT NULL
                        COMMENT 'Id de la cuenta en el proveedor de pago externo (MP = user_id). Match: (subtipo, identificador_externo)'
                        AFTER `subtipo`,
                    ADD UNIQUE KEY `{$prefix}cuentas_empresa_identidad_unique` (`subtipo`, `identificador_externo`)
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
                    DROP INDEX `{$prefix}cuentas_empresa_identidad_unique`,
                    DROP COLUMN `identificador_externo`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
