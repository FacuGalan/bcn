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
            $tabla = "{$prefix}movimientos_caja";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'anulado_por_movimiento_id'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `anulado_por_movimiento_id` BIGINT UNSIGNED DEFAULT NULL
                        COMMENT 'FK logico self-reference al contraasiento que anula este movimiento (patron append-only)'
                        AFTER `monto_moneda_original`,
                        ADD INDEX `idx_mc_anulado_por` (`anulado_por_movimiento_id`)
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_anulado_por_movimiento_id falló para comercio {$comercio->id}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}movimientos_caja";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'anulado_por_movimiento_id'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        DROP INDEX `idx_mc_anulado_por`,
                        DROP COLUMN `anulado_por_movimiento_id`
                    ");
                }
            } catch (\Exception $e) {
                // continuar
            }
        }
    }
};
