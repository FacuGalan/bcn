<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — QR monto-libre (qr_libre).
 *
 * Agrega la columna `config_qr_libre` (JSON) al pivote tenant
 * `forma_pago_integraciones`. Guarda la config específica del modo qr_libre por
 * FormaPago (p.ej. ruta/URL de la imagen del QR "Cobrar" subida por el comercio),
 * análogo a como `config_point` guarda la config de Point.
 *
 * En master el pivote aún NO tiene `config_point` (lo agrega Point/#128), por eso
 * la columna se ubica AFTER `es_principal`.
 *
 * Idempotente: verifica que la columna no exista antes de agregarla.
 *
 * Ref: .claude/specs/integraciones-pago-qr-monto-libre.md (Fase 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $existe = DB::connection('pymes')->selectOne("
                    SELECT COUNT(*) AS c
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = '{$prefix}forma_pago_integraciones'
                      AND COLUMN_NAME = 'config_qr_libre'
                ");

                if ($existe && (int) $existe->c > 0) {
                    continue;
                }

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}forma_pago_integraciones`
                    ADD COLUMN `config_qr_libre` json DEFAULT NULL
                    COMMENT 'Config del modo qr_libre por FormaPago (ej: imagen del QR Cobrar subida)'
                    AFTER `es_principal`
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
                $existe = DB::connection('pymes')->selectOne("
                    SELECT COUNT(*) AS c
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = '{$prefix}forma_pago_integraciones'
                      AND COLUMN_NAME = 'config_qr_libre'
                ");

                if (! $existe || (int) $existe->c === 0) {
                    continue;
                }

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}forma_pago_integraciones`
                    DROP COLUMN `config_qr_libre`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
