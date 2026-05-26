<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Fase 1: esqueleto BD tenant.
 *
 * Crea 4 tablas tenant interdependientes (catálogo, config por sucursal,
 * transacciones polimórficas, eventos de auditoría) + agrega columna
 * permite_integracion a conceptos_pago.
 *
 * La tabla de resolución multi-tenant para webhooks (mercadopago_collector_index)
 * vive en DB config y se crea en una migración separada.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 1).
 *
 * Bundle migration: las 4 tablas se crean juntas porque son interdependientes
 * por FKs (sucursales → catálogo, transacciones → sucursales + formas_pago,
 * eventos → transacciones + sucursales).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $this->crearIntegracionesPago($prefix);
                $this->crearIntegracionesPagoSucursales($prefix);
                $this->crearIntegracionesPagoTransacciones($prefix);
                $this->crearIntegracionesPagoEventos($prefix);
                $this->agregarPermiteIntegracionAConceptosPago($prefix);
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}integraciones_pago_eventos`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}integraciones_pago_transacciones`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}integraciones_pago_sucursales`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}integraciones_pago`");

                // Revertir columna en conceptos_pago.
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}conceptos_pago`
                    DROP COLUMN `permite_integracion`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Catálogo de integraciones de pago disponibles.
     * Semilla inicial: MercadoPago con modos qr_dinamico, qr_estatico.
     */
    private function crearIntegracionesPago(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}integraciones_pago` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Slug único: mercadopago, modo, paypal, ...',
                `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
                `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `modos_disponibles` json NOT NULL COMMENT 'Lista de modos soportados: qr_dinamico, qr_estatico, link, point, ...',
                `gateway_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FQCN del Gateway PHP que implementa IntegracionPagoGatewayContract',
                `activo` tinyint(1) NOT NULL DEFAULT '1',
                `orden` int(11) NOT NULL DEFAULT '0',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_intp_codigo` (`codigo`),
                KEY `idx_intp_activo` (`activo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Semilla MVP: MercadoPago.
        $now = now();
        DB::connection('pymes')->statement("
            INSERT INTO `{$prefix}integraciones_pago`
                (`codigo`, `nombre`, `descripcion`, `modos_disponibles`, `gateway_class`, `activo`, `orden`, `created_at`, `updated_at`)
            VALUES
                ('mercadopago',
                 'Mercado Pago',
                 'Cobros con Mercado Pago: QR dinámico (monto fijo) y QR estático (monto libre).',
                 '[\"qr_dinamico\",\"qr_estatico\"]',
                 'App\\\\Services\\\\IntegracionesPago\\\\MercadoPagoGateway',
                 1,
                 1,
                 '{$now}',
                 '{$now}')
        ");
    }

    /**
     * Configuración por sucursal: credenciales (encriptadas) prod+test,
     * modo activo, timeout, user_id MP para resolución de webhook.
     */
    private function crearIntegracionesPagoSucursales(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}integraciones_pago_sucursales` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `integracion_pago_id` bigint(20) unsigned NOT NULL,
                `sucursal_id` bigint(20) unsigned NOT NULL,
                `modo` enum('test','produccion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test' COMMENT 'Cuál set de credenciales usar',
                `access_token_produccion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Encriptado con Crypt::encryptString',
                `access_token_test` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Encriptado con Crypt::encryptString',
                `public_key_produccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `public_key_test` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `user_id_externo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID de usuario en el proveedor (user_id MP). Clave para resolver webhooks.',
                `webhook_secret` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Encriptado. Para verificar firma x-signature del webhook.',
                `config_adicional` json DEFAULT NULL COMMENT 'Campos específicos del proveedor (qr_estatico_url, store_id, etc.)',
                `timeout_segundos` int(10) unsigned NOT NULL DEFAULT '300' COMMENT 'Timeout cobro sincrónico (default 5 min)',
                `activo` tinyint(1) NOT NULL DEFAULT '1',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_ips_integracion_sucursal` (`integracion_pago_id`,`sucursal_id`),
                KEY `idx_ips_sucursal` (`sucursal_id`),
                KEY `idx_ips_user_id_externo` (`user_id_externo`),
                KEY `idx_ips_activo` (`activo`),
                CONSTRAINT `{$prefix}fk_ips_integracion` FOREIGN KEY (`integracion_pago_id`) REFERENCES `{$prefix}integraciones_pago` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_ips_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Transacciones de cobro con integración. Polimórficas: pueden originarse
     * desde Venta, PedidoMostrador y futuros módulos (cobrable_type/cobrable_id).
     */
    private function crearIntegracionesPagoTransacciones(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}integraciones_pago_transacciones` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `integracion_pago_sucursal_id` bigint(20) unsigned NOT NULL,
                `forma_pago_id` bigint(20) unsigned NOT NULL,
                `sucursal_id` bigint(20) unsigned NOT NULL COMMENT 'Denormalizado: facilita queries de matching QR estático',
                `caja_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Denormalizado para reportes',
                `usuario_iniciador_id` bigint(20) unsigned NOT NULL COMMENT 'FK lógico cross-DB a config.users.id',
                `modo_usado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'qr_dinamico, qr_estatico, link, point, ...',
                `monto` decimal(15,2) NOT NULL,
                `moneda_id` bigint(20) unsigned DEFAULT NULL,
                `external_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ref que enviamos al gateway (para matching QR dinámico)',
                `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'payment_id del proveedor al confirmar',
                `qr_data` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Base64 PNG o string del QR',
                `link_pago` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL si modo link (futuro)',
                `estado` enum('pendiente','confirmado','confirmado_manual','fallido','expirado','cancelado','sin_match') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
                `expira_en` timestamp NULL DEFAULT NULL COMMENT 'created_at + timeout_segundos. NULL para QR estático sin expiración estricta.',
                `confirmado_en` timestamp NULL DEFAULT NULL,
                `payload_respuesta` json DEFAULT NULL COMMENT 'Respuesta del gateway al iniciar el cobro',
                `metadata` json DEFAULT NULL COMMENT 'Datos extra (motivo confirmación manual, etc.)',
                `cobrable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FQCN del cobrable: App\\\\Models\\\\Venta, App\\\\Models\\\\PedidoMostrador, ...',
                `cobrable_id` bigint(20) unsigned NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_ipt_external_reference` (`external_reference`),
                KEY `idx_ipt_estado_expira` (`estado`,`expira_en`),
                KEY `idx_ipt_matching_qr_estatico` (`integracion_pago_sucursal_id`,`estado`,`monto`,`created_at`),
                KEY `idx_ipt_cobrable` (`cobrable_type`,`cobrable_id`),
                KEY `idx_ipt_sucursal` (`sucursal_id`),
                KEY `idx_ipt_caja` (`caja_id`),
                KEY `idx_ipt_external_id` (`external_id`),
                KEY `idx_ipt_forma_pago` (`forma_pago_id`),
                CONSTRAINT `{$prefix}fk_ipt_integracion_sucursal` FOREIGN KEY (`integracion_pago_sucursal_id`) REFERENCES `{$prefix}integraciones_pago_sucursales` (`id`),
                CONSTRAINT `{$prefix}fk_ipt_forma_pago` FOREIGN KEY (`forma_pago_id`) REFERENCES `{$prefix}formas_pago` (`id`),
                CONSTRAINT `{$prefix}fk_ipt_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `{$prefix}sucursales` (`id`),
                CONSTRAINT `{$prefix}fk_ipt_caja` FOREIGN KEY (`caja_id`) REFERENCES `{$prefix}cajas` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_ipt_moneda` FOREIGN KEY (`moneda_id`) REFERENCES `{$prefix}monedas` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Eventos de auditoría: cada cambio de estado / webhook recibido / error
     * queda registrado para soporte y conciliación. Append-only.
     */
    private function crearIntegracionesPagoEventos(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}integraciones_pago_eventos` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `transaccion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL si webhook llegó sin match a ninguna transacción',
                `integracion_pago_sucursal_id` bigint(20) unsigned DEFAULT NULL,
                `evento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'creado, iniciado_en_gateway, webhook_recibido, confirmado, confirmado_manual, fallido, expirado, cancelado, sin_match, error',
                `payload_externo` json DEFAULT NULL COMMENT 'Payload del proveedor (webhook, respuesta API)',
                `metadata` json DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ipe_transaccion` (`transaccion_id`),
                KEY `idx_ipe_integracion_sucursal` (`integracion_pago_sucursal_id`),
                KEY `idx_ipe_evento` (`evento`),
                KEY `idx_ipe_created` (`created_at`),
                CONSTRAINT `{$prefix}fk_ipe_transaccion` FOREIGN KEY (`transaccion_id`) REFERENCES `{$prefix}integraciones_pago_transacciones` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_ipe_integracion_sucursal` FOREIGN KEY (`integracion_pago_sucursal_id`) REFERENCES `{$prefix}integraciones_pago_sucursales` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Marca qué conceptos de pago pueden tener integración asociada
     * (solo wallet y transferencia tienen sentido en MVP).
     */
    private function agregarPermiteIntegracionAConceptosPago(string $prefix): void
    {
        DB::connection('pymes')->statement("
            ALTER TABLE `{$prefix}conceptos_pago`
            ADD COLUMN `permite_integracion` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Si conceptos de este tipo pueden conectarse a una integración de pago externa'
            AFTER `permite_vuelto`
        ");

        // Actualizar seed: wallet y transferencia habilitados.
        DB::connection('pymes')->statement("
            UPDATE `{$prefix}conceptos_pago`
            SET `permite_integracion` = 1
            WHERE `codigo` IN ('wallet', 'transferencia')
        ");
    }
};
