-- BCN Pymes - Tablas Tenant (template con prefijo {{PREFIX}})
-- Generado automáticamente desde la estructura de comercio 1
-- Fecha: 2026-02-18 14:14:15

SET FOREIGN_KEY_CHECKS=0;

-- Tabla: arqueos_tesoreria
DROP TABLE IF EXISTS `{{PREFIX}}arqueos_tesoreria`;
CREATE TABLE `{{PREFIX}}arqueos_tesoreria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tesoreria_id` bigint(20) unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `saldo_sistema` decimal(14,2) NOT NULL,
  `saldo_contado` decimal(14,2) NOT NULL,
  `diferencia` decimal(14,2) NOT NULL DEFAULT '0.00',
  `usuario_id` bigint(20) unsigned NOT NULL,
  `supervisor_id` bigint(20) unsigned DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `arqueos_tesoreria_tesoreria_id_index` (`tesoreria_id`),
  KEY `arqueos_tesoreria_fecha_index` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulo_etiqueta
DROP TABLE IF EXISTS `{{PREFIX}}articulo_etiqueta`;
CREATE TABLE `{{PREFIX}}articulo_etiqueta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `etiqueta_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}articulo_etiqueta_articulo_id_etiqueta_id_unique` (`articulo_id`,`etiqueta_id`),
  KEY `{{PREFIX}}articulo_etiqueta_etiqueta_id_foreign` (`etiqueta_id`),
  CONSTRAINT `{{PREFIX}}articulo_etiqueta_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulo_etiqueta_etiqueta_id_foreign` FOREIGN KEY (`etiqueta_id`) REFERENCES `{{PREFIX}}etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulo_grupo_opcional
DROP TABLE IF EXISTS `{{PREFIX}}articulo_grupo_opcional`;
CREATE TABLE `{{PREFIX}}articulo_grupo_opcional` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `grupo_opcional_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Permite desactivar el grupo para este articulo en esta sucursal sin borrar',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_art_grupo_suc` (`articulo_id`,`grupo_opcional_id`,`sucursal_id`),
  KEY `{{PREFIX}}articulo_grupo_opcional_grupo_opcional_id_foreign` (`grupo_opcional_id`),
  KEY `{{PREFIX}}articulo_grupo_opcional_sucursal_id_index` (`sucursal_id`),
  CONSTRAINT `{{PREFIX}}articulo_grupo_opcional_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulo_grupo_opcional_grupo_opcional_id_foreign` FOREIGN KEY (`grupo_opcional_id`) REFERENCES `{{PREFIX}}grupos_opcionales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulo_grupo_opcional_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulo_grupo_opcional_opcion
DROP TABLE IF EXISTS `{{PREFIX}}articulo_grupo_opcional_opcion`;
CREATE TABLE `{{PREFIX}}articulo_grupo_opcional_opcion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_grupo_opcional_id` bigint(20) unsigned NOT NULL,
  `opcional_id` bigint(20) unsigned NOT NULL,
  `precio_extra` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Precio concreto para esta asignacion. Se copia del template al crear',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Decision del admin: desactivar sin borrar',
  `disponible` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado de stock: false=agotado en esta sucursal',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ago_opcional` (`articulo_grupo_opcional_id`,`opcional_id`),
  KEY `fk_agoo_opcional` (`opcional_id`),
  KEY `{{PREFIX}}articulo_grupo_opcional_opcion_disponible_index` (`disponible`),
  CONSTRAINT `fk_agoo_ago` FOREIGN KEY (`articulo_grupo_opcional_id`) REFERENCES `{{PREFIX}}articulo_grupo_opcional` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_agoo_opcional` FOREIGN KEY (`opcional_id`) REFERENCES `{{PREFIX}}opcionales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulo_sucursal_canal
DROP TABLE IF EXISTS `{{PREFIX}}articulo_sucursal_canal`;
CREATE TABLE `{{PREFIX}}articulo_sucursal_canal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `canal_venta_id` bigint(20) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_art_suc_canal` (`articulo_id`,`sucursal_id`,`canal_venta_id`),
  KEY `{{PREFIX}}articulo_sucursal_canal_sucursal_id_foreign` (`sucursal_id`),
  KEY `{{PREFIX}}articulo_sucursal_canal_canal_venta_id_foreign` (`canal_venta_id`),
  CONSTRAINT `{{PREFIX}}articulo_sucursal_canal_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulo_sucursal_canal_canal_venta_id_foreign` FOREIGN KEY (`canal_venta_id`) REFERENCES `{{PREFIX}}canales_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulo_sucursal_canal_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulos
DROP TABLE IF EXISTS `{{PREFIX}}articulos`;
CREATE TABLE `{{PREFIX}}articulos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único del artículo',
  `codigo_barras` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del artículo',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción detallada',
  `categoria_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Categoría del artículo',
  `unidad_medida` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad' COMMENT 'Unidad de medida',
  `es_materia_prima` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Informativo: indica si es materia prima (para filtrado)',
  `codigo_barra` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código de barras',
  `tipo_iva_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a tipos_iva',
  `precio_iva_incluido` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si los precios incluyen IVA',
  `precio_base` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Precio base sin IVA',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_activo` (`activo`),
  KEY `{{PREFIX}}articulos_tipo_iva_id_foreign` (`tipo_iva_id`),
  KEY `{{PREFIX}}articulos_codigo_barras_index` (`codigo_barras`),
  CONSTRAINT `{{PREFIX}}articulos_tipo_iva_id_foreign` FOREIGN KEY (`tipo_iva_id`) REFERENCES `{{PREFIX}}tipos_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: articulos_sucursales
DROP TABLE IF EXISTS `{{PREFIX}}articulos_sucursales`;
CREATE TABLE `{{PREFIX}}articulos_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `modo_stock` enum('ninguno','unitario','receta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ninguno' COMMENT 'Modo de control de stock: ninguno, unitario (descuenta articulo), receta (descuenta ingredientes)',
  `vendible` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si aparece en pantalla de ventas',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_articulo_sucursal` (`articulo_id`,`sucursal_id`),
  KEY `{{PREFIX}}articulos_sucursales_sucursal_id_foreign` (`sucursal_id`),
  CONSTRAINT `{{PREFIX}}articulos_sucursales_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}articulos_sucursales_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cajas
DROP TABLE IF EXISTS `{{PREFIX}}cajas`;
CREATE TABLE `{{PREFIX}}cajas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `numero` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indica si la caja está activa',
  `limite_efectivo` decimal(12,2) DEFAULT NULL COMMENT 'Límite máximo de efectivo en caja',
  `modo_carga_inicial` enum('manual','ultimo_cierre','monto_fijo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT 'Forma de carga inicial de cada turno',
  `monto_fijo_inicial` decimal(12,2) DEFAULT NULL COMMENT 'Monto fijo para carga inicial (si modo es monto_fijo)',
  `grupo_cierre_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_inicial` decimal(12,2) DEFAULT '0.00',
  `saldo_actual` decimal(12,2) DEFAULT '0.00',
  `fecha_apertura` timestamp NULL DEFAULT NULL,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `usuario_apertura_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_cierre_id` bigint(20) unsigned DEFAULT NULL,
  `estado` enum('abierta','cerrada') COLLATE utf8mb4_unicode_ci DEFAULT 'cerrada',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cajas_sucursal_numero_unique` (`sucursal_id`,`numero`),
  KEY `idx_estado` (`estado`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `{{PREFIX}}cajas_grupo_cierre_id_foreign` (`grupo_cierre_id`),
  CONSTRAINT `{{PREFIX}}cajas_grupo_cierre_id_foreign` FOREIGN KEY (`grupo_cierre_id`) REFERENCES `{{PREFIX}}grupos_cierre` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{{PREFIX}}cajas_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: canales_venta
DROP TABLE IF EXISTS `{{PREFIX}}canales_venta`;
CREATE TABLE `{{PREFIX}}canales_venta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del canal',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código alfanumérico',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: categorias
DROP TABLE IF EXISTS `{{PREFIX}}categorias`;
CREATE TABLE `{{PREFIX}}categorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la categoría',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código alfanumérico opcional',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción de la categoría',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Color en hex para UI (#FF5733)',
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del icono (ej: heroicon-o-tag)',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activa',
  `tipo_iva_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Tipo de IVA por defecto para conceptos de esta categoría',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_activo` (`activo`),
  KEY `{{PREFIX}}categorias_tipo_iva_id_foreign` (`tipo_iva_id`),
  CONSTRAINT `{{PREFIX}}categorias_tipo_iva_id_foreign` FOREIGN KEY (`tipo_iva_id`) REFERENCES `{{PREFIX}}tipos_iva` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cierre_turno_cajas
DROP TABLE IF EXISTS `{{PREFIX}}cierre_turno_cajas`;
CREATE TABLE `{{PREFIX}}cierre_turno_cajas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cierre_turno_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned NOT NULL,
  `caja_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la caja al momento del cierre',
  `saldo_inicial` decimal(14,2) NOT NULL DEFAULT '0.00',
  `saldo_final` decimal(14,2) NOT NULL DEFAULT '0.00',
  `saldo_sistema` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo calculado por el sistema',
  `saldo_declarado` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo declarado por el usuario',
  `total_ingresos` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_egresos` decimal(14,2) NOT NULL DEFAULT '0.00',
  `diferencia` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Positivo = sobrante, Negativo = faltante',
  `desglose_formas_pago` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON con desglose por forma de pago',
  `desglose_conceptos` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON con desglose por concepto',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}cierre_turno_cajas_cierre_turno_id_caja_id_unique` (`cierre_turno_id`,`caja_id`),
  KEY `{{PREFIX}}cierre_turno_cajas_caja_id_index` (`caja_id`),
  CONSTRAINT `{{PREFIX}}cierre_turno_cajas_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}cierre_turno_cajas_cierre_turno_id_foreign` FOREIGN KEY (`cierre_turno_id`) REFERENCES `{{PREFIX}}cierres_turno` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cierres_turno
DROP TABLE IF EXISTS `{{PREFIX}}cierres_turno`;
CREATE TABLE `{{PREFIX}}cierres_turno` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `grupo_cierre_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL si fue cierre individual',
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'Usuario que realizó el cierre',
  `tipo` enum('individual','grupo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `fecha_apertura` datetime DEFAULT NULL COMMENT 'Fecha/hora de apertura más antigua del turno',
  `fecha_cierre` datetime NOT NULL COMMENT 'Fecha/hora del cierre',
  `total_saldo_inicial` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Suma de saldos iniciales',
  `total_saldo_final` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Suma de saldos finales',
  `total_ingresos` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Suma de ingresos',
  `total_egresos` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Suma de egresos',
  `total_diferencia` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Diferencia total (faltante/sobrante)',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `revertido` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_reversion` timestamp NULL DEFAULT NULL,
  `usuario_reversion_id` bigint(20) unsigned DEFAULT NULL,
  `motivo_reversion` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}cierres_turno_sucursal_id_fecha_cierre_index` (`sucursal_id`,`fecha_cierre`),
  KEY `{{PREFIX}}cierres_turno_usuario_id_index` (`usuario_id`),
  KEY `{{PREFIX}}cierres_turno_grupo_cierre_id_index` (`grupo_cierre_id`),
  KEY `{{PREFIX}}cierres_turno_tipo_index` (`tipo`),
  CONSTRAINT `{{PREFIX}}cierres_turno_grupo_cierre_id_foreign` FOREIGN KEY (`grupo_cierre_id`) REFERENCES `{{PREFIX}}grupos_cierre` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{{PREFIX}}cierres_turno_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: clientes
DROP TABLE IF EXISTS `{{PREFIX}}clientes`;
CREATE TABLE `{{PREFIX}}clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion_iva_id` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Lista de precios asignada al cliente',
  `tiene_cuenta_corriente` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si el cliente puede comprar a crédito',
  `limite_credito` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Límite máximo de crédito (0 = sin límite)',
  `dias_credito` int(10) unsigned NOT NULL DEFAULT '30' COMMENT 'Días de crédito por defecto para nuevas ventas',
  `tasa_interes_mensual` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Tasa de interés mensual por mora (%)',
  `saldo_deudor_cache` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Cache: suma de ventas.saldo_pendiente_cache del cliente',
  `saldo_a_favor_cache` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Cache: saldo a favor del cliente (crédito disponible)',
  `ultimo_movimiento_cc_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha del último movimiento en cuenta corriente',
  `bloqueado_por_mora` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si está bloqueado por mora',
  `dias_mora_max` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Máximos días de mora actual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_cuit` (`cuit`),
  KEY `idx_lista_precio` (`lista_precio_id`),
  KEY `idx_cli_cc` (`tiene_cuenta_corriente`),
  KEY `idx_cli_saldo_deudor` (`saldo_deudor_cache`),
  KEY `idx_cli_bloqueado_mora` (`bloqueado_por_mora`),
  CONSTRAINT `fk_clientes_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{{PREFIX}}listas_precios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: clientes_sucursales
DROP TABLE IF EXISTS `{{PREFIX}}clientes_sucursales`;
CREATE TABLE `{{PREFIX}}clientes_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
  `descuento_porcentaje` decimal(5,2) DEFAULT '0.00',
  `limite_credito` decimal(12,2) DEFAULT '0.00',
  `saldo_actual` decimal(12,2) DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_sucursal` (`cliente_id`,`sucursal_id`),
  KEY `{{PREFIX}}clientes_sucursales_sucursal_id_foreign` (`sucursal_id`),
  CONSTRAINT `{{PREFIX}}clientes_sucursales_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `{{PREFIX}}clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}clientes_sucursales_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cobro_pagos
DROP TABLE IF EXISTS `{{PREFIX}}cobro_pagos`;
CREATE TABLE `{{PREFIX}}cobro_pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cobro_id` bigint(20) unsigned NOT NULL,
  `forma_pago_id` bigint(20) unsigned NOT NULL,
  `concepto_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Concepto usado (para formas mixtas)',
  `monto_base` decimal(12,2) NOT NULL COMMENT 'Monto antes de ajustes',
  `ajuste_porcentaje` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Ajuste aplicado (+ recargo, - descuento)',
  `monto_ajuste` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto del ajuste',
  `monto_final` decimal(12,2) NOT NULL COMMENT 'Monto final después de ajustes',
  `monto_recibido` decimal(12,2) DEFAULT NULL,
  `vuelto` decimal(12,2) DEFAULT NULL,
  `cuotas` tinyint(3) unsigned DEFAULT NULL,
  `recargo_cuotas_porcentaje` decimal(6,2) DEFAULT NULL,
  `recargo_cuotas_monto` decimal(12,2) DEFAULT NULL,
  `monto_cuota` decimal(12,2) DEFAULT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nro autorización, voucher, etc',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `afecta_caja` tinyint(1) NOT NULL DEFAULT '1',
  `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL,
  `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cierre de turno donde se procesó este pago',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cp_concepto` (`concepto_pago_id`),
  KEY `fk_cp_mov_caja` (`movimiento_caja_id`),
  KEY `idx_cp_cobro` (`cobro_id`),
  KEY `idx_cp_forma_pago` (`forma_pago_id`),
  KEY `idx_cp_estado` (`estado`),
  KEY `idx_cobro_pagos_cierre_turno` (`cierre_turno_id`),
  CONSTRAINT `fk_cp_cobro` FOREIGN KEY (`cobro_id`) REFERENCES `{{PREFIX}}cobros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_concepto` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{{PREFIX}}conceptos_pago` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`),
  CONSTRAINT `fk_cp_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `{{PREFIX}}movimientos_caja` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cobro_ventas
DROP TABLE IF EXISTS `{{PREFIX}}cobro_ventas`;
CREATE TABLE `{{PREFIX}}cobro_ventas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cobro_id` bigint(20) unsigned NOT NULL,
  `venta_id` bigint(20) unsigned NOT NULL,
  `venta_pago_id` bigint(20) unsigned DEFAULT NULL,
  `monto_aplicado` decimal(12,2) NOT NULL COMMENT 'Monto del cobro aplicado a esta venta',
  `interes_aplicado` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Interés cobrado por esta venta',
  `saldo_anterior` decimal(12,2) NOT NULL COMMENT 'Saldo pendiente de la venta antes del cobro',
  `saldo_posterior` decimal(12,2) NOT NULL COMMENT 'Saldo pendiente de la venta después del cobro',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cv` (`cobro_id`,`venta_id`),
  KEY `idx_cv_cobro` (`cobro_id`),
  KEY `idx_cv_venta` (`venta_id`),
  KEY `idx_venta_pago` (`venta_pago_id`),
  CONSTRAINT `fk_cv_cobro` FOREIGN KEY (`cobro_id`) REFERENCES `{{PREFIX}}cobros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_venta` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cobros
DROP TABLE IF EXISTS `{{PREFIX}}cobros`;
CREATE TABLE `{{PREFIX}}cobros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Caja donde se registró el cobro',
  `numero_recibo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número de recibo de cobro',
  `tipo` enum('cobro','anticipo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cobro',
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `monto_cobrado` decimal(12,2) NOT NULL COMMENT 'Monto total cobrado',
  `interes_aplicado` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Interés cobrado (calculado al momento del cobro)',
  `descuento_aplicado` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Descuento por pronto pago u otro',
  `monto_aplicado_a_deuda` decimal(12,2) NOT NULL COMMENT 'Monto que se aplicó a cancelar deuda',
  `monto_a_favor` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto que quedó a favor del cliente',
  `saldo_favor_usado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'Usuario que registró el cobro',
  `anulado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `anulado_at` timestamp NULL DEFAULT NULL,
  `motivo_anulacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cierre de turno donde se registró el cobro',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cobros_recibo` (`sucursal_id`,`numero_recibo`),
  KEY `fk_cobros_caja` (`caja_id`),
  KEY `idx_cobros_sucursal_fecha` (`sucursal_id`,`fecha`),
  KEY `idx_cobros_cliente` (`cliente_id`),
  KEY `idx_cobros_estado` (`estado`),
  KEY `idx_cobros_fecha` (`fecha`),
  KEY `idx_cobros_cierre_turno` (`cierre_turno_id`),
  CONSTRAINT `fk_cobros_caja` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cobros_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{{PREFIX}}clientes` (`id`),
  CONSTRAINT `fk_cobros_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: compras
DROP TABLE IF EXISTS `{{PREFIX}}compras`;
CREATE TABLE `{{PREFIX}}compras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `proveedor_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `iva` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `forma_pago` enum('efectivo','tarjeta','transferencia','cheque','cuenta_corriente') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'completada',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `{{PREFIX}}compras_proveedor_id_foreign` (`proveedor_id`),
  KEY `{{PREFIX}}compras_caja_id_foreign` (`caja_id`),
  CONSTRAINT `{{PREFIX}}compras_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`),
  CONSTRAINT `{{PREFIX}}compras_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `{{PREFIX}}proveedores` (`id`),
  CONSTRAINT `{{PREFIX}}compras_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: compras_detalle
DROP TABLE IF EXISTS `{{PREFIX}}compras_detalle`;
CREATE TABLE `{{PREFIX}}compras_detalle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `compra_id` bigint(20) unsigned NOT NULL,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
  `iva_monto` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_compra` (`compra_id`),
  KEY `{{PREFIX}}compras_detalle_articulo_id_foreign` (`articulo_id`),
  CONSTRAINT `{{PREFIX}}compras_detalle_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`),
  CONSTRAINT `{{PREFIX}}compras_detalle_compra_id_foreign` FOREIGN KEY (`compra_id`) REFERENCES `{{PREFIX}}compras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: comprobante_fiscal_items
DROP TABLE IF EXISTS `{{PREFIX}}comprobante_fiscal_items`;
CREATE TABLE `{{PREFIX}}comprobante_fiscal_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_fiscal_id` bigint(20) unsigned NOT NULL,
  `venta_detalle_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK al ítem de venta (si aplica)',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código del artículo',
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción del artículo/servicio',
  `cantidad` decimal(12,4) NOT NULL,
  `unidad_medida` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'u' COMMENT 'Código unidad de medida AFIP',
  `precio_unitario` decimal(12,4) NOT NULL COMMENT 'Precio unitario neto',
  `bonificacion` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Descuento/bonificación',
  `subtotal` decimal(12,2) NOT NULL COMMENT 'Subtotal neto',
  `iva_codigo_afip` tinyint(3) unsigned NOT NULL COMMENT 'Código AFIP de la alícuota',
  `iva_alicuota` decimal(5,2) NOT NULL COMMENT 'Porcentaje de IVA',
  `iva_importe` decimal(12,2) NOT NULL COMMENT 'Importe de IVA del ítem',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cfitems_comprobante` (`comprobante_fiscal_id`),
  KEY `idx_cfitems_venta_detalle` (`venta_detalle_id`),
  CONSTRAINT `fk_cfitems_comprobante` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `{{PREFIX}}comprobantes_fiscales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cfitems_venta_detalle` FOREIGN KEY (`venta_detalle_id`) REFERENCES `{{PREFIX}}ventas_detalle` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: comprobante_fiscal_iva
DROP TABLE IF EXISTS `{{PREFIX}}comprobante_fiscal_iva`;
CREATE TABLE `{{PREFIX}}comprobante_fiscal_iva` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_fiscal_id` bigint(20) unsigned NOT NULL,
  `codigo_afip` tinyint(3) unsigned NOT NULL COMMENT 'Código AFIP: 3=0%, 4=10.5%, 5=21%, 6=27%, 8=5%, 9=2.5%',
  `alicuota` decimal(5,2) NOT NULL COMMENT 'Porcentaje de IVA',
  `base_imponible` decimal(12,2) NOT NULL COMMENT 'Base imponible para esta alícuota',
  `importe` decimal(12,2) NOT NULL COMMENT 'Importe de IVA',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cfi_alicuota` (`comprobante_fiscal_id`,`codigo_afip`),
  KEY `idx_cfi_comprobante` (`comprobante_fiscal_id`),
  CONSTRAINT `fk_cfi_comprobante` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `{{PREFIX}}comprobantes_fiscales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: comprobante_fiscal_ventas
DROP TABLE IF EXISTS `{{PREFIX}}comprobante_fiscal_ventas`;
CREATE TABLE `{{PREFIX}}comprobante_fiscal_ventas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_fiscal_id` bigint(20) unsigned NOT NULL,
  `venta_id` bigint(20) unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL COMMENT 'Monto de la venta incluido en este comprobante',
  `es_anulacion` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True si el comprobante anula (NC) esta venta',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cfv` (`comprobante_fiscal_id`,`venta_id`),
  KEY `idx_cfv_comprobante` (`comprobante_fiscal_id`),
  KEY `idx_cfv_venta` (`venta_id`),
  CONSTRAINT `fk_cfv_comprobante` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `{{PREFIX}}comprobantes_fiscales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cfv_venta` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: comprobantes_fiscales
DROP TABLE IF EXISTS `{{PREFIX}}comprobantes_fiscales`;
CREATE TABLE `{{PREFIX}}comprobantes_fiscales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `punto_venta_id` bigint(20) unsigned NOT NULL,
  `cuit_id` bigint(20) unsigned NOT NULL COMMENT 'CUIT emisor del comprobante',
  `tipo` enum('factura_a','factura_b','factura_c','factura_e','factura_m','nota_credito_a','nota_credito_b','nota_credito_c','nota_credito_e','nota_credito_m','nota_debito_a','nota_debito_b','nota_debito_c','nota_debito_e','nota_debito_m','recibo_a','recibo_b','recibo_c') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de comprobante fiscal',
  `letra` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Letra del comprobante (A, B, C, E, M)',
  `punto_venta_numero` int(10) unsigned NOT NULL COMMENT 'Número del punto de venta',
  `numero_comprobante` bigint(20) unsigned NOT NULL COMMENT 'Número del comprobante',
  `cae` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CAE otorgado por AFIP',
  `cae_vencimiento` date DEFAULT NULL COMMENT 'Fecha de vencimiento del CAE',
  `fecha_emision` date NOT NULL,
  `fecha_servicio_desde` date DEFAULT NULL COMMENT 'Fecha desde (para servicios)',
  `fecha_servicio_hasta` date DEFAULT NULL COMMENT 'Fecha hasta (para servicios)',
  `cliente_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cliente asociado (puede ser diferente al de la venta)',
  `condicion_iva_id` bigint(20) unsigned NOT NULL COMMENT 'Condición de IVA del receptor (ref: config.condiciones_iva)',
  `receptor_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre/Razón social del receptor',
  `receptor_documento_tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CUIT' COMMENT 'Tipo de documento (CUIT, DNI, etc.)',
  `receptor_documento_numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número de documento del receptor',
  `receptor_domicilio` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `neto_gravado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `neto_no_gravado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `neto_exento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `iva_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tributos` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Otros tributos (percepciones, etc.)',
  `total` decimal(12,2) NOT NULL,
  `moneda` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PES' COMMENT 'Código de moneda AFIP',
  `cotizacion` decimal(12,6) NOT NULL DEFAULT '1.000000' COMMENT 'Cotización de la moneda',
  `estado` enum('pendiente','autorizado','rechazado','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente' COMMENT 'Estado ante AFIP',
  `afip_response` text COLLATE utf8mb4_unicode_ci COMMENT 'Respuesta completa de AFIP (JSON)',
  `afip_observaciones` text COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones de AFIP',
  `afip_errores` text COLLATE utf8mb4_unicode_ci COMMENT 'Errores de AFIP',
  `comprobante_asociado_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a comprobante original (para NC/ND)',
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'Usuario que emitió el comprobante',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `es_total_venta` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'true = factura por total de venta, false = factura parcial (mixto)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cf_numero` (`punto_venta_id`,`tipo`,`numero_comprobante`),
  KEY `fk_cf_cuit` (`cuit_id`),
  KEY `fk_cf_comprobante_asociado` (`comprobante_asociado_id`),
  KEY `idx_cf_sucursal_fecha` (`sucursal_id`,`fecha_emision`),
  KEY `idx_cf_cliente` (`cliente_id`),
  KEY `idx_cf_cae` (`cae`),
  KEY `idx_cf_estado` (`estado`),
  KEY `idx_cf_tipo` (`tipo`),
  KEY `idx_cf_receptor_doc` (`receptor_documento_numero`),
  CONSTRAINT `fk_cf_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{{PREFIX}}clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cf_comprobante_asociado` FOREIGN KEY (`comprobante_asociado_id`) REFERENCES `{{PREFIX}}comprobantes_fiscales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cf_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{{PREFIX}}cuits` (`id`),
  CONSTRAINT `fk_cf_punto_venta` FOREIGN KEY (`punto_venta_id`) REFERENCES `{{PREFIX}}puntos_venta` (`id`),
  CONSTRAINT `fk_cf_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: conceptos_pago
DROP TABLE IF EXISTS `{{PREFIX}}conceptos_pago`;
CREATE TABLE `{{PREFIX}}conceptos_pago` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `permite_cuotas` tinyint(1) NOT NULL DEFAULT '0',
  `permite_vuelto` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}conceptos_pago_codigo_unique` (`codigo`),
  KEY `{{PREFIX}}conceptos_pago_activo_index` (`activo`),
  KEY `{{PREFIX}}conceptos_pago_orden_index` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: configuracion_impresion
DROP TABLE IF EXISTS `{{PREFIX}}configuracion_impresion`;
CREATE TABLE `{{PREFIX}}configuracion_impresion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `impresion_automatica_venta` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Imprimir ticket automaticamente',
  `impresion_automatica_factura` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Imprimir factura automaticamente',
  `abrir_cajon_efectivo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Abrir cajon con pagos en efectivo',
  `cortar_papel_automatico` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Corte automatico en termicas',
  `logo_ticket_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ruta al logo para tickets',
  `texto_pie_ticket` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto al pie del ticket',
  `texto_legal_factura` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto legal para facturas',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_impresion_sucursal` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cuentas_bancarias
DROP TABLE IF EXISTS `{{PREFIX}}cuentas_bancarias`;
CREATE TABLE `{{PREFIX}}cuentas_bancarias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `banco` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_cuenta` enum('corriente','ahorro','caja_ahorro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'corriente',
  `numero_cuenta` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cbu` varchar(22) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARS',
  `saldo_actual` decimal(14,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuentas_bancarias_sucursal_id_index` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cuit_sucursal
DROP TABLE IF EXISTS `{{PREFIX}}cuit_sucursal`;
CREATE TABLE `{{PREFIX}}cuit_sucursal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cuit_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si es el CUIT principal de la sucursal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cuit_sucursal` (`cuit_id`,`sucursal_id`),
  KEY `idx_cuit_sucursal_sucursal` (`sucursal_id`),
  CONSTRAINT `{{PREFIX}}cuit_sucursal_cuit_id_foreign` FOREIGN KEY (`cuit_id`) REFERENCES `{{PREFIX}}cuits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}cuit_sucursal_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cuits
DROP TABLE IF EXISTS `{{PREFIX}}cuits`;
CREATE TABLE `{{PREFIX}}cuits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero_cuit` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CUIT sin guiones, 11 dígitos',
  `razon_social` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_fantasia` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `localidad_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a config.localidades',
  `condicion_iva_id` bigint(20) unsigned NOT NULL COMMENT 'FK a config.condiciones_iva',
  `numero_iibb` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de Ingresos Brutos',
  `fecha_inicio_actividades` date DEFAULT NULL,
  `fecha_vencimiento_certificado` date DEFAULT NULL,
  `entorno_afip` enum('testing','produccion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'testing',
  `certificado_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path al certificado AFIP encriptado',
  `clave_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path a la clave privada encriptada',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}cuits_numero_cuit_unique` (`numero_cuit`),
  KEY `idx_cuits_numero` (`numero_cuit`),
  KEY `idx_cuits_activo` (`activo`),
  KEY `idx_cuits_condicion_iva` (`condicion_iva_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: depositos_bancarios
DROP TABLE IF EXISTS `{{PREFIX}}depositos_bancarios`;
CREATE TABLE `{{PREFIX}}depositos_bancarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tesoreria_id` bigint(20) unsigned NOT NULL,
  `cuenta_bancaria_id` bigint(20) unsigned NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_deposito` date NOT NULL,
  `numero_comprobante` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `estado` enum('pendiente','confirmado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `fecha_confirmacion` timestamp NULL DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `depositos_bancarios_tesoreria_id_index` (`tesoreria_id`),
  KEY `depositos_bancarios_fecha_deposito_index` (`fecha_deposito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: empresa_config
DROP TABLE IF EXISTS `{{PREFIX}}empresa_config`;
CREATE TABLE `{{PREFIX}}empresa_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: etiquetas
DROP TABLE IF EXISTS `{{PREFIX}}etiquetas`;
CREATE TABLE `{{PREFIX}}etiquetas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grupo_etiqueta_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}etiquetas_grupo_etiqueta_id_codigo_unique` (`grupo_etiqueta_id`,`codigo`),
  CONSTRAINT `{{PREFIX}}etiquetas_grupo_etiqueta_id_foreign` FOREIGN KEY (`grupo_etiqueta_id`) REFERENCES `{{PREFIX}}grupos_etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: forma_pago_conceptos
DROP TABLE IF EXISTS `{{PREFIX}}forma_pago_conceptos`;
CREATE TABLE `{{PREFIX}}forma_pago_conceptos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `forma_pago_id` bigint(20) unsigned NOT NULL,
  `concepto_pago_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forma_pago_concepto_unique` (`forma_pago_id`,`concepto_pago_id`),
  KEY `{{PREFIX}}forma_pago_conceptos_concepto_pago_id_foreign` (`concepto_pago_id`),
  CONSTRAINT `{{PREFIX}}forma_pago_conceptos_concepto_pago_id_foreign` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{{PREFIX}}conceptos_pago` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}forma_pago_conceptos_forma_pago_id_foreign` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: formas_pago
DROP TABLE IF EXISTS `{{PREFIX}}formas_pago`;
CREATE TABLE `{{PREFIX}}formas_pago` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la forma de pago',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código alfanumérico',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción',
  `concepto_pago_id` bigint(20) unsigned DEFAULT NULL,
  `es_mixta` tinyint(1) NOT NULL DEFAULT '0',
  `concepto` enum('efectivo','tarjeta_debito','tarjeta_credito','transferencia','wallet','cheque','credito_cliente','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'otro',
  `permite_cuotas` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si permite pago en cuotas',
  `ajuste_porcentaje` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Ajuste porcentual: positivo=recargo, negativo=descuento',
  `factura_fiscal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si esta forma de pago genera factura fiscal por defecto',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_concepto` (`concepto`),
  KEY `idx_activo` (`activo`),
  KEY `{{PREFIX}}formas_pago_concepto_pago_id_foreign` (`concepto_pago_id`),
  KEY `{{PREFIX}}formas_pago_es_mixta_index` (`es_mixta`),
  CONSTRAINT `{{PREFIX}}formas_pago_concepto_pago_id_foreign` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{{PREFIX}}conceptos_pago` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: formas_pago_cuotas
DROP TABLE IF EXISTS `{{PREFIX}}formas_pago_cuotas`;
CREATE TABLE `{{PREFIX}}formas_pago_cuotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `forma_pago_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = aplica a todas las sucursales',
  `cantidad_cuotas` int(11) NOT NULL COMMENT 'Cantidad de cuotas (1, 3, 6, 12, etc.)',
  `recargo_porcentaje` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Recargo porcentual (0 = sin interés)',
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción opcional del plan de cuotas',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_formas_pago_cuotas_sucursal` (`sucursal_id`),
  KEY `idx_forma_sucursal_activo` (`forma_pago_id`,`sucursal_id`,`activo`),
  KEY `idx_cantidad_cuotas` (`cantidad_cuotas`),
  CONSTRAINT `fk_formas_pago_cuotas_forma` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_formas_pago_cuotas_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: formas_pago_cuotas_sucursales
DROP TABLE IF EXISTS `{{PREFIX}}formas_pago_cuotas_sucursales`;
CREATE TABLE `{{PREFIX}}formas_pago_cuotas_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `forma_pago_cuota_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `recargo_porcentaje` decimal(5,2) DEFAULT NULL COMMENT 'Recargo específico para esta sucursal. NULL = usar el del plan general',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si este plan de cuotas está activo en esta sucursal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cuota_sucursal` (`forma_pago_cuota_id`,`sucursal_id`),
  KEY `idx_cuota_sucursal_activo` (`sucursal_id`,`activo`),
  CONSTRAINT `fk_cuotas_sucursales_cuota` FOREIGN KEY (`forma_pago_cuota_id`) REFERENCES `{{PREFIX}}formas_pago_cuotas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cuotas_sucursales_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: formas_pago_sucursales
DROP TABLE IF EXISTS `{{PREFIX}}formas_pago_sucursales`;
CREATE TABLE `{{PREFIX}}formas_pago_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `forma_pago_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está disponible en esta sucursal',
  `ajuste_porcentaje` decimal(8,2) DEFAULT NULL COMMENT 'Ajuste porcentual específico para esta sucursal: positivo=recargo, negativo=descuento. NULL = usar el de la forma de pago',
  `factura_fiscal` tinyint(1) DEFAULT NULL COMMENT 'Factura fiscal específico para esta sucursal (null = usar el de empresa)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_forma_pago_sucursal` (`forma_pago_id`,`sucursal_id`),
  KEY `idx_sucursal_activo` (`sucursal_id`,`activo`),
  CONSTRAINT `fk_formas_pago_sucursales_forma` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_formas_pago_sucursales_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: formas_venta
DROP TABLE IF EXISTS `{{PREFIX}}formas_venta`;
CREATE TABLE `{{PREFIX}}formas_venta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la forma de venta',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código alfanumérico',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: grupos_cierre
DROP TABLE IF EXISTS `{{PREFIX}}grupos_cierre`;
CREATE TABLE `{{PREFIX}}grupos_cierre` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre descriptivo del grupo',
  `fondo_comun` tinyint(1) NOT NULL DEFAULT '0',
  `saldo_fondo_comun` decimal(14,2) DEFAULT '0.00',
  `tesoreria_id` bigint(20) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}grupos_cierre_sucursal_id_activo_index` (`sucursal_id`,`activo`),
  CONSTRAINT `{{PREFIX}}grupos_cierre_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: grupos_etiquetas
DROP TABLE IF EXISTS `{{PREFIX}}grupos_etiquetas`;
CREATE TABLE `{{PREFIX}}grupos_etiquetas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}grupos_etiquetas_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: grupos_opcionales
DROP TABLE IF EXISTS `{{PREFIX}}grupos_opcionales`;
CREATE TABLE `{{PREFIX}}grupos_opcionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `obligatorio` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si el cliente DEBE elegir',
  `tipo` enum('seleccionable','cuantitativo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seleccionable' COMMENT 'seleccionable=si/no por opcion, cuantitativo=cantidad por opcion',
  `min_seleccion` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Minimo de opciones/cantidad total',
  `max_seleccion` int(10) unsigned DEFAULT NULL COMMENT 'Maximo (null=sin limite)',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}grupos_opcionales_activo_index` (`activo`),
  KEY `{{PREFIX}}grupos_opcionales_orden_index` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: impresora_sucursal_caja
DROP TABLE IF EXISTS `{{PREFIX}}impresora_sucursal_caja`;
CREATE TABLE `{{PREFIX}}impresora_sucursal_caja` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `impresora_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = aplica a toda la sucursal',
  `es_defecto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si es la impresora por defecto',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_impresora_sucursal_caja` (`impresora_id`,`sucursal_id`,`caja_id`),
  KEY `idx_isc_impresora` (`impresora_id`),
  KEY `idx_isc_sucursal` (`sucursal_id`),
  KEY `idx_isc_caja` (`caja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: impresora_tipo_documento
DROP TABLE IF EXISTS `{{PREFIX}}impresora_tipo_documento`;
CREATE TABLE `{{PREFIX}}impresora_tipo_documento` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `impresora_sucursal_caja_id` bigint(20) unsigned NOT NULL,
  `tipo_documento` enum('ticket_venta','factura_a','factura_b','factura_c','comanda','precuenta','cierre_turno','cierre_caja','arqueo','recibo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `copias` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_impresora_tipo_doc` (`impresora_sucursal_caja_id`,`tipo_documento`),
  KEY `idx_itd_asignacion` (`impresora_sucursal_caja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: impresoras
DROP TABLE IF EXISTS `{{PREFIX}}impresoras`;
CREATE TABLE `{{PREFIX}}impresoras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre amigable de la impresora',
  `nombre_sistema` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre exacto devuelto por QZ Tray',
  `tipo` enum('termica','laser_inkjet') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'termica',
  `formato_papel` enum('80mm','58mm','a4','carta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '80mm',
  `ancho_caracteres` tinyint(3) unsigned NOT NULL DEFAULT '48' COMMENT 'Caracteres por línea',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `configuracion` text COLLATE utf8mb4_unicode_ci COMMENT 'Config adicional: cortador, cajon, etc. (JSON)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_impresoras_activa` (`activa`),
  KEY `idx_impresoras_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: lista_precio_articulos
DROP TABLE IF EXISTS `{{PREFIX}}lista_precio_articulos`;
CREATE TABLE `{{PREFIX}}lista_precio_articulos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lista_precio_id` bigint(20) unsigned NOT NULL,
  `articulo_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID del artículo específico',
  `categoria_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID de la categoría (aplica a todos sus artículos)',
  `precio_fijo` decimal(12,2) DEFAULT NULL COMMENT 'Precio fijo que pisa al precio base (opcional)',
  `ajuste_porcentaje` decimal(8,2) DEFAULT NULL COMMENT 'Porcentaje de ajuste sobre precio base (+ recargo, - descuento)',
  `precio_base_original` decimal(12,2) DEFAULT NULL COMMENT 'Precio base del artículo al momento de crear el registro',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lista_articulo` (`lista_precio_id`,`articulo_id`),
  UNIQUE KEY `unique_lista_categoria` (`lista_precio_id`,`categoria_id`),
  KEY `idx_lista_precio` (`lista_precio_id`),
  KEY `idx_articulo` (`articulo_id`),
  KEY `idx_categoria` (`categoria_id`),
  CONSTRAINT `fk_lp_art_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_art_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `{{PREFIX}}categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_art_lista` FOREIGN KEY (`lista_precio_id`) REFERENCES `{{PREFIX}}listas_precios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: lista_precio_condiciones
DROP TABLE IF EXISTS `{{PREFIX}}lista_precio_condiciones`;
CREATE TABLE `{{PREFIX}}lista_precio_condiciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lista_precio_id` bigint(20) unsigned NOT NULL,
  `tipo_condicion` enum('por_forma_pago','por_forma_venta','por_canal','por_total_compra') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de condición a evaluar',
  `forma_pago_id` bigint(20) unsigned DEFAULT NULL,
  `forma_venta_id` bigint(20) unsigned DEFAULT NULL,
  `canal_venta_id` bigint(20) unsigned DEFAULT NULL,
  `monto_minimo` decimal(12,2) DEFAULT NULL COMMENT 'Monto mínimo de compra',
  `monto_maximo` decimal(12,2) DEFAULT NULL COMMENT 'Monto máximo de compra',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lista_tipo` (`lista_precio_id`,`tipo_condicion`),
  KEY `idx_forma_pago` (`forma_pago_id`),
  KEY `idx_forma_venta` (`forma_venta_id`),
  KEY `idx_canal_venta` (`canal_venta_id`),
  CONSTRAINT `fk_lp_cond_canal` FOREIGN KEY (`canal_venta_id`) REFERENCES `{{PREFIX}}canales_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_cond_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_cond_forma_venta` FOREIGN KEY (`forma_venta_id`) REFERENCES `{{PREFIX}}formas_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_cond_lista` FOREIGN KEY (`lista_precio_id`) REFERENCES `{{PREFIX}}listas_precios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: listas_precios
DROP TABLE IF EXISTS `{{PREFIX}}listas_precios`;
CREATE TABLE `{{PREFIX}}listas_precios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL COMMENT 'Sucursal a la que pertenece',
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la lista',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código único por sucursal',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción detallada',
  `ajuste_porcentaje` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Porcentaje de ajuste global (+ recargo, - descuento)',
  `redondeo` enum('ninguno','entero','decena','centena') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ninguno' COMMENT 'Tipo de redondeo a aplicar en precios',
  `aplica_promociones` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si permite aplicar promociones',
  `promociones_alcance` enum('todos','excluir_lista') COLLATE utf8mb4_unicode_ci DEFAULT 'todos',
  `vigencia_desde` date DEFAULT NULL COMMENT 'Fecha desde la cual aplica',
  `vigencia_hasta` date DEFAULT NULL COMMENT 'Fecha hasta la cual aplica',
  `dias_semana` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON: Días de semana [0-6] donde 0=Domingo',
  `hora_desde` time DEFAULT NULL COMMENT 'Hora desde la cual aplica',
  `hora_hasta` time DEFAULT NULL COMMENT 'Hora hasta la cual aplica',
  `cantidad_minima` decimal(12,3) DEFAULT NULL COMMENT 'Cantidad mínima para que aplique',
  `cantidad_maxima` decimal(12,3) DEFAULT NULL COMMENT 'Cantidad máxima para que aplique',
  `es_lista_base` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si es la lista base obligatoria de la sucursal',
  `prioridad` int(11) NOT NULL DEFAULT '100' COMMENT 'Prioridad (menor número = mayor prioridad)',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sucursal_codigo` (`sucursal_id`,`codigo`),
  KEY `idx_sucursal_activo` (`sucursal_id`,`activo`),
  KEY `idx_sucursal_lista_base` (`sucursal_id`,`es_lista_base`),
  KEY `idx_vigencia` (`vigencia_desde`,`vigencia_hasta`),
  KEY `idx_prioridad` (`prioridad`),
  CONSTRAINT `fk_listas_precios_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: migrations
DROP TABLE IF EXISTS `{{PREFIX}}migrations`;
CREATE TABLE `{{PREFIX}}migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: model_has_permissions
DROP TABLE IF EXISTS `{{PREFIX}}model_has_permissions`;
CREATE TABLE `{{PREFIX}}model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `{{PREFIX}}model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: model_has_roles
DROP TABLE IF EXISTS `{{PREFIX}}model_has_roles`;
CREATE TABLE `{{PREFIX}}model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '0 = acceso a todas las sucursales, >0 = sucursal específica',
  PRIMARY KEY (`role_id`,`model_id`,`model_type`,`sucursal_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `{{PREFIX}}model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `{{PREFIX}}roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: movimientos_caja
DROP TABLE IF EXISTS `{{PREFIX}}movimientos_caja`;
CREATE TABLE `{{PREFIX}}movimientos_caja` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `caja_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('ingreso','egreso') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `referencia_tipo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = no cerrado aún',
  PRIMARY KEY (`id`),
  KEY `idx_caja` (`caja_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `{{PREFIX}}movimientos_caja_cierre_turno_id_index` (`cierre_turno_id`),
  CONSTRAINT `{{PREFIX}}movimientos_caja_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}movimientos_caja_cierre_turno_id_foreign` FOREIGN KEY (`cierre_turno_id`) REFERENCES `{{PREFIX}}cierres_turno` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: movimientos_cuenta_corriente
DROP TABLE IF EXISTS `{{PREFIX}}movimientos_cuenta_corriente`;
CREATE TABLE `{{PREFIX}}movimientos_cuenta_corriente` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('venta','cobro','anticipo','uso_saldo_favor','devolucion_saldo','anulacion_venta','anulacion_cobro','nota_credito','ajuste_debito','ajuste_credito') COLLATE utf8mb4_unicode_ci NOT NULL,
  `debe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `haber` decimal(12,2) NOT NULL DEFAULT '0.00',
  `saldo_favor_debe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `saldo_favor_haber` decimal(12,2) NOT NULL DEFAULT '0.00',
  `documento_tipo` enum('venta','venta_pago','cobro','cobro_venta','cobro_pago','nota_credito','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_id` bigint(20) unsigned NOT NULL,
  `venta_id` bigint(20) unsigned DEFAULT NULL,
  `venta_pago_id` bigint(20) unsigned DEFAULT NULL,
  `cobro_id` bigint(20) unsigned DEFAULT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion_comprobantes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `anulado_por_movimiento_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_sucursal_estado` (`cliente_id`,`sucursal_id`,`estado`),
  KEY `idx_cliente_fecha` (`cliente_id`,`fecha`),
  KEY `idx_sucursal_fecha` (`sucursal_id`,`fecha`),
  KEY `idx_documento` (`documento_tipo`,`documento_id`),
  KEY `idx_venta` (`venta_id`),
  KEY `idx_venta_pago` (`venta_pago_id`),
  KEY `idx_cobro` (`cobro_id`),
  KEY `idx_tipo_estado` (`tipo`,`estado`),
  KEY `fk_mcc_anulado_por` (`anulado_por_movimiento_id`),
  CONSTRAINT `fk_mcc_anulado_por` FOREIGN KEY (`anulado_por_movimiento_id`) REFERENCES `{{PREFIX}}movimientos_cuenta_corriente` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `{{PREFIX}}clientes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_cobro` FOREIGN KEY (`cobro_id`) REFERENCES `{{PREFIX}}cobros` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_venta` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_venta_pago` FOREIGN KEY (`venta_pago_id`) REFERENCES `{{PREFIX}}venta_pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: movimientos_stock
DROP TABLE IF EXISTS `{{PREFIX}}movimientos_stock`;
CREATE TABLE `{{PREFIX}}movimientos_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entrada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `salida` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_resultante` decimal(10,2) NOT NULL DEFAULT '0.00',
  `documento_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_id` bigint(20) unsigned DEFAULT NULL,
  `venta_id` bigint(20) unsigned DEFAULT NULL,
  `venta_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `compra_id` bigint(20) unsigned DEFAULT NULL,
  `compra_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `transferencia_stock_id` bigint(20) unsigned DEFAULT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `costo_unitario` decimal(10,4) DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `anulado_por_movimiento_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mov_stock_art_suc_fecha` (`articulo_id`,`sucursal_id`,`fecha`),
  KEY `mov_stock_art_suc_estado` (`articulo_id`,`sucursal_id`,`estado`),
  KEY `mov_stock_doc` (`documento_tipo`,`documento_id`),
  KEY `mov_stock_venta` (`venta_id`),
  KEY `mov_stock_compra` (`compra_id`),
  KEY `mov_stock_transf` (`transferencia_stock_id`),
  KEY `mov_stock_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: movimientos_tesoreria
DROP TABLE IF EXISTS `{{PREFIX}}movimientos_tesoreria`;
CREATE TABLE `{{PREFIX}}movimientos_tesoreria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tesoreria_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('ingreso','egreso') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `saldo_anterior` decimal(14,2) NOT NULL,
  `saldo_posterior` decimal(14,2) NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint(20) unsigned DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movimientos_tesoreria_tesoreria_id_index` (`tesoreria_id`),
  KEY `movimientos_tesoreria_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: opcionales
DROP TABLE IF EXISTS `{{PREFIX}}opcionales`;
CREATE TABLE `{{PREFIX}}opcionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grupo_opcional_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio_extra` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Precio template/default. Se copia a las asignaciones al crear',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Activo/inactivo global. Si false, no aparece en ningun lado',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}opcionales_grupo_opcional_id_foreign` (`grupo_opcional_id`),
  KEY `{{PREFIX}}opcionales_activo_index` (`activo`),
  KEY `{{PREFIX}}opcionales_orden_index` (`orden`),
  CONSTRAINT `{{PREFIX}}opcionales_grupo_opcional_id_foreign` FOREIGN KEY (`grupo_opcional_id`) REFERENCES `{{PREFIX}}grupos_opcionales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promocion_especial_escalas
DROP TABLE IF EXISTS `{{PREFIX}}promocion_especial_escalas`;
CREATE TABLE `{{PREFIX}}promocion_especial_escalas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promocion_especial_id` bigint(20) unsigned NOT NULL,
  `cantidad_desde` int(11) NOT NULL,
  `cantidad_hasta` int(11) DEFAULT NULL,
  `lleva` int(11) NOT NULL,
  `paga` int(11) NOT NULL,
  `bonifica` int(11) DEFAULT NULL,
  `beneficio_tipo` enum('gratis','descuento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gratis',
  `beneficio_porcentaje` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promo_esp_escala_promo_idx` (`promocion_especial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promocion_especial_grupo_articulos
DROP TABLE IF EXISTS `{{PREFIX}}promocion_especial_grupo_articulos`;
CREATE TABLE `{{PREFIX}}promocion_especial_grupo_articulos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grupo_id` bigint(20) unsigned NOT NULL,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promo_esp_grupo_art_unique` (`grupo_id`,`articulo_id`),
  KEY `promo_esp_grupo_art_grupo_idx` (`grupo_id`),
  KEY `promo_esp_grupo_art_art_idx` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promocion_especial_grupos
DROP TABLE IF EXISTS `{{PREFIX}}promocion_especial_grupos`;
CREATE TABLE `{{PREFIX}}promocion_especial_grupos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promocion_especial_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT '1',
  `es_trigger` tinyint(1) NOT NULL DEFAULT '0',
  `es_reward` tinyint(1) NOT NULL DEFAULT '0',
  `orden` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promo_esp_grupo_promo_idx` (`promocion_especial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promociones
DROP TABLE IF EXISTS `{{PREFIX}}promociones`;
CREATE TABLE `{{PREFIX}}promociones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL COMMENT 'Sucursal a la que aplica',
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la promoción',
  `descripcion` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción detallada',
  `codigo_cupon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código de cupón (si requiere)',
  `tipo` enum('descuento_porcentaje','descuento_monto','precio_fijo','recargo_porcentaje','recargo_monto','descuento_escalonado') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de promoción',
  `valor` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Valor según tipo (monto o porcentaje)',
  `prioridad` int(11) NOT NULL DEFAULT '999' COMMENT 'Orden de aplicación (1 = mayor prioridad)',
  `combinable` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si puede combinarse con otras',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activa',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `vigencia_desde` date DEFAULT NULL COMMENT 'Fecha desde la cual aplica',
  `vigencia_hasta` date DEFAULT NULL COMMENT 'Fecha hasta la cual aplica',
  `dias_semana` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON: Días de semana [0,1,2,3,4,5,6] donde 0=Domingo',
  `hora_desde` time DEFAULT NULL COMMENT 'Hora desde la cual aplica',
  `hora_hasta` time DEFAULT NULL COMMENT 'Hora hasta la cual aplica',
  `usos_maximos` int(11) DEFAULT NULL COMMENT 'Cantidad máxima de usos total',
  `usos_por_cliente` int(11) DEFAULT NULL COMMENT 'Usos máximos por cliente',
  `usos_actuales` int(11) NOT NULL DEFAULT '0' COMMENT 'Contador de usos actuales',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{{PREFIX}}promociones_codigo_cupon_unique` (`codigo_cupon`),
  KEY `idx_sucursal_activo` (`sucursal_id`,`activo`),
  KEY `idx_vigencia` (`vigencia_desde`,`vigencia_hasta`),
  KEY `idx_prioridad_combinable` (`prioridad`,`combinable`),
  KEY `idx_codigo_cupon` (`codigo_cupon`),
  CONSTRAINT `fk_promociones_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promociones_condiciones
DROP TABLE IF EXISTS `{{PREFIX}}promociones_condiciones`;
CREATE TABLE `{{PREFIX}}promociones_condiciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promocion_id` bigint(20) unsigned NOT NULL,
  `tipo_condicion` enum('por_articulo','por_categoria','por_forma_pago','por_forma_venta','por_canal','por_cantidad','por_total_compra') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de condición a evaluar',
  `articulo_id` bigint(20) unsigned DEFAULT NULL,
  `categoria_id` bigint(20) unsigned DEFAULT NULL,
  `forma_pago_id` bigint(20) unsigned DEFAULT NULL,
  `forma_venta_id` bigint(20) unsigned DEFAULT NULL,
  `canal_venta_id` bigint(20) unsigned DEFAULT NULL,
  `cantidad_minima` decimal(12,3) DEFAULT NULL COMMENT 'Cantidad mínima requerida',
  `cantidad_maxima` decimal(12,3) DEFAULT NULL COMMENT 'Cantidad máxima permitida',
  `monto_minimo` decimal(12,2) DEFAULT NULL COMMENT 'Monto mínimo de compra',
  `monto_maximo` decimal(12,2) DEFAULT NULL COMMENT 'Monto máximo de compra',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_promo_cond_forma_pago` (`forma_pago_id`),
  KEY `fk_promo_cond_forma_venta` (`forma_venta_id`),
  KEY `fk_promo_cond_canal` (`canal_venta_id`),
  KEY `idx_promocion_tipo` (`promocion_id`,`tipo_condicion`),
  KEY `idx_articulo` (`articulo_id`),
  KEY `idx_categoria` (`categoria_id`),
  CONSTRAINT `fk_promo_cond_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_cond_canal` FOREIGN KEY (`canal_venta_id`) REFERENCES `{{PREFIX}}canales_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_cond_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `{{PREFIX}}categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_cond_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_cond_forma_venta` FOREIGN KEY (`forma_venta_id`) REFERENCES `{{PREFIX}}formas_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_cond_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{{PREFIX}}promociones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promociones_escalas
DROP TABLE IF EXISTS `{{PREFIX}}promociones_escalas`;
CREATE TABLE `{{PREFIX}}promociones_escalas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promocion_id` bigint(20) unsigned NOT NULL,
  `cantidad_desde` decimal(12,3) NOT NULL COMMENT 'Cantidad inicial del rango',
  `cantidad_hasta` decimal(12,3) DEFAULT NULL COMMENT 'Cantidad final (NULL = infinito)',
  `tipo_descuento` enum('porcentaje','monto','precio_fijo') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de descuento en este escalón',
  `valor` decimal(12,2) NOT NULL COMMENT 'Valor según tipo (%, monto o precio)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_promocion_cantidad` (`promocion_id`,`cantidad_desde`),
  CONSTRAINT `fk_promo_escalas_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{{PREFIX}}promociones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: promociones_especiales
DROP TABLE IF EXISTS `{{PREFIX}}promociones_especiales`;
CREATE TABLE `{{PREFIX}}promociones_especiales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `tipo` enum('nxm','nxm_avanzado','combo','menu') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nxm_lleva` int(11) DEFAULT NULL,
  `nxm_paga` int(11) DEFAULT NULL,
  `nxm_bonifica` int(11) DEFAULT NULL,
  `beneficio_tipo` enum('gratis','descuento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gratis',
  `beneficio_porcentaje` decimal(5,2) DEFAULT NULL,
  `nxm_articulo_id` bigint(20) unsigned DEFAULT NULL,
  `nxm_categoria_id` bigint(20) unsigned DEFAULT NULL,
  `usa_escalas` tinyint(1) NOT NULL DEFAULT '0',
  `precio_tipo` enum('fijo','porcentaje') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fijo',
  `precio_valor` decimal(12,2) DEFAULT NULL,
  `prioridad` int(11) NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `vigencia_desde` date DEFAULT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `dias_semana` text COLLATE utf8mb4_unicode_ci,
  `hora_desde` time DEFAULT NULL,
  `hora_hasta` time DEFAULT NULL,
  `forma_venta_id` bigint(20) unsigned DEFAULT NULL,
  `canal_venta_id` bigint(20) unsigned DEFAULT NULL,
  `forma_pago_id` bigint(20) unsigned DEFAULT NULL,
  `usos_maximos` int(11) DEFAULT NULL,
  `usos_actuales` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promo_esp_sucursal_idx` (`sucursal_id`,`activo`),
  KEY `promo_esp_tipo_idx` (`tipo`,`activo`),
  KEY `promo_esp_vigencia_idx` (`vigencia_desde`,`vigencia_hasta`),
  KEY `promo_esp_articulo_idx` (`nxm_articulo_id`),
  KEY `promo_esp_categoria_idx` (`nxm_categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: proveedores
DROP TABLE IF EXISTS `{{PREFIX}}proveedores`;
CREATE TABLE `{{PREFIX}}proveedores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_fiscal` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion_iva_id` int(10) unsigned DEFAULT NULL,
  `es_sucursal_interna` tinyint(1) NOT NULL DEFAULT '0',
  `sucursal_id` bigint(20) unsigned DEFAULT NULL,
  `cliente_id` bigint(20) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proveedor_codigo` (`codigo`),
  KEY `idx_proveedor_cliente` (`cliente_id`),
  KEY `idx_proveedor_sucursal` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: provision_fondos
DROP TABLE IF EXISTS `{{PREFIX}}provision_fondos`;
CREATE TABLE `{{PREFIX}}provision_fondos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tesoreria_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `usuario_entrega_id` bigint(20) unsigned NOT NULL,
  `usuario_recibe_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('pendiente','confirmado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmado',
  `movimiento_tesoreria_id` bigint(20) unsigned DEFAULT NULL,
  `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `provision_fondos_tesoreria_id_index` (`tesoreria_id`),
  KEY `provision_fondos_caja_id_index` (`caja_id`),
  KEY `provision_fondos_fecha_index` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: punto_venta_caja
DROP TABLE IF EXISTS `{{PREFIX}}punto_venta_caja`;
CREATE TABLE `{{PREFIX}}punto_venta_caja` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `punto_venta_id` bigint(20) unsigned NOT NULL,
  `caja_id` bigint(20) unsigned NOT NULL,
  `es_defecto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si es el punto de venta por defecto de la caja',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_punto_venta_caja` (`punto_venta_id`,`caja_id`),
  KEY `idx_punto_venta_caja_caja` (`caja_id`),
  CONSTRAINT `{{PREFIX}}punto_venta_caja_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}punto_venta_caja_punto_venta_id_foreign` FOREIGN KEY (`punto_venta_id`) REFERENCES `{{PREFIX}}puntos_venta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: puntos_venta
DROP TABLE IF EXISTS `{{PREFIX}}puntos_venta`;
CREATE TABLE `{{PREFIX}}puntos_venta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cuit_id` bigint(20) unsigned NOT NULL,
  `numero` smallint(6) NOT NULL COMMENT 'Número de punto de venta (1-99999)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción o alias del punto',
  `certificado_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path al certificado encriptado',
  `clave_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path a la clave privada encriptada',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_puntos_venta_cuit_numero` (`cuit_id`,`numero`),
  KEY `idx_puntos_venta_cuit` (`cuit_id`),
  KEY `idx_puntos_venta_activo` (`activo`),
  CONSTRAINT `{{PREFIX}}puntos_venta_cuit_id_foreign` FOREIGN KEY (`cuit_id`) REFERENCES `{{PREFIX}}cuits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: receta_ingredientes
DROP TABLE IF EXISTS `{{PREFIX}}receta_ingredientes`;
CREATE TABLE `{{PREFIX}}receta_ingredientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receta_id` bigint(20) unsigned NOT NULL,
  `articulo_id` bigint(20) unsigned NOT NULL COMMENT 'El ingrediente (siempre un articulo)',
  `cantidad` decimal(12,3) NOT NULL COMMENT 'Cantidad necesaria del ingrediente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}receta_ingredientes_receta_id_index` (`receta_id`),
  KEY `{{PREFIX}}receta_ingredientes_articulo_id_index` (`articulo_id`),
  CONSTRAINT `{{PREFIX}}receta_ingredientes_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}receta_ingredientes_receta_id_foreign` FOREIGN KEY (`receta_id`) REFERENCES `{{PREFIX}}recetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: recetas
DROP TABLE IF EXISTS `{{PREFIX}}recetas`;
CREATE TABLE `{{PREFIX}}recetas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recetable_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Articulo u Opcional',
  `recetable_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null=receta default para todas. Con valor=override para esa sucursal',
  `cantidad_producida` decimal(12,3) NOT NULL DEFAULT '1.000' COMMENT 'Esta receta produce X unidades del producto',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_receta_tipo_id_suc` (`recetable_type`,`recetable_id`,`sucursal_id`),
  KEY `{{PREFIX}}recetas_sucursal_id_foreign` (`sucursal_id`),
  KEY `idx_recetable` (`recetable_type`,`recetable_id`),
  CONSTRAINT `{{PREFIX}}recetas_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: rendicion_fondos
DROP TABLE IF EXISTS `{{PREFIX}}rendicion_fondos`;
CREATE TABLE `{{PREFIX}}rendicion_fondos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `caja_id` bigint(20) unsigned NOT NULL,
  `tesoreria_id` bigint(20) unsigned NOT NULL,
  `monto_declarado` decimal(14,2) NOT NULL,
  `monto_sistema` decimal(14,2) NOT NULL,
  `monto_entregado` decimal(14,2) NOT NULL,
  `diferencia` decimal(14,2) NOT NULL DEFAULT '0.00',
  `usuario_entrega_id` bigint(20) unsigned NOT NULL,
  `usuario_recibe_id` bigint(20) unsigned DEFAULT NULL,
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('pendiente','confirmado','cancelado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `fecha_confirmacion` timestamp NULL DEFAULT NULL,
  `movimiento_tesoreria_id` bigint(20) unsigned DEFAULT NULL,
  `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci,
  `usuario_rechazo_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_rechazo` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rendicion_fondos_caja_id_index` (`caja_id`),
  KEY `rendicion_fondos_tesoreria_id_index` (`tesoreria_id`),
  KEY `rendicion_fondos_fecha_index` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: role_has_permissions
DROP TABLE IF EXISTS `{{PREFIX}}role_has_permissions`;
CREATE TABLE `{{PREFIX}}role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `{{PREFIX}}role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `{{PREFIX}}roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: roles
DROP TABLE IF EXISTS `{{PREFIX}}roles`;
CREATE TABLE `{{PREFIX}}roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name_guard` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: stock
DROP TABLE IF EXISTS `{{PREFIX}}stock`;
CREATE TABLE `{{PREFIX}}stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT '0.000',
  `cantidad_minima` decimal(10,2) DEFAULT NULL,
  `cantidad_maxima` decimal(10,2) DEFAULT NULL,
  `ultima_actualizacion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_articulo_sucursal` (`articulo_id`,`sucursal_id`),
  KEY `idx_cantidad` (`cantidad`),
  KEY `{{PREFIX}}stock_sucursal_id_foreign` (`sucursal_id`),
  CONSTRAINT `{{PREFIX}}stock_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{{PREFIX}}stock_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: sucursales
DROP TABLE IF EXISTS `{{PREFIX}}sucursales`;
CREATE TABLE `{{PREFIX}}sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la sucursal',
  `nombre_publico` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre comercial visible al público',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único de la sucursal',
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dirección física',
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teléfono de contacto',
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email de contacto',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si es la sucursal principal/central',
  `datos_fiscales_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Si factura con datos propios',
  `configuracion` text COLLATE utf8mb4_unicode_ci COMMENT 'Configuraciones específicas (JSON)',
  `activa` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si la sucursal está activa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `usa_clave_autorizacion` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si requiere clave para operaciones especiales',
  `clave_autorizacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Clave/PIN de autorización',
  `tipo_impresion_factura` enum('solo_datos','solo_logo','ambos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambos' COMMENT 'Tipo de impresión en facturas: solo_datos (fiscales), solo_logo, ambos',
  `imprime_encabezado_comanda` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si imprime encabezado en comandas',
  `agrupa_articulos_venta` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si agrupa artículos al cargar detalle de venta',
  `agrupa_articulos_impresion` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si agrupa artículos al imprimir',
  `control_stock_venta` enum('no_controla','advierte','bloquea') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bloquea' COMMENT 'Control de stock en ventas: no_controla, advierte, bloquea',
  `facturacion_fiscal_automatica` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si emite factura fiscal automáticamente según formas de pago',
  `usa_whatsapp_escritorio` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si usa WhatsApp desktop',
  `envia_whatsapp_comanda` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si envía WhatsApp al comandar',
  `mensaje_whatsapp_comanda` text COLLATE utf8mb4_unicode_ci COMMENT 'Mensaje adicional para WhatsApp al comandar',
  `envia_whatsapp_listo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si envía WhatsApp cuando pedido está listo/en camino',
  `mensaje_whatsapp_listo` text COLLATE utf8mb4_unicode_ci COMMENT 'Mensaje adicional para WhatsApp pedido listo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo` (`codigo`),
  KEY `idx_activa` (`activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tesorerias
DROP TABLE IF EXISTS `{{PREFIX}}tesorerias`;
CREATE TABLE `{{PREFIX}}tesorerias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Tesorería Principal',
  `saldo_actual` decimal(14,2) NOT NULL DEFAULT '0.00',
  `saldo_minimo` decimal(14,2) DEFAULT '0.00',
  `saldo_maximo` decimal(14,2) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tesorerias_sucursal_id_index` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tipos_iva
DROP TABLE IF EXISTS `{{PREFIX}}tipos_iva`;
CREATE TABLE `{{PREFIX}}tipos_iva` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del tipo de IVA',
  `porcentaje` decimal(5,2) NOT NULL COMMENT 'Porcentaje de IVA',
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código AFIP',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: transferencias_efectivo
DROP TABLE IF EXISTS `{{PREFIX}}transferencias_efectivo`;
CREATE TABLE `{{PREFIX}}transferencias_efectivo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `caja_origen_id` bigint(20) unsigned NOT NULL,
  `caja_destino_id` bigint(20) unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` enum('pendiente','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'completada',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}transferencias_efectivo_caja_origen_foreign` (`caja_origen_id`),
  KEY `{{PREFIX}}transferencias_efectivo_caja_destino_foreign` (`caja_destino_id`),
  CONSTRAINT `{{PREFIX}}transferencias_efectivo_caja_destino_foreign` FOREIGN KEY (`caja_destino_id`) REFERENCES `{{PREFIX}}cajas` (`id`),
  CONSTRAINT `{{PREFIX}}transferencias_efectivo_caja_origen_foreign` FOREIGN KEY (`caja_origen_id`) REFERENCES `{{PREFIX}}cajas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: transferencias_stock
DROP TABLE IF EXISTS `{{PREFIX}}transferencias_stock`;
CREATE TABLE `{{PREFIX}}transferencias_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `sucursal_origen_id` bigint(20) unsigned NOT NULL,
  `sucursal_destino_id` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` enum('pendiente','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'completada',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `{{PREFIX}}transferencias_stock_articulo_id_foreign` (`articulo_id`),
  KEY `{{PREFIX}}transferencias_stock_sucursal_origen_foreign` (`sucursal_origen_id`),
  KEY `{{PREFIX}}transferencias_stock_sucursal_destino_foreign` (`sucursal_destino_id`),
  CONSTRAINT `{{PREFIX}}transferencias_stock_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`),
  CONSTRAINT `{{PREFIX}}transferencias_stock_sucursal_destino_foreign` FOREIGN KEY (`sucursal_destino_id`) REFERENCES `{{PREFIX}}sucursales` (`id`),
  CONSTRAINT `{{PREFIX}}transferencias_stock_sucursal_origen_foreign` FOREIGN KEY (`sucursal_origen_id`) REFERENCES `{{PREFIX}}sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: user_cajas
DROP TABLE IF EXISTS `{{PREFIX}}user_cajas`;
CREATE TABLE `{{PREFIX}}user_cajas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID del usuario en tabla config.users',
  `caja_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la caja',
  `sucursal_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la sucursal (redundante pero útil para queries)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_caja` (`user_id`,`caja_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_caja` (`caja_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_user_sucursal` (`user_id`,`sucursal_id`),
  CONSTRAINT `fk_user_cajas_caja` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_cajas_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: venta_detalle_opcionales
DROP TABLE IF EXISTS `{{PREFIX}}venta_detalle_opcionales`;
CREATE TABLE `{{PREFIX}}venta_detalle_opcionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `venta_detalle_id` bigint(20) unsigned NOT NULL,
  `grupo_opcional_id` bigint(20) unsigned NOT NULL,
  `opcional_id` bigint(20) unsigned NOT NULL,
  `nombre_grupo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_opcional` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT '1.000',
  `precio_extra` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal_extra` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vdo_venta_detalle` (`venta_detalle_id`),
  KEY `idx_vdo_opcional` (`opcional_id`),
  KEY `fk_vdo_grupo_opcional` (`grupo_opcional_id`),
  CONSTRAINT `fk_vdo_grupo_opcional` FOREIGN KEY (`grupo_opcional_id`) REFERENCES `{{PREFIX}}grupos_opcionales` (`id`),
  CONSTRAINT `fk_vdo_opcional` FOREIGN KEY (`opcional_id`) REFERENCES `{{PREFIX}}opcionales` (`id`),
  CONSTRAINT `fk_vdo_venta_detalle` FOREIGN KEY (`venta_detalle_id`) REFERENCES `{{PREFIX}}ventas_detalle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: venta_detalle_promociones
DROP TABLE IF EXISTS `{{PREFIX}}venta_detalle_promociones`;
CREATE TABLE `{{PREFIX}}venta_detalle_promociones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `venta_detalle_id` bigint(20) unsigned NOT NULL,
  `tipo_promocion` enum('promocion','promocion_especial','lista_precio') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de promoción aplicada',
  `promocion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a promociones (tipo=promocion)',
  `promocion_especial_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a promociones_especiales (tipo=promocion_especial)',
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a listas_precios (tipo=lista_precio)',
  `descripcion_promocion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre/descripción de la promoción al momento de la venta',
  `tipo_beneficio` enum('porcentaje','monto_fijo','precio_especial','nx1') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de beneficio aplicado',
  `valor_beneficio` decimal(12,2) NOT NULL COMMENT 'Valor del beneficio (%, monto o precio)',
  `descuento_aplicado` decimal(12,2) NOT NULL COMMENT 'Monto del descuento efectivamente aplicado',
  `cantidad_requerida` int(10) unsigned DEFAULT NULL COMMENT 'N en promoción NxM',
  `cantidad_bonificada` int(10) unsigned DEFAULT NULL COMMENT 'M unidades gratis en NxM',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vdp_lista_precio` (`lista_precio_id`),
  KEY `idx_vdp_venta_detalle` (`venta_detalle_id`),
  KEY `idx_vdp_tipo` (`tipo_promocion`),
  KEY `idx_vdp_promocion` (`promocion_id`),
  KEY `idx_vdp_promo_especial` (`promocion_especial_id`),
  CONSTRAINT `fk_vdp_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{{PREFIX}}listas_precios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vdp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{{PREFIX}}promociones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vdp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{{PREFIX}}promociones_especiales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vdp_venta_detalle` FOREIGN KEY (`venta_detalle_id`) REFERENCES `{{PREFIX}}ventas_detalle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: venta_pagos
DROP TABLE IF EXISTS `{{PREFIX}}venta_pagos`;
CREATE TABLE `{{PREFIX}}venta_pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` bigint(20) unsigned NOT NULL,
  `forma_pago_id` bigint(20) unsigned NOT NULL,
  `concepto_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Concepto usado (para mixtas)',
  `monto_base` decimal(12,2) NOT NULL COMMENT 'Monto antes de ajustes',
  `ajuste_porcentaje` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Ajuste aplicado (+ recargo, - descuento)',
  `monto_ajuste` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto del ajuste',
  `monto_final` decimal(12,2) NOT NULL COMMENT 'Monto final después de ajustes',
  `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT '0.00',
  `monto_recibido` decimal(12,2) DEFAULT NULL COMMENT 'Monto recibido (efectivo)',
  `vuelto` decimal(12,2) DEFAULT NULL COMMENT 'Vuelto entregado',
  `cuotas` tinyint(3) unsigned DEFAULT NULL COMMENT 'Cantidad de cuotas',
  `recargo_cuotas_porcentaje` decimal(6,2) DEFAULT NULL COMMENT 'Recargo por cuotas',
  `recargo_cuotas_monto` decimal(12,2) DEFAULT NULL COMMENT 'Monto recargo por cuotas',
  `monto_cuota` decimal(12,2) DEFAULT NULL COMMENT 'Valor de cada cuota',
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nro autorización, voucher, etc',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `es_cuenta_corriente` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True si este pago genera deuda en cuenta corriente',
  `afecta_caja` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'True si genera movimiento en caja',
  `estado` enum('activo','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo' COMMENT 'Estado del pago',
  `movimiento_caja_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK al movimiento de caja generado',
  `comprobante_fiscal_id` bigint(20) unsigned DEFAULT NULL,
  `monto_facturado` decimal(12,2) DEFAULT NULL,
  `anulado_por_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `anulado_at` timestamp NULL DEFAULT NULL,
  `motivo_anulacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cierre de turno donde se procesó este pago',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venta_pagos_venta` (`venta_id`),
  KEY `idx_venta_pagos_forma` (`forma_pago_id`),
  KEY `idx_venta_pagos_concepto` (`concepto_pago_id`),
  KEY `fk_venta_pagos_mov_caja` (`movimiento_caja_id`),
  KEY `idx_vp_cuenta_corriente` (`es_cuenta_corriente`),
  KEY `idx_vp_estado` (`estado`),
  KEY `idx_vp_afecta_caja` (`afecta_caja`),
  KEY `{{PREFIX}}venta_pagos_comprobante_fiscal_id_foreign` (`comprobante_fiscal_id`),
  KEY `idx_venta_pagos_cierre_turno` (`cierre_turno_id`),
  CONSTRAINT `{{PREFIX}}venta_pagos_comprobante_fiscal_id_foreign` FOREIGN KEY (`comprobante_fiscal_id`) REFERENCES `{{PREFIX}}comprobantes_fiscales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_venta_pagos_concepto` FOREIGN KEY (`concepto_pago_id`) REFERENCES `{{PREFIX}}conceptos_pago` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_venta_pagos_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`),
  CONSTRAINT `fk_venta_pagos_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `{{PREFIX}}movimientos_caja` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_venta_pagos_venta` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: venta_promociones
DROP TABLE IF EXISTS `{{PREFIX}}venta_promociones`;
CREATE TABLE `{{PREFIX}}venta_promociones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` bigint(20) unsigned NOT NULL,
  `tipo_promocion` enum('promocion','promocion_especial','forma_pago','cupon') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de promoción aplicada',
  `promocion_id` bigint(20) unsigned DEFAULT NULL,
  `promocion_especial_id` bigint(20) unsigned DEFAULT NULL,
  `forma_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK para descuentos por forma de pago',
  `codigo_cupon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código del cupón utilizado',
  `descripcion_promocion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción de la promoción',
  `tipo_beneficio` enum('porcentaje','monto_fijo') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de descuento',
  `valor_beneficio` decimal(12,2) NOT NULL COMMENT 'Valor del beneficio (% o monto)',
  `descuento_aplicado` decimal(12,2) NOT NULL COMMENT 'Monto del descuento efectivamente aplicado',
  `monto_minimo_requerido` decimal(12,2) DEFAULT NULL COMMENT 'Monto mínimo que se requería para aplicar',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vp_promocion` (`promocion_id`),
  KEY `fk_vp_promocion_especial` (`promocion_especial_id`),
  KEY `fk_vp_forma_pago` (`forma_pago_id`),
  KEY `idx_vp_venta` (`venta_id`),
  KEY `idx_vp_tipo` (`tipo_promocion`),
  KEY `idx_vp_cupon` (`codigo_cupon`),
  CONSTRAINT `fk_vp_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vp_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `{{PREFIX}}promociones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vp_promocion_especial` FOREIGN KEY (`promocion_especial_id`) REFERENCES `{{PREFIX}}promociones_especiales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vp_venta` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: ventas
DROP TABLE IF EXISTS `{{PREFIX}}ventas`;
CREATE TABLE `{{PREFIX}}ventas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sucursal_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned DEFAULT NULL,
  `caja_id` bigint(20) unsigned NOT NULL,
  `canal_venta_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Canal de venta: mostrador, delivery, web, etc.',
  `forma_venta_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Forma de venta: consumo final, mayorista, etc.',
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Lista de precios aplicada',
  `punto_venta_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Punto de venta fiscal (para facturación)',
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `iva` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ajuste_forma_pago` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Suma de ajustes (recargos/descuentos) de formas de pago. total + ajuste = total_final',
  `total_final` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Total después de ajustes por forma de pago',
  `forma_pago_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK a formas_pago - forma de pago principal (para mixtas el detalle está en venta_pagos)',
  `estado` enum('pendiente','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'completada',
  `es_cuenta_corriente` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si la venta va a cuenta corriente del cliente',
  `saldo_pendiente_cache` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Cache del saldo pendiente (calculado desde cobros)',
  `fecha_vencimiento` timestamp NULL DEFAULT NULL COMMENT 'Fecha de vencimiento para cuenta corriente',
  `monto_fiscal_cache` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Cache: suma de comprobantes fiscales asociados',
  `monto_no_fiscal_cache` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Cache: total_final - monto_fiscal_cache',
  `anulado_por_usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Usuario que anuló la venta',
  `anulado_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora de anulación',
  `motivo_anulacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motivo de la anulación',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `cierre_turno_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cierre de turno donde se registró la venta',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_numero` (`numero`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_estado` (`estado`),
  KEY `{{PREFIX}}ventas_cliente_id_foreign` (`cliente_id`),
  KEY `{{PREFIX}}ventas_caja_id_foreign` (`caja_id`),
  KEY `idx_ventas_deleted` (`deleted_at`),
  KEY `idx_ventas_forma_pago` (`forma_pago_id`),
  KEY `idx_ventas_cierre_turno` (`cierre_turno_id`),
  CONSTRAINT `{{PREFIX}}ventas_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{{PREFIX}}cajas` (`id`),
  CONSTRAINT `{{PREFIX}}ventas_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `{{PREFIX}}clientes` (`id`),
  CONSTRAINT `{{PREFIX}}ventas_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{{PREFIX}}sucursales` (`id`),
  CONSTRAINT `fk_ventas_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{{PREFIX}}formas_pago` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: ventas_detalle
DROP TABLE IF EXISTS `{{PREFIX}}ventas_detalle`;
CREATE TABLE `{{PREFIX}}ventas_detalle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` bigint(20) unsigned NOT NULL,
  `articulo_id` bigint(20) unsigned NOT NULL,
  `tipo_iva_id` bigint(20) unsigned DEFAULT NULL,
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Lista de precios usada para calcular el precio',
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `precio_sin_iva` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `precio_lista` decimal(12,2) DEFAULT NULL COMMENT 'Precio de lista original antes de cualquier descuento',
  `precio_opcionales` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL,
  `ajuste_manual_tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ajuste_manual_valor` decimal(12,2) DEFAULT NULL,
  `precio_sin_ajuste_manual` decimal(12,2) DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
  `iva_monto` decimal(12,2) DEFAULT '0.00',
  `descuento_porcentaje` decimal(5,2) DEFAULT '0.00',
  `descuento_monto` decimal(12,2) DEFAULT '0.00',
  `descuento_promocion` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Descuento aplicado por promociones automáticas',
  `descuento_lista` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Descuento por lista de precios asignada al cliente',
  `tiene_promocion` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica si se aplicó alguna promoción',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venta` (`venta_id`),
  KEY `{{PREFIX}}ventas_detalle_articulo_id_foreign` (`articulo_id`),
  KEY `idx_venta_detalle_promocion` (`tiene_promocion`),
  KEY `idx_vd_lista_precio` (`lista_precio_id`),
  CONSTRAINT `{{PREFIX}}ventas_detalle_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{{PREFIX}}articulos` (`id`),
  CONSTRAINT `{{PREFIX}}ventas_detalle_venta_id_foreign` FOREIGN KEY (`venta_id`) REFERENCES `{{PREFIX}}ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vd_lista_precio` FOREIGN KEY (`lista_precio_id`) REFERENCES `{{PREFIX}}listas_precios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- VISTAS
-- =====================================================

-- Vista: v_saldos_cliente_global
DROP VIEW IF EXISTS `{{PREFIX}}v_saldos_cliente_global`;
CREATE ALGORITHM=UNDEFINED VIEW `{{PREFIX}}v_saldos_cliente_global` AS select `{{PREFIX}}movimientos_cuenta_corriente`.`cliente_id` AS `cliente_id`,(coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`debe`),0) - coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`haber`),0)) AS `saldo_deudor_total`,(coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`saldo_favor_haber`),0) - coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`saldo_favor_debe`),0)) AS `saldo_a_favor_total`,count(distinct `{{PREFIX}}movimientos_cuenta_corriente`.`sucursal_id`) AS `sucursales_con_movimientos`,max(`{{PREFIX}}movimientos_cuenta_corriente`.`created_at`) AS `ultimo_movimiento` from `{{PREFIX}}movimientos_cuenta_corriente` where (`{{PREFIX}}movimientos_cuenta_corriente`.`estado` = 'activo') group by `{{PREFIX}}movimientos_cuenta_corriente`.`cliente_id`;

-- Vista: v_saldos_cuenta_corriente
DROP VIEW IF EXISTS `{{PREFIX}}v_saldos_cuenta_corriente`;
CREATE ALGORITHM=UNDEFINED VIEW `{{PREFIX}}v_saldos_cuenta_corriente` AS select `{{PREFIX}}movimientos_cuenta_corriente`.`cliente_id` AS `cliente_id`,`{{PREFIX}}movimientos_cuenta_corriente`.`sucursal_id` AS `sucursal_id`,(coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`debe`),0) - coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`haber`),0)) AS `saldo_deudor`,(coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`saldo_favor_haber`),0) - coalesce(sum(`{{PREFIX}}movimientos_cuenta_corriente`.`saldo_favor_debe`),0)) AS `saldo_a_favor`,max(`{{PREFIX}}movimientos_cuenta_corriente`.`created_at`) AS `ultimo_movimiento` from `{{PREFIX}}movimientos_cuenta_corriente` where (`{{PREFIX}}movimientos_cuenta_corriente`.`estado` = 'activo') group by `{{PREFIX}}movimientos_cuenta_corriente`.`cliente_id`,`{{PREFIX}}movimientos_cuenta_corriente`.`sucursal_id`;

SET FOREIGN_KEY_CHECKS=1;
