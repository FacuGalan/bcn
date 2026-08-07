<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 2, RF-T82): estado `devuelto`
 * en el ciclo de vida de la transacción.
 *
 * Al rechazar/cancelar un pedido pagado online el core ejecuta el refund
 * total contra MP; si MP lo acepta, la transacción pasa de `confirmado` a
 * `devuelto` (evento append-only + contraasiento en CuentaEmpresa). Una tx
 * `confirmado` cuyo pedido fue cancelado y SIN refund exitoso queda "a
 * devolver" (badge en panel + reintento manual).
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (RF-T82).
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
                    MODIFY COLUMN `estado` enum('pendiente','confirmado','confirmado_manual','fallido','expirado','cancelado','sin_match','devuelto')
                        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente'
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
                    MODIFY COLUMN `estado` enum('pendiente','confirmado','confirmado_manual','fallido','expirado','cancelado','sin_match')
                        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
