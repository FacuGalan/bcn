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
                    ALTER TABLE `{$prefix}formas_pago_sucursales`
                    ADD COLUMN `multiplicador_puntos` decimal(4,2) DEFAULT NULL COMMENT 'Multiplicador de puntos específico para esta sucursal (NULL = usar el de la forma de pago)'
                    AFTER `ajuste_porcentaje`
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
                    ALTER TABLE `{$prefix}formas_pago_sucursales`
                    DROP COLUMN `multiplicador_puntos`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
