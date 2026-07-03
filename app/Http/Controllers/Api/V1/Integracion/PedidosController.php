<?php

namespace App\Http\Controllers\Api\V1\Integracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\PedidoDeliveryResource;
use App\Models\PedidoDelivery;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\Pedidos\PedidoTiendaService;
use App\Services\Pedidos\RepartidorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de integración de pedidos delivery (RF-11) — token Sanctum de
 * COMERCIO con abilities (`pedidos:read` / `pedidos:write`). Sucursal por
 * header X-Sucursal-Id (api.tenant deja api_sucursal en el request).
 *
 * Mismos services y validaciones que el panel (D2): la API no reimplementa
 * reglas de negocio.
 */
class PedidosController extends Controller
{
    /**
     * GET /v1/pedidos-delivery — listado paginado con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $sucursal = $request->attributes->get('api_sucursal');

        $query = PedidoDelivery::with(['detalles.opcionales', 'detalles.articulo:id,nombre', 'zona:id,nombre', 'repartidor:id,nombre'])
            ->where('sucursal_id', $sucursal->id);

        if ($request->filled('estado')) {
            $query->where('estado_pedido', $request->query('estado'));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->query('tipo'));
        }
        if ($request->filled('origen')) {
            $query->where('origen', $request->query('origen'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->query('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->query('hasta'));
        }

        $pedidos = $query->orderByDesc('id')->paginate(min(100, (int) $request->query('per_page', 25)));

        return response()->json([
            'data' => PedidoDeliveryResource::collection($pedidos->items()),
            'meta' => [
                'current_page' => $pedidos->currentPage(),
                'last_page' => $pedidos->lastPage(),
                'per_page' => $pedidos->perPage(),
                'total' => $pedidos->total(),
            ],
        ]);
    }

    /**
     * GET /v1/pedidos-delivery/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'data' => new PedidoDeliveryResource($this->pedidoDeSucursal($request, $id, [
                'detalles.opcionales', 'detalles.articulo:id,nombre', 'zona:id,nombre', 'repartidor:id,nombre',
            ])),
        ]);
    }

    /**
     * POST /v1/pedidos-delivery — alta (mismo payload que el endpoint público
     * de tienda; origen 'api' + origen_referencia del integrador). Respeta la
     * aceptación configurada (D14).
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
            'cliente.nombre' => 'required|string|max:150',
            'cliente.telefono' => 'nullable|string|max:30',
            'cliente.email' => 'nullable|email|max:150',
            'direccion.direccion' => 'required_if:tipo,delivery|string|max:255',
            'direccion.referencia' => 'nullable|string|max:255',
            'direccion.latitud' => 'nullable|numeric|between:-90,90',
            'direccion.longitud' => 'nullable|numeric|between:-180,180',
            'direccion.localidad_id' => 'nullable|integer',
            'cupon_codigo' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string|max:1000',
            'origen_referencia' => 'nullable|string|max:100',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $datos['origen'] = PedidoDelivery::ORIGEN_API;

        $pedido = $tiendaService->crearPedidoExterno($sucursal, $datos);

        return response()->json([
            'data' => new PedidoDeliveryResource($pedido->fresh(['detalles.opcionales', 'zona', 'repartidor'])),
        ], 201);
    }

    /**
     * PATCH /v1/pedidos-delivery/{id} — modificaciones operativas puntuales:
     * cambio de estado, asignación de repartidor y observaciones. La edición
     * completa del carrito se hace por el panel.
     */
    public function update(Request $request, int $id, PedidoDeliveryService $pedidoService, RepartidorService $repartidorService): JsonResponse
    {
        $datos = $request->validate([
            'estado' => 'nullable|string|in:confirmado,en_preparacion,listo,en_camino,entregado',
            'repartidor_id' => 'nullable|integer',
            'observaciones' => 'nullable|string|max:1000',
            'observacion_estado' => 'nullable|string|max:255',
        ]);

        $pedido = $this->pedidoDeSucursal($request, $id);

        if (array_key_exists('repartidor_id', $datos)) {
            $pedido = $pedidoService->asignarRepartidor($pedido, $datos['repartidor_id'] !== null ? (int) $datos['repartidor_id'] : null);
        }

        if (! empty($datos['estado'])) {
            // El despacho SIEMPRE pasa por la salida (RF-08): en_camino con
            // repartidor usa la salida implícita; el resto, transición normal.
            if ($datos['estado'] === PedidoDelivery::ESTADO_EN_CAMINO && $pedido->repartidor_id) {
                $repartidorService->despacharPedido($pedido);
            } else {
                $pedidoService->cambiarEstado($pedido, $datos['estado'], $datos['observacion_estado'] ?? null);
            }
        }

        if (array_key_exists('observaciones', $datos) && $datos['observaciones'] !== null) {
            $pedido->update(['observaciones' => $datos['observaciones']]);
        }

        return response()->json([
            'data' => new PedidoDeliveryResource($pedido->fresh(['detalles.opcionales', 'zona', 'repartidor'])),
        ]);
    }

    protected function pedidoDeSucursal(Request $request, int $id, array $with = []): PedidoDelivery
    {
        $sucursal = $request->attributes->get('api_sucursal');

        return PedidoDelivery::with($with)
            ->where('sucursal_id', $sucursal->id)
            ->findOrFail($id);
    }
}
