<?php

namespace App\Services\Pedidos;

use App\Events\Broadcasting\PedidoDeliveryBroadcast;
use App\Events\PedidoDelivery\PedidoDeliveryCancelado;
use App\Events\PedidoDelivery\PedidoDeliveryConvertidoEnVenta;
use App\Events\PedidoDelivery\PedidoDeliveryCreado;
use App\Events\PedidoDelivery\PedidoDeliveryEstadoCambiado;
use App\Events\PedidoDelivery\PedidoDeliveryEstadoPagoCambiado;
use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\FormaPago;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MovimientoCaja;
use App\Models\MovimientoStock;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryDetalle;
use App\Models\PedidoDeliveryPago;
use App\Models\Receta;
use App\Models\Repartidor;
use App\Models\RepartidorFondoMovimiento;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TipoIva;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\VentaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Pedidos Delivery / Take-Away.
 *
 * Espejo de PedidoMostradorService (mismo ciclo de vida: alta borrador o
 * confirmado, transiciones, pagos planificados/activos, cancelación por
 * contraasiento, conversión a Venta) MÁS la dimensión logística:
 *
 * - Renglón-concepto "Costo de envío" (D17): el service lo crea/actualiza/
 *   elimina desde `costo_envio` del encabezado. Los callers pasan detalles y
 *   totales SIN envío; el service materializa el renglón y ajusta totales.
 *   Excluido de descuentos/cupones/promos/puntos.
 * - Dirección de entrega con snapshot + persistencia en cliente (D6/D18) y
 *   cotización/alcance vía DeliveryEnvioService (D5/D7).
 * - Estado `en_camino` (solo delivery) con exigencia de repartidor según
 *   config; take-away salta listo → entregado y se anuncia en el llamador.
 * - Cobro contra entrega al FONDO del repartidor (D13): override
 *   `destino_fondo` en confirmarPagoPlanificado — sin MovimientoCaja.
 * - Conversión a venta con los fixes D19 (CuponUso, puntos ganados,
 *   opcionales a venta_detalle_opcionales) + origen polimórfico D20.
 *
 * API-first: TODO consumible idéntico desde Livewire y controllers API v1
 * (payload array validado); los permisos los chequean los callers.
 *
 * Ver spec en .claude/specs/pedidos-delivery.md.
 */
class PedidoDeliveryService
{
    use Concerns\ConNumeracionDisplay;

    public const DESCRIPCION_RENGLON_ENVIO = 'Costo de envío';

    public function __construct(
        protected ?VentaService $ventaService = null,
        protected ?DeliveryEnvioService $envioService = null,
    ) {
        $this->ventaService ??= new VentaService;
        $this->envioService ??= new DeliveryEnvioService;
    }

    // ==================== ALTA / EDICION ====================

    /**
     * Crea un pedido delivery/take-away. Contrato espejo de mostrador:
     *
     * - `$data['tipo']` es OBLIGATORIO ('delivery'|'take_away').
     * - `$detalles` y los totales de `$data` vienen SIN el renglón de envío:
     *   el service lo materializa desde `$data['costo_envio']` (D17) y ajusta
     *   subtotal/iva/total/total_final por el delta.
     * - Delivery confirmado exige `direccion_entrega`; take-away la ignora.
     * - Si no viene `hora_pactada_at` y el modo de promesa es 'automatica',
     *   se calcula desde la distancia cotizada (RF-15 core).
     */
    public function crearPedido(array $data, array $detalles, bool $esBorrador = false): PedidoDelivery
    {
        if (empty($detalles)) {
            throw new Exception('El pedido debe tener al menos un artículo');
        }

        $tipo = $data['tipo'] ?? null;
        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            throw new Exception("Tipo de pedido inválido: '{$tipo}' (delivery|take_away)");
        }

        $sucursal = Sucursal::findOrFail((int) $data['sucursal_id']);
        $this->validarTipoContraSucursal($sucursal, $tipo);

        if (! $esBorrador && $tipo === PedidoDelivery::TIPO_DELIVERY && empty($data['direccion_entrega'])) {
            throw new Exception('Un pedido delivery requiere dirección de entrega');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($data, $detalles, $esBorrador, $tipo, $sucursal) {
            $estado = $esBorrador ? PedidoDelivery::ESTADO_BORRADOR : PedidoDelivery::ESTADO_CONFIRMADO;
            $numero = $esBorrador ? null : $this->siguienteNumero($sucursal->id);
            $numeroDisplay = $esBorrador ? null : $this->siguienteNumeroDisplay($sucursal->id);

            $costoEnvio = $tipo === PedidoDelivery::TIPO_DELIVERY ? (float) ($data['costo_envio'] ?? 0) : 0.0;

            $totalFinalInicial = (float) ($data['total_final'] ?? ($data['total'] ?? 0)) + $costoEnvio;
            $estadoPagoInicial = $totalFinalInicial <= 0.005
                ? PedidoDelivery::ESTADO_PAGO_PAGADO
                : PedidoDelivery::ESTADO_PAGO_PENDIENTE;

            $pedido = PedidoDelivery::create([
                'numero' => $numero,
                'numero_display' => $numeroDisplay,
                'identificador' => $data['identificador'] ?? null,
                'numero_beeper' => $tipo === PedidoDelivery::TIPO_TAKE_AWAY ? ($data['numero_beeper'] ?? null) : null,
                'tipo' => $tipo,
                'sucursal_id' => $sucursal->id,
                'cliente_id' => $data['cliente_id'] ?? null,
                'nombre_cliente_temporal' => $data['nombre_cliente_temporal'] ?? null,
                'telefono_cliente_temporal' => $data['telefono_cliente_temporal'] ?? null,
                'email_cliente_temporal' => $data['email_cliente_temporal'] ?? null,
                'caja_id' => $data['caja_id'] ?? null,
                'canal_venta_id' => $data['canal_venta_id'] ?? null,
                'forma_venta_id' => $data['forma_venta_id'] ?? null,
                'lista_precio_id' => $data['lista_precio_id'] ?? null,
                'usuario_id' => $data['usuario_id'] ?? null,
                'fecha' => $data['fecha'] ?? now(),
                'estado_pedido' => $estado,
                'estado_pago' => $estadoPagoInicial,
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
                'es_invitacion_total' => (bool) ($data['es_invitacion_total'] ?? false),
                'invitacion_motivo' => $data['invitacion_motivo'] ?? null,
                'invitado_por_usuario_id' => $data['invitado_por_usuario_id'] ?? null,
                'invitado_at' => $data['invitado_at'] ?? null,
                'total_invitado' => $data['total_invitado'] ?? 0,
                // Logística (RF-04/RF-06)
                'direccion_entrega' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($data['direccion_entrega'] ?? null) : null,
                'direccion_referencia' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($data['direccion_referencia'] ?? null) : null,
                'localidad_entrega_id' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($data['localidad_entrega_id'] ?? null) : null,
                'latitud' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($data['latitud'] ?? null) : null,
                'longitud' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($data['longitud'] ?? null) : null,
                'zona_id' => $data['zona_id'] ?? null,
                'costo_envio' => $costoEnvio,
                'costo_envio_manual' => (bool) ($data['costo_envio_manual'] ?? false),
                'costo_envio_usuario_id' => $data['costo_envio_usuario_id'] ?? null,
                'distancia_km' => $data['distancia_km'] ?? null,
                'fuera_de_alcance' => (bool) ($data['fuera_de_alcance'] ?? false),
                'repartidor_id' => $data['repartidor_id'] ?? null,
                'hora_pactada_at' => $data['hora_pactada_at'] ?? null,
                'programado_para' => $data['programado_para'] ?? null,
                'datos_fiscales_snapshot' => $data['datos_fiscales_snapshot'] ?? null,
                'origen' => $data['origen'] ?? PedidoDelivery::ORIGEN_PANEL,
                'origen_referencia' => $data['origen_referencia'] ?? null,
                'consumidor_id' => $data['consumidor_id'] ?? null,
                'token_seguimiento' => $data['token_seguimiento'] ?? null, // hook creating genera ULID
            ]);

            $this->guardarPromocionesPedido($pedido, $data);

            foreach ($detalles as $detalle) {
                $this->crearDetalle($pedido, $detalle);
            }

            // Renglón-concepto del envío (D17): materializa costo_envio y
            // ajusta subtotal/iva/total/total_final por el delta.
            $this->gestionarRenglonCostoEnvio($pedido);

            // D6/D18: persistir la dirección de ENTREGA en el cliente (campos
            // propios, jamás el `direccion` fiscal) salvo "entregar en otra
            // dirección" (el caller manda _actualizar_direccion_cliente=false).
            $this->actualizarDireccionEntregaCliente($pedido, $data);

            // Promesa automática (RF-15 core): si no vino hora pactada y el
            // modo es 'automatica', calcular desde la distancia cotizada.
            if (! $pedido->hora_pactada_at && $tipo === PedidoDelivery::TIPO_DELIVERY) {
                $horaPactada = $this->envioService->calcularHoraPactada(
                    $sucursal,
                    $pedido->distancia_km !== null ? (float) $pedido->distancia_km : null
                );
                if ($horaPactada) {
                    $pedido->update(['hora_pactada_at' => $horaPactada]);
                }
            }

            if (! $esBorrador) {
                $pedido->load('detalles');
                $this->descontarStockPorPedido($pedido);

                event(new PedidoDeliveryCreado(
                    pedidoId: $pedido->id,
                    sucursalId: $pedido->sucursal_id,
                    usuarioId: $pedido->usuario_id,
                ));
                $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_CREADO);

                $this->maybeImprimirComandaAutomatica($pedido);
            }

            Log::info('Pedido delivery creado', [
                'pedido_id' => $pedido->id,
                'numero' => $pedido->numero,
                'tipo' => $pedido->tipo,
                'origen' => $pedido->origen,
                'estado' => $pedido->estado_pedido,
                'sucursal_id' => $pedido->sucursal_id,
            ]);

            return $pedido->fresh(['detalles', 'pagos']);
        });
    }

    /**
     * Actualiza un pedido existente. Misma regla de edición que mostrador:
     * estados no terminales sin cobros materializados. Los `$detalles` y
     * totales vienen SIN renglón de envío — se regenera desde costo_envio.
     */
    public function actualizarPedido(PedidoDelivery $pedido, array $data, array $detalles): PedidoDelivery
    {
        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
        ], true)) {
            throw new Exception("No se puede editar un pedido en estado '{$pedido->estado_pedido}'");
        }

        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR
            && $pedido->estado_pago !== PedidoDelivery::ESTADO_PAGO_PENDIENTE
            && $pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)->exists()) {
            throw new Exception('No se puede editar un pedido con cobros registrados');
        }

        if (empty($detalles)) {
            throw new Exception('El pedido debe tener al menos un artículo');
        }

        $tipo = $data['tipo'] ?? $pedido->tipo;
        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            throw new Exception("Tipo de pedido inválido: '{$tipo}'");
        }

        // RF-02: cambiar a delivery exige dirección (salvo borrador); cambiar
        // a take-away limpia envío/repartidor.
        if ($tipo === PedidoDelivery::TIPO_DELIVERY
            && $pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR
            && empty($data['direccion_entrega'] ?? $pedido->direccion_entrega)) {
            throw new Exception('Un pedido delivery requiere dirección de entrega');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $data, $detalles, $tipo) {
            $stockEstaDescontado = $pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR;

            if ($stockEstaDescontado) {
                $this->revertirStockPorPedido($pedido, motivo: 'Edición del pedido');
            }

            $pedido->detalles()->each(function ($d) {
                $d->opcionales()->delete();
                $d->promocionesAplicadas()->delete();
                $d->delete();
            });

            $esTakeAway = $tipo === PedidoDelivery::TIPO_TAKE_AWAY;

            $pedido->update([
                'tipo' => $tipo,
                'cliente_id' => $data['cliente_id'] ?? $pedido->cliente_id,
                'nombre_cliente_temporal' => $data['nombre_cliente_temporal'] ?? null,
                'telefono_cliente_temporal' => $data['telefono_cliente_temporal'] ?? null,
                'email_cliente_temporal' => $data['email_cliente_temporal'] ?? $pedido->email_cliente_temporal,
                'identificador' => $data['identificador'] ?? null,
                'numero_beeper' => $esTakeAway ? ($data['numero_beeper'] ?? null) : null,
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
                'es_invitacion_total' => (bool) ($data['es_invitacion_total'] ?? false),
                'invitacion_motivo' => $data['invitacion_motivo'] ?? null,
                'invitado_por_usuario_id' => $data['invitado_por_usuario_id'] ?? null,
                'invitado_at' => $data['invitado_at'] ?? null,
                'total_invitado' => $data['total_invitado'] ?? 0,
                // Logística: take-away limpia envío/dirección/repartidor (RF-02)
                'direccion_entrega' => $esTakeAway ? null : ($data['direccion_entrega'] ?? $pedido->direccion_entrega),
                'direccion_referencia' => $esTakeAway ? null : ($data['direccion_referencia'] ?? $pedido->direccion_referencia),
                'localidad_entrega_id' => $esTakeAway ? null : ($data['localidad_entrega_id'] ?? $pedido->localidad_entrega_id),
                'latitud' => $esTakeAway ? null : ($data['latitud'] ?? $pedido->latitud),
                'longitud' => $esTakeAway ? null : ($data['longitud'] ?? $pedido->longitud),
                'zona_id' => $esTakeAway ? null : ($data['zona_id'] ?? $pedido->zona_id),
                'costo_envio' => $esTakeAway ? 0 : (float) ($data['costo_envio'] ?? $pedido->costo_envio),
                'costo_envio_manual' => $esTakeAway ? false : (bool) ($data['costo_envio_manual'] ?? $pedido->costo_envio_manual),
                'costo_envio_usuario_id' => $esTakeAway ? null : ($data['costo_envio_usuario_id'] ?? $pedido->costo_envio_usuario_id),
                'distancia_km' => $esTakeAway ? null : ($data['distancia_km'] ?? $pedido->distancia_km),
                'fuera_de_alcance' => $esTakeAway ? false : (bool) ($data['fuera_de_alcance'] ?? $pedido->fuera_de_alcance),
                'repartidor_id' => $esTakeAway ? null : ($data['repartidor_id'] ?? $pedido->repartidor_id),
                'hora_pactada_at' => $data['hora_pactada_at'] ?? $pedido->hora_pactada_at,
            ]);

            $this->guardarPromocionesPedido($pedido, $data);

            foreach ($detalles as $detalle) {
                $this->crearDetalle($pedido, $detalle);
            }

            $this->gestionarRenglonCostoEnvio($pedido);

            $this->actualizarDireccionEntregaCliente($pedido, $data);

            if ($stockEstaDescontado) {
                $pedido->load('detalles');
                $this->descontarStockPorPedido($pedido);
            }

            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);

            return $pedido->fresh(['detalles', 'pagos']);
        });
    }

    // ==================== LOGISTICA (RF-04/RF-06/RF-08) ====================

    /**
     * D6/D18: si el caller lo pide (`_actualizar_direccion_cliente`, default
     * false) y el pedido delivery tiene cliente y dirección, persiste el
     * domicilio de ENTREGA en el cliente (campos propios; el `direccion`
     * fiscal NUNCA se pisa).
     */
    protected function actualizarDireccionEntregaCliente(PedidoDelivery $pedido, array $data): void
    {
        if (empty($data['_actualizar_direccion_cliente'])
            || ! $pedido->cliente_id
            || $pedido->tipo !== PedidoDelivery::TIPO_DELIVERY
            || empty($pedido->direccion_entrega)) {
            return;
        }

        Cliente::where('id', $pedido->cliente_id)->update([
            'direccion_entrega' => $pedido->direccion_entrega,
            'direccion_entrega_referencia' => $pedido->direccion_referencia,
            'latitud' => $pedido->latitud,
            'longitud' => $pedido->longitud,
        ]);
    }

    /**
     * Setea/actualiza la dirección de entrega del pedido (snapshot, D6):
     * re-cotiza el envío y, si `$actualizarCliente` y el pedido tiene cliente,
     * persiste el domicilio de ENTREGA en el cliente para precargar el próximo
     * pedido ("entregar en otra dirección" pasa false y no pisa nada — D18:
     * NUNCA se toca `clientes.direccion`, que es el domicilio fiscal).
     */
    public function establecerDireccion(PedidoDelivery $pedido, array $direccion, bool $actualizarCliente = true): PedidoDelivery
    {
        if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
            throw new Exception('Solo los pedidos delivery llevan dirección de entrega');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $direccion, $actualizarCliente) {
            $pedido->update([
                'direccion_entrega' => $direccion['direccion_entrega'] ?? $direccion['direccion'] ?? null,
                'direccion_referencia' => $direccion['direccion_referencia'] ?? $direccion['referencia'] ?? null,
                'localidad_entrega_id' => $direccion['localidad_entrega_id'] ?? $direccion['localidad_id'] ?? null,
                'latitud' => $direccion['latitud'] ?? null,
                'longitud' => $direccion['longitud'] ?? null,
            ]);

            $this->recotizarEnvio($pedido);

            if ($actualizarCliente && $pedido->cliente_id) {
                Cliente::where('id', $pedido->cliente_id)->update([
                    'direccion_entrega' => $pedido->direccion_entrega,
                    'direccion_entrega_referencia' => $pedido->direccion_referencia,
                    'latitud' => $pedido->latitud,
                    'longitud' => $pedido->longitud,
                ]);
            }

            return $pedido->fresh(['detalles']);
        });
    }

    /**
     * Re-cotiza el envío del pedido con DeliveryEnvioService y actualiza el
     * encabezado logístico (distancia, zona) + el renglón-concepto. Si el
     * costo fue pisado a mano (D7), lo respeta y solo refresca distancia/zona.
     */
    public function recotizarEnvio(PedidoDelivery $pedido): CotizacionEnvio
    {
        $sucursal = Sucursal::findOrFail($pedido->sucursal_id);

        $cotizacion = $this->envioService->cotizar(
            $sucursal,
            $pedido->latitud !== null ? (float) $pedido->latitud : null,
            $pedido->longitud !== null ? (float) $pedido->longitud : null,
        );

        $update = [
            'distancia_km' => $cotizacion->distanciaKm,
            'zona_id' => $cotizacion->zona?->id,
        ];

        if (! $pedido->costo_envio_manual && $cotizacion->esOk()) {
            $update['costo_envio'] = $cotizacion->costo;
        }

        $pedido->update($update);
        $this->gestionarRenglonCostoEnvio($pedido);
        $this->recalcularEstadoPago($pedido);

        return $cotizacion;
    }

    /**
     * Pisa el costo de envío a mano (D7). Audita quién lo hizo y sincroniza
     * el renglón-concepto + totales.
     */
    public function establecerCostoEnvio(PedidoDelivery $pedido, float $monto, bool $manual, ?int $usuarioId = null): PedidoDelivery
    {
        if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
            throw new Exception('Solo los pedidos delivery llevan costo de envío');
        }

        if ($monto < 0) {
            throw new Exception('El costo de envío no puede ser negativo');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $monto, $manual, $usuarioId) {
            $pedido->update([
                'costo_envio' => round($monto, 2),
                'costo_envio_manual' => $manual,
                'costo_envio_usuario_id' => $manual ? ($usuarioId ?: ((int) auth()->id() ?: null)) : null,
            ]);

            $this->gestionarRenglonCostoEnvio($pedido);
            $this->recalcularEstadoPago($pedido);

            return $pedido->fresh(['detalles']);
        });
    }

    /**
     * Asigna (o desasigna con null) el repartidor del pedido. Libre hasta
     * `listo`; en `en_camino` solo vía vuelta fallida + re-despacho (RF-08:
     * evita salidas/fondos cruzados).
     */
    public function asignarRepartidor(PedidoDelivery $pedido, ?int $repartidorId, ?int $usuarioId = null): PedidoDelivery
    {
        if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
            throw new Exception('Los pedidos take-away no llevan repartidor');
        }

        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_EN_CAMINO,
            PedidoDelivery::ESTADO_ENTREGADO,
            PedidoDelivery::ESTADO_FACTURADO,
            PedidoDelivery::ESTADO_CANCELADO,
        ], true)) {
            throw new Exception("No se puede reasignar repartidor con el pedido en '{$pedido->estado_pedido}' (usar vuelta + re-despacho)");
        }

        if ($repartidorId !== null) {
            $repartidor = Repartidor::findOrFail($repartidorId);
            if (! $repartidor->activo) {
                throw new Exception('El repartidor está inactivo');
            }
            $habilitado = $repartidor->sucursales()->where('sucursales.id', $pedido->sucursal_id)->exists();
            if (! $habilitado) {
                throw new Exception('El repartidor no está habilitado en esta sucursal');
            }
        }

        $pedido->update(['repartidor_id' => $repartidorId]);

        Log::info('Repartidor asignado a pedido delivery', [
            'pedido_id' => $pedido->id,
            'repartidor_id' => $repartidorId,
            'usuario_id' => $usuarioId ?: (int) auth()->id(),
        ]);

        $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_ESTADO_CAMBIADO);

        return $pedido->fresh();
    }

    /**
     * Nombres de artículos NO disponibles para el tipo de pedido (RF-16).
     * El panel advierte (operador puede forzar); la API pública bloquea.
     */
    public function articulosNoDisponibles(array $articuloIds, string $tipo): array
    {
        $columna = $tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'disponible_take_away' : 'disponible_delivery';

        return Articulo::whereIn('id', array_filter($articuloIds))
            ->where($columna, false)
            ->pluck('nombre')
            ->all();
    }

    // ==================== ESTADOS ====================

    /**
     * Cambia el estado validando la máquina de transiciones + reglas delivery:
     * - `en_camino` SOLO para tipo delivery (RF-03).
     * - listo → en_camino exige repartidor asignado si la sucursal lo pide
     *   (`exigir_repartidor`, default true).
     *
     * NOTA: el pase manual listo → en_camino desde acá NO registra salida;
     * RepartidorService::despacharPedido es el camino canónico (crea y
     * registra la salida implícita de 1 pedido).
     *
     * `$convertirAutomatico` = false suprime la conversión automática a venta
     * al entregar: RepartidorService::registrarVuelta la corre POST-vuelta,
     * individual y fuera de su transacción (una falla de ARCA no puede dejar
     * la vuelta a medias).
     */
    public function cambiarEstado(PedidoDelivery $pedido, string $nuevoEstado, ?string $observacion = null, bool $convertirAutomatico = true): void
    {
        $anterior = $pedido->estado_pedido;

        if (! isset(PedidoDelivery::TRANSICIONES_PERMITIDAS[$anterior])) {
            throw new Exception("Estado actual desconocido: {$anterior}");
        }

        if (! in_array($nuevoEstado, PedidoDelivery::TRANSICIONES_PERMITIDAS[$anterior], true)) {
            throw new Exception("Transición no permitida: {$anterior} -> {$nuevoEstado}");
        }

        if ($nuevoEstado === PedidoDelivery::ESTADO_EN_CAMINO) {
            if ($pedido->tipo !== PedidoDelivery::TIPO_DELIVERY) {
                throw new Exception('Un pedido take-away no puede pasar a en camino');
            }

            $config = $this->envioService->configDelivery(Sucursal::findOrFail($pedido->sucursal_id));
            if ($config['exigir_repartidor'] && ! $pedido->repartidor_id) {
                throw new Exception('Asignar un repartidor antes de despachar el pedido');
            }
        }

        $timestampField = match ($nuevoEstado) {
            PedidoDelivery::ESTADO_CONFIRMADO => 'confirmado_at',
            PedidoDelivery::ESTADO_EN_PREPARACION => 'en_preparacion_at',
            PedidoDelivery::ESTADO_LISTO => 'listo_at',
            PedidoDelivery::ESTADO_EN_CAMINO => 'en_camino_at',
            PedidoDelivery::ESTADO_ENTREGADO => 'entregado_at',
            PedidoDelivery::ESTADO_CANCELADO => 'cancelado_at',
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

            $update['orden_kanban'] = $pedido->id;

            $pedido->update($update);

            event(new PedidoDeliveryEstadoCambiado(
                pedidoId: $pedido->id,
                estadoAnterior: $anterior,
                estadoNuevo: $nuevoEstado,
                usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
            ));
            $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_ESTADO_CAMBIADO);
            $this->dispatchLlamadorPublico($pedido, $nuevoEstado, $anterior);
        });

        // Conversión automática post-commit (config compartida con mostrador).
        if ($convertirAutomatico && $nuevoEstado === PedidoDelivery::ESTADO_ENTREGADO) {
            $sucursal = Sucursal::find($pedido->sucursal_id);
            if ($sucursal && $sucursal->pedido_conversion_automatica_al_entregar) {
                $this->convertirEnVenta($pedido->fresh());
            }
        }
    }

    /**
     * Transiciona un BORRADOR a CONFIRMADO (número + display + stock).
     * Idempotente. Un pago real sobre borrador lo confirma automáticamente
     * (paridad mostrador). Valida dirección si es delivery.
     */
    public function confirmarBorrador(PedidoDelivery $pedido): void
    {
        if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR) {
            return;
        }

        if ($pedido->tipo === PedidoDelivery::TIPO_DELIVERY && empty($pedido->direccion_entrega)) {
            throw new Exception('Un pedido delivery requiere dirección de entrega antes de confirmarse');
        }

        $numero = $this->siguienteNumero((int) $pedido->sucursal_id);
        $numeroDisplay = $this->siguienteNumeroDisplay((int) $pedido->sucursal_id);
        $pedido->update([
            'estado_pedido' => PedidoDelivery::ESTADO_CONFIRMADO,
            'numero' => $numero,
            'numero_display' => $numeroDisplay,
            'confirmado_at' => now(),
        ]);

        $pedido->load('detalles');
        $this->descontarStockPorPedido($pedido);

        event(new PedidoDeliveryCreado(
            pedidoId: $pedido->id,
            sucursalId: $pedido->sucursal_id,
            usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
        ));
        $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_CREADO);

        $this->maybeImprimirComandaAutomatica($pedido);

        Log::info('Pedido delivery borrador confirmado', [
            'pedido_id' => $pedido->id,
            'numero' => $numero,
        ]);
    }

    // ==================== PAGOS ====================

    /**
     * Agrega un pago (cobrado o planificado). Paridad exacta con mostrador;
     * el cobro contra entrega viaja como PLANIFICADO y se confirma a la
     * vuelta del repartidor (RF-08) o desde el panel.
     */
    public function agregarPago(PedidoDelivery $pedido, array $datosPago): PedidoDeliveryPago
    {
        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
        ], true)) {
            throw new Exception("No se pueden agregar pagos a un pedido en estado '{$pedido->estado_pedido}'");
        }

        $esPlanificado = (bool) ($datosPago['planificado'] ?? false);

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $datosPago, $esPlanificado) {
            if ($pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR && ! $esPlanificado) {
                $this->confirmarBorrador($pedido);
                $pedido->refresh();
            }

            $formaPago = FormaPago::findOrFail($datosPago['forma_pago_id']);
            $afectaCaja = (bool) ($datosPago['afecta_caja'] ?? $formaPago->afecta_caja ?? true);

            $movimientoCajaId = null;
            if (! $esPlanificado && $afectaCaja && ! empty($pedido->caja_id)) {
                $movimientoCajaId = $this->crearMovimientoCajaIngreso($pedido, $datosPago, $formaPago);
            }

            $pago = PedidoDeliveryPago::create([
                'pedido_delivery_id' => $pedido->id,
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
                    ? PedidoDeliveryPago::ESTADO_PLANIFICADO
                    : PedidoDeliveryPago::ESTADO_ACTIVO,
                'movimiento_caja_id' => $movimientoCajaId,
                'creado_por_usuario_id' => ((int) auth()->id()) ?: ($datosPago['creado_por_usuario_id'] ?? $pedido->usuario_id),
                'moneda_id' => $datosPago['moneda_id'] ?? null,
                'monto_moneda_original' => $datosPago['monto_moneda_original'] ?? null,
                'tipo_cambio_id' => $datosPago['tipo_cambio_id'] ?? null,
                'tipo_cambio_tasa' => $datosPago['tipo_cambio_tasa'] ?? null,
            ]);

            $this->recalcularTotales($pedido);
            if (! $esPlanificado) {
                $this->recalcularEstadoPago($pedido);
            }

            return $pago->fresh();
        });
    }

    /**
     * Materializa un pago planificado. Override `destino_fondo` (D13): el
     * efectivo cobrado en la calle NO genera MovimientoCaja — el pedido queda
     * pagado y el dinero vive en el fondo del repartidor hasta la rendición.
     *
     * `$opciones['destino_fondo']` (bool) + `$opciones['repartidor_fondo_id']`.
     * El movimiento del fondo (tipo cobro_pedido) lo registra RepartidorService
     * al procesar la vuelta — acá solo se marca el pago.
     */
    public function confirmarPagoPlanificado(PedidoDeliveryPago $pago, array $datosCobro = [], array $opciones = []): PedidoDeliveryPago
    {
        if (! $pago->esPlanificado()) {
            throw new Exception("Solo se pueden confirmar pagos en estado 'planificado' (actual: '{$pago->estado}')");
        }

        $destinoFondo = (bool) ($opciones['destino_fondo'] ?? false);

        if ($destinoFondo && empty($opciones['repartidor_fondo_id'])) {
            throw new Exception('Un cobro al fondo del repartidor requiere el fondo destino (repartidor_fondo_id)');
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pago, $datosCobro, $destinoFondo, $opciones) {
            $pedido = $pago->pedido()->first();

            if ($pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR) {
                $this->confirmarBorrador($pedido);
                $pedido->refresh();
            }

            $formaPago = FormaPago::findOrFail($pago->forma_pago_id);

            $update = [
                'estado' => PedidoDeliveryPago::ESTADO_ACTIVO,
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

            if ($destinoFondo) {
                // D13: sin MovimientoCaja. La caja recibe recién al rendir el
                // fondo (UN ingreso neto).
                $update['destino_fondo'] = true;
                $update['repartidor_fondo_id'] = (int) $opciones['repartidor_fondo_id'];
            } elseif ($pago->afecta_caja && ! empty($pedido->caja_id)) {
                $update['movimiento_caja_id'] = $this->crearMovimientoCajaIngreso($pedido, [
                    'monto_final' => $pago->monto_final,
                    'moneda_id' => $pago->moneda_id,
                    'tipo_cambio_id' => $pago->tipo_cambio_id,
                    'tipo_cambio_tasa' => $pago->tipo_cambio_tasa,
                    'monto_moneda_original' => $pago->monto_moneda_original,
                ], $formaPago);
            }

            $pago->update($update);
            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);

            return $pago->fresh();
        });
    }

    /**
     * Elimina un pago planificado (nunca afectó caja ni fondo): DELETE directo.
     */
    public function eliminarPagoPlanificado(PedidoDeliveryPago $pago): void
    {
        if (! $pago->esPlanificado()) {
            throw new Exception("Solo se pueden eliminar pagos en estado 'planificado' (actual: '{$pago->estado}'). Para anular un pago activo, usar anularPago.");
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pago) {
            $pedidoId = $pago->pedido_delivery_id;
            $pago->delete();

            $pedido = PedidoDelivery::find($pedidoId);
            if ($pedido) {
                $this->recalcularTotales($pedido);
                $this->recalcularEstadoPago($pedido);
            }
        });
    }

    /**
     * Anula un pago activo por contraasiento. Si el pago vivía en el FONDO del
     * repartidor (destino_fondo), el contraasiento es un movimiento inverso
     * del fondo (D13) en lugar de MovimientoCaja.
     */
    public function anularPago(PedidoDeliveryPago $pago, ?string $motivo = null): void
    {
        if ($pago->estado !== PedidoDeliveryPago::ESTADO_ACTIVO) {
            throw new Exception('El pago ya estaba anulado');
        }

        $fpPago = $pago->formaPago()->first();
        if ($fpPago && $fpPago->tieneIntegracion()) {
            $pedidoDelPago = $pago->pedido()->first();
            if ($pedidoDelPago && $pedidoDelPago->tieneIntegracionPagoConfirmada()) {
                throw new Exception(__('No se puede modificar: este pago se cobró por integración (QR) y ya fue confirmado. La devolución debe hacerse desde el proveedor de pago.'));
            }
        }

        if ($pago->cierre_turno_id !== null) {
            $user = \App\Models\User::find((int) auth()->id());
            if (! $user || ! $user->hasPermissionTo('func.cambiar_forma_pago_turno_cerrado')) {
                throw new Exception(__('No tenés permiso para anular pagos de turnos cerrados.'));
            }
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pago, $motivo) {
            $usuarioId = (int) auth()->id() ?: $pago->creado_por_usuario_id;

            if ($pago->destino_fondo && $pago->repartidor_fondo_id) {
                // Movimiento inverso del fondo (append-only): el efectivo del
                // cobro anulado deja de contarse en el saldo teórico.
                RepartidorFondoMovimiento::create([
                    'fondo_id' => $pago->repartidor_fondo_id,
                    'tipo' => RepartidorFondoMovimiento::TIPO_AJUSTE,
                    'monto' => -(float) $pago->monto_final,
                    'pedido_id' => $pago->pedido_delivery_id,
                    'usuario_id' => $usuarioId ?: 0,
                    'detalle' => "Anulación cobro Pedido delivery #{$pago->pedido_delivery_id}".($motivo ? " — {$motivo}" : ''),
                ]);
            } elseif ($pago->movimiento_caja_id) {
                $original = MovimientoCaja::find($pago->movimiento_caja_id);
                if ($original && empty($original->anulado_por_movimiento_id)) {
                    MovimientoCaja::crearContraasiento(
                        movimientoOriginal: $original,
                        usuarioId: $usuarioId,
                        referenciaTipo: MovimientoCaja::REF_ANULACION_PEDIDO_DELIVERY,
                        referenciaId: $pago->pedido_delivery_id,
                        conceptoOverride: "Anulación pago Pedido delivery #{$pago->pedido_delivery_id}".($motivo ? " — {$motivo}" : ''),
                    );
                }
            }

            $pago->update([
                'estado' => PedidoDeliveryPago::ESTADO_ANULADO,
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            $pedido = $pago->pedido()->first();
            $this->recalcularTotales($pedido);
            $this->recalcularEstadoPago($pedido);
        });
    }

    // ==================== CANCELACION ====================

    /**
     * Cancela el pedido: contraasienta pagos activos (incl. movimiento inverso
     * del fondo para cobros contra entrega, D13), borra planificados y
     * revierte stock.
     */
    public function cancelarPedido(PedidoDelivery $pedido, string $motivo): void
    {
        if (in_array($pedido->estado_pedido, [
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::ESTADO_FACTURADO,
        ], true)) {
            throw new Exception("No se puede cancelar un pedido en estado '{$pedido->estado_pedido}'");
        }

        if ($pedido->tieneIntegracionPagoConfirmada()) {
            throw new Exception(__('No se puede anular ni modificar: este pedido tiene un cobro por integración (QR) ya confirmado. La devolución debe hacerse desde el proveedor de pago.'));
        }

        DB::connection('pymes_tenant')->transaction(function () use ($pedido, $motivo) {
            $usuarioId = (int) auth()->id() ?: $pedido->usuario_id;

            foreach ($pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)->get() as $pago) {
                $this->anularPago($pago, motivo: 'Cancelación del pedido');
            }

            $pedido->pagos()
                ->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)
                ->delete();

            if ($pedido->estado_pedido !== PedidoDelivery::ESTADO_BORRADOR) {
                $this->revertirStockPorPedido($pedido, motivo: $motivo);
            }

            $pedido->update([
                'estado_pedido' => PedidoDelivery::ESTADO_CANCELADO,
                'cancelado_at' => now(),
                'cancelado_por_usuario_id' => $usuarioId,
                'motivo_cancelacion' => $motivo,
            ]);

            event(new PedidoDeliveryCancelado(
                pedidoId: $pedido->id,
                motivo: $motivo,
                usuarioId: $usuarioId,
            ));
            $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_CANCELADO);
        });
    }

    // ==================== CONVERSION A VENTA ====================

    /**
     * Convierte el pedido en Venta. Paridad con mostrador MÁS los fixes D19
     * y el origen polimórfico D20:
     *
     * - `ventas.origen_type/origen_id` → este pedido (D20).
     * - Registra CuponUso + uso_actual del cupón (D19 — mostrador no lo hacía).
     * - Acredita los puntos GANADOS del cliente (D19).
     * - Migra los opcionales a venta_detalle_opcionales (D19).
     *
     * Caja (regla derivada de D19): ventas.caja_id es NOT NULL y la numeración
     * es por caja. Pedidos de tienda/API sin caja: pasar `$cajaId` (la caja
     * activa de quien convierte / la de la vuelta); sin caja el pedido queda
     * "por facturar" (excepción clara, no explota nada).
     */
    public function convertirEnVenta(PedidoDelivery $pedido, ?array $opcionesFiscales = null, ?int $cajaId = null): Venta
    {
        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_FACTURADO || $pedido->venta_id) {
            throw new Exception('El pedido ya fue convertido en venta');
        }

        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_CANCELADO) {
            throw new Exception('No se puede convertir un pedido cancelado');
        }

        if ($pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR) {
            throw new Exception('Confirmar el pedido antes de convertirlo en venta');
        }

        if ($cajaId !== null && ! $pedido->caja_id) {
            Caja::findOrFail($cajaId);
            $pedido->update(['caja_id' => $cajaId]);
            $pedido->refresh();
        }

        if (! $pedido->caja_id) {
            throw new Exception('El pedido no tiene caja asignada: queda pendiente de facturar. Convertirlo desde una caja abierta.');
        }

        $this->guardConversionConPagosSuficientes($pedido);

        $venta = DB::connection('pymes_tenant')->transaction(function () use ($pedido) {
            $this->materializarPagosPlanificados($pedido);

            $pedido->load(['detalles.opcionales', 'detalles.promocionesAplicadas', 'pagos']);

            $datosVenta = [
                'sucursal_id' => $pedido->sucursal_id,
                'cliente_id' => $pedido->cliente_id,
                'caja_id' => $pedido->caja_id,
                'canal_venta_id' => $pedido->canal_venta_id,
                'forma_venta_id' => $pedido->forma_venta_id,
                'lista_precio_id' => $pedido->lista_precio_id,
                'usuario_id' => $pedido->usuario_id ?: ((int) auth()->id() ?: null),
                'numero' => null,
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
                'es_invitacion_total' => (bool) $pedido->es_invitacion_total,
                'invitacion_motivo' => $pedido->invitacion_motivo,
                'invitado_por_usuario_id' => $pedido->invitado_por_usuario_id,
                'invitado_at' => $pedido->invitado_at,
                'total_invitado' => (float) $pedido->total_invitado,
            ];

            $detalles = $pedido->detalles->map(fn ($d) => $this->mapearDetalleAArrayVenta($d))->all();

            $venta = $this->ventaService->crearVenta(
                data: $datosVenta,
                detalles: $detalles,
                opciones: ['stock_ya_descontado' => true],
            );

            $venta->update([
                'puntos_canjeados_pago' => $pedido->puntos_canjeados_pago,
                'puntos_canjeados_articulos' => $pedido->puntos_canjeados_articulos,
                // Origen polimórfico (D20): la venta sabe de qué pedido nació.
                'origen_type' => $pedido->getMorphClass(),
                'origen_id' => $pedido->id,
            ]);

            $this->migrarPromocionesAVenta($pedido, $venta);
            $this->reasignarMovimientosStockAVenta($pedido, $venta);
            $this->migrarPagosAVenta($pedido, $venta);
            $this->procesarCanjesPuntos($pedido, $venta);
            $this->registrarUsoCupon($pedido, $venta);

            $pedido->update([
                'estado_pedido' => PedidoDelivery::ESTADO_FACTURADO,
                'venta_id' => $venta->id,
                'convertido_at' => now(),
            ]);

            event(new PedidoDeliveryConvertidoEnVenta(
                pedidoId: $pedido->id,
                ventaId: $venta->id,
                usuarioId: (int) auth()->id() ?: $pedido->usuario_id,
            ));
            $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_CONVERTIDO_VENTA);

            return $venta;
        });

        // Puntos GANADOS (D19): se acreditan POST-commit (igual que la venta
        // directa: los puntos son secundarios y no deben tumbar la conversión).
        $this->acreditarPuntosGanados($pedido, $venta);

        Log::info('Pedido delivery convertido en venta', [
            'pedido_id' => $pedido->id,
            'venta_id' => $venta->id,
        ]);

        return $venta;
    }

    // ==================== NUMERACION ====================

    /**
     * Próximo número correlativo de DELIVERY de la sucursal (contador propio,
     * separado del de mostrador). Lock pesimista.
     */
    public function siguienteNumero(int $sucursalId): int
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($sucursalId) {
            $actual = DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->lockForUpdate()
                ->value('pedido_delivery_ultimo_numero');

            $siguiente = ((int) ($actual ?? 0)) + 1;

            DB::connection('pymes_tenant')
                ->table('sucursales')
                ->where('id', $sucursalId)
                ->update(['pedido_delivery_ultimo_numero' => $siguiente]);

            return $siguiente;
        });
    }

    public function resetearNumeracion(int $sucursalId, int $usuarioId): void
    {
        DB::connection('pymes_tenant')
            ->table('sucursales')
            ->where('id', $sucursalId)
            ->update(['pedido_delivery_ultimo_numero' => 0]);

        Log::info('Numeración pedidos delivery reseteada', [
            'sucursal_id' => $sucursalId,
            'usuario_id' => $usuarioId,
        ]);
    }

    // ==================== COMANDA / IMPRESION ====================

    public const ALCANCE_COMANDA_TODOS = 'todos';

    public const ALCANCE_COMANDA_NUEVOS = 'nuevos';

    /**
     * Comanda el pedido (o solo items nuevos). Paridad con mostrador.
     *
     * @return array{escpos: string, html: string, tipo_documento: 'comanda', pedido_id: int}
     */
    public function comandarPedido(PedidoDelivery $pedido, string $alcance = self::ALCANCE_COMANDA_TODOS): array
    {
        if (! in_array($alcance, [self::ALCANCE_COMANDA_TODOS, self::ALCANCE_COMANDA_NUEVOS], true)) {
            throw new Exception("Alcance de comanda inválido: {$alcance}");
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($pedido, $alcance) {
            $pedido->loadMissing('detalles');

            $detalles = $alcance === self::ALCANCE_COMANDA_NUEVOS
                ? $pedido->detalles->whereNull('comandado_at')
                : $pedido->detalles;

            if ($detalles->isEmpty()) {
                throw new Exception($alcance === self::ALCANCE_COMANDA_NUEVOS
                    ? 'No hay items nuevos para comandar'
                    : 'El pedido no tiene detalles');
            }

            $detalleIds = $detalles->pluck('id')->all();
            $this->marcarDetallesComoComandados($pedido, $detalleIds);
            $this->transicionarTrasComanda($pedido);

            $pedido->load('detalles');

            $esParcial = $alcance === self::ALCANCE_COMANDA_NUEVOS;
            $plantillas = app(\App\Services\Impresion\PlantillasComanda::class);

            Log::info('Pedido delivery comandado', [
                'pedido_id' => $pedido->id,
                'alcance' => $alcance,
                'detalle_ids' => $detalleIds,
            ]);

            return [
                'tipo_documento' => 'comanda',
                'pedido_id' => $pedido->id,
                'escpos' => $plantillas->generarComandaESCPOS($pedido, $detalleIds, $esParcial),
                'html' => $plantillas->generarComandaHTML($pedido, $detalleIds, $esParcial),
            ];
        });
    }

    /**
     * @return array{escpos: string, html: string, tipo_documento: 'comanda', pedido_id: int}
     */
    public function imprimirComanda(PedidoDelivery $pedido): array
    {
        return $this->comandarPedido($pedido, self::ALCANCE_COMANDA_TODOS);
    }

    /**
     * @return array{escpos: string, html: string, tipo_documento: 'precuenta', pedido_id: int}
     */
    public function imprimirPrecuenta(PedidoDelivery $pedido): array
    {
        $plantillas = app(\App\Services\Impresion\PlantillasComanda::class);

        return [
            'tipo_documento' => 'precuenta',
            'pedido_id' => $pedido->id,
            'escpos' => $plantillas->generarPrecuentaESCPOS($pedido),
            'html' => $plantillas->generarPrecuentaHTML($pedido),
        ];
    }

    // ==================== KANBAN ====================

    /**
     * Reordena una columna del Kanban (paridad mostrador; incluye en_camino).
     *
     * @param  array<int>  $idsOrdenados
     */
    public function reordenarColumna(int $sucursalId, ?int $cajaId, string $estado, array $idsOrdenados): void
    {
        if (! in_array($estado, [
            PedidoDelivery::ESTADO_CONFIRMADO,
            PedidoDelivery::ESTADO_EN_PREPARACION,
            PedidoDelivery::ESTADO_LISTO,
            PedidoDelivery::ESTADO_EN_CAMINO,
            PedidoDelivery::ESTADO_ENTREGADO,
        ], true)) {
            throw new \InvalidArgumentException("Estado '{$estado}' no es del Kanban");
        }

        $idsOrdenados = array_values(array_unique(array_filter(array_map('intval', $idsOrdenados))));
        if (empty($idsOrdenados)) {
            return;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($sucursalId, $cajaId, $estado, $idsOrdenados) {
            $query = PedidoDelivery::where('sucursal_id', $sucursalId)
                ->where('estado_pedido', $estado)
                ->whereIn('id', $idsOrdenados);
            if ($cajaId !== null) {
                $query->where('caja_id', $cajaId);
            }
            $idsValidos = $query->pluck('id')->map(fn ($i) => (int) $i)->all();
            if (empty($idsValidos)) {
                return;
            }

            $set = array_flip($idsValidos);
            $idsOrdenadosFinales = array_values(array_filter($idsOrdenados, fn ($id) => isset($set[$id])));
            if (empty($idsOrdenadosFinales)) {
                return;
            }

            $maxOrden = (int) PedidoDelivery::where('sucursal_id', $sucursalId)
                ->where('estado_pedido', $estado)
                ->when($cajaId !== null, fn ($q) => $q->where('caja_id', $cajaId))
                ->max('orden_kanban');

            $base = $maxOrden + count($idsOrdenadosFinales);
            foreach ($idsOrdenadosFinales as $i => $id) {
                PedidoDelivery::where('id', $id)->update(['orden_kanban' => $base - $i]);
            }
        });
    }

    // ==================== INTERNOS: RENGLON ENVIO (D17) ====================

    /**
     * Sincroniza el renglón-concepto "Costo de envío" con `costo_envio` del
     * encabezado (D17) y ajusta los totales del pedido por el DELTA:
     *
     * - costo_envio > 0 (delivery): crea o actualiza el renglón (es_concepto +
     *   es_costo_envio, IVA 21% incluido).
     * - costo_envio = 0 o take-away: elimina el renglón si existía.
     *
     * El renglón NO participa de descuentos/cupones/promos/puntos: se suma
     * aparte de la cascada. Sin él, `calcularDetallesIva` no lo vería y el
     * comprobante daría ImpTotal ≠ ImpNeto+ImpIVA (rechazo de ARCA).
     */
    protected function gestionarRenglonCostoEnvio(PedidoDelivery $pedido): void
    {
        $renglon = $pedido->detalles()->where('es_costo_envio', true)->first();
        $montoAnterior = $renglon ? (float) $renglon->total : 0.0;
        $montoNuevo = $pedido->tipo === PedidoDelivery::TIPO_DELIVERY
            ? round((float) $pedido->costo_envio, 2)
            : 0.0;

        if (abs($montoNuevo - $montoAnterior) < 0.005 && ($montoNuevo > 0) === (bool) $renglon) {
            return; // sin cambios
        }

        $ivaAnterior = $renglon ? (float) $renglon->iva_monto : 0.0;

        if ($montoNuevo <= 0.005) {
            $renglon?->delete();
            $deltaMonto = -$montoAnterior;
            $deltaIva = -$ivaAnterior;
        } else {
            $precioSinIva = round($montoNuevo / 1.21, 2);
            $ivaMonto = round($montoNuevo - $precioSinIva, 2);
            $tipoIva21 = TipoIva::where('porcentaje', 21)->value('id');
            $config = $this->envioService->configDelivery(Sucursal::findOrFail($pedido->sucursal_id));

            $atributos = [
                'es_concepto' => true,
                'es_costo_envio' => true,
                'concepto_descripcion' => self::DESCRIPCION_RENGLON_ENVIO,
                'concepto_categoria_id' => $config['concepto_categoria_envio_id'] ?? null,
                'tipo_iva_id' => $tipoIva21,
                'cantidad' => 1,
                'precio_unitario' => $montoNuevo,
                'precio_sin_iva' => $precioSinIva,
                'iva_porcentaje' => 21,
                'iva_monto' => $ivaMonto,
                'subtotal' => $montoNuevo,
                'total' => $montoNuevo,
            ];

            if ($renglon) {
                $renglon->update($atributos);
            } else {
                PedidoDeliveryDetalle::create(array_merge($atributos, [
                    'pedido_delivery_id' => $pedido->id,
                ]));
            }

            $deltaMonto = $montoNuevo - $montoAnterior;
            $deltaIva = $ivaMonto - $ivaAnterior;
        }

        // Ajustar totales por el delta. El envío no recibe descuentos, así que
        // solo mueve subtotal (bruto), iva, total y total_final.
        $pedido->update([
            'subtotal' => round((float) $pedido->subtotal + $deltaMonto, 2),
            'iva' => round((float) $pedido->iva + $deltaIva, 2),
            'total' => round((float) $pedido->total + $deltaMonto, 2),
            'total_final' => round((float) $pedido->total_final + $deltaMonto, 2),
        ]);
    }

    // ==================== INTERNOS: DETALLES / STOCK ====================

    protected function crearDetalle(PedidoDelivery $pedido, array $detalle): PedidoDeliveryDetalle
    {
        $pdDetalle = PedidoDeliveryDetalle::create([
            'pedido_delivery_id' => $pedido->id,
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
            'es_invitacion' => (bool) ($detalle['es_invitacion'] ?? false),
            'invitacion_motivo' => $detalle['invitacion_motivo'] ?? null,
            'invitado_por_usuario_id' => $detalle['invitado_por_usuario_id'] ?? null,
            'invitado_at' => $detalle['invitado_at'] ?? null,
            'monto_invitado' => $detalle['monto_invitado'] ?? 0,
            'precio_unitario_original' => $detalle['precio_unitario_original'] ?? null,
        ]);

        if (! empty($detalle['_promociones_item'])) {
            $this->guardarPromocionesDetalle($pdDetalle, $detalle['_promociones_item']);
        }

        // Opcionales del renglón (formato NuevaVenta/NuevoPedido:
        // [{grupo_id, grupo_nombre, selecciones:[{opcional_id, nombre, cantidad, precio_extra}]}]).
        if (! empty($detalle['opcionales'])) {
            $this->guardarOpcionalesDetalle($pdDetalle, $detalle['opcionales']);
        }

        return $pdDetalle;
    }

    protected function guardarOpcionalesDetalle(PedidoDeliveryDetalle $detalle, array $opcionales): void
    {
        foreach ($opcionales as $grupo) {
            foreach ($grupo['selecciones'] ?? [] as $sel) {
                DB::connection('pymes_tenant')->table('pedido_delivery_detalle_opcionales')->insert([
                    'pedido_delivery_detalle_id' => $detalle->id,
                    'grupo_opcional_id' => $grupo['grupo_id'],
                    'opcional_id' => $sel['opcional_id'],
                    'nombre_grupo' => $grupo['grupo_nombre'],
                    'nombre_opcional' => $sel['nombre'],
                    'cantidad' => $sel['cantidad'] ?? 1,
                    'precio_extra' => $sel['precio_extra'] ?? 0,
                    'subtotal_extra' => ($sel['precio_extra'] ?? 0) * ($sel['cantidad'] ?? 1),
                    'created_at' => now(),
                ]);
            }
        }
    }

    protected function guardarPromocionesDetalle(PedidoDeliveryDetalle $detalle, array $promocionesItem): void
    {
        $promocionesComunes = $promocionesItem['promociones_comunes'] ?? [];
        foreach ($promocionesComunes as $promo) {
            if (is_string($promo)) {
                continue;
            }
            DB::connection('pymes_tenant')->table('pedido_delivery_detalle_promociones')->insert([
                'pedido_delivery_detalle_id' => $detalle->id,
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
                DB::connection('pymes_tenant')->table('pedido_delivery_detalle_promociones')->insert([
                    'pedido_delivery_detalle_id' => $detalle->id,
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

            DB::connection('pymes_tenant')->table('pedido_delivery_detalle_promociones')->insert([
                'pedido_delivery_detalle_id' => $detalle->id,
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

    protected function descontarStockPorPedido(PedidoDelivery $pedido): void
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

            $usuarioId = $pedido->usuario_id ?: ((int) auth()->id() ?: 0);

            if ($modoStock === 'receta') {
                $receta = $articulo->resolverReceta($pedido->sucursal_id);
                if ($receta) {
                    $this->descontarStockPorReceta(
                        $receta, (float) $detalle->cantidad, $pedido,
                        $detalle->id, "Pedido delivery #{$pedido->id} - Receta {$articulo->nombre}",
                        $permitirNegativo
                    );
                }

                continue;
            }

            $stock = Stock::where('sucursal_id', $pedido->sucursal_id)
                ->where('articulo_id', $detalle->articulo_id)
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->disminuir((float) $detalle->cantidad, $permitirNegativo);

            MovimientoStock::crearMovimientoPedidoDelivery(
                articuloId: $detalle->articulo_id,
                sucursalId: $pedido->sucursal_id,
                cantidad: (float) $detalle->cantidad,
                pedidoId: $pedido->id,
                pedidoDetalleId: $detalle->id,
                concepto: "Pedido delivery #{$pedido->id} - {$articulo->nombre}",
                usuarioId: $usuarioId,
            );
        }
    }

    protected function descontarStockPorReceta(
        Receta $receta, float $cantidadVendida, PedidoDelivery $pedido,
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

            MovimientoStock::crearMovimientoPedidoDelivery(
                articuloId: $ingrediente->articulo_id,
                sucursalId: $pedido->sucursal_id,
                cantidad: $cantidad,
                pedidoId: $pedido->id,
                pedidoDetalleId: $pedidoDetalleId,
                concepto: $conceptoBase,
                usuarioId: $pedido->usuario_id ?: ((int) auth()->id() ?: 0),
            );
        }
    }

    protected function revertirStockPorPedido(PedidoDelivery $pedido, string $motivo): void
    {
        $movimientos = MovimientoStock::where('documento_tipo', MovimientoStock::DOC_PEDIDO_DELIVERY_DETALLE)
            ->whereIn('documento_id', $pedido->detalles->pluck('id'))
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        foreach ($movimientos as $mov) {
            $stock = Stock::where('sucursal_id', $mov->sucursal_id)
                ->where('articulo_id', $mov->articulo_id)
                ->first();
            if ($stock && $mov->salida > 0) {
                $stock->increment('cantidad', $mov->salida);
            }

            MovimientoStock::crearContraasiento(
                movimientoOriginal: $mov,
                motivo: $motivo,
                usuarioId: (int) auth()->id() ?: ($pedido->usuario_id ?: 0),
            );
        }
    }

    // ==================== INTERNOS: PAGOS / TOTALES ====================

    protected function crearMovimientoCajaIngreso(PedidoDelivery $pedido, array $datosPago, FormaPago $formaPago): int
    {
        $caja = Caja::findOrFail($pedido->caja_id);
        $usuarioId = (int) auth()->id() ?: ($pedido->usuario_id ?: 0);

        $movimiento = MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => MovimientoCaja::TIPO_INGRESO,
            'concepto' => "Pedido delivery #{$pedido->id} - {$formaPago->nombre}",
            'monto' => $datosPago['monto_final'],
            'usuario_id' => $usuarioId,
            'referencia_tipo' => MovimientoCaja::REF_PEDIDO_DELIVERY,
            'referencia_id' => $pedido->id,
            'moneda_id' => $datosPago['moneda_id'] ?? null,
            'tipo_cambio_id' => $datosPago['tipo_cambio_id'] ?? null,
            'tipo_cambio_tasa' => $datosPago['tipo_cambio_tasa'] ?? null,
            'monto_moneda_original' => $datosPago['monto_moneda_original'] ?? null,
        ]);

        $caja->aumentarSaldo((float) $datosPago['monto_final']);

        return $movimiento->id;
    }

    protected function recalcularTotales(PedidoDelivery $pedido): void
    {
        $pagos = $pedido->pagos()
            ->whereIn('estado', [
                PedidoDeliveryPago::ESTADO_ACTIVO,
                PedidoDeliveryPago::ESTADO_PLANIFICADO,
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

    protected function guardarPromocionesPedido(PedidoDelivery $pedido, array $datos): void
    {
        DB::connection('pymes_tenant')
            ->table('pedido_delivery_promociones')
            ->where('pedido_delivery_id', $pedido->id)
            ->delete();

        $promocionesComunes = $datos['_promociones_comunes'] ?? [];
        $promocionesEspeciales = $datos['_promociones_especiales'] ?? [];

        foreach ($promocionesComunes as $promo) {
            DB::connection('pymes_tenant')->table('pedido_delivery_promociones')->insert([
                'pedido_delivery_id' => $pedido->id,
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

            DB::connection('pymes_tenant')->table('pedido_delivery_promociones')->insert([
                'pedido_delivery_id' => $pedido->id,
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

    protected function recalcularEstadoPago(PedidoDelivery $pedido): void
    {
        $pagado = (float) $pedido->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)
            ->sum('monto_final');

        $total = (float) $pedido->total_final;
        $anterior = $pedido->estado_pago;

        $nuevo = match (true) {
            $total <= 0.005 => PedidoDelivery::ESTADO_PAGO_PAGADO,
            $pagado <= 0 => PedidoDelivery::ESTADO_PAGO_PENDIENTE,
            $pagado + 0.005 >= $total => PedidoDelivery::ESTADO_PAGO_PAGADO,
            default => PedidoDelivery::ESTADO_PAGO_PARCIAL,
        };

        if ($nuevo !== $anterior) {
            $pedido->update(['estado_pago' => $nuevo]);

            event(new PedidoDeliveryEstadoPagoCambiado(
                pedidoId: $pedido->id,
                estadoAnterior: $anterior,
                estadoNuevo: $nuevo,
            ));
        }

        $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_PAGO_CAMBIADO);
    }

    protected function maybeImprimirComandaAutomatica(PedidoDelivery $pedido): void
    {
        $sucursal = Sucursal::find($pedido->sucursal_id);
        if (! $sucursal || ! $sucursal->imprime_comanda_automatico) {
            return;
        }

        $this->comandarPedido($pedido, self::ALCANCE_COMANDA_TODOS);
    }

    protected function marcarDetallesComoComandados(PedidoDelivery $pedido, array $detalleIds): int
    {
        if (empty($detalleIds)) {
            return 0;
        }

        return PedidoDeliveryDetalle::query()
            ->where('pedido_delivery_id', $pedido->id)
            ->whereIn('id', $detalleIds)
            ->update(['comandado_at' => now()]);
    }

    protected function transicionarTrasComanda(PedidoDelivery $pedido): void
    {
        $estado = $pedido->estado_pedido;

        if ($estado === PedidoDelivery::ESTADO_CONFIRMADO) {
            $this->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_PREPARACION);

            return;
        }

        if (in_array($estado, [PedidoDelivery::ESTADO_LISTO, PedidoDelivery::ESTADO_ENTREGADO], true)) {
            $this->forzarEstado($pedido, PedidoDelivery::ESTADO_EN_PREPARACION, 're-comandado');
        }
    }

    protected function forzarEstado(PedidoDelivery $pedido, string $nuevoEstado, ?string $motivo = null): void
    {
        $anterior = $pedido->estado_pedido;

        if ($anterior === $nuevoEstado) {
            return;
        }

        $timestampField = match ($nuevoEstado) {
            PedidoDelivery::ESTADO_CONFIRMADO => 'confirmado_at',
            PedidoDelivery::ESTADO_EN_PREPARACION => 'en_preparacion_at',
            PedidoDelivery::ESTADO_LISTO => 'listo_at',
            PedidoDelivery::ESTADO_EN_CAMINO => 'en_camino_at',
            PedidoDelivery::ESTADO_ENTREGADO => 'entregado_at',
            PedidoDelivery::ESTADO_CANCELADO => 'cancelado_at',
            default => null,
        };

        $update = ['estado_pedido' => $nuevoEstado];
        if ($timestampField) {
            $update[$timestampField] = now();
        }

        $pedido->update($update);

        Log::info('Pedido delivery estado forzado (bypass de transiciones)', [
            'pedido_id' => $pedido->id,
            'anterior' => $anterior,
            'nuevo' => $nuevoEstado,
            'motivo' => $motivo,
        ]);

        $this->dispatchBroadcast($pedido, PedidoDeliveryBroadcast::TIPO_ESTADO_CAMBIADO);
    }

    protected function pedidoTieneSaldoEnCC(PedidoDelivery $pedido): bool
    {
        $pagado = (float) $pedido->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)
            ->sum('monto_final');

        return $pagado + 0.005 < (float) $pedido->total_final;
    }

    protected function guardConversionConPagosSuficientes(PedidoDelivery $pedido): void
    {
        $total = (float) $pedido->total_final;
        if ($total <= 0.005) {
            return;
        }

        $cubierto = (float) $pedido->pagos()
            ->whereIn('estado', [
                PedidoDeliveryPago::ESTADO_ACTIVO,
                PedidoDeliveryPago::ESTADO_PLANIFICADO,
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
     * Confirma los pagos planificados restantes al convertir. Los cobros
     * contra entrega en efectivo NUNCA se materializan acá (D13): esos se
     * confirmaron con destino_fondo en la vuelta del repartidor, ANTES de
     * marcar entregado — a esta altura ya son activos.
     */
    protected function materializarPagosPlanificados(PedidoDelivery $pedido): void
    {
        $planificados = $pedido->pagos()
            ->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)
            ->get();

        foreach ($planificados as $pago) {
            $this->confirmarPagoPlanificado($pago);
        }
    }

    // ==================== INTERNOS: CONVERSION ====================

    protected function mapearDetalleAArrayVenta(PedidoDeliveryDetalle $d): array
    {
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

        // Opcionales del pedido → formato que VentaService::guardarOpcionalesDetalle
        // espera (D19: mostrador NO los migraba y venta_detalle_opcionales quedaba vacía).
        $opcionales = ($d->opcionales ?? collect())
            ->groupBy('grupo_opcional_id')
            ->map(fn ($grupo) => [
                'grupo_id' => $grupo->first()->grupo_opcional_id,
                'grupo_nombre' => $grupo->first()->nombre_grupo,
                'selecciones' => $grupo->map(fn ($o) => [
                    'opcional_id' => $o->opcional_id,
                    'nombre' => $o->nombre_opcional,
                    'cantidad' => (float) $o->cantidad,
                    'precio_extra' => (float) $o->precio_extra,
                ])->values()->all(),
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
            'opcionales' => $opcionales,
            '_promociones_item' => [
                'promociones_comunes' => $promocionesComunes,
                'promociones_especiales' => $promocionesEspeciales,
            ],
            'es_invitacion' => (bool) $d->es_invitacion,
            'invitacion_motivo' => $d->invitacion_motivo,
            'invitado_por_usuario_id' => $d->invitado_por_usuario_id,
            'invitado_at' => $d->invitado_at,
            'monto_invitado' => (float) $d->monto_invitado,
            'precio_unitario_original' => $d->precio_unitario_original !== null
                ? (float) $d->precio_unitario_original
                : null,
            '_pedido_detalle_id' => $d->id,
        ];
    }

    protected function reasignarMovimientosStockAVenta(PedidoDelivery $pedido, Venta $venta): void
    {
        MovimientoStock::where('documento_tipo', MovimientoStock::DOC_PEDIDO_DELIVERY_DETALLE)
            ->whereIn('documento_id', $pedido->detalles->pluck('id'))
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->where('tipo', MovimientoStock::TIPO_PEDIDO_DELIVERY)
            ->update([
                'tipo' => MovimientoStock::TIPO_VENTA,
                'venta_id' => $venta->id,
                'observaciones' => DB::raw("CONCAT(IFNULL(observaciones, ''), ' | Convertido en Venta #{$venta->id}')"),
            ]);
    }

    protected function migrarPagosAVenta(PedidoDelivery $pedido, Venta $venta): void
    {
        $pagos = $pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_ACTIVO)->get();

        $txsIntegracionPorFp = IntegracionPagoTransaccion::porCobrable($pedido->getMorphClass(), $pedido->id)
            ->confirmadas()
            ->get()
            ->keyBy('forma_pago_id');

        foreach ($pagos as $pago) {
            $ventaPago = VentaPago::create([
                'integracion_pago_transaccion_id' => $txsIntegracionPorFp->get($pago->forma_pago_id)?->id,
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
                'observaciones' => trim(($pago->observaciones ?? '')." | Originado en pedido delivery #{$pedido->id}".($pago->destino_fondo ? ' (cobrado al fondo del repartidor)' : '')),
                'es_cuenta_corriente' => $pago->es_cuenta_corriente,
                'es_pago_puntos' => $pago->es_pago_puntos,
                'puntos_usados' => $pago->puntos_usados,
                'afecta_caja' => $pago->afecta_caja,
                'estado' => 'activo',
                'movimiento_caja_id' => $pago->movimiento_caja_id,
                'creado_por_usuario_id' => $pago->creado_por_usuario_id ?: ($pedido->usuario_id ?: 0),
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

    protected function migrarPromocionesAVenta(PedidoDelivery $pedido, Venta $venta): void
    {
        $promos = DB::connection('pymes_tenant')
            ->table('pedido_delivery_promociones')
            ->where('pedido_delivery_id', $pedido->id)
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
     * Registra el USO del cupón en la conversión (D19: mostrador copiaba los
     * montos pero nunca creaba CuponUso ni incrementaba uso_actual, dejando
     * el control de usos máximos agujereado).
     *
     * NO se revalida vigencia acá: el uso ya OCURRIÓ cuando el pedido aplicó
     * el descuento — si el cupón venció entre el pedido y la conversión, el
     * descuento igual se otorgó y debe quedar auditado.
     */
    protected function registrarUsoCupon(PedidoDelivery $pedido, Venta $venta): void
    {
        if (! $pedido->cupon_id || (float) $pedido->monto_cupon <= 0) {
            return;
        }

        $cupon = Cupon::find($pedido->cupon_id);
        if (! $cupon) {
            return;
        }

        $yaRegistrado = CuponUso::where('cupon_id', $cupon->id)
            ->where('venta_id', $venta->id)
            ->exists();
        if ($yaRegistrado) {
            return;
        }

        CuponUso::create([
            'cupon_id' => $cupon->id,
            'venta_id' => $venta->id,
            'cliente_id' => $pedido->cliente_id,
            'sucursal_id' => $pedido->sucursal_id,
            'monto_descontado' => (float) $pedido->monto_cupon,
            'fecha' => now(),
            'usuario_id' => (int) auth()->id() ?: ($pedido->usuario_id ?: 0),
            'created_at' => now(),
        ]);

        $cupon->increment('uso_actual');

        Log::info('Cupón registrado al convertir pedido delivery', [
            'cupon_id' => $cupon->id,
            'pedido_id' => $pedido->id,
            'venta_id' => $venta->id,
            'monto_descontado' => (float) $pedido->monto_cupon,
        ]);
    }

    /**
     * Acredita los puntos GANADOS al cliente (D19: la conversión de mostrador
     * nunca los acreditaba — solo el Livewire de venta directa). Post-commit,
     * best-effort: un fallo acá no revierte la conversión.
     */
    protected function acreditarPuntosGanados(PedidoDelivery $pedido, Venta $venta): void
    {
        if (! $pedido->cliente_id) {
            return;
        }

        try {
            $pagos = $venta->pagos()
                ->where('estado', 'activo')
                ->get()
                ->map(function ($pago) use ($pedido) {
                    $multiplicador = 1.0;
                    $fp = FormaPago::find($pago->forma_pago_id);
                    if ($fp && $fp->multiplicador_puntos !== null) {
                        $multiplicador = (float) $fp->multiplicador_puntos;
                    }

                    $fpSucursal = \App\Models\FormaPagoSucursal::where('forma_pago_id', $pago->forma_pago_id)
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->first();
                    if ($fpSucursal && $fpSucursal->multiplicador_puntos !== null) {
                        $multiplicador = (float) $fpSucursal->multiplicador_puntos;
                    }

                    return [
                        'monto_final' => (float) $pago->monto_final,
                        'es_pago_puntos' => (bool) $pago->es_pago_puntos,
                        'es_cuenta_corriente' => (bool) $pago->es_cuenta_corriente,
                        'multiplicador_puntos' => $multiplicador,
                    ];
                });

            app(\App\Services\PuntosService::class)->acumularPuntosPorVenta(
                $venta,
                $pagos,
                (int) auth()->id() ?: ($pedido->usuario_id ?: 0),
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudieron acreditar puntos ganados al convertir pedido delivery', [
                'pedido_id' => $pedido->id,
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Canjes de puntos (pagos con puntos + artículos canjeados) — espejo
     * exacto de mostrador.
     */
    protected function procesarCanjesPuntos(PedidoDelivery $pedido, Venta $venta): void
    {
        if (! $pedido->cliente_id) {
            return;
        }

        $puntosService = app(\App\Services\PuntosService::class);
        $usuarioId = (int) auth()->id() ?: ($pedido->usuario_id ?: 0);
        $algoCanjeado = false;

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
                $algoCanjeado = true;
            } catch (\Throwable $e) {
                Log::error('No se pudo registrar canje de puntos como descuento al convertir pedido delivery', [
                    'pedido_id' => $pedido->id,
                    'venta_id' => $venta->id,
                    'venta_pago_id' => $vp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $detallesPuntos = $pedido->detalles()
            ->where('pagado_con_puntos', true)
            ->where('puntos_usados', '>', 0)
            ->get();

        foreach ($detallesPuntos as $d) {
            if (! $d->articulo_id) {
                continue;
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
                $algoCanjeado = true;
            } catch (\Throwable $e) {
                Log::error('No se pudo registrar canje de artículo por puntos al convertir pedido delivery', [
                    'pedido_id' => $pedido->id,
                    'venta_id' => $venta->id,
                    'detalle_id' => $d->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($algoCanjeado) {
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

    // ==================== INTERNOS: VALIDACIONES / BROADCAST ====================

    protected function validarTipoContraSucursal(Sucursal $sucursal, string $tipo): void
    {
        if (! $sucursal->usa_delivery) {
            throw new Exception('La sucursal no tiene habilitados los pedidos delivery/take-away');
        }

        if ($tipo === PedidoDelivery::TIPO_TAKE_AWAY) {
            $config = $this->envioService->configDelivery($sucursal);
            if (! $config['takeaway_habilitado']) {
                throw new Exception('La sucursal no acepta pedidos take-away');
            }
        }
    }

    private function dispatchBroadcast(PedidoDelivery $pedido, string $tipo): void
    {
        try {
            $comercioId = app(\App\Services\TenantService::class)->getComercioId();
            if ($comercioId === null) {
                return;
            }
            broadcast(new PedidoDeliveryBroadcast(
                $comercioId,
                (int) $pedido->sucursal_id,
                (int) $pedido->id,
                $tipo,
            ))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('No se pudo broadcastear PedidoDeliveryBroadcast', [
                'pedido_id' => $pedido->id,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast PÚBLICO para el monitor llamador. SOLO take-away (RF-03): el
     * take-away `listo` se anuncia como "listo para retirar" con su número
     * display (secuencia compartida con mostrador); delivery no se canta.
     */
    private function dispatchLlamadorPublico(PedidoDelivery $pedido, string $estadoNuevo, string $estadoAnterior): void
    {
        if ($pedido->tipo !== PedidoDelivery::TIPO_TAKE_AWAY) {
            return;
        }

        $relevantes = [PedidoDelivery::ESTADO_EN_PREPARACION, PedidoDelivery::ESTADO_LISTO];

        if (! in_array($estadoNuevo, $relevantes, true) && ! in_array($estadoAnterior, $relevantes, true)) {
            return;
        }

        try {
            $suc = Sucursal::where('id', $pedido->sucursal_id)
                ->first(['usa_llamador', 'token_publico']);

            if (! $suc || ! $suc->usa_llamador || ! $suc->token_publico) {
                return;
            }

            broadcast(new \App\Events\Broadcasting\PedidoLlamadorPublicoBroadcast(
                $suc->token_publico,
                (int) $pedido->numero_visible,
                $pedido->nombreLlamador(),
                $estadoNuevo,
            ));
        } catch (\Throwable $e) {
            Log::warning('No se pudo broadcastear PedidoLlamadorPublicoBroadcast (delivery)', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
