<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 9 (Integraciones de Pago): trazabilidad del pago individual.
 *
 * Vincula un `venta_pago` puntual con la `IntegracionPagoTransaccion` que lo
 * cobró (MercadoPago QR). Hasta ahora el vínculo era sólo Venta -> transacción
 * (polimórfico), lo que volvía ambiguo cuál de los pagos del desglose mixto fue
 * el cobrado por integración. Esta columna lo resuelve y habilita el bloqueo de
 * modificación/cancelación cuando el cobro ya se efectuó en el proveedor.
 *
 * ON DELETE SET NULL: las transacciones son append-only y no se borran; el
 * SET NULL es sólo una defensa para no perder el pago si alguna vez se eliminara.
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
                    ALTER TABLE `{$prefix}venta_pagos`
                    ADD COLUMN `integracion_pago_transaccion_id` bigint(20) unsigned DEFAULT NULL AFTER `comprobante_fiscal_id`,
                    ADD CONSTRAINT `{$prefix}fk_vp_integracion_tx`
                        FOREIGN KEY (`integracion_pago_transaccion_id`)
                        REFERENCES `{$prefix}integraciones_pago_transacciones` (`id`)
                        ON DELETE SET NULL
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
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP FOREIGN KEY `{$prefix}fk_vp_integracion_tx`
                ");
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP COLUMN `integracion_pago_transaccion_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
