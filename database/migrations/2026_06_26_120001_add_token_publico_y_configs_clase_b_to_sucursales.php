<?php

use App\Models\PantallaPublicaToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Multi-PWA Clase B — Fase 1: token_publico + configs de personalización en
 * sucursales (tenant), con backfill a la columna tenant Y al índice global
 * config (pantalla_publica_tokens).
 *
 * - token_publico: copia del token largo para la UI/config (la resolución sin
 *   sesión usa el índice global).
 * - config_llamador / config_consultor_precios: personalización por pantalla
 *   (título, logo, colores, sonido), patrón config_pantalla_cliente.
 *
 * Itera todos los comercios con SQL raw + prefijo + try/catch por comercio.
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-01, Migraciones Necesarias).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            // 1. Columnas nuevas (idempotente: si ya existen, el catch deja seguir
            //    con el backfill).
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `token_publico` varchar(40) DEFAULT NULL AFTER `config_pantalla_cliente`,
                    ADD COLUMN `config_llamador` json DEFAULT NULL AFTER `token_publico`,
                    ADD COLUMN `config_consultor_precios` json DEFAULT NULL AFTER `config_llamador`,
                    ADD UNIQUE KEY `sucursales_token_publico_unique` (`token_publico`)
                ");
            } catch (\Exception $e) {
                // columnas ya presentes u otra inconsistencia → no abortar el backfill
            }

            // 2. Backfill: token + código corto por sucursal existente, tanto en la
            //    columna tenant como en el índice global config.
            try {
                $sucursales = DB::connection('pymes')->table("{$prefix}sucursales")->get();

                foreach ($sucursales as $sucursal) {
                    $yaIndexada = PantallaPublicaToken::query()
                        ->where('comercio_id', $comercio->id)
                        ->where('sucursal_id', $sucursal->id)
                        ->exists();

                    if ($yaIndexada) {
                        continue;
                    }

                    $token = PantallaPublicaToken::generarTokenUnico();
                    $codigo = PantallaPublicaToken::generarCodigoUnico();

                    DB::connection('pymes')->table("{$prefix}sucursales")
                        ->where('id', $sucursal->id)
                        ->update(['token_publico' => $token]);

                    PantallaPublicaToken::create([
                        'token' => $token,
                        'codigo_corto' => $codigo,
                        'comercio_id' => $comercio->id,
                        'sucursal_id' => $sucursal->id,
                    ]);
                }
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

            // Limpiar el índice global de este comercio.
            try {
                PantallaPublicaToken::query()->where('comercio_id', $comercio->id)->delete();
            } catch (\Exception $e) {
                // tabla config inexistente → ignorar
            }

            // Dropear columnas (la unique key se va con la columna).
            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}sucursales`
                    DROP COLUMN `token_publico`,
                    DROP COLUMN `config_llamador`,
                    DROP COLUMN `config_consultor_precios`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
