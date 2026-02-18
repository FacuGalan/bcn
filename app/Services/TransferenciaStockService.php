<?php

namespace App\Services;

use App\Models\TransferenciaStock;
use App\Models\Stock;
use App\Models\Articulo;
use App\Models\Sucursal;
use App\Models\MovimientoStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Transferencias de Stock
 *
 * Maneja toda la lógica de negocio relacionada con transferencias de inventario:
 * - Transferencias simples (sin facturación)
 * - Transferencias fiscales (con venta/compra entre sucursales)
 * - Workflow: solicitud → aprobación → recepción
 * - Actualización automática de stock
 * - Validaciones de inventario disponible
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class TransferenciaStockService
{
    protected $ventaService;
    protected $compraService;

    public function __construct(VentaService $ventaService, CompraService $compraService)
    {
        $this->ventaService = $ventaService;
        $this->compraService = $compraService;
    }

    /**
     * Solicita una transferencia de stock entre sucursales
     *
     * @param array $data Datos de la transferencia
     * @return TransferenciaStock
     * @throws Exception
     */
    public function solicitarTransferencia(array $data): TransferenciaStock
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Validar datos básicos
            if ($data['sucursal_origen_id'] === $data['sucursal_destino_id']) {
                throw new Exception('Las sucursales origen y destino deben ser diferentes');
            }

            // Validar que el artículo existe
            $articulo = Articulo::findOrFail($data['articulo_id']);

            if (!$articulo->controlaStock($data['sucursal_origen_id'])) {
                throw new Exception('Este artículo no controla stock en la sucursal origen y no puede ser transferido');
            }

            // Validar stock disponible en sucursal origen
            $stock = Stock::where('sucursal_id', $data['sucursal_origen_id'])
                         ->where('articulo_id', $data['articulo_id'])
                         ->first();

            if (!$stock) {
                throw new Exception('El artículo no tiene stock en la sucursal origen');
            }

            if ($stock->cantidad < $data['cantidad']) {
                throw new Exception(
                    "Stock insuficiente en sucursal origen. Disponible: {$stock->cantidad}, Solicitado: {$data['cantidad']}"
                );
            }

            // Crear la transferencia
            $transferencia = TransferenciaStock::create([
                'sucursal_origen_id' => $data['sucursal_origen_id'],
                'sucursal_destino_id' => $data['sucursal_destino_id'],
                'articulo_id' => $data['articulo_id'],
                'cantidad' => $data['cantidad'],
                'tipo_transferencia' => $data['tipo_transferencia'] ?? 'simple',
                'estado' => 'pendiente',
                'usuario_solicita_id' => $data['usuario_id'],
                'fecha_solicitud' => now(),
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia de stock solicitada', [
                'transferencia_id' => $transferencia->id,
                'articulo_id' => $transferencia->articulo_id,
                'sucursal_origen' => $transferencia->sucursal_origen_id,
                'sucursal_destino' => $transferencia->sucursal_destino_id,
                'cantidad' => $transferencia->cantidad,
            ]);

            return $transferencia->fresh(['articulo', 'sucursalOrigen', 'sucursalDestino']);

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al solicitar transferencia', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Aprueba y procesa una transferencia de stock
     *
     * @param int $transferenciaId
     * @param int $usuarioId
     * @return TransferenciaStock
     * @throws Exception
     */
    public function aprobarTransferencia(int $transferenciaId, int $usuarioId): TransferenciaStock
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $transferencia = TransferenciaStock::findOrFail($transferenciaId);

            if (!$transferencia->estaPendiente()) {
                throw new Exception('Solo se pueden aprobar transferencias pendientes');
            }

            // Validar stock nuevamente
            $stock = Stock::where('sucursal_id', $transferencia->sucursal_origen_id)
                         ->where('articulo_id', $transferencia->articulo_id)
                         ->first();

            if (!$stock || $stock->cantidad < $transferencia->cantidad) {
                throw new Exception('Stock insuficiente para aprobar la transferencia');
            }

            // Si es transferencia fiscal, crear documentos
            if ($transferencia->esFiscal()) {
                $this->crearDocumentosFiscales($transferencia, $usuarioId);
            }

            // Descontar stock de origen
            $stock->disminuir($transferencia->cantidad);

            // Registrar movimiento de stock (salida por transferencia)
            $sucursalDestino = Sucursal::find($transferencia->sucursal_destino_id);
            $articulo = Articulo::find($transferencia->articulo_id);
            MovimientoStock::crearMovimientoTransferenciaSalida(
                $transferencia->articulo_id,
                $transferencia->sucursal_origen_id,
                $transferencia->cantidad,
                $transferencia->id,
                "Transferencia #{$transferencia->id} → {$sucursalDestino->nombre}",
                $usuarioId
            );

            // Aprobar la transferencia
            $transferencia->aprobar($usuarioId);

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia aprobada y stock descontado', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al aprobar transferencia', [
                'transferencia_id' => $transferenciaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Recibe y completa una transferencia de stock
     *
     * @param int $transferenciaId
     * @param int $usuarioId
     * @return TransferenciaStock
     * @throws Exception
     */
    public function recibirTransferencia(int $transferenciaId, int $usuarioId): TransferenciaStock
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $transferencia = TransferenciaStock::findOrFail($transferenciaId);

            if (!$transferencia->estaEnTransito()) {
                throw new Exception('Solo se pueden recibir transferencias en tránsito');
            }

            // Buscar o crear stock en sucursal destino
            $stockDestino = Stock::firstOrCreate(
                [
                    'sucursal_id' => $transferencia->sucursal_destino_id,
                    'articulo_id' => $transferencia->articulo_id,
                ],
                [
                    'cantidad' => 0,
                    'cantidad_minima' => null,
                    'cantidad_maxima' => null,
                    'ultima_actualizacion' => now(),
                ]
            );

            // Aumentar stock en destino
            $stockDestino->aumentar($transferencia->cantidad);

            // Registrar movimiento de stock (entrada por transferencia)
            $sucursalOrigen = Sucursal::find($transferencia->sucursal_origen_id);
            $articulo = Articulo::find($transferencia->articulo_id);
            MovimientoStock::crearMovimientoTransferenciaEntrada(
                $transferencia->articulo_id,
                $transferencia->sucursal_destino_id,
                $transferencia->cantidad,
                $transferencia->id,
                "Transferencia #{$transferencia->id} ← {$sucursalOrigen->nombre}",
                $usuarioId
            );

            // Marcar como recibida
            $transferencia->recibir($usuarioId);

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia recibida y stock incrementado', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al recibir transferencia', [
                'transferencia_id' => $transferenciaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancela una transferencia y revierte el stock si ya fue aprobada
     *
     * @param int $transferenciaId
     * @return TransferenciaStock
     * @throws Exception
     */
    public function cancelarTransferencia(int $transferenciaId): TransferenciaStock
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $transferencia = TransferenciaStock::findOrFail($transferenciaId);

            if ($transferencia->estaCompletada()) {
                throw new Exception('No se puede cancelar una transferencia completada');
            }

            // Si estaba en tránsito, devolver stock a origen
            if ($transferencia->estaEnTransito()) {
                $stock = Stock::where('sucursal_id', $transferencia->sucursal_origen_id)
                             ->where('articulo_id', $transferencia->articulo_id)
                             ->first();

                if ($stock) {
                    $stock->aumentar($transferencia->cantidad);
                }

                // Crear contraasientos para los movimientos de esta transferencia
                $movimientosSalida = MovimientoStock::where('transferencia_stock_id', $transferencia->id)
                    ->where('tipo', MovimientoStock::TIPO_TRANSFERENCIA_SALIDA)
                    ->activos()
                    ->get();

                foreach ($movimientosSalida as $movimiento) {
                    MovimientoStock::crearContraasiento(
                        $movimiento,
                        'Cancelación de transferencia',
                        $transferencia->usuario_solicita_id
                    );
                }
            }

            // Cancelar la transferencia
            $transferencia->cancelar();

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia cancelada', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cancelar transferencia', [
                'transferencia_id' => $transferenciaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crea los documentos fiscales para una transferencia (venta y compra)
     *
     * @param TransferenciaStock $transferencia
     * @param int $usuarioId
     * @throws Exception
     */
    protected function crearDocumentosFiscales(TransferenciaStock $transferencia, int $usuarioId): void
    {
        // TODO: Implementar cuando tengamos el sistema de proveedores internos
        // Por ahora lanzamos una excepción indicando que esta funcionalidad está pendiente

        Log::warning('Transferencia fiscal solicitada pero aún no implementada', [
            'transferencia_id' => $transferencia->id,
        ]);

        // Para transferencias fiscales, se debería:
        // 1. Crear un proveedor interno para la sucursal origen
        // 2. Crear una venta en sucursal origen
        // 3. Crear una compra en sucursal destino
        // 4. Vincular estos documentos a la transferencia

        // Ejemplo (a implementar):
        /*
        $venta = $this->ventaService->crearVenta([
            'sucursal_id' => $transferencia->sucursal_origen_id,
            'cliente_id' => null, // Cliente interno = sucursal destino
            'usuario_id' => $usuarioId,
            'tipo_comprobante' => 'transferencia',
            'forma_pago' => 'transferencia',
            // ... otros datos
        ], [
            [
                'articulo_id' => $transferencia->articulo_id,
                'cantidad' => $transferencia->cantidad,
                'precio_unitario' => 0, // O precio de costo
            ]
        ]);

        $compra = $this->compraService->crearCompra([
            'sucursal_id' => $transferencia->sucursal_destino_id,
            'proveedor_id' => null, // Proveedor interno = sucursal origen
            'usuario_id' => $usuarioId,
            'tipo_comprobante' => 'transferencia',
            'forma_pago' => 'transferencia',
            // ... otros datos
        ], [
            [
                'articulo_id' => $transferencia->articulo_id,
                'cantidad' => $transferencia->cantidad,
                'precio_unitario' => 0,
            ]
        ]);

        $transferencia->venta_id = $venta->id;
        $transferencia->compra_id = $compra->id;
        $transferencia->save();
        */
    }
}
