<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paridad de persistencia Pedido <-> Venta (spec: pedidos-mostrador-paridad-venta).
 *
 * Agrega a `pedidos_mostrador` las 4 columnas de canje/uso de puntos que ya
 * existen en `ventas`, con tipo, default y orden identicos para que la
 * conversion Pedido -> Venta sea un mapeo trivial.
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
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    ADD COLUMN `puntos_canjeados_pago` int(10) unsigned NOT NULL DEFAULT '0' AFTER `puntos_usados`,
                    ADD COLUMN `puntos_canjeados_articulos` int(10) unsigned NOT NULL DEFAULT '0' AFTER `puntos_canjeados_pago`,
                    ADD COLUMN `puntos_usados_monto` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `puntos_canjeados_articulos`,
                    ADD COLUMN `articulos_canjeados_monto` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `puntos_usados_monto`
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
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    DROP COLUMN `articulos_canjeados_monto`,
                    DROP COLUMN `puntos_usados_monto`,
                    DROP COLUMN `puntos_canjeados_articulos`,
                    DROP COLUMN `puntos_canjeados_pago`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
