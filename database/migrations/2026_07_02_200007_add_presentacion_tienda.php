<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: presentación en tienda (RF-17, D21).
 *
 * - `articulos_sucursales.visible_tienda`: visibilidad en la tienda de ESA
 *   sucursal (la tienda es por sucursal, D15). Independiente de `vendible`,
 *   que gobierna la pantalla POS interna.
 * - `categorias.imagen_path` + `categorias.orden`: presentación del catálogo
 *   público (las categorías hoy solo tienen color/icono para UI interna).
 *
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (RF-17, D21).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    ADD COLUMN `visible_tienda` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Visible en la tienda online de esta sucursal (RF-17). Independiente de vendible (POS interno)' AFTER `vendible`
                ");
            } catch (\Exception $e) {
                // columna ya presente → seguir con categorias
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}categorias`
                    ADD COLUMN `imagen_path` varchar(255) DEFAULT NULL COMMENT 'Imagen para el catalogo de la tienda (RF-17)' AFTER `icono`,
                    ADD COLUMN `orden` int(11) NOT NULL DEFAULT 0 COMMENT 'Orden de presentacion en catalogo tienda (RF-17)' AFTER `imagen_path`
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
                    ALTER TABLE `{$prefix}articulos_sucursales`
                    DROP COLUMN `visible_tienda`
                ");
            } catch (\Exception $e) {
                // ignorar
            }

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}categorias`
                    DROP COLUMN `imagen_path`,
                    DROP COLUMN `orden`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
