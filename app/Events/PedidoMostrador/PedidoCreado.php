<?php

namespace App\Events\PedidoMostrador;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoCreado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public int $sucursalId,
        public int $usuarioId,
    ) {}
}
