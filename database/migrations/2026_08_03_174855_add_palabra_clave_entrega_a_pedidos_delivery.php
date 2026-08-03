<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T64: palabra clave de entrega.
 *
 * Columna `{prefix}pedidos_delivery.palabra_clave_entrega`: palabra simple
 * generada al CONFIRMAR un pedido delivery cuando la sucursal tiene activado
 * `usar_palabra_clave` (config delivery). El consumidor la ve en el
 * seguimiento cuando hay repartidor asignado y la dice al recibir el pedido;
 * el panel la muestra junto al repartidor. NULL = sucursal sin el feature o
 * pedido anterior a el.
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
                    ALTER TABLE `{$prefix}pedidos_delivery`
                    ADD COLUMN `palabra_clave_entrega` varchar(30) DEFAULT NULL
                    COMMENT 'Palabra clave que el consumidor dice al recibir (RF-T64). Generada al confirmar si la sucursal usa el feature'
                    AFTER `no_entregado_motivo`
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
                    ALTER TABLE `{$prefix}pedidos_delivery` DROP COLUMN `palabra_clave_entrega`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
