<?php

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base abstracta para eventos broadcasteados con aislamiento multi-tenant.
 *
 * Toda subclase queda forzada a transmitir sobre un canal privado prefijado
 * por comercio_id (`comercios.{id}.{resource}`). El acceso al canal se
 * autoriza en routes/channels.php validando que el user pertenezca al
 * comercio — un usuario de comercio A no puede subscribirse a canales de B.
 *
 * Subclases deben implementar `resourceName()` (sufijo dinamico del canal,
 * ej: "pedidos-mostrador" o "mesas.42"). El prefijo lo decide esta clase.
 *
 * Usa `ShouldBroadcastNow` (no `ShouldBroadcast`) para que el evento se
 * transmita sincronicamente sin pasar por la queue. Razon: para tiempo real
 * el delay de la queue arruina el "instantaneo". Trade-off conocido: el
 * dispatch agrega latencia chica al request HTTP que lo origina.
 */
abstract class TenantBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $comercioId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("comercios.{$this->comercioId}.{$this->resourceName()}"),
        ];
    }

    abstract protected function resourceName(): string;
}
