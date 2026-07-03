<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Token de acceso personal de Sanctum en la BD CONFIG (RF-11).
 *
 * Los tokens de integración son POR COMERCIO (tokenable = Comercio) y los
 * futuros tokens de consumidores de tienda son globales: ambos cross-tenant,
 * por eso la tabla vive en config (registrado en AppServiceProvider via
 * Sanctum::usePersonalAccessTokenModel).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'config';
}
