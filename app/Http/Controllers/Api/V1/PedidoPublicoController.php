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

        // RF-T40: fuera de la gracia de verificación no se pide LOGUEADO
        // (la tienda intercepta antes con su propio CTA; esto es el candado
        // real). Comprar como invitado sigue abierto: sin Bearer no pasa acá.
        if ($consumidor?->verificacionVencida()) {
            throw new \App\Exceptions\VerificacionRequeridaException(
                __('Verificá tu email para seguir pidiendo con tu cuenta (o comprá como invitado)')
            );
        }

        $datos = $request->validate([
            'tipo' => 'required|in:delivery,take_away',
            'items' => 'required|array|min:1|max:100',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            // RF-T47: renglón canjeado por puntos (requiere consumidor con
            // cliente y saldo; el service valida y persiste el canje).
            'items.*.canjear_con_puntos' => 'nullable|boolean',
            // Aclaración del cliente por ítem (aditivo 2026-07-22): "sin pepino".
            'items.*.observaciones' => 'nullable|string|max:255',
            'items.*.opcionales' => 'nullable|array',
            'items.*.opcionales.*.opcional_id' => 'required|integer',
            'items.*.opcionales.*.cantidad' => 'nullable|numeric|min:0.001',
            'cliente.nombre' => ($consumidor ? 'nullable' : 'required').'|string|max:150',
            'cliente.telefono' => 'nullable|string|max:30',
            'cliente.email' => 'nullable|email|max:150',
            // RF-T19: cumpleaños OPCIONAL siempre (la tienda lo pide solo si
            // la config lo activa; se persiste en cliente y cuenta global).
            'cliente.fecha_nacimiento' => 'nullable|date|before:today',
            'direccion.direccion' => 'required_if:tipo,delivery|string|max:255',
            'direccion.entre_calles' => 'nullable|string|max:150',
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
            // Pago ONLINE (RF-T77): URL de retorno de la tienda para el
            // back_url de MP (admite el placeholder {token}: la tienda no
            // conoce el token de seguimiento antes del alta).
            'pago.retorno_url' => 'nullable|string|max:500',
            // Propina online (RF-T83): solo con FP online y propina habilitada
            // en la config del checkout (el service valida ambas).
            'propina' => 'nullable|numeric|min:0|max:9999999',
            // Multi-pago (RF-T18): hasta 2 FP con el monto (sin ajuste) que
            // cubre cada una. Si viaja, `pago` singular se ignora.
            'pagos' => 'nullable|array|min:1|max:2',
            'pagos.*.forma_pago_id' => 'required|integer',
            'pagos.*.monto' => 'nullable|numeric|min:0.01',
            'pagos.*.paga_con' => 'nullable|numeric|min:0',
            // Canje de puntos (RF-T9, Fase 3): pago por el máximo canjeable.
            // Solo tiene efecto con Bearer de consumidor con cliente.
            'usar_puntos' => 'nullable|boolean',
        ]);

        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');

        $datos['origen'] = PedidoDelivery::ORIGEN_TIENDA;

        $pedido = $tiendaService->crearPedidoExterno($sucursal, $datos, $tienda, $consumidor);

        $respuesta = [
            'data' => new PedidoDeliveryResource($pedido->fresh(['detalles.opcionales', 'zona', 'repartidor'])),
        ];

        // Pago ONLINE (RF-T77, aditivo): la tienda redirige a url_pago (el
        // init_point de MP). El pedido quedó BORRADOR "esperando pago".
        if ($tx = $tiendaService->transaccionPagoOnline) {
            $respuesta['pago_online'] = [
                'transaccion_id' => $tx->id,
                'url_pago' => $tx->link_pago,
                'expira_en' => $tx->expira_en?->toIso8601String(),
                'estado' => 'pendiente',
            ];
        }

        return response()->json($respuesta, 201);
    }

    /**
     * GET /v1/tiendas/{slug}/pedidos/{token}/pago — estado del pago online
     * (RF-T79). Lo consume la página de retorno del navegador: NUNCA acredita
     * (la acreditación la decide el webhook), solo refleja el estado real.
     */
    public function pagoEstado(Request $request, string $slug, string $token, \App\Services\Pedidos\PedidoPagoOnlineService $pagoOnline): JsonResponse
    {
        $pedido = $this->pedidoPorToken($request, $token);

        return response()->json(['data' => $pagoOnline->estadoPago($pedido)]);
    }

    /**
     * POST /v1/tiendas/{slug}/pedidos/{token}/pago — re-pago (RF-T79): si el
     * pedido sigue "esperando pago" y la tx anterior murió, crea una tx NUEVA
     * sobre el mismo pedido. El token es la credencial (throttled).
     */
    public function pagoReintentar(Request $request, string $slug, string $token, \App\Services\Pedidos\PedidoPagoOnlineService $pagoOnline): JsonResponse
    {
        $datos = $request->validate([
            'retorno_url' => 'nullable|string|max:500',
        ]);

        $pedido = $this->pedidoPorToken($request, $token);
        $sucursal = $request->attributes->get('api_sucursal');
        $tienda = $request->attributes->get('api_tienda');

        $tx = $pagoOnline->reiniciarPago($sucursal, $pedido, $datos['retorno_url'] ?? null, $tienda?->nombre);

        return response()->json([
            'data' => [
                'transaccion_id' => $tx->id,
                'url_pago' => $tx->link_pago,
                'expira_en' => $tx->expira_en?->toIso8601String(),
                'estado' => 'pendiente',
            ],
        ]);
    }

    /**
     * Pedido por token de seguimiento acotado a la sucursal del slug (la
     * misma regla de show/cancelar: 404 genérico, sin enumeración).
     */
    protected function pedidoPorToken(Request $request, string $token): PedidoDelivery
    {
        $pedido = PedidoDelivery::where('token_seguimiento', $token)->first();
        $sucursal = $request->attributes->get('api_sucursal');

        if (! $pedido || (int) $pedido->sucursal_id !== (int) $sucursal->id) {
            abort(404);
        }

        return $pedido;
    }

    /**
     * GET /v1/tiendas/{slug}/pedidos/{token} — seguimiento público (RF-11).
     *
     * Vista recortada: estado, tiempos y repartidor en camino. El token ULID
     * es la credencial (sin enumeración: 404 genérico).
     */
    public function show(Request $request, string $slug, string $token): JsonResponse
    {
        $pedido = PedidoDelivery::with(['repartidor:id,nombre,telefono', 'cliente:id,nombre,telefono', 'detalles.opcionales', 'detalles.articulo:id,nombre'])
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

        // RF-T77: un borrador "esperando pago online" NO está por aceptar (el
        // comercio ni lo ve). La tienda muestra el estado del pago (aditivo).
        $esperandoPago = $pedido->esperandoPagoOnline();

        $porAceptar = $pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR
            && $pedido->origen !== PedidoDelivery::ORIGEN_PANEL
            && ! $esperandoPago;

        // RF-T56 (aditivo): puntos que este pedido suma (o hubiese sumado,
        // si es de invitado) — la tienda arma el CTA "registrate y sumalos"
        // con este número. Solo viaja con programa activo. `vinculado` le
        // dice a la tienda si ya hay una cuenta atada al pedido.
        $puntos = null;
        $puntosTienda = app(\App\Services\Pedidos\PuntosTiendaService::class);
        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_CANCELADO) {
            $pagoReal = $pedido->pagos()
                ->whereIn('estado', [
                    \App\Models\PedidoDeliveryPago::ESTADO_ACTIVO,
                    \App\Models\PedidoDeliveryPago::ESTADO_PLANIFICADO,
                ])
                ->where('es_pago_puntos', false)
                ->get(['forma_pago_id', 'monto_final']);
            // Sin FP declarada (pedido sin pagos) se estima sobre el total —
            // mismo monto que terminará pagando contra entrega.
            $aGanar = $puntosTienda->estimarAGanar(
                $sucursal,
                $pagoReal->first()?->forma_pago_id,
                (float) ($pagoReal->sum('monto_final') ?: $pedido->total_final),
            );
            if ($aGanar > 0) {
                $puntos = [
                    'activo' => true,
                    'a_ganar' => $aGanar,
                    'vinculado' => (bool) $pedido->consumidor_id,
                ];
            }
        }

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
                // RF-T77 (aditivo): true mientras el pago online no acredite.
                'esperando_pago' => $esperandoPago,
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
                // RF-T63 (aditivos): la pantalla de seguimiento se completa
                // desde CUALQUIER dispositivo (el token es la credencial) —
                // antes la dirección y los datos del cliente vivían solo en
                // la sesión del navegador que hizo el pedido.
                'direccion' => $pedido->tipo === PedidoDelivery::TIPO_DELIVERY && $pedido->direccion_entrega
                    ? [
                        'direccion' => $pedido->direccion_entrega,
                        'referencia' => $pedido->direccion_referencia,
                    ]
                    : null,
                'cliente' => ($pedido->nombre_cliente_temporal || $pedido->cliente)
                    ? [
                        'nombre' => $pedido->nombre_cliente_temporal ?: $pedido->cliente?->nombre,
                        'telefono' => $pedido->telefono_cliente_temporal ?: $pedido->cliente?->telefono,
                    ]
                    : null,
                // RF-T63: repartidor desde que está ASIGNADO (no solo en
                // camino), con teléfono. `repartidor_en_camino` queda por
                // compatibilidad (contrato: solo aditivos).
                'repartidor' => $pedido->tipo === PedidoDelivery::TIPO_DELIVERY && $pedido->repartidor
                    ? [
                        'nombre' => $pedido->repartidor->nombre,
                        'telefono' => $pedido->repartidor->telefono,
                    ]
                    : null,
                // RF-T64: palabra clave de entrega — viaja junto con el
                // repartidor asignado (antes no aporta y después de entregado
                // ya no importa; se corta al cancelar).
                'palabra_clave' => $pedido->tipo === PedidoDelivery::TIPO_DELIVERY
                    && $pedido->repartidor_id
                    && $pedido->estado_pedido !== PedidoDelivery::ESTADO_CANCELADO
                    ? $pedido->palabra_clave_entrega
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
                        // Aclaración del cliente (aditivo 2026-07-22): el
                        // seguimiento la muestra y re-pedir la conserva.
                        'observaciones' => $d->observaciones,
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
                // RF-T56 (aditivo): null si el programa no aplica o no hay
                // nada que ganar — la tienda no muestra nada en ese caso.
                'puntos' => $puntos,
            ],
        ]);
    }

    /**
     * POST /v1/tiendas/{slug}/pedidos/{token}/vincular — vinculación
     * retroactiva del pedido de INVITADO a la cuenta del consumidor
     * (RF-T56). Bearer de consumidor obligatorio; el token de seguimiento es
     * la credencial sobre el pedido (misma regla que seguimiento y
     * cancelación). Idempotente.
     */
    public function vincular(Request $request, string $slug, string $token, PedidoTiendaService $tiendaService): JsonResponse
    {
        $consumidor = $request->user('sanctum');
        abort_unless($consumidor instanceof Consumidor, 401);

        $sucursal = $request->attributes->get('api_sucursal');

        $pedido = PedidoDelivery::where('token_seguimiento', $token)->first();
        if (! $pedido || (int) $pedido->sucursal_id !== (int) $sucursal->id) {
            abort(404);
        }

        return response()->json([
            'data' => $tiendaService->vincularConsumidor($pedido, $sucursal, $consumidor),
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
