<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
                    ADD COLUMN `estado_facturacion` ENUM('no_facturado','facturado','pendiente_de_facturar','error_arca')
                        NOT NULL DEFAULT 'no_facturado'
                        COMMENT 'Estado de facturación del pago: no_facturado|facturado|pendiente_de_facturar|error_arca'
                        AFTER `datos_snapshot_json`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    ADD INDEX `idx_vp_estado_facturacion` (`estado_facturacion`)
                ");
            } catch (\Exception $e) {
                // index puede ya existir, seguir
            }

            // Backfill: pagos con comprobante_fiscal_id → 'facturado'
            try {
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}venta_pagos`
                    SET `estado_facturacion` = 'facturado'
                    WHERE `comprobante_fiscal_id` IS NOT NULL
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
                    DROP INDEX `idx_vp_estado_facturacion`
                ");
            } catch (\Exception $e) {
                // continue
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}venta_pagos`
                    DROP COLUMN `estado_facturacion`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
