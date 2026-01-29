-- BCN Pymes - Tablas Compartidas (sin prefijo)
-- Generado: 2026-01-06 13:29:53

SET FOREIGN_KEY_CHECKS=0;

-- Tabla: menu_items
DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre visible en el menú',
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador único (ej: ventas.nueva-venta)',
  `icono` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Icono de Heroicons (ej: heroicon-o-shopping-cart)',
  `route_type` enum('route','component','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'Tipo: route=ruta Laravel, component=Livewire, none=solo agrupa',
  `route_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Valor de la ruta o nombre del componente',
  `orden` int(11) NOT NULL DEFAULT '0' COMMENT 'Orden de aparición en el menú',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo y visible en el menú',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_items_slug_unique` (`slug`),
  KEY `menu_items_parent_id_index` (`parent_id`),
  KEY `menu_items_activo_index` (`activo`),
  KEY `menu_items_parent_id_orden_index` (`parent_id`,`orden`),
  CONSTRAINT `menu_items_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: permisos_funcionales
DROP TABLE IF EXISTS `permisos_funcionales`;
CREATE TABLE `permisos_funcionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único del permiso (sin prefijo func.)',
  `etiqueta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Etiqueta para mostrar en la UI',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción detallada del permiso',
  `grupo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grupo para agrupar en la UI (Facturación, Ventas, etc.)',
  `orden` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Orden dentro del grupo',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si el permiso está activo y visible',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permisos_funcionales_codigo_unique` (`codigo`),
  KEY `permisos_funcionales_grupo_orden_index` (`grupo`,`orden`),
  KEY `permisos_funcionales_activo_index` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: permissions
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
