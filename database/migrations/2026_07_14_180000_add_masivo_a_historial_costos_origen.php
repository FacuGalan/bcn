<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-C2 del spec hardening-circuito-precios: el cambio masivo extendido a
 * COSTOS registra historial_costos con origen 'masivo' — el ENUM de la
 * columna no lo incluía (el spec lo asumía string).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->alterarEnum("enum('compra','manual','importacion','cancelacion','masivo')");
    }

    public function down(): void
    {
        // Antes de achicar el ENUM, las filas 'masivo' caen a 'manual'.
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement(
                    "UPDATE `{$prefix}historial_costos` SET origen = 'manual' WHERE origen = 'masivo'"
                );
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->alterarEnum("enum('compra','manual','importacion','cancelacion')");
    }

    private function alterarEnum(string $definicion): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement(
                    "ALTER TABLE `{$prefix}historial_costos` MODIFY `origen` {$definicion} COLLATE utf8mb4_unicode_ci NOT NULL"
                );
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
