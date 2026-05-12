<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR2.B.1 (Pedidos por Mostrador): amplia el enum `estado` de
 * `pedidos_mostrador_pagos` para incluir 'planificado'.
 *
 * Semántica:
 * - planificado: pago registrado con monto y forma definidos, sin ejecutar
 *   cobro. NO crea MovimientoCaja. Permite al pedido pre-cargar el desglose
 *   antes de cobrar (mesero arma, cliente paga al irse; tótem auto-genera
 *   plan; etc).
 * - activo: pago efectivamente cobrado, con MovimientoCaja asociado.
 * - anulado: contraasiento aplicado.
 *
 * Transiciones permitidas:
 * - planificado -> activo (vía confirmarPagoPlanificado en el service)
 * - planificado -> DELETE directo (eliminarPagoPlanificado)
 * - activo -> anulado (vía anularPago, ya existente)
 *
 * El estado_pago del pedido se calcula SOLO sobre activos. Los planificados
 * no cuentan como cobrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}pedidos_mostrador_pagos";

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tabla}`
                    MODIFY COLUMN `estado` ENUM('activo','anulado','planificado')
                    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo'
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
            $tabla = "{$prefix}pedidos_mostrador_pagos";

            try {
                // Cualquier fila en estado planificado pasa a activo para no
                // perderla al estrechar el ENUM. Esto es defensivo — el down
                // típicamente solo se ejecuta sobre fixtures de test sin datos.
                DB::connection('pymes')->table($tabla)
                    ->where('estado', 'planificado')
                    ->update(['estado' => 'activo']);

                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tabla}`
                    MODIFY COLUMN `estado` ENUM('activo','anulado')
                    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
