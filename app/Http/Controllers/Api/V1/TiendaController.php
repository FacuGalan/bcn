<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PedidoDelivery;
use App\Services\Pedidos\CatalogoTiendaService;
use App\Services\Pedidos\DeliveryEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints públicos de la tienda (RF-11/RF-13): datos + catálogo RF-17.
 * El tenant lo resuelve api.tenant por el slug (deja api_sucursal/api_tienda
 * en el request).
 */
class TiendaController extends Controller
{
    /**
     * GET /v1/tiendas/{slug} — datos públicos de la tienda/sucursal.
     */
    public function show(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');
        $comercio = $request->attributes->get('api_comercio');
        $config = $envioService->configDelivery($sucursal);

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
            ],
        ]);
    }

    /**
     * GET /v1/tiendas/{slug}/catalogo?tipo=delivery|take_away — catálogo RF-17.
     */
    public function catalogo(Request $request, CatalogoTiendaService $catalogoService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $tipo = $request->query('tipo', PedidoDelivery::TIPO_DELIVERY);

        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            $tipo = PedidoDelivery::TIPO_DELIVERY;
        }

        return response()->json([
            'data' => $catalogoService->catalogo($sucursal, $tipo),
        ]);
    }
}
