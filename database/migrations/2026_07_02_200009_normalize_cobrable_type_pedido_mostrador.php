<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1 (D20, corrección de consistencia polimórfica).
 *
 * Al registrar 'PedidoMostrador' en el morphMap (AppServiceProvider),
 * getMorphClass() pasa de devolver el FQCN al alias corto. Las transacciones
 * de integración de pago viejas guardaron el FQCN en `cobrable_type`
 * (asociadas vía cobrable()->associate()): sin esta normalización, los
 * lookups por porCobrable() dejarían de matchear filas históricas (p.ej. el
 * guard "pedido con QR confirmado no puede cancelarse").
 *
 * `App\Models\Venta` NO se aliasa y conserva su FQCN — no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}integraciones_pago_transacciones`
                    SET `cobrable_type` = 'PedidoMostrador'
                    WHERE `cobrable_type` = 'App\\\\Models\\\\PedidoMostrador'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}integraciones_pago_transacciones`
                    SET `cobrable_type` = 'App\\\\Models\\\\PedidoMostrador'
                    WHERE `cobrable_type` = 'PedidoMostrador'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
