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
        // Consumidor logueado (opcional): bearer token del guard consumidores.
        // Se resuelve ANTES de validar: con consumidor, `cliente.nombre` es
        // opcional (la identidad viene del token, no de un campo del payload).
        $consumidor = $request->user('sanctum');
        $consumidor = $consumidor instanceof Consumidor ? $consumidor : null;

        $datos = $request->validate([
            'tipo' => 'required|in:delivery,take_away',
            'items' => 'required|array|min:1|max:100',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'items.*.opcionales' => 'nullable|array',
            'items.*.opcionales.*.opcional_id' => 'required|integer',
            'items.*.opcionales.*.cantidad' => 'nullable|numeric|min:0.001',
            'cliente.nombre' => ($consumidor ? 'nullable' : 'required').'|string|max:150',
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
            // Promesa de entrega (RF-15): ASAP o franja elegida (modo franjas,
            // ISO 8601 de GET /franjas). El service la valida contra la config.
            'entrega.lo_antes_posible' => 'nullable|boolean',
            'entrega.franja' => 'nullable|date',
            // Encargo para día futuro (RF-T16): el service lo valida contra
            // el calendario de encargos y los artículos aptos.
            'entrega.programado_para' => 'nullable|date',
            // Pago declarado contra entrega/retiro (planificado): FP de
            // GET /tiendas/{slug} y, para efectivo, "¿con cuánto pagás?".
            'pago.forma_pago_id' => 'nullable|integer',
            'pago.paga_con' => 'nullable|numeric|min:0',
            // Canje de puntos (RF-T9, Fase 3): pago por el máximo canjeable.
            // Solo tiene efecto con Bearer de consumidor con cliente.
            'usar_puntos' => 'nullable|boolean',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');

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
        $pedido = PedidoDelivery::with(['repartidor:id,nombre', 'detalles.opcionales', 'detalles.articulo:id,nombre'])
            ->where('token_seguimiento', $token)
            ->first();

        $sucursal = $request->attributes->get('api_sucursal');

        if (! $pedido || (int) $pedido->sucursal_id !== (int) $sucursal->id) {
            abort(404);
        }

        // "facturado" (convertido en venta) es jerga interna: para el
        // consumidor el pedido sigue ENTREGADO. El canal de tiempo real
        // tampoco emite facturado — misma verdad en GET y WebSocket.
        $esFacturado = $pedido->estado_pedido === PedidoDelivery::ESTADO_FACTURADO;
        $estadoPublico = $esFacturado ? PedidoDelivery::ESTADO_ENTREGADO : $pedido->estado_pedido;

        $porAceptar = $pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR
            && $pedido->origen !== PedidoDelivery::ORIGEN_PANEL;

        // D14: timeout de aceptación vencido ⇒ el consumidor lo ve demorado.
        $timeoutMin = (int) (app(\App\Services\Pedidos\DeliveryEnvioService::class)
            ->configDelivery($sucursal)['timeout_aceptacion_min'] ?? 0);
        $demorado = $porAceptar
            && $timeoutMin > 0
            && $pedido->created_at->diffInMinutes(now()) >= $timeoutMin;

        return response()->json([
            'data' => [
                'numero' => $pedido->numero_visible,
                'tipo' => $pedido->tipo,
                'estado' => $estadoPublico,
                'estado_label' => $esFacturado ? __('Entregado') : $pedido->estado_label,
                'por_aceptar' => $porAceptar,
                'demorado' => $demorado,
                'cancelado_motivo' => $pedido->estado_pedido === PedidoDelivery::ESTADO_CANCELADO
                    ? $pedido->motivo_cancelacion
                    : null,
                'hora_pactada_at' => $pedido->hora_pactada_at?->toIso8601String(),
                'lo_antes_posible' => (bool) $pedido->lo_antes_posible,
                'repartidor_en_camino' => $pedido->tipo === PedidoDelivery::TIPO_DELIVERY
                    && $pedido->estado_pedido === PedidoDelivery::ESTADO_EN_CAMINO
                    ? $pedido->repartidor?->nombre
                    : null,
                'total_final' => (float) $pedido->total_final,
                'estado_pago' => $pedido->estado_pago,
                // Renglones pedibles (sin costo de envío ni conceptos): la
                // tienda arma "re-pedir" desde acá y RE-COTIZA (RF-T3).
                'items' => $pedido->detalles
                    ->filter(fn ($d) => $d->articulo_id && ! $d->es_costo_envio && ! $d->es_concepto)
                    ->values()
                    ->map(fn ($d) => [
                        'articulo_id' => (int) $d->articulo_id,
                        'nombre' => $d->articulo?->nombre,
                        'cantidad' => (float) $d->cantidad,
                        'opcionales' => $d->opcionales->map(fn ($op) => [
                            'opcional_id' => (int) $op->opcional_id,
                            'nombre' => $op->nombre_opcional,
                            'cantidad' => (float) $op->cantidad,
                        ])->values()->all(),
                    ])->all(),
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
