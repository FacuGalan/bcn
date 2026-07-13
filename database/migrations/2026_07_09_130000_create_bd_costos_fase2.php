<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compras → Costos → Precios (Fase 2): BD de costos completa.
 *
 * Bundle de las migraciones 2-11 del spec (patrón validado en integraciones
 * de pago): encabezado completo de compras (RF-05/13/14/21) + compra_ivas +
 * compra_conceptos (con tipo_iva_id, RF-15) + articulo_costos +
 * historial_costos + articulo_proveedor (RF-02/03/04) + configuracion_costos
 * (seed) + cuentas_compra (RF-22, seed + FK en proveedores y compras) +
 * detalle de compras (descuentos/prorrateos/cantidades/computable, RF-05/16)
 * + utilidad en articulos y categorias (RF-08/11).
 *
 * Idempotente columna a columna / tabla a tabla; los comercios sin tablas
 * tenant (residuos 5-13) se saltean con el guard de sucursales.
 *
 * Ref: .claude/specs/compras-costos-precios.md (Modelo de Datos).
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
        if (! $this->tablaExiste($p.'sucursales') || ! $this->tablaExiste($p.'compras')) {
            return;
        }

        $this->encabezadoCompras($p);          // migración 2 (RF-05/13/14/21)
        $this->crearCompraIvas($p);            // migración 3 (RF-14)
        $this->crearCompraConceptos($p);       // migración 4 (RF-15)
        $this->crearArticuloCostos($p);        // migración 5 (RF-02)
        $this->crearHistorialCostos($p);       // migración 6 (RF-03)
        $this->crearArticuloProveedor($p);     // migración 7 (RF-04/16)
        $this->crearConfiguracionCostos($p);   // migración 8
        $this->crearCuentasCompra($p);         // migración 8b (RF-22)
        $this->detalleCompras($p);             // migración 9 (RF-05/16)
        $this->utilidadArticulos($p);          // migración 10 (RF-08/11)
        $this->utilidadCategorias($p);         // migración 11 (RF-08)
    }

    // ── Migración 2: encabezado completo de compras ──────────────────────

    private function encabezadoCompras(string $p): void
    {
        $db = DB::connection('pymes');

        $columnas = [
            'numero_comprobante_proveedor' => 'varchar(20) DEFAULT NULL AFTER `numero_comprobante`',
            'fecha_comprobante' => 'date DEFAULT NULL AFTER `fecha`',
            'fecha_vencimiento' => 'date DEFAULT NULL AFTER `fecha_comprobante`',
            'neto_gravado' => "decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `subtotal`",
            'neto_no_gravado' => "decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `neto_gravado`",
            'neto_exento' => "decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `neto_no_gravado`",
            'descuento_global_porcentaje' => 'decimal(6,2) DEFAULT NULL AFTER `neto_exento`',
            'descuento_global_monto' => "decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `descuento_global_porcentaje`",
            'compra_origen_id' => 'bigint unsigned DEFAULT NULL AFTER `proveedor_id`',
        ];

        foreach ($columnas as $col => $def) {
            if (! $this->columnaExiste($p.'compras', $col)) {
                $db->statement("ALTER TABLE `{$p}compras` ADD COLUMN `{$col}` {$def}");
            }
        }

        if (! $this->indiceExiste($p.'compras', $p.'idx_compras_comprobante_prov')) {
            // Búsqueda del anti-duplicado (validación de APLICACIÓN que excluye
            // canceladas, RF-13/17 — un UNIQUE de BD impediría recargar).
            $db->statement("ALTER TABLE `{$p}compras` ADD KEY `{$p}idx_compras_comprobante_prov` (`proveedor_id`,`tipo_comprobante`,`numero_comprobante_proveedor`)");
        }

        if (! $this->fkExiste($p.'compras', $p.'fk_compras_origen')) {
            $db->statement("ALTER TABLE `{$p}compras` ADD CONSTRAINT `{$p}fk_compras_origen` FOREIGN KEY (`compra_origen_id`) REFERENCES `{$p}compras` (`id`) ON DELETE SET NULL");
        }
    }

    // ── Migración 3: compra_ivas ─────────────────────────────────────────

    private function crearCompraIvas(string $p): void
    {
        if ($this->tablaExiste($p.'compra_ivas')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}compra_ivas` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `compra_id` bigint unsigned NOT NULL,
              `alicuota` decimal(5,2) NOT NULL,
              `base_imponible` decimal(12,2) NOT NULL,
              `importe` decimal(12,2) NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `{$p}idx_civas_compra` (`compra_id`),
              CONSTRAINT `{$p}fk_civas_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 4: compra_conceptos ────────────────────────────────────

    private function crearCompraConceptos(string $p): void
    {
        if ($this->tablaExiste($p.'compra_conceptos')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}compra_conceptos` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `compra_id` bigint unsigned NOT NULL,
              `tipo` enum('flete','impuestos_internos','envases','otro') NOT NULL DEFAULT 'otro',
              `descripcion` varchar(150) DEFAULT NULL,
              `monto` decimal(12,2) NOT NULL,
              `tipo_iva_id` bigint unsigned DEFAULT NULL,
              `computa_costo` tinyint(1) NOT NULL DEFAULT '0',
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `{$p}idx_cconc_compra` (`compra_id`),
              KEY `{$p}fk_cconc_tipo_iva` (`tipo_iva_id`),
              CONSTRAINT `{$p}fk_cconc_compra` FOREIGN KEY (`compra_id`) REFERENCES `{$p}compras` (`id`) ON DELETE CASCADE,
              CONSTRAINT `{$p}fk_cconc_tipo_iva` FOREIGN KEY (`tipo_iva_id`) REFERENCES `{$p}tipos_iva` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 5: articulo_costos ─────────────────────────────────────

    private function crearArticuloCostos(string $p): void
    {
        if ($this->tablaExiste($p.'articulo_costos')) {
            return;
        }

        // OJO MySQL: el UNIQUE (articulo_id, sucursal_id) NO impide duplicar la
        // fila consolidada (sucursal_id NULL admite N filas iguales). La unicidad
        // del consolidado la garantiza CostoService (única puerta de escritura,
        // firstOrCreate + lockForUpdate en transacción).
        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}articulo_costos` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `articulo_id` bigint unsigned NOT NULL,
              `sucursal_id` bigint unsigned DEFAULT NULL COMMENT 'NULL = consolidado del comercio',
              `costo_ultimo` decimal(12,4) DEFAULT NULL,
              `costo_promedio` decimal(12,4) DEFAULT NULL,
              `costo_reposicion` decimal(12,4) DEFAULT NULL,
              `proveedor_ultimo_id` bigint unsigned DEFAULT NULL,
              `compra_ultima_id` bigint unsigned DEFAULT NULL,
              `fecha_costo_ultimo` timestamp NULL DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `{$p}uq_acostos_articulo_sucursal` (`articulo_id`,`sucursal_id`),
              KEY `{$p}idx_acostos_sucursal` (`sucursal_id`),
              KEY `{$p}fk_acostos_proveedor` (`proveedor_ultimo_id`),
              KEY `{$p}fk_acostos_compra` (`compra_ultima_id`),
              CONSTRAINT `{$p}fk_acostos_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$p}articulos` (`id`) ON DELETE CASCADE,
              CONSTRAINT `{$p}fk_acostos_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$p}sucursales` (`id`) ON DELETE CASCADE,
              CONSTRAINT `{$p}fk_acostos_proveedor` FOREIGN KEY (`proveedor_ultimo_id`) REFERENCES `{$p}proveedores` (`id`) ON DELETE SET NULL,
              CONSTRAINT `{$p}fk_acostos_compra` FOREIGN KEY (`compra_ultima_id`) REFERENCES `{$p}compras` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 6: historial_costos ────────────────────────────────────

    private function crearHistorialCostos(string $p): void
    {
        if ($this->tablaExiste($p.'historial_costos')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}historial_costos` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `articulo_id` bigint unsigned NOT NULL,
              `sucursal_id` bigint unsigned DEFAULT NULL COMMENT 'NULL = consolidado del comercio',
              `tipo_costo` enum('ultimo','reposicion') NOT NULL,
              `costo_anterior` decimal(12,4) DEFAULT NULL,
              `costo_nuevo` decimal(12,4) NOT NULL,
              `porcentaje_cambio` decimal(8,2) DEFAULT NULL,
              `origen` enum('compra','manual','importacion','cancelacion') NOT NULL,
              `compra_id` bigint unsigned DEFAULT NULL,
              `proveedor_id` bigint unsigned DEFAULT NULL,
              `usuario_id` bigint unsigned DEFAULT NULL,
              `detalle` varchar(255) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `{$p}idx_hcostos_articulo` (`articulo_id`,`created_at`),
              KEY `{$p}idx_hcostos_compra` (`compra_id`),
              CONSTRAINT `{$p}fk_hcostos_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$p}articulos` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 7: articulo_proveedor ──────────────────────────────────

    private function crearArticuloProveedor(string $p): void
    {
        if ($this->tablaExiste($p.'articulo_proveedor')) {
            return;
        }

        DB::connection('pymes')->statement("
            CREATE TABLE `{$p}articulo_proveedor` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `articulo_id` bigint unsigned NOT NULL,
              `proveedor_id` bigint unsigned NOT NULL,
              `codigo_proveedor` varchar(50) DEFAULT NULL,
              `factor_conversion` decimal(10,4) NOT NULL DEFAULT '1.0000' COMMENT 'Unidades de stock por unidad de compra (D8)',
              `descuentos_habituales` json DEFAULT NULL,
              `costo_ultimo` decimal(12,4) DEFAULT NULL COMMENT 'Ultimo costo computable de ESTE proveedor',
              `fecha_ultima_compra` timestamp NULL DEFAULT NULL,
              `activo` tinyint(1) NOT NULL DEFAULT '1',
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `{$p}uq_aprov_articulo_proveedor` (`articulo_id`,`proveedor_id`),
              KEY `{$p}idx_aprov_codigo` (`proveedor_id`,`codigo_proveedor`),
              CONSTRAINT `{$p}fk_aprov_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `{$p}articulos` (`id`) ON DELETE CASCADE,
              CONSTRAINT `{$p}fk_aprov_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `{$p}proveedores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Migración 8: configuracion_costos (+ seed) ───────────────────────

    private function crearConfiguracionCostos(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->tablaExiste($p.'configuracion_costos')) {
            $db->statement("
                CREATE TABLE `{$p}configuracion_costos` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `utilidad_default` decimal(6,2) NOT NULL DEFAULT '30.00',
                  `costo_rector` enum('ultimo','promedio','reposicion') NOT NULL DEFAULT 'ultimo',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (! $db->table($p.'configuracion_costos')->exists()) {
            $db->table($p.'configuracion_costos')->insert([
                'utilidad_default' => 30.00,
                'costo_rector' => 'ultimo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ── Migración 8b: cuentas_compra (+ seed + FKs) ──────────────────────

    private function crearCuentasCompra(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->tablaExiste($p.'cuentas_compra')) {
            $db->statement("
                CREATE TABLE `{$p}cuentas_compra` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `nombre` varchar(100) NOT NULL,
                  `orden` int NOT NULL DEFAULT '0',
                  `activo` tinyint(1) NOT NULL DEFAULT '1',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (! $db->table($p.'cuentas_compra')->exists()) {
            $orden = 0;
            foreach (['Mercadería', 'Insumos', 'Servicios', 'Gastos generales'] as $nombre) {
                $db->table($p.'cuentas_compra')->insert([
                    'nombre' => $nombre,
                    'orden' => ++$orden,
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // FK default por proveedor + override por compra (D22).
        if (! $this->columnaExiste($p.'proveedores', 'cuenta_compra_id')) {
            $db->statement("ALTER TABLE `{$p}proveedores` ADD COLUMN `cuenta_compra_id` bigint unsigned DEFAULT NULL");
            $db->statement("ALTER TABLE `{$p}proveedores` ADD KEY `{$p}fk_prov_cuenta_compra` (`cuenta_compra_id`)");
            $db->statement("ALTER TABLE `{$p}proveedores` ADD CONSTRAINT `{$p}fk_prov_cuenta_compra` FOREIGN KEY (`cuenta_compra_id`) REFERENCES `{$p}cuentas_compra` (`id`) ON DELETE SET NULL");
        }

        if (! $this->columnaExiste($p.'compras', 'cuenta_compra_id')) {
            $db->statement("ALTER TABLE `{$p}compras` ADD COLUMN `cuenta_compra_id` bigint unsigned DEFAULT NULL AFTER `cuit_id`");
            $db->statement("ALTER TABLE `{$p}compras` ADD KEY `{$p}fk_compras_cuenta_compra` (`cuenta_compra_id`)");
            $db->statement("ALTER TABLE `{$p}compras` ADD CONSTRAINT `{$p}fk_compras_cuenta_compra` FOREIGN KEY (`cuenta_compra_id`) REFERENCES `{$p}cuentas_compra` (`id`) ON DELETE SET NULL");
        }
    }

    // ── Migración 9: detalle de compras ──────────────────────────────────

    private function detalleCompras(string $p): void
    {
        $db = DB::connection('pymes');

        $columnas = [
            'descuentos' => 'json DEFAULT NULL AFTER `precio_sin_iva`',
            'descuento_monto' => "decimal(12,2) NOT NULL DEFAULT '0.00' AFTER `descuentos`",
            'descuento_global_monto' => "decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER `descuento_monto`",
            'conceptos_costo_monto' => "decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER `descuento_global_monto`",
            'cantidad_comprada' => 'decimal(12,3) DEFAULT NULL AFTER `cantidad`',
            'factor_conversion' => "decimal(10,4) NOT NULL DEFAULT '1.0000' AFTER `cantidad_comprada`",
            'codigo_proveedor_usado' => 'varchar(50) DEFAULT NULL AFTER `factor_conversion`',
            'costo_unitario_computable' => 'decimal(12,4) DEFAULT NULL AFTER `conceptos_costo_monto`',
        ];

        foreach ($columnas as $col => $def) {
            if (! $this->columnaExiste($p.'compras_detalle', $col)) {
                $db->statement("ALTER TABLE `{$p}compras_detalle` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    // ── Migración 10: utilidad en articulos ──────────────────────────────

    private function utilidadArticulos(string $p): void
    {
        $db = DB::connection('pymes');

        if (! $this->columnaExiste($p.'articulos', 'utilidad_porcentaje')) {
            $db->statement("ALTER TABLE `{$p}articulos` ADD COLUMN `utilidad_porcentaje` decimal(6,2) DEFAULT NULL AFTER `precio_iva_incluido`");
        }
        if (! $this->columnaExiste($p.'articulos', 'precio_administrado_por_utilidad')) {
            $db->statement("ALTER TABLE `{$p}articulos` ADD COLUMN `precio_administrado_por_utilidad` tinyint(1) NOT NULL DEFAULT '0' AFTER `utilidad_porcentaje`");
        }
    }

    // ── Migración 11: utilidad en categorias ─────────────────────────────

    private function utilidadCategorias(string $p): void
    {
        if (! $this->columnaExiste($p.'categorias', 'utilidad_porcentaje')) {
            DB::connection('pymes')->statement("ALTER TABLE `{$p}categorias` ADD COLUMN `utilidad_porcentaje` decimal(6,2) DEFAULT NULL");
        }
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

    private function indiceExiste(string $tabla, string $indice): bool
    {
        return ! empty(DB::connection('pymes')->select(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [DB::connection('pymes')->getDatabaseName(), $tabla, $indice]
        ));
    }

    private function fkExiste(string $tabla, string $constraint): bool
    {
        return ! empty(DB::connection('pymes')->select(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [DB::connection('pymes')->getDatabaseName(), $tabla, $constraint]
        ));
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $p = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $db = DB::connection('pymes');

                if (! $this->tablaExiste($p.'compras')) {
                    continue;
                }

                if ($this->columnaExiste($p.'compras', 'cuenta_compra_id')) {
                    $db->statement("ALTER TABLE `{$p}compras` DROP FOREIGN KEY `{$p}fk_compras_cuenta_compra`");
                    $db->statement("ALTER TABLE `{$p}compras` DROP COLUMN `cuenta_compra_id`");
                }
                if ($this->columnaExiste($p.'proveedores', 'cuenta_compra_id')) {
                    $db->statement("ALTER TABLE `{$p}proveedores` DROP FOREIGN KEY `{$p}fk_prov_cuenta_compra`");
                    $db->statement("ALTER TABLE `{$p}proveedores` DROP COLUMN `cuenta_compra_id`");
                }
                if ($this->fkExiste($p.'compras', $p.'fk_compras_origen')) {
                    $db->statement("ALTER TABLE `{$p}compras` DROP FOREIGN KEY `{$p}fk_compras_origen`");
                }

                foreach (['numero_comprobante_proveedor', 'fecha_comprobante', 'fecha_vencimiento', 'neto_gravado', 'neto_no_gravado', 'neto_exento', 'descuento_global_porcentaje', 'descuento_global_monto', 'compra_origen_id'] as $col) {
                    if ($this->columnaExiste($p.'compras', $col)) {
                        $db->statement("ALTER TABLE `{$p}compras` DROP COLUMN `{$col}`");
                    }
                }

                foreach (['descuentos', 'descuento_monto', 'descuento_global_monto', 'conceptos_costo_monto', 'cantidad_comprada', 'factor_conversion', 'codigo_proveedor_usado', 'costo_unitario_computable'] as $col) {
                    if ($this->columnaExiste($p.'compras_detalle', $col)) {
                        $db->statement("ALTER TABLE `{$p}compras_detalle` DROP COLUMN `{$col}`");
                    }
                }

                foreach (['utilidad_porcentaje', 'precio_administrado_por_utilidad'] as $col) {
                    if ($this->columnaExiste($p.'articulos', $col)) {
                        $db->statement("ALTER TABLE `{$p}articulos` DROP COLUMN `{$col}`");
                    }
                }
                if ($this->columnaExiste($p.'categorias', 'utilidad_porcentaje')) {
                    $db->statement("ALTER TABLE `{$p}categorias` DROP COLUMN `utilidad_porcentaje`");
                }

                foreach (['compra_ivas', 'compra_conceptos', 'articulo_costos', 'historial_costos', 'articulo_proveedor', 'configuracion_costos', 'cuentas_compra'] as $tabla) {
                    $db->statement("DROP TABLE IF EXISTS `{$p}{$tabla}`");
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
