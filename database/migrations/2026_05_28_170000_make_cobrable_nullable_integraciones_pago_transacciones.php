<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5 (integraciones de pago): hace NULLABLE el cobrable polimórfico de
 * `integraciones_pago_transacciones`.
 *
 * En el cobro por QR dinámico la transacción se crea ANTES que la venta: el
 * cajero genera el QR y espera el pago; la venta se materializa (y se asocia
 * a la transacción) recién cuando el pago se confirma. Hasta entonces la
 * transacción no tiene cobrable.
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
                    ALTER TABLE `{$prefix}integraciones_pago_transacciones`
                    MODIFY COLUMN `cobrable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'FQCN del cobrable: App\\\\Models\\\\Venta, App\\\\Models\\\\PedidoMostrador, ...',
                    MODIFY COLUMN `cobrable_id` bigint unsigned DEFAULT NULL
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
                    ALTER TABLE `{$prefix}integraciones_pago_transacciones`
                    MODIFY COLUMN `cobrable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FQCN del cobrable: App\\\\Models\\\\Venta, App\\\\Models\\\\PedidoMostrador, ...',
                    MODIFY COLUMN `cobrable_id` bigint unsigned NOT NULL
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
