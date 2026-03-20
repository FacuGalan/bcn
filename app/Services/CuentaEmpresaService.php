<?php

namespace App\Services;

use App\Models\ConceptoMovimientoCuenta;
use App\Models\CuentaEmpresa;
use App\Models\MovimientoCuentaEmpresa;
use App\Models\TransferenciaCuentaEmpresa;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CuentaEmpresaService
{
    /**
     * Obtener cuentas disponibles para una sucursal
     */
    public static function getCuentasDisponibles(int $sucursalId): Collection
    {
        return CuentaEmpresa::activas()
            ->with('moneda')
            ->orderBy('orden')
            ->get()
            ->filter(function ($cuenta) use ($sucursalId) {
                return $cuenta->estaDisponibleEnSucursal($sucursalId);
            })
            ->values();
    }

    /**
     * Registrar movimiento automático (llamado desde ventas/cobros)
     */
    public static function registrarMovimientoAutomatico(
        CuentaEmpresa $cuenta,
        string $tipo,
        float $monto,
        string $conceptoCodigo,
        string $origenTipo,
        int $origenId,
        string $descripcion,
        int $usuarioId,
        ?int $sucursalId = null
    ): MovimientoCuentaEmpresa {
        return DB::connection('pymes_tenant')->transaction(function () use (
            $cuenta, $tipo, $monto, $conceptoCodigo, $origenTipo, $origenId, $descripcion, $usuarioId, $sucursalId
        ) {
            // Bloquear la cuenta para evitar race conditions
            $cuenta = CuentaEmpresa::lockForUpdate()->find($cuenta->id);

            $concepto = ConceptoMovimientoCuenta::where('codigo', $conceptoCodigo)->first();

            $saldoAnterior = $cuenta->saldo_actual;
            $saldoPosterior = $tipo === 'ingreso'
                ? $saldoAnterior + $monto
                : $saldoAnterior - $monto;

            $movimiento = MovimientoCuentaEmpresa::create([
                'cuenta_empresa_id' => $cuenta->id,
                'tipo' => $tipo,
                'concepto_movimiento_cuenta_id' => $concepto?->id,
                'concepto_descripcion' => $descripcion,
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
                'usuario_id' => $usuarioId,
                'sucursal_id' => $sucursalId,
                'estado' => 'activo',
            ]);

            // Actualizar saldo de la cuenta
            $cuenta->update(['saldo_actual' => $saldoPosterior]);

            Log::info('Movimiento cuenta empresa registrado', [
                'movimiento_id' => $movimiento->id,
                'cuenta_id' => $cuenta->id,
                'tipo' => $tipo,
                'monto' => $monto,
                'origen' => "{$origenTipo}#{$origenId}",
            ]);

            return $movimiento;
        });
    }

    /**
     * Revertir movimiento (contraasiento append-only)
     */
    public static function revertirMovimiento(
        int $movimientoId,
        string $motivo,
        int $usuarioId
    ): MovimientoCuentaEmpresa {
        return DB::connection('pymes_tenant')->transaction(function () use ($movimientoId, $motivo, $usuarioId) {
            $movimientoOriginal = MovimientoCuentaEmpresa::findOrFail($movimientoId);

            if ($movimientoOriginal->esAnulado()) {
                throw new Exception('El movimiento ya fue anulado');
            }

            // Bloquear la cuenta
            $cuenta = CuentaEmpresa::lockForUpdate()->find($movimientoOriginal->cuenta_empresa_id);

            // Tipo inverso para contraasiento
            $tipoInverso = $movimientoOriginal->tipo === 'ingreso' ? 'egreso' : 'ingreso';

            $saldoAnterior = $cuenta->saldo_actual;
            $saldoPosterior = $tipoInverso === 'ingreso'
                ? $saldoAnterior + $movimientoOriginal->monto
                : $saldoAnterior - $movimientoOriginal->monto;

            // Crear contraasiento
            $contraasiento = MovimientoCuentaEmpresa::create([
                'cuenta_empresa_id' => $cuenta->id,
                'tipo' => $tipoInverso,
                'concepto_movimiento_cuenta_id' => $movimientoOriginal->concepto_movimiento_cuenta_id,
                'concepto_descripcion' => "Anulación: {$motivo}",
                'monto' => $movimientoOriginal->monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'origen_tipo' => $movimientoOriginal->origen_tipo,
                'origen_id' => $movimientoOriginal->origen_id,
                'usuario_id' => $usuarioId,
                'sucursal_id' => $movimientoOriginal->sucursal_id,
                'estado' => 'activo',
                'observaciones' => $motivo,
            ]);

            // Marcar original como anulado (ambos quedan activos, se cancelan matemáticamente)
            $movimientoOriginal->update([
                'estado' => 'anulado',
                'anulado_por_movimiento_id' => $contraasiento->id,
            ]);

            // Actualizar saldo
            $cuenta->update(['saldo_actual' => $saldoPosterior]);

            Log::info('Movimiento cuenta empresa revertido', [
                'movimiento_original_id' => $movimientoId,
                'contraasiento_id' => $contraasiento->id,
                'monto' => $movimientoOriginal->monto,
                'motivo' => $motivo,
            ]);

            return $contraasiento;
        });
    }

    /**
     * Registrar movimiento manual
     */
    public static function registrarMovimientoManual(
        int $cuentaId,
        string $tipo,
        float $monto,
        ?int $conceptoId,
        string $descripcion,
        int $usuarioId,
        ?int $sucursalId = null,
        ?string $observaciones = null
    ): MovimientoCuentaEmpresa {
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a cero');
        }

        return DB::connection('pymes_tenant')->transaction(function () use (
            $cuentaId, $tipo, $monto, $conceptoId, $descripcion, $usuarioId, $sucursalId, $observaciones
        ) {
            $cuenta = CuentaEmpresa::lockForUpdate()->findOrFail($cuentaId);

            $saldoAnterior = $cuenta->saldo_actual;
            $saldoPosterior = $tipo === 'ingreso'
                ? $saldoAnterior + $monto
                : $saldoAnterior - $monto;

            $movimiento = MovimientoCuentaEmpresa::create([
                'cuenta_empresa_id' => $cuenta->id,
                'tipo' => $tipo,
                'concepto_movimiento_cuenta_id' => $conceptoId,
                'concepto_descripcion' => $descripcion,
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'origen_tipo' => MovimientoCuentaEmpresa::ORIGEN_MANUAL,
                'origen_id' => null,
                'usuario_id' => $usuarioId,
                'sucursal_id' => $sucursalId,
                'estado' => 'activo',
                'observaciones' => $observaciones,
            ]);

            $cuenta->update(['saldo_actual' => $saldoPosterior]);

            Log::info('Movimiento manual en cuenta empresa', [
                'movimiento_id' => $movimiento->id,
                'cuenta_id' => $cuenta->id,
                'tipo' => $tipo,
                'monto' => $monto,
            ]);

            return $movimiento;
        });
    }

    /**
     * Transferir entre cuentas propias
     */
    public static function transferirEntreCuentas(
        int $cuentaOrigenId,
        int $cuentaDestinoId,
        float $monto,
        string $concepto,
        int $usuarioId
    ): TransferenciaCuentaEmpresa {
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a cero');
        }

        if ($cuentaOrigenId === $cuentaDestinoId) {
            throw new Exception('La cuenta origen y destino deben ser diferentes');
        }

        return DB::connection('pymes_tenant')->transaction(function () use (
            $cuentaOrigenId, $cuentaDestinoId, $monto, $concepto, $usuarioId
        ) {
            $cuentaOrigen = CuentaEmpresa::lockForUpdate()->findOrFail($cuentaOrigenId);
            $cuentaDestino = CuentaEmpresa::lockForUpdate()->findOrFail($cuentaDestinoId);

            // Validar misma moneda
            if ($cuentaOrigen->moneda_id !== $cuentaDestino->moneda_id) {
                throw new Exception('Las cuentas deben tener la misma moneda para transferir');
            }

            $conceptoTransf = ConceptoMovimientoCuenta::where('codigo', 'transferencia_entre_cuentas')->first();

            // Movimiento egreso en cuenta origen
            $saldoAnteriorOrigen = $cuentaOrigen->saldo_actual;
            $saldoPosteriorOrigen = $saldoAnteriorOrigen - $monto;

            $movOrigen = MovimientoCuentaEmpresa::create([
                'cuenta_empresa_id' => $cuentaOrigen->id,
                'tipo' => 'egreso',
                'concepto_movimiento_cuenta_id' => $conceptoTransf?->id,
                'concepto_descripcion' => "Transferencia a {$cuentaDestino->nombre}: {$concepto}",
                'monto' => $monto,
                'saldo_anterior' => $saldoAnteriorOrigen,
                'saldo_posterior' => $saldoPosteriorOrigen,
                'origen_tipo' => MovimientoCuentaEmpresa::ORIGEN_TRANSFERENCIA,
                'usuario_id' => $usuarioId,
                'estado' => 'activo',
            ]);

            $cuentaOrigen->update(['saldo_actual' => $saldoPosteriorOrigen]);

            // Movimiento ingreso en cuenta destino
            $saldoAnteriorDestino = $cuentaDestino->saldo_actual;
            $saldoPosteriorDestino = $saldoAnteriorDestino + $monto;

            $movDestino = MovimientoCuentaEmpresa::create([
                'cuenta_empresa_id' => $cuentaDestino->id,
                'tipo' => 'ingreso',
                'concepto_movimiento_cuenta_id' => $conceptoTransf?->id,
                'concepto_descripcion' => "Transferencia desde {$cuentaOrigen->nombre}: {$concepto}",
                'monto' => $monto,
                'saldo_anterior' => $saldoAnteriorDestino,
                'saldo_posterior' => $saldoPosteriorDestino,
                'origen_tipo' => MovimientoCuentaEmpresa::ORIGEN_TRANSFERENCIA,
                'usuario_id' => $usuarioId,
                'estado' => 'activo',
            ]);

            $cuentaDestino->update(['saldo_actual' => $saldoPosteriorDestino]);

            // Crear registro de transferencia
            $transferencia = TransferenciaCuentaEmpresa::create([
                'cuenta_origen_id' => $cuentaOrigen->id,
                'cuenta_destino_id' => $cuentaDestino->id,
                'monto' => $monto,
                'moneda_id' => $cuentaOrigen->moneda_id,
                'concepto' => $concepto,
                'movimiento_origen_id' => $movOrigen->id,
                'movimiento_destino_id' => $movDestino->id,
                'usuario_id' => $usuarioId,
            ]);

            // Actualizar origen_id en ambos movimientos
            $movOrigen->update(['origen_id' => $transferencia->id]);
            $movDestino->update(['origen_id' => $transferencia->id]);

            Log::info('Transferencia entre cuentas empresa', [
                'transferencia_id' => $transferencia->id,
                'origen' => $cuentaOrigen->nombre,
                'destino' => $cuentaDestino->nombre,
                'monto' => $monto,
            ]);

            return $transferencia;
        });
    }
}
