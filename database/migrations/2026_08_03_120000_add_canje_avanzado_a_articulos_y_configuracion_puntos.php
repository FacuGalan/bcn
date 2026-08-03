<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T58/RF-T59 (ronda canje avanzado): dos columnas tenant.
 *
 * - `{prefix}articulos.canje_opcionales`: cómo juegan los opcionales con
 *   precio cuando el artículo se canjea por puntos (POS y tienda).
 *   'incluidos' (default) = el canje los cubre (con costo derivado, suman
 *   al cálculo; con costo fijo, van de regalo); 'en_plata' = se cobran en
 *   pesos aparte del canje; 'en_puntos' = se convierten a puntos y se
 *   suman al costo del renglón.
 * - `{prefix}configuracion_puntos.restringir_canje_articulos`: con el
 *   switch prendido, TODO canje (por artículo y como pago) queda limitado
 *   a los artículos habilitados (`articulos_sucursales.canje_tienda`).
 *   Apagado (default) preserva el comportamiento histórico del POS.
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
                    ADD COLUMN `canje_opcionales` enum('incluidos','en_plata','en_puntos') NOT NULL DEFAULT 'incluidos'
                    COMMENT 'RF-T59: tratamiento de opcionales con precio al canjear por puntos'
                    AFTER `puntos_canje`
                ");
            } catch (\Exception $e) {
                // columna ya existente: seguir con la otra tabla igual
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}configuracion_puntos`
                    ADD COLUMN `restringir_canje_articulos` tinyint(1) NOT NULL DEFAULT 0
                    COMMENT 'RF-T58: canje (articulo y pago) limitado a articulos habilitados'
                    AFTER `redondeo`
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
                    ALTER TABLE `{$prefix}articulos` DROP COLUMN `canje_opcionales`
                ");
            } catch (\Exception $e) {
                // seguir
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}configuracion_puntos` DROP COLUMN `restringir_canje_articulos`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
