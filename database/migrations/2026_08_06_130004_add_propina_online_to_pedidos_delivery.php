<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): propina online (RF-T83).
 *
 * Monto de propina que el consumidor agregó al pagar online. NO integra el
 * total del pedido ni el total facturable (la conversión pedido→venta la
 * excluye): se cobra junto al pedido en la misma preferencia de MP y se
 * registra en la CuentaEmpresa como movimiento separado (concepto
 * `propina_online`) para que un módulo futuro la rinda al repartidor.
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 1, RF-T83).
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
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    ADD COLUMN `propina_online` decimal(12,2) NOT NULL DEFAULT 0
                        COMMENT 'Propina agregada por el consumidor al pagar online (RF-T83). Fuera del total del pedido y del total facturable.'
                        AFTER `total_final`
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
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    DROP COLUMN `propina_online`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
