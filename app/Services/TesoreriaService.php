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
use App\Models\CierreTurno;
use App\Models\CierreTurnoCaja;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\Cobro;
use App\Models\CobroPago;
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
     * Rechaza una rendición pendiente y revierte completamente el cierre de turno asociado.
     *
     * Maneja 3 escenarios:
     * - Individual: 1 caja, 1 rendición
     * - Grupo CON fondo común: N cajas, 1 rendición consolidada
     * - Grupo SIN fondo común: N cajas, N rendiciones individuales
     *
     * @throws \Exception
     */
    public static function rechazarYRevertirCierre(
        RendicionFondo $rendicion,
        int $usuarioId,
        ?string $motivo = null
    ): bool {
        // ── Validaciones previas ──

        if (!$rendicion->esta_pendiente) {
            throw new \Exception('La rendición no está pendiente, no se puede rechazar');
        }

        $cierre = $rendicion->cierreTurno;
        if (!$cierre) {
            throw new \Exception('La rendición no tiene un cierre de turno asociado');
        }

        if ($cierre->estaRevertido()) {
            throw new \Exception('El cierre de turno ya fue revertido anteriormente');
        }

        // Validar que las cajas no estén abiertas con un nuevo turno
        foreach ($cierre->detalleCajas as $detalleCaja) {
            $caja = Caja::find($detalleCaja->caja_id);
            if ($caja && $caja->estado === 'abierta') {
                throw new \Exception('No se puede revertir: las cajas ya tienen un turno activo');
            }
        }

        // Validar que sea el último cierre para esa caja/grupo
        if ($cierre->esIndividual()) {
            $detalleCaja = $cierre->detalleCajas->first();
            if (!$detalleCaja) {
                throw new \Exception('No se encontró el detalle de caja del cierre');
            }
            $ultimoCierre = CierreTurno::noRevertidos()
                ->whereHas('detalleCajas', fn($q) => $q->where('caja_id', $detalleCaja->caja_id))
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (!$ultimoCierre || $ultimoCierre->id !== $cierre->id) {
                throw new \Exception('Solo se puede revertir el último cierre de esta caja');
            }
        } else {
            // Grupal
            $ultimoCierreGrupo = CierreTurno::noRevertidos()
                ->where('grupo_cierre_id', $cierre->grupo_cierre_id)
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (!$ultimoCierreGrupo || $ultimoCierreGrupo->id !== $cierre->id) {
                throw new \Exception('Solo se puede revertir el último cierre de este grupo');
            }
        }

        // Determinar escenario
        $esGrupalConFondoComun = $cierre->esGrupal()
            && $cierre->grupoCierre
            && $cierre->grupoCierre->usaFondoComun();

        // Para grupo sin fondo común, verificar que TODAS las rendiciones estén pendientes
        if ($cierre->esGrupal() && !$esGrupalConFondoComun) {
            $rendicionesDelCierre = RendicionFondo::where('cierre_turno_id', $cierre->id)->get();
            $noPendientes = $rendicionesDelCierre->filter(fn($r) => !$r->esta_pendiente);
            if ($noPendientes->isNotEmpty()) {
                throw new \Exception('No se puede revertir: hay rendiciones de este cierre que ya fueron confirmadas o procesadas');
            }
        }

        // ── Ejecutar reversión en transacción ──

        return DB::transaction(function () use ($rendicion, $cierre, $usuarioId, $motivo, $esGrupalConFondoComun) {
            $tesoreria = $rendicion->tesoreria;

            if ($esGrupalConFondoComun) {
                // ── Escenario B: Grupo CON fondo común ──
                // Una sola rendición consolidada, sin MovimientoCaja

                // Verificar saldo suficiente en tesorería para el contra-movimiento
                if (!$tesoreria->tieneSaldoSuficiente($rendicion->monto_entregado)) {
                    throw new \Exception('Saldo insuficiente en tesorería para revertir la rendición');
                }

                // 1. Rechazar la rendición
                $rendicion->rechazar($usuarioId, $motivo);

                // 2. Contra-movimiento en tesorería (egreso)
                $tesoreria->egreso(
                    $rendicion->monto_entregado,
                    "Reversión de rendición #{$rendicion->id} - Rechazo de cierre",
                    $usuarioId,
                    MovimientoTesoreria::REFERENCIA_RENDICION,
                    $rendicion->id,
                    'Contra-movimiento por rechazo: ' . ($motivo ?? 'Sin motivo')
                );

                // 3. Restaurar fondo común del grupo al valor que tenía ANTES del cierre
                // cierre.total_saldo_inicial = grupo.saldo_fondo_comun al momento del cierre
                // NO usar monto_sistema porque ese ya incluye ingresos/egresos que se re-contarían
                $grupo = $cierre->grupoCierre;
                $grupo->update(['saldo_fondo_comun' => $cierre->total_saldo_inicial]);

                // 4. Reabrir todas las cajas del grupo con su saldo operativo individual
                // Aunque el fondo es común, cada caja acumula saldo_actual durante la operación
                // CierreTurnoCaja guarda total_ingresos y total_egresos por caja
                foreach ($cierre->detalleCajas as $detalleCaja) {
                    $caja = Caja::find($detalleCaja->caja_id);
                    if ($caja) {
                        $saldoCaja = $detalleCaja->total_ingresos - $detalleCaja->total_egresos;
                        $caja->update([
                            'estado' => 'abierta',
                            'saldo_actual' => $saldoCaja,
                            'fecha_cierre' => null,
                            'usuario_cierre_id' => null,
                        ]);
                    }
                }

            } elseif ($cierre->esGrupal()) {
                // ── Escenario C: Grupo SIN fondo común ──
                // Múltiples rendiciones, una por caja

                $rendicionesDelCierre = RendicionFondo::where('cierre_turno_id', $cierre->id)->get();

                // Verificar saldo total necesario
                $montoTotalARevertir = $rendicionesDelCierre->sum('monto_entregado');
                if (!$tesoreria->tieneSaldoSuficiente($montoTotalARevertir)) {
                    throw new \Exception('Saldo insuficiente en tesorería para revertir todas las rendiciones');
                }

                foreach ($rendicionesDelCierre as $rend) {
                    // 1. Rechazar cada rendición
                    $rend->rechazar($usuarioId, $motivo);

                    // 2. Contra-movimiento en tesorería (egreso)
                    $tesoreria->refresh(); // refrescar saldo actualizado
                    $tesoreria->egreso(
                        $rend->monto_entregado,
                        "Reversión de rendición #{$rend->id} - Rechazo de cierre",
                        $usuarioId,
                        MovimientoTesoreria::REFERENCIA_RENDICION,
                        $rend->id,
                        'Contra-movimiento por rechazo: ' . ($motivo ?? 'Sin motivo')
                    );

                    // 3. Contra-movimiento en caja (ingreso)
                    if ($rend->movimiento_caja_id) {
                        MovimientoCaja::create([
                            'caja_id' => $rend->caja_id,
                            'tipo' => 'ingreso',
                            'concepto' => "Reversión de rendición #{$rend->id} - Rechazo de cierre",
                            'monto' => $rend->monto_entregado,
                            'usuario_id' => $usuarioId,
                            'referencia_tipo' => 'rendicion_fondo',
                            'referencia_id' => $rend->id,
                        ]);
                    }
                }

                // 4. Reabrir cada caja con su saldo sistema
                foreach ($cierre->detalleCajas as $detalleCaja) {
                    $caja = Caja::find($detalleCaja->caja_id);
                    if ($caja) {
                        $caja->update([
                            'estado' => 'abierta',
                            'saldo_actual' => $detalleCaja->saldo_sistema,
                            'fecha_cierre' => null,
                            'usuario_cierre_id' => null,
                        ]);
                    }
                }

            } else {
                // ── Escenario A: Caja individual ──

                // Verificar saldo suficiente
                if (!$tesoreria->tieneSaldoSuficiente($rendicion->monto_entregado)) {
                    throw new \Exception('Saldo insuficiente en tesorería para revertir la rendición');
                }

                // 1. Rechazar la rendición
                $rendicion->rechazar($usuarioId, $motivo);

                // 2. Contra-movimiento en tesorería (egreso)
                $tesoreria->egreso(
                    $rendicion->monto_entregado,
                    "Reversión de rendición #{$rendicion->id} - Rechazo de cierre",
                    $usuarioId,
                    MovimientoTesoreria::REFERENCIA_RENDICION,
                    $rendicion->id,
                    'Contra-movimiento por rechazo: ' . ($motivo ?? 'Sin motivo')
                );

                // 3. Contra-movimiento en caja (ingreso)
                if ($rendicion->movimiento_caja_id) {
                    MovimientoCaja::create([
                        'caja_id' => $rendicion->caja_id,
                        'tipo' => 'ingreso',
                        'concepto' => "Reversión de rendición #{$rendicion->id} - Rechazo de cierre",
                        'monto' => $rendicion->monto_entregado,
                        'usuario_id' => $usuarioId,
                        'referencia_tipo' => 'rendicion_fondo',
                        'referencia_id' => $rendicion->id,
                    ]);
                }

                // 4. Reabrir la caja
                $detalleCaja = $cierre->detalleCajas->first();
                $caja = Caja::find($detalleCaja->caja_id);
                if ($caja) {
                    $caja->update([
                        'estado' => 'abierta',
                        'saldo_actual' => $detalleCaja->saldo_sistema,
                        'fecha_cierre' => null,
                        'usuario_cierre_id' => null,
                    ]);
                }
            }

            // ── Pasos comunes a los 3 escenarios ──

            // 5. Limpiar cierre_turno_id de transacciones asociadas
            Venta::where('cierre_turno_id', $cierre->id)->update(['cierre_turno_id' => null]);
            VentaPago::where('cierre_turno_id', $cierre->id)->update(['cierre_turno_id' => null]);
            Cobro::where('cierre_turno_id', $cierre->id)->update(['cierre_turno_id' => null]);
            CobroPago::where('cierre_turno_id', $cierre->id)->update(['cierre_turno_id' => null]);
            MovimientoCaja::where('cierre_turno_id', $cierre->id)->update(['cierre_turno_id' => null]);

            // 6. Marcar el cierre como revertido
            $cierre->marcarComoRevertido($usuarioId, $motivo);

            Log::info('Cierre de turno revertido', [
                'cierre_turno_id' => $cierre->id,
                'tipo' => $cierre->tipo,
                'rendicion_id' => $rendicion->id,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
            ]);

            return true;
        });
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
