<?php

namespace App\Services\Pedidos;

use App\Models\DeliveryZona;

/**
 * DTO resultado de DeliveryEnvioService::cotizar() (RF-06).
 *
 * `alcance`:
 *  - 'ok': dentro de zona o radio — `costo` y `distanciaKm` válidos.
 *  - 'fuera_de_alcance': fuera del radio y sin zona que matchee. Solo
 *    confirmable con permiso pedidos_delivery.forzar_alcance (nunca API pública).
 *  - 'desconocido': sin coordenadas o georreferenciación apagada — no hay
 *    cálculo automático, el costo es manual (D5).
 */
class CotizacionEnvio
{
    public const ALCANCE_OK = 'ok';

    public const ALCANCE_FUERA = 'fuera_de_alcance';

    public const ALCANCE_DESCONOCIDO = 'desconocido';

    public function __construct(
        public readonly string $alcance,
        public readonly ?float $costo = null,
        public readonly ?float $distanciaKm = null,
        public readonly ?DeliveryZona $zona = null,
        public readonly ?int $demoraEstimadaMin = null,
    ) {}

    public function esOk(): bool
    {
        return $this->alcance === self::ALCANCE_OK;
    }

    public function esFueraDeAlcance(): bool
    {
        return $this->alcance === self::ALCANCE_FUERA;
    }

    /**
     * @return array<string, mixed> Forma serializable (Livewire / API Resource).
     */
    public function toArray(): array
    {
        return [
            'alcance' => $this->alcance,
            'costo' => $this->costo,
            'distancia_km' => $this->distanciaKm,
            'zona_id' => $this->zona?->id,
            'zona_nombre' => $this->zona?->nombre,
            'demora_estimada_min' => $this->demoraEstimadaMin,
        ];
    }
}
