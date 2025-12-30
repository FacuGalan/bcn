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

            // Validar crédito del cliente si es venta a cuenta corriente
            if (isset($data['cliente_id']) && ($data['es_cuenta_corriente'] ?? false)) {
                $this->validarCreditoCliente($data['cliente_id'], $data['sucursal_id'], $data['total'] ?? 0);
            }

            // Validar que la caja esté abierta si hay caja asignada
            if (isset($data['caja_id']) && $data['caja_id']) {
                $this->validarCajaAbierta($data['caja_id']);
            }

            // Generar número de venta si no viene
            if (empty($data['numero'])) {
                $data['numero'] = $this->generarNumeroVenta($data['sucursal_id']);
            }

            // Verificar si vienen totales ya calculados
            $usarTotalesProporcionados = $data['_usar_totales_proporcionados'] ?? false;

            // Log para debugging
            Log::info('VentaService::crearVenta - Datos recibidos', [
                '_usar_totales_proporcionados' => $usarTotalesProporcionados,
                'subtotal' => $data['subtotal'] ?? 'NO DEFINIDO',
                'descuento' => $data['descuento'] ?? 'NO DEFINIDO',
                'total' => $data['total'] ?? 'NO DEFINIDO',
                'iva' => $data['iva'] ?? 'NO DEFINIDO',
                'detalles_count' => count($detalles),
            ]);

            // Crear la venta con totales proporcionados o en 0 para recalcular
            $esCuentaCorriente = $data['es_cuenta_corriente'] ?? false;

            $venta = Venta::create([
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'caja_id' => $data['caja_id'] ?? null,
                'canal_venta_id' => $data['canal_venta_id'] ?? null,
                'forma_venta_id' => $data['forma_venta_id'] ?? null,
                'lista_precio_id' => $data['lista_precio_id'] ?? null,
                'punto_venta_id' => $data['punto_venta_id'] ?? null,
                'forma_pago_id' => $data['forma_pago_id'] ?? null,
                'usuario_id' => $data['usuario_id'],
                'numero' => $data['numero'],
                'fecha' => $data['fecha'] ?? now(),
                'subtotal' => $usarTotalesProporcionados ? ($data['subtotal'] ?? 0) : 0,
                'descuento' => $data['descuento'] ?? 0,
                'total' => $usarTotalesProporcionados ? ($data['total'] ?? 0) : 0,
                'ajuste_forma_pago' => $data['ajuste_forma_pago'] ?? 0,
                'total_final' => $usarTotalesProporcionados ? ($data['total_final'] ?? $data['total'] ?? 0) : 0,
                'iva' => $usarTotalesProporcionados ? ($data['iva'] ?? 0) : 0,
                'estado' => $esCuentaCorriente ? 'pendiente' : 'completada',
                'es_cuenta_corriente' => $esCuentaCorriente,
                'saldo_pendiente_cache' => $data['saldo_pendiente_cache'] ?? 0,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Guardar promociones aplicadas si vienen en los datos
            if ($usarTotalesProporcionados) {
                $this->guardarPromocionesVenta($venta, $data);
            }

            // Crear detalles de la venta
            foreach ($detalles as $detalle) {
                $this->crearDetalleVenta($venta, $detalle, $usarTotalesProporcionados);
            }

            // Solo recalcular totales si no vienen proporcionados
            if (!$usarTotalesProporcionados) {
                $venta->actualizarTotales();
            }

            // Actualizar stock
            $this->actualizarStockPorVenta($venta);

            // NOTA: El movimiento de caja se registra desde NuevaVenta
            // para cada pago individual (desglosePagos), no aquí.
            // Esto permite manejar ventas con múltiples formas de pago.

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
     * @param bool $usarDatosProporcionados Si true, usa los datos del detalle sin recalcular
     * @return VentaDetalle
     */
    protected function crearDetalleVenta(Venta $venta, array $detalle, bool $usarDatosProporcionados = false): VentaDetalle
    {
        $articulo = Articulo::findOrFail($detalle['articulo_id']);
        $tipoIva = $articulo->tipoIva;

        $precioUnitario = $detalle['precio_unitario'];
        $cantidad = $detalle['cantidad'];

        // Log para debugging
        Log::info('VentaService::crearDetalleVenta', [
            'venta_id' => $venta->id,
            'articulo_id' => $detalle['articulo_id'],
            'usarDatosProporcionados' => $usarDatosProporcionados,
            'precio_lista_recibido' => $detalle['precio_lista'] ?? 'NO DEFINIDO',
            'descuento_promocion_recibido' => $detalle['descuento_promocion'] ?? 'NO DEFINIDO',
            'tiene_promocion_recibido' => $detalle['tiene_promocion'] ?? 'NO DEFINIDO',
        ]);

        if ($usarDatosProporcionados) {
            // Usar datos proporcionados desde la UI (ya calculados)
            $ivaPorcentaje = $detalle['iva_porcentaje'] ?? $tipoIva->porcentaje;
            $precioIvaIncluido = $detalle['precio_iva_incluido'] ?? true;

            // Calcular precio sin IVA
            if ($ivaPorcentaje > 0 && $precioIvaIncluido) {
                $precioSinIva = $precioUnitario / (1 + $ivaPorcentaje / 100);
            } else {
                $precioSinIva = $precioUnitario;
            }

            $subtotalSinIva = $precioSinIva * $cantidad;
            $ivaMonto = $subtotalSinIva * ($ivaPorcentaje / 100);
            $subtotal = $precioUnitario * $cantidad; // Subtotal con IVA incluido
            $descuentoPromocion = $detalle['descuento_promocion'] ?? 0;
            $total = $subtotal - $descuentoPromocion; // Total después de descuentos de promoción

            $ventaDetalle = VentaDetalle::create([
                'venta_id' => $venta->id,
                'articulo_id' => $articulo->id,
                'tipo_iva_id' => $tipoIva->id,
                'lista_precio_id' => $detalle['lista_precio_id'] ?? null,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'precio_lista' => $detalle['precio_lista'] ?? $precioUnitario,
                'iva_porcentaje' => $ivaPorcentaje,
                'precio_sin_iva' => round($precioSinIva, 2),
                'descuento' => $detalle['descuento'] ?? 0,
                'descuento_promocion' => round($descuentoPromocion, 2),
                'tiene_promocion' => $detalle['tiene_promocion'] ?? false,
                'iva_monto' => round($ivaMonto, 2),
                'subtotal' => round($subtotal, 2),
                'total' => round($total, 2),
                // Ajuste manual
                'ajuste_manual_tipo' => $detalle['ajuste_manual_tipo'] ?? null,
                'ajuste_manual_valor' => $detalle['ajuste_manual_valor'] ?? null,
                'precio_sin_ajuste_manual' => $detalle['precio_sin_ajuste_manual'] ?? null,
            ]);

            // Guardar promociones aplicadas al detalle
            if (!empty($detalle['_promociones_item'])) {
                $this->guardarPromocionesDetalle($ventaDetalle, $detalle['_promociones_item']);
            }

            return $ventaDetalle;
        }

        // Modo legacy: recalcular todo
        $descuento = $detalle['descuento'] ?? 0;
        $precioConDescuento = $precioUnitario - $descuento;

        // Determinar si el precio incluye IVA según configuración del artículo
        if ($articulo->precio_iva_incluido) {
            $precioSinIva = $tipoIva->obtenerPrecioSinIva($precioConDescuento, true);
        } else {
            $precioSinIva = $precioConDescuento;
        }

        // Calcular subtotal e IVA
        $subtotalSinIva = $precioSinIva * $cantidad;
        $ivaMonto = $subtotalSinIva * ($tipoIva->porcentaje / 100);
        $subtotal = $subtotalSinIva + $ivaMonto;

        return VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $tipoIva->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'iva_porcentaje' => $tipoIva->porcentaje,
            'precio_sin_iva' => $precioSinIva,
            'descuento' => $descuento,
            'iva_monto' => $ivaMonto,
            'subtotal' => $subtotal,
            'total' => $subtotal, // En modo legacy, total = subtotal (sin promociones)
        ]);
    }

    /**
     * Guarda las promociones aplicadas a la venta
     *
     * @param Venta $venta
     * @param array $data Datos con _promociones_comunes y _promociones_especiales
     */
    protected function guardarPromocionesVenta(Venta $venta, array $data): void
    {
        $promocionesComunes = $data['_promociones_comunes'] ?? [];
        $promocionesEspeciales = $data['_promociones_especiales'] ?? [];

        // Guardar promociones comunes (ej: 5% de descuento)
        foreach ($promocionesComunes as $promo) {
            DB::connection('pymes_tenant')->table('venta_promociones')->insert([
                'venta_id' => $venta->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => $promo['nombre'] ?? $promo['descripcion'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0, // Valor original (%, monto)
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'monto_minimo_requerido' => $promo['monto_minimo'] ?? null,
                'created_at' => now(),
            ]);
        }

        // Guardar promociones especiales (ej: coca + alfajor)
        foreach ($promocionesEspeciales as $promo) {
            // tipo_beneficio debe ser 'porcentaje' o 'monto_fijo'
            $tipoBeneficio = $promo['tipo'] ?? 'monto_fijo';
            if (!in_array($tipoBeneficio, ['porcentaje', 'monto_fijo'])) {
                $tipoBeneficio = 'monto_fijo'; // Para combos, nx1, etc. usamos monto_fijo
            }

            DB::connection('pymes_tenant')->table('venta_promociones')->insert([
                'venta_id' => $venta->id,
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

        Log::info('VentaService::guardarPromocionesVenta', [
            'venta_id' => $venta->id,
            'promociones_comunes' => count($promocionesComunes),
            'promociones_especiales' => count($promocionesEspeciales),
        ]);
    }

    /**
     * Guarda las promociones aplicadas a un detalle de venta
     *
     * @param VentaDetalle $detalle
     * @param array $promocionesItem Info de promociones del ítem
     */
    protected function guardarPromocionesDetalle(VentaDetalle $detalle, array $promocionesItem): void
    {
        // Promociones comunes aplicadas al ítem (ahora vienen como objetos completos)
        $promocionesComunes = $promocionesItem['promociones_comunes'] ?? [];
        foreach ($promocionesComunes as $promo) {
            // Si es string (backwards compatibility), saltar
            if (is_string($promo)) {
                continue;
            }

            DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                'venta_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0, // Valor original (%, monto)
                'descuento_aplicado' => $promo['descuento_item'] ?? $promo['descuento'] ?? 0,
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }

        // Promociones especiales que incluyen este ítem (ahora vienen como objetos completos)
        $promocionesEspeciales = $promocionesItem['promociones_especiales'] ?? [];
        foreach ($promocionesEspeciales as $promo) {
            // Si es string (backwards compatibility), crear registro básico
            if (is_string($promo)) {
                DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                    'venta_detalle_id' => $detalle->id,
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

            // Objeto completo con toda la info
            DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                'venta_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion_especial',
                'promocion_id' => null,
                'promocion_especial_id' => $promo['promocion_especial_id'] ?? $promo['id'] ?? null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción Especial',
                'tipo_beneficio' => 'monto_fijo', // Promociones especiales siempre son monto fijo
                'valor_beneficio' => $promo['descuento'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0, // El descuento se distribuye a nivel venta
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }
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
     * Genera un número de venta único secuencial por sucursal
     *
     * @param int $sucursalId
     * @return string
     */
    protected function generarNumeroVenta(int $sucursalId): string
    {
        // Obtener el último número de venta para esta sucursal
        $ultimaVenta = Venta::where('sucursal_id', $sucursalId)
                           ->orderBy('id', 'desc')
                           ->first();

        $numero = 1;
        if ($ultimaVenta && $ultimaVenta->numero) {
            // Extraer el número del formato actual (ej: "0001-00000001" -> 1)
            $partes = explode('-', $ultimaVenta->numero);
            $ultimoNumero = end($partes);
            $numero = intval($ultimoNumero) + 1;
        }

        // Formato: SUCURSAL-NUMERO (ej: 0001-00000001)
        return sprintf('%04d-%08d', $sucursalId, $numero);
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
