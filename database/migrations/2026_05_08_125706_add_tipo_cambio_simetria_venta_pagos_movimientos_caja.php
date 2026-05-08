<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repaso 1 — PR D — Trazabilidad completa de cotización en pagos en moneda extranjera.
 *
 * Cierra la simetría snapshot id+tasa en ambas tablas que registran conversión:
 *
 *   - `venta_pagos`: ya tiene `tipo_cambio_tasa` (valor) → AGREGA `tipo_cambio_id` (FK lógico).
 *   - `movimientos_caja`: ya tiene `tipo_cambio_id` (FK lógico) → AGREGA `tipo_cambio_tasa` (valor).
 *
 * Por qué snapshot completo (id + tasa):
 *   - El valor (`tasa`) es inmutable: aunque el record `tipos_cambio` se edite/borre, el
 *     pago conserva la cotización exacta usada al cobrar (precisión histórica).
 *   - El id permite trazabilidad (qué usuario cargó la cotización, fecha, contexto).
 *   - Reportes por cotización son joins simples por id; reportes históricos usan tasa.
 *
 * Nota: `tipo_cambio_id` NO lleva FK declarada porque la convención multi-tenant del
 * proyecto evita constraints cross-tabla — el nombre real es `{NNNNNN}_tipos_cambio` y la
 * FK requeriría incluir el prefijo. Se mantiene sólo el índice.
 *
 * Idempotente: cada ALTER verifica si la columna ya existe antes de agregarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // ── 1. ALTER venta_pagos: agregar tipo_cambio_id ──
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}venta_pagos'
                    AND COLUMN_NAME = 'tipo_cambio_id'
                ");
                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}venta_pagos`
                        ADD COLUMN `tipo_cambio_id` bigint(20) unsigned DEFAULT NULL
                        COMMENT 'FK logico a tipos_cambio.id (sin constraint por convencion multi-tenant). Snapshot del registro de cotizacion usado'
                        AFTER `tipo_cambio_tasa`
                    ");

                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}venta_pagos`
                        ADD INDEX `idx_vp_tipo_cambio` (`tipo_cambio_id`)
                    ");
                }

                // ── 2. ALTER movimientos_caja: agregar tipo_cambio_tasa ──
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}movimientos_caja'
                    AND COLUMN_NAME = 'tipo_cambio_tasa'
                ");
                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}movimientos_caja`
                        ADD COLUMN `tipo_cambio_tasa` decimal(14,6) DEFAULT NULL
                        COMMENT 'Snapshot del valor de la tasa al momento del movimiento. Inmutable aunque se edite el record tipos_cambio'
                        AFTER `tipo_cambio_id`
                    ");
                }
            } catch (\Exception $e) {
                // Comercio con BD inexistente o tablas faltantes: continuar con el siguiente.
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
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}venta_pagos'
                    AND COLUMN_NAME = 'tipo_cambio_id'
                ");
                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}venta_pagos` DROP INDEX `idx_vp_tipo_cambio`");
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}venta_pagos` DROP COLUMN `tipo_cambio_id`");
                }

                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$prefix}movimientos_caja'
                    AND COLUMN_NAME = 'tipo_cambio_tasa'
                ");
                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}movimientos_caja` DROP COLUMN `tipo_cambio_tasa`");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
