<?php

/*
|--------------------------------------------------------------------------
| Tienda online (proyecto bcn-tienda, spec tienda-online)
|--------------------------------------------------------------------------
| Config que el CORE necesita conocer del frontend de la tienda: la URL
| base pública (para armar los links de los emails de consumidores) y los
| orígenes permitidos para CORS de la API v1.
*/

return [

    // URL base del frontend de la tienda. Los emails de verificación y de
    // recuperación de password linkean a páginas de ESTE dominio
    // ({url}/verificar?token=..., {url}/recuperar?token=...).
    'url' => rtrim(env('TIENDA_URL', env('APP_URL', 'http://localhost')), '/'),

];
