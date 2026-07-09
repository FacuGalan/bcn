<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Numeración display PROPIA de delivery (separada de mostrador):
 *
 * - Columnas nuevas en `sucursales`: contador y segmento de reset propios
 *   (`pedido_delivery_display_ultimo_numero` / `_segmento_at`). El contador de
 *   mostrador (`pedido_display_ultimo_numero`) deja de compartirse.
 * - Seed en el JSON `config_delivery`: las keys nuevas de configuración
 *   (`conversion_automatica_al_entregar`, `usa_numeracion_display`,
 *   `numeracion_display_modo`, `numeracion_display_horas`) se copian desde las
 *   columnas compartidas con mostrador para que el comportamiento existente
 *   NO cambie al separar ambas configuraciones.
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
                    ADD COLUMN `pedido_delivery_display_ultimo_numero` int unsigned NOT NULL DEFAULT 0
                        AFTER `pedido_delivery_ultimo_numero`,
                    ADD COLUMN `pedido_delivery_display_segmento_at` datetime DEFAULT NULL
                        AFTER `pedido_delivery_display_ultimo_numero`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                $sucursales = DB::connection('pymes')
                    ->table($prefix.'sucursales')
                    ->get([
                        'id', 'config_delivery', 'pedido_conversion_automatica_al_entregar',
                        'usa_numeracion_display', 'numeracion_display_modo', 'numeracion_display_horas',
                    ]);

                foreach ($sucursales as $sucursal) {
                    $config = $sucursal->config_delivery
                        ? (json_decode($sucursal->config_delivery, true) ?: [])
                        : [];

                    $horas = $sucursal->numeracion_display_horas
                        ? (json_decode($sucursal->numeracion_display_horas, true) ?: [6])
                        : [6];

                    $config += [
                        'conversion_automatica_al_entregar' => (bool) $sucursal->pedido_conversion_automatica_al_entregar,
                        'usa_numeracion_display' => (bool) $sucursal->usa_numeracion_display,
                        'numeracion_display_modo' => $sucursal->numeracion_display_modo ?: 'diario',
                        'numeracion_display_horas' => $horas,
                    ];

                    DB::connection('pymes')
                        ->table($prefix.'sucursales')
                        ->where('id', $sucursal->id)
                        ->update(['config_delivery' => json_encode($config)]);
                }
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
                    DROP COLUMN `pedido_delivery_display_ultimo_numero`,
                    DROP COLUMN `pedido_delivery_display_segmento_at`
                ");
            } catch (\Exception $e) {
                continue;
            }

            try {
                $sucursales = DB::connection('pymes')
                    ->table($prefix.'sucursales')
                    ->get(['id', 'config_delivery']);

                foreach ($sucursales as $sucursal) {
                    $config = $sucursal->config_delivery
                        ? (json_decode($sucursal->config_delivery, true) ?: [])
                        : [];

                    unset(
                        $config['conversion_automatica_al_entregar'],
                        $config['usa_numeracion_display'],
                        $config['numeracion_display_modo'],
                        $config['numeracion_display_horas'],
                    );

                    DB::connection('pymes')
                        ->table($prefix.'sucursales')
                        ->where('id', $sucursal->id)
                        ->update(['config_delivery' => json_encode($config)]);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
