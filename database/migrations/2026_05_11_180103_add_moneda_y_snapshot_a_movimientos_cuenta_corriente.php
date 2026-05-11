<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR L del Repaso 3: agrega snapshot id+tasa de moneda a movimientos_cuenta_corriente.
 *
 * Hueco previo: cuando un cliente con CC pagaba/se le aplicaba un cobro en moneda
 * extranjera (USD), el ledger de cuenta corriente solo guardaba el monto convertido
 * a ARS. Se perdía la trazabilidad de la moneda original — imposible reconstruir
 * el histórico del cliente en USD si la cotización cambia.
 *
 * Patrón snapshot id+tasa establecido en PR #62 (VentaPago + MovimientoCaja).
 * FK lógico (sin constraint) para tipo_cambio_id por consistencia con esos modelos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}movimientos_cuenta_corriente";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'moneda_id'
                ");

                if (empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `moneda_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK logico a monedas. NULL = moneda principal implicita',
                        ADD COLUMN `tipo_cambio_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id (sin constraint). Snapshot del registro de cotizacion usado',
                        ADD COLUMN `tipo_cambio_tasa` DECIMAL(14,6) DEFAULT NULL COMMENT 'Snapshot inmutable del valor de la tasa al momento del movimiento',
                        ADD COLUMN `monto_moneda_original` DECIMAL(14,2) DEFAULT NULL COMMENT 'Monto expresado en la moneda original (antes de convertir a moneda principal)',
                        ADD INDEX `idx_mcc_tipo_cambio` (`tipo_cambio_id`)
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración add_moneda_y_snapshot_mcc falló para comercio {$comercio->id}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}movimientos_cuenta_corriente";

            try {
                $colExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'moneda_id'
                ");

                if (! empty($colExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        DROP INDEX `idx_mcc_tipo_cambio`,
                        DROP COLUMN `moneda_id`,
                        DROP COLUMN `tipo_cambio_id`,
                        DROP COLUMN `tipo_cambio_tasa`,
                        DROP COLUMN `monto_moneda_original`
                    ");
                }
            } catch (\Exception $e) {
                // continuar
            }
        }
    }
};
