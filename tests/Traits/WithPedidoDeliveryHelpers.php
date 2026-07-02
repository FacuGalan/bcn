<?php

namespace Tests\Traits;

use App\Models\PedidoDelivery;
use App\Models\Sucursal;

/**
 * Helpers compartidos por los tests del módulo Pedidos Delivery.
 * Espejo de WithPedidoMostradorHelpers. Requiere WithSucursal y
 * `protected PedidoDeliveryService $service`.
 */
trait WithPedidoDeliveryHelpers
{
    /**
     * Habilita delivery en la sucursal del test y mergea config custom.
     */
    protected function habilitarDelivery(array $configDelivery = []): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'usa_delivery' => true,
            'config_delivery' => json_encode($configDelivery),
            'latitud' => -34.6037000,   // Obelisco, CABA
            'longitud' => -58.3816000,
        ]);
    }

    /**
     * Payload base para `PedidoDeliveryService::crearPedido`. Totales SIN
     * envío (el service materializa el renglón desde costo_envio).
     */
    protected function datosBaseDelivery(float $total = 1000, ?int $cajaId = null, array $overrides = []): array
    {
        return array_merge([
            'tipo' => PedidoDelivery::TIPO_DELIVERY,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $cajaId,
            'usuario_id' => 1,
            'fecha' => now(),
            'subtotal' => $total,
            'iva' => round($total - $total / 1.21, 2),
            'descuento' => 0,
            'total' => $total,
            'ajuste_forma_pago' => 0,
            'total_final' => $total,
            'nombre_cliente_temporal' => 'Juan Delivery',
            'telefono_cliente_temporal' => '1155550000',
            'direccion_entrega' => 'Av. Siempreviva 742',
            'direccion_referencia' => 'Timbre 3B',
        ], $overrides);
    }

    protected function detalleDeliveryDe($articulo, float $cantidad, float $precioUnitario): array
    {
        $subtotal = $precioUnitario * $cantidad;

        return [
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'es_concepto' => false,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'precio_sin_iva' => $precioUnitario / 1.21,
            'descuento' => 0,
            'precio_lista' => $precioUnitario,
            'subtotal' => $subtotal,
            'iva_porcentaje' => 21,
            'iva_monto' => $subtotal - ($subtotal / 1.21),
            'total' => $subtotal,
        ];
    }

    /**
     * Pedido delivery confirmado con 1 detalle simple.
     */
    protected function pedidoDeliveryConfirmado(float $totalFinal = 1000, ?int $cajaId = null, array $overrides = []): PedidoDelivery
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);

        return $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: $totalFinal, cajaId: $cajaId, overrides: $overrides),
            detalles: [$this->detalleDeliveryDe($articulo, cantidad: 1, precioUnitario: $totalFinal)],
            esBorrador: false,
        );
    }
}
