<?php

namespace App\Services\Pedidos\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de DISPLAY (turno) compartida entre PedidoMostradorService y
 * PedidoDeliveryService.
 *
 * El contador `sucursales.pedido_display_ultimo_numero` es ÚNICO por sucursal
 * y lo COMPARTEN mostrador y delivery a propósito: el llamador/pantalla
 * pública canta números únicos sin colisiones entre ambos módulos. Extraído
 * a trait para que la lógica (lockForUpdate + segmentos de reset diario) no
 * divergiera al espejarse (spec pedidos-delivery, nota de implementación).
 */
trait ConNumeracionDisplay
{
    /**
     * Reserva atómicamente el próximo número de DISPLAY (turno) de la sucursal,
     * o null si la sucursal no usa numeración de display (entonces se muestra el
     * `numero` permanente). En modo `diario` reinicia el contador cuando el
     * segmento (definido por las horas de reset) avanza.
     */
    public function siguienteNumeroDisplay(int $sucursalId): ?int
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($sucursalId) {
            $suc = DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->lockForUpdate()
                ->first([
                    'usa_numeracion_display', 'numeracion_display_modo',
                    'numeracion_display_horas', 'pedido_display_ultimo_numero',
                    'pedido_display_segmento_at',
                ]);

            if (! $suc || ! $suc->usa_numeracion_display) {
                return null;
            }

            $contador = (int) ($suc->pedido_display_ultimo_numero ?? 0);
            $update = [];

            if (($suc->numeracion_display_modo ?? 'diario') === 'diario') {
                $segmentoActual = $this->inicioSegmentoDisplay(
                    $this->horasResetDisplay($suc->numeracion_display_horas),
                    now()
                );
                $segmentoGuardado = $suc->pedido_display_segmento_at
                    ? Carbon::parse($suc->pedido_display_segmento_at)
                    : null;

                if (! $segmentoGuardado || $segmentoGuardado->lt($segmentoActual)) {
                    $contador = 0;
                    $update['pedido_display_segmento_at'] = $segmentoActual->toDateTimeString();
                }
            }

            $siguiente = $contador + 1;
            $update['pedido_display_ultimo_numero'] = $siguiente;

            DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->update($update);

            return $siguiente;
        });
    }

    /**
     * Reinicia a 0 la numeración de display (modo manual, con permiso). Audita.
     */
    public function reiniciarNumeracionDisplay(int $sucursalId, int $usuarioId): void
    {
        DB::connection('pymes_tenant')
            ->table('sucursales')
            ->where('id', $sucursalId)
            ->update(['pedido_display_ultimo_numero' => 0, 'pedido_display_segmento_at' => null]);

        Log::info('Numeración display reiniciada manualmente', [
            'sucursal_id' => $sucursalId,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Normaliza la lista de horas de reset (json crudo del row) a enteros 0-23
     * ordenados y sin duplicados. Default `[6]`.
     *
     * @return list<int>
     */
    private function horasResetDisplay(?string $json): array
    {
        $horas = $json ? (json_decode($json, true) ?: []) : [];
        $horas = array_values(array_unique(array_filter(
            array_map('intval', is_array($horas) ? $horas : []),
            fn ($h) => $h >= 0 && $h <= 23
        )));
        sort($horas);

        return $horas ?: [6];
    }

    /**
     * Inicio del segmento actual: el último horario de reset (de hoy o ayer) que
     * sea <= ahora. Define a qué "turno" pertenece el contador.
     */
    private function inicioSegmentoDisplay(array $horas, Carbon $ahora): Carbon
    {
        $inicio = null;

        foreach ([$ahora->copy()->subDay(), $ahora->copy()] as $dia) {
            foreach ($horas as $h) {
                $cand = $dia->copy()->setTime($h, 0, 0);
                if ($cand->lte($ahora) && ($inicio === null || $cand->gt($inicio))) {
                    $inicio = $cand;
                }
            }
        }

        return $inicio ?? $ahora->copy()->startOfDay();
    }
}
