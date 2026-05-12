<?php

namespace App\Events\PedidoMostrador;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoConvertidoEnVenta
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $pedidoId,
        public int $ventaId,
        public int $usuarioId,
    ) {}
}
