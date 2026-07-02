<?php

namespace App\Events\PedidoDelivery;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoDeliveryEstadoPagoCambiado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public string $estadoAnterior,
        public string $estadoNuevo,
    ) {}
}
