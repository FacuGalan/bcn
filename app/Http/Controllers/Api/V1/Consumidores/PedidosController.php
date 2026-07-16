<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Historial de pedidos del consumidor CROSS-comercio (RF-T3, spec
 * tienda-online). Requiere email verificado (decisión RF-T1: la
 * verificación desbloquea el historial).
 *
 * Fan-out controlado: los pedidos viven en la BD tenant de cada comercio
 * (pedidos_delivery.consumidor_id, indexado). Se recorren solo los
 * comercios con tienda (config.tiendas) y se mergea ordenado por fecha.
 * "Re-pedir" lo arma la tienda con GET /pedidos/{token} + re-cotización
 * (los precios son los de hoy, nunca los históricos).
 */
class PedidosController extends Controller
{
    /** Tope de filas consideradas por comercio en el merge (fan-out acotado). */
    protected const CAP_POR_COMERCIO = 500;

    /**
     * GET /v1/consumidores/pedidos?page=&per_page=
     */
    public function index(Request $request, TenantService $tenantService): JsonResponse
    {
        $consumidor = $request->user();

        if (! $consumidor->email_verified_at) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                __('Verificá tu email para ver tu historial de pedidos')
            );
        }

        $datos = $request->validate([
            'page' => 'nullable|integer|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        $pagina = (int) ($datos['page'] ?? 1);
        $porPagina = (int) ($datos['per_page'] ?? 20);

        // Comercios candidatos: los que tienen (o tuvieron) tienda + los que
        // ya materializaron al consumidor como cliente (mapping D11).
        $comercioIds = Tienda::query()->pluck('comercio_id')
            ->merge($consumidor->comercios()->pluck('comercio_id'))
            ->unique()->values();

        $pedidos = collect();
        $total = 0;

        foreach ($comercioIds as $comercioId) {
            try {
                $tenantService->usarComercioParaProceso((int) $comercioId);

                $query = PedidoDelivery::query()->where('consumidor_id', $consumidor->id);

                $cuantos = $query->count();
                if ($cuantos === 0) {
                    continue;
                }
                $total += $cuantos;

                $tiendasPorSucursal = Tienda::where('comercio_id', $comercioId)->get()->keyBy('sucursal_id');
                $sucursales = Sucursal::pluck('nombre', 'id');

                $pedidos = $pedidos->concat(
                    $query->orderByDesc('fecha')->orderByDesc('id')
                        ->limit(min(self::CAP_POR_COMERCIO, $pagina * $porPagina))
                        ->get()
                        ->map(fn (PedidoDelivery $p) => $this->payload($p, $tiendasPorSucursal, $sucursales))
                );
            } catch (\Throwable $e) {
                // Un tenant caído no voltea el historial completo.
                Log::error('Historial de consumidor: comercio inaccesible', [
                    'comercio_id' => $comercioId,
                    'consumidor_id' => $consumidor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $ordenados = $pedidos->sortByDesc('fecha')->values();
        $paginaItems = $ordenados->slice(($pagina - 1) * $porPagina, $porPagina)->values();

        return response()->json([
            'data' => $paginaItems->all(),
            'meta' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $total,
                'has_more' => $total > $pagina * $porPagina,
            ],
        ]);
    }

    /**
     * Resumen de un pedido para la lista (el detalle/re-pedir sale del
     * seguimiento público por token). Estado con la MISMA verdad pública
     * del seguimiento: facturado (jerga interna) = entregado.
     */
    protected function payload(PedidoDelivery $pedido, $tiendasPorSucursal, $sucursales): array
    {
        $esFacturado = $pedido->estado_pedido === PedidoDelivery::ESTADO_FACTURADO;
        $tienda = $tiendasPorSucursal->get($pedido->sucursal_id);

        return [
            'fecha' => $pedido->fecha?->toIso8601String(),
            'numero' => $pedido->numero_visible,
            'tipo' => $pedido->tipo,
            'estado' => $esFacturado ? PedidoDelivery::ESTADO_ENTREGADO : $pedido->estado_pedido,
            'por_aceptar' => $pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR
                && $pedido->origen !== PedidoDelivery::ORIGEN_PANEL,
            'total_final' => (float) $pedido->total_final,
            'token_seguimiento' => $pedido->token_seguimiento,
            'tienda' => [
                'slug' => $tienda?->slug,
                'habilitada' => (bool) ($tienda?->habilitada ?? false),
                'nombre' => $sucursales->get($pedido->sucursal_id),
            ],
        ];
    }
}
