<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR2.A (Pedidos por Mostrador): config por sucursal.
 *
 * - pedido_mostrador_ultimo_numero: contador correlativo por sucursal para
 *   numerar pedidos. Se incrementa al confirmar (no en borrador).
 * - imprime_comanda_automatico: si true, al confirmar dispara impresion via
 *   QZ Tray segun asignacion en impresora_tipo_documento.
 * - pedido_conversion_automatica_al_entregar: si true, al pasar el pedido a
 *   estado entregado se ejecuta automaticamente la conversion a Venta.
 * - usa_beepers: si true, el pedido requiere numero_beeper para confirmar.
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
                    ALTER TABLE `{$prefix}sucursales`
                    ADD COLUMN `pedido_mostrador_ultimo_numero` int unsigned NOT NULL DEFAULT 0 COMMENT 'Contador correlativo de pedidos por mostrador (reset manual con permiso)'
                    AFTER `facturacion_fiscal_automatica`,
                    ADD COLUMN `imprime_comanda_automatico` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si imprime comanda automaticamente al confirmar pedido'
                    AFTER `pedido_mostrador_ultimo_numero`,
                    ADD COLUMN `pedido_conversion_automatica_al_entregar` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si convierte pedido en venta automaticamente al pasar a entregado'
                    AFTER `imprime_comanda_automatico`,
                    ADD COLUMN `usa_beepers` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si la sucursal usa beepers llamadores (numero_beeper obligatorio al confirmar)'
                    AFTER `pedido_conversion_automatica_al_entregar`
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
                    ALTER TABLE `{$prefix}sucursales`
                    DROP COLUMN `usa_beepers`,
                    DROP COLUMN `pedido_conversion_automatica_al_entregar`,
                    DROP COLUMN `imprime_comanda_automatico`,
                    DROP COLUMN `pedido_mostrador_ultimo_numero`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
