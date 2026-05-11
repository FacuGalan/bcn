<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR M del Repaso 3: completa el patrón snapshot id+tasa en los 3 modelos
 * que tenían huecos parciales (auditoría de moneda al cierre sesión 4).
 *
 * - cobro_pagos: tenía tasa pero faltaba tipo_cambio_id (asimetria con VentaPago).
 * - movimientos_tesoreria: faltaba id y tasa.
 * - provision_fondos: faltaba id y tasa.
 *
 * Patrón snapshot id+tasa establecido en PR #62 (VentaPago + MovimientoCaja).
 * FK lógico tipo_cambio_id (sin constraint) para consistencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            // cobro_pagos: solo tipo_cambio_id (ya tiene tasa)
            $this->agregarColumnas(
                "{$prefix}cobro_pagos",
                [
                    'tipo_cambio_id' => "BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id (sin constraint). Snapshot del registro de cotizacion usado'",
                ],
                'idx_cp_tipo_cambio',
                $comercio->id
            );

            // movimientos_tesoreria: id + tasa
            $this->agregarColumnas(
                "{$prefix}movimientos_tesoreria",
                [
                    'tipo_cambio_id' => "BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id (sin constraint). Snapshot del registro de cotizacion usado'",
                    'tipo_cambio_tasa' => "DECIMAL(14,6) DEFAULT NULL COMMENT 'Snapshot inmutable del valor de la tasa al momento del movimiento'",
                ],
                'idx_mt_tipo_cambio',
                $comercio->id
            );

            // provision_fondos: id + tasa
            $this->agregarColumnas(
                "{$prefix}provision_fondos",
                [
                    'tipo_cambio_id' => "BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id (sin constraint). Snapshot del registro de cotizacion usado'",
                    'tipo_cambio_tasa' => "DECIMAL(14,6) DEFAULT NULL COMMENT 'Snapshot inmutable del valor de la tasa al momento de la provision'",
                ],
                'idx_pf_tipo_cambio',
                $comercio->id
            );
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            $this->eliminarColumnas("{$prefix}cobro_pagos", ['tipo_cambio_id'], 'idx_cp_tipo_cambio', $comercio->id);
            $this->eliminarColumnas("{$prefix}movimientos_tesoreria", ['tipo_cambio_id', 'tipo_cambio_tasa'], 'idx_mt_tipo_cambio', $comercio->id);
            $this->eliminarColumnas("{$prefix}provision_fondos", ['tipo_cambio_id', 'tipo_cambio_tasa'], 'idx_pf_tipo_cambio', $comercio->id);
        }
    }

    /**
     * Agrega columnas + índice sobre tipo_cambio_id si no existen.
     */
    private function agregarColumnas(string $tabla, array $columnas, string $indice, int $comercioId): void
    {
        try {
            $firstCol = array_key_first($columnas);
            $colExists = DB::connection('pymes')->select("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tabla}'
                AND COLUMN_NAME = '{$firstCol}'
            ");

            if (! empty($colExists)) {
                return;
            }

            $clauses = [];
            foreach ($columnas as $nombre => $definicion) {
                $clauses[] = "ADD COLUMN `{$nombre}` {$definicion}";
            }
            $clauses[] = "ADD INDEX `{$indice}` (`tipo_cambio_id`)";

            DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` ".implode(', ', $clauses));
        } catch (\Exception $e) {
            \Log::warning("Migración completar_snapshot_id_tasa ({$tabla}) falló para comercio {$comercioId}: ".$e->getMessage());
        }
    }

    /**
     * Elimina columnas + índice si existen.
     */
    private function eliminarColumnas(string $tabla, array $columnas, string $indice, int $comercioId): void
    {
        try {
            $firstCol = $columnas[0];
            $colExists = DB::connection('pymes')->select("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tabla}'
                AND COLUMN_NAME = '{$firstCol}'
            ");

            if (empty($colExists)) {
                return;
            }

            $idxExists = DB::connection('pymes')->select("
                SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tabla}'
                AND INDEX_NAME = '{$indice}'
            ");

            $clauses = [];
            if (! empty($idxExists)) {
                $clauses[] = "DROP INDEX `{$indice}`";
            }
            foreach ($columnas as $col) {
                $clauses[] = "DROP COLUMN `{$col}`";
            }

            DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` ".implode(', ', $clauses));
        } catch (\Exception $e) {
            // continuar
        }
    }
};
