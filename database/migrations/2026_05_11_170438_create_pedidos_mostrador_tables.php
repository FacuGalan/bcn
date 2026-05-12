<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PR2.A (Pedidos por Mostrador): crea las 6 tablas tenant del modulo.
 *
 * Estructura espejo de la cadena ventas/ventas_detalle/venta_pagos con los
 * cambios definidos en .claude/specs/pedidos-mostrador.md:
 * - pedidos_mostrador agrega estado_pedido, identificador, numero_beeper,
 *   cliente_temporal y timestamps de transicion.
 * - pedidos_mostrador_pagos NO incluye campos fiscales (esos viven en
 *   venta_pagos al convertir el pedido en venta).
 * - Bundle migration: las 6 tablas se crean juntas porque son interdependientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $this->crearPedidosMostrador($prefix);
                $this->crearPedidosMostradorDetalle($prefix);
                $this->crearPedidoMostradorDetalleOpcionales($prefix);
                $this->crearPedidoMostradorDetallePromociones($prefix);
                $this->crearPedidoMostradorPromociones($prefix);
                $this->crearPedidosMostradorPagos($prefix);
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
                // Orden inverso por FKs.
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedidos_mostrador_pagos`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedido_mostrador_promociones`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedido_mostrador_detalle_promociones`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedido_mostrador_detalle_opcionales`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedidos_mostrador_detalle`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}pedidos_mostrador`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    private function crearPedidosMostrador(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedidos_mostrador` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `numero` int(10) unsigned DEFAULT NULL COMMENT 'Numero correlativo por sucursal. NULL en borrador.',
                `identificador` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto libre: Juan, Mesa 5, etc.',
                `numero_beeper` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero de beeper si la sucursal usa beepers',
                `sucursal_id` bigint(20) unsigned NOT NULL,
                `cliente_id` bigint(20) unsigned DEFAULT NULL,
                `nombre_cliente_temporal` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del cliente sin alta. Obligatorio al confirmar si cliente_id IS NULL.',
                `telefono_cliente_temporal` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Telefono del cliente sin alta.',
                `caja_id` bigint(20) unsigned DEFAULT NULL,
                `canal_venta_id` bigint(20) unsigned DEFAULT NULL,
                `forma_venta_id` bigint(20) unsigned DEFAULT NULL,
                `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'FK logico a config.users.id (quien dio de alta)',
                `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `estado_pedido` enum('borrador','confirmado','en_preparacion','listo','entregado','facturado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
                `estado_pago` enum('pendiente','parcial','pagado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente' COMMENT 'Cache, recalculado al agregar/anular pagos',
                `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
                `iva` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
                `total` decimal(12,2) NOT NULL DEFAULT '0.00',
                `ajuste_forma_pago` decimal(12,2) NOT NULL DEFAULT '0.00',
                `total_final` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento_general_tipo` enum('porcentaje','monto_fijo') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `descuento_general_valor` decimal(12,2) DEFAULT NULL,
                `descuento_general_monto` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento_general_aplicado_por` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico cross-DB a config.users',
                `cupon_id` bigint(20) unsigned DEFAULT NULL,
                `cupon_codigo_snapshot` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `cupon_descripcion_snapshot` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `monto_cupon` decimal(12,2) NOT NULL DEFAULT '0.00',
                `puntos_ganados` int(10) unsigned NOT NULL DEFAULT '0',
                `puntos_usados` int(10) unsigned NOT NULL DEFAULT '0',
                `observaciones` text COLLATE utf8mb4_unicode_ci,
                `motivo_cancelacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `confirmado_at` timestamp NULL DEFAULT NULL,
                `en_preparacion_at` timestamp NULL DEFAULT NULL,
                `listo_at` timestamp NULL DEFAULT NULL,
                `entregado_at` timestamp NULL DEFAULT NULL,
                `cancelado_at` timestamp NULL DEFAULT NULL,
                `cancelado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
                `venta_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a ventas tras conversion',
                `convertido_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                `deleted_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pm_numero` (`numero`),
                KEY `idx_pm_sucursal` (`sucursal_id`),
                KEY `idx_pm_estado_pedido` (`estado_pedido`),
                KEY `idx_pm_estado_pago` (`estado_pago`),
                KEY `idx_pm_fecha` (`fecha`),
                KEY `idx_pm_cliente` (`cliente_id`),
                KEY `idx_pm_caja` (`caja_id`),
                KEY `idx_pm_venta` (`venta_id`),
                KEY `idx_pm_telefono_temporal` (`telefono_cliente_temporal`),
                CONSTRAINT `{$prefix}fk_pm_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                CONSTRAINT `{$prefix}fk_pm_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pm_caja` FOREIGN KEY (`caja_id`) REFERENCES `{$prefix}cajas` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pm_venta` FOREIGN KEY (`venta_id`) REFERENCES `{$prefix}ventas` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function crearPedidosMostradorDetalle(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedidos_mostrador_detalle` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `pedido_mostrador_id` bigint(20) unsigned NOT NULL,
                `articulo_id` bigint(20) unsigned DEFAULT NULL,
                `es_concepto` tinyint(1) NOT NULL DEFAULT '0',
                `concepto_descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `concepto_categoria_id` bigint(20) unsigned DEFAULT NULL,
                `tipo_iva_id` bigint(20) unsigned DEFAULT NULL,
                `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                `cantidad` decimal(12,3) NOT NULL,
                `precio_unitario` decimal(12,2) NOT NULL,
                `precio_sin_iva` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
                `precio_lista` decimal(12,2) DEFAULT NULL,
                `precio_opcionales` decimal(12,2) NOT NULL DEFAULT '0.00',
                `subtotal` decimal(12,2) NOT NULL,
                `ajuste_manual_tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `ajuste_manual_valor` decimal(12,2) DEFAULT NULL,
                `ajuste_manual_origen` enum('manual','descuento_general') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `ajuste_manual_aplicado_por` bigint(20) unsigned DEFAULT NULL,
                `precio_sin_ajuste_manual` decimal(12,2) DEFAULT NULL,
                `pagado_con_puntos` tinyint(1) NOT NULL DEFAULT '0',
                `puntos_usados` int(10) unsigned NOT NULL DEFAULT '0',
                `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
                `iva_monto` decimal(12,2) DEFAULT '0.00',
                `descuento_porcentaje` decimal(5,2) DEFAULT '0.00',
                `descuento_monto` decimal(12,2) DEFAULT '0.00',
                `descuento_promocion` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento_promocion_especial` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento_cupon` decimal(12,2) NOT NULL DEFAULT '0.00',
                `descuento_lista` decimal(12,2) NOT NULL DEFAULT '0.00',
                `tiene_promocion` tinyint(1) NOT NULL DEFAULT '0',
                `total` decimal(12,2) NOT NULL DEFAULT '0.00',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pmd_pedido` (`pedido_mostrador_id`),
                KEY `idx_pmd_articulo` (`articulo_id`),
                KEY `idx_pmd_lista_precio` (`lista_precio_id`),
                KEY `idx_pmd_concepto_categoria` (`concepto_categoria_id`),
                CONSTRAINT `{$prefix}fk_pmd_pedido` FOREIGN KEY (`pedido_mostrador_id`) REFERENCES `{$prefix}pedidos_mostrador` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_pmd_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$prefix}articulos` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmd_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{$prefix}listas_precios` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmd_concepto_categoria` FOREIGN KEY (`concepto_categoria_id`) REFERENCES `{$prefix}categorias` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function crearPedidoMostradorDetalleOpcionales(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedido_mostrador_detalle_opcionales` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `pedido_mostrador_detalle_id` bigint(20) unsigned NOT NULL,
                `grupo_opcional_id` bigint(20) unsigned NOT NULL,
                `opcional_id` bigint(20) unsigned NOT NULL,
                `nombre_grupo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `nombre_opcional` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `cantidad` decimal(12,3) NOT NULL DEFAULT '1.000',
                `precio_extra` decimal(12,2) NOT NULL DEFAULT '0.00',
                `subtotal_extra` decimal(12,2) NOT NULL DEFAULT '0.00',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pmdo_detalle` (`pedido_mostrador_detalle_id`),
                KEY `idx_pmdo_opcional` (`opcional_id`),
                KEY `idx_pmdo_grupo` (`grupo_opcional_id`),
                CONSTRAINT `{$prefix}fk_pmdo_detalle` FOREIGN KEY (`pedido_mostrador_detalle_id`) REFERENCES `{$prefix}pedidos_mostrador_detalle` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_pmdo_grupo` FOREIGN KEY (`grupo_opcional_id`) REFERENCES `{$prefix}grupos_opcionales` (`id`),
                CONSTRAINT `{$prefix}fk_pmdo_opcional` FOREIGN KEY (`opcional_id`) REFERENCES `{$prefix}opcionales` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function crearPedidoMostradorDetallePromociones(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedido_mostrador_detalle_promociones` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `pedido_mostrador_detalle_id` bigint(20) unsigned NOT NULL,
                `tipo_promocion` enum('promocion','promocion_especial','lista_precio') COLLATE utf8mb4_unicode_ci NOT NULL,
                `promocion_id` bigint(20) unsigned DEFAULT NULL,
                `promocion_especial_id` bigint(20) unsigned DEFAULT NULL,
                `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                `descripcion_promocion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `tipo_beneficio` enum('porcentaje','monto_fijo','precio_especial','nx1') COLLATE utf8mb4_unicode_ci NOT NULL,
                `valor_beneficio` decimal(12,2) NOT NULL,
                `descuento_aplicado` decimal(12,2) NOT NULL,
                `cantidad_requerida` int(10) unsigned DEFAULT NULL,
                `cantidad_bonificada` int(10) unsigned DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pmdp_detalle` (`pedido_mostrador_detalle_id`),
                KEY `idx_pmdp_tipo` (`tipo_promocion`),
                KEY `idx_pmdp_promocion` (`promocion_id`),
                KEY `idx_pmdp_promo_especial` (`promocion_especial_id`),
                KEY `idx_pmdp_lista_precio` (`lista_precio_id`),
                CONSTRAINT `{$prefix}fk_pmdp_detalle` FOREIGN KEY (`pedido_mostrador_detalle_id`) REFERENCES `{$prefix}pedidos_mostrador_detalle` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_pmdp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{$prefix}promociones` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmdp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{$prefix}promociones_especiales` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmdp_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{$prefix}listas_precios` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function crearPedidoMostradorPromociones(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedido_mostrador_promociones` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `pedido_mostrador_id` bigint(20) unsigned NOT NULL,
                `tipo_promocion` enum('promocion','promocion_especial','forma_pago','cupon') COLLATE utf8mb4_unicode_ci NOT NULL,
                `promocion_id` bigint(20) unsigned DEFAULT NULL,
                `promocion_especial_id` bigint(20) unsigned DEFAULT NULL,
                `forma_pago_id` bigint(20) unsigned DEFAULT NULL,
                `codigo_cupon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `descripcion_promocion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `tipo_beneficio` enum('porcentaje','monto_fijo') COLLATE utf8mb4_unicode_ci NOT NULL,
                `valor_beneficio` decimal(12,2) NOT NULL,
                `descuento_aplicado` decimal(12,2) NOT NULL,
                `monto_minimo_requerido` decimal(12,2) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pmp_pedido` (`pedido_mostrador_id`),
                KEY `idx_pmp_tipo` (`tipo_promocion`),
                KEY `idx_pmp_promocion` (`promocion_id`),
                KEY `idx_pmp_promo_especial` (`promocion_especial_id`),
                KEY `idx_pmp_forma_pago` (`forma_pago_id`),
                KEY `idx_pmp_cupon` (`codigo_cupon`),
                CONSTRAINT `{$prefix}fk_pmp_pedido` FOREIGN KEY (`pedido_mostrador_id`) REFERENCES `{$prefix}pedidos_mostrador` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_pmp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{$prefix}promociones` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{$prefix}promociones_especiales` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmp_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function crearPedidosMostradorPagos(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}pedidos_mostrador_pagos` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `pedido_mostrador_id` bigint(20) unsigned NOT NULL,
                `forma_pago_id` bigint(20) unsigned NOT NULL,
                `concepto_pago_id` bigint(20) unsigned DEFAULT NULL,
                `monto_base` decimal(12,2) NOT NULL,
                `ajuste_porcentaje` decimal(6,2) NOT NULL DEFAULT '0.00',
                `monto_ajuste` decimal(12,2) NOT NULL DEFAULT '0.00',
                `monto_final` decimal(12,2) NOT NULL,
                `monto_recibido` decimal(12,2) DEFAULT NULL,
                `vuelto` decimal(12,2) DEFAULT NULL,
                `cuotas` tinyint(3) unsigned DEFAULT NULL,
                `recargo_cuotas_porcentaje` decimal(6,2) DEFAULT NULL,
                `recargo_cuotas_monto` decimal(12,2) DEFAULT NULL,
                `monto_cuota` decimal(12,2) DEFAULT NULL,
                `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `observaciones` text COLLATE utf8mb4_unicode_ci,
                `es_cuenta_corriente` tinyint(1) NOT NULL DEFAULT '0',
                `es_pago_puntos` tinyint(1) NOT NULL DEFAULT '0',
                `puntos_usados` int(10) unsigned NOT NULL DEFAULT '0',
                `afecta_caja` tinyint(1) NOT NULL DEFAULT '1',
                `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
                `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL,
                `anulado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
                `anulado_at` timestamp NULL DEFAULT NULL,
                `motivo_anulacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `creado_por_usuario_id` bigint(20) unsigned NOT NULL,
                `cierre_turno_id` bigint(20) unsigned DEFAULT NULL,
                `moneda_id` bigint(20) unsigned DEFAULT NULL,
                `monto_moneda_original` decimal(14,2) DEFAULT NULL,
                `tipo_cambio_tasa` decimal(14,6) DEFAULT NULL,
                `tipo_cambio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id',
                `venta_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a venta_pagos al convertir',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pmpago_pedido` (`pedido_mostrador_id`),
                KEY `idx_pmpago_forma` (`forma_pago_id`),
                KEY `idx_pmpago_concepto` (`concepto_pago_id`),
                KEY `idx_pmpago_mov_caja` (`movimiento_caja_id`),
                KEY `idx_pmpago_estado` (`estado`),
                KEY `idx_pmpago_cierre_turno` (`cierre_turno_id`),
                KEY `idx_pmpago_tipo_cambio` (`tipo_cambio_id`),
                KEY `idx_pmpago_venta_pago` (`venta_pago_id`),
                CONSTRAINT `{$prefix}fk_pmpago_pedido` FOREIGN KEY (`pedido_mostrador_id`) REFERENCES `{$prefix}pedidos_mostrador` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_pmpago_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`),
                CONSTRAINT `{$prefix}fk_pmpago_concepto` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{$prefix}conceptos_pago` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmpago_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `{$prefix}movimientos_caja` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_pmpago_venta_pago` FOREIGN KEY (`venta_pago_id`) REFERENCES `{$prefix}venta_pagos` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};
