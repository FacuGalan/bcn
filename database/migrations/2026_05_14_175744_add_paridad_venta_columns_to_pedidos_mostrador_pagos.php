<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paridad de persistencia Pedido <-> Venta (spec: pedidos-mostrador-paridad-venta).
 *
 * Agrega a `pedidos_mostrador_pagos` 2 columnas de auditoria que ya existen
 * en `venta_pagos`: saldo_pendiente y operacion_origen. El ENUM de
 * operacion_origen replica exactamente los valores de venta_pagos para que
 * la conversion Pedido -> Venta no necesite mapeo.
 *
 * Nota: `creado_por_usuario_id` ya existe en la migracion original de
 * pedidos_mostrador_pagos, por eso esta migracion agrega solo 2 columnas
 * (no 3 como decia el reporte de exploracion inicial).
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
                    ALTER TABLE `{$prefix}pedidos_mostrador_pagos`
                    ADD COLUMN `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `monto_final`,
                    ADD COLUMN `operacion_origen` enum('venta_original','cambio_pago','pago_agregado','anulacion_sin_reemplazo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'venta_original' AFTER `saldo_pendiente`
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
                    ALTER TABLE `{$prefix}pedidos_mostrador_pagos`
                    DROP COLUMN `operacion_origen`,
                    DROP COLUMN `saldo_pendiente`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
