<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-A1 del spec hardening-circuito-precios: el precio de venta es SIEMPRE
 * final con IVA incluido. Migración de DATOS (no schema): fuerza
 * precio_iva_incluido=1 en los artículos que tuvieran el modo "neto".
 * La columna queda deprecada (default 1, la UI ya no la ofrece); no se
 * elimina para no romper reads existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement(
                    "UPDATE `{$prefix}articulos` SET precio_iva_incluido = 1 WHERE precio_iva_incluido = 0"
                );
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no se sabe qué artículos eran "neto" y el
        // sistema ya no soporta esa semántica (el precio siempre es final).
    }
};
