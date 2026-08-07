<?php

namespace App\Services\Consumidores;

use App\Models\Consumidor;
use App\Models\ConsumidorDispositivo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dispositivos recordados del consumidor (RF-T66, spec
 * tienda-sesion-persistente): remember-token rotativo patrón
 * selector/validator.
 *
 * - El validator NUNCA se persiste plano (solo sha256); el par viaja en una
 *   cookie cifrada de la tienda — el Bearer jamás sale al navegador.
 * - Cada canje ROTA el validator y desliza el vencimiento: robar la cookie
 *   sirve una sola vez, y el dueño legítimo queda con un validator viejo →
 *   el próximo canje detecta el reuso y revoca la familia entera.
 */
class DispositivoService
{
    /** Máximo de dispositivos por consumidor (al emitir de más, se poda el menos usado). */
    public const MAX_DISPOSITIVOS = 10;

    /** Vencimiento deslizante del dispositivo (días desde el último canje). */
    public const TTL_DIAS = 365;

    /**
     * Emite un par nuevo para el consumidor. El validator retornado es la
     * ÚNICA copia en texto plano que existirá.
     *
     * @return array{selector: string, validator: string}
     */
    public function emitir(Consumidor $consumidor, ?string $userAgent, ?string $ip): array
    {
        $this->podar($consumidor);

        $selector = Str::random(24);
        $validator = Str::random(48);

        $consumidor->dispositivos()->create([
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'nombre' => $this->nombreDesdeUserAgent($userAgent),
            'ip_ultima' => $ip,
            'expira_at' => now()->addDays(self::TTL_DIAS),
        ]);

        return ['selector' => $selector, 'validator' => $validator];
    }

    /**
     * Canjea un par: si es válido rota el validator, desliza el vencimiento
     * y devuelve el consumidor + el par nuevo. Null si no existe, venció, o
     * el validator no matchea (reuso ⇒ revoca TODOS los dispositivos del
     * consumidor: familia comprometida).
     *
     * @return array{consumidor: Consumidor, dispositivo: array{selector: string, validator: string}}|null
     */
    public function canjear(string $selector, string $validator, ?string $userAgent, ?string $ip): ?array
    {
        $dispositivo = ConsumidorDispositivo::where('selector', $selector)->first();

        if (! $dispositivo) {
            return null;
        }

        if ($dispositivo->expira_at->isPast()) {
            $dispositivo->delete();

            return null;
        }

        if (! hash_equals($dispositivo->validator_hash, hash('sha256', $validator))) {
            Log::warning('Reuso de validator de dispositivo de consumidor: familia revocada', [
                'consumidor_id' => $dispositivo->consumidor_id,
                'selector' => $selector,
                'ip' => $ip,
            ]);

            ConsumidorDispositivo::where('consumidor_id', $dispositivo->consumidor_id)->delete();

            return null;
        }

        $nuevoValidator = Str::random(48);

        $dispositivo->forceFill([
            'validator_hash' => hash('sha256', $nuevoValidator),
            // El nombre se refresca con el UA de quien CANJEA: un dispositivo
            // emitido para pairing (RF-T68) nace con el UA del navegador que
            // lo pidió y se corrige al primer canje desde el webview.
            'nombre' => $this->nombreDesdeUserAgent($userAgent) ?? $dispositivo->nombre,
            'ip_ultima' => $ip,
            'ultimo_uso_at' => now(),
            'expira_at' => now()->addDays(self::TTL_DIAS),
        ])->save();

        return [
            'consumidor' => $dispositivo->consumidor,
            'dispositivo' => ['selector' => $selector, 'validator' => $nuevoValidator],
        ];
    }

    public function revocarTodos(Consumidor $consumidor): void
    {
        $consumidor->dispositivos()->delete();
    }

    /**
     * Deja lugar para un dispositivo nuevo: si ya está en el máximo, borra
     * los menos usados (los que nunca canjearon puntúan por creación).
     */
    protected function podar(Consumidor $consumidor): void
    {
        $sobran = $consumidor->dispositivos()->count() - (self::MAX_DISPOSITIVOS - 1);

        if ($sobran <= 0) {
            return;
        }

        $consumidor->dispositivos()
            ->orderByRaw('COALESCE(ultimo_uso_at, created_at) asc')
            ->orderBy('id')
            ->limit($sobran)
            ->delete();
    }

    /**
     * Nombre amigable para "Mis dispositivos" a partir del User-Agent
     * ("Chrome · Android", "Instagram · iPhone"). Best-effort, sin libs.
     */
    protected function nombreDesdeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        $app = match (true) {
            str_contains($userAgent, 'Instagram') => 'Instagram',
            (bool) preg_match('/FBAN|FBAV|FB_IAB/', $userAgent) => 'Facebook',
            str_contains($userAgent, 'Firefox') || str_contains($userAgent, 'FxiOS') => 'Firefox',
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'Chrome') || str_contains($userAgent, 'CriOS') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => __('Navegador'),
        };

        $so = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $userAgent) => 'iPhone',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return mb_substr($so !== null ? "{$app} · {$so}" : $app, 0, 120);
    }
}
