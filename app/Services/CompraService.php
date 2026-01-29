<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Stock;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Proveedor;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Compras
 *
 * Maneja toda la lógica de negocio relacionada con compras:
 * - Creación y actualización de compras
 * - Gestión de stock (aumentos)
 * - Movimientos de caja (egresos)
 * - Control de cuenta corriente con proveedores
 * - Cálculos de crédito fiscal de IVA
 * - Compras internas (transferencias fiscales entre sucursales)
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class CompraService
{
    /**
     * Crea una nueva compra con sus detalles
     *
     * @param array $data Datos de la compra
     * @param array $detalles Array de detalles de la compra
     * @return Compra
     * @throws Exception
     */
    public function crearCompra(array $data, array $detalles): Compra
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Validar que haya detalles
            if (empty($detalles)) {
                throw new Exception('La compra debe tener al menos un artículo');
            }

            // Validar que la caja esté abierta si es pago en efectivo
            if (isset($data['caja_id']) && in_array($data['forma_pago'], ['efectivo', 'debito', 'credito'])) {
                $this->validarCajaAbierta($data['caja_id']);
            }

            // Validar saldo en caja si es pago en efectivo
            if (isset($data['caja_id']) && $data['forma_pago'] === 'efectivo') {
                $this->validarSaldoCaja($data['caja_id'], $data['total']);
            }

            // Generar número de comprobante si no viene
            if (empty($data['numero_comprobante'])) {
                $data['numero_comprobante'] = $this->generarNumeroComprobante(
                    $data['sucursal_id'],
                    $data['tipo_comprobante']
                );
            }

            // Crear la compra
            $compra = Compra::create([
                'sucursal_id' => $data['sucursal_id'],
                'proveedor_id' => $data['proveedor_id'],
                'caja_id' => $data['caja_id'] ?? null,
                'usuario_id' => $data['usuario_id'],
                'numero_comprobante' => $data['numero_comprobante'],
                'fecha' => $data['fecha'] ?? now(),
                'tipo_comprobante' => $data['tipo_comprobante'],
                'subtotal' => 0,
                'total' => 0,
                'total_iva' => 0,
                'forma_pago' => $data['forma_pago'],
                'estado' => ($data['forma_pago'] === 'cta_cte') ? 'pendiente' : 'completada',
                'saldo_pendiente' => 0,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Crear detalles de la compra
            foreach ($detalles as $detalle) {
                $this->crearDetalleCompra($compra, $detalle);
            }

            // Recalcular totales
            $compra->actualizarTotales();

            // Actualizar stock (aumentar)
            $this->actualizarStockPorCompra($compra);

            // Registrar movimiento de caja si corresponde
            if ($compra->caja_id && $compra->forma_pago !== 'cta_cte') {
                $this->registrarMovimientoCaja($compra);
            }

            DB::connection('pymes_tenant')->commit();

            Log::info('Compra creada exitosamente', [
                'compra_id' => $compra->id,
                'numero_comprobante' => $compra->numero_comprobante,
                'total' => $compra->total,
                'es_transferencia_interna' => $compra->esTransferenciaInterna(),
            ]);

            return $compra->fresh(['detalles', 'proveedor', 'sucursal', 'caja']);

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al crear compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Crea un detalle de compra con cálculos de crédito fiscal de IVA
     *
     * @param Compra $compra
     * @param array $detalle
     * @return CompraDetalle
     */
    protected function crearDetalleCompra(Compra $compra, array $detalle): CompraDetalle
    {
        $articulo = Articulo::findOrFail($detalle['articulo_id']);
        $tipoIva = $articulo->tipoIva;

        // En compras, normalmente el precio viene sin IVA
        $precioSinIva = $detalle['precio_sin_iva'] ?? $detalle['precio_unitario'];
        $precioUnitario = $detalle['precio_unitario'];

        // Calcular IVA (crédito fiscal)
        $subtotalSinIva = $precioSinIva * $detalle['cantidad'];
        $ivaMonto = $subtotalSinIva * ($tipoIva->porcentaje / 100);
        $subtotal = $subtotalSinIva + $ivaMonto;

        return CompraDetalle::create([
            'compra_id' => $compra->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $tipoIva->id,
            'cantidad' => $detalle['cantidad'],
            'precio_unitario' => $precioUnitario,
            'iva_porcentaje' => $tipoIva->porcentaje,
            'precio_sin_iva' => $precioSinIva,
            'iva_monto' => $ivaMonto,
            'subtotal' => $subtotal,
        ]);
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
            throw new Exception('La caja debe estar abierta para realizar compras');
        }
    }

    /**
     * Valida que haya saldo suficiente en caja
     *
     * @param int $cajaId
     * @param float $monto
     * @throws Exception
     */
    protected function validarSaldoCaja(int $cajaId, float $monto): void
    {
        $caja = Caja::findOrFail($cajaId);

        if (!$caja->tieneSaldoSuficiente($monto)) {
            throw new Exception(
                "Saldo insuficiente en caja. Disponible: $" . number_format($caja->saldo_actual, 2) .
                ", Necesario: $" . number_format($monto, 2)
            );
        }
    }

    /**
     * Actualiza el stock por la compra realizada (aumenta stock)
     *
     * @param Compra $compra
     */
    protected function actualizarStockPorCompra(Compra $compra): void
    {
        foreach ($compra->detalles as $detalle) {
            $articulo = $detalle->articulo;

            // Solo actualizar stock si el artículo lo controla
            if (!$articulo->controla_stock) {
                continue;
            }

            // Buscar o crear stock para este artículo en la sucursal
            $stock = Stock::firstOrCreate(
                [
                    'sucursal_id' => $compra->sucursal_id,
                    'articulo_id' => $detalle->articulo_id,
                ],
                [
                    'cantidad' => 0,
                    'cantidad_minima' => null,
                    'cantidad_maxima' => null,
                    'ultima_actualizacion' => now(),
                ]
            );

            $stock->aumentar($detalle->cantidad);
        }
    }

    /**
     * Registra el movimiento de caja por la compra (egreso)
     *
     * @param Compra $compra
     */
    protected function registrarMovimientoCaja(Compra $compra): void
    {
        $caja = Caja::findOrFail($compra->caja_id);

        // Crear movimiento de caja (egreso)
        $movimiento = new MovimientoCaja();
        $movimiento->caja_id = $caja->id;
        $movimiento->tipo_movimiento = 'egreso';
        $movimiento->concepto = "Compra #{$compra->numero_comprobante}";
        $movimiento->monto = $compra->total;
        $movimiento->forma_pago = $compra->forma_pago;
        $movimiento->referencia = $compra->numero_comprobante;
        $movimiento->compra_id = $compra->id;
        $movimiento->usuario_id = $compra->usuario_id;

        // Calcular saldos
        $movimiento->calcularSaldos();
        $movimiento->save();

        // Actualizar saldo de la caja (disminuir)
        $caja->disminuirSaldo($compra->total);
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
        $ultimaCompra = Compra::where('sucursal_id', $sucursalId)
                             ->where('tipo_comprobante', $tipoComprobante)
                             ->orderBy('id', 'desc')
                             ->first();

        $numero = $ultimaCompra ? intval(substr($ultimaCompra->numero_comprobante, -8)) + 1 : 1;

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
            'factura_a' => 'CA',
            'factura_b' => 'CB',
            'factura_c' => 'CC',
            'nota_credito' => 'NCC',
            'nota_debito' => 'NDC',
            'remito' => 'RM',
        ];

        return $prefijos[$tipoComprobante] ?? 'CO';
    }

    /**
     * Cancela una compra y revierte sus efectos
     *
     * @param int $compraId
     * @return Compra
     * @throws Exception
     */
    public function cancelarCompra(int $compraId): Compra
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $compra = Compra::findOrFail($compraId);

            if ($compra->estaCancelada()) {
                throw new Exception('La compra ya está cancelada');
            }

            // Revertir stock (disminuir)
            $this->revertirStockPorCompra($compra);

            // Revertir movimiento de caja si existe
            if ($compra->movimientoCaja) {
                $caja = $compra->caja;
                $caja->aumentarSaldo($compra->total);
            }

            // Marcar como cancelada
            $compra->cancelar();

            DB::connection('pymes_tenant')->commit();

            Log::info('Compra cancelada exitosamente', ['compra_id' => $compra->id]);

            return $compra->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cancelar compra', [
                'compra_id' => $compraId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Revierte el stock por cancelación de compra
     *
     * @param Compra $compra
     * @throws Exception
     */
    protected function revertirStockPorCompra(Compra $compra): void
    {
        foreach ($compra->detalles as $detalle) {
            $articulo = $detalle->articulo;

            if (!$articulo->controla_stock) {
                continue;
            }

            $stock = Stock::where('sucursal_id', $compra->sucursal_id)
                         ->where('articulo_id', $detalle->articulo_id)
                         ->first();

            if (!$stock) {
                throw new Exception("No se encontró stock para el artículo {$articulo->nombre}");
            }

            // Validar que haya suficiente stock para revertir
            if ($stock->cantidad < $detalle->cantidad) {
                throw new Exception(
                    "No se puede cancelar la compra. Stock actual de '{$articulo->nombre}': {$stock->cantidad}, " .
                    "Se necesita revertir: {$detalle->cantidad}"
                );
            }

            $stock->disminuir($detalle->cantidad);
        }
    }

    /**
     * Registra un pago a una compra en cuenta corriente
     *
     * @param int $compraId
     * @param float $monto
     * @param int $cajaId
     * @param int $usuarioId
     * @return Compra
     * @throws Exception
     */
    public function registrarPago(int $compraId, float $monto, int $cajaId, int $usuarioId): Compra
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $compra = Compra::findOrFail($compraId);

            if (!$compra->esCtaCte()) {
                throw new Exception('Esta compra no es a cuenta corriente');
            }

            if ($monto > $compra->saldo_pendiente) {
                throw new Exception('El monto del pago excede el saldo pendiente');
            }

            // Validar caja
            $caja = Caja::findOrFail($cajaId);
            if (!$caja->estaAbierta()) {
                throw new Exception('La caja debe estar abierta para registrar pagos');
            }

            if (!$caja->tieneSaldoSuficiente($monto)) {
                throw new Exception('Saldo insuficiente en caja');
            }

            // Registrar movimiento de caja
            $movimiento = new MovimientoCaja();
            $movimiento->caja_id = $caja->id;
            $movimiento->tipo_movimiento = 'egreso';
            $movimiento->concepto = "Pago compra #{$compra->numero_comprobante}";
            $movimiento->monto = $monto;
            $movimiento->forma_pago = 'efectivo';
            $movimiento->referencia = $compra->numero_comprobante;
            $movimiento->compra_id = $compra->id;
            $movimiento->usuario_id = $usuarioId;
            $movimiento->calcularSaldos();
            $movimiento->save();

            // Actualizar saldo de caja
            $caja->disminuirSaldo($monto);

            // Registrar pago en la compra
            $compra->registrarPago($monto);

            DB::connection('pymes_tenant')->commit();

            Log::info('Pago de compra registrado', [
                'compra_id' => $compra->id,
                'monto' => $monto,
                'saldo_pendiente' => $compra->saldo_pendiente
            ]);

            return $compra->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al registrar pago de compra', [
                'compra_id' => $compraId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
