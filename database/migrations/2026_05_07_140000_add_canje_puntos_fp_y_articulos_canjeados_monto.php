<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repaso 1 — Trazabilidad de canje de puntos.
 *
 * Esta migración hace 4 cosas en cada comercio:
 *
 *  1. ALTER `formas_pago`: agrega columna `solo_sistema` (boolean default false).
 *     Patrón "FP solo sistema": formas de pago que el sistema usa internamente
 *     pero NO debe mostrar en el selector de cobro al cajero.
 *
 *  2. ALTER `ventas`: agrega columna `articulos_canjeados_monto` (decimal default 0).
 *     Resumen de cabecera del monto en pesos correspondiente a artículos pagados
 *     con puntos. Coherente con `puntos_usados_monto` ya existente (PR #58).
 *
 *  3. INSERT en `conceptos_pago`: nuevo concepto `canje_puntos` para distinguir
 *     en reportes por concepto los pagos hechos con puntos.
 *
 *  4. INSERT en `formas_pago`: nueva FP "Canje Puntos" con `solo_sistema=true`
 *     y vinculada al concepto recién creado.
 *
 * Idempotente: cada operación verifica si ya existe antes de insertar/alterar.
 *
 * Nuevos comercios: el provisioning (`comercio:provision`) replica este patrón
 * en `ProvisionComercioCommand` — verlo allí para mantener sincronizado.
 *
 * Para futuras formas de pago internas similares, usar la skill
 * `/concepto-pago-interno` que automatiza este patrón.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // ── 1. ALTER formas_pago: agregar solo_sistema ──
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}formas_pago'
                    AND COLUMN_NAME = 'solo_sistema'
                ");
                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}formas_pago`
                        ADD COLUMN `solo_sistema` tinyint(1) NOT NULL DEFAULT '0'
                        COMMENT 'Si true, esta forma de pago la usa solo el sistema (ej: Canje Puntos) y NO aparece en el selector del cajero'
                        AFTER `activo`
                    ");
                }

                // ── 2. ALTER ventas: agregar articulos_canjeados_monto ──
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}ventas'
                    AND COLUMN_NAME = 'articulos_canjeados_monto'
                ");
                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}ventas`
                        ADD COLUMN `articulos_canjeados_monto` decimal(12,2) NOT NULL DEFAULT '0.00'
                        COMMENT 'Monto en pesos de artículos pagados directamente con puntos (canje de artículo). Coherente con puntos_usados_monto.'
                        AFTER `puntos_usados_monto`
                    ");
                }

                // ── 3. Concepto canje_puntos ──
                $existeConcepto = DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', 'canje_puntos')
                    ->exists();
                if (! $existeConcepto) {
                    $maxOrden = DB::connection('pymes')->table("{$prefix}conceptos_pago")->max('orden') ?? 0;
                    DB::connection('pymes')->table("{$prefix}conceptos_pago")->insert([
                        'codigo' => 'canje_puntos',
                        'nombre' => 'Canje de Puntos',
                        'descripcion' => 'Pago con puntos del programa de fidelización (canje monto o canje de artículo)',
                        'permite_cuotas' => false,
                        'permite_vuelto' => false,
                        'activo' => true,
                        'orden' => $maxOrden + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $conceptoId = DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', 'canje_puntos')
                    ->value('id');

                // ── 4. Forma de pago "Canje Puntos" (solo_sistema=true) ──
                $existeFp = DB::connection('pymes')->table("{$prefix}formas_pago")
                    ->where('codigo', 'CANJE_PUNTOS')
                    ->exists();
                if (! $existeFp && $conceptoId) {
                    $maxOrden = DB::connection('pymes')->table("{$prefix}formas_pago")->max('orden') ?? 0;
                    DB::connection('pymes')->table("{$prefix}formas_pago")->insert([
                        'nombre' => 'Canje Puntos',
                        'codigo' => 'CANJE_PUNTOS',
                        'concepto_pago_id' => $conceptoId,
                        // El campo `concepto` es un ENUM legacy con valores fijos.
                        // Los nuevos casos usamos 'otro' aquí; la trazabilidad real
                        // va por concepto_pago_id (FK a conceptos_pago).
                        'concepto' => 'otro',
                        'permite_cuotas' => false,
                        'es_mixta' => false,
                        'activo' => true,
                        'solo_sistema' => true,
                        'orden' => $maxOrden + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning("Migración canje_puntos_fp falló para comercio {$comercio->id}: ".$e->getMessage());

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
                // Borrar FP CANJE_PUNTOS
                DB::connection('pymes')->table("{$prefix}formas_pago")
                    ->where('codigo', 'CANJE_PUNTOS')
                    ->delete();

                // Borrar concepto canje_puntos
                DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', 'canje_puntos')
                    ->delete();

                // Drop columnas
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}ventas'
                    AND COLUMN_NAME = 'articulos_canjeados_monto'
                ");
                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}ventas` DROP COLUMN `articulos_canjeados_monto`");
                }

                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}formas_pago'
                    AND COLUMN_NAME = 'solo_sistema'
                ");
                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}formas_pago` DROP COLUMN `solo_sistema`");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
