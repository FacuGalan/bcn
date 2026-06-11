<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — QR monto-libre (qr_libre).
 *
 * Agrega el modo `qr_libre` al catálogo de la integración `mercadopago_qr`
 * (columna JSON `modos_disponibles`). Es un MODO más del producto QR (no una
 * integración separada): el sistema NO empuja monto a MP, muestra una imagen de
 * QR "Cobrar" subida por el comercio y el cajero confirma manualmente.
 *
 * Idempotente: solo agrega `qr_libre` si todavía no está. Si la fila
 * `mercadopago_qr` no existe en un comercio, lo saltea.
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
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}integraciones_pago`
                    SET `modos_disponibles` = JSON_ARRAY_APPEND(`modos_disponibles`, '$', 'qr_libre')
                    WHERE `codigo` = 'mercadopago_qr'
                      AND NOT JSON_CONTAINS(`modos_disponibles`, '\"qr_libre\"')
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
                    UPDATE `{$prefix}integraciones_pago`
                    SET `modos_disponibles` = JSON_REMOVE(
                        `modos_disponibles`,
                        JSON_UNQUOTE(JSON_SEARCH(`modos_disponibles`, 'one', 'qr_libre'))
                    )
                    WHERE `codigo` = 'mercadopago_qr'
                      AND JSON_CONTAINS(`modos_disponibles`, '\"qr_libre\"')
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
