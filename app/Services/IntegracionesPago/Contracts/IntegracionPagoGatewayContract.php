<?php

namespace App\Services\IntegracionesPago\Contracts;

use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;

/**
 * Contrato que todo Gateway de integración de pago debe implementar.
 *
 * Permite que el sistema soporte múltiples proveedores (MercadoPago, MODO,
 * Cuenta DNI, PayPal, etc.) intercambiables sin que los consumidores
 * (CobroIntegracionService, NuevaVenta, futuros módulos) sepan qué
 * proveedor hay detrás.
 *
 * Las implementaciones se registran en la tabla `integraciones_pago` vía
 * el campo `gateway_class` (FQCN). El método getGatewayInstance() del
 * modelo IntegracionPago se encarga de instanciarlas.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md ("Servicios y Gateways").
 *
 * NOTA: la implementación concreta (MercadoPagoGateway) se construye en
 * Fase 3. En Fase 1 solo se define el contrato.
 */
interface IntegracionPagoGatewayContract
{
    /**
     * Inicia un cobro en el proveedor externo. Devuelve los datos necesarios
     * para que el cliente pague (QR base64, link, comando POS, etc.).
     *
     * @param  IntegracionPagoSucursal  $config  Credenciales y configuración de la sucursal
     * @param  IntegracionPagoTransaccion  $transaccion  Transacción pre-creada en estado 'pendiente'
     * @return array{qr_data?: string, link?: string, external_reference: string, external_id?: string, payload: array}
     */
    public function iniciarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array;

    /**
     * Consulta el estado actual del cobro en el proveedor (polling fallback).
     *
     * @return array{estado: string, payload: array} Estado normalizado: 'pendiente'|'aprobado'|'rechazado'|'cancelado'
     */
    public function consultarEstado(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): array;

    /**
     * Cancela un cobro pendiente (si el proveedor lo soporta para el modo).
     */
    public function cancelarCobro(
        IntegracionPagoSucursal $config,
        IntegracionPagoTransaccion $transaccion
    ): bool;

    /**
     * Procesa un webhook entrante del proveedor.
     *
     * Debe: validar firma (si aplica), resolver la sucursal (vía índice global),
     * matchear la transacción correspondiente y devolver el resultado.
     *
     * @return array{transaccion: ?IntegracionPagoTransaccion, estado: string, payload: array, match_type: string}
     */
    public function procesarWebhook(array $payload, array $headers): array;

    /**
     * Verifica que las credenciales sean válidas contra el proveedor.
     * Devuelve info de la cuenta (nickname, email, etc.) o lanza excepción con el motivo.
     *
     * @return array Info de la cuenta para mostrar al usuario en UI.
     *
     * @throws \Exception si las credenciales son inválidas o hay otro problema
     */
    public function probarConexion(IntegracionPagoSucursal $config): array;

    /**
     * Lista los modos que este gateway soporta. Debe coincidir con
     * `modos_disponibles` del catálogo. Útil para validaciones.
     *
     * @return string[]
     */
    public function modosSoportados(): array;

    /**
     * Identidad de la cuenta del proveedor a la que llega la plata de esta
     * config, para vincularla con una CuentaEmpresa del ledger (match por
     * `(subtipo, identificador_externo)` — varias filas de catálogo del mismo
     * proveedor comparten la MISMA cuenta real, ej. MP QR y MP Point).
     *
     * Devuelve null si el proveedor no se mapea a una cuenta conciliable o si
     * a la config le faltan datos para identificarla.
     *
     * Único punto provider-specific del vínculo de cuentas: todo el resto
     * (auto-creación, autocompletado, registro de movimientos) es genérico.
     *
     * @return array{subtipo: string, identificador_externo: string, nombre_sugerido: string}|null
     */
    public function identidadCuentaEmpresa(IntegracionPagoSucursal $config): ?array;
}
