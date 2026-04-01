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
                    ADD COLUMN `es_pago_puntos` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si es pago con puntos de fidelización'
                    AFTER `es_cuenta_corriente`,
                    ADD COLUMN `puntos_usados` int unsigned NOT NULL DEFAULT 0 COMMENT 'Puntos consumidos en este pago'
                    AFTER `es_pago_puntos`
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
                    DROP COLUMN `es_pago_puntos`,
                    DROP COLUMN `puntos_usados`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
