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
                    ADD COLUMN `disponible_en_tienda` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si la FP se ofrece en la tienda online de esta sucursal (RF-T18)'
                    AFTER `factura_fiscal`
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
                    DROP COLUMN `disponible_en_tienda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
