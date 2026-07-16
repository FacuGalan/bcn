<?php

namespace App\Services\Consumidores;

use App\Models\Consumidor;

/**
 * Tokens STATELESS (HMAC, sin tablas) para los flujos de email del
 * consumidor de la tienda online (RF-T1): verificación de email y
 * recuperación de password.
 *
 * Formato: base64url("{tipo}|{consumidor_id}|{expira_ts}") . "." . firma.
 * La firma es HMAC-SHA256 con la APP_KEY sobre el payload MÁS un dato que
 * invalida el token cuando deja de tener sentido:
 *  - verificación: el email actual (si el consumidor cambia el email, los
 *    tokens viejos mueren) — y validar es idempotente sobre verificados.
 *  - reset: un fragmento del hash de password actual (usar el token cambia
 *    el password → la firma ya no matchea → single-use sin guardar nada).
 */
class ConsumidorTokenService
{
    private const TIPO_VERIFICACION = 'ver';

    private const TIPO_RESET = 'rst';

    /** Vigencia del token de verificación de email (horas). */
    public const TTL_VERIFICACION_HORAS = 48;

    /** Vigencia del token de reset de password (minutos). */
    public const TTL_RESET_MINUTOS = 60;

    public function generarTokenVerificacion(Consumidor $consumidor): string
    {
        $expira = now()->addHours(self::TTL_VERIFICACION_HORAS)->getTimestamp();

        return $this->armar(self::TIPO_VERIFICACION, $consumidor->id, $expira, $this->salVerificacion($consumidor));
    }

    /**
     * Consumidor del token de verificación, o null si el token es inválido,
     * expiró o el email cambió desde que se emitió.
     */
    public function validarTokenVerificacion(string $token): ?Consumidor
    {
        return $this->validar($token, self::TIPO_VERIFICACION, fn (Consumidor $c) => $this->salVerificacion($c));
    }

    public function generarTokenReset(Consumidor $consumidor): string
    {
        $expira = now()->addMinutes(self::TTL_RESET_MINUTOS)->getTimestamp();

        return $this->armar(self::TIPO_RESET, $consumidor->id, $expira, $this->salReset($consumidor));
    }

    /**
     * Consumidor del token de reset, o null si el token es inválido, expiró
     * o el password ya cambió (token single-use).
     */
    public function validarTokenReset(string $token): ?Consumidor
    {
        return $this->validar($token, self::TIPO_RESET, fn (Consumidor $c) => $this->salReset($c));
    }

    private function armar(string $tipo, int $consumidorId, int $expiraTs, string $sal): string
    {
        $payload = "{$tipo}|{$consumidorId}|{$expiraTs}";

        return $this->base64url($payload).'.'.$this->firmar($payload, $sal);
    }

    /**
     * @param  callable(Consumidor): string  $sal
     */
    private function validar(string $token, string $tipoEsperado, callable $sal): ?Consumidor
    {
        $partes = explode('.', $token, 2);
        if (count($partes) !== 2) {
            return null;
        }

        $payload = base64_decode(strtr($partes[0], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }

        [$tipo, $id, $expira] = array_pad(explode('|', $payload, 3), 3, null);
        if ($tipo !== $tipoEsperado || ! ctype_digit((string) $id) || ! ctype_digit((string) $expira)) {
            return null;
        }

        if ((int) $expira < now()->getTimestamp()) {
            return null;
        }

        $consumidor = Consumidor::find((int) $id);
        if (! $consumidor) {
            return null;
        }

        if (! hash_equals($this->firmar($payload, $sal($consumidor)), $partes[1])) {
            return null;
        }

        return $consumidor;
    }

    private function firmar(string $payload, string $sal): string
    {
        return hash_hmac('sha256', $payload.'|'.$sal, (string) config('app.key'));
    }

    private function salVerificacion(Consumidor $consumidor): string
    {
        return (string) $consumidor->email;
    }

    private function salReset(Consumidor $consumidor): string
    {
        // Fragmento del hash actual: al cambiar el password el token muere.
        return substr((string) $consumidor->getAuthPassword(), 0, 24);
    }

    private function base64url(string $dato): string
    {
        return rtrim(strtr(base64_encode($dato), '+/', '-_'), '=');
    }
}
