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
            $tabla = "{$prefix}ventas_detalle";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'descuento_promocion_especial'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `descuento_promocion_especial` decimal(12,2) NOT NULL DEFAULT '0.00'
                        COMMENT 'Atribución al item del descuento por promociones especiales (NxM/Combo/Menú). Suma cuadra con venta_promociones.descuento_aplicado.'
                        AFTER `descuento_promocion`
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_descuento_promocion_especial falló para comercio {$comercio->id}: ".$e->getMessage());

                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas_detalle";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'descuento_promocion_especial'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}` DROP COLUMN `descuento_promocion_especial`
                    ");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
