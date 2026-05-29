<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5 (integraciones de pago) — pantalla orientada al cliente.
 *
 * Flag por CAJA (no por sucursal): cada puesto físico puede tener o no un
 * segundo monitor apuntando al cliente. Cuando está activo y el navegador
 * detecta una segunda pantalla, el QR de cobro se muestra en ese monitor en
 * lugar del modal del cajero.
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
                    ALTER TABLE `{$prefix}cajas`
                    ADD COLUMN `usa_pantalla_cliente` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si el puesto/caja tiene un segundo monitor orientado al cliente para mostrar el QR de cobro'
                    AFTER `mp_pos_qr_pdf_url`
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
                    ALTER TABLE `{$prefix}cajas`
                    DROP COLUMN `usa_pantalla_cliente`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
