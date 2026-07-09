<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compras → Costos → Precios (Fase 1, RF-12): reconciliación del schema de
 * compras al estado final.
 *
 * Estado real verificado (2026-07-09): solo el comercio 1 tiene las tablas
 * compras/compras_detalle/compra_percepciones (con CERO filas); el resto de
 * los comercios NO las tiene (provisión vieja). El SQL base además difiere de
 * lo que los modelos usan (numero vs numero_comprobante, iva vs total_iva,
 * faltan tipo_comprobante/saldo_pendiente/observaciones/tipo_iva_id/
 * precio_sin_iva) — el módulo estaba funcionalmente roto.
 *
 * Esta migración:
 *  - CREA las tres tablas con el schema final donde falten.
 *  - Donde existan, aplica ALTERs idempotentes columna por columna:
 *    · compras: numero→numero_comprobante, iva→total_iva, + tipo_comprobante,
 *      saldo_pendiente, observaciones; fecha timestamp ON UPDATE → date
 *      (el ON UPDATE re-pisaba la fecha en cada update y es la fecha que
 *      lee el ledger fiscal); forma_pago al set final del código (mapeo
 *      cuenta_corriente→cta_cte); estado D11 borrador/completada/cancelada
 *      (mapeo pendiente→completada — lo impago se deriva de saldo_pendiente).
 *    · compras_detalle: + tipo_iva_id (FK tipos_iva), precio_sin_iva,
 *      total con default (drift del fillable del modelo).
 *  - compras.caja_id se CONSERVA a pesar de D14 (la caja pertenece al pago):
 *    el CompraService actual la usa; el drop se difiere a la Fase 4/5 cuando
 *    el service reescrito y pago_proveedor_pagos tomen su lugar.
 *
 * Ref: .claude/specs/compras-costos-precios.md (RF-12, D11, D14).
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
    }

    private function migrarComercio(string $p): void
    {
        // Sin las tablas madre (tenant no provisionado en esta BD), no hay nada que hacer.
        if (! $this->tablaExiste($p.'sucursales')) {
            return;
        }

        $this->reconciliarCompras($p);
        $this->reconciliarComprasDetalle($p);
        $this->crearComprasPercepcionesSiFalta($p);
    }

    // ── compras ──────────────────────────────────────────────────────────

    private function reconciliarCompras(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->tablaExiste($p.'compras')) {
            $cuitFk = $this->tablaExiste($p.'cuits')
                ? ", CONSTRAINT `{$p}fk_compras_cuit` FOREIGN KEY (`cuit_id`) REFERENCES `{$p}cuits` (`id`) ON DELETE SET NULL"
                : '';

            $db->statement("
                CREATE TABLE `{$p}compras` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `numero_comprobante` varchar(191) NOT NULL,
                  `sucursal_id` bigint unsigned NOT NULL,
                  `proveedor_id` bigint unsigned NOT NULL,
                  `cuit_id` bigint unsigned DEFAULT NULL COMMENT 'CUIT del comercio que realizo la compra (atribucion fiscal)',
                  `caja_id` bigint unsigned DEFAULT NULL,
                  `usuario_id` bigint unsigned NOT NULL,
                  `fecha` date NOT NULL,
                  `tipo_comprobante` varchar(30) DEFAULT NULL,
                  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `total_iva` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `forma_pago` enum('efectivo','debito','credito','tarjeta','transferencia','cheque','cta_cte') NOT NULL,
                  `estado` enum('borrador','completada','cancelada') NOT NULL DEFAULT 'borrador',
                  `observaciones` text DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_sucursal` (`sucursal_id`),
                  KEY `idx_fecha` (`fecha`),
                  KEY `{$p}compras_proveedor_id_foreign` (`proveedor_id`),
                  KEY `{$p}compras_caja_id_foreign` (`caja_id`),
                  KEY `{$p}fk_compras_cuit` (`cuit_id`),
                  CONSTRAINT `{$p}compras_caja_id_foreign` FOREIGN KEY (`caja_id`) REFERENCES `{$p}cajas` (`id`),
                  CONSTRAINT `{$p}compras_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `{$p}proveedores` (`id`),
                  CONSTRAINT `{$p}compras_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `{$p}sucursales` (`id`)
                  {$cuitFk}
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            return;
        }

        // Renombres (drift SQL base vs modelo)
        if ($this->columnaExiste($p.'compras', 'numero')) {
            $db->statement("ALTER TABLE `{$p}compras` CHANGE `numero` `numero_comprobante` varchar(191) NOT NULL");
        }
        if ($this->columnaExiste($p.'compras', 'iva')) {
            $db->statement("ALTER TABLE `{$p}compras` CHANGE `iva` `total_iva` decimal(12,2) NOT NULL DEFAULT '0.00'");
        }

        // Columnas faltantes
        if (! $this->columnaExiste($p.'compras', 'tipo_comprobante')) {
            $db->statement("ALTER TABLE `{$p}compras` ADD COLUMN `tipo_comprobante` varchar(30) DEFAULT NULL AFTER `fecha`");
        }
        if (! $this->columnaExiste($p.'compras', 'saldo_pendiente')) {
            $db->statement("ALTER TABLE `{$p}compras` ADD COLUMN `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `total`");
        }
        if (! $this->columnaExiste($p.'compras', 'observaciones')) {
            $db->statement("ALTER TABLE `{$p}compras` ADD COLUMN `observaciones` text DEFAULT NULL AFTER `estado`");
        }

        // fecha: timestamp ON UPDATE CURRENT_TIMESTAMP → date plano (se auto-pisaba
        // en cada update y es la fecha que hoy lee el ledger fiscal).
        if ($this->tipoColumna($p.'compras', 'fecha') !== 'date') {
            $db->statement("ALTER TABLE `{$p}compras` MODIFY `fecha` date NOT NULL");
        }

        // forma_pago: set final = unión de lo que el código escribe y el SQL tenía.
        $db->statement("UPDATE `{$p}compras` SET `forma_pago` = 'cta_cte' WHERE `forma_pago` = 'cuenta_corriente'");
        $db->statement("
            ALTER TABLE `{$p}compras`
            MODIFY `forma_pago` enum('efectivo','debito','credito','tarjeta','transferencia','cheque','cta_cte') NOT NULL
        ");

        // Estados D11: ciclo de vida puro; lo impago se deriva de saldo_pendiente.
        $db->statement("UPDATE `{$p}compras` SET `estado` = 'completada' WHERE `estado` = 'pendiente'");
        $db->statement("
            ALTER TABLE `{$p}compras`
            MODIFY `estado` enum('borrador','completada','cancelada') NOT NULL DEFAULT 'borrador'
        ");
    }

    // ── compras_detalle ──────────────────────────────────────────────────

    private function reconciliarComprasDetalle(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->tablaExiste($p.'compras_detalle')) {
            $db->statement("
                CREATE TABLE `{$p}compras_detalle` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `compra_id` bigint unsigned NOT NULL,
                  `articulo_id` bigint unsigned NOT NULL,
                  `tipo_iva_id` bigint unsigned DEFAULT NULL,
                  `cantidad` decimal(12,3) NOT NULL,
                  `precio_unitario` decimal(12,2) NOT NULL,
                  `precio_sin_iva` decimal(12,2) DEFAULT NULL,
                  `subtotal` decimal(12,2) NOT NULL,
                  `iva_porcentaje` decimal(5,2) DEFAULT '0.00',
                  `iva_monto` decimal(12,2) DEFAULT '0.00',
                  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_compra` (`compra_id`),
                  KEY `{$p}compras_detalle_articulo_id_foreign` (`articulo_id`),
                  KEY `{$p}fk_cdet_tipo_iva` (`tipo_iva_id`),
                  CONSTRAINT `{$p}compras_detalle_articulo_id_foreign` FOREIGN KEY (`articulo_id`) REFERENCES `{$p}articulos` (`id`),
                  CONSTRAINT `{$p}compras_detalle_compra_id_foreign` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `{$p}fk_cdet_tipo_iva` FOREIGN KEY (`tipo_iva_id`) REFERENCES `{$p}tipos_iva` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            return;
        }

        if (! $this->columnaExiste($p.'compras_detalle', 'tipo_iva_id')) {
            $db->statement("ALTER TABLE `{$p}compras_detalle` ADD COLUMN `tipo_iva_id` bigint unsigned DEFAULT NULL AFTER `articulo_id`");
            $db->statement("ALTER TABLE `{$p}compras_detalle` ADD KEY `{$p}fk_cdet_tipo_iva` (`tipo_iva_id`)");
            $db->statement("ALTER TABLE `{$p}compras_detalle` ADD CONSTRAINT `{$p}fk_cdet_tipo_iva` FOREIGN KEY (`tipo_iva_id`) REFERENCES `{$p}tipos_iva` (`id`)");
        }
        if (! $this->columnaExiste($p.'compras_detalle', 'precio_sin_iva')) {
            $db->statement("ALTER TABLE `{$p}compras_detalle` ADD COLUMN `precio_sin_iva` decimal(12,2) DEFAULT NULL AFTER `precio_unitario`");
        }

        // total sin default rompía el insert del modelo (no está en el fillable).
        $db->statement("ALTER TABLE `{$p}compras_detalle` MODIFY `total` decimal(12,2) NOT NULL DEFAULT '0.00'");
    }

    // ── compra_percepciones ──────────────────────────────────────────────

    private function crearComprasPercepcionesSiFalta(string $p): void
    {
        if ($this->tablaExiste($p.'compra_percepciones') || ! $this->tablaExiste($p.'impuestos')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}compra_percepciones` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `compra_id` bigint unsigned NOT NULL,
              `impuesto_id` bigint unsigned NOT NULL,
              `base_imponible` decimal(14,2) DEFAULT NULL,
              `alicuota` decimal(6,4) DEFAULT NULL,
              `monto` decimal(14,2) NOT NULL,
              `certificado_numero` varchar(50) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `{$p}idx_cperc_compra` (`compra_id`),
              KEY `{$p}fk_cperc_impuesto` (`impuesto_id`),
              CONSTRAINT `{$p}fk_cperc_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`) ON DELETE CASCADE,
              CONSTRAINT `{$p}fk_cperc_impuesto` FOREIGN KEY (`impuesto_id`) REFERENCES `{$p}impuestos` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
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

    private function tipoColumna(string $tabla, string $columna): ?string
    {
        $row = DB::connection('pymes')->select(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [DB::connection('pymes')->getDatabaseName(), $tabla, $columna]
        );

        return $row[0]->DATA_TYPE ?? null;
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $db = DB::connection('pymes');

                if (! $this->tablaExiste($prefix.'compras')) {
                    continue;
                }

                // Reversa de renombres y enums (las tablas creadas no se dropean:
                // podrían haber ganado datos).
                if ($this->columnaExiste($prefix.'compras', 'numero_comprobante')) {
                    $db->statement("ALTER TABLE `{$prefix}compras` CHANGE `numero_comprobante` `numero` varchar(191) NOT NULL");
                }
                if ($this->columnaExiste($prefix.'compras', 'total_iva')) {
                    $db->statement("ALTER TABLE `{$prefix}compras` CHANGE `total_iva` `iva` decimal(12,2) NOT NULL DEFAULT '0.00'");
                }

                $db->statement("UPDATE `{$prefix}compras` SET `estado` = 'completada' WHERE `estado` = 'borrador'");
                $db->statement("ALTER TABLE `{$prefix}compras` MODIFY `estado` enum('pendiente','completada','cancelada') DEFAULT 'completada'");
                $db->statement("ALTER TABLE `{$prefix}compras` MODIFY `forma_pago` enum('efectivo','tarjeta','transferencia','cheque','cuenta_corriente','debito','credito','cta_cte') NOT NULL");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
