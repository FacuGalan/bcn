<?php

namespace App\Events\Broadcasting;

/**
 * Evento broadcast unificado para cambios en pedidos delivery/take-away.
 *
 * Espejo de PedidoMostradorBroadcast: se dispatcha desde PedidoDeliveryService
 * en cada operacion que modifica el estado visible del pedido. Solo transporta
 * IDs y tipo — el cliente re-consulta la BD para el estado fresco.
 */
class PedidoDeliveryBroadcast extends TenantBroadcastEvent
{
    public const TIPO_CREADO = 'creado';

    public const TIPO_ESTADO_CAMBIADO = 'estado_cambiado';

    public const TIPO_PAGO_CAMBIADO = 'pago_cambiado';

    public const TIPO_CANCELADO = 'cancelado';

    public const TIPO_CONVERTIDO_VENTA = 'convertido_venta';

    public function __construct(
        int $comercioId,
        public readonly int $sucursalId,
        public readonly int $pedidoId,
        public readonly string $tipo,
    ) {
        parent::__construct($comercioId);
    }

    protected function resourceName(): string
    {
        return 'pedidos-delivery';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'pedidoId' => $this->pedidoId,
            'sucursalId' => $this->sucursalId,
            'tipo' => $this->tipo,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PedidoDeliveryBroadcast';
    }
}
