<?php

namespace App\Http\Controllers\Api\V1\Integracion;

use App\Http\Controllers\Controller;
use App\Models\Repartidor;
use App\Services\Pedidos\DeliveryEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Config y repartidores para integradores (RF-11, ability config:read).
 */
class ConfigController extends Controller
{
    /**
     * GET /v1/delivery/config — configuración operativa de la sucursal.
     */
    public function show(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');
        $config = $envioService->configDelivery($sucursal);

        return response()->json([
            'data' => [
                'sucursal_id' => (int) $sucursal->id,
                'sucursal' => $sucursal->nombre,
                'usa_delivery' => (bool) $sucursal->usa_delivery,
                'abierta_ahora' => $envioService->estaAbierto($sucursal),
                'georreferenciar_pedidos' => (bool) $config['georreferenciar_pedidos'],
                'radio_entrega_km' => $config['radio_entrega_km'],
                'costo_envio_base' => (float) $config['costo_envio_base'],
                'costo_por_km_extra' => (float) $config['costo_por_km_extra'],
                'km_incluidos_en_base' => (float) $config['km_incluidos_en_base'],
                'takeaway_habilitado' => (bool) $config['takeaway_habilitado'],
                'exigir_repartidor' => (bool) $config['exigir_repartidor'],
                'aceptacion_pedidos_externos' => $config['aceptacion_pedidos_externos'],
                'horarios_atencion' => $config['horarios_atencion'],
                'dias_laborales' => $config['dias_laborales'],
                'feriados' => $config['feriados'],
                'modo_promesa' => $config['modo_promesa'],
            ],
        ]);
    }

    /**
     * GET /v1/repartidores — repartidores activos de la sucursal.
     */
    public function repartidores(Request $request): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');

        $repartidores = Repartidor::activos()
            ->porSucursal((int) $sucursal->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'telefono', 'tipo']);

        return response()->json(['data' => $repartidores]);
    }
}
