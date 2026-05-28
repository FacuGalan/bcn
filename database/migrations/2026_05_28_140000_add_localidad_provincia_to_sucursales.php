<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3.5 (Integraciones de Pago): localidad y provincia en sucursales.
 *
 * Mercado Pago requiere `city_name` y `state_name` separados al crear una
 * Store. Tenerlos también sirve para reportes y para tienda online futura.
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
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `localidad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `longitud`,
                    ADD COLUMN `provincia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `localidad`
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
                    ALTER TABLE `{$prefix}sucursales`
                    DROP COLUMN `provincia`,
                    DROP COLUMN `localidad`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
