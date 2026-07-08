<?php

namespace App\Services\Pedidos;

use App\Models\Caja;
use App\Models\DeliverySalida;
use App\Models\DeliverySalidaPedido;
use App\Models\MovimientoCaja;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Repartidor;
use App\Models\RepartidorFondo;
use App\Models\RepartidorFondoMovimiento;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RepartidorService (Fase 3 pedidos-delivery)
 *
 * Salidas/vueltas de reparto (RF-08) y fondo del repartidor (RF-09, D4/D13).
 *
 * Reglas de oro:
 * - El circuito de vuelta, cobros y fondo SIEMPRE opera sobre salidas: el
 *   pase manual listo → en_camino con repartidor usa despacharPedido()
 *   (salida implícita de 1 pedido). PedidoDeliveryService::cambiarEstado NO
 *   crea salidas.
 * - D13: el efectivo cobrado contra entrega vive en el FONDO del repartidor
 *   (pago confirmado con destino_fondo, sin MovimientoCaja); la caja recibe
 *   UN ingreso neto recién en la rendición.
 * - La vuelta registra los cobros ANTES de marcar entregado (el guard de
 *   conversión exige pagos suficientes) y la conversión automática a venta
 *   corre POST-vuelta, individual y FUERA de la transacción (una falla de
 *   ARCA no deja la vuelta a medias).
 * - El fondo es de CICLO LARGO: queda abierto entre salidas y se rinde
 *   cuando se decide cerrarlo. Movimientos append-only, saldo teórico
 *   calculado, a lo sumo UN fondo abierto por repartidor+sucursal.
 */
class RepartidorService
{
    public function __construct(
        protected PedidoDeliveryService $pedidoService,
    ) {}

    // ==================== SALIDAS (RF-08) ====================

    /**
     * Crea una salida en estado `armando` agrupando pedidos despachables
     * (confirmado/en_preparacion/listo — "listo" no es paso obligado) de un
     * repartidor. Los pedidos quedan apuntando a la salida ACTUAL
     * (pedidos_delivery.salida_id) y el repartidor de la salida pisa la
     * asignación previa del pedido (reasignación libre hasta `listo`).
     */
    public function crearSalida(int $sucursalId, int $repartidorId, array $pedidoIds, ?int $usuarioId = null, ?string $observaciones = null): DeliverySalida
    {
        if (empty($pedidoIds)) {
            throw new Exception('Una salida necesita al menos un pedido');
        }

        $repartidor = Repartidor::findOrFail($repartidorId);
        $this->validarRepartidorOperativo($repartidor, $sucursalId);

        return DB::connection('pymes_tenant')->transaction(function () use ($sucursalId, $repartidor, $pedidoIds, $usuarioId, $observaciones) {
            $salida = DeliverySalida::create([
                'sucursal_id' => $sucursalId,
                'repartidor_id' => $repartidor->id,
                'estado' => DeliverySalida::ESTADO_ARMANDO,
                'usuario_id' => $usuarioId ?: ((int) auth()->id() ?: 0),
                'observaciones' => $observaciones,
            ]);

            $this->attachPedidosASalida($salida, $pedidoIds);

            Log::info('Salida de reparto creada', [
                'salida_id' => $salida->id,
                'repartidor_id' => $repartidor->id,
                'pedidos' => $pedidoIds,
            ]);

            return $salida->fresh();
        });
    }

    /**
     * Suma pedidos a una salida que todavía está `armando` (el repartidor no
     * partió).
     */
    public function agregarPedidosASalida(DeliverySalida $salida, array $pedidoIds): DeliverySalida
    {
        if (! $salida->estaArmando()) {
            throw new Exception('Solo se pueden agregar pedidos mientras la salida se está armando');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($salida, $pedidoIds) {
            $this->attachPedidosASalida($salida, $pedidoIds);

            return $salida->fresh();
        });
    }

    /**
     * Quita un pedido de una salida `armando`. Como la salida nunca se
     * registró, acá SÍ se borra la fila del pivot (el historial append-only
     * aplica a salidas registradas: re-despachos y resultados).
     */
    public function quitarPedidoDeSalida(DeliverySalida $salida, PedidoDelivery $pedido): DeliverySalida
    {
        if (! $salida->estaArmando()) {
            throw new Exception('Solo se pueden quitar pedidos mientras la salida se está armando');
        }

        if ((int) $pedido->salida_id !== (int) $salida->id) {
            throw new Exception('El pedido no pertenece a esta salida');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($salida, $pedido) {
            $salida->salidaPedidos()->where('pedido_id', $pedido->id)->delete();
            $pedido->update(['salida_id' => null]);

            return $salida->fresh();
        });
    }

    /**
     * Registra la partida: todos los pedidos de la salida pasan
     * listo → en_camino (timestamps + eventos vía cambiarEstado) y la salida
     * queda `en_camino` con salida_at.
     */
    public function registrarSalida(DeliverySalida $salida, ?int $usuarioId = null): DeliverySalida
    {
        if (! $salida->estaArmando()) {
            throw new Exception("La salida ya fue registrada (estado '{$salida->estado}')");
        }

        $pedidos = $salida->pedidosActuales()->get();

        if ($pedidos->isEmpty()) {
            throw new Exception('La salida no tiene pedidos');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($salida, $pedidos) {
            foreach ($pedidos as $pedido) {
                if (! in_array($pedido->estado_pedido, PedidoDelivery::ESTADOS_DESPACHABLES, true)) {
                    throw new Exception("El pedido #{$pedido->numero} no está despachable (estado '{$pedido->estado_pedido}')");
                }

                $this->pedidoService->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_CAMINO);
            }

            $salida->update([
                'estado' => DeliverySalida::ESTADO_EN_CAMINO,
                'salida_at' => now(),
            ]);

            Log::info('Salida de reparto registrada', [
                'salida_id' => $salida->id,
                'repartidor_id' => $salida->repartidor_id,
                'pedidos' => $pedidos->pluck('id')->all(),
            ]);

            return $salida->fresh();
        });
    }

    /**
     * Pase manual listo → en_camino de UN pedido con repartidor. Delegado en
     * despacharPedidos: si el repartidor ya está EN LA CALLE, el pedido se
     * SUMA a ese viaje (no se abre una salida paralela); si no, se crea y
     * registra la salida implícita de 1 pedido.
     *
     * Si la sucursal permite despachar sin repartidor
     * (`exigir_repartidor` = false) y el pedido no tiene uno, no hay circuito
     * de fondo posible: usar cambiarEstado directo desde el panel.
     */
    public function despacharPedido(PedidoDelivery $pedido, ?int $usuarioId = null): DeliverySalida
    {
        if (! $pedido->repartidor_id) {
            throw new Exception('Asignar un repartidor antes de despachar el pedido');
        }

        return $this->despacharPedidos(
            sucursalId: (int) $pedido->sucursal_id,
            repartidorId: (int) $pedido->repartidor_id,
            pedidoIds: [$pedido->id],
            usuarioId: $usuarioId,
        );
    }

    /**
     * Despacha pedidos con un repartidor, UN VIAJE por repartidor (rev9): si
     * ya tiene una salida `en_camino` en la sucursal, los pedidos se SUMAN a
     * ese viaje (attach al pivot + pase a en_camino de cada uno) — despachar
     * 4 pedidos de a uno con Jose en la calle NO abre 4 salidas paralelas.
     * Sin salida en curso, crea la salida y la registra en el acto.
     */
    public function despacharPedidos(int $sucursalId, int $repartidorId, array $pedidoIds, ?int $usuarioId = null): DeliverySalida
    {
        if (empty($pedidoIds)) {
            throw new Exception('Una salida necesita al menos un pedido');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($sucursalId, $repartidorId, $pedidoIds, $usuarioId) {
            $abierta = DeliverySalida::where('repartidor_id', $repartidorId)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', DeliverySalida::ESTADO_EN_CAMINO)
                ->lockForUpdate()
                ->orderByDesc('salida_at')
                ->first();

            if ($abierta) {
                $this->attachPedidosASalida($abierta, $pedidoIds);

                foreach (PedidoDelivery::whereIn('id', $pedidoIds)->get() as $pedido) {
                    if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_EN_CAMINO) {
                        $this->pedidoService->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_CAMINO);
                    }
                }

                Log::info('Pedidos sumados a salida en camino', [
                    'salida_id' => $abierta->id,
                    'repartidor_id' => $repartidorId,
                    'pedidos' => $pedidoIds,
                ]);

                return $abierta->fresh();
            }

            $salida = $this->crearSalida(
                sucursalId: $sucursalId,
                repartidorId: $repartidorId,
                pedidoIds: $pedidoIds,
                usuarioId: $usuarioId,
            );

            return $this->registrarSalida($salida, $usuarioId);
        });
    }

    // ==================== VUELTA (RF-08) ====================

    /**
     * Registra la vuelta del repartidor: por pedido marca el resultado y, si
     * fue entregado, registra los cobros contra entrega ANTES de marcarlo
     * entregado (guard de conversión). Efectivo → fondo (D13); no-efectivo →
     * circuito normal de pagos. Pedidos no entregados vuelven a `listo`
     * (re-despacho; sus pagos previos persisten) con el intento conservado en
     * el pivot append-only.
     *
     * `$resultados` = [pedido_id => [
     *     'resultado' => 'entregado'|'no_entregado',
     *     'motivo'    => ?string (obligatorio si no_entregado),
     *     'cobros'    => [['pago_id' => int, 'monto_recibido' => ?float, 'referencia' => ?string], ...],
     * ]]
     *
     * La conversión automática a venta (config de sucursal) corre POST-commit,
     * individual y en try/catch: un pedido que no puede facturarse (p. ej. sin
     * caja) queda `entregado` en cola "por facturar" sin romper la vuelta.
     * `$cajaConversionId` es la caja de quien registra la vuelta, usada para
     * convertir pedidos sin caja propia (tienda/API) y como caja default del
     * balanceo del fondo.
     *
     * `$rendicion` (opcional) = balanceo del fondo en la misma vuelta:
     * ['modo' => 'nada'|'devolver'|'cerrar'|'reforzar', 'monto' => float,
     *  'caja_id' => ?int (default $cajaConversionId)].
     * - devolver: devolución PARCIAL a caja, el fondo sigue abierto (D4).
     * - cerrar: rendición definitiva (rendirFondo — acá sí hay diferencia).
     * - reforzar: se lleva MÁS cambio (egreso de caja; sin fondo lo abre).
     * Corre POST-vuelta: si falla, la vuelta ya quedó registrada (el mensaje
     * de error lo aclara) y el fondo se balancea después desde Repartidores.
     */
    public function registrarVuelta(DeliverySalida $salida, array $resultados, ?int $cajaConversionId = null, ?int $usuarioId = null, ?array $rendicion = null): DeliverySalida
    {
        if ($salida->estado !== DeliverySalida::ESTADO_EN_CAMINO) {
            throw new Exception("Solo se puede registrar la vuelta de una salida en camino (estado '{$salida->estado}')");
        }

        $rendicion = $this->validarRendicionDeVuelta($rendicion, $cajaConversionId);

        $pendientes = $salida->salidaPedidos()
            ->where('resultado', DeliverySalidaPedido::RESULTADO_PENDIENTE)
            ->with('pedido')
            ->get();

        foreach ($pendientes as $sp) {
            $res = $resultados[$sp->pedido_id] ?? null;
            if (! $res || ! in_array($res['resultado'] ?? null, [DeliverySalidaPedido::RESULTADO_ENTREGADO, DeliverySalidaPedido::RESULTADO_NO_ENTREGADO], true)) {
                throw new Exception("Falta el resultado del pedido #{$sp->pedido->numero} para registrar la vuelta");
            }
            if ($res['resultado'] === DeliverySalidaPedido::RESULTADO_NO_ENTREGADO && empty($res['motivo'])) {
                throw new Exception("Indicar el motivo de la no entrega del pedido #{$sp->pedido->numero}");
            }
        }

        $entregadosIds = [];

        DB::connection('pymes_tenant')->transaction(function () use ($salida, $resultados, $pendientes, $cajaConversionId, $usuarioId, &$entregadosIds) {
            foreach ($pendientes as $sp) {
                $pedido = $sp->pedido;
                $res = $resultados[$pedido->id];

                if ($res['resultado'] === DeliverySalidaPedido::RESULTADO_ENTREGADO) {
                    // 1. Cobros ANTES de entregar (guard de conversión).
                    foreach ($res['cobros'] ?? [] as $cobro) {
                        $this->registrarCobroDeVuelta($salida, $pedido, $cobro, $usuarioId, $cajaConversionId);
                    }

                    $sp->update(['resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO]);

                    // 2. Estado (la conversión automática se suprime acá y
                    // corre post-commit, fuera de esta transacción).
                    $this->pedidoService->cambiarEstado(
                        $pedido,
                        PedidoDelivery::ESTADO_ENTREGADO,
                        convertirAutomatico: false,
                        viaVuelta: true,
                    );

                    $entregadosIds[] = $pedido->id;
                } else {
                    if (! empty($res['cobros'])) {
                        throw new Exception("No se pueden registrar cobros de un pedido no entregado (#{$pedido->numero})");
                    }

                    $sp->update([
                        'resultado' => DeliverySalidaPedido::RESULTADO_NO_ENTREGADO,
                        'motivo' => $res['motivo'],
                    ]);

                    $this->pedidoService->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO, $res['motivo'], viaVuelta: true);
                    $pedido->update(['salida_id' => null]);
                }
            }

            $salida->update([
                'estado' => DeliverySalida::ESTADO_FINALIZADA,
                'vuelta_at' => now(),
            ]);

            // Liquidación de envíos de terceros de los pedidos entregados de
            // ESTA salida (D3): el saldo del fondo queda honesto vuelta a
            // vuelta; rendirFondo barre después solo los que falten.
            if (! empty($entregadosIds)) {
                $fondo = $salida->repartidor()->first()?->fondoAbierto((int) $salida->sucursal_id);
                if ($fondo) {
                    $this->liquidarEnviosDeTerceros($fondo, $usuarioId ?: ((int) auth()->id() ?: 0), $entregadosIds);
                }
            }
        });

        Log::info('Vuelta de reparto registrada', [
            'salida_id' => $salida->id,
            'entregados' => $entregadosIds,
        ]);

        // Conversión automática POST-vuelta: individual y fuera de la
        // transacción — una falla (ARCA, sin caja) no rompe la vuelta.
        // Config PROPIA de delivery (key del JSON config_delivery).
        $sucursal = $salida->sucursal()->first();
        if ($sucursal && $this->pedidoService->conversionAutomaticaAlEntregar($sucursal)) {
            foreach ($entregadosIds as $pedidoId) {
                $pedido = PedidoDelivery::find($pedidoId);
                if (! $pedido || $pedido->venta_id) {
                    continue;
                }

                try {
                    $this->pedidoService->convertirEnVenta($pedido, cajaId: $cajaConversionId);
                } catch (Exception $e) {
                    Log::warning('Pedido entregado quedó por facturar (conversión post-vuelta falló)', [
                        'pedido_id' => $pedidoId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($rendicion) {
            $this->ejecutarRendicionDeVuelta($salida, $rendicion, $usuarioId);
        }

        return $salida->fresh();
    }

    /**
     * Normaliza y pre-valida el balanceo del fondo pedido en la vuelta, ANTES
     * de tocar nada (que un error obvio de caja no deje la vuelta a medias).
     */
    protected function validarRendicionDeVuelta(?array $rendicion, ?int $cajaFallbackId): ?array
    {
        $modo = $rendicion['modo'] ?? 'nada';

        if (! $rendicion || $modo === 'nada') {
            return null;
        }

        if (! in_array($modo, ['devolver', 'cerrar', 'reforzar'], true)) {
            throw new Exception("Modo de rendición desconocido: '{$modo}'");
        }

        $monto = round((float) ($rendicion['monto'] ?? 0), 2);

        if ($monto < 0) {
            throw new Exception('El monto de la rendición no puede ser negativo');
        }

        if ($monto <= 0 && $modo !== 'cerrar') {
            throw new Exception('Indicar el monto a '.($modo === 'reforzar' ? 'reforzar' : 'devolver'));
        }

        $cajaId = (int) ($rendicion['caja_id'] ?? 0) ?: (int) $cajaFallbackId;

        if (! $cajaId) {
            throw new Exception('Se necesita una caja para mover el efectivo del fondo');
        }

        $caja = Caja::findOrFail($cajaId);

        if ($monto > 0 && ! $caja->estaAbierta()) {
            throw new Exception('La caja debe estar abierta para el movimiento del fondo');
        }

        return ['modo' => $modo, 'monto' => $monto, 'caja_id' => $cajaId];
    }

    /**
     * Ejecuta el balanceo del fondo POST-vuelta. La vuelta ya está commiteada:
     * un fallo acá no la revierte (el mensaje lo aclara) y el fondo puede
     * balancearse después desde la pantalla Repartidores.
     */
    protected function ejecutarRendicionDeVuelta(DeliverySalida $salida, array $rendicion, ?int $usuarioId): void
    {
        try {
            $repartidor = $salida->repartidor()->first();
            $fondo = $repartidor?->fondoAbierto((int) $salida->sucursal_id);
            $detalle = "Vuelta de reparto (salida #{$salida->id})";

            if ($rendicion['modo'] === 'reforzar') {
                if ($fondo) {
                    $this->reforzarFondo($fondo, $rendicion['monto'], $rendicion['caja_id'], $usuarioId, $detalle);
                } else {
                    $this->abrirFondo((int) $salida->repartidor_id, (int) $salida->sucursal_id, $rendicion['caja_id'], $rendicion['monto'], $usuarioId, $detalle);
                }

                return;
            }

            if (! $fondo) {
                throw new Exception('El repartidor no tiene un fondo abierto en esta sucursal');
            }

            if ($rendicion['modo'] === 'devolver') {
                $this->devolverACaja($fondo, $rendicion['monto'], $rendicion['caja_id'], $usuarioId, $detalle);
            } else {
                $this->rendirFondo($fondo, $rendicion['monto'], $rendicion['caja_id'], $usuarioId, $detalle);
            }
        } catch (Exception $e) {
            throw new Exception(__('La vuelta quedó registrada, pero el movimiento del fondo falló: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Confirma un cobro contra entrega en EFECTIVO al fondo del repartidor
     * (D13): el pago planificado se activa con destino_fondo (sin
     * MovimientoCaja) y este service registra los asientos del fondo —
     * cobro_pedido por el efectivo recibido y vuelto por el cambio entregado
     * (el neto es el monto del pago, y el fondo refleja el arqueo físico).
     *
     * Camino compartido por la vuelta y por el cobro manual desde el panel.
     *
     * `$cajaAutoAperturaId`: si el repartidor NO tiene fondo abierto, se abre
     * uno en $0 contra esa caja (informacional: con $0 no hay MovimientoCaja)
     * para que el cobro no se corte. Sin caja de contexto, error como antes.
     */
    public function confirmarCobroContraEntrega(PedidoDeliveryPago $pago, array $datosCobro = [], ?int $usuarioId = null, ?int $cajaAutoAperturaId = null): PedidoDeliveryPago
    {
        if (! $pago->esPlanificado()) {
            throw new Exception("Solo se pueden confirmar al fondo pagos planificados (actual: '{$pago->estado}')");
        }

        if (! $this->esPagoEnEfectivo($pago)) {
            throw new Exception('Solo los cobros en efectivo van al fondo del repartidor; confirmar este pago por el circuito normal');
        }

        $pedido = $pago->pedido()->firstOrFail();

        if (! $pedido->repartidor_id) {
            throw new Exception('El pedido no tiene repartidor asignado');
        }

        $repartidor = Repartidor::findOrFail($pedido->repartidor_id);
        $fondo = $repartidor->fondoAbierto((int) $pedido->sucursal_id);

        if (! $fondo && $cajaAutoAperturaId) {
            $fondo = $this->abrirFondo(
                repartidorId: (int) $repartidor->id,
                sucursalId: (int) $pedido->sucursal_id,
                cajaOrigenId: $cajaAutoAperturaId,
                monto: 0,
                usuarioId: $usuarioId,
                detalle: 'Apertura automática al cobrar contra entrega',
            );
        }

        if (! $fondo) {
            throw new Exception("El repartidor {$repartidor->nombre} no tiene un fondo abierto en esta sucursal: abrir uno antes de registrar cobros en efectivo");
        }

        $montoFinal = (float) $pago->monto_final;
        $recibido = round((float) ($datosCobro['monto_recibido'] ?? $montoFinal), 2);

        if ($recibido + 0.005 < $montoFinal) {
            throw new Exception("El efectivo recibido (\${$recibido}) no cubre el monto del pago (\${$montoFinal})");
        }

        $vuelto = round($recibido - $montoFinal, 2);

        return DB::connection('pymes_tenant')->transaction(function () use ($pago, $pedido, $fondo, $recibido, $vuelto, $datosCobro, $usuarioId) {
            $usuarioId = $usuarioId ?: ((int) auth()->id() ?: (int) $pago->creado_por_usuario_id);

            $pago = $this->pedidoService->confirmarPagoPlanificado($pago, [
                'monto_recibido' => $recibido,
                'vuelto' => $vuelto,
                'referencia' => $datosCobro['referencia'] ?? null,
            ], [
                'destino_fondo' => true,
                'repartidor_fondo_id' => $fondo->id,
            ]);

            RepartidorFondoMovimiento::create([
                'fondo_id' => $fondo->id,
                'tipo' => RepartidorFondoMovimiento::TIPO_COBRO_PEDIDO,
                'monto' => $recibido,
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'detalle' => "Cobro Pedido delivery #{$pedido->id}",
            ]);

            if ($vuelto > 0.005) {
                RepartidorFondoMovimiento::create([
                    'fondo_id' => $fondo->id,
                    'tipo' => RepartidorFondoMovimiento::TIPO_VUELTO,
                    'monto' => -$vuelto,
                    'pedido_id' => $pedido->id,
                    'usuario_id' => $usuarioId,
                    'detalle' => "Vuelto Pedido delivery #{$pedido->id}",
                ]);
            }

            return $pago;
        });
    }

    // ==================== FONDO (RF-09, D4) ====================

    /**
     * Abre el fondo del repartidor con cambio entregado desde una caja:
     * egreso de MovimientoCaja (REF_FONDO_REPARTIDOR) + movimiento
     * entrega_inicial. Monto 0 permitido (fondo solo para recibir cobros).
     * A lo sumo UN fondo abierto por repartidor+sucursal.
     */
    public function abrirFondo(int $repartidorId, int $sucursalId, int $cajaOrigenId, float $monto, ?int $usuarioId = null, ?string $detalle = null): RepartidorFondo
    {
        if ($monto < 0) {
            throw new Exception('El monto inicial del fondo no puede ser negativo');
        }

        $repartidor = Repartidor::findOrFail($repartidorId);
        $this->validarRepartidorOperativo($repartidor, $sucursalId);

        $caja = Caja::findOrFail($cajaOrigenId);

        if ($monto > 0 && ! $caja->estaAbierta()) {
            throw new Exception('La caja de origen debe estar abierta para entregar el fondo');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($repartidor, $sucursalId, $caja, $monto, $usuarioId, $detalle) {
            $yaAbierto = RepartidorFondo::where('repartidor_id', $repartidor->id)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', RepartidorFondo::ESTADO_ABIERTO)
                ->lockForUpdate()
                ->exists();

            if ($yaAbierto) {
                throw new Exception("El repartidor {$repartidor->nombre} ya tiene un fondo abierto en esta sucursal");
            }

            $usuarioId = $usuarioId ?: ((int) auth()->id() ?: 0);

            $fondo = RepartidorFondo::create([
                'repartidor_id' => $repartidor->id,
                'sucursal_id' => $sucursalId,
                'caja_origen_id' => $caja->id,
                'estado' => RepartidorFondo::ESTADO_ABIERTO,
                'monto_inicial' => round($monto, 2),
                'usuario_apertura_id' => $usuarioId,
                'abierto_at' => now(),
            ]);

            if ($monto > 0) {
                $movimientoCaja = $this->crearEgresoCaja(
                    caja: $caja,
                    monto: $monto,
                    concepto: "Fondo repartidor {$repartidor->nombre} — entrega inicial",
                    fondoId: $fondo->id,
                    usuarioId: $usuarioId,
                );

                RepartidorFondoMovimiento::create([
                    'fondo_id' => $fondo->id,
                    'tipo' => RepartidorFondoMovimiento::TIPO_ENTREGA_INICIAL,
                    'monto' => round($monto, 2),
                    'movimiento_caja_id' => $movimientoCaja->id,
                    'usuario_id' => $usuarioId,
                    'detalle' => $detalle,
                ]);
            }

            Log::info('Fondo de repartidor abierto', [
                'fondo_id' => $fondo->id,
                'repartidor_id' => $repartidor->id,
                'monto_inicial' => $monto,
            ]);

            return $fondo->fresh();
        });
    }

    /**
     * Refuerza un fondo abierto con más cambio desde una caja (por defecto la
     * de origen): egreso de caja + movimiento refuerzo.
     */
    public function reforzarFondo(RepartidorFondo $fondo, float $monto, ?int $cajaId = null, ?int $usuarioId = null, ?string $detalle = null): RepartidorFondo
    {
        if ($monto <= 0) {
            throw new Exception('El refuerzo debe ser mayor a cero');
        }

        $caja = Caja::findOrFail($cajaId ?? $fondo->caja_origen_id);

        if (! $caja->estaAbierta()) {
            throw new Exception('La caja debe estar abierta para entregar el refuerzo');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($fondo, $caja, $monto, $usuarioId, $detalle) {
            $fondo = RepartidorFondo::lockForUpdate()->findOrFail($fondo->id);

            if (! $fondo->estaAbierto()) {
                throw new Exception('El fondo ya fue rendido');
            }

            $usuarioId = $usuarioId ?: ((int) auth()->id() ?: 0);

            $movimientoCaja = $this->crearEgresoCaja(
                caja: $caja,
                monto: $monto,
                concepto: "Fondo repartidor {$fondo->repartidor->nombre} — refuerzo",
                fondoId: $fondo->id,
                usuarioId: $usuarioId,
            );

            RepartidorFondoMovimiento::create([
                'fondo_id' => $fondo->id,
                'tipo' => RepartidorFondoMovimiento::TIPO_REFUERZO,
                'monto' => round($monto, 2),
                'movimiento_caja_id' => $movimientoCaja->id,
                'usuario_id' => $usuarioId,
                'detalle' => $detalle,
            ]);

            Log::info('Fondo de repartidor reforzado', [
                'fondo_id' => $fondo->id,
                'monto' => $monto,
            ]);

            return $fondo->fresh();
        });
    }

    /**
     * Rinde (cierra) el fondo: liquida los envíos si el repartidor es tercero
     * con envío propio, compara el efectivo declarado contra el saldo teórico
     * (diferencia sobrante/faltante, patrón RendicionFondo) y hace ingresar
     * a la caja receptora UN movimiento neto por lo declarado (D13). El
     * ledger del fondo queda en cero (rendición + ajuste por diferencia).
     */
    public function rendirFondo(RepartidorFondo $fondo, float $montoDeclarado, int $cajaRendicionId, ?int $usuarioId = null, ?string $observaciones = null): RepartidorFondo
    {
        if ($montoDeclarado < 0) {
            throw new Exception('El monto declarado no puede ser negativo');
        }

        $cajaRendicion = Caja::findOrFail($cajaRendicionId);

        if ($montoDeclarado > 0 && ! $cajaRendicion->estaAbierta()) {
            throw new Exception('La caja receptora debe estar abierta para recibir la rendición');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($fondo, $montoDeclarado, $cajaRendicion, $usuarioId, $observaciones) {
            $fondo = RepartidorFondo::with('repartidor')->lockForUpdate()->findOrFail($fondo->id);

            if (! $fondo->estaAbierto()) {
                throw new Exception('El fondo ya fue rendido');
            }

            $enCalle = DeliverySalida::where('repartidor_id', $fondo->repartidor_id)
                ->where('sucursal_id', $fondo->sucursal_id)
                ->where('estado', DeliverySalida::ESTADO_EN_CAMINO)
                ->exists();

            if ($enCalle) {
                throw new Exception('El repartidor tiene una salida en camino: registrar la vuelta antes de rendir el fondo');
            }

            $usuarioId = $usuarioId ?: ((int) auth()->id() ?: 0);
            $montoDeclarado = round($montoDeclarado, 2);

            // Liquidación de envíos de terceros (D3): si el envío es del
            // repartidor, los envíos de sus pedidos entregados durante la
            // vida del fondo se le descuentan explícitamente.
            $this->liquidarEnviosDeTerceros($fondo, $usuarioId);

            $saldoTeorico = round((float) $fondo->movimientos()->sum('monto'), 2);
            $diferencia = round($montoDeclarado - $saldoTeorico, 2);

            $movimientoCajaId = null;
            if ($montoDeclarado > 0) {
                $movimientoCaja = MovimientoCaja::create([
                    'caja_id' => $cajaRendicion->id,
                    'tipo' => MovimientoCaja::TIPO_INGRESO,
                    'concepto' => "Rendición fondo repartidor {$fondo->repartidor->nombre}".($observaciones ? " — {$observaciones}" : ''),
                    'monto' => $montoDeclarado,
                    'usuario_id' => $usuarioId,
                    'referencia_tipo' => MovimientoCaja::REF_FONDO_REPARTIDOR,
                    'referencia_id' => $fondo->id,
                ]);
                $cajaRendicion->aumentarSaldo($montoDeclarado);
                $movimientoCajaId = $movimientoCaja->id;
            }

            if (abs($montoDeclarado) > 0.005) {
                RepartidorFondoMovimiento::create([
                    'fondo_id' => $fondo->id,
                    'tipo' => RepartidorFondoMovimiento::TIPO_RENDICION,
                    'monto' => -$montoDeclarado,
                    'movimiento_caja_id' => $movimientoCajaId,
                    'usuario_id' => $usuarioId,
                    'detalle' => 'Rendición del fondo',
                ]);
            }

            if (abs($diferencia) > 0.005) {
                RepartidorFondoMovimiento::create([
                    'fondo_id' => $fondo->id,
                    'tipo' => RepartidorFondoMovimiento::TIPO_AJUSTE,
                    'monto' => $diferencia,
                    'usuario_id' => $usuarioId,
                    'detalle' => $diferencia > 0
                        ? 'Sobrante en rendición'
                        : 'Faltante en rendición',
                ]);
            }

            $fondo->update([
                'estado' => RepartidorFondo::ESTADO_RENDIDO,
                'monto_rendido' => $montoDeclarado,
                'diferencia' => $diferencia,
                'caja_rendicion_id' => $cajaRendicion->id,
                'usuario_cierre_id' => $usuarioId,
                'rendido_at' => now(),
            ]);

            Log::info('Fondo de repartidor rendido', [
                'fondo_id' => $fondo->id,
                'monto_declarado' => $montoDeclarado,
                'saldo_teorico' => $saldoTeorico,
                'diferencia' => $diferencia,
            ]);

            return $fondo->fresh();
        });
    }

    /**
     * Devolución PARCIAL del fondo a caja (vuelta del repartidor): ingreso a
     * la caja receptora + movimiento `devolucion` negativo, el fondo SIGUE
     * ABIERTO (ciclo largo, D4). Sin control de diferencia: el arqueo contra
     * el saldo teórico es exclusivo del cierre definitivo (rendirFondo).
     */
    public function devolverACaja(RepartidorFondo $fondo, float $monto, int $cajaId, ?int $usuarioId = null, ?string $detalle = null): RepartidorFondo
    {
        if ($monto <= 0) {
            throw new Exception('La devolución debe ser mayor a cero');
        }

        $caja = Caja::findOrFail($cajaId);

        if (! $caja->estaAbierta()) {
            throw new Exception('La caja receptora debe estar abierta para recibir la devolución');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($fondo, $monto, $caja, $usuarioId, $detalle) {
            $fondo = RepartidorFondo::with('repartidor')->lockForUpdate()->findOrFail($fondo->id);

            if (! $fondo->estaAbierto()) {
                throw new Exception('El fondo ya fue rendido');
            }

            $monto = round($monto, 2);
            $saldoTeorico = $this->saldoTeorico($fondo);

            if ($monto > $saldoTeorico + 0.005) {
                throw new Exception(sprintf('La devolución ($%.2f) supera el saldo del fondo ($%.2f) — para cerrar con faltante usar la rendición', $monto, $saldoTeorico));
            }

            $usuarioId = $usuarioId ?: ((int) auth()->id() ?: 0);

            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => MovimientoCaja::TIPO_INGRESO,
                'concepto' => "Devolución fondo repartidor {$fondo->repartidor->nombre}".($detalle ? " — {$detalle}" : ''),
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => MovimientoCaja::REF_FONDO_REPARTIDOR,
                'referencia_id' => $fondo->id,
            ]);
            $caja->aumentarSaldo($monto);

            RepartidorFondoMovimiento::create([
                'fondo_id' => $fondo->id,
                'tipo' => RepartidorFondoMovimiento::TIPO_DEVOLUCION,
                'monto' => -$monto,
                'movimiento_caja_id' => $movimientoCaja->id,
                'usuario_id' => $usuarioId,
                'detalle' => $detalle ?: 'Devolución a caja en la vuelta',
            ]);

            Log::info('Devolución parcial de fondo de repartidor', [
                'fondo_id' => $fondo->id,
                'monto' => $monto,
                'caja_id' => $caja->id,
            ]);

            return $fondo->fresh();
        });
    }

    /**
     * Saldo teórico de un fondo (suma de movimientos append-only).
     */
    public function saldoTeorico(RepartidorFondo $fondo): float
    {
        return round((float) $fondo->movimientos()->sum('monto'), 2);
    }

    /**
     * Fondos abiertos cuyo cambio salió de una caja (para la advertencia al
     * cerrar caja, D13: advierte, no bloquea).
     */
    public function fondosAbiertosDeCaja(int $cajaId): Collection
    {
        return RepartidorFondo::abiertos()
            ->where('caja_origen_id', $cajaId)
            ->with('repartidor')
            ->get();
    }

    /**
     * Total "en fondos de repartidores" de la sucursal (suma de saldos
     * teóricos de fondos abiertos) — línea informativa de tesorería para que
     * el efectivo en la calle nunca sea invisible.
     */
    public function totalEnFondosAbiertos(int $sucursalId): float
    {
        return round((float) RepartidorFondoMovimiento::whereHas(
            'fondo',
            fn ($q) => $q->where('estado', RepartidorFondo::ESTADO_ABIERTO)->where('sucursal_id', $sucursalId),
        )->sum('monto'), 2);
    }

    /**
     * Mensaje de advertencia si hay fondos abiertos originados en las cajas a
     * cerrar (null si no hay). Helper común de los tres caminos de cierre.
     */
    public function advertenciaFondosAbiertos(array $cajaIds): ?string
    {
        $fondos = RepartidorFondo::abiertos()
            ->whereIn('caja_origen_id', $cajaIds)
            ->with('repartidor')
            ->get();

        if ($fondos->isEmpty()) {
            return null;
        }

        $detalle = $fondos
            ->map(fn ($f) => "{$f->repartidor->nombre} ($".number_format($this->saldoTeorico($f), 2, ',', '.').')')
            ->implode(', ');

        return __('Hay :count fondo(s) de repartidor abiertos con cambio de esta caja: :detalle. La caja puede cerrarse igual; el efectivo ingresará al rendir cada fondo.', [
            'count' => $fondos->count(),
            'detalle' => $detalle,
        ]);
    }

    // ==================== INTERNOS ====================

    protected function validarRepartidorOperativo(Repartidor $repartidor, int $sucursalId): void
    {
        if (! $repartidor->activo) {
            throw new Exception("El repartidor {$repartidor->nombre} está inactivo");
        }

        $habilitado = $repartidor->sucursales()->where('sucursales.id', $sucursalId)->exists();
        if (! $habilitado) {
            throw new Exception("El repartidor {$repartidor->nombre} no está habilitado en esta sucursal");
        }
    }

    /**
     * Valida y vincula pedidos a una salida: estado despachable, tipo
     * delivery, misma sucursal y sin otra salida actual. Pisa el repartidor del pedido
     * con el de la salida y crea la fila del pivot (resultado pendiente).
     */
    protected function attachPedidosASalida(DeliverySalida $salida, array $pedidoIds): void
    {
        foreach (array_unique($pedidoIds) as $pedidoId) {
            $pedido = PedidoDelivery::findOrFail($pedidoId);

            if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
                throw new Exception("El pedido #{$pedido->numero} es take-away: no va en una salida de reparto");
            }

            if ((int) $pedido->sucursal_id !== (int) $salida->sucursal_id) {
                throw new Exception("El pedido #{$pedido->numero} es de otra sucursal");
            }

            if (! in_array($pedido->estado_pedido, PedidoDelivery::ESTADOS_DESPACHABLES, true)) {
                throw new Exception("El pedido #{$pedido->numero} no está despachable (estado '{$pedido->estado_pedido}')");
            }

            if ($pedido->salida_id && (int) $pedido->salida_id !== (int) $salida->id) {
                throw new Exception("El pedido #{$pedido->numero} ya está en otra salida");
            }

            if ((int) $pedido->salida_id === (int) $salida->id) {
                continue;
            }

            $pedido->update([
                'salida_id' => $salida->id,
                'repartidor_id' => $salida->repartidor_id,
            ]);

            DeliverySalidaPedido::create([
                'salida_id' => $salida->id,
                'pedido_id' => $pedido->id,
                'resultado' => DeliverySalidaPedido::RESULTADO_PENDIENTE,
            ]);
        }
    }

    /**
     * Rutea un cobro de la vuelta: efectivo → fondo (D13); no-efectivo →
     * confirmación normal (MovimientoCaja si el pedido tiene caja).
     */
    protected function registrarCobroDeVuelta(DeliverySalida $salida, PedidoDelivery $pedido, array $cobro, ?int $usuarioId, ?int $cajaAutoAperturaId = null): void
    {
        $pago = PedidoDeliveryPago::findOrFail($cobro['pago_id']);

        if ((int) $pago->pedido_delivery_id !== (int) $pedido->id) {
            throw new Exception("El pago #{$pago->id} no pertenece al pedido #{$pedido->numero}");
        }

        if ($this->esPagoEnEfectivo($pago)) {
            $this->confirmarCobroContraEntrega($pago, [
                'monto_recibido' => $cobro['monto_recibido'] ?? null,
                'referencia' => $cobro['referencia'] ?? null,
            ], $usuarioId, $cajaAutoAperturaId);

            return;
        }

        // Una FP con integración (QR) exige su propio ciclo de cobro con
        // confirmación del proveedor — materializarla acá dejaría un pago
        // "activo" sin transacción asociada (misma regla que el panel en
        // cobrarRapido). Se confirma después desde "Cobrar pendiente".
        $formaPago = $pago->formaPago()->first();
        if ($formaPago && $formaPago->tieneIntegracion()) {
            throw new Exception(
                "El pago con {$formaPago->nombre} del pedido #{$pedido->numero} usa una integración (QR): ".
                'destildalo en la vuelta y confirmalo desde "Cobrar pendiente" con el circuito de pago.'
            );
        }

        $this->pedidoService->confirmarPagoPlanificado($pago, [
            'referencia' => $cobro['referencia'] ?? null,
        ], [
            'caja_id' => $cajaAutoAperturaId,
        ]);
    }

    protected function esPagoEnEfectivo(PedidoDeliveryPago $pago): bool
    {
        $formaPago = $pago->formaPago()->with('conceptoPago')->first();

        return $formaPago
            && $formaPago->conceptoPago
            && strtoupper((string) $formaPago->conceptoPago->codigo) === 'EFECTIVO';
    }

    /**
     * Egreso de caja vinculado al fondo (entrega inicial / refuerzo).
     */
    protected function crearEgresoCaja(Caja $caja, float $monto, string $concepto, int $fondoId, int $usuarioId): MovimientoCaja
    {
        $movimiento = MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => MovimientoCaja::TIPO_EGRESO,
            'concepto' => $concepto,
            'monto' => round($monto, 2),
            'usuario_id' => $usuarioId,
            'referencia_tipo' => MovimientoCaja::REF_FONDO_REPARTIDOR,
            'referencia_id' => $fondoId,
        ]);

        $caja->disminuirSaldo(round($monto, 2));

        return $movimiento;
    }

    /**
     * Si el repartidor es tercero con envío propio, descuenta del fondo los
     * costos de envío de sus pedidos entregados durante la vida del fondo
     * (movimiento liquidacion_envios, explícito en el detalle).
     */
    protected function liquidarEnviosDeTerceros(RepartidorFondo $fondo, int $usuarioId, ?array $soloPedidoIds = null): void
    {
        $repartidor = $fondo->repartidor;

        if (! $repartidor->envio_es_del_repartidor) {
            return;
        }

        $query = PedidoDelivery::where('repartidor_id', $repartidor->id)
            ->where('sucursal_id', $fondo->sucursal_id)
            ->whereIn('estado_pedido', [PedidoDelivery::ESTADO_ENTREGADO, PedidoDelivery::ESTADO_FACTURADO])
            ->where('entregado_at', '>=', $fondo->abierto_at)
            ->where('costo_envio', '>', 0);

        if ($soloPedidoIds !== null) {
            $query->whereIn('id', $soloPedidoIds);
        }

        // Idempotencia: cada envío se liquida UNA sola vez (un movimiento por
        // pedido con pedido_id) — la vuelta liquida los de su salida y
        // rendirFondo barre después solo los que falten.
        $yaLiquidados = RepartidorFondoMovimiento::where('tipo', RepartidorFondoMovimiento::TIPO_LIQUIDACION_ENVIOS)
            ->whereNotNull('pedido_id')
            ->pluck('pedido_id');

        $pedidos = $query->whereNotIn('id', $yaLiquidados)->get(['id', 'costo_envio']);

        foreach ($pedidos as $pedido) {
            RepartidorFondoMovimiento::create([
                'fondo_id' => $fondo->id,
                'tipo' => RepartidorFondoMovimiento::TIPO_LIQUIDACION_ENVIOS,
                'monto' => -round((float) $pedido->costo_envio, 2),
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'detalle' => "Envío del pedido delivery #{$pedido->id} liquidado al repartidor",
            ]);
        }
    }
}
