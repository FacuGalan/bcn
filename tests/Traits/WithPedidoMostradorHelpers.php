<?php

namespace Tests\Traits;

use App\Models\PedidoMostrador;

/**
 * Helpers compartidos por los tests del módulo Pedidos Mostrador.
 *
 * Antes vivían como métodos privados duplicados en cada test file
 * (~140 líneas duplicadas). Extraerlos a un trait reduce la deuda y
 * centraliza el contrato de fixtures: si cambia la estructura del
 * payload del pedido, hay un solo lugar que actualizar.
 *
 * Requiere que el test file también use `WithSucursal` (para `$sucursalId`)
 * y tenga `protected PedidoMostradorService $service` instanciado.
 */
trait WithPedidoMostradorHelpers
{
    /**
     * Payload base del pedido para `PedidoMostradorService::crearPedido`.
     * Totales en una moneda (sin IVA, sin ajuste FP) que el caller puede
     * sobrescribir con `array_merge` cuando necesite agregar cupón,
     * promociones, descuento general, etc.
     */
    protected function datosBaseDelPedido(float $total = 1000, ?int $cajaId = null): array
    {
        return [
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $cajaId,
            'usuario_id' => 1,
            'fecha' => now(),
            'subtotal' => $total,
            'iva' => 0,
            'descuento' => 0,
            'total' => $total,
            'ajuste_forma_pago' => 0,
            'total_final' => $total,
            'identificador' => 'Mesa 1',
        ];
    }

    /**
     * Payload de un detalle simple (1 artículo, IVA 21%). Asume que el
     * artículo viene con `tipo_iva_id` correcto.
     */
    protected function detalleDe($articulo, float $cantidad, float $precioUnitario): array
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
     * Crea y persiste un pedido confirmado con 1 detalle simple. El test
     * file debe tener `$this->service` (PedidoMostradorService) y debe
     * haber llamado a `crearTiposIva()` antes (el artículo necesita
     * tipo_iva_id válido).
     */
    protected function pedidoConfirmadoSimple(float $totalFinal = 1000, ?int $cajaId = null): PedidoMostrador
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 100);

        return $this->service->crearPedido(
            data: $this->datosBaseDelPedido(total: $totalFinal, cajaId: $cajaId),
            detalles: [$this->detalleDe($articulo, cantidad: 1, precioUnitario: $totalFinal)],
            esBorrador: false,
        );
    }
}
