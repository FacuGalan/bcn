<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): un checkout iniciado desde la
 * tienda no tiene operador del panel detrás. NULL = transacción iniciada por
 * el consumidor (todas las tx presenciales siguen registrando su usuario).
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 1, modelo de datos).
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
                    ALTER TABLE `{$prefix}integraciones_pago_transacciones`
                    MODIFY COLUMN `usuario_iniciador_id` bigint(20) unsigned DEFAULT NULL
                        COMMENT 'FK lógico cross-DB a config.users.id (NULL = iniciada por el consumidor en la tienda)'
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
                    ALTER TABLE `{$prefix}integraciones_pago_transacciones`
                    MODIFY COLUMN `usuario_iniciador_id` bigint(20) unsigned NOT NULL
                        COMMENT 'FK lógico cross-DB a config.users.id'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
