<?php

namespace App\Services\Pedidos;

use App\Models\Cliente;
use App\Models\Consumidor;
use App\Models\ConsumidorComercio;
use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use App\Models\Tienda;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Alta de pedidos EXTERNOS (tienda/API pública) — RF-11/RF-12/D14/D11.
 *
 * Orquesta: bloqueos de API pública (horario, alcance, pedibilidad),
 * cotización server-side del carrito (CotizadorCarritoTienda — mismo motor
 * del sistema), envío (DeliveryEnvioService), y el alta vía
 * PedidoDeliveryService respetando la aceptación configurada (D14):
 * - `manual` (default): entra como BORRADOR con origen tienda/api ("por
 *   aceptar" en el panel) — sin número ni stock; los precios cotizados
 *   quedan snapshot en los renglones.
 * - `automatica`: entra CONFIRMADO directo y, si
 *   `imprimir_comanda_al_aceptar`, la comanda sale sola.
 *
 * Consumidor logueado (D11): el pedido SIEMPRE guarda consumidor_id +
 * snapshot de contacto; el cliente tenant solo se completa si existe mapping
 * (o se crea, si el comercio tiene `tienda_alta_cliente_automatica`).
 */
class PedidoTiendaService
{
    public function __construct(
        protected PedidoDeliveryService $pedidoService,
        protected DeliveryEnvioService $envioService,
        protected CotizadorCarritoTienda $cotizador,
    ) {}

    /**
     * Crea un pedido externo. `$payload` (ya validado por el FormRequest):
     * tipo, items[], cliente{nombre,telefono,email}, direccion{direccion,
     * referencia,latitud,longitud,localidad_id}, cupon_codigo?, observaciones?,
     * origen ('tienda'|'api'), origen_referencia?.
     */
    public function crearPedidoExterno(
        Sucursal $sucursal,
        array $payload,
        ?Tienda $tienda = null,
        ?Consumidor $consumidor = null,
    ): PedidoDelivery {
        $config = $this->envioService->configDelivery($sucursal);
        $tipo = $payload['tipo'] ?? PedidoDelivery::TIPO_DELIVERY;

        // Bloqueos de API pública (el panel advierte; acá se bloquea).
        if (! $this->envioService->estaAbierto($sucursal)) {
            throw new Exception(__('La tienda está cerrada en este momento'));
        }

        if ($tipo === PedidoDelivery::TIPO_TAKE_AWAY && ! ($config['takeaway_habilitado'] ?? true)) {
            throw new Exception(__('El take-away está deshabilitado en esta sucursal'));
        }

        // Envío (RF-06): con georreferenciación, coordenadas obligatorias y
        // fuera de alcance BLOQUEA (la API pública nunca fuerza).
        $costoEnvio = 0.0;
        $cotizacionEnvio = null;
        if ($tipo === PedidoDelivery::TIPO_DELIVERY) {
            $lat = $payload['direccion']['latitud'] ?? null;
            $lng = $payload['direccion']['longitud'] ?? null;

            if (! empty($config['georreferenciar_pedidos'])) {
                if ($lat === null || $lng === null) {
                    throw new Exception(__('La dirección debe incluir coordenadas (latitud/longitud)'));
                }

                $cotizacionEnvio = $this->envioService->cotizar($sucursal, (float) $lat, (float) $lng);

                if ($cotizacionEnvio->esFueraDeAlcance()) {
                    throw new Exception(__('La dirección está fuera del alcance de entrega'));
                }

                $costoEnvio = (float) ($cotizacionEnvio->costo ?? 0);
            }
        }

        // Carrito por el MISMO motor (D12). Lanza con mensaje claro ante
        // artículos no pedibles (RF-16/RF-17). La FP declarada participa del
        // precio (promos/listas por FP + ajuste por FP), igual que en el panel.
        $clienteId = $this->resolverClienteId($sucursal, $consumidor);
        $formaPagoId = isset($payload['pago']['forma_pago_id'])
            ? (int) $payload['pago']['forma_pago_id']
            : null;
        $resultado = $this->cotizador->cotizar(
            $sucursal,
            $tipo,
            $payload['items'],
            $payload['cupon_codigo'] ?? null,
            $clienteId,
            $formaPagoId ?: null,
        );
        $ajusteFormaPago = $this->cotizador->ajusteFormaPagoMonto();

        // Canje de puntos (RF-T9, Fase 3): pago por el MÁXIMO sobre el total
        // de bienes + ajuste FP (SIN envío — los puntos nunca cubren el
        // envío, mismo alcance que la cotización). Saldo FRESCO del ledger;
        // el MovimientoPunto se crea recién al convertir (procesarCanjesPuntos).
        $canjePuntos = null;
        if (! empty($payload['usar_puntos']) && $clienteId) {
            $puntosTienda = app(PuntosTiendaService::class);
            $infoPuntos = $puntosTienda->info($sucursal, $clienteId);
            $canjePuntos = $puntosTienda->calcularCanjeMaximo(
                $infoPuntos,
                round((float) ($resultado['total_final'] ?? 0) + $ajusteFormaPago, 2),
            );
        }

        $aceptacionManual = ($config['aceptacion_pedidos_externos'] ?? 'manual') !== 'automatica';

        // Promesa de entrega elegida por el CONSUMIDOR (RF-15): franja (modo
        // franjas) o "lo antes posible". Validada contra la config — la API
        // pública no negocia.
        [$horaPactada, $loAntesPosible] = $this->resolverPromesa($sucursal, $config, $tipo, $payload, $aceptacionManual);

        $data = [
            'tipo' => $tipo,
            'origen' => $payload['origen'] ?? PedidoDelivery::ORIGEN_TIENDA,
            'origen_referencia' => $payload['origen_referencia'] ?? null,
            'consumidor_id' => $consumidor?->id,
            'sucursal_id' => $sucursal->id,
            'cliente_id' => $clienteId,
            'nombre_cliente_temporal' => $clienteId ? null : ($payload['cliente']['nombre'] ?? $consumidor?->nombre),
            'telefono_cliente_temporal' => $clienteId ? null : ($payload['cliente']['telefono'] ?? $consumidor?->telefono),
            'email_cliente_temporal' => $clienteId ? null : ($payload['cliente']['email'] ?? $consumidor?->email),
            'caja_id' => null, // pedidos externos no tienen caja (la aporta quien convierte)
            'canal_venta_id' => $resultado['canal_venta_id'],
            'forma_venta_id' => $resultado['forma_venta_id'],
            'lista_precio_id' => $resultado['lista_precio_id'],
            'usuario_id' => null,
            'fecha' => now(),
            'subtotal' => (float) ($resultado['subtotal'] ?? 0),
            'iva' => (float) ($resultado['iva_total'] ?? 0),
            'descuento' => (float) ($resultado['descuento_total'] ?? 0),
            'total' => (float) ($resultado['total_final'] ?? 0),
            // Ajuste por la FP declarada (descuento/recargo): mismo cálculo que
            // el panel (WithAjusteFormaPago); el envío queda fuera de la base
            // por construcción (la cotización del carrito no lo incluye, D17).
            'ajuste_forma_pago' => $ajusteFormaPago,
            'total_final' => round((float) ($resultado['total_final'] ?? 0) + $ajusteFormaPago, 2),
            'cupon_id' => $resultado['cupon']['id'] ?? null,
            'cupon_codigo_snapshot' => $resultado['cupon']['codigo'] ?? null,
            'cupon_descripcion_snapshot' => $resultado['cupon']['descripcion'] ?? null,
            'monto_cupon' => (float) ($resultado['cupon']['descuento'] ?? 0),
            // Canje de puntos (RF-T9): cabecera espejo del panel; el pago
            // es_pago_puntos se registra después del alta.
            'puntos_usados' => $canjePuntos['usados'] ?? 0,
            'puntos_canjeados_pago' => $canjePuntos['usados'] ?? 0,
            'puntos_usados_monto' => $canjePuntos['monto'] ?? 0,
            '_promociones_comunes' => $resultado['promociones_comunes_aplicadas'] ?? [],
            '_promociones_especiales' => $resultado['promociones_especiales_aplicadas'] ?? [],
            'observaciones' => $payload['observaciones'] ?? null,
            'datos_fiscales_snapshot' => $payload['datos_fiscales'] ?? null,
            // Dirección (snapshot RF-04) + envío.
            'direccion_entrega' => $tipo === PedidoDelivery::TIPO_DELIVERY ? ($payload['direccion']['direccion'] ?? null) : null,
            'direccion_referencia' => $payload['direccion']['referencia'] ?? null,
            'localidad_entrega_id' => $payload['direccion']['localidad_id'] ?? null,
            'latitud' => $payload['direccion']['latitud'] ?? null,
            'longitud' => $payload['direccion']['longitud'] ?? null,
            'zona_id' => $cotizacionEnvio?->zona?->id,
            'costo_envio' => $costoEnvio,
            'costo_envio_manual' => false,
            'distancia_km' => $cotizacionEnvio?->distanciaKm,
            'hora_pactada_at' => $horaPactada,
            'lo_antes_posible' => $loAntesPosible,
            '_actualizar_direccion_cliente' => false, // el consumidor gestiona sus direcciones globales
        ];

        $detalles = $this->construirDetalles($resultado);

        $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: $aceptacionManual);

        // Pago con puntos (RF-T9): pago PLANIFICADO bajo la FP interna "Canje
        // Puntos" (solo_sistema) — la conversión a venta lo copia y ahí
        // procesarCanjesPuntos crea el MovimientoPunto real.
        $this->registrarPagoPuntos($pedido, $canjePuntos);

        // Pago declarado contra entrega/retiro (planificado, D14): "pago con
        // efectivo, con $X" queda en el pedido — el panel/vuelta lo confirma.
        // Con canje de puntos, la FP declarada cubre el RESTO del total.
        $this->registrarPagoDeclarado(
            $sucursal,
            $pedido,
            $payload['pago'] ?? null,
            $ajusteFormaPago,
            $this->cotizador->ajusteFormaPagoPorcentaje(),
            (float) ($canjePuntos['monto'] ?? 0),
        );

        // Aceptación automática (D14): comandar solo (marca los renglones y
        // transiciona a en_preparacion; la impresión física sigue el circuito
        // de comandas existente).
        if (! $aceptacionManual && ! empty($config['imprimir_comanda_al_aceptar'])) {
            try {
                $this->pedidoService->comandarPedido($pedido->fresh(['detalles']));
            } catch (Exception $e) {
                Log::warning('No se pudo comandar automáticamente el pedido externo', [
                    'pedido_id' => $pedido->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $pedido;
    }

    /**
     * Resuelve la promesa elegida por el consumidor → [hora_pactada_at, asap].
     *
     * - `entrega.franja` (solo modo franjas): debe ser una franja VIGENTE de
     *   franjasDisponibles — la API no acepta horarios inventados.
     * - `entrega.lo_antes_posible`: solo si la config lo ofrece.
     * - Sin elección: modo franjas exige elegir (o ASAP si está ofrecido);
     *   en automática/manual, con aceptación AUTOMÁTICA el pedido no puede
     *   quedar sin promesa (nadie la pacta después): automática la calcula
     *   crearPedido por distancia; manual queda ASAP si la config lo ofrece.
     *   Con aceptación MANUAL queda para el modal de aceptación (D14).
     */
    protected function resolverPromesa(Sucursal $sucursal, array $config, string $tipo, array $payload, bool $aceptacionManual): array
    {
        $modo = (string) ($config['modo_promesa'] ?? 'manual');
        $aceptaAsap = (bool) ($config['acepta_lo_antes_posible'] ?? true);
        $franjaElegida = $payload['entrega']['franja'] ?? null;
        $pidioAsap = (bool) ($payload['entrega']['lo_antes_posible'] ?? false);

        if ($franjaElegida !== null) {
            if ($modo !== 'franjas') {
                throw new Exception(__('Esta tienda no trabaja con franjas horarias'));
            }

            $elegida = \Illuminate\Support\Carbon::parse($franjaElegida);
            foreach ($this->envioService->franjasDisponibles($sucursal, $tipo) as $slot) {
                if ($slot->equalTo($elegida)) {
                    return [$slot, false];
                }
            }

            throw new Exception(__('La franja elegida ya no está disponible: consultá los horarios vigentes'));
        }

        if ($pidioAsap) {
            if (! $aceptaAsap) {
                throw new Exception(__('Esta tienda no ofrece entrega "lo antes posible": elegí un horario'));
            }

            return [null, true];
        }

        // Sin elección explícita.
        if ($modo === 'franjas') {
            if ($aceptaAsap) {
                return [null, true];
            }

            throw new Exception(__('Elegí un horario de entrega (franja)'));
        }

        // automática: crearPedido calcula por distancia (hora null acá).
        // manual + aceptación automática: ASAP honesto si la config lo ofrece
        // (nadie va a pactar hora después); con aceptación manual, el modal
        // de aceptación la define (D14).
        if ($modo === 'manual' && ! $aceptacionManual && $aceptaAsap) {
            return [null, true];
        }

        return [null, false];
    }

    /**
     * Registra el pago con PUNTOS como planificado bajo la FP interna
     * "Canje Puntos" (RF-T9). La conversión a venta copia este pago y
     * procesarCanjesPuntos crea el MovimientoPunto (descuenta saldo).
     */
    protected function registrarPagoPuntos(PedidoDelivery $pedido, ?array $canje): void
    {
        if (! $canje || ($canje['monto'] ?? 0) <= 0) {
            return;
        }

        $fpCanjeId = \App\Models\FormaPago::where('codigo', 'CANJE_PUNTOS')->value('id');
        if (! $fpCanjeId) {
            // Comercio sin la FP interna provisionada: el canje no puede
            // registrarse con paridad de reportes — se bloquea, no se inventa.
            throw new Exception(__('El canje de puntos no está disponible en esta tienda'));
        }

        $this->pedidoService->agregarPago($pedido, [
            'forma_pago_id' => $fpCanjeId,
            'monto_base' => $canje['monto'],
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $canje['monto'],
            'es_pago_puntos' => true,
            'puntos_usados' => $canje['usados'],
            'planificado' => true,
        ]);
    }

    /**
     * Registra el pago DECLARADO por el consumidor como planificado (nunca
     * cobra): FP pública de la sucursal + "¿con cuánto pagás?" para efectivo
     * (vuelto planificado, mismo patrón del panel). Sin `pago` es no-op.
     * `$montoPuntos`: lo ya cubierto con canje — la FP declarada paga el resto.
     */
    protected function registrarPagoDeclarado(
        Sucursal $sucursal,
        PedidoDelivery $pedido,
        ?array $pago,
        float $ajusteFormaPago = 0.0,
        float $ajustePorcentaje = 0.0,
        float $montoPuntos = 0.0,
    ): void {
        $formaPagoId = (int) ($pago['forma_pago_id'] ?? 0);
        if (! $formaPagoId) {
            return;
        }

        $formaPago = \App\Models\FormaPago::find($formaPagoId);

        // Regla única (la misma que valida la cotización — FormaPago).
        if (! $formaPago || ! $formaPago->esDeclarableEnTienda((int) $sucursal->id)) {
            throw new Exception(__('La forma de pago elegida no está disponible en esta tienda'));
        }

        // total_final ya incluye el ajuste por FP (y el envío, que queda fuera
        // de la base del ajuste). El pago se descompone igual que en el panel:
        // monto_base (bienes + envío) + monto_ajuste (ajuste FP) = monto_final.
        // Con canje de puntos, esta FP cubre el NETO (total − monto en puntos).
        $total = round((float) $pedido->total_final - $montoPuntos, 2);
        if ($total <= 0) {
            return; // los puntos cubrieron todo: no hay pago declarado
        }
        $pagaCon = isset($pago['paga_con']) ? round((float) $pago['paga_con'], 2) : null;

        $permiteVuelto = (bool) ($formaPago->conceptoPago?->permite_vuelto ?? false);
        if ($pagaCon !== null && ! $permiteVuelto) {
            $pagaCon = null; // "paga con" solo tiene sentido con efectivo
        }
        if ($pagaCon !== null && $pagaCon > 0 && $pagaCon < $total) {
            throw new Exception(__('El monto declarado no cubre el total del pedido'));
        }

        $this->pedidoService->agregarPago($pedido, [
            'forma_pago_id' => $formaPago->id,
            'monto_base' => round($total - $ajusteFormaPago, 2),
            'ajuste_porcentaje' => $ajustePorcentaje,
            'monto_ajuste' => $ajusteFormaPago,
            'monto_final' => $total,
            'monto_recibido' => $pagaCon && $pagaCon > 0 ? $pagaCon : null,
            'vuelto' => $pagaCon && $pagaCon > $total ? round($pagaCon - $total, 2) : 0,
            'planificado' => true,
        ]);
    }

    /**
     * D11: cliente tenant del consumidor para este comercio. Con mapping →
     * ese cliente; sin mapping y alta automática ON → crea cliente + mapping;
     * sin mapping y OFF → null (el pedido vive con consumidor_id + snapshot).
     */
    protected function resolverClienteId(Sucursal $sucursal, ?Consumidor $consumidor): ?int
    {
        if (! $consumidor) {
            return null;
        }

        // La sucursal es TENANT (sin comercio_id): el comercio es el activo
        // del proceso, que api.tenant ya configuró en TenantService.
        $comercioId = (int) (app(\App\Services\TenantService::class)->getComercioId() ?? 0);
        if (! $comercioId) {
            return null;
        }

        $clienteId = $consumidor->clienteIdEn($comercioId);
        if ($clienteId && Cliente::find($clienteId)) {
            return $clienteId;
        }

        $comercio = \App\Models\Comercio::find($comercioId);
        if (! $comercio || ! $comercio->tienda_alta_cliente_automatica) {
            return null;
        }

        $cliente = Cliente::create([
            'nombre' => $consumidor->nombre,
            'telefono' => $consumidor->telefono,
            'email' => $consumidor->email,
            'activo' => true,
        ]);

        ConsumidorComercio::updateOrCreate(
            ['consumidor_id' => $consumidor->id, 'comercio_id' => $comercioId],
            ['cliente_id' => $cliente->id],
        );

        Log::info('Cliente tenant creado automáticamente desde consumidor (D11)', [
            'consumidor_id' => $consumidor->id,
            'cliente_id' => $cliente->id,
            'comercio_id' => $comercioId,
        ]);

        return $cliente->id;
    }

    /**
     * Renglones para PedidoDeliveryService::crearPedido a partir del
     * resultado del cotizador (promos por línea atribuidas por el motor).
     */
    protected function construirDetalles(array $resultado): array
    {
        $items = $this->cotizador->itemsCotizados();
        $detalles = [];

        foreach ($items as $index => $item) {
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio'];
            $ivaPorc = (float) ($item['iva_porcentaje'] ?? 21);
            $precioSinIva = round($precioUnitario / (1 + $ivaPorc / 100), 2);

            $itemResultado = $resultado['items'][$index] ?? [];
            $promocionesComunes = $itemResultado['promociones_comunes'] ?? [];
            $promocionesEspeciales = $itemResultado['promociones_especiales'] ?? [];

            $descuentoPromocion = (float) ($itemResultado['descuento_comun'] ?? 0);
            $descuentoPromocionEspecial = 0.0;
            foreach ($promocionesEspeciales as $promoEsp) {
                $descuentoPromocionEspecial += (float) ($promoEsp['descuento'] ?? 0);
            }

            $detalles[] = [
                'articulo_id' => $item['articulo_id'],
                'es_concepto' => false,
                'tipo_iva_id' => null,
                'lista_precio_id' => null,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'precio_sin_iva' => $precioSinIva,
                'descuento' => 0,
                'precio_lista' => (float) ($item['precio_base'] ?? $item['precio']),
                'precio_opcionales' => (float) ($item['precio_opcionales'] ?? 0),
                'subtotal' => $precioUnitario * $cantidad,
                'iva_porcentaje' => $ivaPorc,
                'iva_monto' => (float) ($item['iva_monto'] ?? 0),
                'descuento_promocion' => round($descuentoPromocion, 2),
                'descuento_promocion_especial' => round($descuentoPromocionEspecial, 2),
                'descuento_cupon' => 0,
                'tiene_promocion' => ! empty($promocionesComunes) || ! empty($promocionesEspeciales),
                'total' => $precioUnitario * $cantidad,
                'opcionales' => $item['opcionales'] ?? [],
                '_promociones_item' => [
                    'promociones_comunes' => $promocionesComunes,
                    'promociones_especiales' => $promocionesEspeciales,
                ],
            ];
        }

        return $detalles;
    }
}
