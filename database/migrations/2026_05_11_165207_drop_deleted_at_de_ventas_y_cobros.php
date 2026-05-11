<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina SoftDeletes muerto de `ventas` y `cobros`.
 *
 * Ambas tablas tenían el trait + columna `deleted_at`, pero el codebase nunca
 * llama `->delete()` ni `withTrashed()/onlyTrashed()/trashed()` sobre Venta/Cobro.
 * El patrón vivo es `estado='cancelada'/'anulado'` + auditoría
 * (anulado_at, anulado_por_usuario_id, motivo_anulacion).
 *
 * Si en el futuro el negocio define "eliminar venta" distinto de "anular",
 * el rollback es trivial (esta misma migración invertida).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            // ventas: drop indice + columna
            $this->dropDeletedAtSiExiste("{$prefix}ventas", 'idx_ventas_deleted', $comercio->id);

            // cobros: solo columna (sin indice)
            $this->dropDeletedAtSiExiste("{$prefix}cobros", null, $comercio->id);
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            $this->addDeletedAtSiNoExiste("{$prefix}ventas", 'idx_ventas_deleted', $comercio->id);
            $this->addDeletedAtSiNoExiste("{$prefix}cobros", null, $comercio->id);
        }
    }

    private function dropDeletedAtSiExiste(string $tabla, ?string $indice, int $comercioId): void
    {
        try {
            $col = DB::connection('pymes')->select("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tabla}'
                AND COLUMN_NAME = 'deleted_at'
            ");

            if (empty($col)) {
                return;
            }

            if ($indice) {
                $idxExists = DB::connection('pymes')->select("
                    SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND INDEX_NAME = '{$indice}'
                ");
                if (! empty($idxExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` DROP INDEX `{$indice}`");
                }
            }

            DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` DROP COLUMN `deleted_at`");
        } catch (\Exception $e) {
            \Log::warning("Migración drop_deleted_at ({$tabla}) falló para comercio {$comercioId}: ".$e->getMessage());
        }
    }

    private function addDeletedAtSiNoExiste(string $tabla, ?string $indice, int $comercioId): void
    {
        try {
            $col = DB::connection('pymes')->select("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tabla}'
                AND COLUMN_NAME = 'deleted_at'
            ");

            if (! empty($col)) {
                return;
            }

            DB::connection('pymes')->statement("
                ALTER TABLE `{$tabla}` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
            ");

            if ($indice) {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tabla}` ADD INDEX `{$indice}` (`deleted_at`)
                ");
            }
        } catch (\Exception $e) {
            \Log::warning("Migración drop_deleted_at down ({$tabla}) falló para comercio {$comercioId}: ".$e->getMessage());
        }
    }
};
