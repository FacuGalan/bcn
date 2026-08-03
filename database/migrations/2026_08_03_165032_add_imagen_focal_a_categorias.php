<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T62: punto focal del banner de categoría de la tienda.
 *
 * Columnas `{prefix}categorias.imagen_focal_x/y` (%), espejo exacto de las
 * de artículos (2026_05_15_155718): el banner del encabezado de categoría se
 * renderiza con object-cover sobre una franja panorámica y la foto original
 * casi nunca tiene esa proporción — el focal decide qué parte se ve. Se
 * elige desde el panel de tienda con el mismo selector de click que la
 * imagen de artículo.
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
                    ALTER TABLE `{$prefix}categorias`
                    ADD COLUMN `imagen_focal_x` decimal(5,2) NOT NULL DEFAULT '50.00' COMMENT 'Punto focal X (%) del banner para object-position en render con object-cover (RF-T62)' AFTER `imagen_path`,
                    ADD COLUMN `imagen_focal_y` decimal(5,2) NOT NULL DEFAULT '50.00' COMMENT 'Punto focal Y (%) del banner para object-position en render con object-cover (RF-T62)' AFTER `imagen_focal_x`
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
                    ALTER TABLE `{$prefix}categorias`
                    DROP COLUMN `imagen_focal_y`,
                    DROP COLUMN `imagen_focal_x`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
