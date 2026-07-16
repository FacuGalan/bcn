<?php

namespace App\Services\Pedidos;

use App\Models\DeliveryZona;
use App\Models\Rubro;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Marketplace de tiendas (RF-T4, spec tienda-online): "qué tiendas llegan
 * a mi ubicación", con filtro por rubro. Cross-comercio.
 *
 * Fan-out CONTROLADO: los datos geográficos/branding de cada tienda viven
 * en la BD tenant de su comercio (sucursal + config_delivery + zonas). Se
 * cachean por tienda (TTL corto) y el matching por ubicación (distancia +
 * punto-en-polígono) se resuelve en memoria por request, sin re-abrir
 * conexiones tenant. Degradación honesta (D5): una tienda sin
 * georreferenciar no inventa alcance — se devuelve `alcance: desconocido`.
 */
class MarketplaceTiendasService
{
    /** TTL del snapshot cacheado por tienda (segundos). */
    public const TTL_TIENDA_SEG = 300;

    /** TTL del catálogo de rubros (segundos). */
    public const TTL_RUBROS_SEG = 3600;

    public function __construct(
        protected TenantService $tenantService,
        protected DeliveryEnvioService $envioService,
    ) {}

    /**
     * Tiendas habilitadas, opcionalmente filtradas por ubicación del
     * consumidor y rubro del comercio. Con ubicación: excluye las que NO
     * llegan (alcance `fuera`) y ordena por distancia; sin ubicación:
     * todas, orden alfabético.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(?float $lat = null, ?float $lng = null, ?int $rubroId = null): array
    {
        $tiendas = Tienda::habilitadas()->with('comercio:id,nombre,rubro_id')->get();

        if ($rubroId !== null) {
            $tiendas = $tiendas->filter(fn (Tienda $t) => (int) ($t->comercio?->rubro_id) === $rubroId);
        }

        $rubros = Rubro::query()->pluck('nombre', 'id');

        $resultado = collect();

        foreach ($tiendas as $tienda) {
            $snapshot = $this->snapshotTienda($tienda);
            if (! ($snapshot['valida'] ?? false)) {
                continue;
            }

            $card = $this->evaluarUbicacion($snapshot, $lat, $lng);

            if ($lat !== null && $card['alcance'] === 'fuera') {
                continue;
            }

            $rubroIdComercio = $tienda->comercio?->rubro_id;
            $resultado->push([
                'slug' => $tienda->slug,
                'nombre' => $snapshot['nombre'],
                'comercio' => $tienda->comercio?->nombre,
                'rubro' => $rubroIdComercio ? [
                    'id' => (int) $rubroIdComercio,
                    'nombre' => $rubros->get($rubroIdComercio),
                ] : null,
                'logo_url' => $snapshot['logo_url'],
                'direccion' => $snapshot['direccion'],
                'localidad' => $snapshot['localidad'],
                'latitud' => $snapshot['latitud'],
                'longitud' => $snapshot['longitud'],
                'abierta_ahora' => $this->envioService->estaAbiertoSegunConfig($snapshot['config_calendario']),
                'takeaway_habilitado' => $snapshot['takeaway_habilitado'],
                'alcance' => $card['alcance'],
                'distancia_km' => $card['distancia_km'],
            ]);
        }

        return ($lat !== null
            ? $resultado->sortBy(fn (array $c) => $c['distancia_km'] ?? PHP_FLOAT_MAX)
            : $resultado->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE))
            ->values()->all();
    }

    /**
     * Catálogo global de rubros activos (para el filtro del marketplace).
     */
    public function rubros(): array
    {
        return Cache::remember('marketplace_rubros', self::TTL_RUBROS_SEG, fn () => Rubro::activos()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'slug'])
            ->map(fn (Rubro $r) => ['id' => $r->id, 'nombre' => $r->nombre, 'slug' => $r->slug])
            ->all());
    }

    /**
     * Snapshot cacheado de una tienda: lo que cuesta caro (conexión tenant)
     * se lee una vez cada TTL_TIENDA_SEG. `valida: false` cachea también las
     * tiendas con sucursal inactiva/sin delivery (no reintentar por request).
     */
    protected function snapshotTienda(Tienda $tienda): array
    {
        return Cache::remember(
            "marketplace_tienda_{$tienda->id}",
            self::TTL_TIENDA_SEG,
            function () use ($tienda) {
                try {
                    $this->tenantService->usarComercioParaProceso((int) $tienda->comercio_id);

                    $sucursal = Sucursal::find($tienda->sucursal_id);
                    if (! $sucursal || ! $sucursal->activa || ! $sucursal->usa_delivery) {
                        return ['valida' => false];
                    }

                    $config = $this->envioService->configDelivery($sucursal);

                    return [
                        'valida' => true,
                        'nombre' => $sucursal->nombre,
                        'direccion' => $sucursal->direccion,
                        'localidad' => $sucursal->localidad,
                        'logo_url' => $sucursal->logoPantallaClienteUrl(),
                        'latitud' => $sucursal->latitud !== null ? (float) $sucursal->latitud : null,
                        'longitud' => $sucursal->longitud !== null ? (float) $sucursal->longitud : null,
                        'georreferenciada' => (bool) ($config['georreferenciar_pedidos'] ?? false),
                        'radio_entrega_km' => $config['radio_entrega_km'] ?? null,
                        'takeaway_habilitado' => (bool) ($config['takeaway_habilitado'] ?? true),
                        'config_calendario' => [
                            'dias_laborales' => $config['dias_laborales'] ?? null,
                            'feriados' => $config['feriados'] ?? null,
                            'horarios_atencion' => $config['horarios_atencion'] ?? null,
                        ],
                        // Solo los vértices: alcanzan para punto-en-polígono.
                        'zonas_poligonos' => $this->envioService->zonasDibujadas($sucursal)
                            ->map(fn (DeliveryZona $z) => $z->poligono)
                            ->values()->all(),
                    ];
                } catch (\Throwable $e) {
                    Log::error('Marketplace: tienda inaccesible', [
                        'tienda_id' => $tienda->id,
                        'comercio_id' => $tienda->comercio_id,
                        'error' => $e->getMessage(),
                    ]);

                    return ['valida' => false];
                }
            }
        );
    }

    /**
     * Alcance/distancia contra el punto del consumidor, con la MISMA
     * semántica de DeliveryEnvioService::cotizar (zonas dibujadas definen;
     * si no hay, radio general; sin georreferencia = desconocido).
     *
     * @return array{alcance: string, distancia_km: float|null}
     */
    protected function evaluarUbicacion(array $snapshot, ?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['alcance' => 'desconocido', 'distancia_km' => null];
        }

        if (! $snapshot['georreferenciada'] || $snapshot['latitud'] === null || $snapshot['longitud'] === null) {
            return ['alcance' => 'desconocido', 'distancia_km' => null];
        }

        $distancia = round($this->envioService->distanciaKm($snapshot['latitud'], $snapshot['longitud'], $lat, $lng), 2);

        if (! empty($snapshot['zonas_poligonos'])) {
            foreach ($snapshot['zonas_poligonos'] as $poligono) {
                if ((new DeliveryZona(['poligono' => $poligono]))->contienePunto($lat, $lng)) {
                    return ['alcance' => 'ok', 'distancia_km' => $distancia];
                }
            }

            return ['alcance' => 'fuera', 'distancia_km' => $distancia];
        }

        $radio = $snapshot['radio_entrega_km'];
        if ($radio !== null && $distancia > (float) $radio) {
            return ['alcance' => 'fuera', 'distancia_km' => $distancia];
        }

        return ['alcance' => 'ok', 'distancia_km' => $distancia];
    }
}
