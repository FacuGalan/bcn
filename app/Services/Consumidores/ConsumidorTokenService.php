<?php

namespace App\Services\Consumidores;

use App\Models\Consumidor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

    private const TIPO_MAGIC = 'mgc';

    /** Vigencia del token de verificación de email (horas). */
    public const TTL_VERIFICACION_HORAS = 48;

    /** Vigencia del token de reset de password (minutos). */
    public const TTL_RESET_MINUTOS = 60;

    /** Vigencia del magic link de login (minutos). */
    public const TTL_MAGIC_MINUTOS = 15;

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

    /**
     * Magic link de login (RF-T69): payload de 4 partes con un `jti` random
     * que habilita el single-use (el HMAC puro no puede ser single-use: no
     * hay estado que cambie al canjearlo, a diferencia del reset).
     * Sal = email (si el consumidor cambia el email, los links viejos mueren).
     */
    public function generarTokenMagic(Consumidor $consumidor): string
    {
        $expira = now()->addMinutes(self::TTL_MAGIC_MINUTOS)->getTimestamp();
        $payload = self::TIPO_MAGIC."|{$consumidor->id}|{$expira}|".Str::random(16);

        return $this->base64url($payload).'.'.$this->firmar($payload, $this->salVerificacion($consumidor));
    }

    /**
     * Valida Y CONSUME el magic link (single-use: el jti se marca usado en
     * cache hasta que el token venza solo). Null si es inválido, venció,
     * ya fue usado o el email cambió.
     */
    public function consumirTokenMagic(string $token): ?Consumidor
    {
        $partes = explode('.', $token, 2);
        if (count($partes) !== 2) {
            return null;
        }

        $payload = base64_decode(strtr($partes[0], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }

        [$tipo, $id, $expira, $jti] = array_pad(explode('|', $payload, 4), 4, null);
        if ($tipo !== self::TIPO_MAGIC || ! ctype_digit((string) $id) || ! ctype_digit((string) $expira) || ! is_string($jti) || $jti === '') {
            return null;
        }

        if ((int) $expira < now()->getTimestamp()) {
            return null;
        }

        $consumidor = Consumidor::find((int) $id);
        if (! $consumidor) {
            return null;
        }

        if (! hash_equals($this->firmar($payload, $this->salVerificacion($consumidor)), $partes[1])) {
            return null;
        }

        // Single-use: add() es atómico — si el jti ya está, alguien lo usó.
        $vence = \Illuminate\Support\Carbon::createFromTimestamp((int) $expira)->addMinute();
        if (! Cache::add('consumidor-mgc:'.$jti, 1, $vence)) {
            return null;
        }

        return $consumidor;
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
