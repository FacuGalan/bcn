<?php

namespace App\Services\Pedidos;

use App\Models\DeliveryZona;
use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use Illuminate\Support\Carbon;

/**
 * Servicio de cotización de envío y reglas de entrega (RF-05/RF-06/RF-15).
 *
 * Resolución de la cotización (D5/D7):
 *   1. Sin coordenadas o georreferenciación OFF → alcance 'desconocido'
 *      (costo manual, el sistema no inventa).
 *   2. Zona activa que matchee (por orden de prioridad, respetando su rango
 *      horario) → costo de la zona.
 *   3. Sin zona: distancia Haversine a la sucursal → dentro de
 *      radio_entrega_km ⇒ costo_base + max(0, km − km_incluidos) × costo_km;
 *      fuera ⇒ 'fuera_de_alcance'.
 *
 * Promesa de entrega CORE (D22): modo 'automatica' (base + min/km) y 'manual'
 * (botones al aceptar). Franjas con cupos, pedidos programados y Routes API
 * quedan para Fase 8.
 *
 * Servicio de LECTURA pura (sin transacciones): calcula, no persiste.
 */
class DeliveryEnvioService
{
    private const RADIO_TIERRA_KM = 6371.0;

    /**
     * Cotiza el envío a un punto. `$cuando` permite cotizar para otro momento
     * (los rangos horarios de zona dependen de la hora); default ahora.
     */
    public function cotizar(Sucursal $sucursal, ?float $lat, ?float $lng, ?Carbon $cuando = null): CotizacionEnvio
    {
        $config = $this->configDelivery($sucursal);
        $cuando ??= now();

        if (! $config['georreferenciar_pedidos'] || $lat === null || $lng === null) {
            return new CotizacionEnvio(alcance: CotizacionEnvio::ALCANCE_DESCONOCIDO);
        }

        $distanciaKm = $this->distanciaKm(
            (float) $sucursal->latitud,
            (float) $sucursal->longitud,
            $lat,
            $lng
        );

        // 1) Zona con prioridad sobre el cálculo por km (D7).
        $zona = $this->matchearZona($sucursal, $lat, $lng, $cuando);
        if ($zona) {
            return new CotizacionEnvio(
                alcance: CotizacionEnvio::ALCANCE_OK,
                costo: (float) $zona->costo_envio,
                distanciaKm: round($distanciaKm, 2),
                zona: $zona,
                demoraEstimadaMin: $this->estimarDemora($config, $distanciaKm),
            );
        }

        // 2) Radio general de la sucursal.
        $radioMax = $config['radio_entrega_km'];
        if ($radioMax !== null && $distanciaKm > (float) $radioMax) {
            return new CotizacionEnvio(
                alcance: CotizacionEnvio::ALCANCE_FUERA,
                distanciaKm: round($distanciaKm, 2),
            );
        }

        $kmExtra = max(0.0, $distanciaKm - (float) $config['km_incluidos_en_base']);
        $costo = round((float) $config['costo_envio_base'] + $kmExtra * (float) $config['costo_por_km_extra'], 2);

        return new CotizacionEnvio(
            alcance: CotizacionEnvio::ALCANCE_OK,
            costo: $costo,
            distanciaKm: round($distanciaKm, 2),
            demoraEstimadaMin: $this->estimarDemora($config, $distanciaKm),
        );
    }

    /**
     * Primera zona activa de la sucursal (por `orden`, luego id) cuyo círculo
     * contiene el punto y cuyo rango horario está activo en `$cuando`.
     */
    public function matchearZona(Sucursal $sucursal, float $lat, float $lng, Carbon $cuando): ?DeliveryZona
    {
        return DeliveryZona::porSucursal($sucursal->id)
            ->activas()
            ->ordenadas()
            ->get()
            ->first(function (DeliveryZona $zona) use ($lat, $lng, $cuando) {
                $dist = $this->distanciaKm(
                    (float) $zona->centro_lat,
                    (float) $zona->centro_lng,
                    $lat,
                    $lng
                );

                return $dist <= (float) $zona->radio_km
                    && $this->rangoHorarioActivo($zona->rangos_horarios, $cuando);
            });
    }

    /**
     * Distancia Haversine en km (línea recta, v1 — distancia por calle vía
     * API de rutas queda como mejora futura).
     */
    public function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::RADIO_TIERRA_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Demora estimada en minutos según `modo_promesa=automatica`:
     * base + min/km × km. Con otro modo devuelve null (manual la fija el
     * operador al aceptar; franjas es Fase 8). `usar_maps_para_demora`
     * (Routes API) es Fase 8 — v1 siempre calcula por km.
     */
    public function estimarDemora(array $config, ?float $km): ?int
    {
        if (($config['modo_promesa'] ?? 'manual') !== 'automatica' || $km === null) {
            return null;
        }

        return (int) ceil((float) $config['demora_base_min'] + (float) $config['demora_min_por_km'] * $km);
    }

    /**
     * Hora pactada según el modo de promesa CORE (RF-15):
     *  - 'automatica' (delivery con distancia): ahora + demora estimada.
     *  - 'manual' / 'franjas' / sin distancia: null (la fija el operador al
     *    aceptar; franjas es Fase 8).
     */
    public function calcularHoraPactada(Sucursal $sucursal, ?float $km): ?Carbon
    {
        $demora = $this->estimarDemora($this->configDelivery($sucursal), $km);

        return $demora !== null ? now()->addMinutes($demora) : null;
    }

    /**
     * Franjas horarias elegibles para HOY con `modo_promesa=franjas` (RF-15):
     * los horarios de entrega que el comercio dio de alta A MANO en la config
     * (`franjas`: hora exacta + días que aplica + tipo que sirve), filtrados
     * por tipo de pedido, día de hoy, feriados/días laborales y hora futura.
     * Devuelve Carbon[] ordenados; vacío si hoy no se atiende o no hay
     * horarios que apliquen. Los CUPOS por franja quedan para Fase 8.
     *
     * @param  string|null  $tipo  'delivery' | 'take_away' | null (ambos)
     * @return Carbon[]
     */
    public function franjasDisponibles(Sucursal $sucursal, ?string $tipo = null, ?Carbon $desde = null): array
    {
        $config = $this->configDelivery($sucursal);
        $desde ??= now();

        $diasLaborales = array_map('intval', (array) ($config['dias_laborales'] ?? [1, 2, 3, 4, 5, 6, 7]));
        if (! in_array($desde->isoWeekday(), $diasLaborales, true)) {
            return [];
        }
        if (in_array($desde->toDateString(), (array) ($config['feriados'] ?? []), true)) {
            return [];
        }

        $dia = $desde->isoWeekday();
        $slots = [];
        foreach ((array) ($config['franjas'] ?? []) as $franja) {
            $hora = trim((string) ($franja['hora'] ?? ''));
            if ($hora === '') {
                continue;
            }

            $dias = array_map('intval', (array) ($franja['dias'] ?? [1, 2, 3, 4, 5, 6, 7]));
            if (! in_array($dia, $dias, true)) {
                continue;
            }

            // Flags de tipo (ausentes = aplica a ambos, retrocompatible).
            if ($tipo === PedidoDelivery::TIPO_DELIVERY && ! ($franja['delivery'] ?? true)) {
                continue;
            }
            if ($tipo === PedidoDelivery::TIPO_TAKE_AWAY && ! ($franja['take_away'] ?? true)) {
                continue;
            }

            $slot = $desde->copy()->setTimeFromTimeString($hora);
            if ($slot->greaterThanOrEqualTo($desde)) {
                $slots[$slot->format('H:i')] = $slot;
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Si la sucursal está atendiendo pedidos en `$cuando` según su calendario
     * (RF-05, D16): día laboral + no feriado + dentro de horarios_atencion.
     * La API pública rechaza pedidos fuera de horario; el panel solo advierte
     * (operador manda).
     */
    public function estaAbierto(Sucursal $sucursal, ?Carbon $cuando = null): bool
    {
        $config = $this->configDelivery($sucursal);
        $cuando ??= now();

        $diasLaborales = array_map('intval', (array) ($config['dias_laborales'] ?? [1, 2, 3, 4, 5, 6, 7]));
        if (! in_array($cuando->isoWeekday(), $diasLaborales, true)) {
            return false;
        }

        if (in_array($cuando->toDateString(), (array) ($config['feriados'] ?? []), true)) {
            return false;
        }

        return $this->rangoHorarioActivo($config['horarios_atencion'], $cuando);
    }

    /**
     * Config de delivery de la sucursal con DEFAULTS mergeados (RF-05).
     */
    public function configDelivery(Sucursal $sucursal): array
    {
        return $sucursal->getConfigDelivery();
    }

    /**
     * Evalúa rangos horarios `[{dias:[1..7 ISO], desde:'19:00', hasta:'23:30'}]`.
     * NULL o lista vacía = siempre activo. Soporta rangos que cruzan
     * medianoche (desde > hasta: activo si hora >= desde O hora <= hasta).
     */
    private function rangoHorarioActivo(mixed $rangos, Carbon $cuando): bool
    {
        if (empty($rangos) || ! is_array($rangos)) {
            return true;
        }

        $dia = $cuando->isoWeekday();
        $hora = $cuando->format('H:i');

        foreach ($rangos as $rango) {
            $dias = array_map('intval', (array) ($rango['dias'] ?? [1, 2, 3, 4, 5, 6, 7]));
            if (! in_array($dia, $dias, true)) {
                continue;
            }

            $desde = (string) ($rango['desde'] ?? '00:00');
            $hasta = (string) ($rango['hasta'] ?? '23:59');

            $activo = $desde <= $hasta
                ? ($hora >= $desde && $hora <= $hasta)
                : ($hora >= $desde || $hora <= $hasta); // cruza medianoche

            if ($activo) {
                return true;
            }
        }

        return false;
    }
}
