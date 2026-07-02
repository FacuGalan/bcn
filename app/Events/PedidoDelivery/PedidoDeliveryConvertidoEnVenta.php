<?php

namespace App\Events\PedidoDelivery;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoDeliveryConvertidoEnVenta
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public int $ventaId,
        public ?int $usuarioId,
    ) {}
}
