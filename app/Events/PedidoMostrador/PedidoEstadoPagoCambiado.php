<?php

namespace App\Events\PedidoMostrador;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoEstadoPagoCambiado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public string $estadoAnterior,
        public string $estadoNuevo,
    ) {}
}
