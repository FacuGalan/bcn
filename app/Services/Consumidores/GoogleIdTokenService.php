<?php

namespace App\Services\Consumidores;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verificación server-side del ID token de Google Identity Services
 * (RF-T49, Sign in with Google de la tienda online).
 *
 * La tienda obtiene el credential (un JWT firmado por Google) en el
 * navegador y lo reenvía al core; acá se valida firma (JWKS de Google,
 * cacheadas), emisor, audiencia (nuestro client ID) y expiración. Verificador
 * puro: no toca BD — la resolución de la cuenta vive en el AuthController.
 */
class GoogleIdTokenService
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const JWKS_CACHE_KEY = 'google_jwks_certs';

    /** Google rota claves con solapamiento amplio; 1 h de cache alcanza. */
    private const JWKS_CACHE_TTL = 3600;

    private const EMISORES_VALIDOS = ['https://accounts.google.com', 'accounts.google.com'];

    /** Hay client ID configurado (sin él, el feature queda apagado). */
    public function configurado(): bool
    {
        return (string) config('services.google.client_id') !== '';
    }

    /**
     * Claims del ID token, o null si es inválido (firma, emisor, audiencia
     * o expiración). Claims relevantes: sub, email, email_verified, name, hd.
     *
     * @return array<string, mixed>|null
     */
    public function verificar(string $credential): ?array
    {
        if (! $this->configurado()) {
            return null;
        }

        $claims = $this->decodificar($credential);

        if ($claims === null) {
            return null;
        }

        if (! in_array($claims['iss'] ?? null, self::EMISORES_VALIDOS, true)) {
            return null;
        }

        if (($claims['aud'] ?? null) !== config('services.google.client_id')) {
            return null;
        }

        if (! isset($claims['sub'], $claims['email'])) {
            return null;
        }

        return $claims;
    }

    /**
     * Google es AUTORITATIVO sobre el email (doc oficial de GIS): Gmail, o
     * cuenta Workspace verificada (claim hd). En esos casos el login con
     * Google prueba la posesión de la casilla y se saltea la verificación
     * por email (RF-T40 no aplica).
     */
    public function esAutoritativo(array $claims): bool
    {
        $email = strtolower((string) ($claims['email'] ?? ''));

        if (str_ends_with($email, '@gmail.com')) {
            return true;
        }

        return ($claims['email_verified'] ?? false) === true && ! empty($claims['hd']);
    }

    /**
     * Decodifica y valida firma+exp contra las JWKS de Google. Si la clave
     * del token no está en el set cacheado (rotación), refresca una vez.
     *
     * @return array<string, mixed>|null
     */
    private function decodificar(string $credential): ?array
    {
        foreach ([false, true] as $refrescar) {
            $jwks = $this->jwks($refrescar);

            if ($jwks === null) {
                return null;
            }

            try {
                JWT::$leeway = 60; // tolerancia de reloj

                return (array) JWT::decode($credential, JWK::parseKeySet($jwks, 'RS256'));
            } catch (\Throwable $e) {
                // 'Key ID' desconocido en el primer intento → puede ser
                // rotación de claves: reintenta con JWKS frescas.
                if (! $refrescar && str_contains($e->getMessage(), '"kid"')) {
                    continue;
                }

                Log::info('ID token de Google rechazado', ['error' => $e->getMessage()]);

                return null;
            }
        }

        return null;
    }

    /**
     * @return array{keys: array<int, array<string, string>>}|null
     */
    private function jwks(bool $refrescar = false): ?array
    {
        if ($refrescar) {
            Cache::forget(self::JWKS_CACHE_KEY);
        }

        try {
            return Cache::remember(self::JWKS_CACHE_KEY, self::JWKS_CACHE_TTL, function () {
                $respuesta = Http::timeout(5)->get(self::JWKS_URL);

                if (! $respuesta->successful() || empty($respuesta->json('keys'))) {
                    throw new \RuntimeException('Respuesta inválida de las JWKS de Google');
                }

                return $respuesta->json();
            });
        } catch (\Throwable $e) {
            Log::error('No se pudieron obtener las JWKS de Google', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
