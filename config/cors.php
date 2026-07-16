<?php

/*
|--------------------------------------------------------------------------
| CORS (RF-T5, spec tienda-online)
|--------------------------------------------------------------------------
| Sin este archivo Laravel 11 aplica su default: api/* abierto a cualquier
| origen (*). Acá se restringe por env: CORS_ALLOWED_ORIGINS acepta una
| lista separada por comas (ej: "https://tienda.bcnsoft.com.ar"). El
| default '*' mantiene el comportamiento actual hasta que se configure.
|
| Nota: la tienda (Laravel server-side) consume la API servidor-a-servidor,
| donde CORS no aplica; esto cubre cualquier consumo browser-side futuro.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
