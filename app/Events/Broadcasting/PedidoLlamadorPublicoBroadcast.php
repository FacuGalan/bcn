<?php

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento PÚBLICO para el monitor llamador de pedidos (pantalla Clase B remota).
 *
 * A diferencia de PedidoMostradorBroadcast (canal PRIVADO del comercio, para el
 * POS con sesión), este transmite en un canal PÚBLICO acotado al token de la
 * sucursal: `llamador.{token}`. Los dispositivos sin sesión (TV en el salón) se
 * suscriben con Echo en modo público (sin /broadcasting/auth). El token largo de
 * la URL/localStorage es el secreto del canal.
 *
 * Seguridad: canal público = SOLO suscripción (el cliente no puede publicar);
 * whisper deshabilitado; payload mínimo sin datos sensibles. El canal privado
 * del comercio queda intacto.
 *
 * Se emite cuando un pedido entra o sale de EN_PREPARACION o LISTO (las dos
 * columnas del llamador). El cliente mueve la tarjeta entre columnas según el
 * estado y suena un chime al entrar a "Listo".
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-04, Broadcast).
 */
class PedidoLlamadorPublicoBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly int $numero,
        public readonly ?string $nombre,
        public readonly string $estado,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("llamador.{$this->token}")];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'numero' => $this->numero,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PedidoLlamador';
    }
}
