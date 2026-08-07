<?php

namespace App\Services\Consumidores;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verificación server-side de Cloudflare Turnstile (RF-T72) en los
 * endpoints de consumidores que son blanco de bots (registro, recuperar,
 * restablecer). La TIENDA renderiza el widget con el site key; acá se
 * valida el token con el secret.
 *
 * Degradación honesta: sin secret configurado el feature está APAGADO (los
 * endpoints no exigen el campo). Cloudflare caído ⇒ fail-open con warning:
 * un registro legítimo no puede depender de la disponibilidad de CF.
 */
class TurnstileService
{
    protected const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function configurado(): bool
    {
        return (string) config('services.turnstile.secret') !== '';
    }

    public function verificar(?string $token, ?string $ip): bool
    {
        if (! $this->configurado()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        try {
            $respuesta = Http::asForm()->timeout(5)->post(self::URL, [
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $respuesta->json('success');
        } catch (\Throwable $e) {
            Log::warning('Turnstile inaccesible: se permite el request (fail-open)', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
