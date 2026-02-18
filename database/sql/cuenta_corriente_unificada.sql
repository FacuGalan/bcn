-- ============================================================
-- SISTEMA DE CUENTA CORRIENTE UNIFICADA
-- ============================================================
-- Ejecutar estos scripts en la base de datos del tenant
-- Reemplazar 000001_ por el prefijo del comercio correspondiente
-- ============================================================

-- 1. CREAR TABLA DE MOVIMIENTOS DE CUENTA CORRIENTE
-- ============================================================
CREATE TABLE IF NOT EXISTS `000001_movimientos_cuenta_corriente` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `cliente_id` BIGINT(20) UNSIGNED NOT NULL,
    `sucursal_id` BIGINT(20) UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,

    -- Tipo de movimiento
    `tipo` ENUM(
        'venta',              -- Venta a CC (genera deuda)
        'cobro',              -- Cobro aplicado a deuda
        'anticipo',           -- Anticipo recibido (genera saldo a favor)
        'uso_saldo_favor',    -- Uso de saldo a favor como pago
        'devolucion_saldo',   -- Devolución de saldo a favor al cliente
        'anulacion_venta',    -- Anulación de venta (contraasiento)
        'anulacion_cobro',    -- Anulación de cobro (contraasiento)
        'nota_credito',       -- Nota de crédito fiscal
        'ajuste_debito',      -- Ajuste manual que aumenta deuda
        'ajuste_credito'      -- Ajuste manual que disminuye deuda
    ) NOT NULL,

    -- Montos de cuenta corriente (deuda)
    `debe` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Aumenta deuda del cliente',
    `haber` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Disminuye deuda del cliente',

    -- Montos de saldo a favor (unificado)
    `saldo_favor_debe` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Usa/disminuye saldo a favor',
    `saldo_favor_haber` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Genera/aumenta saldo a favor',

    -- Referencias al documento origen (trazabilidad completa)
    `documento_tipo` ENUM('venta', 'venta_pago', 'cobro', 'cobro_venta', 'cobro_pago', 'nota_credito', 'ajuste') NOT NULL,
    `documento_id` BIGINT(20) UNSIGNED NOT NULL,

    -- Referencia específica al pago de venta (para saber exactamente qué pago CC afecta)
    `venta_id` BIGINT(20) UNSIGNED NULL COMMENT 'Venta relacionada (para facilitar consultas)',
    `venta_pago_id` BIGINT(20) UNSIGNED NULL COMMENT 'Pago específico de la venta afectado',
    `cobro_id` BIGINT(20) UNSIGNED NULL COMMENT 'Cobro relacionado (para facilitar consultas)',

    -- Descripción
    `concepto` VARCHAR(255) NOT NULL,
    `observaciones` TEXT NULL,

    -- Control de estado
    `estado` ENUM('activo', 'anulado') NOT NULL DEFAULT 'activo',
    `anulado_por_movimiento_id` BIGINT(20) UNSIGNED NULL COMMENT 'Movimiento de contraasiento que anuló este',

    -- Auditoría
    `usuario_id` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Índices para consultas frecuentes
    INDEX `idx_cliente_sucursal_estado` (`cliente_id`, `sucursal_id`, `estado`),
    INDEX `idx_cliente_fecha` (`cliente_id`, `fecha`),
    INDEX `idx_sucursal_fecha` (`sucursal_id`, `fecha`),
    INDEX `idx_documento` (`documento_tipo`, `documento_id`),
    INDEX `idx_venta` (`venta_id`),
    INDEX `idx_venta_pago` (`venta_pago_id`),
    INDEX `idx_cobro` (`cobro_id`),
    INDEX `idx_tipo_estado` (`tipo`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. MODIFICAR TABLA cobro_ventas (agregar referencia a venta_pago)
-- ============================================================
ALTER TABLE `000001_cobro_ventas`
ADD COLUMN `venta_pago_id` BIGINT(20) UNSIGNED NULL AFTER `venta_id`,
ADD INDEX `idx_venta_pago` (`venta_pago_id`);


-- 3. MODIFICAR TABLA venta_pagos (agregar saldo_pendiente para CC)
-- ============================================================
ALTER TABLE `000001_venta_pagos`
ADD COLUMN `saldo_pendiente` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `monto_final`;


-- 4. ELIMINAR TABLA movimientos_saldo_favor (ya no es necesaria)
-- ============================================================
-- NOTA: Ejecutar solo si no hay datos importantes o después de migrar los datos
-- DROP TABLE IF EXISTS `000001_movimientos_saldo_favor`;


-- ============================================================
-- VIEWS ÚTILES (OPCIONALES)
-- ============================================================

-- Vista para obtener saldo de cuenta corriente por cliente/sucursal
CREATE OR REPLACE VIEW `000001_v_saldos_cuenta_corriente` AS
SELECT
    cliente_id,
    sucursal_id,
    COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) AS saldo_deudor,
    COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) AS saldo_a_favor,
    MAX(created_at) AS ultimo_movimiento
FROM `000001_movimientos_cuenta_corriente`
WHERE estado = 'activo'
GROUP BY cliente_id, sucursal_id;


-- Vista para resumen de cuenta corriente por cliente (todas las sucursales)
CREATE OR REPLACE VIEW `000001_v_saldos_cliente_global` AS
SELECT
    cliente_id,
    COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) AS saldo_deudor_total,
    COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) AS saldo_a_favor_total,
    COUNT(DISTINCT sucursal_id) AS sucursales_con_movimientos,
    MAX(created_at) AS ultimo_movimiento
FROM `000001_movimientos_cuenta_corriente`
WHERE estado = 'activo'
GROUP BY cliente_id;
