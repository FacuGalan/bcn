<?php

namespace App\Events\PedidoDelivery;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoDeliveryEstadoCambiado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public string $estadoAnterior,
        public string $estadoNuevo,
        public ?int $usuarioId,
    ) {}
}
