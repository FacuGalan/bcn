<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T36 (ronda crossmap 2): badges de tienda POR CATEGORÍA.
 *
 * Columna `{prefix}categorias.badges_tienda` JSON: mismo shape que el de
 * artículos (RF-T14) — [{"tipo":"sin_tacc"},{"tipo":"custom","texto":"..."}],
 * máx 4, catálogo de tipos en Articulo::BADGES_TIENDA, saneador en
 * Categoria::badgesTienda(). La tienda los muestra junto al título del grupo.
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
                    ADD COLUMN `badges_tienda` json DEFAULT NULL
                    COMMENT 'Badges de la tienda (RF-T36): tipos predefinidos + custom'
                    AFTER `imagen_path`
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
                    ALTER TABLE `{$prefix}categorias` DROP COLUMN `badges_tienda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
