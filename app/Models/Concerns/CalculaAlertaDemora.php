<?php

namespace App\Models\Concerns;

/**
 * Timestamps de alerta de demora de un pedido (delivery/mostrador) para el
 * tick client-side (Alpine `demoraAlerta`): el navegador compara contra estos
 * instantes y colorea la card sin round-trips al servidor.
 *
 * - CON promesa (hora_pactada_at): amarillo `alerta_amarilla` minutos ANTES
 *   de vencer; rojo al vencer.
 * - SIN promesa (ASAP / manual sin hora / mostrador): amarillo/rojo cuando la
 *   edad desde la confirmación supera cada umbral.
 *
 * Umbrales en 0 o pedido fuera de juego (borrador/entregado/cancelado) ⇒ null.
 */
trait CalculaAlertaDemora
{
    /**
     * @return array{amarillo: ?string, rojo: ?string, desde: string}|null ISO-8601, null = sin alerta
     */
    public function alertaDemora(int $amarillaMin, int $rojaMin): ?array
    {
        if ($amarillaMin <= 0 && $rojaMin <= 0) {
            return null;
        }
        if (! in_array($this->estado_pedido, ['confirmado', 'en_preparacion', 'listo', 'en_camino'], true)) {
            return null;
        }

        $desde = $this->confirmado_at ?? $this->fecha ?? $this->created_at;
        if (! $desde) {
            return null;
        }

        // Mostrador no tiene promesa: el operador mide edad desde confirmación.
        $promesa = $this->hora_pactada_at ?? null;
        if ($promesa) {
            return [
                'amarillo' => $amarillaMin > 0 ? $promesa->copy()->subMinutes($amarillaMin)->toIso8601String() : null,
                'rojo' => $promesa->toIso8601String(),
                'desde' => $desde->toIso8601String(),
            ];
        }

        return [
            'amarillo' => $amarillaMin > 0 ? $desde->copy()->addMinutes($amarillaMin)->toIso8601String() : null,
            'rojo' => $rojaMin > 0 ? $desde->copy()->addMinutes($rojaMin)->toIso8601String() : null,
            'desde' => $desde->toIso8601String(),
        ];
    }
}
