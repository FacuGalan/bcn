<?php

namespace App\Services\IntegracionesPago;

use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta el cobro vía integraciones de pago (Gateway pattern).
 *
 * API única que consumen NuevaVenta y futuros módulos (Pedidos, Cobranza CC,
 * Salón, Delivery). Modelo "cobro primero, venta después": la transacción se
 * crea SIN cobrable, se muestra el QR y se espera el pago. Recién cuando el
 * pago se confirma, el caller crea la venta (su flujo normal) y la asocia con
 * `confirmarCobro($tx, $venta, ...)`. Si el pago no impacta, no se crea venta.
 *
 * La llamada HTTP al gateway se hace FUERA de la transacción DB para no
 * mantener locks tenant durante la latencia de red. Cada paso queda auditado
 * en `integraciones_pago_eventos`.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 5).
 */
class CobroIntegracionService
{
    /**
     * Inicia un cobro: crea la transacción pendiente, pide el QR al gateway y
     * lo persiste. Devuelve la transacción con `qr_data` listo.
     *
     * El cobrable es opcional: NuevaVenta no lo pasa (la venta se crea al
     * confirmar y se asocia con confirmarCobro); Pedidos Mostrador sí lo pasa
     * (el pedido existe y se cobra contra él).
     *
     * @param  array{forma_pago_id:int,sucursal_id:int,caja_id:?int,usuario_iniciador_id:int,modo_usado:string,monto:float,moneda_id:?int}  $datos
     *
     * @throws \RuntimeException si el gateway falla al generar el cobro
     */
    public function iniciarCobro(IntegracionPagoSucursal $config, array $datos, ?Model $cobrable = null): IntegracionPagoTransaccion
    {
        $transaccion = DB::connection('pymes_tenant')->transaction(function () use ($config, $datos, $cobrable) {
            $transaccion = new IntegracionPagoTransaccion([
                'integracion_pago_sucursal_id' => $config->id,
                'forma_pago_id' => $datos['forma_pago_id'],
                'sucursal_id' => $datos['sucursal_id'],
                'caja_id' => $datos['caja_id'] ?? null,
                'usuario_iniciador_id' => $datos['usuario_iniciador_id'],
                'modo_usado' => $datos['modo_usado'],
                'monto' => $datos['monto'],
                'moneda_id' => $datos['moneda_id'] ?? null,
                'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
                'expira_en' => now()->addSeconds($config->timeout_segundos),
            ]);
            if ($cobrable) {
                $transaccion->cobrable()->associate($cobrable);
            }
            $transaccion->save();

            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_CREADO);

            return $transaccion;
        });

        // Llamada HTTP al proveedor: fuera de la transacción DB.
        try {
            $resultado = $config->integracion->getGatewayInstance()->iniciarCobro($config, $transaccion);
        } catch (\Throwable $e) {
            $this->marcarFallida($transaccion, $e->getMessage());

            Log::error('CobroIntegracionService::iniciarCobro - gateway falló', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(__('No se pudo iniciar el cobro: ').$e->getMessage(), 0, $e);
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($transaccion, $resultado) {
            $transaccion->qr_data = $resultado['qr_data'] ?? null;
            $transaccion->link_pago = $resultado['link'] ?? null;
            $transaccion->external_reference = $resultado['external_reference'] ?? null;
            $transaccion->external_id = $resultado['external_id'] ?? null;
            $transaccion->payload_respuesta = $resultado['payload'] ?? null;
            $transaccion->save();

            $this->registrarEvento(
                $transaccion,
                IntegracionPagoEvento::EVENTO_INICIADO_EN_GATEWAY,
                $resultado['payload'] ?? null,
            );

            return $transaccion->fresh();
        });
    }

    /**
     * Consulta el estado actual del cobro en el proveedor (polling).
     * No muta la transacción: devuelve el estado normalizado del gateway
     * ('pendiente'|'aprobado'|'cancelado'|'expirado') para que el caller decida.
     */
    public function consultarEstado(IntegracionPagoTransaccion $transaccion): string
    {
        $config = $transaccion->integracionSucursal;
        $resultado = $config->integracion->getGatewayInstance()->consultarEstado($config, $transaccion);

        return $resultado['estado'] ?? 'pendiente';
    }

    /**
     * Marca el cobro como confirmado y, si se provee, asocia el cobrable
     * (la venta recién creada por el flujo normal del caller).
     *
     * Idempotente: si la transacción ya está en estado terminal, no hace nada.
     */
    public function confirmarCobro(IntegracionPagoTransaccion $transaccion, ?Model $cobrable = null, array $payload = []): void
    {
        if ($transaccion->estaEnEstadoTerminal()) {
            return;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($transaccion, $cobrable, $payload) {
            if ($cobrable) {
                $transaccion->cobrable()->associate($cobrable);
            }
            $transaccion->estado = IntegracionPagoTransaccion::ESTADO_CONFIRMADO;
            $transaccion->confirmado_en = now();
            if (! empty($payload)) {
                $transaccion->payload_respuesta = $payload;
            }
            $transaccion->save();

            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_CONFIRMADO, $payload ?: null);
        });
    }

    /**
     * Asocia el cobrable (venta/pedido recién creado) a una transacción que ya
     * se confirmó. Necesario en el modelo "cobro primero, venta después": el
     * pago se confirma cuando el cliente paga el QR, pero el comprobante se crea
     * después; recién ahí existe el cobrable para asociar.
     *
     * Idempotente: si la transacción ya tiene cobrable, no hace nada.
     */
    public function asociarCobrable(IntegracionPagoTransaccion $transaccion, Model $cobrable): void
    {
        if ($transaccion->cobrable_id !== null) {
            return;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($transaccion, $cobrable) {
            $transaccion->cobrable()->associate($cobrable);
            $transaccion->save();

            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO);
        });
    }

    /**
     * Cancela un cobro pendiente: avisa al proveedor y marca la transacción.
     * Idempotente: si ya está en estado terminal, devuelve true sin hacer nada.
     */
    public function cancelarCobro(IntegracionPagoTransaccion $transaccion): bool
    {
        if ($transaccion->estaEnEstadoTerminal()) {
            return true;
        }

        try {
            $config = $transaccion->integracionSucursal;
            $config->integracion->getGatewayInstance()->cancelarCobro($config, $transaccion);
        } catch (\Throwable $e) {
            // El cobro local se cancela igual; queda registro del fallo remoto.
            Log::warning('CobroIntegracionService::cancelarCobro - gateway no canceló', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($transaccion) {
            $transaccion->estado = IntegracionPagoTransaccion::ESTADO_CANCELADO;
            $transaccion->save();

            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_CANCELADO);

            return true;
        });
    }

    /**
     * Registra la recepción de un webhook del proveedor para una transacción
     * (auditoría). Append-only; no muta el estado de la transacción.
     */
    public function registrarEventoWebhook(IntegracionPagoTransaccion $transaccion, ?array $payload = null): void
    {
        $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_WEBHOOK_RECIBIDO, $payload);
    }

    /**
     * Marca la transacción como fallida y registra el evento de error.
     */
    private function marcarFallida(IntegracionPagoTransaccion $transaccion, string $motivo): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($transaccion, $motivo) {
            $transaccion->estado = IntegracionPagoTransaccion::ESTADO_FALLIDO;
            $transaccion->save();

            $this->registrarEvento(
                $transaccion,
                IntegracionPagoEvento::EVENTO_ERROR,
                null,
                ['motivo' => $motivo],
            );
        });
    }

    /**
     * Registra un evento de auditoría append-only de la transacción.
     */
    private function registrarEvento(
        IntegracionPagoTransaccion $transaccion,
        string $evento,
        ?array $payloadExterno = null,
        ?array $metadata = null,
    ): void {
        IntegracionPagoEvento::create([
            'transaccion_id' => $transaccion->id,
            'integracion_pago_sucursal_id' => $transaccion->integracion_pago_sucursal_id,
            'evento' => $evento,
            'payload_externo' => $payloadExterno,
            'metadata' => $metadata,
        ]);
    }
}
