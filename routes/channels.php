<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Aqui se registran los canales de broadcast. La autorizacion se ejecuta
| en el handshake de Reverb antes de aceptar la subscripcion al canal
| privado — si la closure devuelve false, el cliente NO puede recibir
| eventos del canal.
|
| REGLA DE SEGURIDAD MULTI-TENANT:
| Todo canal privado de la app va prefijado por `comercios.{id}` y la
| autorizacion valida que el user autenticado tenga acceso a ese comercio
| (`hasAccessToComercio` ya contempla system admins). Esto impide que un
| usuario del comercio A pueda escuchar eventos del comercio B incluso si
| conoce el nombre del canal.
|
*/

Broadcast::channel('comercios.{comercioId}.{resource}', function (User $user, int $comercioId, string $resource) {
    return $user->hasAccessToComercio($comercioId);
});
