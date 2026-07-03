<?php

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Seguimiento público de un pedido delivery (RF-11): canal
 * `pedidos-delivery.seguimiento.{token}` — el token ULID del pedido es la
 * credencial (patrón llamador público: canal PÚBLICO, sin tenant en el
 * nombre, payload mínimo). Lo consume la tienda/el consumidor para ver el
 * pedido avanzar en vivo. ShouldBroadcastNow: tiempo real instantáneo.
 */
class PedidoSeguimientoPublicoBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $token,
        public string $estado,
        public string $estadoLabel,
        public ?string $repartidor = null,
        public ?string $horaPactada = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("pedidos-delivery.seguimiento.{$this->token}")];
    }

    public function broadcastAs(): string
    {
        return 'SeguimientoActualizado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'estado' => $this->estado,
            'estado_label' => $this->estadoLabel,
            'repartidor' => $this->repartidor,
            'hora_pactada_at' => $this->horaPactada,
            'at' => now()->toIso8601String(),
        ];
    }
}
