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
                    ALTER TABLE `{$prefix}ventas_detalle`
                    ADD COLUMN `pagado_con_puntos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si fue canjeado con puntos'
                    AFTER `precio_sin_ajuste_manual`,
                    ADD COLUMN `puntos_usados` int unsigned NOT NULL DEFAULT 0 COMMENT 'Puntos consumidos para este artículo'
                    AFTER `pagado_con_puntos`
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
                    ALTER TABLE `{$prefix}ventas_detalle`
                    DROP COLUMN `pagado_con_puntos`,
                    DROP COLUMN `puntos_usados`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
