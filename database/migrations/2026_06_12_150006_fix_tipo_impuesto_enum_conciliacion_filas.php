<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FIX de 2026_06_12_140001 (ya mergeada): esa migración alteraba
 * `conciliaciones_filas` pero la tabla real se llama `conciliacion_filas`
 * (singular) → el try/catch por comercio se tragó el error y el enum `tipo`
 * quedó SIN el valor 'impuesto' en los comercios existentes (los tests no lo
 * detectaron porque recrean las tablas desde tenant_tables.sql, que sí está
 * correcto). Sin este fix, una conciliación con filas de impuestos falla el
 * insert y la corrida queda clavada en `generando`.
 *
 * Idempotente: MODIFY al mismo enum es no-op si ya está aplicado.
 */
return new class extends Migration
{
    private const ENUM_CON_IMPUESTO = "enum('cobro','comision','impuesto','devolucion','contracargo','retiro','retiro_cancelado','acreditacion','ajuste_inicial','otro')";

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}conciliacion_filas`
                    MODIFY `tipo` ".self::ENUM_CON_IMPUESTO." COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo normalizado provider-agnostic'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        // No se revierte: el enum ampliado es el estado correcto (la reversión
        // real vive en el down() de 2026_06_12_140001).
    }
};
