<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T47 (ronda cuenta consumidor): canje de artículos por puntos EN LA
 * TIENDA. `{prefix}articulos_sucursales.canje_tienda` (bool, default false):
 * el comercio elige qué artículos se ofrecen para canjear online, por
 * sucursal (misma granularidad que visible_tienda). El costo en puntos NO se
 * persiste acá: se deriva del precio del día (ceil(precio/valor_punto_canje),
 * misma regla del POS RF-25).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    ADD COLUMN `canje_tienda` tinyint(1) NOT NULL DEFAULT 0
                    COMMENT 'RF-T47: canjeable por puntos en la tienda online'
                    AFTER `visible_tienda`
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
                    ALTER TABLE `{$prefix}articulos_sucursales` DROP COLUMN `canje_tienda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
