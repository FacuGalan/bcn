<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repaso 2 — PR E — Trazabilidad de canje de puntos en cierres de turno.
 *
 * Las FPs con `solo_sistema=true` (ej: "Canje Puntos") y los pagos con
 * `afecta_caja=false` se mezclaban hoy en `desglose_formas_pago` del cierre,
 * distorsionando reportes por FP. La suma del desglose dejaba de cuadrar con
 * `total_ingresos` cuando había canjes.
 *
 * Esta migración separa la trazabilidad sin tocar el monto cobrado real:
 *
 *   1. `cierre_turno_cajas.desglose_internos` JSON: pagos que NO afectan caja
 *      (canje puntos / canje artículos / FPs solo_sistema). Visible para el
 *      operador, pero no suma a `total_ingresos`.
 *
 *   2. `cierres_turno`: tres contadores snapshot de puntos del turno:
 *      - `total_puntos_canjeados_pago`: puntos consumidos como medio de pago.
 *      - `total_puntos_canjeados_articulos`: puntos consumidos en canje directo.
 *      - `total_puntos_acumulados`: puntos generados por las ventas del turno.
 *
 *      Aunque son derivables de las ventas del turno, persistirlos en cabecera
 *      preserva snapshot histórico (anulaciones posteriores no alteran el cierre).
 *
 *   3. `ventas`: separa los puntos consumidos por venta en dos columnas para
 *      que el cierre del turno los pueda sumar sin tener que distinguir tipos
 *      de VentaPago por matching de montos:
 *      - `puntos_canjeados_pago`: puntos usados como medio de pago en la venta.
 *      - `puntos_canjeados_articulos`: puntos usados en canje directo de articulos.
 *      `ventas.puntos_usados` (existente) sigue siendo el total = pago + articulos.
 *
 * Idempotente: cada operación verifica si la columna ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // ── 1. cierre_turno_cajas.desglose_internos ──
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}cierre_turno_cajas'
                    AND COLUMN_NAME = 'desglose_internos'
                ");
                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}cierre_turno_cajas`
                        ADD COLUMN `desglose_internos` text COLLATE utf8mb4_unicode_ci
                        COMMENT 'JSON con desglose de pagos que NO afectan caja (canje puntos, FPs solo_sistema). Trazabilidad sin sumar a total_ingresos'
                        AFTER `desglose_monedas`
                    ");
                }

                // ── 2. cierres_turno: 3 contadores de puntos ──
                $cols = ['total_puntos_canjeados_pago', 'total_puntos_canjeados_articulos', 'total_puntos_acumulados'];
                $comments = [
                    'Puntos consumidos como medio de pago en el turno (snapshot)',
                    'Puntos consumidos en canje directo de articulos en el turno (snapshot)',
                    'Puntos generados por ventas del turno (snapshot)',
                ];

                foreach ($cols as $i => $col) {
                    $exists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$prefix}cierres_turno'
                        AND COLUMN_NAME = '{$col}'
                    ");
                    if (empty($exists)) {
                        $afterCol = $i === 0 ? 'total_diferencia' : $cols[$i - 1];
                        DB::connection('pymes')->statement("
                            ALTER TABLE `{$prefix}cierres_turno`
                            ADD COLUMN `{$col}` int(10) unsigned NOT NULL DEFAULT 0
                            COMMENT '{$comments[$i]}'
                            AFTER `{$afterCol}`
                        ");
                    }
                }

                // ── 3. ventas: separar puntos por tipo de canje ──
                $ventasCols = ['puntos_canjeados_pago', 'puntos_canjeados_articulos'];
                $ventasComments = [
                    'Puntos usados como medio de pago en la venta',
                    'Puntos usados en canje directo de articulos en la venta',
                ];
                foreach ($ventasCols as $i => $col) {
                    $exists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$prefix}ventas'
                        AND COLUMN_NAME = '{$col}'
                    ");
                    if (empty($exists)) {
                        $afterCol = $i === 0 ? 'puntos_usados' : $ventasCols[$i - 1];
                        DB::connection('pymes')->statement("
                            ALTER TABLE `{$prefix}ventas`
                            ADD COLUMN `{$col}` int(10) unsigned NOT NULL DEFAULT 0
                            COMMENT '{$ventasComments[$i]}'
                            AFTER `{$afterCol}`
                        ");
                    }
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

            try {
                $col = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}cierre_turno_cajas'
                    AND COLUMN_NAME = 'desglose_internos'
                ");
                if (! empty($col)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}cierre_turno_cajas` DROP COLUMN `desglose_internos`");
                }

                foreach (['total_puntos_acumulados', 'total_puntos_canjeados_articulos', 'total_puntos_canjeados_pago'] as $c) {
                    $exists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$prefix}cierres_turno'
                        AND COLUMN_NAME = '{$c}'
                    ");
                    if (! empty($exists)) {
                        DB::connection('pymes')->statement("ALTER TABLE `{$prefix}cierres_turno` DROP COLUMN `{$c}`");
                    }
                }

                foreach (['puntos_canjeados_articulos', 'puntos_canjeados_pago'] as $c) {
                    $exists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$prefix}ventas'
                        AND COLUMN_NAME = '{$c}'
                    ");
                    if (! empty($exists)) {
                        DB::connection('pymes')->statement("ALTER TABLE `{$prefix}ventas` DROP COLUMN `{$c}`");
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
