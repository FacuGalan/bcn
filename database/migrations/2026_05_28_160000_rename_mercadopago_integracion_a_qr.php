<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Fase 4: renombra la integración de catálogo
 * `mercadopago` a `mercadopago_qr`.
 *
 * Motivo: en Mercado Pago cada producto (QR, Point, Checkout) es una aplicación
 * con credenciales propias. El registro actual representa el producto QR Code,
 * así que su código pasa a ser explícito para dar lugar a `mercadopago_point`,
 * `mercadopago_checkout`, etc. en el futuro (cada uno fila de catálogo aparte).
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 4).
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
                    SET `codigo` = 'mercadopago_qr', `nombre` = 'Mercado Pago - QR'
                    WHERE `codigo` = 'mercadopago'
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
                    SET `codigo` = 'mercadopago', `nombre` = 'Mercado Pago'
                    WHERE `codigo` = 'mercadopago_qr'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
