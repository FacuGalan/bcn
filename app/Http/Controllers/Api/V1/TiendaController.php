<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Consumidor;
use App\Models\FormaPago;
use App\Models\PedidoDelivery;
use App\Services\Pedidos\CatalogoTiendaService;
use App\Services\Pedidos\DeliveryEnvioService;
use App\Services\Pedidos\PuntosTiendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints públicos de la tienda (RF-11/RF-13): datos + catálogo RF-17 +
 * franjas de entrega (RF-15). El tenant lo resuelve api.tenant por el slug
 * (deja api_sucursal/api_tienda en el request).
 */
class TiendaController extends Controller
{
    /**
     * GET /v1/tiendas/{slug} — datos públicos de la tienda/sucursal.
     *
     * Incluye el contrato de PROMESA (modo, ASAP, demoras) y las formas de
     * pago declarables contra entrega — lo que el checkout necesita para el
     * paso "¿cuándo lo querés?" y "¿cómo pagás?".
     */
    public function show(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');
        $comercio = $request->attributes->get('api_comercio');
        $config = $envioService->configDelivery($sucursal);
        $modoPromesa = (string) ($config['modo_promesa'] ?? 'manual');

        return response()->json([
            'data' => [
                'slug' => $tienda->slug,
                'nombre' => $sucursal->nombre,
                'comercio' => $comercio->nombre,
                'direccion' => $sucursal->direccion,
                'localidad' => $sucursal->localidad,
                'telefono' => $sucursal->telefono,
                'latitud' => $sucursal->latitud !== null ? (float) $sucursal->latitud : null,
                'longitud' => $sucursal->longitud !== null ? (float) $sucursal->longitud : null,
                'abierta_ahora' => $envioService->estaAbierto($sucursal),
                'takeaway_habilitado' => (bool) ($config['takeaway_habilitado'] ?? true),
                'georreferenciada' => (bool) ($config['georreferenciar_pedidos'] ?? false),
                'radio_entrega_km' => $config['radio_entrega_km'],
                'horarios_atencion' => $config['horarios_atencion'],
                'dias_laborales' => $config['dias_laborales'],
                'feriados' => $config['feriados'],
                'entrega' => [
                    'modo_promesa' => $modoPromesa,
                    'acepta_lo_antes_posible' => (bool) ($config['acepta_lo_antes_posible'] ?? true),
                    // Modo automática: para "te llega en ~X min" en el checkout.
                    'demora_base_min' => $modoPromesa === 'automatica' ? (int) ($config['demora_base_min'] ?? 0) : null,
                    'demora_min_por_km' => $modoPromesa === 'automatica' ? (float) ($config['demora_min_por_km'] ?? 0) : null,
                    // Modo franjas: los horarios del día viven en GET /franjas.
                    'usa_franjas' => $modoPromesa === 'franjas',
                ],
                'formas_pago' => $this->formasPagoPublicas($sucursal),
                // RF-T7 (Principio 11): la tienda inyecta los scripts SOLO si
                // el ID está cargado; null = sin analytics de ese proveedor.
                'analytics' => [
                    'ga4_measurement_id' => $tienda->ga4_measurement_id,
                    'meta_pixel_id' => $tienda->meta_pixel_id,
                ],
                // RF-T6 (Principio 10): design tokens efectivos (defaults del
                // core + JSON persistido) y seteos de conducta (v1: defaults).
                'tema' => $tienda->temaCompleto(),
                'comportamiento' => (object) \App\Models\Tienda::COMPORTAMIENTO_DEFAULTS,
            ],
        ]);
    }

    /**
     * GET /v1/tiendas/{slug}/puntos *(Bearer consumidor)* — saldo y reglas
     * del programa de puntos para el consumidor logueado (RF-T8, Fase 3).
     *
     * Solo-lectura: la resolución consumidor→cliente NUNCA crea el cliente
     * (eso es del alta de pedido, D11). Sin cliente materializado o programa
     * inactivo → activo:false con saldo 0 (degradación honesta, no error).
     */
    public function puntos(Request $request, PuntosTiendaService $puntosTienda): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');

        $clienteId = null;
        $consumidor = $request->user();
        if ($consumidor instanceof Consumidor) {
            $clienteId = $consumidor->clienteIdEn((int) $tienda->comercio_id);
            if ($clienteId && ! Cliente::find($clienteId)) {
                $clienteId = null;
            }
        }

        return response()->json([
            'data' => $puntosTienda->info($sucursal, $clienteId),
        ]);
    }

    /**
     * GET /v1/tiendas/{slug}/franjas?tipo=delivery|take_away — horarios de
     * entrega/retiro de la JORNADA con lugar (RF-15, modo franjas). Vacío si
     * la sucursal no trabaja por franjas o la jornada no tiene slots futuros.
     */
    public function franjas(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $config = $envioService->configDelivery($sucursal);

        $tipo = $request->query('tipo', PedidoDelivery::TIPO_DELIVERY);
        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            $tipo = PedidoDelivery::TIPO_DELIVERY;
        }

        $modoPromesa = (string) ($config['modo_promesa'] ?? 'manual');

        $franjas = $modoPromesa === 'franjas'
            ? array_map(fn ($slot) => [
                'hora' => $slot->toIso8601String(),
                'label' => $slot->format('H:i'),
            ], $envioService->franjasDisponibles($sucursal, $tipo))
            : [];

        return response()->json([
            'data' => [
                'modo_promesa' => $modoPromesa,
                'acepta_lo_antes_posible' => (bool) ($config['acepta_lo_antes_posible'] ?? true),
                'franjas' => $franjas,
            ],
        ]);
    }

    /**
     * GET /v1/tiendas/{slug}/catalogo?tipo=delivery|take_away — catálogo RF-17.
     *
     * Cache HTTP (RF-T5): es el endpoint más golpeado de la tienda. ETag +
     * max-age corto — el cliente revalida con If-None-Match y se ahorra el
     * payload (304) cuando el catálogo no cambió. Además cache SERVER-SIDE
     * del armado (60s, alineado al max-age): sin él, cada revalidación
     * re-consultaba la BD tenant y el motor de precios aunque respondiera 304.
     */
    public function catalogo(Request $request, CatalogoTiendaService $catalogoService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $comercio = $request->attributes->get('api_comercio');
        $tipo = $request->query('tipo', PedidoDelivery::TIPO_DELIVERY);

        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            $tipo = PedidoDelivery::TIPO_DELIVERY;
        }

        // Key por comercio+sucursal+tipo: el store es compartido entre tenants.
        $cacheKey = "tienda_catalogo:{$comercio->id}:{$sucursal->id}:{$tipo}";
        $payload = \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            60,
            fn () => ['data' => $catalogoService->catalogo($sucursal, $tipo)]
        );
        $etag = '"'.md5(json_encode($payload)).'"';

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=60',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304, $headers);
        }

        return response()->json($payload, 200, $headers);
    }

    /**
     * Formas de pago que el consumidor puede DECLARAR al pedir (pago contra
     * entrega/retiro, planificado): activas, habilitadas en la sucursal, no
     * mixtas ni internas del sistema ni cuenta corriente (requiere cliente).
     * El pago online (checkout integrado) es otro circuito, pendiente en el
     * spec de integraciones.
     */
    protected function formasPagoPublicas($sucursal): array
    {
        return FormaPago::query()
            ->activas()
            ->simples()
            ->where('solo_sistema', false)
            ->with('conceptoPago:id,codigo,nombre,permite_vuelto')
            ->whereHas('sucursales', fn ($q) => $q
                ->where('sucursal_id', $sucursal->id)
                ->where('formas_pago_sucursales.activo', true))
            ->orderBy('nombre')
            ->get()
            ->reject(fn ($fp) => in_array(strtoupper((string) $fp->conceptoPago?->codigo), ['CTA_CTE', 'CUENTA_CORRIENTE', 'PUNTOS'], true))
            ->map(function ($fp) use ($sucursal) {
                // Ajuste efectivo (override de sucursal > general): la tienda
                // lo muestra junto a la FP ("Efectivo -10%") y la cotización
                // con forma_pago_id lo aplica con el mismo cálculo del panel.
                $ajusteSucursal = \App\Models\FormaPagoSucursal::where('forma_pago_id', $fp->id)
                    ->where('sucursal_id', $sucursal->id)
                    ->value('ajuste_porcentaje');

                return [
                    'id' => $fp->id,
                    'nombre' => $fp->nombre,
                    'codigo' => $fp->codigo,
                    'permite_vuelto' => (bool) ($fp->conceptoPago?->permite_vuelto ?? false),
                    'ajuste_porcentaje' => (float) ($ajusteSucursal ?? $fp->ajuste_porcentaje ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
