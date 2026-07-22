<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aclaración del cliente POR ÍTEM del pedido de la tienda online (ronda 3f:
 * "sin pepino", "bien cocida"). La carga el consumidor en el detalle del
 * artículo y el panel la muestra junto al renglón del pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement(
                    "ALTER TABLE `{$prefix}pedidos_delivery_detalle`
                     ADD COLUMN `observaciones` VARCHAR(255) NULL DEFAULT NULL
                     COMMENT 'Aclaracion del cliente para este item (tienda online)'
                     AFTER `precio_unitario_original`"
                );
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
                DB::connection('pymes')->statement(
                    "ALTER TABLE `{$prefix}pedidos_delivery_detalle` DROP COLUMN `observaciones`"
                );
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
