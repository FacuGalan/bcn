<?php

namespace App\Services;

use App\Models\Tesoreria;
use App\Models\Caja;
use App\Models\GrupoCierre;
use App\Models\MovimientoTesoreria;
use App\Models\MovimientoCaja;
use App\Models\ProvisionFondo;
use App\Models\RendicionFondo;
use App\Models\DepositoBancario;
use App\Models\CuentaBancaria;
use App\Models\ArqueoTesoreria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Servicio de Tesorería
 *
 * Gestiona todas las operaciones de tesorería:
 * - Provisión de fondos a cajas (apertura)
 * - Rendición de fondos de cajas (cierre)
 * - Transferencias entre entidades
 * - Depósitos bancarios
 * - Arqueos
 */
class TesoreriaService
{
    /**
     * Obtiene o crea la tesorería de una sucursal
     */
    public static function obtenerOCrear(int $sucursalId): Tesoreria
    {
        return Tesoreria::firstOrCreate(
            ['sucursal_id' => $sucursalId],
            [
                'nombre' => 'Tesorería Principal',
                'saldo_actual' => 0,
                'activo' => true,
            ]
        );
    }

    /**
     * Provisiona fondo de tesorería a una caja (apertura de caja)
     *
     * @param Tesoreria $tesoreria
     * @param Caja $caja
     * @param float $monto
     * @param int $usuarioId
     * @param string|null $observaciones
     * @return ProvisionFondo
     * @throws \Exception
     */
    public static function provisionarFondo(
        Tesoreria $tesoreria,
        Caja $caja,
        float $monto,
        int $usuarioId,
        ?string $observaciones = null
    ): ProvisionFondo {
        if ($monto <= 0) {
            throw new \Exception('El monto debe ser mayor a cero');
        }

        if (!$tesoreria->tieneSaldoSuficiente($monto)) {
            throw new \Exception('Saldo insuficiente en tesorería');
        }

        return DB::transaction(function () use ($tesoreria, $caja, $monto, $usuarioId, $observaciones) {
            // 1. Crear registro de provisión
            $provision = ProvisionFondo::create([
                'tesoreria_id' => $tesoreria->id,
                'caja_id' => $caja->id,
                'monto' => $monto,
                'usuario_entrega_id' => $usuarioId,
                'fecha' => now(),
                'estado' => ProvisionFondo::ESTADO_CONFIRMADO,
                'observaciones' => $observaciones,
            ]);

            // 2. Registrar egreso en tesorería (con trazabilidad)
            $movimientoTesoreria = $tesoreria->egreso(
                $monto,
                "Provisión de fondo a caja {$caja->nombre}",
                $usuarioId,
                MovimientoTesoreria::REFERENCIA_PROVISION,
                $provision->id
            );

            // 3. Registrar ingreso en caja (con trazabilidad)
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => 'ingreso',
                'concepto' => 'Provisión de fondo desde tesorería',
                'monto' => $monto,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'provision_fondo',
                'referencia_id' => $provision->id,
            ]);

            // 4. Actualizar saldo de la caja
            $caja->saldo_actual += $monto;
            $caja->save();

            // 5. Vincular movimientos con la provisión
            $provision->update([
                'movimiento_tesoreria_id' => $movimientoTesoreria->id,
                'movimiento_caja_id' => $movimientoCaja->id,
            ]);

            Log::info('Provisión de fondo realizada', [
                'provision_id' => $provision->id,
                'tesoreria_id' => $tesoreria->id,
                'caja_id' => $caja->id,
                'monto' => $monto,
                'usuario_id' => $usuarioId,
            ]);

            return $provision;
        });
    }

    /**
     * Provisiona fondo de tesorería a un grupo con fondo común
     *
     * @param Tesoreria $tesoreria
     * @param GrupoCierre $grupo
     * @param float $monto
     * @param int $usuarioId
     * @param string|null $observaciones
     * @return ProvisionFondo
     * @throws \Exception
     */
    public static function provisionarFondoGrupo(
        Tesoreria $tesoreria,
        GrupoCierre $grupo,
        float $monto,
        int $usuarioId,
        ?string $observaciones = null
    ): ProvisionFondo {
        if ($monto <= 0) {
            throw new \Exception('El monto debe ser mayor a cero');
        }

        if (!$tesoreria->tieneSaldoSuficiente($monto)) {
            throw new \Exception('Saldo insuficiente en tesorería. Disponible: $' . number_format($tesoreria->saldo_actual, 2));
        }

        return DB::transaction(function () use ($tesoreria, $grupo, $monto, $usuarioId, $observaciones) {
            // Usar la primera caja del grupo como referencia para el registro
            $cajaReferencia = $grupo->cajas->first();

            // 1. Crear registro de provisión
            $provision = ProvisionFondo::create([
                'tesoreria_id' => $tesoreria->id,
                'caja_id' => $cajaReferencia?->id, // Referencia para auditoría
                'monto' => $monto,
                'usuario_entrega_id' => $usuarioId,
                'fecha' => now(),
                'estado' => ProvisionFondo::ESTADO_CONFIRMADO,
                'observaciones' => $observaciones ?? "Fondo común para grupo: {$grupo->nombre}",
            ]);

            // 2. Registrar egreso en tesorería
            $nombreGrupo = $grupo->nombre ?? "Grupo #{$grupo->id}";
            $movimientoTesoreria = $tesoreria->egreso(
                $monto,
                "Provisión de fondo común - {$nombreGrupo}",
                $usuarioId,
                MovimientoTesoreria::REFERENCIA_PROVISION,
                $provision->id
            );

            // 3. Vincular movimiento con la provisión
            $provision->update([
                'movimiento_tesoreria_id' => $movimientoTesoreria->id,
            ]);

            Log::info('Provisión de fondo común de grupo realizada', [
                'provision_id' => $provision->id,
                'tesoreria_id' => $tesoreria->id,
                'grupo_id' => $grupo->id,
                'monto' => $monto,
                'usuario_id' => $usuarioId,
            ]);

            return $provision;
        });
    }

    /**
     * Rinde fondo de una caja a tesorería (cierre de caja)
     *
     * @param Caja $caja
     * @param Tesoreria $tesoreria
     * @param float $montoDeclarado Monto declarado por el cajero
     * @param float $montoSistema Monto calculado por el sistema
     * @param int $usuarioId
     * @param int|null $cierreTurnoId
     * @param string|null $observaciones
     * @return RendicionFondo
     */
    public static function rendirFondo(
        Caja $caja,
        Tesoreria $tesoreria,
        float $montoDeclarado,
        float $montoSistema,
        int $usuarioId,
        ?int $cierreTurnoId = null,
        ?string $observaciones = null
    ): RendicionFondo {
        return DB::transaction(function () use ($caja, $tesoreria, $montoDeclarado, $montoSistema, $usuarioId, $cierreTurnoId, $observaciones) {
            $diferencia = $montoDeclarado - $montoSistema;
            $montoEntregado = $montoDeclarado; // Se entrega lo declarado

            // 1. Crear registro de rendición
            $rendicion = RendicionFondo::create([
                'caja_id' => $caja->id,
                'tesoreria_id' => $tesoreria->id,
                'monto_declarado' => $montoDeclarado,
                'monto_sistema' => $montoSistema,
                'monto_entregado' => $montoEntregado,
                'diferencia' => $diferencia,
                'usuario_entrega_id' => $usuarioId,
                'cierre_turno_id' => $cierreTurnoId,
                'fecha' => now(),
                'estado' => RendicionFondo::ESTADO_PENDIENTE,
                'observaciones' => $observaciones,
            ]);

            // 2. Registrar egreso en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => 'egreso',
                'concepto' => 'Rendición de fondo a tesorería',
                'monto' => $montoEntregado,
                'usuario_id' => $usuarioId,
                'referencia_tipo' => 'rendicion_fondo',
                'referencia_id' => $rendicion->id,
            ]);

            // 3. Registrar ingreso en tesorería
            $movimientoTesoreria = $tesoreria->ingreso(
                $montoEntregado,
                "Rendición de caja {$caja->nombre}",
                $usuarioId,
                MovimientoTesoreria::REFERENCIA_RENDICION,
                $rendicion->id
            );

            // 4. Actualizar saldo de la caja
            $caja->saldo_actual -= $montoEntregado;
            $caja->save();

            // 5. Vincular movimientos con la rendición
            $rendicion->update([
                'movimiento_tesoreria_id' => $movimientoTesoreria->id,
                'movimiento_caja_id' => $movimientoCaja->id,
            ]);

            Log::info('Rendición de fondo realizada', [
                'rendicion_id' => $rendicion->id,
                'caja_id' => $caja->id,
                'tesoreria_id' => $tesoreria->id,
                'monto_entregado' => $montoEntregado,
                'diferencia' => $diferencia,
                'usuario_id' => $usuarioId,
            ]);

            return $rendicion;
        });
    }

    /**
     * Rinde fondo común de un grupo a tesorería (cierre de grupo con fondo común)
     *
     * @param GrupoCierre $grupo
     * @param Tesoreria $tesoreria
     * @param float $montoDeclarado Monto declarado por el cajero
     * @param float $montoSistema Monto calculado por el sistema
     * @param int $usuarioId
     * @param int|null $cierreTurnoId
     * @param string|null $observaciones
     * @return RendicionFondo
     */
    public static function rendirFondoGrupo(
        GrupoCierre $grupo,
        Tesoreria $tesoreria,
        float $montoDeclarado,
        float $montoSistema,
        int $usuarioId,
        ?int $cierreTurnoId = null,
        ?string $observaciones = null
    ): RendicionFondo {
        return DB::transaction(function () use ($grupo, $tesoreria, $montoDeclarado, $montoSistema, $usuarioId, $cierreTurnoId, $observaciones) {
            $diferencia = $montoDeclarado - $montoSistema;
            $montoEntregado = $montoDeclarado;

            // Usar la primera caja del grupo como referencia
            $cajaReferencia = $grupo->cajas->first();

            // 1. Crear registro de rendición (usando caja de referencia)
            $rendicion = RendicionFondo::create([
                'caja_id' => $cajaReferencia?->id,
                'tesoreria_id' => $tesoreria->id,
                'monto_declarado' => $montoDeclarado,
                'monto_sistema' => $montoSistema,
                'monto_entregado' => $montoEntregado,
                'diferencia' => $diferencia,
                'usuario_entrega_id' => $usuarioId,
                'cierre_turno_id' => $cierreTurnoId,
                'fecha' => now(),
                'estado' => RendicionFondo::ESTADO_PENDIENTE,
                'observaciones' => $observaciones ?? "Cierre de grupo: {$grupo->nombre}",
            ]);

            // 2. Registrar ingreso en tesorería
            $nombreGrupo = $grupo->nombre ?? "Grupo #{$grupo->id}";
            $movimientoTesoreria = $tesoreria->ingreso(
                $montoEntregado,
                "Rendición de fondo común - {$nombreGrupo}",
                $usuarioId,
                MovimientoTesoreria::REFERENCIA_RENDICION,
                $rendicion->id
            );

            // 3. Vincular movimiento con la rendición
            $rendicion->update([
                'movimiento_tesoreria_id' => $movimientoTesoreria->id,
            ]);

            Log::info('Rendición de fondo común de grupo realizada', [
                'rendicion_id' => $rendicion->id,
                'grupo_id' => $grupo->id,
                'tesoreria_id' => $tesoreria->id,
                'monto_entregado' => $montoEntregado,
                'diferencia' => $diferencia,
                'usuario_id' => $usuarioId,
            ]);

            return $rendicion;
        });
    }

    /**
     * Confirma una rendición pendiente
     */
    public static function confirmarRendicion(RendicionFondo $rendicion, int $usuarioRecibeId): bool
    {
        if (!$rendicion->esta_pendiente) {
            return false;
        }

        return $rendicion->confirmarRecepcion($usuarioRecibeId);
    }

    /**
     * Registra un depósito bancario
     *
     * @param Tesoreria $tesoreria
     * @param CuentaBancaria $cuenta
     * @param float $monto
     * @param Carbon $fechaDeposito
     * @param int $usuarioId
     * @param string|null $numeroComprobante
     * @param string|null $observaciones
     * @return DepositoBancario
     * @throws \Exception
     */
    public static function registrarDeposito(
        Tesoreria $tesoreria,
        CuentaBancaria $cuenta,
        float $monto,
        Carbon $fechaDeposito,
        int $usuarioId,
        ?string $numeroComprobante = null,
        ?string $observaciones = null
    ): DepositoBancario {
        if ($monto <= 0) {
            throw new \Exception('El monto debe ser mayor a cero');
        }

        if (!$tesoreria->tieneSaldoSuficiente($monto)) {
            throw new \Exception('Saldo insuficiente en tesorería');
        }

        return DB::transaction(function () use ($tesoreria, $cuenta, $monto, $fechaDeposito, $usuarioId, $numeroComprobante, $observaciones) {
            // 1. Crear registro de depósito
            $deposito = DepositoBancario::create([
                'tesoreria_id' => $tesoreria->id,
                'cuenta_bancaria_id' => $cuenta->id,
                'monto' => $monto,
                'fecha_deposito' => $fechaDeposito,
                'numero_comprobante' => $numeroComprobante,
                'usuario_id' => $usuarioId,
                'estado' => DepositoBancario::ESTADO_PENDIENTE,
                'observaciones' => $observaciones,
            ]);

            // 2. Registrar egreso en tesorería
            MovimientoTesoreria::crearDeposito($tesoreria, $monto, $usuarioId, $deposito->id);

            Log::info('Depósito bancario registrado', [
                'deposito_id' => $deposito->id,
                'tesoreria_id' => $tesoreria->id,
                'cuenta_id' => $cuenta->id,
                'monto' => $monto,
            ]);

            return $deposito;
        });
    }

    /**
     * Confirma un depósito bancario
     */
    public static function confirmarDeposito(DepositoBancario $deposito): bool
    {
        return $deposito->confirmar();
    }

    /**
     * Realiza un arqueo de tesorería
     *
     * @param Tesoreria $tesoreria
     * @param float $saldoContado
     * @param int $usuarioId
     * @param string|null $observaciones
     * @return ArqueoTesoreria
     */
    public static function realizarArqueo(
        Tesoreria $tesoreria,
        float $saldoContado,
        int $usuarioId,
        ?string $observaciones = null
    ): ArqueoTesoreria {
        $arqueo = ArqueoTesoreria::realizar($tesoreria, $saldoContado, $usuarioId, $observaciones);

        Log::info('Arqueo de tesorería realizado', [
            'arqueo_id' => $arqueo->id,
            'tesoreria_id' => $tesoreria->id,
            'saldo_sistema' => $arqueo->saldo_sistema,
            'saldo_contado' => $arqueo->saldo_contado,
            'diferencia' => $arqueo->diferencia,
        ]);

        return $arqueo;
    }

    /**
     * Aprueba un arqueo y opcionalmente aplica el ajuste
     */
    public static function aprobarArqueo(ArqueoTesoreria $arqueo, int $supervisorId, bool $aplicarAjuste = false): bool
    {
        return $arqueo->aprobar($supervisorId, $aplicarAjuste);
    }

    /**
     * Transferencia entre tesorerías (para sucursales múltiples)
     */
    public static function transferirEntreTesorerías(
        Tesoreria $origen,
        Tesoreria $destino,
        float $monto,
        int $usuarioId,
        ?string $observaciones = null
    ): array {
        if ($monto <= 0) {
            throw new \Exception('El monto debe ser mayor a cero');
        }

        if (!$origen->tieneSaldoSuficiente($monto)) {
            throw new \Exception('Saldo insuficiente en tesorería origen');
        }

        return DB::transaction(function () use ($origen, $destino, $monto, $usuarioId, $observaciones) {
            // Egreso de origen
            $movimientoEgreso = $origen->egreso(
                $monto,
                "Transferencia a tesorería {$destino->nombre}",
                $usuarioId,
                'transferencia',
                $destino->id,
                $observaciones
            );

            // Ingreso en destino
            $movimientoIngreso = $destino->ingreso(
                $monto,
                "Transferencia desde tesorería {$origen->nombre}",
                $usuarioId,
                'transferencia',
                $origen->id,
                $observaciones
            );

            return [
                'egreso' => $movimientoEgreso,
                'ingreso' => $movimientoIngreso,
            ];
        });
    }

    // ==================== MÉTODOS DE CONSULTA ====================

    /**
     * Obtiene el resumen de movimientos de un período
     */
    public static function resumenPeriodo(Tesoreria $tesoreria, Carbon $desde, Carbon $hasta): array
    {
        $movimientos = $tesoreria->movimientosDelPeriodo($desde, $hasta);

        return [
            'periodo' => [
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
            ],
            'total_ingresos' => $tesoreria->totalIngresosDelPeriodo($desde, $hasta),
            'total_egresos' => $tesoreria->totalEgresosDelPeriodo($desde, $hasta),
            'cantidad_movimientos' => $movimientos->count(),
            'saldo_actual' => $tesoreria->saldo_actual,
            'por_concepto' => $movimientos->groupBy('concepto')->map(function ($grupo) {
                return [
                    'cantidad' => $grupo->count(),
                    'total_ingresos' => $grupo->where('tipo', 'ingreso')->sum('monto'),
                    'total_egresos' => $grupo->where('tipo', 'egreso')->sum('monto'),
                ];
            }),
        ];
    }

    /**
     * Obtiene las rendiciones pendientes de una tesorería
     */
    public static function rendicionesPendientes(Tesoreria $tesoreria)
    {
        return RendicionFondo::where('tesoreria_id', $tesoreria->id)
            ->pendientes()
            ->with(['caja', 'usuarioEntrega'])
            ->orderBy('fecha', 'desc')
            ->get();
    }

    /**
     * Obtiene los depósitos pendientes de una tesorería
     */
    public static function depositosPendientes(Tesoreria $tesoreria)
    {
        return DepositoBancario::where('tesoreria_id', $tesoreria->id)
            ->pendientes()
            ->with(['cuentaBancaria'])
            ->orderBy('fecha_deposito', 'desc')
            ->get();
    }

    /**
     * Obtiene estadísticas del día
     */
    public static function estadisticasHoy(Tesoreria $tesoreria): array
    {
        $hoy = today();

        return [
            'provisiones' => ProvisionFondo::where('tesoreria_id', $tesoreria->id)
                ->whereDate('fecha', $hoy)
                ->confirmados()
                ->sum('monto'),
            'rendiciones' => RendicionFondo::where('tesoreria_id', $tesoreria->id)
                ->whereDate('fecha', $hoy)
                ->sum('monto_entregado'),
            'depositos' => DepositoBancario::where('tesoreria_id', $tesoreria->id)
                ->whereDate('fecha_deposito', $hoy)
                ->sum('monto'),
            'cantidad_provisiones' => ProvisionFondo::where('tesoreria_id', $tesoreria->id)
                ->whereDate('fecha', $hoy)
                ->count(),
            'cantidad_rendiciones' => RendicionFondo::where('tesoreria_id', $tesoreria->id)
                ->whereDate('fecha', $hoy)
                ->count(),
        ];
    }
}
