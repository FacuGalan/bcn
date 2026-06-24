<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Google Maps Platform — una sola key global (de BCN, no por comercio).
     * Habilita el picker de domicilio (autocomplete + mapa). Sin key, el form
     * de domicilio cae a los inputs de lat/lng manuales. Restringir la key por
     * dominio/HTTP referrer en Google Cloud. APIs: Maps JavaScript, Places, Geocoding.
     */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        // Map ID requerido por los Advanced Markers. 'DEMO_MAP_ID' sirve para
        // desarrollo; en producción crear uno propio en Google Cloud para estilo.
        'map_id' => env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID'),
    ],

];
