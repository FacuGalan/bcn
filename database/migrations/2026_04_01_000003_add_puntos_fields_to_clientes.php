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
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}clientes`
                    ADD COLUMN `programa_puntos_activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si participa del programa de puntos'
                    AFTER `dias_mora_max`,
                    ADD COLUMN `puntos_acumulados_cache` int unsigned NOT NULL DEFAULT 0 COMMENT 'Total histórico de puntos acumulados'
                    AFTER `programa_puntos_activo`,
                    ADD COLUMN `puntos_canjeados_cache` int unsigned NOT NULL DEFAULT 0 COMMENT 'Total histórico de puntos canjeados'
                    AFTER `puntos_acumulados_cache`,
                    ADD COLUMN `puntos_saldo_cache` int NOT NULL DEFAULT 0 COMMENT 'Saldo disponible actual de puntos'
                    AFTER `puntos_canjeados_cache`,
                    ADD COLUMN `ultimo_movimiento_puntos_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha del último movimiento de puntos'
                    AFTER `puntos_saldo_cache`
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
                    ALTER TABLE `{$prefix}clientes`
                    DROP COLUMN `programa_puntos_activo`,
                    DROP COLUMN `puntos_acumulados_cache`,
                    DROP COLUMN `puntos_canjeados_cache`,
                    DROP COLUMN `puntos_saldo_cache`,
                    DROP COLUMN `ultimo_movimiento_puntos_at`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
