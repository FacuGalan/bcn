<?php

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
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
 */
abstract class TenantBroadcastEvent implements ShouldBroadcast
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
