<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega `imagen_path` (nullable) a `articulos` para el upload de foto del
 * artículo (RF-16 / PR2.E). El path guarda la ruta relativa al disk public
 * (ej. `articulos/{comercio_id}/{uuid}.webp`). Servido vía Storage::url().
 *
 * Sin backfill: artículos existentes quedan con imagen NULL, lo que el
 * panel táctil ya soporta mostrando el ícono de la categoría como fallback.
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
                    ADD COLUMN `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `pesable`
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
                    DROP COLUMN `imagen_path`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
