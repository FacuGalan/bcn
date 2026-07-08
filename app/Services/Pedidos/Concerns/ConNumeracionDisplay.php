<?php

namespace App\Services\Pedidos\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de DISPLAY (turno) de los pedidos, compartida como LÓGICA entre
 * PedidoMostradorService y PedidoDeliveryService pero con CONTADOR Y CONFIG
 * PROPIOS por módulo (rev9 del spec pedidos-delivery: el llamador ya no se
 * comparte, así que cada módulo numera y resetea por su cuenta):
 *
 * - Mostrador (default del trait): config en columnas de `sucursales`
 *   (`usa_numeracion_display`, `numeracion_display_modo/_horas`) y contador
 *   `pedido_display_ultimo_numero` / `pedido_display_segmento_at`.
 * - Delivery: PedidoDeliveryService overridea los hooks para leer la config
 *   del JSON `config_delivery` y usar el contador
 *   `pedido_delivery_display_ultimo_numero` / `_segmento_at`.
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
                ->first();

            if (! $suc) {
                return null;
            }

            $config = $this->configNumeracionDisplay($suc);

            if (! $config['usa']) {
                return null;
            }

            $columnaContador = $this->columnaContadorDisplay();
            $columnaSegmento = $this->columnaSegmentoDisplay();

            $contador = (int) ($suc->{$columnaContador} ?? 0);
            $update = [];

            if (($config['modo'] ?? 'diario') === 'diario') {
                $segmentoActual = $this->inicioSegmentoDisplay($config['horas'], now());
                $segmentoGuardado = $suc->{$columnaSegmento}
                    ? Carbon::parse($suc->{$columnaSegmento})
                    : null;

                if (! $segmentoGuardado || $segmentoGuardado->lt($segmentoActual)) {
                    $contador = 0;
                    $update[$columnaSegmento] = $segmentoActual->toDateTimeString();
                }
            }

            $siguiente = $contador + 1;
            $update[$columnaContador] = $siguiente;

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
            ->update([
                $this->columnaContadorDisplay() => 0,
                $this->columnaSegmentoDisplay() => null,
            ]);

        Log::info('Numeración display reiniciada manualmente', [
            'sucursal_id' => $sucursalId,
            'usuario_id' => $usuarioId,
            'contador' => $this->columnaContadorDisplay(),
        ]);
    }

    // ==================== HOOKS (override por módulo) ====================

    /**
     * Config de numeración display desde la fila `sucursales` lockeada.
     * Default: columnas compartidas históricas (mostrador).
     *
     * @return array{usa: bool, modo: string, horas: list<int>}
     */
    protected function configNumeracionDisplay(object $suc): array
    {
        return [
            'usa' => (bool) $suc->usa_numeracion_display,
            'modo' => $suc->numeracion_display_modo ?? 'diario',
            'horas' => $this->horasResetDisplay($suc->numeracion_display_horas),
        ];
    }

    protected function columnaContadorDisplay(): string
    {
        return 'pedido_display_ultimo_numero';
    }

    protected function columnaSegmentoDisplay(): string
    {
        return 'pedido_display_segmento_at';
    }

    // ==================== INTERNOS ====================

    /**
     * Normaliza la lista de horas de reset (json crudo del row o array ya
     * decodificado) a enteros 0-23 ordenados y sin duplicados. Default `[6]`.
     *
     * @return list<int>
     */
    protected function horasResetDisplay(string|array|null $horas): array
    {
        if (is_string($horas)) {
            $horas = json_decode($horas, true) ?: [];
        }
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
