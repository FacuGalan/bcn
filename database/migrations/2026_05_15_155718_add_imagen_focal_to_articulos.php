<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega coordenadas de focal point a `articulos` para que el usuario
 * controle qué parte de la imagen queda visible cuando el render usa
 * `object-cover` (panel táctil, modal Ver pedido, gestión de artículos).
 *
 * Valores: porcentajes 0..100. Default 50/50 (centro = comportamiento
 * actual de object-cover sin object-position). En CSS se aplica como
 * `object-position: {x}% {y}%`.
 *
 * Solo aplica si la imagen existe; si `imagen_path` es null estos campos
 * se ignoran al renderizar.
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
                    ALTER TABLE `{$prefix}articulos`
                    ADD COLUMN `imagen_focal_x` decimal(5,2) NOT NULL DEFAULT '50.00' COMMENT 'Punto focal X (%) para object-position en render con object-cover' AFTER `imagen_path`,
                    ADD COLUMN `imagen_focal_y` decimal(5,2) NOT NULL DEFAULT '50.00' COMMENT 'Punto focal Y (%) para object-position en render con object-cover' AFTER `imagen_focal_x`
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
                    ALTER TABLE `{$prefix}articulos`
                    DROP COLUMN `imagen_focal_y`,
                    DROP COLUMN `imagen_focal_x`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
