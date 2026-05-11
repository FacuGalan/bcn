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
            $tablaDetalle = "{$prefix}ventas_detalle";
            $tablaVentas = "{$prefix}ventas";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tablaDetalle}'
                    AND COLUMN_NAME = 'ajuste_manual_aplicado_por'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tablaDetalle}`
                        ADD COLUMN `ajuste_manual_aplicado_por` BIGINT UNSIGNED DEFAULT NULL
                        COMMENT 'Auditoria: user.id que aplico el ajuste manual (FK logico cross-DB a config.users)'
                        AFTER `ajuste_manual_origen`
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_auditoria_aplicado_por (ventas_detalle) falló para comercio {$comercio->id}: ".$e->getMessage());
            }

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tablaVentas}'
                    AND COLUMN_NAME = 'descuento_general_aplicado_por'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tablaVentas}`
                        ADD COLUMN `descuento_general_aplicado_por` BIGINT UNSIGNED DEFAULT NULL
                        COMMENT 'Auditoria: user.id que aplico el descuento general (FK logico cross-DB a config.users)'
                        AFTER `descuento_general_monto`
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_auditoria_aplicado_por (ventas) falló para comercio {$comercio->id}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tablaDetalle = "{$prefix}ventas_detalle";
            $tablaVentas = "{$prefix}ventas";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tablaDetalle}'
                    AND COLUMN_NAME = 'ajuste_manual_aplicado_por'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tablaDetalle}` DROP COLUMN `ajuste_manual_aplicado_por`
                    ");
                }
            } catch (\Exception $e) {
                // continuar
            }

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tablaVentas}'
                    AND COLUMN_NAME = 'descuento_general_aplicado_por'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tablaVentas}` DROP COLUMN `descuento_general_aplicado_por`
                    ");
                }
            } catch (\Exception $e) {
                // continuar
            }
        }
    }
};
