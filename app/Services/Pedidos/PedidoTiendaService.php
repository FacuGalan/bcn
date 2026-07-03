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
        // artículos no pedibles (RF-16/RF-17).
        $clienteId = $this->resolverClienteId($sucursal, $consumidor);
        $resultado = $this->cotizador->cotizar(
            $sucursal,
            $tipo,
            $payload['items'],
            $payload['cupon_codigo'] ?? null,
            $clienteId,
        );

        $aceptacionManual = ($config['aceptacion_pedidos_externos'] ?? 'manual') !== 'automatica';

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
            'ajuste_forma_pago' => 0,
            'total_final' => (float) ($resultado['total_final'] ?? 0),
            'cupon_id' => $resultado['cupon']['id'] ?? null,
            'cupon_codigo_snapshot' => $resultado['cupon']['codigo'] ?? null,
            'cupon_descripcion_snapshot' => $resultado['cupon']['descripcion'] ?? null,
            'monto_cupon' => (float) ($resultado['cupon']['descuento'] ?? 0),
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
            '_actualizar_direccion_cliente' => false, // el consumidor gestiona sus direcciones globales
        ];

        $detalles = $this->construirDetalles($resultado);

        $pedido = $this->pedidoService->crearPedido($data, $detalles, esBorrador: $aceptacionManual);

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
     * D11: cliente tenant del consumidor para este comercio. Con mapping →
     * ese cliente; sin mapping y alta automática ON → crea cliente + mapping;
     * sin mapping y OFF → null (el pedido vive con consumidor_id + snapshot).
     */
    protected function resolverClienteId(Sucursal $sucursal, ?Consumidor $consumidor): ?int
    {
        if (! $consumidor) {
            return null;
        }

        $comercioId = (int) $sucursal->comercio_id;

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
