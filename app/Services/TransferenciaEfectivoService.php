<?php

namespace App\Services;

use App\Models\TransferenciaEfectivo;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio de Transferencias de Efectivo
 *
 * Maneja toda la lógica de negocio relacionada con transferencias de dinero entre cajas:
 * - Transferencias entre cajas de la misma sucursal
 * - Transferencias entre cajas de diferentes sucursales
 * - Workflow: solicitud → autorización → completar
 * - Actualización automática de saldos
 * - Registro de movimientos en ambas cajas
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class TransferenciaEfectivoService
{
    /**
     * Solicita una transferencia de efectivo entre cajas
     *
     * @param array $data Datos de la transferencia
     * @return TransferenciaEfectivo
     * @throws Exception
     */
    public function solicitarTransferencia(array $data): TransferenciaEfectivo
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Validar datos básicos
            if ($data['caja_origen_id'] === $data['caja_destino_id']) {
                throw new Exception('Las cajas origen y destino deben ser diferentes');
            }

            if ($data['monto'] <= 0) {
                throw new Exception('El monto debe ser mayor a cero');
            }

            // Validar caja origen
            $cajaOrigen = Caja::findOrFail($data['caja_origen_id']);

            if (!$cajaOrigen->estaAbierta()) {
                throw new Exception('La caja origen debe estar abierta');
            }

            // Validar saldo disponible
            if (!$cajaOrigen->tieneSaldoSuficiente($data['monto'])) {
                throw new Exception(
                    "Saldo insuficiente en caja origen. Disponible: $" . number_format($cajaOrigen->saldo_actual, 2) .
                    ", Solicitado: $" . number_format($data['monto'], 2)
                );
            }

            // Validar caja destino
            $cajaDestino = Caja::findOrFail($data['caja_destino_id']);

            if (!$cajaDestino->estaAbierta()) {
                throw new Exception('La caja destino debe estar abierta');
            }

            // Crear la transferencia
            $transferencia = TransferenciaEfectivo::create([
                'caja_origen_id' => $data['caja_origen_id'],
                'caja_destino_id' => $data['caja_destino_id'],
                'monto' => $data['monto'],
                'concepto' => $data['concepto'],
                'estado' => 'pendiente',
                'usuario_solicita_id' => $data['usuario_id'],
                'fecha_solicitud' => now(),
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia de efectivo solicitada', [
                'transferencia_id' => $transferencia->id,
                'caja_origen' => $transferencia->caja_origen_id,
                'caja_destino' => $transferencia->caja_destino_id,
                'monto' => $transferencia->monto,
            ]);

            return $transferencia->fresh(['cajaOrigen', 'cajaDestino']);

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al solicitar transferencia de efectivo', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Autoriza y completa una transferencia de efectivo
     *
     * @param int $transferenciaId
     * @param int $usuarioAutorizaId
     * @param int $usuarioRecibeId
     * @return TransferenciaEfectivo
     * @throws Exception
     */
    public function autorizarYCompletar(int $transferenciaId, int $usuarioAutorizaId, int $usuarioRecibeId): TransferenciaEfectivo
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $transferencia = TransferenciaEfectivo::findOrFail($transferenciaId);

            if (!$transferencia->estaPendiente()) {
                throw new Exception('Solo se pueden autorizar transferencias pendientes');
            }

            // Validar nuevamente las cajas y el saldo
            $cajaOrigen = $transferencia->cajaOrigen;
            $cajaDestino = $transferencia->cajaDestino;

            if (!$cajaOrigen->estaAbierta()) {
                throw new Exception('La caja origen ya no está abierta');
            }

            if (!$cajaDestino->estaAbierta()) {
                throw new Exception('La caja destino ya no está abierta');
            }

            if (!$cajaOrigen->tieneSaldoSuficiente($transferencia->monto)) {
                throw new Exception('Saldo insuficiente en caja origen');
            }

            // Crear movimiento de egreso en caja origen
            $movimientoOrigen = new MovimientoCaja();
            $movimientoOrigen->caja_id = $cajaOrigen->id;
            $movimientoOrigen->tipo_movimiento = 'egreso';
            $movimientoOrigen->concepto = "Transferencia a caja #{$cajaDestino->id} - {$transferencia->concepto}";
            $movimientoOrigen->monto = $transferencia->monto;
            $movimientoOrigen->forma_pago = 'transferencia';
            $movimientoOrigen->referencia = "TRANS-{$transferencia->id}";
            $movimientoOrigen->transferencia_id = $transferencia->id;
            $movimientoOrigen->usuario_id = $usuarioAutorizaId;
            $movimientoOrigen->calcularSaldos();
            $movimientoOrigen->save();

            // Actualizar saldo de caja origen
            $cajaOrigen->disminuirSaldo($transferencia->monto);

            // Crear movimiento de ingreso en caja destino
            $movimientoDestino = new MovimientoCaja();
            $movimientoDestino->caja_id = $cajaDestino->id;
            $movimientoDestino->tipo_movimiento = 'ingreso';
            $movimientoDestino->concepto = "Transferencia desde caja #{$cajaOrigen->id} - {$transferencia->concepto}";
            $movimientoDestino->monto = $transferencia->monto;
            $movimientoDestino->forma_pago = 'transferencia';
            $movimientoDestino->referencia = "TRANS-{$transferencia->id}";
            $movimientoDestino->transferencia_id = $transferencia->id;
            $movimientoDestino->usuario_id = $usuarioRecibeId;
            $movimientoDestino->calcularSaldos();
            $movimientoDestino->save();

            // Actualizar saldo de caja destino
            $cajaDestino->aumentarSaldo($transferencia->monto);

            // Autorizar y completar la transferencia
            $transferencia->autorizar($usuarioAutorizaId);
            $transferencia->completar($usuarioRecibeId);

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia de efectivo autorizada y completada', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al autorizar transferencia de efectivo', [
                'transferencia_id' => $transferenciaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancela una transferencia de efectivo
     *
     * @param int $transferenciaId
     * @return TransferenciaEfectivo
     * @throws Exception
     */
    public function cancelarTransferencia(int $transferenciaId): TransferenciaEfectivo
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $transferencia = TransferenciaEfectivo::findOrFail($transferenciaId);

            if (!$transferencia->estaPendiente()) {
                throw new Exception('Solo se pueden cancelar transferencias pendientes');
            }

            // Cancelar la transferencia
            $transferencia->cancelar();

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia de efectivo cancelada', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cancelar transferencia de efectivo', [
                'transferencia_id' => $transferenciaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ejecuta una transferencia directa e inmediata (sin workflow)
     *
     * Útil para transferencias rápidas entre cajas de la misma sucursal
     * cuando el mismo usuario opera ambas cajas.
     *
     * @param array $data
     * @return TransferenciaEfectivo
     * @throws Exception
     */
    public function transferirDirecto(array $data): TransferenciaEfectivo
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Solicitar transferencia
            $transferencia = $this->solicitarTransferencia($data);

            // Autorizar y completar inmediatamente
            $transferencia = $this->autorizarYCompletar(
                $transferencia->id,
                $data['usuario_id'],
                $data['usuario_id']
            );

            DB::connection('pymes_tenant')->commit();

            Log::info('Transferencia directa ejecutada', [
                'transferencia_id' => $transferencia->id,
            ]);

            return $transferencia;

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error en transferencia directa', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene el historial de transferencias para una caja
     *
     * @param int $cajaId
     * @param bool $soloSalientes Si true, solo muestra transferencias donde la caja es origen
     * @param bool $soloEntrantes Si true, solo muestra transferencias donde la caja es destino
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerHistorialCaja(int $cajaId, bool $soloSalientes = false, bool $soloEntrantes = false)
    {
        $query = TransferenciaEfectivo::with(['cajaOrigen', 'cajaDestino', 'usuarioSolicita']);

        if ($soloSalientes) {
            $query->porCajaOrigen($cajaId);
        } elseif ($soloEntrantes) {
            $query->porCajaDestino($cajaId);
        } else {
            $query->where(function ($q) use ($cajaId) {
                $q->where('caja_origen_id', $cajaId)
                  ->orWhere('caja_destino_id', $cajaId);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
