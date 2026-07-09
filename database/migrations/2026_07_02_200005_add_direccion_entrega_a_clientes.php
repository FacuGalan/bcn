<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: domicilio de ENTREGA del cliente (D6/D18).
 *
 * SEPARADO del domicilio fiscal: `clientes.direccion` alimenta el receptor de
 * ARCA, la impresión y el padrón — NUNCA se pisa con una dirección de entrega.
 * Estos campos se actualizan al confirmar un pedido delivery (salvo "entregar
 * en otra dirección", que solo queda en el snapshot del pedido) y precargan
 * el próximo pedido.
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-04, D6/D18).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}clientes`
                    ADD COLUMN `direccion_entrega` varchar(255) DEFAULT NULL COMMENT 'Domicilio de ENTREGA (delivery). NO es el fiscal (direccion)' AFTER `direccion`,
                    ADD COLUMN `direccion_entrega_referencia` varchar(255) DEFAULT NULL COMMENT 'Piso/depto/indicaciones de entrega' AFTER `direccion_entrega`,
                    ADD COLUMN `latitud` decimal(10,7) DEFAULT NULL COMMENT 'Geo del domicilio de entrega' AFTER `direccion_entrega_referencia`,
                    ADD COLUMN `longitud` decimal(10,7) DEFAULT NULL COMMENT 'Geo del domicilio de entrega' AFTER `latitud`
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
                    ALTER TABLE `{$prefix}clientes`
                    DROP COLUMN `direccion_entrega`,
                    DROP COLUMN `direccion_entrega_referencia`,
                    DROP COLUMN `latitud`,
                    DROP COLUMN `longitud`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
