<?php

namespace App\Events\IntegracionesPago;

use App\Events\Broadcasting\TenantBroadcastEvent;

/**
 * Notifica en tiempo real que el estado de una transacción de cobro por
 * integración (QR) cambió — lo dispara el webhook de Mercado Pago (Fase 6)
 * cuando MP confirma/cancela/expira un pago.
 *
 * El frontend que espera el cobro (modal "Esperando pago") escucha este evento
 * en el canal de la transacción y, al recibirlo, re-consulta el estado real con
 * `pollearCobroIntegracion()` (que confirma con MP y materializa). No transporta
 * datos sensibles — solo el id de la transacción y el estado normalizado.
 *
 * Canal: `comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}`.
 * Usa ShouldBroadcastNow (vía TenantBroadcastEvent) para latencia mínima.
 */
class IntegracionPagoActualizado extends TenantBroadcastEvent
{
    public function __construct(
        int $comercioId,
        public readonly int $transaccionId,
        public readonly string $estado,
    ) {
        parent::__construct($comercioId);
    }

    protected function resourceName(): string
    {
        return "integraciones-pago.transaccion.{$this->transaccionId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'transaccionId' => $this->transaccionId,
            'estado' => $this->estado,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'IntegracionPagoActualizado';
    }
}
