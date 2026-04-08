<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE `{$prefix}movimientos_puntos` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cliente_id` bigint unsigned NOT NULL,
                        `sucursal_id` bigint unsigned NOT NULL,
                        `fecha` datetime NOT NULL,
                        `tipo` enum('acumulacion','canje_descuento','canje_articulo','canje_cupon','ajuste_manual','anulacion') NOT NULL COMMENT 'Tipo de movimiento',
                        `puntos` int NOT NULL COMMENT 'Positivo = acumulación, negativo = consumo',
                        `monto_asociado` decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Monto de la transacción asociada',
                        `documento_tipo` varchar(50) DEFAULT NULL COMMENT 'venta, venta_pago, cupon, ajuste',
                        `documento_id` bigint unsigned DEFAULT NULL COMMENT 'ID del documento referenciado',
                        `venta_id` bigint unsigned DEFAULT NULL COMMENT 'FK directa a ventas (shortcut)',
                        `venta_pago_id` bigint unsigned DEFAULT NULL COMMENT 'FK a venta_pagos (para canje como pago)',
                        `cupon_id` bigint unsigned DEFAULT NULL COMMENT 'FK a cupones (para canje por cupón)',
                        `concepto` varchar(255) NOT NULL COMMENT 'Descripción legible del movimiento',
                        `observaciones` text DEFAULT NULL COMMENT 'Notas adicionales (para ajustes manuales)',
                        `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
                        `anulado_por_movimiento_id` bigint unsigned DEFAULT NULL COMMENT 'FK a movimiento contraasiento',
                        `usuario_id` bigint unsigned NOT NULL COMMENT 'FK a users (quien registró)',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_mp_cliente_estado` (`cliente_id`, `estado`),
                        KEY `idx_mp_cliente_sucursal_estado` (`cliente_id`, `sucursal_id`, `estado`),
                        KEY `idx_mp_venta` (`venta_id`),
                        KEY `idx_mp_cupon` (`cupon_id`),
                        KEY `idx_mp_tipo` (`tipo`),
                        KEY `idx_mp_fecha` (`fecha`),
                        CONSTRAINT `{$prefix}fk_mp_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`),
                        CONSTRAINT `{$prefix}fk_mp_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                        CONSTRAINT `{$prefix}fk_mp_venta` FOREIGN KEY (`venta_id`) REFERENCES `{$prefix}ventas` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_mp_anulado_por` FOREIGN KEY (`anulado_por_movimiento_id`) REFERENCES `{$prefix}movimientos_puntos` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}movimientos_puntos`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
