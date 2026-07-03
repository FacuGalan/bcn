<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PedidoDeliveryResource;
use App\Models\Consumidor;
use App\Models\PedidoDelivery;
use App\Services\Pedidos\PedidoTiendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pedidos públicos de la tienda (RF-11/RF-12): alta de invitado/consumidor +
 * seguimiento por token (sin auth, throttled).
 */
class PedidoPublicoController extends Controller
{
    /**
     * POST /v1/tiendas/{slug}/pedidos — alta de pedido externo.
     *
     * Invitado: `cliente.{nombre,telefono,email}`. Consumidor: bearer token
     * de Sanctum del guard consumidores (opcional — la tienda lo usará).
     * Según la config de la sucursal (D14) entra "por aceptar" (manual) o
     * confirmado (automática).
     */
    public function store(Request $request, PedidoTiendaService $tiendaService): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => 'required|in:delivery,take_away',
            'items' => 'required|array|min:1|max:100',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'items.*.opcionales' => 'nullable|array',
            'items.*.opcionales.*.opcional_id' => 'required|integer',
            'items.*.opcionales.*.cantidad' => 'nullable|numeric|min:0.001',
            'cliente.nombre' => 'required_without:consumidor|string|max:150',
            'cliente.telefono' => 'nullable|string|max:30',
            'cliente.email' => 'nullable|email|max:150',
            'direccion.direccion' => 'required_if:tipo,delivery|string|max:255',
            'direccion.referencia' => 'nullable|string|max:255',
            'direccion.latitud' => 'nullable|numeric|between:-90,90',
            'direccion.longitud' => 'nullable|numeric|between:-180,180',
            'direccion.localidad_id' => 'nullable|integer',
            'cupon_codigo' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string|max:1000',
            'datos_fiscales' => 'nullable|array',
            'origen_referencia' => 'nullable|string|max:100',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');

        // Consumidor logueado (opcional): bearer token del guard consumidores.
        $consumidor = $request->user('sanctum');
        $consumidor = $consumidor instanceof Consumidor ? $consumidor : null;

        $datos['origen'] = PedidoDelivery::ORIGEN_TIENDA;

        $pedido = $tiendaService->crearPedidoExterno($sucursal, $datos, $tienda, $consumidor);

        return response()->json([
            'data' => new PedidoDeliveryResource($pedido->fresh(['detalles.opcionales', 'zona', 'repartidor'])),
        ], 201);
    }

    /**
     * GET /v1/tiendas/{slug}/pedidos/{token} — seguimiento público (RF-11).
     *
     * Vista recortada: estado, tiempos y repartidor en camino. El token ULID
     * es la credencial (sin enumeración: 404 genérico).
     */
    public function show(Request $request, string $slug, string $token): JsonResponse
    {
        $pedido = PedidoDelivery::with(['repartidor:id,nombre'])
            ->where('token_seguimiento', $token)
            ->first();

        $sucursal = $request->attributes->get('api_sucursal');

        if (! $pedido || (int) $pedido->sucursal_id !== (int) $sucursal->id) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'numero' => $pedido->numero_visible,
                'tipo' => $pedido->tipo,
                'estado' => $pedido->estado_pedido,
                'estado_label' => $pedido->estado_label,
                'por_aceptar' => $pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR
                    && $pedido->origen !== PedidoDelivery::ORIGEN_PANEL,
                'cancelado_motivo' => $pedido->estado_pedido === PedidoDelivery::ESTADO_CANCELADO
                    ? $pedido->motivo_cancelacion
                    : null,
                'hora_pactada_at' => $pedido->hora_pactada_at?->toIso8601String(),
                'repartidor_en_camino' => $pedido->estado_pedido === PedidoDelivery::ESTADO_EN_CAMINO
                    ? $pedido->repartidor?->nombre
                    : null,
                'total_final' => (float) $pedido->total_final,
                'estado_pago' => $pedido->estado_pago,
                'timestamps' => [
                    'confirmado_at' => $pedido->confirmado_at?->toIso8601String(),
                    'en_preparacion_at' => $pedido->en_preparacion_at?->toIso8601String(),
                    'listo_at' => $pedido->listo_at?->toIso8601String(),
                    'en_camino_at' => $pedido->en_camino_at?->toIso8601String(),
                    'entregado_at' => $pedido->entregado_at?->toIso8601String(),
                ],
                'canal_tiempo_real' => 'pedidos-delivery.seguimiento.'.$pedido->token_seguimiento,
            ],
        ]);
    }

    /**
     * POST /v1/tiendas/{slug}/pedidos/{token}/cancelar — cancelación por el
     * CONSUMIDOR (RF-12): permitida hasta `confirmado` (antes de preparación).
     */
    public function cancelar(Request $request, string $slug, string $token, \App\Services\Pedidos\PedidoDeliveryService $pedidoService): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');

        $pedido = PedidoDelivery::where('token_seguimiento', $token)->first();
        if (! $pedido || (int) $pedido->sucursal_id !== (int) $sucursal->id) {
            abort(404);
        }

        if (! in_array($pedido->estado_pedido, [PedidoDelivery::ESTADO_BORRADOR, PedidoDelivery::ESTADO_CONFIRMADO], true)) {
            throw new \Exception(__('El pedido ya está en preparación: contactá al comercio para cancelarlo'));
        }

        $pedidoService->cancelarPedido($pedido, __('Cancelado por el consumidor'));

        return response()->json(['data' => ['estado' => 'cancelado']]);
    }
}
