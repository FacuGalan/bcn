<?php

namespace App\Exceptions;

use Exception;

/**
 * RF-T40: la cuenta del consumidor pasó los días de gracia sin verificar el
 * email — los pedidos LOGUEADOS quedan bloqueados hasta verificar (comprar
 * como invitado sigue abierto). bootstrap/app.php la mapea a 403 con code
 * `verificacion_requerida` para que la tienda la distinga de `sin_permiso`.
 */
class VerificacionRequeridaException extends Exception {}
