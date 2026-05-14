<?php

namespace App\Events\Broadcasting;

/**
 * Evento broadcast unificado para cambios en pedidos por mostrador.
 *
 * Se dispatcha desde PedidoMostradorService en cada operacion que modifica
 * el estado visible de un pedido: creacion, cambio de estado, cambio de
 * pago, cancelacion, conversion en venta. El frontend (Livewire) escucha
 * un solo canal y refresca segun el tipo recibido.
 *
 * No transporta el pedido completo — solo IDs y tipo. El cliente debe
 * re-consultar la BD para obtener el estado fresco. Esto reduce el payload
 * y evita race conditions cuando varios cambios llegan en rafaga.
 */
class PedidoMostradorBroadcast extends TenantBroadcastEvent
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
        return 'pedidos-mostrador';
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

    /**
     * Forzar el nombre del evento que llega al cliente. Por default Laravel
     * usa el FQCN, pero acortado es mas comodo para `.PedidoMostradorBroadcast`.
     */
    public function broadcastAs(): string
    {
        return 'PedidoMostradorBroadcast';
    }
}
