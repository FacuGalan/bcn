<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // Agregar columna orden
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}formas_pago`
                    ADD COLUMN `orden` int unsigned NOT NULL DEFAULT 0 COMMENT 'Orden de visualización (menor = primero)'
                    AFTER `activo`
                ");

                // Asignar orden por defecto basado en el ID actual
                DB::connection('pymes')->statement('SET @row_number = 0');
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}formas_pago`
                    SET `orden` = (@row_number := @row_number + 1)
                    ORDER BY `id`
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
                    ALTER TABLE `{$prefix}formas_pago`
                    DROP COLUMN `orden`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
