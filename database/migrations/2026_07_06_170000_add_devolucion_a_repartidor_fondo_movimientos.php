<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — devolución parcial del fondo del repartidor (vuelta).
 *
 * Nuevo tipo de movimiento `devolucion`: en la vuelta el repartidor puede
 * devolver PARTE del efectivo a caja sin cerrar el fondo (ciclo largo, D4).
 * `rendicion` queda reservado al cierre definitivo del fondo.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-08/RF-09, D13).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}repartidor_fondo_movimientos`
                    MODIFY COLUMN `tipo` enum('entrega_inicial','refuerzo','cobro_pedido','vuelto','liquidacion_envios','devolucion','rendicion','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL
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
                    ALTER TABLE `{$prefix}repartidor_fondo_movimientos`
                    MODIFY COLUMN `tipo` enum('entrega_inicial','refuerzo','cobro_pedido','vuelto','liquidacion_envios','rendicion','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
