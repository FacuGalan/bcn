<?php

namespace App\Services\Pedidos;

use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MovimientoCuentaEmpresa;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Sucursal;
use App\Services\CuentaEmpresaService;
use App\Services\IntegracionesPago\CobroIntegracionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pago ONLINE de pedidos de la tienda (Checkout Pro) — RF-T77..T83.
 *
 * Modelo "pedido primero, cobro después" (Opción B): el pedido se crea como
 * BORRADOR "esperando pago" (invisible para el comercio: sin burbuja ni
 * por-aceptar) y la transacción de checkout nace con el pedido como cobrable.
 * El webhook no materializa nada desde cero: solo confirma la tx y este
 * service transiciona el pedido que ya existe.
 *
 * Ciclo:
 *  - iniciarPago: tx checkout (cobrable = pedido) → init_point para la tienda.
 *  - procesarAcreditacion (webhook aprobado): materializa el pago planificado
 *    (sin caja), transiciona el borrador (por-aceptar o confirmado según
 *    config D14) y avisa por el canal público de seguimiento.
 *  - cancelarPorPagoNoCompletado (tx expirada/terminal sin pago): cancela el
 *    borrador — stock y caja nunca se tocaron.
 *  - reiniciarPago (re-pago por token): tx NUEVA sobre el mismo pedido.
 *  - devolver (RF-T82): refund total en MP + tx `devuelto` + contraasientos
 *    en CuentaEmpresa; si falla, el cobro queda "a devolver" (reintento).
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md.
 */
class PedidoPagoOnlineService
{
    /**
     * Timeout mínimo razonable de un checkout: el consumidor navega la página
     * de MP (los 300 s default de la config, pensados para QR presencial, no
     * alcanzan). Configs con timeout presencial se elevan al default online.
     */
    private const TIMEOUT_DEFAULT_ONLINE = 1800;

    public function __construct(
        protected CobroIntegracionService $cobroService,
        protected PedidoDeliveryService $pedidoService,
        protected DeliveryEnvioService $envioService,
    ) {}

    /**
     * Crea la transacción de checkout para un pedido recién creado (RF-T77).
     * `$monto` = lo que cubre la FP online (total del pedido neto de puntos)
     * SIN propina; la tx es por monto + propina y su metadata los discrimina.
     */
    public function iniciarPago(
        Sucursal $sucursal,
        PedidoDelivery $pedido,
        FormaPago $formaPago,
        IntegracionPagoSucursal $config,
        float $monto,
        float $propina = 0.0,
        ?string $retornoUrl = null,
        ?string $nombreTienda = null,
    ): IntegracionPagoTransaccion {
        $timeout = (int) ($config->timeout_segundos ?: 0);
        if ($timeout <= 300) {
            $timeout = self::TIMEOUT_DEFAULT_ONLINE;
        }

        return $this->cobroService->iniciarCobro($config, [
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $sucursal->id,
            'caja_id' => null,
            'usuario_iniciador_id' => null, // iniciado por el consumidor
            'modo_usado' => IntegracionPagoTransaccion::MODO_CHECKOUT_PRO,
            'monto' => round($monto + $propina, 2),
            'timeout_segundos' => $timeout,
            'metadata' => [
                'checkout' => array_filter([
                    'titulo' => $this->tituloCheckout($pedido, $nombreTienda),
                    'total_pedido' => round($monto, 2),
                    'propina' => $propina > 0 ? round($propina, 2) : null,
                    'back_url' => $this->resolverBackUrl($retornoUrl, $pedido),
                    'statement' => $this->nombreComercio(),
                    'cuotas_max' => $this->cuotasMax($formaPago),
                    'pedido_id' => $pedido->id,
                ], fn ($v) => $v !== null && $v !== ''),
            ],
        ], $pedido);
    }

    /**
     * Acreditación del pago (RF-T78) — la llama el webhook DESPUÉS de
     * confirmar la tx. Idempotente: reprocesar una tx ya aplicada no duplica.
     *
     *  1. Materializa el pago planificado → activo (sin caja, sin operador) y
     *     lo vincula a la tx. El movimiento de CuentaEmpresa lo registró
     *     confirmarCobro (D6/D7) — cobro y propina discriminados.
     *  2. Transiciona el borrador según la config del comercio (D14):
     *     manual → "por aceptar" (AHORA sí burbuja/chime); automática →
     *     confirmado (+ comanda si corresponde).
     *  3. Broadcast por el canal PÚBLICO de seguimiento.
     *
     * Pago que llega sobre un pedido YA cancelado (webhook tardío tras la
     * expiración): la plata no corresponde — devolución automática.
     */
    public function procesarAcreditacion(IntegracionPagoTransaccion $transaccion): void
    {
        $pedido = $transaccion->cobrable;
        if (! $pedido instanceof PedidoDelivery) {
            return;
        }

        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_CANCELADO) {
            Log::warning('Pago online acreditado sobre un pedido ya cancelado: se devuelve automáticamente', [
                'pedido_id' => $pedido->id,
                'transaccion_id' => $transaccion->id,
            ]);
            $this->devolver($transaccion);

            return;
        }

        $pago = $pedido->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)
            ->where('forma_pago_id', $transaccion->forma_pago_id)
            ->orderBy('id')
            ->first();

        if ($pago) {
            $this->pedidoService->materializarPagoOnline($pago, $transaccion);
        }

        $pedido->refresh();

        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR) {
            $sucursal = Sucursal::findOrFail((int) $pedido->sucursal_id);
            $config = $this->envioService->configDelivery($sucursal);

            if (($config['aceptacion_pedidos_externos'] ?? 'manual') === 'automatica') {
                $this->pedidoService->aceptarPedidoExterno($pedido);
            } else {
                // Recién ahora el pedido existe para el comercio (RF-T80).
                $this->pedidoService->avisarPedidoPorAceptar($pedido);
            }
        }

        $this->broadcastSeguimientoPago($pedido->fresh(), 'aprobado');
    }

    /**
     * La tx de checkout murió sin pago (expirada / cancelada / fallida):
     * el borrador "esperando pago" se cancela solo (RF-T79). Stock y caja
     * nunca se tocaron. Si hay OTRA tx pendiente más nueva (re-pago en
     * vuelo), el pedido sigue esperando y no se cancela.
     */
    public function cancelarPorPagoNoCompletado(IntegracionPagoTransaccion $transaccion): void
    {
        $pedido = $transaccion->cobrable;
        if (! $pedido instanceof PedidoDelivery) {
            return;
        }

        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR
            || $pedido->origen === PedidoDelivery::ORIGEN_PANEL) {
            return;
        }

        // Alguna OTRA tx del pedido ya cobró o sigue esperando (re-pago en
        // vuelo): el pedido no se cancela.
        $hayViva = $pedido->transaccionesIntegracion()
            ->where('id', '<>', $transaccion->id)
            ->whereIn('estado', [
                IntegracionPagoTransaccion::ESTADO_PENDIENTE,
                IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
                IntegracionPagoTransaccion::ESTADO_CONFIRMADO_MANUAL,
            ])
            ->exists();

        if ($hayViva) {
            return;
        }

        $this->pedidoService->cancelarPedido($pedido, __('Pago online no completado'));

        Log::info('Pedido borrador cancelado por pago online no completado (RF-T79)', [
            'pedido_id' => $pedido->id,
            'transaccion_id' => $transaccion->id,
        ]);
    }

    /**
     * Re-pago (RF-T79): el pedido sigue "esperando pago" y la tx anterior
     * murió (o el consumidor quiere reintentar) → tx NUEVA sobre el MISMO
     * pedido. Evita que un fallo de MP le haga perder el pedido armado.
     */
    public function reiniciarPago(Sucursal $sucursal, PedidoDelivery $pedido, ?string $retornoUrl = null, ?string $nombreTienda = null): IntegracionPagoTransaccion
    {
        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR) {
            throw new \Exception(__('El pedido ya no está esperando el pago'));
        }

        if ($pedido->transaccionesIntegracion()->confirmadas()->exists()) {
            throw new \Exception(__('El pago ya se acreditó'));
        }

        [$formaPago, $config, $monto] = $this->resolverPagoOnlinePlanificado($sucursal, $pedido);

        // Cancelar la(s) tx pendiente(s) previas: dos links de pago vivos del
        // mismo pedido serían un doble cobro esperando a suceder.
        $pedido->transaccionesIntegracion()
            ->pendientes()
            ->where('modo_usado', IntegracionPagoTransaccion::MODO_CHECKOUT_PRO)
            ->get()
            ->each(fn ($tx) => $this->cobroService->cancelarCobro($tx));

        return $this->iniciarPago(
            $sucursal,
            $pedido,
            $formaPago,
            $config,
            $monto,
            (float) $pedido->propina_online,
            $retornoUrl,
            $nombreTienda,
        );
    }

    /**
     * Estado del pago online para la tienda (RF-T79): lo consume el retorno
     * del navegador y el seguimiento. NUNCA acredita (el webhook manda);
     * con la tx pendiente re-consulta el estado vivo a MP para que el
     * retorno no muestre "pendiente" si el pago ya está aprobado.
     *
     * @return array{estado: string, url_pago: ?string, expira_en: ?string}
     */
    public function estadoPago(PedidoDelivery $pedido): array
    {
        $tx = $pedido->transaccionesIntegracion()
            ->where('modo_usado', IntegracionPagoTransaccion::MODO_CHECKOUT_PRO)
            ->latest('id')
            ->first();

        if (! $tx) {
            return ['estado' => 'sin_pago', 'url_pago' => null, 'expira_en' => null];
        }

        if ($tx->estaConfirmada()) {
            return ['estado' => 'aprobado', 'url_pago' => null, 'expira_en' => null];
        }

        if ($tx->estado === IntegracionPagoTransaccion::ESTADO_DEVUELTO) {
            return ['estado' => 'devuelto', 'url_pago' => null, 'expira_en' => null];
        }

        if (! $tx->estaPendiente() || $tx->estaVencida()) {
            return ['estado' => 'fallido', 'url_pago' => null, 'expira_en' => null];
        }

        // Pendiente vigente: estado VIVO de MP (el webhook puede venir en camino).
        $estadoVivo = 'pendiente';
        try {
            $estadoVivo = $this->cobroService->consultarEstado($tx);
        } catch (\Throwable $e) {
            Log::warning('No se pudo consultar el estado vivo del checkout', [
                'transaccion_id' => $tx->id,
                'error' => $e->getMessage(),
            ]);
        }

        return match ($estadoVivo) {
            'aprobado' => ['estado' => 'aprobado', 'url_pago' => null, 'expira_en' => null],
            'fallido', 'cancelado' => [
                'estado' => 'fallido',
                'url_pago' => $tx->link_pago,
                'expira_en' => $tx->expira_en?->toIso8601String(),
            ],
            default => [
                'estado' => 'pendiente',
                'url_pago' => $tx->link_pago,
                'expira_en' => $tx->expira_en?->toIso8601String(),
            ],
        };
    }

    /**
     * Devolución del cobro acreditado (RF-T82): refund TOTAL en MP (incluye
     * la propina — mismo pago). Refund OK ⇒ tx `devuelto` + evento +
     * contraasientos en la CuentaEmpresa (patrón ledger: el ingreso original
     * no se toca) + aviso al consumidor. Refund FALLA ⇒ evento
     * `devolucion_fallida` y devuelve false — el cobro queda "a devolver"
     * (tx confirmada + pedido cancelado) con reintento manual.
     *
     * Idempotente: tx ya devuelta ⇒ true sin tocar nada.
     */
    public function devolver(IntegracionPagoTransaccion $transaccion, ?int $usuarioId = null): bool
    {
        if ($transaccion->estado === IntegracionPagoTransaccion::ESTADO_DEVUELTO) {
            return true;
        }

        if (! $transaccion->estaConfirmada() || ! $transaccion->esCheckoutOnline()) {
            throw new \Exception(__('Solo se puede devolver un cobro de checkout online acreditado'));
        }

        $config = $transaccion->integracionSucursal;

        try {
            $refund = $config->integracion->getGatewayInstance()->reembolsar($config, $transaccion);
        } catch (\Throwable $e) {
            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_DEVOLUCION_FALLIDA, null, [
                'motivo' => $e->getMessage(),
                'usuario_id' => $usuarioId,
            ]);

            Log::warning('Refund de checkout falló: el cobro queda a devolver (RF-T82)', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($transaccion, $refund, $usuarioId) {
            $transaccion->estado = IntegracionPagoTransaccion::ESTADO_DEVUELTO;
            $transaccion->save();

            $this->registrarEvento($transaccion, IntegracionPagoEvento::EVENTO_DEVUELTO, $refund, array_filter([
                'usuario_id' => $usuarioId,
            ]));
        });

        $this->registrarContraasientos($transaccion, $usuarioId);

        $pedido = $transaccion->cobrable;
        if ($pedido instanceof PedidoDelivery) {
            $this->broadcastSeguimientoPago($pedido, 'devuelto');
        }

        return true;
    }

    // ==================== Helpers internos ====================

    /**
     * FP online del pedido: el pago PLANIFICADO cuya FP tiene integración de
     * checkout activa en la sucursal. Devuelve [FormaPago, config, monto].
     *
     * @return array{0: FormaPago, 1: IntegracionPagoSucursal, 2: float}
     */
    protected function resolverPagoOnlinePlanificado(Sucursal $sucursal, PedidoDelivery $pedido): array
    {
        foreach ($pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)->get() as $pago) {
            $formaPago = FormaPago::find($pago->forma_pago_id);
            $config = $formaPago?->integracionCheckout((int) $sucursal->id);

            if ($config) {
                return [$formaPago, $config, (float) $pago->monto_final];
            }
        }

        throw new \Exception(__('El pedido no tiene un pago online pendiente'));
    }

    /**
     * Contraasientos del refund (RF-T82/T83): egresos espejo de los ingresos
     * del cobro (cobro + propina discriminados), concepto
     * `devolucion_integracion` (mismo catálogo de la conciliación). Solo
     * producción e idempotente por origen; nunca rompe la devolución.
     */
    protected function registrarContraasientos(IntegracionPagoTransaccion $transaccion, ?int $usuarioId): void
    {
        try {
            $config = $transaccion->integracionSucursal;
            if (! $config || ! $config->esProduccion()) {
                return;
            }

            $yaContraasentado = MovimientoCuentaEmpresa::where('origen_tipo', 'IntegracionPagoTransaccion')
                ->where('origen_id', $transaccion->id)
                ->where('tipo', 'egreso')
                ->exists();
            if ($yaContraasentado) {
                return;
            }

            $cuenta = CuentaEmpresaService::findOrCreateParaIntegracion($config);
            if (! $cuenta && $transaccion->formaPago?->cuenta_empresa_id) {
                $cuenta = CuentaEmpresa::find($transaccion->formaPago->cuenta_empresa_id);
            }
            if (! $cuenta) {
                return;
            }

            $propina = round((float) ($transaccion->metadata['checkout']['propina'] ?? 0), 2);
            $montoCobro = round((float) $transaccion->monto - $propina, 2);
            $usuario = (int) ($usuarioId ?? 0);

            CuentaEmpresaService::registrarMovimientoAutomatico(
                $cuenta,
                'egreso',
                $montoCobro,
                'devolucion_integracion',
                'IntegracionPagoTransaccion',
                $transaccion->id,
                "Devolución cobro online #{$transaccion->id} (pedido rechazado/cancelado)",
                $usuario,
                $transaccion->sucursal_id,
            );

            if ($propina > 0) {
                CuentaEmpresaService::registrarMovimientoAutomatico(
                    $cuenta,
                    'egreso',
                    $propina,
                    'devolucion_integracion',
                    'IntegracionPagoTransaccion',
                    $transaccion->id,
                    "Devolución propina online #{$transaccion->id}",
                    $usuario,
                    $transaccion->sucursal_id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron registrar los contraasientos del refund', [
                'transaccion_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function tituloCheckout(PedidoDelivery $pedido, ?string $nombreTienda): string
    {
        // El borrador no tiene número todavía: la referencia corta del token
        // identifica el pedido ante el pagador sin esperar la aceptación.
        $ref = strtoupper(substr((string) $pedido->token_seguimiento, -8));

        return trim(__('Pedido :ref', ['ref' => $ref]).($nombreTienda ? ' - '.$nombreTienda : ''));
    }

    /**
     * back_url de la tienda: soporta el placeholder `{token}` (la tienda no
     * conoce el token de seguimiento antes del alta).
     */
    protected function resolverBackUrl(?string $retornoUrl, PedidoDelivery $pedido): ?string
    {
        if (empty($retornoUrl)) {
            return null;
        }

        return str_replace('{token}', (string) $pedido->token_seguimiento, $retornoUrl);
    }

    protected function nombreComercio(): ?string
    {
        try {
            return app(\App\Services\TenantService::class)->getComercio()?->nombre;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cuotasMax(FormaPago $formaPago): ?int
    {
        $integracion = $formaPago->integraciones()
            ->where('codigo', IntegracionPago::CODIGO_MERCADOPAGO_CHECKOUT)
            ->first();

        $config = json_decode((string) ($integracion?->pivot->config_checkout ?? ''), true);
        $cuotas = (int) data_get($config, 'cuotas_max', 0);

        return $cuotas > 0 ? $cuotas : null;
    }

    /**
     * Aviso de pago por el canal PÚBLICO de seguimiento (la tienda ya lo
     * escucha): mismo evento del circuito con el bloque `pago_online` aditivo.
     */
    protected function broadcastSeguimientoPago(PedidoDelivery $pedido, string $estadoPago): void
    {
        if (! $pedido->token_seguimiento) {
            return;
        }

        try {
            $esFacturado = $pedido->estado_pedido === PedidoDelivery::ESTADO_FACTURADO;

            broadcast(new \App\Events\Broadcasting\PedidoSeguimientoPublicoBroadcast(
                token: (string) $pedido->token_seguimiento,
                estado: $esFacturado ? PedidoDelivery::ESTADO_ENTREGADO : $pedido->estado_pedido,
                estadoLabel: $esFacturado ? __('Entregado') : $pedido->estado_label,
                pagoOnline: ['estado' => $estadoPago],
            ));
        } catch (\Throwable $e) {
            Log::warning('No se pudo broadcastear el estado del pago online', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function registrarEvento(
        IntegracionPagoTransaccion $transaccion,
        string $evento,
        ?array $payloadExterno = null,
        ?array $metadata = null,
    ): void {
        IntegracionPagoEvento::create([
            'transaccion_id' => $transaccion->id,
            'integracion_pago_sucursal_id' => $transaccion->integracion_pago_sucursal_id,
            'evento' => $evento,
            'payload_externo' => $payloadExterno,
            'metadata' => $metadata ?: null,
        ]);
    }
}
