<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Personalización de la segunda pantalla (pantalla cliente) por sucursal.
 *
 * JSON con: mostrar_logo, mostrar_nombre, color_fondo, animacion, color_acento,
 * color_texto, mensaje_idle, tamano_logo. Se castea a array en el modelo Sucursal.
 * La pantalla cliente recibe esta config por BroadcastChannel desde el POS.
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
                    ADD COLUMN `config_pantalla_cliente` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
                        COMMENT 'Personalizacion de la segunda pantalla (pantalla cliente) - JSON'
                        AFTER `configuracion`
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
                    DROP COLUMN `config_pantalla_cliente`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
