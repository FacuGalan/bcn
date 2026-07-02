<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos Delivery — Fase 1: las 6 tablas espejo de pedidos_mostrador.
 *
 * `pedidos_delivery` replica TODAS las columnas de `pedidos_mostrador` y agrega
 * la dimensión logística (tipo delivery/take_away, dirección de entrega con
 * snapshot geo, costo de envío, repartidor/salida, promesa de entrega,
 * programados) y la de origen externo (tienda/API: origen, consumidor_id,
 * email temporal, token de seguimiento público, datos fiscales del checkout).
 *
 * Las FK a `repartidores`, `delivery_zonas` y `delivery_salidas` se agregan en
 * las migraciones siguientes (las tablas referenciadas aún no existen acá).
 *
 * Itera todos los comercios con SQL raw + prefijo + try/catch por comercio.
 * Regenerar database/sql/tenant_tables.sql tras esta migración.
 *
 * Ref: .claude/specs/pedidos-delivery.md (Modelo de Datos, D1/D17/D18/D20).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::connection('config')->table('comercios')->get() as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedidos_delivery` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `numero` int(10) unsigned DEFAULT NULL COMMENT 'Numero correlativo por sucursal (contador propio de delivery). NULL en borrador.',
                        `numero_display` int(10) unsigned DEFAULT NULL COMMENT 'Numero amigable mostrado (monitor/comanda/kanban). Comparte contador con mostrador.',
                        `identificador` varchar(100) DEFAULT NULL COMMENT 'Texto libre: Juan, Depto 3B, etc.',
                        `numero_beeper` varchar(20) DEFAULT NULL COMMENT 'Numero de beeper (take-away en el local), delivery lo ignora',
                        `tipo` enum('delivery','take_away') NOT NULL COMMENT 'RF-02',
                        `sucursal_id` bigint(20) unsigned NOT NULL,
                        `cliente_id` bigint(20) unsigned DEFAULT NULL,
                        `nombre_cliente_temporal` varchar(150) DEFAULT NULL COMMENT 'Nombre del cliente sin alta. Obligatorio al confirmar si cliente_id IS NULL.',
                        `telefono_cliente_temporal` varchar(30) DEFAULT NULL COMMENT 'Telefono del cliente sin alta.',
                        `email_cliente_temporal` varchar(150) DEFAULT NULL COMMENT 'Email de invitados de tienda (RF-12)',
                        `caja_id` bigint(20) unsigned DEFAULT NULL,
                        `canal_venta_id` bigint(20) unsigned DEFAULT NULL,
                        `forma_venta_id` bigint(20) unsigned DEFAULT NULL,
                        `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                        `usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico a config.users.id (quien dio de alta, NULL en pedidos de tienda/API)',
                        `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `estado_pedido` enum('borrador','confirmado','en_preparacion','listo','en_camino','entregado','facturado','cancelado') NOT NULL DEFAULT 'borrador',
                        `estado_pago` enum('pendiente','parcial','pagado') NOT NULL DEFAULT 'pendiente' COMMENT 'Cache, recalculado al agregar/anular pagos',
                        `orden_kanban` bigint(20) unsigned NOT NULL DEFAULT 0,
                        `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `iva` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `total` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `ajuste_forma_pago` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `total_final` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `es_invitacion_total` tinyint(1) NOT NULL DEFAULT 0,
                        `invitacion_motivo` varchar(500) DEFAULT NULL,
                        `invitado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
                        `invitado_at` timestamp NULL DEFAULT NULL,
                        `total_invitado` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento_general_tipo` enum('porcentaje','monto_fijo') DEFAULT NULL,
                        `descuento_general_valor` decimal(12,2) DEFAULT NULL,
                        `descuento_general_monto` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento_general_aplicado_por` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico cross-DB a config.users',
                        `cupon_id` bigint(20) unsigned DEFAULT NULL,
                        `cupon_codigo_snapshot` varchar(50) DEFAULT NULL,
                        `cupon_descripcion_snapshot` varchar(500) DEFAULT NULL,
                        `monto_cupon` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `puntos_ganados` int(10) unsigned NOT NULL DEFAULT 0,
                        `puntos_usados` int(10) unsigned NOT NULL DEFAULT 0,
                        `puntos_canjeados_pago` int(10) unsigned NOT NULL DEFAULT 0,
                        `puntos_canjeados_articulos` int(10) unsigned NOT NULL DEFAULT 0,
                        `puntos_usados_monto` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `articulos_canjeados_monto` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `observaciones` text,
                        `motivo_cancelacion` varchar(500) DEFAULT NULL,
                        `direccion_entrega` varchar(255) DEFAULT NULL COMMENT 'Snapshot inmutable (NULL en take_away)',
                        `direccion_referencia` varchar(255) DEFAULT NULL COMMENT 'Piso/depto/indicaciones',
                        `localidad_entrega_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Ref soft a localidades (config)',
                        `latitud` decimal(10,7) DEFAULT NULL COMMENT 'Snapshot geo',
                        `longitud` decimal(10,7) DEFAULT NULL COMMENT 'Snapshot geo',
                        `zona_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK delivery_zonas (la que matcheo al cotizar)',
                        `costo_envio` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fuente logistica, el monto se materializa como renglon es_concepto (D17)',
                        `costo_envio_manual` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pisado a mano (D7)',
                        `costo_envio_usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico config.users: quien piso el costo',
                        `distancia_km` decimal(8,2) DEFAULT NULL COMMENT 'Calculada al cotizar (Haversine v1)',
                        `fuera_de_alcance` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Confirmado con permiso forzar_alcance',
                        `repartidor_id` bigint(20) unsigned DEFAULT NULL,
                        `salida_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Salida ACTUAL, historial completo en delivery_salida_pedidos',
                        `en_camino_at` timestamp NULL DEFAULT NULL,
                        `no_entregado_motivo` varchar(255) DEFAULT NULL COMMENT 'Ultima vuelta fallida (RF-08)',
                        `hora_pactada_at` timestamp NULL DEFAULT NULL COMMENT 'Promesa de entrega/retiro (RF-15)',
                        `programado_para` timestamp NULL DEFAULT NULL COMMENT 'Pedido programado (RF-15, logica en Fase 8)',
                        `datos_fiscales_snapshot` json DEFAULT NULL COMMENT 'DNI/CUIT opcional del checkout (RF-13)',
                        `origen` enum('panel','tienda','api') NOT NULL DEFAULT 'panel' COMMENT 'RF-12',
                        `origen_referencia` varchar(100) DEFAULT NULL COMMENT 'Id externo del integrador',
                        `consumidor_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico a config.consumidores',
                        `token_seguimiento` char(26) DEFAULT NULL COMMENT 'ULID para tracking publico sin auth',
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
                        UNIQUE KEY `{$prefix}pd_token_seguimiento_unique` (`token_seguimiento`),
                        KEY `idx_pd_numero` (`numero`),
                        KEY `idx_pd_sucursal` (`sucursal_id`),
                        KEY `idx_pd_estado_pedido` (`estado_pedido`),
                        KEY `idx_pd_estado_pago` (`estado_pago`),
                        KEY `idx_pd_fecha` (`fecha`),
                        KEY `idx_pd_cliente` (`cliente_id`),
                        KEY `idx_pd_caja` (`caja_id`),
                        KEY `idx_pd_venta` (`venta_id`),
                        KEY `idx_pd_telefono_temporal` (`telefono_cliente_temporal`),
                        KEY `idx_pd_tipo` (`tipo`),
                        KEY `idx_pd_repartidor_estado` (`repartidor_id`,`estado_pedido`),
                        KEY `idx_pd_salida` (`salida_id`),
                        KEY `idx_pd_origen` (`origen`),
                        KEY `idx_pd_consumidor` (`consumidor_id`),
                        KEY `{$prefix}pedidos_delivery_orden_kanban_idx` (`estado_pedido`,`orden_kanban`),
                        KEY `idx_pd_es_invitacion_total` (`es_invitacion_total`,`fecha`),
                        CONSTRAINT `{$prefix}fk_pd_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                        CONSTRAINT `{$prefix}fk_pd_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{$prefix}clientes` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pd_caja` FOREIGN KEY (`caja_id`) REFERENCES `{$prefix}cajas` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pd_venta` FOREIGN KEY (`venta_id`) REFERENCES `{$prefix}ventas` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedidos_delivery_detalle` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `pedido_delivery_id` bigint(20) unsigned NOT NULL,
                        `articulo_id` bigint(20) unsigned DEFAULT NULL,
                        `es_concepto` tinyint(1) NOT NULL DEFAULT 0,
                        `concepto_descripcion` varchar(255) DEFAULT NULL,
                        `concepto_categoria_id` bigint(20) unsigned DEFAULT NULL,
                        `es_costo_envio` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Renglon-concepto del costo de envio (D17): gestionado por el service, excluido de descuentos',
                        `tipo_iva_id` bigint(20) unsigned DEFAULT NULL,
                        `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                        `cantidad` decimal(12,3) NOT NULL,
                        `precio_unitario` decimal(12,2) NOT NULL,
                        `precio_sin_iva` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `precio_lista` decimal(12,2) DEFAULT NULL,
                        `precio_opcionales` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `subtotal` decimal(12,2) NOT NULL,
                        `ajuste_manual_tipo` varchar(20) DEFAULT NULL,
                        `ajuste_manual_valor` decimal(12,2) DEFAULT NULL,
                        `ajuste_manual_origen` enum('manual','descuento_general') DEFAULT NULL,
                        `ajuste_manual_aplicado_por` bigint(20) unsigned DEFAULT NULL,
                        `precio_sin_ajuste_manual` decimal(12,2) DEFAULT NULL,
                        `pagado_con_puntos` tinyint(1) NOT NULL DEFAULT 0,
                        `comandado_at` timestamp NULL DEFAULT NULL COMMENT 'Momento en que el detalle fue enviado a cocina (null = no comandado)',
                        `puntos_usados` int(10) unsigned NOT NULL DEFAULT 0,
                        `iva_porcentaje` decimal(5,2) DEFAULT 0.00,
                        `iva_monto` decimal(12,2) DEFAULT 0.00,
                        `descuento_porcentaje` decimal(5,2) DEFAULT 0.00,
                        `descuento_monto` decimal(12,2) DEFAULT 0.00,
                        `descuento_promocion` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento_promocion_especial` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento_cupon` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `descuento_lista` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `tiene_promocion` tinyint(1) NOT NULL DEFAULT 0,
                        `total` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `es_invitacion` tinyint(1) NOT NULL DEFAULT 0,
                        `invitacion_motivo` varchar(500) DEFAULT NULL,
                        `invitado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
                        `invitado_at` timestamp NULL DEFAULT NULL,
                        `monto_invitado` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `precio_unitario_original` decimal(12,2) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_pdd_pedido` (`pedido_delivery_id`),
                        KEY `idx_pdd_articulo` (`articulo_id`),
                        KEY `idx_pdd_lista_precio` (`lista_precio_id`),
                        KEY `idx_pdd_concepto_categoria` (`concepto_categoria_id`),
                        KEY `idx_pdd_es_invitacion` (`es_invitacion`),
                        CONSTRAINT `{$prefix}fk_pdd_pedido` FOREIGN KEY (`pedido_delivery_id`) REFERENCES `{$prefix}pedidos_delivery` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_pdd_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$prefix}articulos` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdd_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{$prefix}listas_precios` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdd_concepto_categoria` FOREIGN KEY (`concepto_categoria_id`) REFERENCES `{$prefix}categorias` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedido_delivery_detalle_opcionales` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `pedido_delivery_detalle_id` bigint(20) unsigned NOT NULL,
                        `grupo_opcional_id` bigint(20) unsigned NOT NULL,
                        `opcional_id` bigint(20) unsigned NOT NULL,
                        `nombre_grupo` varchar(255) NOT NULL,
                        `nombre_opcional` varchar(255) NOT NULL,
                        `cantidad` decimal(12,3) NOT NULL DEFAULT 1.000,
                        `precio_extra` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `subtotal_extra` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_pddo_detalle` (`pedido_delivery_detalle_id`),
                        KEY `idx_pddo_opcional` (`opcional_id`),
                        KEY `idx_pddo_grupo` (`grupo_opcional_id`),
                        CONSTRAINT `{$prefix}fk_pddo_detalle` FOREIGN KEY (`pedido_delivery_detalle_id`) REFERENCES `{$prefix}pedidos_delivery_detalle` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_pddo_grupo` FOREIGN KEY (`grupo_opcional_id`) REFERENCES `{$prefix}grupos_opcionales` (`id`),
                        CONSTRAINT `{$prefix}fk_pddo_opcional` FOREIGN KEY (`opcional_id`) REFERENCES `{$prefix}opcionales` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedido_delivery_detalle_promociones` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `pedido_delivery_detalle_id` bigint(20) unsigned NOT NULL,
                        `tipo_promocion` enum('promocion','promocion_especial','lista_precio') NOT NULL,
                        `promocion_id` bigint(20) unsigned DEFAULT NULL,
                        `promocion_especial_id` bigint(20) unsigned DEFAULT NULL,
                        `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
                        `descripcion_promocion` varchar(255) NOT NULL,
                        `tipo_beneficio` enum('porcentaje','monto_fijo','precio_especial','nx1') NOT NULL,
                        `valor_beneficio` decimal(12,2) NOT NULL,
                        `descuento_aplicado` decimal(12,2) NOT NULL,
                        `cantidad_requerida` int(10) unsigned DEFAULT NULL,
                        `cantidad_bonificada` int(10) unsigned DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_pddp_detalle` (`pedido_delivery_detalle_id`),
                        KEY `idx_pddp_tipo` (`tipo_promocion`),
                        KEY `idx_pddp_promocion` (`promocion_id`),
                        KEY `idx_pddp_promo_especial` (`promocion_especial_id`),
                        KEY `idx_pddp_lista_precio` (`lista_precio_id`),
                        CONSTRAINT `{$prefix}fk_pddp_detalle` FOREIGN KEY (`pedido_delivery_detalle_id`) REFERENCES `{$prefix}pedidos_delivery_detalle` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_pddp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{$prefix}promociones` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pddp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{$prefix}promociones_especiales` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pddp_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{$prefix}listas_precios` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedido_delivery_promociones` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `pedido_delivery_id` bigint(20) unsigned NOT NULL,
                        `tipo_promocion` enum('promocion','promocion_especial','forma_pago','cupon') NOT NULL,
                        `promocion_id` bigint(20) unsigned DEFAULT NULL,
                        `promocion_especial_id` bigint(20) unsigned DEFAULT NULL,
                        `forma_pago_id` bigint(20) unsigned DEFAULT NULL,
                        `codigo_cupon` varchar(50) DEFAULT NULL,
                        `descripcion_promocion` varchar(255) NOT NULL,
                        `tipo_beneficio` enum('porcentaje','monto_fijo') NOT NULL,
                        `valor_beneficio` decimal(12,2) NOT NULL,
                        `descuento_aplicado` decimal(12,2) NOT NULL,
                        `monto_minimo_requerido` decimal(12,2) DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_pdp_pedido` (`pedido_delivery_id`),
                        KEY `idx_pdp_tipo` (`tipo_promocion`),
                        KEY `idx_pdp_promocion` (`promocion_id`),
                        KEY `idx_pdp_promo_especial` (`promocion_especial_id`),
                        KEY `idx_pdp_forma_pago` (`forma_pago_id`),
                        KEY `idx_pdp_cupon` (`codigo_cupon`),
                        CONSTRAINT `{$prefix}fk_pdp_pedido` FOREIGN KEY (`pedido_delivery_id`) REFERENCES `{$prefix}pedidos_delivery` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_pdp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{$prefix}promociones` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{$prefix}promociones_especiales` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdp_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}pedidos_delivery_pagos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `pedido_delivery_id` bigint(20) unsigned NOT NULL,
                        `forma_pago_id` bigint(20) unsigned NOT NULL,
                        `concepto_pago_id` bigint(20) unsigned DEFAULT NULL,
                        `monto_base` decimal(12,2) NOT NULL,
                        `ajuste_porcentaje` decimal(6,2) NOT NULL DEFAULT 0.00,
                        `monto_ajuste` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `monto_final` decimal(12,2) NOT NULL,
                        `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT 0.00,
                        `operacion_origen` enum('venta_original','cambio_pago','pago_agregado','anulacion_sin_reemplazo') NOT NULL DEFAULT 'venta_original',
                        `monto_recibido` decimal(12,2) DEFAULT NULL,
                        `vuelto` decimal(12,2) DEFAULT NULL,
                        `cuotas` tinyint(3) unsigned DEFAULT NULL,
                        `recargo_cuotas_porcentaje` decimal(6,2) DEFAULT NULL,
                        `recargo_cuotas_monto` decimal(12,2) DEFAULT NULL,
                        `monto_cuota` decimal(12,2) DEFAULT NULL,
                        `referencia` varchar(100) DEFAULT NULL,
                        `observaciones` text,
                        `es_cuenta_corriente` tinyint(1) NOT NULL DEFAULT 0,
                        `es_pago_puntos` tinyint(1) NOT NULL DEFAULT 0,
                        `puntos_usados` int(10) unsigned NOT NULL DEFAULT 0,
                        `afecta_caja` tinyint(1) NOT NULL DEFAULT 1,
                        `destino_fondo` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Cobro contra entrega en efectivo: el dinero entra al fondo del repartidor, sin MovimientoCaja (D13)',
                        `repartidor_fondo_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Fondo que recibio el efectivo (si destino_fondo)',
                        `estado` enum('activo','anulado','planificado') NOT NULL DEFAULT 'activo',
                        `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL,
                        `anulado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
                        `anulado_at` timestamp NULL DEFAULT NULL,
                        `motivo_anulacion` varchar(500) DEFAULT NULL,
                        `creado_por_usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL en pagos online acreditados sin operador',
                        `cierre_turno_id` bigint(20) unsigned DEFAULT NULL,
                        `moneda_id` bigint(20) unsigned DEFAULT NULL,
                        `monto_moneda_original` decimal(14,2) DEFAULT NULL,
                        `tipo_cambio_tasa` decimal(14,6) DEFAULT NULL,
                        `tipo_cambio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK logico a tipos_cambio.id',
                        `venta_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a venta_pagos al convertir',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_pdpago_pedido` (`pedido_delivery_id`),
                        KEY `idx_pdpago_forma` (`forma_pago_id`),
                        KEY `idx_pdpago_concepto` (`concepto_pago_id`),
                        KEY `idx_pdpago_mov_caja` (`movimiento_caja_id`),
                        KEY `idx_pdpago_estado` (`estado`),
                        KEY `idx_pdpago_cierre_turno` (`cierre_turno_id`),
                        KEY `idx_pdpago_tipo_cambio` (`tipo_cambio_id`),
                        KEY `idx_pdpago_venta_pago` (`venta_pago_id`),
                        KEY `idx_pdpago_fondo` (`repartidor_fondo_id`),
                        CONSTRAINT `{$prefix}fk_pdpago_pedido` FOREIGN KEY (`pedido_delivery_id`) REFERENCES `{$prefix}pedidos_delivery` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}fk_pdpago_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`),
                        CONSTRAINT `{$prefix}fk_pdpago_concepto` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{$prefix}conceptos_pago` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdpago_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `{$prefix}movimientos_caja` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}fk_pdpago_venta_pago` FOREIGN KEY (`venta_pago_id`) REFERENCES `{$prefix}venta_pagos` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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

            foreach ([
                'pedidos_delivery_pagos',
                'pedido_delivery_promociones',
                'pedido_delivery_detalle_promociones',
                'pedido_delivery_detalle_opcionales',
                'pedidos_delivery_detalle',
                'pedidos_delivery',
            ] as $tabla) {
                try {
                    DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}{$tabla}`");
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
};
