<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): config específica del modo
 * checkout por forma de pago, en el pivote `forma_pago_integraciones`.
 *
 * - config_checkout: JSON con la configuración del checkout online para esa
 *   FP. Hoy: {"cuotas_max": n} (ausente/null = defaults del proveedor). JSON
 *   para ser extensible (patrón config_point / config_qr_libre).
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
                    ALTER TABLE `{$prefix}forma_pago_integraciones`
                    ADD COLUMN `config_checkout` json DEFAULT NULL
                        COMMENT 'Config del checkout online por FP: {\"cuotas_max\": n} (null = defaults)'
                        AFTER `config_qr_libre`
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
                    DROP COLUMN `config_checkout`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
