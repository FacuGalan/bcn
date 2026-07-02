<?php

namespace App\Events\PedidoDelivery;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoDeliveryCreado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public int $sucursalId,
        public ?int $usuarioId,
    ) {}
}
