<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compras → Costos → Precios (Fase 5, RF-18/19): cuenta corriente de
 * proveedores completa (D12) — bundle de las migraciones 12-14 del spec.
 *
 * - proveedores: tiene_cuenta_corriente, dias_pago, caches (patrón Cliente).
 * - movimientos_cuenta_corriente_proveedor: ledger espejo de clientes con
 *   semántica de PASIVO (HABER = compra aumenta la deuda, DEBE = pago la
 *   reduce). Sin columnas ME (v1).
 * - pagos_proveedores + pago_proveedor_compras + pago_proveedor_pagos
 *   (espejo Cobro/CobroVenta/CobroPago; el desglose guarda origen de fondos
 *   D14 + FKs a los movimientos generados + cierre_turno POR RENGLÓN D16).
 * - Activa los ítems de menú Proveedores y Pagos a Proveedores (sus pantallas
 *   llegan en esta misma fase).
 *
 * Ref: .claude/specs/compras-costos-precios.md (Modelo de Datos, RF-18/19).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $this->migrarComercio($prefix);
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->activarMenu();
    }

    private function migrarComercio(string $p): void
    {
        if (! $this->tablaExiste($p.'proveedores') || ! $this->tablaExiste($p.'compras')) {
            return;
        }

        $this->columnasProveedores($p);        // migración 12
        $this->crearPagos($p);                 // migración 14 (3 tablas) — antes
        $this->crearLedger($p);                // migración 13 (su FK apunta a pagos_proveedores)
    }

    // ── Migración 12: proveedores ────────────────────────────────────────

    private function columnasProveedores(string $p): void
    {
        $db = DB::connection('pymes');

        $columnas = [
            'tiene_cuenta_corriente' => "tinyint(1) NOT NULL DEFAULT '0'",
            'dias_pago' => 'int DEFAULT NULL',
            'saldo_cache' => "decimal(12,2) NOT NULL DEFAULT '0.00'",
            'ultimo_movimiento_ccp_at' => 'timestamp NULL DEFAULT NULL',
        ];

        foreach ($columnas as $col => $def) {
            if (! $this->columnaExiste($p.'proveedores', $col)) {
                $db->statement("ALTER TABLE `{$p}proveedores` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    // ── Migración 13: ledger ─────────────────────────────────────────────

    private function crearLedger(string $p): void
    {
        if ($this->tablaExiste($p.'movimientos_cuenta_corriente_proveedor')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}movimientos_cuenta_corriente_proveedor` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `proveedor_id` bigint unsigned NOT NULL,
              `sucursal_id` bigint unsigned NOT NULL,
              `fecha` date NOT NULL,
              `tipo` enum('compra','pago','anticipo','uso_saldo_favor','nota_credito','devolucion_saldo','anulacion_compra','anulacion_pago','ajuste_debito','ajuste_credito') NOT NULL,
              `debe` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Reduce la deuda (pago, NC del proveedor)',
              `haber` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Aumenta la deuda (compra) - semantica pasivo',
              `saldo_favor_debe` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Consume saldo a favor nuestro',
              `saldo_favor_haber` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Genera saldo a favor nuestro (anticipo)',
              `documento_tipo` enum('compra','pago','pago_compra','ajuste') NOT NULL,
              `documento_id` bigint unsigned NOT NULL,
              `compra_id` bigint unsigned DEFAULT NULL,
              `pago_proveedor_id` bigint unsigned DEFAULT NULL,
              `concepto` varchar(255) NOT NULL,
              `observaciones` text,
              `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
              `anulado_por_movimiento_id` bigint unsigned DEFAULT NULL,
              `usuario_id` bigint unsigned NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `{$p}idx_mccp_prov_suc_estado` (`proveedor_id`,`sucursal_id`,`estado`),
              KEY `{$p}idx_mccp_prov_fecha` (`proveedor_id`,`fecha`),
              KEY `{$p}idx_mccp_documento` (`documento_tipo`,`documento_id`),
              KEY `{$p}idx_mccp_tipo_estado` (`tipo`,`estado`),
              KEY `{$p}idx_mccp_compra` (`compra_id`),
              KEY `{$p}idx_mccp_pago` (`pago_proveedor_id`),
              KEY `{$p}fk_mccp_anulado_por` (`anulado_por_movimiento_id`),
              CONSTRAINT `{$p}fk_mccp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `{$p}proveedores` (`id`),
              CONSTRAINT `{$p}fk_mccp_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$p}sucursales` (`id`),
              CONSTRAINT `{$p}fk_mccp_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`) ON DELETE SET NULL,
              CONSTRAINT `{$p}fk_mccp_pago` FOREIGN KEY (`pago_proveedor_id`) REFERENCES `{$p}pagos_proveedores` (`id`) ON DELETE SET NULL,
              CONSTRAINT `{$p}fk_mccp_anulado_por` FOREIGN KEY (`anulado_por_movimiento_id`) REFERENCES `{$p}movimientos_cuenta_corriente_proveedor` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 14: pagos (3 tablas) ───────────────────────────────────

    private function crearPagos(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->tablaExiste($p.'pagos_proveedores')) {
            $db->statement("
                CREATE TABLE `{$p}pagos_proveedores` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `numero` varchar(20) NOT NULL,
                  `proveedor_id` bigint unsigned NOT NULL,
                  `sucursal_id` bigint unsigned NOT NULL,
                  `caja_id` bigint unsigned DEFAULT NULL,
                  `fecha` date NOT NULL,
                  `monto_total` decimal(12,2) NOT NULL,
                  `saldo_favor_usado` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `monto_a_favor` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `tipo` enum('pago','anticipo') NOT NULL DEFAULT 'pago',
                  `observaciones` text,
                  `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
                  `motivo_anulacion` varchar(255) DEFAULT NULL,
                  `anulado_por_usuario_id` bigint unsigned DEFAULT NULL,
                  `anulado_at` timestamp NULL DEFAULT NULL,
                  `cierre_turno_id` bigint unsigned DEFAULT NULL,
                  `usuario_id` bigint unsigned NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `{$p}idx_pprov_proveedor` (`proveedor_id`,`estado`),
                  KEY `{$p}idx_pprov_sucursal` (`sucursal_id`,`fecha`),
                  KEY `{$p}idx_pprov_caja` (`caja_id`),
                  CONSTRAINT `{$p}fk_pprov_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `{$p}proveedores` (`id`),
                  CONSTRAINT `{$p}fk_pprov_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$p}sucursales` (`id`),
                  CONSTRAINT `{$p}fk_pprov_caja` FOREIGN KEY (`caja_id`) REFERENCES `{$p}cajas` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (! $this->tablaExiste($p.'pago_proveedor_compras')) {
            $db->statement("
                CREATE TABLE `{$p}pago_proveedor_compras` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `pago_proveedor_id` bigint unsigned NOT NULL,
                  `compra_id` bigint unsigned NOT NULL,
                  `monto_aplicado` decimal(12,2) NOT NULL,
                  `saldo_anterior` decimal(12,2) NOT NULL,
                  `saldo_posterior` decimal(12,2) NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `{$p}idx_ppc_pago` (`pago_proveedor_id`),
                  KEY `{$p}idx_ppc_compra` (`compra_id`),
                  CONSTRAINT `{$p}fk_ppc_pago` FOREIGN KEY (`pago_proveedor_id`) REFERENCES `{$p}pagos_proveedores` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `{$p}fk_ppc_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (! $this->tablaExiste($p.'pago_proveedor_pagos')) {
            $db->statement("
                CREATE TABLE `{$p}pago_proveedor_pagos` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `pago_proveedor_id` bigint unsigned NOT NULL,
                  `forma_pago_id` bigint unsigned NOT NULL,
                  `monto` decimal(12,2) NOT NULL,
                  `origen` enum('caja','tesoreria','cuenta_empresa') NOT NULL DEFAULT 'caja',
                  `caja_id` bigint unsigned DEFAULT NULL,
                  `cuenta_empresa_id` bigint unsigned DEFAULT NULL,
                  `movimiento_caja_id` bigint unsigned DEFAULT NULL,
                  `movimiento_cuenta_empresa_id` bigint unsigned DEFAULT NULL,
                  `movimiento_tesoreria_id` bigint unsigned DEFAULT NULL,
                  `cierre_turno_id` bigint unsigned DEFAULT NULL,
                  `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `{$p}idx_ppp_pago` (`pago_proveedor_id`),
                  KEY `{$p}idx_ppp_fp` (`forma_pago_id`),
                  KEY `{$p}idx_ppp_caja` (`caja_id`),
                  CONSTRAINT `{$p}fk_ppp_pago` FOREIGN KEY (`pago_proveedor_id`) REFERENCES `{$p}pagos_proveedores` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `{$p}fk_ppp_fp` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$p}formas_pago` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    // ── Activar menú (las pantallas llegan en esta fase) ─────────────────

    private function activarMenu(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->whereIn('slug', ['proveedores', 'pagos-proveedores'])
            ->update(['activo' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function tablaExiste(string $tabla): bool
    {
        return ! empty(DB::connection('pymes')->select(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [DB::connection('pymes')->getDatabaseName(), $tabla]
        ));
    }

    private function columnaExiste(string $tabla, string $columna): bool
    {
        return ! empty(DB::connection('pymes')->select(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [DB::connection('pymes')->getDatabaseName(), $tabla, $columna]
        ));
    }

    public function down(): void
    {
        DB::connection('pymes')->table('menu_items')
            ->whereIn('slug', ['proveedores', 'pagos-proveedores'])
            ->update(['activo' => false]);

        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $p = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $db = DB::connection('pymes');

                foreach (['movimientos_cuenta_corriente_proveedor', 'pago_proveedor_pagos', 'pago_proveedor_compras', 'pagos_proveedores'] as $tabla) {
                    $db->statement("DROP TABLE IF EXISTS `{$p}{$tabla}`");
                }

                foreach (['tiene_cuenta_corriente', 'dias_pago', 'saldo_cache', 'ultimo_movimiento_ccp_at'] as $col) {
                    if ($this->columnaExiste($p.'proveedores', $col)) {
                        $db->statement("ALTER TABLE `{$p}proveedores` DROP COLUMN `{$col}`");
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
