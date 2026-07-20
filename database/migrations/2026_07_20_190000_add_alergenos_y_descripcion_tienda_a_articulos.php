<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T14 (ampliación 2026-07-20): más config de tienda por artículo.
 *
 * 1. `alergenos_tienda` JSON: lista libre de alérgenos (["soja","huevos"]).
 *    La tienda muestra el aviso "Contiene: ..." en el detalle del artículo.
 * 2. `descripcion_tienda` TEXT: descripción específica para la tienda.
 *    Vacía ⇒ el catálogo sirve la descripción operativa del artículo.
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
                    ADD COLUMN `alergenos_tienda` json DEFAULT NULL
                    COMMENT 'Alergenos del articulo para el aviso de la tienda (RF-T14)'
                    AFTER `badges_tienda`,
                    ADD COLUMN `descripcion_tienda` text DEFAULT NULL
                    COMMENT 'Descripcion especifica para la tienda, vacia usa la operativa (RF-T14)'
                    AFTER `alergenos_tienda`
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
                    DROP COLUMN `alergenos_tienda`,
                    DROP COLUMN `descripcion_tienda`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
