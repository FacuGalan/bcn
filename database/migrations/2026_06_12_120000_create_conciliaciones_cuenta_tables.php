<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de cuenta con el proveedor de pago (Fase 1): corridas de
 * conciliación + detalle de filas.
 *
 * - conciliaciones_cuenta: una corrida por cuenta/período con máquina de
 *   estados (generando → pendiente_revision → aplicada | descartada; error).
 *   El comando del scheduler la avanza (el reporte del proveedor es asíncrono).
 * - conciliacion_filas: detalle clasificado del match (matcheado /
 *   solo_proveedor / solo_sistema / ya_registrado). Las filas con movimiento
 *   propuesto guardan concepto y tipo; al aplicar se liga el
 *   MovimientoCuentaEmpresa generado (origen polimórfico 'ConciliacionFila').
 *
 * Bundle migration: las 2 tablas van juntas (filas → corrida por FK).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 1, RF-03/RF-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $this->crearConciliacionesCuenta($prefix);
                $this->crearConciliacionFilas($prefix);
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}conciliacion_filas`");
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}conciliaciones_cuenta`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Corridas de conciliación: una por cuenta/período, avanzada por el
     * comando del scheduler. Snapshot de saldo y contadores para el listado.
     */
    private function crearConciliacionesCuenta(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}conciliaciones_cuenta` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `cuenta_empresa_id` bigint(20) unsigned NOT NULL,
                `desde` date NOT NULL,
                `hasta` date NOT NULL,
                `estado` enum('generando','pendiente_revision','aplicada','descartada','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generando',
                `origen` enum('manual','programada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
                `solicitud_reporte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identificador de la solicitud del reporte al proveedor (NULL = aún no solicitado)',
                `archivo_reporte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del archivo descargado del proveedor (auditoría)',
                `saldo_sistema` decimal(15,2) DEFAULT NULL COMMENT 'Snapshot del saldo del ledger al crear la corrida',
                `total_matcheados` int(11) NOT NULL DEFAULT '0',
                `total_solo_proveedor` int(11) NOT NULL DEFAULT '0',
                `total_solo_sistema` int(11) NOT NULL DEFAULT '0',
                `monto_propuesto_ingresos` decimal(15,2) NOT NULL DEFAULT '0.00',
                `monto_propuesto_egresos` decimal(15,2) NOT NULL DEFAULT '0.00',
                `error_mensaje` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK lógico cross-DB a config.users. NULL = corrida programada',
                `aplicada_por` bigint(20) unsigned DEFAULT NULL COMMENT 'FK lógico cross-DB a config.users',
                `aplicada_en` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_concc_cuenta_estado` (`cuenta_empresa_id`,`estado`),
                KEY `idx_concc_estado` (`estado`),
                CONSTRAINT `{$prefix}fk_concc_cuenta` FOREIGN KEY (`cuenta_empresa_id`) REFERENCES `{$prefix}cuentas_empresa` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Detalle de filas de la corrida: una por movimiento del reporte del
     * proveedor (+ filas hijas de comisión + alertas solo_sistema).
     */
    private function crearConciliacionFilas(string $prefix): void
    {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}conciliacion_filas` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `conciliacion_cuenta_id` bigint(20) unsigned NOT NULL,
                `tipo` enum('cobro','comision','devolucion','contracargo','retiro','retiro_cancelado','acreditacion','ajuste_inicial','otro') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo normalizado provider-agnostic',
                `clasificacion` enum('matcheado','solo_proveedor','solo_sistema','ya_registrado') COLLATE utf8mb4_unicode_ci NOT NULL,
                `id_externo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Id de la operación en el proveedor (MP = SOURCE_ID)',
                `referencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Referencia que el sistema envió al cobrar (MP = EXTERNAL_REFERENCE)',
                `fecha` datetime DEFAULT NULL COMMENT 'Fecha de la operación en el proveedor',
                `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `monto_bruto` decimal(15,2) NOT NULL DEFAULT '0.00',
                `comision` decimal(15,2) NOT NULL DEFAULT '0.00',
                `monto_neto` decimal(15,2) NOT NULL DEFAULT '0.00',
                `accion` enum('generar_movimiento','ignorar','sin_accion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_accion' COMMENT 'Editable en la revisión. Propuestas arrancan en generar_movimiento',
                `tipo_movimiento` enum('ingreso','egreso') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Del movimiento propuesto',
                `concepto_codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Concepto del movimiento propuesto',
                `integracion_pago_transaccion_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Transacción del sistema matcheada',
                `movimiento_cuenta_empresa_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Movimiento generado al aplicar',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_concf_corrida_clasif` (`conciliacion_cuenta_id`,`clasificacion`),
                KEY `idx_concf_id_externo` (`id_externo`),
                KEY `idx_concf_transaccion` (`integracion_pago_transaccion_id`),
                CONSTRAINT `{$prefix}fk_concf_corrida` FOREIGN KEY (`conciliacion_cuenta_id`) REFERENCES `{$prefix}conciliaciones_cuenta` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}fk_concf_transaccion` FOREIGN KEY (`integracion_pago_transaccion_id`) REFERENCES `{$prefix}integraciones_pago_transacciones` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{$prefix}fk_concf_movimiento` FOREIGN KEY (`movimiento_cuenta_empresa_id`) REFERENCES `{$prefix}movimientos_cuenta_empresa` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};
