<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): trazabilidad del pago del
 * pedido cobrado por integración, espejo de `venta_pagos` (Fase 9 de
 * integraciones de pago).
 *
 * Vincula el `pedidos_delivery_pago` puntual con la IntegracionPagoTransaccion
 * que lo cobró (checkout online). Habilita: no doble-registrar en la conversión
 * pedido→venta (migrarPagosAVenta copia el vínculo) y el bloqueo de
 * modificaciones cuando el cobro ya se acreditó en el proveedor.
 *
 * ON DELETE SET NULL: las transacciones son append-only y no se borran; el
 * SET NULL es sólo defensa para no perder el pago si alguna vez se eliminara.
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
                    ALTER TABLE `{$prefix}pedidos_delivery_pagos`
                    ADD COLUMN `integracion_pago_transaccion_id` bigint(20) unsigned DEFAULT NULL AFTER `venta_pago_id`,
                    ADD CONSTRAINT `{$prefix}fk_pdp_integracion_tx`
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
                    ALTER TABLE `{$prefix}pedidos_delivery_pagos`
                    DROP FOREIGN KEY `{$prefix}fk_pdp_integracion_tx`
                ");
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_delivery_pagos`
                    DROP COLUMN `integracion_pago_transaccion_id`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
