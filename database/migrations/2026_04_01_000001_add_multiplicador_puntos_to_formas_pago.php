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
                    ALTER TABLE `{$prefix}formas_pago`
                    ADD COLUMN `multiplicador_puntos` decimal(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Multiplicador de puntos por forma de pago (0=no suma, 2=doble)'
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
                    ALTER TABLE `{$prefix}formas_pago`
                    DROP COLUMN `multiplicador_puntos`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
