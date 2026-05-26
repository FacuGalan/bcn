<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feature "comanda por detalle" en Pedidos por Mostrador.
 *
 * Agrega columna `comandado_at` a `pedidos_mostrador_detalle` para trackear
 * cuando cada item individual fue enviado a cocina. Permite re-comandar solo
 * los items nuevos cuando se agregan articulos a un pedido ya en preparacion.
 *
 * Spec: .claude/specs/comanda-por-detalle-pedido-mostrador.md (Fase 1).
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
                    ALTER TABLE `{$prefix}pedidos_mostrador_detalle`
                    ADD COLUMN `comandado_at` timestamp NULL DEFAULT NULL AFTER `pagado_con_puntos`
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
                    ALTER TABLE `{$prefix}pedidos_mostrador_detalle`
                    DROP COLUMN `comandado_at`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
