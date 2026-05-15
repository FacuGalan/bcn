<?php

namespace App\Services\Pedidos;

use App\Events\Broadcasting\PedidoMostradorBroadcast;
use App\Events\PedidoMostrador\PedidoCancelado;
use App\Events\PedidoMostrador\PedidoConvertidoEnVenta;
use App\Events\PedidoMostrador\PedidoCreado;
use App\Events\PedidoMostrador\PedidoEstadoCambiado;
use App\Events\PedidoMostrador\PedidoEstadoPagoCambiado;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\FormaPago;
use App\Models\MovimientoCaja;
use App\Models\MovimientoStock;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorDetalle;
use App\Models\PedidoMostradorPago;
use App\Models\Receta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\VentaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Pedidos por Mostrador.
 *
 * Orquesta el ciclo de vida del pedido: alta (borrador o confirmado),
 * cambio de estado, gestión de pagos, cancelación y conversión a Venta.
 *
 * Ver spec en .claude/specs/pedidos-mostrador.md.
 */
class PedidoMostradorService
{
    public function __construct(
        protected ?VentaService $ventaService = null,
    ) {
        $this->ventaService ??= new VentaService;
    }

    // ==================== ALTA / EDICION ====================

    /**
     * Crea un pedido por mostrador.
     *
     * Si !$esBorrador:
     *   - asigna número atómico desde sucursales.pedido_mostrador_ultimo_numero
     *   - estado_pedido = ESTADO_CONFIRMADO
     *   - descuenta stock (registra MovimientoStock tipo=pedido_mostrador)
     *   - dispara PedidoCreado y (si configurado) comanda
     *
     * Si $esBorrador:
     *   - estado_pedido = ESTADO_BORRADOR
     *   - sin número ni stock
     */
    public function crearPedido(array $data, array $detalles, bool $esBorrador = false): PedidoMostrador
    {
        if (empty($detalles)) {
            throw new Exception('El pedido debe tener al menos un artículo');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($data, $detalles, $esBorrador) {
            $estado = $esBorrador ? PedidoMostrador::ESTADO_BORRADOR : PedidoMostrador::ESTADO_CONFIRMADO;
            $numero = $esBorrador ? null : $this->siguienteNumero((int) $data['sucursal_id']);

            $pedido = PedidoMostrador::create([
                'numero' => $numero,
                'identificador' => $data['identificador'] ?? null,
                'numero_beeper' => $data['numero_beeper'] ?? null,
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'nombre_cliente_temporal' => $data['nombre_cliente_temporal'] ?? null,
                'telefono_cliente_temporal' => $data['telefono_cliente_temporal'] ?? null,
                'caja_id' => $data['caja_id'] ?? null,
                'canal_venta_id' => $data['canal_venta_id'] ?? null,
                'forma_venta_id' => $data['forma_venta_id'] ?? null,
                'lista_precio_id' => $data['lista_precio_id'] ?? null,
                'usuario_id' => $data['usuario_id'],
                'fecha' => $data['fecha'] ?? now(),
                'estado_pedido' => $estado,
                'estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
                'subtotal' => $data['subtotal'] ?? 0,
                'iva' => $data['iva'] ?? 0,
                'descuento' => $data['descuento'] ?? 0,
                'total' => $data['total'] ?? 0,
                'ajuste_forma_pago' => $data['ajuste_forma_pago'] ?? 0,
                'total_final' => $data['total_final'] ?? ($data['total'] ?? 0),
                'descuento_general_tipo' => $data['descuento_general_tipo'] ?? null,
                'descuento_general_valor' => $data['descuento_general_valor'] ?? null,
                'descuento_general_monto' => $data['descuento_general_monto'] ?? 0,
                'descuento_general_aplicado_por' => $data['descuento_general_aplicado_por'] ?? null,
                'cupon_id' => $data['cupon_id'] ?? null,
                'cupon_codigo_snapshot' => $data['cupon_codigo_snapshot'] ?? null,
                'cupon_descripcion_snapshot' => $data['cupon_descripcion_snapshot'] ?? null,
                'monto_cupon' => $data['monto_cupon'] ?? 0,
                'puntos_ganados' => $data['puntos_ganados'] ?? 0,
                'puntos_usados' => $data['puntos_usados'] ?? 0,
                'puntos_canjeados_pago' => $data['puntos_canjeados_pago'] ?? 0,
                'puntos_canjeados_articulos' => $data['puntos_canjeados_articulos'] ?? 0,
                'puntos_usados_monto' => $data['puntos_usados_monto'] ?? 0,
                'articulos_canjeados_monto' => $data['articulos_canjeados_monto'] ?? 0,
                'observaciones' => $data['observaciones'] ?? null,
                'confirmado_at' => $esBorrador ? null : now(),
            ]);

            $this->guardarPromocionesPedido($pedido, $data);

            foreach ($detalles as $detalle) {
                $this->crearDetalle($pedido, $detalle);
            }

            if (! $esBorrador) {
                $this->descontarStockPorPedido($pedido);

                event(new PedidoCreado(
                    pedidoId: $pedido->id,
                    sucursalId: $pedido->sucursal_id,
                    usuarioId: $pedido->usuario_id,
                ));
                $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_CREADO);

                $this->maybeImprimirComandaAutomatica($pedido);
            }

            Log::info('Pedido mostrador creado', [
                'pedido_id' => $pedido->id,
                'numero' => $pedido->numero,
                'estado' => $pedido->estado_pedido,
                'sucursal_id' => $pedido->sucursal_id,
            ]);

            return $pedido->fresh(['detalles', 'pagos']);
        });
    }

    /**
     * Actualiza un pedido existente (sólo permitido en borrador/confirmado).
     *
     * Estrategia: revierte stock previo si lo había, borra detalles, recrea
     * y vuelve a descontar stock si corresponde. Mantiene número, pagos y
     * estado del pedido.
     */
    public function actualizarPedido(PedidoMostrador $pedido, array $data, array $detalles): PedidoMostrador
    {
        if (! in_array($pedido->estado_pedido, [
            PedidoMostrador::ESTADO_BORRADOR,
            PedidoMostrador::ESTADO_CONFIRMADO,
        ], true)) {
            throw new Exception("No se puede editar un pedido en estado '{$pedido->estado_pedido}'");
        }

        if (empty($detalles)) {
            throw new Exception('El pedido debe tener al menos un artículo');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $data, $detalles) {
            $estabaConfirmado = $pedido->estado_pedido === PedidoMostrador::ESTADO_CONFIRMADO;

            if ($estabaConfirmado) {
                $this->revertirStockPorPedido($pedido, motivo: 'Edición del pedido');
            }

            $pedido->detalles()->each(function ($d) {
                $d->opcionales()->delete();
                $d->promocionesAplicadas()->delete();
                $d->delete();
            });

            $pedido->update([
                'cliente_id' => $data['cliente_id'] ?? $pedido->cliente_id,
                'nombre_cliente_temporal' => $data['nombre_cliente_temporal'] ?? null,
                'telefono_cliente_temporal' => $data['telefono_cliente_temporal'] ?? null,
                'identificador' => $data['identificador'] ?? null,
                'numero_beeper' => $data['numero_beeper'] ?? null,
                'lista_precio_id' => $data['lista_precio_id'] ?? null,
                'subtotal' => $data['subtotal'] ?? 0,
                'iva' => $data['iva'] ?? 0,
                'descuento' => $data['descuento'] ?? 0,
                'total' => $data['total'] ?? 0,
                'ajuste_forma_pago' => $data['ajuste_forma_pago'] ?? 0,
                'total_final' => $data['total_final'] ?? ($data['total'] ?? 0),
                'descuento_general_tipo' => $data['descuento_general_tipo'] ?? null,
                'descuento_general_valor' => $data['descuento_general_valor'] ?? null,
                'descuento_general_monto' => $data['descuento_general_monto'] ?? 0,
                'descuento_general_aplicado_por' => $data['descuento_general_aplicado_por'] ?? null,
                'cupon_id' => $data['cupon_id'] ?? null,
                'cupon_codigo_snapshot' => $data['cupon_codigo_snapshot'] ?? null,
                'cupon_descripcion_snapshot' => $data['cupon_descripcion_snapshot'] ?? null,
                'monto_cupon' => $data['monto_cupon'] ?? 0,
                'puntos_ganados' => $data['puntos_ganados'] ?? 0,
                'puntos_usados' => $data['puntos_usados'] ?? 0,
                'puntos_canjeados_pago' => $data['puntos_canjeados_pago'] ?? 0,
                'puntos_canjeados_articulos' => $data['puntos_canjeados_articulos'] ?? 0,
                'puntos_usados_monto' => $data['puntos_usados_monto'] ?? 0,
                'articulos_canjeados_monto' => $data['articulos_canjeados_monto'] ?? 0,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            $this->guardarPromocionesPedido($pedido, $data);

            foreach ($detalles as $detalle) {
                $this->crearDetalle($pedido, $detalle);
            }

            if ($estabaConfirmado) {
                $this->descontarStockPorPedido($pedido);
            }

            // Recalcular totales (ajuste FP desde pagos) y luego estado_pago.
            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);

            return $pedido->fresh(['detalles', 'pagos']);
        });
    }

    // ==================== ESTADOS ====================

    /**
     * Cambia el estado del pedido validando la transición.
     *
     * Si la sucursal tiene `pedido_conversion_automatica_al_entregar=true` y
     * el nuevo estado es ENTREGADO, dispara convertirEnVenta() automáticamente
     * después de marcar el cambio.
     */
    public function cambiarEstado(PedidoMostrador $pedido, string $nuevoEstado, ?string $observacion = null): void
    {
        $anterior = $pedido->estado_pedido;

        if (! isset(PedidoMostrador::TRANSICIONES_PERMITIDAS[$anterior])) {
            throw new Exception("Estado actual desconocido: {$anterior}");
        }

        if (! in_array($nuevoEstado, PedidoMostrador::TRANSICIONES_PERMITIDAS[$anterior], true)) {
            throw new Exception("Transición no permitida: {$anterior} -> {$nuevoEstado}");
        }

        $timestampField = match ($nuevoEstado) {
            PedidoMostrador::ESTADO_CONFIRMADO => 'confirmado_at',
            PedidoMostrador::ESTADO_EN_PREPARACION => 'en_preparacion_at',
            PedidoMostrador::ESTADO_LISTO => 'listo_at',
            PedidoMostrador::ESTADO_ENTREGADO => 'entregado_at',
            PedidoMostrador::ESTADO_CANCELADO => 'cancelado_at',
            default => null,
        };

        DB::connection('pymes_tenant')->transaction(function () use ($pedido, $nuevoEstado, $anterior, $timestampField, $observacion) {
            $update = ['estado_pedido' => $nuevoEstado];
            if ($timestampField) {
                $update[$timestampField] = now();
            }
            if ($observacion !== null && $observacion !== '') {
                $update['observaciones'] = trim(($pedido->observaciones ?? '')."\n[{$nuevoEstado}] {$observacion}");
            }

            $pedido->update($update);

            event(new PedidoEstadoCambiado(
                pedidoId: $pedido->id,
                estadoAnterior: $anterior,
                estadoNuevo: $nuevoEstado,
                usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
            ));
            $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_ESTADO_CAMBIADO);
        });

        // Conversión automática post-commit si está configurada.
        if ($nuevoEstado === PedidoMostrador::ESTADO_ENTREGADO) {
            $sucursal = Sucursal::find($pedido->sucursal_id);
            if ($sucursal && $sucursal->pedido_conversion_automatica_al_entregar) {
                $this->convertirEnVenta($pedido->fresh());
            }
        }
    }

    /**
     * Transiciona un pedido en estado BORRADOR a CONFIRMADO. Asigna número,
     * marca confirmado_at, descuenta stock y dispara PedidoCreado. Idempotente:
     * si el pedido ya está confirmado, no hace nada.
     *
     * Usado por agregarPago/confirmarPagoPlanificado para que un cobro real
     * sobre un borrador automáticamente lo confirme — no queremos pagos sobre
     * pedidos sin número/stock.
     */
    public function confirmarBorrador(PedidoMostrador $pedido): void
    {
        if ($pedido->estado_pedido !== PedidoMostrador::ESTADO_BORRADOR) {
            return;
        }

        $numero = $this->siguienteNumero((int) $pedido->sucursal_id);
        $pedido->update([
            'estado_pedido' => PedidoMostrador::ESTADO_CONFIRMADO,
            'numero' => $numero,
            'confirmado_at' => now(),
        ]);

        // Recargar relaciones para que descontarStockPorPedido vea los detalles.
        $pedido->load('detalles');
        $this->descontarStockPorPedido($pedido);

        event(new PedidoCreado(
            pedidoId: $pedido->id,
            sucursalId: $pedido->sucursal_id,
            usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
        ));
        $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_CREADO);

        $this->maybeImprimirComandaAutomatica($pedido);

        Log::info('Borrador transicionado a confirmado por cobro', [
            'pedido_id' => $pedido->id,
            'numero' => $numero,
        ]);
    }

    // ==================== PAGOS ====================

    /**
     * Agrega un pago al pedido en uno de dos modos:
     *
     * - Cobrado (default): crea PedidoMostradorPago en estado `activo`. Si la
     *   forma de pago afecta caja y hay caja asignada, registra MovimientoCaja
     *   con REF_PEDIDO_MOSTRADOR y dispara PedidoEstadoPagoCambiado.
     *
     * - Planificado (`$datosPago['planificado'] = true`): crea el pago en estado
     *   `planificado`. NO crea MovimientoCaja, NO afecta el saldo de caja y NO
     *   cuenta para `estado_pago` del pedido. Pensado para flujos donde el
     *   pedido pre-configura cómo se va a cobrar (mesero, totem, app externa)
     *   y la materialización ocurre después con confirmarPagoPlanificado() o
     *   automáticamente al convertir el pedido en venta.
     */
    public function agregarPago(PedidoMostrador $pedido, array $datosPago): PedidoMostradorPago
    {
        if (in_array($pedido->estado_pedido, [
            PedidoMostrador::ESTADO_CANCELADO,
            PedidoMostrador::ESTADO_FACTURADO,
        ], true)) {
            throw new Exception("No se pueden agregar pagos a un pedido en estado '{$pedido->estado_pedido}'");
        }

        $esPlanificado = (bool) ($datosPago['planificado'] ?? false);

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $datosPago, $esPlanificado) {
            // Si el pedido está en BORRADOR y el pago NO es planificado,
            // transicionar a CONFIRMADO antes de registrar el cobro. Un pago
            // real no puede caer sobre un borrador sin asignar número/stock.
            if ($pedido->estado_pedido === PedidoMostrador::ESTADO_BORRADOR && ! $esPlanificado) {
                $this->confirmarBorrador($pedido);
                $pedido->refresh();
            }

            $formaPago = FormaPago::findOrFail($datosPago['forma_pago_id']);
            $afectaCaja = (bool) ($datosPago['afecta_caja'] ?? $formaPago->afecta_caja ?? true);

            // Planificado: nunca crea MovimientoCaja, aunque la forma de pago
            // sea efectivo. La materialización viene después.
            $movimientoCajaId = null;
            if (! $esPlanificado && $afectaCaja && ! empty($pedido->caja_id)) {
                $movimientoCajaId = $this->crearMovimientoCajaIngreso($pedido, $datosPago, $formaPago);
            }

            $pago = PedidoMostradorPago::create([
                'pedido_mostrador_id' => $pedido->id,
                'forma_pago_id' => $datosPago['forma_pago_id'],
                'concepto_pago_id' => $datosPago['concepto_pago_id'] ?? null,
                'monto_base' => $datosPago['monto_base'],
                'ajuste_porcentaje' => $datosPago['ajuste_porcentaje'] ?? 0,
                'monto_ajuste' => $datosPago['monto_ajuste'] ?? 0,
                'monto_final' => $datosPago['monto_final'],
                'monto_recibido' => $datosPago['monto_recibido'] ?? null,
                'vuelto' => $datosPago['vuelto'] ?? 0,
                'cuotas' => $datosPago['cuotas'] ?? null,
                'recargo_cuotas_porcentaje' => $datosPago['recargo_cuotas_porcentaje'] ?? null,
                'recargo_cuotas_monto' => $datosPago['recargo_cuotas_monto'] ?? null,
                'monto_cuota' => $datosPago['monto_cuota'] ?? null,
                'referencia' => $datosPago['referencia'] ?? null,
                'observaciones' => $datosPago['observaciones'] ?? null,
                'es_cuenta_corriente' => (bool) ($datosPago['es_cuenta_corriente'] ?? false),
                'es_pago_puntos' => (bool) ($datosPago['es_pago_puntos'] ?? false),
                'puntos_usados' => $datosPago['puntos_usados'] ?? 0,
                'afecta_caja' => $afectaCaja,
                'estado' => $esPlanificado
                    ? PedidoMostradorPago::ESTADO_PLANIFICADO
                    : PedidoMostradorPago::ESTADO_ACTIVO,
                'movimiento_caja_id' => $movimientoCajaId,
                'creado_por_usuario_id' => (int) auth()->id() ?: $pedido->usuario_id,
                'moneda_id' => $datosPago['moneda_id'] ?? null,
                'monto_moneda_original' => $datosPago['monto_moneda_original'] ?? null,
                'tipo_cambio_id' => $datosPago['tipo_cambio_id'] ?? null,
                'tipo_cambio_tasa' => $datosPago['tipo_cambio_tasa'] ?? null,
            ]);

            // Recalcular totales con el nuevo pago (activo o planificado): el
            // ajuste FP y recargo de cuotas afectan total_final independiente
            // del estado. Luego recalcular estado_pago solo si es activo.
            $this->recalcularTotales($pedido);
            if (! $esPlanificado) {
                $this->recalcularEstadoPago($pedido);
            }

            return $pago->fresh();
        });
    }

    /**
     * Materializa un pago planificado: crea MovimientoCaja, marca `activo`,
     * recalcula estado_pago del pedido. `$datosCobro` puede sobreescribir
     * `monto_recibido` y `vuelto` (lo efectivamente entregado al cobrar).
     */
    public function confirmarPagoPlanificado(PedidoMostradorPago $pago, array $datosCobro = []): PedidoMostradorPago
    {
        if (! $pago->esPlanificado()) {
            throw new Exception("Solo se pueden confirmar pagos en estado 'planificado' (actual: '{$pago->estado}')");
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pago, $datosCobro) {
            $pedido = $pago->pedido()->first();

            // Si el pedido está en BORRADOR, transicionar a CONFIRMADO antes
            // de materializar: el pago pasa a tocar caja, no podemos dejarlo
            // sobre un borrador (sin número ni stock descontado).
            if ($pedido->estado_pedido === PedidoMostrador::ESTADO_BORRADOR) {
                $this->confirmarBorrador($pedido);
                $pedido->refresh();
            }

            $formaPago = FormaPago::findOrFail($pago->forma_pago_id);

            $update = [
                'estado' => PedidoMostradorPago::ESTADO_ACTIVO,
            ];

            if (array_key_exists('monto_recibido', $datosCobro)) {
                $update['monto_recibido'] = $datosCobro['monto_recibido'];
            }
            if (array_key_exists('vuelto', $datosCobro)) {
                $update['vuelto'] = $datosCobro['vuelto'];
            }
            if (array_key_exists('referencia', $datosCobro)) {
                $update['referencia'] = $datosCobro['referencia'];
            }

            if ($pago->afecta_caja && ! empty($pedido->caja_id)) {
                $movimientoCajaId = $this->crearMovimientoCajaIngreso($pedido, [
                    'monto_final' => $pago->monto_final,
                    'moneda_id' => $pago->moneda_id,
                    'tipo_cambio_id' => $pago->tipo_cambio_id,
                    'tipo_cambio_tasa' => $pago->tipo_cambio_tasa,
                    'monto_moneda_original' => $pago->monto_moneda_original,
                ], $formaPago);
                $update['movimiento_caja_id'] = $movimientoCajaId;
            }

            $pago->update($update);
            // Confirmar un planificado no cambia ajuste FP (mismo monto_ajuste,
            // ya estaba contado en total_final), pero por consistencia llamamos
            // recalcularTotales — es idempotente y barato.
            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);

            return $pago->fresh();
        });
    }

    /**
     * Elimina un pago planificado. Como nunca afectó caja, es DELETE directo
     * sin contraasiento. Lanza excepción si el pago no está en estado planificado.
     */
    public function eliminarPagoPlanificado(PedidoMostradorPago $pago): void
    {
        if (! $pago->esPlanificado()) {
            throw new Exception("Solo se pueden eliminar pagos en estado 'planificado' (actual: '{$pago->estado}'). Para anular un pago activo, usar anularPago.");
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pago) {
            $pedidoId = $pago->pedido_mostrador_id;
            $pago->delete();

            // Recalcular total_final del pedido al desaparecer un planificado
            // que aportaba ajuste FP / recargo cuotas.
            $pedido = PedidoMostrador::find($pedidoId);
            if ($pedido) {
                $this->recalcularTotales($pedido);
                $this->recalcularEstadoPago($pedido);
            }
        });
    }

    /**
     * Anula un pago activo. Genera contraasiento en MovimientoCaja (si lo
     * tenía) y recalcula estado_pago.
     */
    public function anularPago(PedidoMostradorPago $pago, ?string $motivo = null): void
    {
        if ($pago->estado !== PedidoMostradorPago::ESTADO_ACTIVO) {
            throw new Exception('El pago ya estaba anulado');
        }

        // Guard: paridad con CambioFormaPagoService::puedeModificarPago().
        // Si el pago ya forma parte de un cierre de turno cerrado, anularlo
        // dejaría el contraasiento fuera del cierre y descuadraría el arqueo.
        // Solo se permite con permiso explícito func.cambiar_forma_pago_turno_cerrado.
        if ($pago->cierre_turno_id !== null) {
            $user = \App\Models\User::find((int) auth()->id());
            if (! $user || ! $user->hasPermissionTo('func.cambiar_forma_pago_turno_cerrado')) {
                throw new Exception(__('No tenés permiso para anular pagos de turnos cerrados.'));
            }
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pago, $motivo) {
            $usuarioId = (int) auth()->id() ?: $pago->creado_por_usuario_id;

            if ($pago->movimiento_caja_id) {
                $original = MovimientoCaja::find($pago->movimiento_caja_id);
                if ($original && empty($original->anulado_por_movimiento_id)) {
                    MovimientoCaja::crearContraasiento(
                        movimientoOriginal: $original,
                        usuarioId: $usuarioId,
                        referenciaTipo: MovimientoCaja::REF_ANULACION_PEDIDO_MOSTRADOR,
                        referenciaId: $pago->pedido_mostrador_id,
                        conceptoOverride: "Anulación pago Pedido #{$pago->pedido_mostrador_id}".($motivo ? " — {$motivo}" : ''),
                    );
                }
            }

            $pago->update([
                'estado' => PedidoMostradorPago::ESTADO_ANULADO,
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            $pedido = $pago->pedido()->first();
            // El pago anulado deja de contar para ajuste FP: recalcular total_final.
            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);
        });
    }

    // ==================== CANCELACION ====================

    /**
     * Cancela un pedido: anula todos sus pagos activos (contraasientos) y
     * revierte el stock si estaba descontado.
     */
    public function cancelarPedido(PedidoMostrador $pedido, string $motivo): void
    {
        if (in_array($pedido->estado_pedido, [
            PedidoMostrador::ESTADO_CANCELADO,
            PedidoMostrador::ESTADO_FACTURADO,
        ], true)) {
            throw new Exception("No se puede cancelar un pedido en estado '{$pedido->estado_pedido}'");
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pedido, $motivo) {
            $usuarioId = (int) auth()->id() ?: $pedido->usuario_id;

            foreach ($pedido->pagos()->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)->get() as $pago) {
                $this->anularPago($pago, motivo: 'Cancelación del pedido');
            }

            // Pagos planificados se borran directamente — nunca tocaron caja.
            $pedido->pagos()
                ->where('estado', PedidoMostradorPago::ESTADO_PLANIFICADO)
                ->delete();

            if ($pedido->estado_pedido !== PedidoMostrador::ESTADO_BORRADOR) {
                $this->revertirStockPorPedido($pedido, motivo: $motivo);
            }

            $pedido->update([
                'estado_pedido' => PedidoMostrador::ESTADO_CANCELADO,
                'cancelado_at' => now(),
                'cancelado_por_usuario_id' => $usuarioId,
                'motivo_cancelacion' => $motivo,
            ]);

            event(new PedidoCancelado(
                pedidoId: $pedido->id,
                motivo: $motivo,
                usuarioId: $usuarioId,
            ));
            $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_CANCELADO);
        });
    }

    // ==================== CONVERSION A VENTA ====================

    /**
     * Convierte el pedido en una Venta.
     *
     * - Construye datosVenta + detalles desde el pedido.
     * - Invoca VentaService::crearVenta con flag stock_ya_descontado=true.
     * - Re-asocia MovimientoCaja (referencia_tipo=venta, referencia_id=venta_id)
     *   y MovimientoStock (tipo=venta, venta_id/venta_detalle_id, doc=venta_detalle).
     * - Migra PedidoMostradorPago a VentaPago manteniendo el vínculo bidireccional.
     * - Marca pedido facturado con venta_id y convertido_at.
     *
     * Nota: emisión fiscal y procesamiento de saldos en CC quedan a cargo del
     * caller (PR2.C/D según el flow de UI). Este método solo deja la venta
     * creada y referencia coherente.
     */
    public function convertirEnVenta(PedidoMostrador $pedido, ?array $opcionesFiscales = null): Venta
    {
        if ($pedido->estado_pedido === PedidoMostrador::ESTADO_FACTURADO || $pedido->venta_id) {
            throw new Exception('El pedido ya fue convertido en venta');
        }

        if ($pedido->estado_pedido === PedidoMostrador::ESTADO_CANCELADO) {
            throw new Exception('No se puede convertir un pedido cancelado');
        }

        if ($pedido->estado_pedido === PedidoMostrador::ESTADO_BORRADOR) {
            throw new Exception('Confirmar el pedido antes de convertirlo en venta');
        }

        // Bloquear conversión si los pagos cargados (activos + planificados) no
        // cubren el total_final. Esto evita "ventas fantasma" sin financiación.
        // Si el caller quiere financiar a CC, debe agregar un pago explícito con
        // forma de pago "cuenta corriente" antes de convertir.
        $this->guardConversionConPagosSuficientes($pedido);

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $opcionesFiscales) {
            // Materializar pagos planificados antes de migrar: cada uno crea
            // su MovimientoCaja y pasa a estado activo. Así la venta resultante
            // tiene todos los pagos como cobrados (igual que una venta directa).
            $this->materializarPagosPlanificados($pedido);

            $pedido->load(['detalles.opcionales', 'detalles.promocionesAplicadas', 'pagos']);

            $datosVenta = [
                'sucursal_id' => $pedido->sucursal_id,
                'cliente_id' => $pedido->cliente_id,
                'caja_id' => $pedido->caja_id,
                'canal_venta_id' => $pedido->canal_venta_id,
                'forma_venta_id' => $pedido->forma_venta_id,
                'lista_precio_id' => $pedido->lista_precio_id,
                'usuario_id' => $pedido->usuario_id,
                'numero' => null, // VentaService genera por caja si corresponde
                'fecha' => now(),
                '_usar_totales_proporcionados' => true,
                'subtotal' => (float) $pedido->subtotal,
                'iva' => (float) $pedido->iva,
                'descuento' => (float) $pedido->descuento,
                'total' => (float) $pedido->total,
                'ajuste_forma_pago' => (float) $pedido->ajuste_forma_pago,
                'total_final' => (float) $pedido->total_final,
                'descuento_general_tipo' => $pedido->descuento_general_tipo,
                'descuento_general_valor' => $pedido->descuento_general_valor,
                'descuento_general_monto' => $pedido->descuento_general_monto,
                'descuento_general_aplicado_por' => $pedido->descuento_general_aplicado_por,
                'cupon_id' => $pedido->cupon_id,
                'monto_cupon' => $pedido->monto_cupon,
                'puntos_usados' => $pedido->puntos_usados,
                'puntos_usados_monto' => (float) $pedido->puntos_usados_monto,
                'articulos_canjeados_monto' => (float) $pedido->articulos_canjeados_monto,
                'es_cuenta_corriente' => $this->pedidoTieneSaldoEnCC($pedido),
                'observaciones' => $pedido->observaciones,
            ];

            $detalles = $pedido->detalles->map(fn ($d) => $this->mapearDetalleAArrayVenta($d))->all();

            $venta = $this->ventaService->crearVenta(
                data: $datosVenta,
                detalles: $detalles,
                opciones: ['stock_ya_descontado' => true],
            );

            // Update post-crear venta para los 2 campos de puntos que VentaService::crearVenta
            // no toma en su Venta::create() (NuevaVenta los setea de igual forma post-crear).
            $venta->update([
                'puntos_canjeados_pago' => $pedido->puntos_canjeados_pago,
                'puntos_canjeados_articulos' => $pedido->puntos_canjeados_articulos,
            ]);

            $this->migrarPromocionesAVenta($pedido, $venta);
            $this->reasignarMovimientosStockAVenta($pedido, $venta);
            $this->migrarPagosAVenta($pedido, $venta);
            $this->procesarCanjesPuntos($pedido, $venta);

            $pedido->update([
                'estado_pedido' => PedidoMostrador::ESTADO_FACTURADO,
                'venta_id' => $venta->id,
                'convertido_at' => now(),
            ]);

            event(new PedidoConvertidoEnVenta(
                pedidoId: $pedido->id,
                ventaId: $venta->id,
                usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
            ));
            $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_CONVERTIDO_VENTA);

            Log::info('Pedido mostrador convertido en venta', [
                'pedido_id' => $pedido->id,
                'venta_id' => $venta->id,
                'opciones_fiscales' => $opcionesFiscales,
            ]);

            return $venta;
        });
    }

    // ==================== NUMERACION ====================

    /**
     * Reserva atómicamente el próximo número del contador de sucursal.
     * Bloquea la fila con SELECT FOR UPDATE durante la transacción.
     */
    public function siguienteNumero(int $sucursalId): int
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($sucursalId) {
            $actual = DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->lockForUpdate()
                ->value('pedido_mostrador_ultimo_numero');

            $siguiente = ((int) ($actual ?? 0)) + 1;

            DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->update(['pedido_mostrador_ultimo_numero' => $siguiente]);

            return $siguiente;
        });
    }

    /**
     * Resetea a 0 el contador de la sucursal. Audita en log.
     */
    public function resetearNumeracion(int $sucursalId, int $usuarioId): void
    {
        DB::connection('pymes_tenant')
            ->table('sucursales')
            ->where('id', $sucursalId)
            ->update(['pedido_mostrador_ultimo_numero' => 0]);

        Log::info('Numeración pedidos mostrador reseteada', [
            'sucursal_id' => $sucursalId,
            'usuario_id' => $usuarioId,
        ]);
    }

    // ==================== IMPRESION ====================

    /**
     * Genera el payload de impresión de la comanda y lo devuelve para que el
     * caller (Livewire) lo despache al cliente QZ. Si la sucursal tiene
     * `usa_beepers=true`, el encabezado incluye el número de beeper grande.
     *
     * @return array{escpos: string, html: string, tipo_documento: 'comanda', pedido_id: int}
     */
    public function imprimirComanda(PedidoMostrador $pedido): array
    {
        $plantillas = app(\App\Services\Impresion\PlantillasComanda::class);

        return [
            'tipo_documento' => 'comanda',
            'pedido_id' => $pedido->id,
            'escpos' => $plantillas->generarComandaESCPOS($pedido),
            'html' => $plantillas->generarComandaHTML($pedido),
        ];
    }

    /**
     * Idéntico a imprimirComanda pero para precuenta.
     *
     * @return array{escpos: string, html: string, tipo_documento: 'precuenta', pedido_id: int}
     */
    public function imprimirPrecuenta(PedidoMostrador $pedido): array
    {
        $plantillas = app(\App\Services\Impresion\PlantillasComanda::class);

        return [
            'tipo_documento' => 'precuenta',
            'pedido_id' => $pedido->id,
            'escpos' => $plantillas->generarPrecuentaESCPOS($pedido),
            'html' => $plantillas->generarPrecuentaHTML($pedido),
        ];
    }

    // ==================== INTERNOS ====================

    protected function crearDetalle(PedidoMostrador $pedido, array $detalle): PedidoMostradorDetalle
    {
        $pmDetalle = PedidoMostradorDetalle::create([
            'pedido_mostrador_id' => $pedido->id,
            'articulo_id' => $detalle['articulo_id'] ?? null,
            'es_concepto' => (bool) ($detalle['es_concepto'] ?? false),
            'concepto_descripcion' => $detalle['concepto_descripcion'] ?? null,
            'concepto_categoria_id' => $detalle['concepto_categoria_id'] ?? null,
            'tipo_iva_id' => $detalle['tipo_iva_id'] ?? null,
            'lista_precio_id' => $detalle['lista_precio_id'] ?? null,
            'cantidad' => $detalle['cantidad'],
            'precio_unitario' => $detalle['precio_unitario'],
            'precio_sin_iva' => $detalle['precio_sin_iva'] ?? null,
            'descuento' => $detalle['descuento'] ?? 0,
            'precio_lista' => $detalle['precio_lista'] ?? null,
            'precio_opcionales' => $detalle['precio_opcionales'] ?? 0,
            'subtotal' => $detalle['subtotal'] ?? ($detalle['precio_unitario'] * $detalle['cantidad']),
            'ajuste_manual_tipo' => $detalle['ajuste_manual_tipo'] ?? null,
            'ajuste_manual_valor' => $detalle['ajuste_manual_valor'] ?? null,
            'ajuste_manual_origen' => $detalle['ajuste_manual_origen'] ?? null,
            'ajuste_manual_aplicado_por' => $detalle['ajuste_manual_aplicado_por'] ?? null,
            'precio_sin_ajuste_manual' => $detalle['precio_sin_ajuste_manual'] ?? null,
            'pagado_con_puntos' => (bool) ($detalle['pagado_con_puntos'] ?? false),
            'puntos_usados' => $detalle['puntos_usados'] ?? 0,
            'iva_porcentaje' => $detalle['iva_porcentaje'] ?? 0,
            'iva_monto' => $detalle['iva_monto'] ?? 0,
            'descuento_porcentaje' => $detalle['descuento_porcentaje'] ?? 0,
            'descuento_monto' => $detalle['descuento_monto'] ?? 0,
            'descuento_promocion' => $detalle['descuento_promocion'] ?? 0,
            'descuento_promocion_especial' => $detalle['descuento_promocion_especial'] ?? 0,
            'descuento_cupon' => $detalle['descuento_cupon'] ?? 0,
            'descuento_lista' => $detalle['descuento_lista'] ?? 0,
            'tiene_promocion' => (bool) ($detalle['tiene_promocion'] ?? false),
            'total' => $detalle['total'] ?? ($detalle['precio_unitario'] * $detalle['cantidad']),
        ]);

        // Persistir promociones aplicadas por línea (espejo de
        // VentaService::guardarPromocionesDetalle). Antes de este fix la tabla
        // pedido_mostrador_detalle_promociones quedaba vacía y la auditoría
        // de promo por línea se perdía. El payload viene del Livewire en
        // `_promociones_item` con `promociones_comunes` y `promociones_especiales`.
        if (! empty($detalle['_promociones_item'])) {
            $this->guardarPromocionesDetalle($pmDetalle, $detalle['_promociones_item']);
        }

        return $pmDetalle;
    }

    /**
     * Persiste las promociones aplicadas a un detalle del pedido en
     * `pedido_mostrador_detalle_promociones`. Espejo de
     * `VentaService::guardarPromocionesDetalle()`.
     */
    protected function guardarPromocionesDetalle(PedidoMostradorDetalle $detalle, array $promocionesItem): void
    {
        $promocionesComunes = $promocionesItem['promociones_comunes'] ?? [];
        foreach ($promocionesComunes as $promo) {
            if (is_string($promo)) {
                continue;
            }
            DB::connection('pymes_tenant')->table('pedido_mostrador_detalle_promociones')->insert([
                'pedido_mostrador_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0,
                'descuento_aplicado' => $promo['descuento_item'] ?? $promo['descuento'] ?? 0,
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }

        $promocionesEspeciales = $promocionesItem['promociones_especiales'] ?? [];
        foreach ($promocionesEspeciales as $promo) {
            if (is_string($promo)) {
                DB::connection('pymes_tenant')->table('pedido_mostrador_detalle_promociones')->insert([
                    'pedido_mostrador_detalle_id' => $detalle->id,
                    'tipo_promocion' => 'promocion_especial',
                    'promocion_id' => null,
                    'promocion_especial_id' => null,
                    'lista_precio_id' => null,
                    'descripcion_promocion' => $promo,
                    'tipo_beneficio' => 'monto_fijo',
                    'valor_beneficio' => 0,
                    'descuento_aplicado' => 0,
                    'cantidad_requerida' => null,
                    'cantidad_bonificada' => null,
                    'created_at' => now(),
                ]);

                continue;
            }

            DB::connection('pymes_tenant')->table('pedido_mostrador_detalle_promociones')->insert([
                'pedido_mostrador_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion_especial',
                'promocion_id' => null,
                'promocion_especial_id' => $promo['promocion_especial_id'] ?? $promo['id'] ?? null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción Especial',
                'tipo_beneficio' => 'monto_fijo',
                'valor_beneficio' => $promo['descuento'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Descuenta stock por todos los detalles del pedido. Espeja el patrón de
     * VentaService::actualizarStockPorVenta pero con MovimientoStock de tipo
     * pedido_mostrador (sin venta_id) para que la conversión a venta los pueda
     * re-asociar al final.
     */
    protected function descontarStockPorPedido(PedidoMostrador $pedido): void
    {
        $sucursal = Sucursal::find($pedido->sucursal_id);
        $controlStock = $sucursal->control_stock_venta ?? 'bloquea';
        $permitirNegativo = ($controlStock !== 'bloquea');

        foreach ($pedido->detalles as $detalle) {
            if ($detalle->es_concepto || ! $detalle->articulo_id) {
                continue;
            }

            $articulo = Articulo::find($detalle->articulo_id);
            if (! $articulo) {
                continue;
            }

            $modoStock = $articulo->getModoStock($pedido->sucursal_id);
            if ($modoStock === 'ninguno') {
                continue;
            }

            if ($modoStock === 'receta') {
                $receta = $articulo->resolverReceta($pedido->sucursal_id);
                if ($receta) {
                    $this->descontarStockPorReceta(
                        $receta, (float) $detalle->cantidad, $pedido,
                        $detalle->id, "Pedido #{$pedido->id} - Receta {$articulo->nombre}",
                        $permitirNegativo
                    );
                }

                continue;
            }

            // Modo unitario
            $stock = Stock::where('sucursal_id', $pedido->sucursal_id)
                ->where('articulo_id', $detalle->articulo_id)
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->disminuir((float) $detalle->cantidad, $permitirNegativo);

            MovimientoStock::crearMovimientoPedidoMostrador(
                articuloId: $detalle->articulo_id,
                sucursalId: $pedido->sucursal_id,
                cantidad: (float) $detalle->cantidad,
                pedidoId: $pedido->id,
                pedidoDetalleId: $detalle->id,
                concepto: "Pedido #{$pedido->id} - {$articulo->nombre}",
                usuarioId: $pedido->usuario_id,
            );
        }
    }

    protected function descontarStockPorReceta(
        Receta $receta, float $cantidadVendida, PedidoMostrador $pedido,
        int $pedidoDetalleId, string $conceptoBase, bool $permitirNegativo
    ): void {
        foreach ($receta->ingredientes as $ingrediente) {
            $cantidad = $ingrediente->cantidad * $cantidadVendida / $receta->cantidad_producida;

            $stock = Stock::where('sucursal_id', $pedido->sucursal_id)
                ->where('articulo_id', $ingrediente->articulo_id)
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->disminuir($cantidad, $permitirNegativo);

            MovimientoStock::crearMovimientoPedidoMostrador(
                articuloId: $ingrediente->articulo_id,
                sucursalId: $pedido->sucursal_id,
                cantidad: $cantidad,
                pedidoId: $pedido->id,
                pedidoDetalleId: $pedidoDetalleId,
                concepto: $conceptoBase,
                usuarioId: $pedido->usuario_id,
            );
        }
    }

    /**
     * Genera contraasientos por cada movimiento de stock activo del pedido y
     * vuelve el stock a su valor previo.
     */
    protected function revertirStockPorPedido(PedidoMostrador $pedido, string $motivo): void
    {
        $movimientos = MovimientoStock::where('documento_tipo', MovimientoStock::DOC_PEDIDO_MOSTRADOR_DETALLE)
            ->whereIn('documento_id', $pedido->detalles->pluck('id'))
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        foreach ($movimientos as $mov) {
            // Revertir cantidad en stock antes del contraasiento
            $stock = Stock::where('sucursal_id', $mov->sucursal_id)
                ->where('articulo_id', $mov->articulo_id)
                ->first();
            if ($stock && $mov->salida > 0) {
                $stock->increment('cantidad', $mov->salida);
            }

            MovimientoStock::crearContraasiento(
                movimientoOriginal: $mov,
                motivo: $motivo,
                usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
            );
        }
    }

    /**
     * Crea el ingreso en caja para un pago de pedido. Toma snapshots de moneda
     * cuando vienen.
     */
    protected function crearMovimientoCajaIngreso(PedidoMostrador $pedido, array $datosPago, FormaPago $formaPago): int
    {
        $caja = Caja::findOrFail($pedido->caja_id);
        $usuarioId = (int) auth()->id() ?: $pedido->usuario_id;

        $movimiento = MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => MovimientoCaja::TIPO_INGRESO,
            'concepto' => "Pedido #{$pedido->id} - {$formaPago->nombre}",
            'monto' => $datosPago['monto_final'],
            'usuario_id' => $usuarioId,
            'referencia_tipo' => MovimientoCaja::REF_PEDIDO_MOSTRADOR,
            'referencia_id' => $pedido->id,
            'moneda_id' => $datosPago['moneda_id'] ?? null,
            'tipo_cambio_id' => $datosPago['tipo_cambio_id'] ?? null,
            'tipo_cambio_tasa' => $datosPago['tipo_cambio_tasa'] ?? null,
            'monto_moneda_original' => $datosPago['monto_moneda_original'] ?? null,
        ]);

        $caja->aumentarSaldo((float) $datosPago['monto_final']);

        return $movimiento->id;
    }

    /**
     * Recalcula `ajuste_forma_pago` y `total_final` del pedido a partir de los
     * pagos activos + planificados.
     *
     * Espejo del cálculo que hace `WithCalculoVenta::aplicarAjusteFormaPago()`
     * server-side: total_final = total (sin ajuste) + Σ monto_ajuste + Σ recargo_cuotas_monto
     * de pagos activos+planificados. El signo de `monto_ajuste` ya viene
     * aplicado (negativo en descuento, positivo en recargo).
     *
     * Si NO hay pagos, respeta el `total_final` ya persistido (lo que envió
     * el Livewire al crear el pedido). Apenas hay pagos, el service toma el
     * control y sobrescribe silenciosamente — alineado con
     * feedback_api_first_services (service es el contrato).
     */
    protected function recalcularTotales(PedidoMostrador $pedido): void
    {
        $pagos = $pedido->pagos()
            ->whereIn('estado', [
                PedidoMostradorPago::ESTADO_ACTIVO,
                PedidoMostradorPago::ESTADO_PLANIFICADO,
            ])
            ->get(['monto_ajuste', 'recargo_cuotas_monto']);

        if ($pagos->isEmpty()) {
            return;
        }

        $sumaAjuste = (float) $pagos->sum(fn ($p) => (float) $p->monto_ajuste);
        $sumaRecargo = (float) $pagos->sum(fn ($p) => (float) ($p->recargo_cuotas_monto ?? 0));
        $ajusteTotal = round($sumaAjuste + $sumaRecargo, 2);
        $totalBase = (float) $pedido->total;
        $totalFinal = round($totalBase + $ajusteTotal, 2);

        $pedido->update([
            'ajuste_forma_pago' => $ajusteTotal,
            'total_final' => $totalFinal,
        ]);
    }

    /**
     * Persiste las promociones a nivel pedido en `pedido_mostrador_promociones`.
     *
     * Espejo de `VentaService::guardarPromocionesVenta()`. Lee
     * `_promociones_comunes` y `_promociones_especiales` del array de datos.
     * Idempotente: DELETE previo antes de INSERT batch, así puede llamarse
     * tanto en crearPedido como actualizarPedido.
     */
    protected function guardarPromocionesPedido(PedidoMostrador $pedido, array $datos): void
    {
        DB::connection('pymes_tenant')
            ->table('pedido_mostrador_promociones')
            ->where('pedido_mostrador_id', $pedido->id)
            ->delete();

        $promocionesComunes = $datos['_promociones_comunes'] ?? [];
        $promocionesEspeciales = $datos['_promociones_especiales'] ?? [];

        foreach ($promocionesComunes as $promo) {
            DB::connection('pymes_tenant')->table('pedido_mostrador_promociones')->insert([
                'pedido_mostrador_id' => $pedido->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => $promo['nombre'] ?? $promo['descripcion'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'monto_minimo_requerido' => $promo['monto_minimo'] ?? null,
                'created_at' => now(),
            ]);
        }

        foreach ($promocionesEspeciales as $promo) {
            $tipoBeneficio = $promo['tipo'] ?? 'monto_fijo';
            if (! in_array($tipoBeneficio, ['porcentaje', 'monto_fijo'], true)) {
                $tipoBeneficio = 'monto_fijo';
            }

            DB::connection('pymes_tenant')->table('pedido_mostrador_promociones')->insert([
                'pedido_mostrador_id' => $pedido->id,
                'tipo_promocion' => 'promocion_especial',
                'promocion_id' => null,
                'promocion_especial_id' => $promo['promocion_especial_id'] ?? $promo['id'] ?? null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => $promo['nombre'] ?? $promo['descripcion'] ?? 'Promoción Especial',
                'tipo_beneficio' => $tipoBeneficio,
                'valor_beneficio' => $promo['descuento'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'monto_minimo_requerido' => null,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Recalcula el estado_pago del pedido basado en la suma de pagos activos
     * vs total_final. Dispara PedidoEstadoPagoCambiado si cambia.
     */
    protected function recalcularEstadoPago(PedidoMostrador $pedido): void
    {
        $pagado = (float) $pedido->pagos()
            ->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)
            ->sum('monto_final');

        $total = (float) $pedido->total_final;
        $anterior = $pedido->estado_pago;

        $nuevo = match (true) {
            $pagado <= 0 => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            $pagado + 0.005 >= $total => PedidoMostrador::ESTADO_PAGO_PAGADO,
            default => PedidoMostrador::ESTADO_PAGO_PARCIAL,
        };

        if ($nuevo !== $anterior) {
            $pedido->update(['estado_pago' => $nuevo]);

            event(new PedidoEstadoPagoCambiado(
                pedidoId: $pedido->id,
                estadoAnterior: $anterior,
                estadoNuevo: $nuevo,
            ));
        }

        // Broadcast siempre que se recalcula pago, incluso si el estado_pago
        // no cambia (ej: parcial -> parcial con mas pagos). El cliente refresca
        // y ve los nuevos montos.
        $this->dispatchBroadcast($pedido, PedidoMostradorBroadcast::TIPO_PAGO_CAMBIADO);
    }

    /**
     * Devuelve true si la sucursal tiene impresión automática activada. El
     * caller (Livewire) consume `imprimirComanda($pedido)` y despacha al
     * cliente QZ. Acá sólo dejamos rastro y publicamos el payload por evento
     * para listeners que quieran reaccionar.
     */
    protected function maybeImprimirComandaAutomatica(PedidoMostrador $pedido): void
    {
        $sucursal = Sucursal::find($pedido->sucursal_id);
        if ($sucursal && $sucursal->imprime_comanda_automatico) {
            Log::info('Pedido marcado para comanda automática', [
                'pedido_id' => $pedido->id,
                'sucursal_id' => $pedido->sucursal_id,
            ]);
        }
    }

    protected function pedidoTieneSaldoEnCC(PedidoMostrador $pedido): bool
    {
        $pagado = (float) $pedido->pagos()
            ->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)
            ->sum('monto_final');

        return $pagado + 0.005 < (float) $pedido->total_final;
    }

    /**
     * Verifica que la suma de pagos activos + planificados cubra el total del
     * pedido. Lanza excepción si no, con el detalle del faltante para que la
     * UI/API muestre algo accionable.
     */
    protected function guardConversionConPagosSuficientes(PedidoMostrador $pedido): void
    {
        $total = (float) $pedido->total_final;
        if ($total <= 0.005) {
            return; // total cero: nada que cubrir
        }

        $cubierto = (float) $pedido->pagos()
            ->whereIn('estado', [
                PedidoMostradorPago::ESTADO_ACTIVO,
                PedidoMostradorPago::ESTADO_PLANIFICADO,
            ])
            ->sum('monto_final');

        if ($cubierto + 0.005 < $total) {
            $faltante = round($total - $cubierto, 2);
            throw new Exception(
                "No se puede convertir el pedido en venta: faltan \${$faltante} sin cubrir. ".
                'Cargar pagos (planificados o cobrados) que sumen al menos el total antes de convertir. '.
                'Para financiar a cuenta corriente, agregar un pago con forma "cuenta corriente".'
            );
        }
    }

    /**
     * Confirma todos los pagos planificados del pedido en cadena. Usado por
     * convertirEnVenta para que la venta resultante tenga todo el desglose
     * como pagos activos con sus respectivos MovimientoCaja.
     */
    protected function materializarPagosPlanificados(PedidoMostrador $pedido): void
    {
        $planificados = $pedido->pagos()
            ->where('estado', PedidoMostradorPago::ESTADO_PLANIFICADO)
            ->get();

        foreach ($planificados as $pago) {
            $this->confirmarPagoPlanificado($pago);
        }
    }

    protected function mapearDetalleAArrayVenta(PedidoMostradorDetalle $d): array
    {
        // Reconstruir _promociones_item desde las filas persistidas en
        // pedido_mostrador_detalle_promociones, para que VentaService::
        // crearDetalleVenta llame a guardarPromocionesDetalle y se replique
        // la auditoría de promo por línea en venta_detalle_promociones.
        $promosLinea = $d->promocionesAplicadas ?? collect();
        $promocionesComunes = $promosLinea->where('tipo_promocion', 'promocion')->map(fn ($p) => [
            'promocion_id' => $p->promocion_id,
            'nombre' => $p->descripcion_promocion,
            'tipo_beneficio' => $p->tipo_beneficio,
            'valor' => (float) $p->valor_beneficio,
            'descuento_item' => (float) $p->descuento_aplicado,
        ])->values()->all();

        $promocionesEspeciales = $promosLinea->where('tipo_promocion', 'promocion_especial')->map(fn ($p) => [
            'promocion_especial_id' => $p->promocion_especial_id,
            'nombre' => $p->descripcion_promocion,
            'descuento' => (float) $p->descuento_aplicado,
        ])->values()->all();

        return [
            'articulo_id' => $d->articulo_id,
            'es_concepto' => (bool) $d->es_concepto,
            'concepto_descripcion' => $d->concepto_descripcion,
            'concepto_categoria_id' => $d->concepto_categoria_id,
            'tipo_iva_id' => $d->tipo_iva_id,
            'lista_precio_id' => $d->lista_precio_id,
            'cantidad' => (float) $d->cantidad,
            'precio_unitario' => (float) $d->precio_unitario,
            'precio_sin_iva' => $d->precio_sin_iva !== null ? (float) $d->precio_sin_iva : null,
            'descuento' => (float) $d->descuento,
            'precio_lista' => $d->precio_lista !== null ? (float) $d->precio_lista : null,
            'precio_opcionales' => (float) $d->precio_opcionales,
            'subtotal' => (float) $d->subtotal,
            'iva_porcentaje' => (float) $d->iva_porcentaje,
            'iva_monto' => (float) $d->iva_monto,
            'descuento_porcentaje' => (float) $d->descuento_porcentaje,
            'descuento_monto' => (float) $d->descuento_monto,
            'descuento_promocion' => (float) $d->descuento_promocion,
            'descuento_promocion_especial' => (float) $d->descuento_promocion_especial,
            'descuento_cupon' => (float) $d->descuento_cupon,
            'descuento_lista' => (float) $d->descuento_lista,
            'tiene_promocion' => (bool) $d->tiene_promocion,
            'total' => (float) $d->total,
            'precio_iva_incluido' => true,
            // Promociones por línea reconstruidas para que VentaService las persista
            // en venta_detalle_promociones (paridad post-conversión).
            '_promociones_item' => [
                'promociones_comunes' => $promocionesComunes,
                'promociones_especiales' => $promocionesEspeciales,
            ],
            // Mapeo back-pointer: el caller puede aprovecharlo para re-asociar
            // movimientos de stock por detalle si quisiera.
            '_pedido_detalle_id' => $d->id,
        ];
    }

    /**
     * Re-asocia los movimientos_stock activos del pedido a la venta resultante.
     * Cambia tipo a TIPO_VENTA y popula venta_id. Mantiene venta_detalle_id en
     * null porque el orden de los detalles en venta puede no coincidir 1:1.
     */
    protected function reasignarMovimientosStockAVenta(PedidoMostrador $pedido, Venta $venta): void
    {
        MovimientoStock::where('documento_tipo', MovimientoStock::DOC_PEDIDO_MOSTRADOR_DETALLE)
            ->whereIn('documento_id', $pedido->detalles->pluck('id'))
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->where('tipo', MovimientoStock::TIPO_PEDIDO_MOSTRADOR)
            ->update([
                'tipo' => MovimientoStock::TIPO_VENTA,
                'venta_id' => $venta->id,
                'observaciones' => DB::raw("CONCAT(IFNULL(observaciones, ''), ' | Convertido en Venta #{$venta->id}')"),
            ]);
    }

    /**
     * Migra cada PedidoMostradorPago activo a un VentaPago equivalente.
     * Actualiza MovimientoCaja para apuntar a la venta y guarda la referencia
     * bidireccional (pedido_pago.venta_pago_id).
     */
    protected function migrarPagosAVenta(PedidoMostrador $pedido, Venta $venta): void
    {
        $pagos = $pedido->pagos()->where('estado', PedidoMostradorPago::ESTADO_ACTIVO)->get();

        foreach ($pagos as $pago) {
            $ventaPago = VentaPago::create([
                'venta_id' => $venta->id,
                'forma_pago_id' => $pago->forma_pago_id,
                'concepto_pago_id' => $pago->concepto_pago_id,
                'monto_base' => $pago->monto_base,
                'ajuste_porcentaje' => $pago->ajuste_porcentaje,
                'monto_ajuste' => $pago->monto_ajuste,
                'monto_final' => $pago->monto_final,
                'saldo_pendiente' => $pago->saldo_pendiente ?? 0,
                'operacion_origen' => $pago->operacion_origen ?? 'venta_original',
                'monto_recibido' => $pago->monto_recibido,
                'vuelto' => $pago->vuelto,
                'cuotas' => $pago->cuotas,
                'recargo_cuotas_porcentaje' => $pago->recargo_cuotas_porcentaje,
                'recargo_cuotas_monto' => $pago->recargo_cuotas_monto,
                'monto_cuota' => $pago->monto_cuota,
                'referencia' => $pago->referencia,
                'observaciones' => trim(($pago->observaciones ?? '')." | Originado en pedido #{$pedido->id}"),
                'es_cuenta_corriente' => $pago->es_cuenta_corriente,
                'es_pago_puntos' => $pago->es_pago_puntos,
                'puntos_usados' => $pago->puntos_usados,
                'afecta_caja' => $pago->afecta_caja,
                'estado' => 'activo',
                'movimiento_caja_id' => $pago->movimiento_caja_id,
                'creado_por_usuario_id' => $pago->creado_por_usuario_id,
                'moneda_id' => $pago->moneda_id,
                'monto_moneda_original' => $pago->monto_moneda_original,
                'tipo_cambio_id' => $pago->tipo_cambio_id,
                'tipo_cambio_tasa' => $pago->tipo_cambio_tasa,
            ]);

            if ($pago->movimiento_caja_id) {
                MovimientoCaja::where('id', $pago->movimiento_caja_id)->update([
                    'referencia_tipo' => MovimientoCaja::REF_VENTA,
                    'referencia_id' => $venta->id,
                ]);
            }

            $pago->update(['venta_pago_id' => $ventaPago->id]);
        }
    }

    /**
     * Copia las filas de `pedido_mostrador_promociones` a `venta_promociones`
     * preservando todos los campos (las tablas tienen estructura idéntica salvo
     * la FK). Sin DELETE previo en venta_promociones porque la venta es nueva.
     */
    protected function migrarPromocionesAVenta(PedidoMostrador $pedido, Venta $venta): void
    {
        $promos = DB::connection('pymes_tenant')
            ->table('pedido_mostrador_promociones')
            ->where('pedido_mostrador_id', $pedido->id)
            ->get();

        if ($promos->isEmpty()) {
            return;
        }

        $rows = $promos->map(function ($p) use ($venta) {
            return [
                'venta_id' => $venta->id,
                'tipo_promocion' => $p->tipo_promocion,
                'promocion_id' => $p->promocion_id,
                'promocion_especial_id' => $p->promocion_especial_id,
                'forma_pago_id' => $p->forma_pago_id,
                'codigo_cupon' => $p->codigo_cupon,
                'descripcion_promocion' => $p->descripcion_promocion,
                'tipo_beneficio' => $p->tipo_beneficio,
                'valor_beneficio' => $p->valor_beneficio,
                'descuento_aplicado' => $p->descuento_aplicado,
                'monto_minimo_requerido' => $p->monto_minimo_requerido,
                'created_at' => $p->created_at ?? now(),
            ];
        })->all();

        DB::connection('pymes_tenant')->table('venta_promociones')->insert($rows);
    }

    /**
     * Procesa los canjes de puntos asociados a la venta recién creada:
     *
     *   1. Pagos `es_pago_puntos=true` → `PuntosService::canjearPuntosComoDescuento()`
     *      (crea `MovimientoPunto` tipo canje-descuento atado al venta_pago).
     *   2. Detalles `pagado_con_puntos=true` → `PuntosService::canjearArticuloConPuntos()`
     *      (crea `MovimientoPunto` tipo canje-articulo atado a la venta).
     *
     * Espejo de lo que hace `WithPagosDesglose::confirmarPago()` en NuevaVenta
     * (líneas ~2150-2225). Sin este método, los puntos quedaban como flag en
     * el modelo pero NO se descontaban del saldo del cliente.
     *
     * Si el pedido no tiene `cliente_id`, no se puede canjear (los puntos van
     * por cliente). Se loggea warning y se omite.
     */
    protected function procesarCanjesPuntos(PedidoMostrador $pedido, Venta $venta): void
    {
        if (! $pedido->cliente_id) {
            return;
        }

        $puntosService = app(\App\Services\PuntosService::class);
        $usuarioId = (int) auth()->id() ?: $pedido->usuario_id;
        $algo_canjeado = false;

        // 1) VentaPagos creados desde migrarPagosAVenta() con es_pago_puntos=true.
        $pagosPuntos = $venta->pagos()
            ->where('es_pago_puntos', true)
            ->where('estado', 'activo')
            ->where('puntos_usados', '>', 0)
            ->get();

        foreach ($pagosPuntos as $vp) {
            try {
                $puntosService->canjearPuntosComoDescuento(
                    clienteId: $pedido->cliente_id,
                    sucursalId: $pedido->sucursal_id,
                    montoDescuento: (float) $vp->monto_final,
                    ventaPagoId: $vp->id,
                    ventaId: $venta->id,
                    usuarioId: $usuarioId,
                );
                $algo_canjeado = true;
            } catch (\Throwable $e) {
                // Es operación financiera (descuenta puntos del saldo del
                // cliente). Si falla, queda discrepancia entre el flag
                // es_pago_puntos del VentaPago y el saldo real → ERROR no
                // WARNING para que oncall vea esto en logs.
                Log::error('No se pudo registrar canje de puntos como descuento al convertir pedido', [
                    'pedido_id' => $pedido->id,
                    'venta_id' => $venta->id,
                    'venta_pago_id' => $vp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2) Detalles del pedido con artículos pagados con puntos. Los pasamos
        // por detalles del pedido (no del venta) porque el orden puede diferir
        // tras la creación. Igual referencian la misma venta_id ya creada.
        $detallesPuntos = $pedido->detalles()
            ->where('pagado_con_puntos', true)
            ->where('puntos_usados', '>', 0)
            ->get();

        foreach ($detallesPuntos as $d) {
            if (! $d->articulo_id) {
                continue; // conceptos libres no canjean por puntos
            }
            try {
                $puntosService->canjearArticuloConPuntos(
                    clienteId: $pedido->cliente_id,
                    articuloId: $d->articulo_id,
                    sucursalId: $pedido->sucursal_id,
                    puntosNecesarios: (int) $d->puntos_usados,
                    ventaId: $venta->id,
                    usuarioId: $usuarioId,
                );
                $algo_canjeado = true;
            } catch (\Throwable $e) {
                Log::error('No se pudo registrar canje de artículo por puntos al convertir pedido', [
                    'pedido_id' => $pedido->id,
                    'venta_id' => $venta->id,
                    'detalle_id' => $d->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($algo_canjeado) {
            try {
                $puntosService->actualizarCacheCliente($pedido->cliente_id);
            } catch (\Throwable $e) {
                Log::warning('No se pudo actualizar cache de puntos del cliente', [
                    'cliente_id' => $pedido->cliente_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dispatch del evento broadcast PedidoMostradorBroadcast.
     *
     * Resuelve el comercio activo del TenantService (sesion web). Si no hay
     * contexto tenant (CLI, jobs sin sesion) se hace silent-skip — los flujos
     * de negocio funcionan igual, solo no llega notificacion al cliente.
     *
     * Si falla el dispatch (Reverb caido, etc.), se loggea pero NO rompe el
     * flujo principal del pedido. La consistencia de BD es prioritaria; el
     * tiempo real es best-effort.
     */
    private function dispatchBroadcast(PedidoMostrador $pedido, string $tipo): void
    {
        try {
            $comercioId = app(\App\Services\TenantService::class)->getComercioId();
            if ($comercioId === null) {
                return;
            }
            PedidoMostradorBroadcast::dispatch(
                $comercioId,
                (int) $pedido->sucursal_id,
                (int) $pedido->id,
                $tipo,
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo broadcastear PedidoMostradorBroadcast', [
                'pedido_id' => $pedido->id,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
