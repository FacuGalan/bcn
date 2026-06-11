<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Modo Point (Fase 1): config específica de Point por
 * forma de pago, en el pivote `forma_pago_integraciones`.
 *
 * - config_point: JSON con la configuración del modo Point para esa FP.
 *   Hoy: {"default_type": "credit_card"|"debit_card"|"qr"}. Ausente/null =
 *   "Abierto" (no se envía default_type → el cliente elige en el aparato).
 *   JSON para ser extensible a futuros parámetros Point (print_on_terminal, etc.).
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago-point.md (Fase 1, RF-04).
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
                    ALTER TABLE `{$prefix}forma_pago_integraciones`
                    ADD COLUMN `config_point` json DEFAULT NULL AFTER `modos_permitidos`
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
                    ALTER TABLE `{$prefix}forma_pago_integraciones`
                    DROP COLUMN `config_point`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
