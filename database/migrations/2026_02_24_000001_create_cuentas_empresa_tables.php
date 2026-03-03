<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            // ==================== NUEVAS TABLAS ====================

            // 1. Monedas
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}monedas` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `codigo` varchar(3) NOT NULL,
                        `nombre` varchar(50) NOT NULL,
                        `simbolo` varchar(5) NOT NULL,
                        `es_principal` tinyint(1) NOT NULL DEFAULT '0',
                        `decimales` tinyint NOT NULL DEFAULT '2',
                        `activo` tinyint(1) NOT NULL DEFAULT '1',
                        `orden` int NOT NULL DEFAULT '0',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}monedas_codigo_unique` (`codigo`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                continue;
            }

            // 2. Tipos de cambio
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}tipos_cambio` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `moneda_origen_id` bigint unsigned NOT NULL,
                        `moneda_destino_id` bigint unsigned NOT NULL,
                        `tasa_compra` decimal(14,6) NOT NULL,
                        `tasa_venta` decimal(14,6) NOT NULL,
                        `fecha` date NOT NULL,
                        `usuario_id` bigint unsigned DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}tipos_cambio_monedas_fecha_idx` (`moneda_origen_id`, `moneda_destino_id`, `fecha`),
                        CONSTRAINT `{$prefix}tipos_cambio_moneda_origen_fk` FOREIGN KEY (`moneda_origen_id`) REFERENCES `{$prefix}monedas` (`id`),
                        CONSTRAINT `{$prefix}tipos_cambio_moneda_destino_fk` FOREIGN KEY (`moneda_destino_id`) REFERENCES `{$prefix}monedas` (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // 3. Cuentas empresa
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}cuentas_empresa` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `nombre` varchar(100) NOT NULL,
                        `tipo` enum('banco','billetera_digital') NOT NULL,
                        `subtipo` varchar(50) DEFAULT NULL,
                        `banco` varchar(100) DEFAULT NULL,
                        `numero_cuenta` varchar(50) DEFAULT NULL,
                        `cbu` varchar(22) DEFAULT NULL,
                        `alias` varchar(50) DEFAULT NULL,
                        `titular` varchar(191) DEFAULT NULL,
                        `moneda_id` bigint unsigned DEFAULT NULL,
                        `saldo_actual` decimal(14,2) NOT NULL DEFAULT '0.00',
                        `activo` tinyint(1) NOT NULL DEFAULT '1',
                        `orden` int NOT NULL DEFAULT '0',
                        `color` varchar(7) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}cuentas_empresa_tipo_idx` (`tipo`),
                        KEY `{$prefix}cuentas_empresa_activo_idx` (`activo`),
                        CONSTRAINT `{$prefix}cuentas_empresa_moneda_fk` FOREIGN KEY (`moneda_id`) REFERENCES `{$prefix}monedas` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // 4. Cuenta empresa - sucursal (pivot)
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}cuenta_empresa_sucursal` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cuenta_empresa_id` bigint unsigned NOT NULL,
                        `sucursal_id` bigint unsigned NOT NULL,
                        `activo` tinyint(1) NOT NULL DEFAULT '1',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}cuenta_empresa_sucursal_unique` (`cuenta_empresa_id`, `sucursal_id`),
                        CONSTRAINT `{$prefix}cuenta_emp_suc_cuenta_fk` FOREIGN KEY (`cuenta_empresa_id`) REFERENCES `{$prefix}cuentas_empresa` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `{$prefix}cuenta_emp_suc_sucursal_fk` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // 5. Conceptos de movimiento de cuenta
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}conceptos_movimiento_cuenta` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `codigo` varchar(50) NOT NULL,
                        `nombre` varchar(100) NOT NULL,
                        `tipo` enum('ingreso','egreso','ambos') NOT NULL,
                        `es_sistema` tinyint(1) NOT NULL DEFAULT '0',
                        `activo` tinyint(1) NOT NULL DEFAULT '1',
                        `orden` int NOT NULL DEFAULT '0',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}conceptos_mov_cuenta_codigo_unique` (`codigo`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // 6. Movimientos de cuenta empresa
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}movimientos_cuenta_empresa` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cuenta_empresa_id` bigint unsigned NOT NULL,
                        `tipo` enum('ingreso','egreso') NOT NULL,
                        `concepto_movimiento_cuenta_id` bigint unsigned DEFAULT NULL,
                        `concepto_descripcion` varchar(255) NOT NULL,
                        `monto` decimal(14,2) NOT NULL,
                        `saldo_anterior` decimal(14,2) NOT NULL,
                        `saldo_posterior` decimal(14,2) NOT NULL,
                        `origen_tipo` varchar(50) DEFAULT NULL,
                        `origen_id` bigint unsigned DEFAULT NULL,
                        `usuario_id` bigint unsigned NOT NULL,
                        `sucursal_id` bigint unsigned DEFAULT NULL,
                        `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
                        `anulado_por_movimiento_id` bigint unsigned DEFAULT NULL,
                        `observaciones` text DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `{$prefix}mov_cuenta_emp_cuenta_fecha_idx` (`cuenta_empresa_id`, `created_at`),
                        KEY `{$prefix}mov_cuenta_emp_origen_idx` (`origen_tipo`, `origen_id`),
                        KEY `{$prefix}mov_cuenta_emp_estado_idx` (`estado`),
                        CONSTRAINT `{$prefix}mov_cuenta_emp_cuenta_fk` FOREIGN KEY (`cuenta_empresa_id`) REFERENCES `{$prefix}cuentas_empresa` (`id`),
                        CONSTRAINT `{$prefix}mov_cuenta_emp_concepto_fk` FOREIGN KEY (`concepto_movimiento_cuenta_id`) REFERENCES `{$prefix}conceptos_movimiento_cuenta` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}mov_cuenta_emp_anulado_fk` FOREIGN KEY (`anulado_por_movimiento_id`) REFERENCES `{$prefix}movimientos_cuenta_empresa` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // 7. Transferencias entre cuentas empresa
            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}transferencias_cuenta_empresa` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                        `cuenta_origen_id` bigint unsigned NOT NULL,
                        `cuenta_destino_id` bigint unsigned NOT NULL,
                        `monto` decimal(14,2) NOT NULL,
                        `moneda_id` bigint unsigned DEFAULT NULL,
                        `concepto` varchar(255) NOT NULL,
                        `movimiento_origen_id` bigint unsigned DEFAULT NULL,
                        `movimiento_destino_id` bigint unsigned DEFAULT NULL,
                        `usuario_id` bigint unsigned NOT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        CONSTRAINT `{$prefix}transf_cuenta_emp_origen_fk` FOREIGN KEY (`cuenta_origen_id`) REFERENCES `{$prefix}cuentas_empresa` (`id`),
                        CONSTRAINT `{$prefix}transf_cuenta_emp_destino_fk` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `{$prefix}cuentas_empresa` (`id`),
                        CONSTRAINT `{$prefix}transf_cuenta_emp_moneda_fk` FOREIGN KEY (`moneda_id`) REFERENCES `{$prefix}monedas` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}transf_cuenta_emp_mov_origen_fk` FOREIGN KEY (`movimiento_origen_id`) REFERENCES `{$prefix}movimientos_cuenta_empresa` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `{$prefix}transf_cuenta_emp_mov_destino_fk` FOREIGN KEY (`movimiento_destino_id`) REFERENCES `{$prefix}movimientos_cuenta_empresa` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Exception $e) {
                // continue
            }

            // ==================== ALTER TABLES ====================

            // formas_pago: agregar cuenta_empresa_id y moneda_id
            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}formas_pago` LIKE 'cuenta_empresa_id'");
                if (empty($columns)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}formas_pago`
                        ADD COLUMN `cuenta_empresa_id` bigint unsigned DEFAULT NULL,
                        ADD COLUMN `moneda_id` bigint unsigned DEFAULT NULL
                    ");
                }
            } catch (\Exception $e) {
                // continue
            }

            // venta_pagos: agregar moneda_id y movimiento_cuenta_empresa_id
            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}venta_pagos` LIKE 'movimiento_cuenta_empresa_id'");
                if (empty($columns)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}venta_pagos`
                        ADD COLUMN `moneda_id` bigint unsigned DEFAULT NULL,
                        ADD COLUMN `movimiento_cuenta_empresa_id` bigint unsigned DEFAULT NULL
                    ");
                }
            } catch (\Exception $e) {
                // continue
            }

            // cobro_pagos: agregar moneda_id y movimiento_cuenta_empresa_id
            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}cobro_pagos` LIKE 'movimiento_cuenta_empresa_id'");
                if (empty($columns)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$prefix}cobro_pagos`
                        ADD COLUMN `moneda_id` bigint unsigned DEFAULT NULL,
                        ADD COLUMN `movimiento_cuenta_empresa_id` bigint unsigned DEFAULT NULL
                    ");
                }
            } catch (\Exception $e) {
                // continue
            }

            // ==================== SEED DATA ====================

            $now = now()->format('Y-m-d H:i:s');

            // Seed monedas
            try {
                $existeARS = DB::connection('pymes')->select("SELECT id FROM `{$prefix}monedas` WHERE codigo = 'ARS' LIMIT 1");
                if (empty($existeARS)) {
                    DB::connection('pymes')->statement("
                        INSERT INTO `{$prefix}monedas` (`codigo`, `nombre`, `simbolo`, `es_principal`, `decimales`, `activo`, `orden`, `created_at`, `updated_at`) VALUES
                        ('ARS', 'Peso Argentino', '$', 1, 2, 1, 1, '{$now}', '{$now}'),
                        ('USD', 'Dólar Estadounidense', 'US$', 0, 2, 0, 2, '{$now}', '{$now}'),
                        ('BRL', 'Real Brasileño', 'R$', 0, 2, 0, 3, '{$now}', '{$now}')
                    ");
                }
            } catch (\Exception $e) {
                // continue
            }

            // Seed conceptos movimiento cuenta
            try {
                $existeVenta = DB::connection('pymes')->select("SELECT id FROM `{$prefix}conceptos_movimiento_cuenta` WHERE codigo = 'venta' LIMIT 1");
                if (empty($existeVenta)) {
                    DB::connection('pymes')->statement("
                        INSERT INTO `{$prefix}conceptos_movimiento_cuenta` (`codigo`, `nombre`, `tipo`, `es_sistema`, `activo`, `orden`, `created_at`, `updated_at`) VALUES
                        ('venta', 'Cobro de Venta', 'ingreso', 1, 1, 1, '{$now}', '{$now}'),
                        ('cobro', 'Cobro de Cuenta Corriente', 'ingreso', 1, 1, 2, '{$now}', '{$now}'),
                        ('comision_bancaria', 'Comisión Bancaria', 'egreso', 1, 1, 3, '{$now}', '{$now}'),
                        ('interes', 'Interés Bancario', 'ingreso', 1, 1, 4, '{$now}', '{$now}'),
                        ('transferencia_entre_cuentas', 'Transferencia entre Cuentas', 'ambos', 1, 1, 5, '{$now}', '{$now}'),
                        ('deposito_tesoreria', 'Depósito desde Tesorería', 'ingreso', 1, 1, 6, '{$now}', '{$now}'),
                        ('retiro_tesoreria', 'Retiro hacia Tesorería', 'egreso', 1, 1, 7, '{$now}', '{$now}'),
                        ('pago_proveedor', 'Pago a Proveedor', 'egreso', 0, 1, 8, '{$now}', '{$now}'),
                        ('devolucion', 'Devolución', 'egreso', 1, 1, 9, '{$now}', '{$now}'),
                        ('ajuste', 'Ajuste', 'ambos', 0, 1, 10, '{$now}', '{$now}'),
                        ('otro', 'Otro', 'ambos', 0, 1, 11, '{$now}', '{$now}')
                    ");
                }
            } catch (\Exception $e) {
                // continue
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            // Remove ALTER columns
            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}cobro_pagos` LIKE 'movimiento_cuenta_empresa_id'");
                if (!empty($columns)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}cobro_pagos` DROP COLUMN `movimiento_cuenta_empresa_id`, DROP COLUMN `moneda_id`");
                }
            } catch (\Exception $e) {}

            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}venta_pagos` LIKE 'movimiento_cuenta_empresa_id'");
                if (!empty($columns)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}venta_pagos` DROP COLUMN `movimiento_cuenta_empresa_id`, DROP COLUMN `moneda_id`");
                }
            } catch (\Exception $e) {}

            try {
                $columns = DB::connection('pymes')->select("SHOW COLUMNS FROM `{$prefix}formas_pago` LIKE 'cuenta_empresa_id'");
                if (!empty($columns)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$prefix}formas_pago` DROP COLUMN `cuenta_empresa_id`, DROP COLUMN `moneda_id`");
                }
            } catch (\Exception $e) {}

            // Drop tables in reverse dependency order
            $tables = [
                'transferencias_cuenta_empresa',
                'movimientos_cuenta_empresa',
                'conceptos_movimiento_cuenta',
                'cuenta_empresa_sucursal',
                'cuentas_empresa',
                'tipos_cambio',
                'monedas',
            ];

            foreach ($tables as $table) {
                try {
                    DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}{$table}`");
                } catch (\Exception $e) {}
            }
        }
    }
};
