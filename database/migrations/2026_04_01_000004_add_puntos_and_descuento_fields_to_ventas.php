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
                    ALTER TABLE `{$prefix}ventas`
                    ADD COLUMN `descuento_general_tipo` enum('porcentaje','monto_fijo') DEFAULT NULL COMMENT 'Tipo de descuento general aplicado'
                    AFTER `observaciones`,
                    ADD COLUMN `descuento_general_valor` decimal(12,2) DEFAULT NULL COMMENT 'Valor ingresado por el usuario (% o $)'
                    AFTER `descuento_general_tipo`,
                    ADD COLUMN `descuento_general_monto` decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Monto efectivo descontado por descuento general'
                    AFTER `descuento_general_valor`,
                    ADD COLUMN `cupon_id` bigint unsigned DEFAULT NULL COMMENT 'FK cupón aplicado'
                    AFTER `descuento_general_monto`,
                    ADD COLUMN `monto_cupon` decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Monto descontado por cupón'
                    AFTER `cupon_id`,
                    ADD COLUMN `puntos_ganados` int unsigned NOT NULL DEFAULT 0 COMMENT 'Puntos acumulados en esta venta'
                    AFTER `monto_cupon`,
                    ADD COLUMN `puntos_usados` int unsigned NOT NULL DEFAULT 0 COMMENT 'Puntos canjeados en esta venta'
                    AFTER `puntos_ganados`
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
                    ALTER TABLE `{$prefix}ventas`
                    DROP COLUMN `descuento_general_tipo`,
                    DROP COLUMN `descuento_general_valor`,
                    DROP COLUMN `descuento_general_monto`,
                    DROP COLUMN `cupon_id`,
                    DROP COLUMN `monto_cupon`,
                    DROP COLUMN `puntos_ganados`,
                    DROP COLUMN `puntos_usados`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
