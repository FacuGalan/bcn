<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Multi-PWA Clase B — Fase 3: toggle `usa_consultor_precios` en sucursales (tenant).
 *
 * Gatea el endpoint público de búsqueda de precios: si está en false, la pantalla
 * consultor de precios no devuelve datos (los precios no quedan consultables salvo
 * que el comercio active explícitamente la pantalla). Mismo criterio de control que
 * `usa_llamador`.
 *
 * Itera todos los comercios con SQL raw + prefijo + try/catch por comercio. Sin
 * backfill (default 0 alcanza). Regenerar database/sql/tenant_tables.sql tras esta
 * migración.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-05, RF-08).
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
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `usa_consultor_precios` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si usa la pantalla consultor de precios (Clase B)' AFTER `usa_llamador`
                ");
            } catch (\Exception $e) {
                // columna ya presente u otra inconsistencia → no abortar el resto
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
                    ALTER TABLE `{$prefix}sucursales`
                    DROP COLUMN `usa_consultor_precios`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
