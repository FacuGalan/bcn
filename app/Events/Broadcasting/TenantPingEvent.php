<?php

namespace App\Events\Broadcasting;

/**
 * Evento de prueba para verificar el pipeline broadcast.
 *
 * Util en tests y en el comando `php artisan reverb:ping {comercio_id}` (si
 * se agrega a futuro). NO se usa en flujos de negocio.
 */
class TenantPingEvent extends TenantBroadcastEvent
{
    public function __construct(
        int $comercioId,
        public readonly string $message = 'ping',
    ) {
        parent::__construct($comercioId);
    }

    protected function resourceName(): string
    {
        return 'ping';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'at' => now()->toIso8601String(),
        ];
    }
}
