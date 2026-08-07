<?php

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación (RF-C1, spec tienda-sesion-persistente)
|--------------------------------------------------------------------------
| Sin estos archivos, Laravel cae al inglés del framework ("These
| credentials do not match our records.") en el login del panel.
*/

return [

    'failed' => 'Las credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña ingresada es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Probá de nuevo en :seconds segundos.',

];
