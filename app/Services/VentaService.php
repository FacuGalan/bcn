<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Stock;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Cliente;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Ventas
 *
 * Maneja toda la lógica de negocio relacionada con ventas:
 * - Creación y actualización de ventas
 * - Gestión de stock
 * - Movimientos de caja
 * - Control de cuenta corriente de clientes
 * - Cálculos de IVA y descuentos
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class VentaService
{
    /**
     * Crea una nueva venta con sus detalles
     *
     * @param array $data Datos de la venta
     * @param array $detalles Array de detalles de la venta
     * @return Venta
     * @throws Exception
     */
    public function crearVenta(array $data, array $detalles): Venta
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Validar que haya detalles
            if (empty($detalles)) {
                throw new Exception('La venta debe tener al menos un artículo');
            }

            // Validar stock si los artículos controlan stock
            $this->validarStockDisponible($data['sucursal_id'], $detalles);

            // Validar crédito del cliente si es venta a crédito
            if (isset($data['cliente_id']) && $data['forma_pago'] === 'cta_cte') {
                $this->validarCreditoCliente($data['cliente_id'], $data['sucursal_id'], $data['total']);
            }

            // Validar que la caja esté abierta si es pago en efectivo
            if (isset($data['caja_id']) && in_array($data['forma_pago'], ['efectivo', 'debito', 'credito'])) {
                $this->validarCajaAbierta($data['caja_id']);
            }

            // Generar número de comprobante si no viene
            if (empty($data['numero_comprobante'])) {
                $data['numero_comprobante'] = $this->generarNumeroComprobante(
                    $data['sucursal_id'],
                    $data['tipo_comprobante']
                );
            }

            // Crear la venta
            $venta = Venta::create([
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'caja_id' => $data['caja_id'] ?? null,
                'usuario_id' => $data['usuario_id'],
                'numero_comprobante' => $data['numero_comprobante'],
                'fecha' => $data['fecha'] ?? now(),
                'tipo_comprobante' => $data['tipo_comprobante'],
                'subtotal' => 0,
                'descuento' => $data['descuento'] ?? 0,
                'total' => 0,
                'total_iva' => 0,
                'forma_pago' => $data['forma_pago'],
                'estado' => ($data['forma_pago'] === 'cta_cte') ? 'pendiente' : 'completada',
                'saldo_pendiente' => 0,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Crear detalles de la venta
            foreach ($detalles as $detalle) {
                $this->crearDetalleVenta($venta, $detalle);
            }

            // Recalcular totales
            $venta->actualizarTotales();

            // Actualizar stock
            $this->actualizarStockPorVenta($venta);

            // Registrar movimiento de caja si corresponde
            if ($venta->caja_id && $venta->forma_pago !== 'cta_cte') {
                $this->registrarMovimientoCaja($venta);
            }

            // Actualizar saldo del cliente si es cuenta corriente
            if ($venta->cliente_id && $venta->forma_pago === 'cta_cte') {
                $this->actualizarSaldoCliente($venta);
            }

            DB::connection('pymes_tenant')->commit();

            Log::info('Venta creada exitosamente', [
                'venta_id' => $venta->id,
                'numero_comprobante' => $venta->numero_comprobante,
                'total' => $venta->total,
            ]);

            return $venta->fresh(['detalles', 'cliente', 'sucursal', 'caja']);

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al crear venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Crea un detalle de venta con cálculos de IVA
     *
     * @param Venta $venta
     * @param array $detalle
     * @return VentaDetalle
     */
    protected function crearDetalleVenta(Venta $venta, array $detalle): VentaDetalle
    {
        $articulo = Articulo::findOrFail($detalle['articulo_id']);
        $tipoIva = $articulo->tipoIva;

        // Calcular precio sin IVA
        $precioUnitario = $detalle['precio_unitario'];
        $descuento = $detalle['descuento'] ?? 0;
        $precioConDescuento = $precioUnitario - $descuento;

        // Determinar si el precio incluye IVA según configuración del artículo
        if ($articulo->precio_iva_incluido) {
            $precioSinIva = $tipoIva->obtenerPrecioSinIva($precioConDescuento, true);
        } else {
            $precioSinIva = $precioConDescuento;
        }

        // Calcular subtotal e IVA
        $subtotalSinIva = $precioSinIva * $detalle['cantidad'];
        $ivaMonto = $subtotalSinIva * ($tipoIva->porcentaje / 100);
        $subtotal = $subtotalSinIva + $ivaMonto;

        return VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $tipoIva->id,
            'cantidad' => $detalle['cantidad'],
            'precio_unitario' => $precioUnitario,
            'iva_porcentaje' => $tipoIva->porcentaje,
            'precio_sin_iva' => $precioSinIva,
            'descuento' => $descuento,
            'iva_monto' => $ivaMonto,
            'subtotal' => $subtotal,
        ]);
    }

    /**
     * Valida que hay stock disponible para todos los artículos
     *
     * @param int $sucursalId
     * @param array $detalles
     * @throws Exception
     */
    protected function validarStockDisponible(int $sucursalId, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $articulo = Articulo::findOrFail($detalle['articulo_id']);

            // Solo validar si el artículo controla stock
            if (!$articulo->controla_stock) {
                continue;
            }

            $stock = Stock::where('sucursal_id', $sucursalId)
                         ->where('articulo_id', $detalle['articulo_id'])
                         ->first();

            if (!$stock) {
                throw new Exception(
                    "El artículo '{$articulo->nombre}' no tiene stock en esta sucursal"
                );
            }

            if ($stock->cantidad < $detalle['cantidad']) {
                throw new Exception(
                    "Stock insuficiente para '{$articulo->nombre}'. Disponible: {$stock->cantidad}, Solicitado: {$detalle['cantidad']}"
                );
            }
        }
    }

    /**
     * Valida el crédito disponible del cliente
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @param float $montoVenta
     * @throws Exception
     */
    protected function validarCreditoCliente(int $clienteId, int $sucursalId, float $montoVenta): void
    {
        $cliente = Cliente::findOrFail($clienteId);

        if (!$cliente->tieneDisponibilidadCredito($montoVenta, $sucursalId)) {
            $disponible = $cliente->obtenerCreditoDisponibleEnSucursal($sucursalId);
            throw new Exception(
                "Crédito insuficiente. Disponible: $" . number_format($disponible, 2) .
                ", Necesario: $" . number_format($montoVenta, 2)
            );
        }
    }

    /**
     * Valida que la caja esté abierta
     *
     * @param int $cajaId
     * @throws Exception
     */
    protected function validarCajaAbierta(int $cajaId): void
    {
        $caja = Caja::findOrFail($cajaId);

        if (!$caja->estaAbierta()) {
            throw new Exception('La caja debe estar abierta para realizar ventas');
        }
    }

    /**
     * Actualiza el stock por la venta realizada
     *
     * @param Venta $venta
     */
    protected function actualizarStockPorVenta(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            // Solo actualizar stock si el artículo lo controla
            if (!$articulo->controla_stock) {
                continue;
            }

            $stock = Stock::where('sucursal_id', $venta->sucursal_id)
                         ->where('articulo_id', $detalle->articulo_id)
                         ->firstOrFail();

            $stock->disminuir($detalle->cantidad);
        }
    }

    /**
     * Registra el movimiento de caja por la venta
     *
     * @param Venta $venta
     */
    protected function registrarMovimientoCaja(Venta $venta): void
    {
        $caja = Caja::findOrFail($venta->caja_id);

        // Crear movimiento de caja
        $movimiento = new MovimientoCaja();
        $movimiento->caja_id = $caja->id;
        $movimiento->tipo_movimiento = 'ingreso';
        $movimiento->concepto = "Venta #{$venta->numero_comprobante}";
        $movimiento->monto = $venta->total;
        $movimiento->forma_pago = $venta->forma_pago;
        $movimiento->referencia = $venta->numero_comprobante;
        $movimiento->venta_id = $venta->id;
        $movimiento->usuario_id = $venta->usuario_id;

        // Calcular saldos
        $movimiento->calcularSaldos();
        $movimiento->save();

        // Actualizar saldo de la caja
        $caja->aumentarSaldo($venta->total);
    }

    /**
     * Actualiza el saldo del cliente en cuenta corriente
     *
     * @param Venta $venta
     */
    protected function actualizarSaldoCliente(Venta $venta): void
    {
        $cliente = Cliente::findOrFail($venta->cliente_id);
        $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, $venta->total);
    }

    /**
     * Genera un número de comprobante único
     *
     * @param int $sucursalId
     * @param string $tipoComprobante
     * @return string
     */
    protected function generarNumeroComprobante(int $sucursalId, string $tipoComprobante): string
    {
        // Obtener el último número de comprobante para este tipo y sucursal
        $ultimaVenta = Venta::where('sucursal_id', $sucursalId)
                           ->where('tipo_comprobante', $tipoComprobante)
                           ->orderBy('id', 'desc')
                           ->first();

        $numero = $ultimaVenta ? intval(substr($ultimaVenta->numero_comprobante, -8)) + 1 : 1;

        // Formato: TIPO-SUCURSAL-00000001
        $prefijo = $this->obtenerPrefijoComprobante($tipoComprobante);
        return sprintf('%s-%04d-%08d', $prefijo, $sucursalId, $numero);
    }

    /**
     * Obtiene el prefijo del comprobante según el tipo
     *
     * @param string $tipoComprobante
     * @return string
     */
    protected function obtenerPrefijoComprobante(string $tipoComprobante): string
    {
        $prefijos = [
            'factura_a' => 'FA',
            'factura_b' => 'FB',
            'factura_c' => 'FC',
            'ticket' => 'TK',
            'nota_credito' => 'NC',
            'nota_debito' => 'ND',
            'presupuesto' => 'PR',
        ];

        return $prefijos[$tipoComprobante] ?? 'VT';
    }

    /**
     * Cancela una venta y revierte sus efectos
     *
     * @param int $ventaId
     * @return Venta
     * @throws Exception
     */
    public function cancelarVenta(int $ventaId): Venta
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $venta = Venta::findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                throw new Exception('La venta ya está cancelada');
            }

            // Revertir stock
            $this->revertirStockPorVenta($venta);

            // Revertir saldo del cliente si es cuenta corriente
            if ($venta->cliente_id && $venta->forma_pago === 'cta_cte') {
                $cliente = Cliente::findOrFail($venta->cliente_id);
                $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, -$venta->total);
            }

            // Revertir movimiento de caja si existe
            if ($venta->movimientoCaja) {
                $caja = $venta->caja;
                $caja->disminuirSaldo($venta->total);
            }

            // Marcar como cancelada
            $venta->cancelar();

            DB::connection('pymes_tenant')->commit();

            Log::info('Venta cancelada exitosamente', ['venta_id' => $venta->id]);

            return $venta->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cancelar venta', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Revierte el stock por cancelación de venta
     *
     * @param Venta $venta
     */
    protected function revertirStockPorVenta(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            if (!$articulo->controla_stock) {
                continue;
            }

            $stock = Stock::where('sucursal_id', $venta->sucursal_id)
                         ->where('articulo_id', $detalle->articulo_id)
                         ->first();

            if ($stock) {
                $stock->aumentar($detalle->cantidad);
            }
        }
    }
}
