<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Pedidos\CotizadorCarritoTienda;
use App\Services\Pedidos\DeliveryEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cotizaciones públicas de la tienda (RF-06/D12).
 */
class CotizacionController extends Controller
{
    /**
     * POST /v1/tiendas/{slug}/envios/cotizar — {latitud, longitud} → costo/alcance.
     * `hora_pactada` (opcional, datetime): evalúa las franjas de costo de la
     * zona para ese momento (más caro de noche, etc.); default ahora.
     */
    public function envio(Request $request, DeliveryEnvioService $envioService): JsonResponse
    {
        $datos = $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'hora_pactada' => 'nullable|date',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $cotizacion = $envioService->cotizar(
            $sucursal,
            (float) $datos['latitud'],
            (float) $datos['longitud'],
            cuando: isset($datos['hora_pactada']) ? \Illuminate\Support\Carbon::parse($datos['hora_pactada']) : null,
        );

        return response()->json([
            'data' => [
                'alcance' => $cotizacion->alcance,
                'pedible' => $cotizacion->esOk(),
                'costo_envio' => $cotizacion->costo !== null ? (float) $cotizacion->costo : null,
                'distancia_km' => $cotizacion->distanciaKm !== null ? (float) $cotizacion->distanciaKm : null,
                'zona' => $cotizacion->zona?->nombre,
                'demora_estimada_min' => $cotizacion->demoraEstimadaMin,
            ],
        ]);
    }

    /**
     * POST /v1/tiendas/{slug}/carrito/cotizar — cotización server-side del
     * carrito completo (D12): el contrato que la tienda muestra en el checkout.
     */
    public function carrito(Request $request, CotizadorCarritoTienda $cotizador): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => 'required|in:delivery,take_away',
            'items' => 'required|array|min:1|max:100',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'items.*.opcionales' => 'nullable|array',
            'items.*.opcionales.*.opcional_id' => 'required|integer',
            'items.*.opcionales.*.cantidad' => 'nullable|numeric|min:0.001',
            'cupon_codigo' => 'nullable|string|max:50',
            'forma_pago_id' => 'nullable|integer',
            // Multi-pago (RF-T18): hasta 2 FP con el monto que cubre cada una
            // (SIN su ajuste; los ajustes se devuelven calculados). Si viaja,
            // `forma_pago_id` singular se ignora. `costo_envio` es la
            // cotización de envío que la tienda ya obtuvo de /envios/cotizar
            // (para desglosar el total completo; el envío queda fuera de la
            // base de los ajustes, D17).
            'pagos' => 'nullable|array|min:1|max:2',
            'pagos.*.forma_pago_id' => 'required|integer',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'costo_envio' => 'nullable|numeric|min:0',
            'usar_puntos' => 'nullable|boolean',
            // Encargo (RF-T16): validar acá para que el checkout falle
            // TEMPRANO (slot vencido o artículo no apto) y no en el alta.
            'entrega.programado_para' => 'nullable|date',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');

        if (! empty($datos['entrega']['programado_para'])) {
            try {
                app(DeliveryEnvioService::class)->validarProgramado(
                    $sucursal,
                    \Illuminate\Support\Carbon::parse($datos['entrega']['programado_para']),
                    collect($datos['items'])->pluck('articulo_id')->map(fn ($id) => (int) $id)->all(),
                );
            } catch (\Exception $e) {
                abort(response()->json(['error' => [
                    'code' => 'encargo_invalido',
                    'message' => $e->getMessage(),
                    'details' => null,
                ]], 422));
            }
        }

        // Consumidor logueado con cliente materializado (D11): la cotización
        // del checkout usa SU cliente (precios especiales/promos por cliente)
        // — el mismo que usará el POST del pedido, para que checkout y pedido
        // muestren el MISMO total. Solo mapping existente: cotizar nunca crea
        // clientes.
        $clienteId = null;
        $consumidor = $request->user('sanctum');
        if ($consumidor instanceof \App\Models\Consumidor) {
            // El comercio sale de la TIENDA (config) — la sucursal es tenant
            // y no conoce su comercio (fix 2026-07-17: con ->comercio_id de
            // la sucursal el mapping nunca resolvía y el consumidor cotizaba
            // sin su cliente, es decir sin sus precios).
            $tienda = $request->attributes->get('api_tienda');
            $clienteId = $consumidor->clienteIdEn((int) $tienda->comercio_id);
            if ($clienteId && ! \App\Models\Cliente::find($clienteId)) {
                $clienteId = null;
            }
        }

        // Multi-pago (RF-T18): la PRIMERA FP es la principal — participa del
        // precio (promos/listas condicionadas por FP + restricción de cupón)
        // igual que la FP única; la segunda solo aporta su ajuste sobre su
        // porción. Incompatible con canje de puntos en v1.
        $pagosInput = isset($datos['pagos']) ? array_values($datos['pagos']) : null;
        $formaPagoId = isset($datos['forma_pago_id']) ? (int) $datos['forma_pago_id'] : null;
        if ($pagosInput) {
            if (! empty($datos['usar_puntos'])) {
                abort(response()->json(['error' => [
                    'code' => 'pagos_invalidos',
                    'message' => __('El canje de puntos no se puede combinar con dos formas de pago'),
                    'details' => null,
                ]], 422));
            }
            $formaPagoId = (int) $pagosInput[0]['forma_pago_id'];
        }

        $resultado = $cotizador->cotizar(
            $sucursal,
            $datos['tipo'],
            $datos['items'],
            $datos['cupon_codigo'] ?? null,
            $clienteId,
            $formaPagoId,
        );

        $totalAPagar = (float) ($resultado['total_a_pagar'] ?? ($resultado['total_final'] ?? 0));

        // Desglose multi-pago: cada FP con su ajuste sobre SU porción (misma
        // regla del panel). total_a_pagar pasa a ser la suma de los
        // monto_final e INCLUYE el costo_envio informado.
        $pagosDesglose = null;
        if ($pagosInput) {
            $costoEnvio = round((float) ($datos['costo_envio'] ?? 0), 2);
            $totalBienes = (float) ($resultado['total_final'] ?? 0);

            try {
                $pagosDesglose = $cotizador->desglosarPagos(
                    $sucursal,
                    $pagosInput,
                    round($totalBienes + $costoEnvio, 2),
                    $costoEnvio,
                );
            } catch (\Exception $e) {
                abort(response()->json(['error' => [
                    'code' => 'pagos_invalidos',
                    'message' => $e->getMessage(),
                    'details' => null,
                ]], 422));
            }

            $ajusteCombinado = round(array_sum(array_column($pagosDesglose, 'monto_ajuste')), 2);
            $resultado['desglose_iva'] = $cotizador->desgloseIvaConAjuste($ajusteCombinado);
            $resultado['forma_pago'] = null;
            $totalAPagar = round(array_sum(array_column($pagosDesglose, 'monto_final')), 2);
        }

        // Puntos (RF-T9, Fase 3): el canje es un PAGO por el máximo (toggle)
        // — no toca precios ni total_final; resta del total_a_pagar. El
        // bloque viaja siempre que el programa esté activo para el cliente
        // (con `a_ganar`, aunque no canjee).
        $puntos = null;
        if ($clienteId && ! $pagosInput) {
            $puntosTienda = app(\App\Services\Pedidos\PuntosTiendaService::class);
            $info = $puntosTienda->info($sucursal, $clienteId);
            if ($info['activo']) {
                $canje = ! empty($datos['usar_puntos'])
                    ? $puntosTienda->calcularCanjeMaximo($info, $totalAPagar)
                    : null;
                $puntos = $puntosTienda->bloqueContrato($info, $canje, $sucursal, $formaPagoId, $totalAPagar);
                $totalAPagar = round($totalAPagar - $puntos['monto'], 2);
            }
        }

        return response()->json([
            'data' => [
                'items' => $resultado['items'] ?? [],
                'subtotal' => (float) ($resultado['subtotal'] ?? 0),
                'iva' => (float) ($resultado['iva_total'] ?? 0),
                'descuento' => (float) ($resultado['descuento_total'] ?? 0),
                'total_final' => (float) ($resultado['total_final'] ?? 0),
                'promociones_aplicadas' => $resultado['promociones_comunes_aplicadas'] ?? [],
                'promociones_especiales_aplicadas' => $resultado['promociones_especiales_aplicadas'] ?? [],
                'cupon' => $resultado['cupon'],
                // FP declarada (opcional): descuento/recargo con los MISMOS
                // cálculos del panel. total_a_pagar = total_final + ajuste
                // (sin envío, que va aparte) − canje de puntos si lo hay.
                'forma_pago' => $resultado['forma_pago'] ?? null,
                // Multi-pago (RF-T18): desglose por FP con el ajuste de cada
                // una sobre su porción; null si se cotizó con una sola FP.
                'pagos' => $pagosDesglose ? array_map(fn ($p) => [
                    'forma_pago_id' => $p['forma_pago_id'],
                    'nombre' => $p['nombre'],
                    'monto_base' => $p['monto_base'],
                    'ajuste_porcentaje' => $p['ajuste_porcentaje'],
                    'monto_ajuste' => $p['monto_ajuste'],
                    'monto_final' => $p['monto_final'],
                    'permite_vuelto' => $p['permite_vuelto'],
                ], $pagosDesglose) : null,
                'puntos' => $puntos,
                'total_a_pagar' => $totalAPagar,
                'desglose_iva' => $resultado['desglose_iva'] ?? null,
                'nota' => __('El costo de envío se cotiza aparte y se suma al confirmar el pedido'),
            ],
        ]);
    }
}
