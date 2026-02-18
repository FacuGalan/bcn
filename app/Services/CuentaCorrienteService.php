<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\CobroVenta;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio de Cuenta Corriente Unificada
 *
 * Maneja toda la lógica de cuenta corriente de clientes:
 * - Registro de movimientos de deuda y saldo a favor
 * - Cálculo de saldos en tiempo real
 * - Extractos de cuenta corriente
 * - Anulaciones con contraasientos
 * - Actualización de caches
 *
 * PRINCIPIOS:
 * 1. Los saldos SIEMPRE se calculan sumando movimientos activos (no se guardan intermedios)
 * 2. Las anulaciones crean contraasientos (no borran movimientos)
 * 3. Todo movimiento tiene trazabilidad al documento origen
 * 4. El lock se aplica solo al actualizar cache del cliente
 */
class CuentaCorrienteService
{
    // ==================== REGISTRO DE MOVIMIENTOS ====================

    /**
     * Registra el movimiento de CC cuando se crea una venta con pago a cuenta corriente
     *
     * @param VentaPago $ventaPago El pago de tipo cuenta corriente
     * @param int $usuarioId
     * @return MovimientoCuentaCorriente|null
     */
    public function registrarMovimientoVenta(VentaPago $ventaPago, int $usuarioId): ?MovimientoCuentaCorriente
    {
        $venta = $ventaPago->venta;

        // Solo registrar si tiene cliente
        if (!$venta->cliente_id) {
            return null;
        }

        // Solo registrar si es cuenta corriente
        if (!$ventaPago->es_cuenta_corriente) {
            return null;
        }

        // Actualizar saldo pendiente del pago
        $ventaPago->update([
            'saldo_pendiente' => $ventaPago->monto_final,
        ]);

        // Crear el movimiento
        $movimiento = MovimientoCuentaCorriente::crearMovimientoVenta($ventaPago, $usuarioId);

        // Actualizar cache del cliente
        $this->actualizarCacheCliente($venta->cliente_id, $venta->sucursal_id);

        return $movimiento;
    }

    /**
     * Registra los movimientos de CC cuando se procesa un cobro
     *
     * @param Cobro $cobro
     * @param array $cobroVentas Array de CobroVenta creados
     * @param int $usuarioId
     * @return array Array de MovimientoCuentaCorriente creados
     */
    public function registrarMovimientosCobro(Cobro $cobro, array $cobroVentas, int $usuarioId): array
    {
        $movimientos = [];

        // 1. Si usó saldo a favor
        if ($cobro->saldo_favor_usado > 0) {
            $movimientos[] = MovimientoCuentaCorriente::crearMovimientoUsoSaldoFavor(
                $cobro,
                $cobro->saldo_favor_usado,
                $usuarioId
            );
        }

        // 2. Movimientos por cada venta cobrada
        foreach ($cobroVentas as $cobroVenta) {
            if ($cobroVenta->monto_aplicado > 0) {
                $movimientos[] = MovimientoCuentaCorriente::crearMovimientoCobro(
                    $cobro,
                    $cobroVenta,
                    $usuarioId
                );
            }
        }

        // 3. Si generó saldo a favor (anticipo o excedente)
        if ($cobro->monto_a_favor > 0) {
            $movimientos[] = MovimientoCuentaCorriente::crearMovimientoAnticipo(
                $cobro,
                $cobro->monto_a_favor,
                $usuarioId
            );
        }

        // Actualizar cache del cliente
        $this->actualizarCacheCliente($cobro->cliente_id, $cobro->sucursal_id);

        return $movimientos;
    }

    /**
     * Registra movimiento de anticipo puro (sin aplicar a deuda)
     *
     * @param Cobro $cobro
     * @param int $usuarioId
     * @return MovimientoCuentaCorriente
     */
    public function registrarMovimientoAnticipo(Cobro $cobro, int $usuarioId): MovimientoCuentaCorriente
    {
        $movimiento = MovimientoCuentaCorriente::crearMovimientoAnticipo(
            $cobro,
            $cobro->monto_cobrado,
            $usuarioId
        );

        // Actualizar cache del cliente
        $this->actualizarCacheCliente($cobro->cliente_id, $cobro->sucursal_id);

        return $movimiento;
    }

    // ==================== ANULACIONES ====================

    /**
     * Anula los movimientos de una venta (cuando se cancela la venta)
     *
     * @param Venta $venta
     * @param string $motivo
     * @param int $usuarioId
     * @return array Contraasientos creados
     */
    public function anularMovimientosVenta(Venta $venta, string $motivo, int $usuarioId): array
    {
        $contraasientos = [];

        // Buscar todos los movimientos activos de esta venta
        $movimientos = MovimientoCuentaCorriente::where('venta_id', $venta->id)
            ->where('estado', 'activo')
            ->get();

        foreach ($movimientos as $movimiento) {
            $contraasientos[] = MovimientoCuentaCorriente::crearContraasiento(
                $movimiento,
                $motivo,
                $usuarioId
            );
        }

        // Actualizar cache del cliente
        if ($venta->cliente_id) {
            $this->actualizarCacheCliente($venta->cliente_id, $venta->sucursal_id);
        }

        return $contraasientos;
    }

    /**
     * Anula los movimientos de un cobro
     *
     * Flujo:
     * 1. Si el cobro generó saldo a favor que fue usado posteriormente,
     *    el saldo usado se convierte en deuda del cliente
     * 2. Se crean contraasientos para todos los movimientos del cobro
     *
     * @param Cobro $cobro
     * @param string $motivo
     * @param int $usuarioId
     * @return array ['contraasientos' => [], 'deuda_generada' => float]
     */
    public function anularMovimientosCobro(Cobro $cobro, string $motivo, int $usuarioId): array
    {
        $contraasientos = [];
        $deudaGenerada = 0;

        // Buscar todos los movimientos activos de este cobro
        $movimientos = MovimientoCuentaCorriente::where('cobro_id', $cobro->id)
            ->where('estado', 'activo')
            ->get();

        // Si el cobro generó saldo a favor, verificar cuánto fue usado
        if ($cobro->monto_a_favor > 0) {
            $saldoFavorActual = MovimientoCuentaCorriente::calcularSaldoFavor($cobro->cliente_id);

            // Si el saldo actual es menor que lo que generó este cobro,
            // significa que parte fue usada
            $saldoUsadoDelAnticipo = max(0, $cobro->monto_a_favor - $saldoFavorActual);

            if ($saldoUsadoDelAnticipo > 0) {
                // El cliente usó saldo que ya no existe → se convierte en deuda
                $deudaGenerada = $saldoUsadoDelAnticipo;

                // Crear movimiento de ajuste por la deuda
                MovimientoCuentaCorriente::create([
                    'cliente_id' => $cobro->cliente_id,
                    'sucursal_id' => $cobro->sucursal_id,
                    'fecha' => now()->toDateString(),
                    'tipo' => MovimientoCuentaCorriente::TIPO_AJUSTE_DEBITO,
                    'debe' => $deudaGenerada,
                    'haber' => 0,
                    'saldo_favor_debe' => 0,
                    'saldo_favor_haber' => 0,
                    'documento_tipo' => MovimientoCuentaCorriente::DOC_COBRO,
                    'documento_id' => $cobro->id,
                    'cobro_id' => $cobro->id,
                    'concepto' => "Deuda por anulación de anticipo - Recibo {$cobro->numero_recibo} (saldo usado: $" . number_format($saldoUsadoDelAnticipo, 2) . ")",
                    'observaciones' => $motivo,
                    'usuario_id' => $usuarioId,
                ]);
            }
        }

        // Crear contraasientos para cada movimiento
        foreach ($movimientos as $movimiento) {
            $contraasientos[] = MovimientoCuentaCorriente::crearContraasiento(
                $movimiento,
                $motivo,
                $usuarioId
            );
        }

        // Actualizar cache del cliente
        $this->actualizarCacheCliente($cobro->cliente_id, $cobro->sucursal_id);

        return [
            'contraasientos' => $contraasientos,
            'deuda_generada' => $deudaGenerada,
        ];
    }

    // ==================== CONSULTAS ====================

    /**
     * Obtiene el extracto de cuenta corriente de un cliente en una sucursal
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @param int $limite
     * @param Carbon|null $desde
     * @param Carbon|null $hasta
     * @return Collection
     */
    public function obtenerExtracto(
        int $clienteId,
        int $sucursalId,
        int $limite = 100,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): Collection {
        $query = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->with(['venta', 'ventaPago', 'cobro']);

        if ($desde) {
            $query->where('fecha', '>=', $desde);
        }

        if ($hasta) {
            $query->where('fecha', '<=', $hasta);
        }

        // Obtener movimientos ordenados cronológicamente
        $movimientos = $query->orderBy('fecha', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit($limite)
            ->get();

        // Calcular saldo acumulado
        $saldoDeudor = 0;
        $saldoFavor = 0;

        return $movimientos->map(function ($mov) use (&$saldoDeudor, &$saldoFavor) {
            $saldoDeudor += $mov->debe - $mov->haber;
            $saldoFavor += $mov->saldo_favor_haber - $mov->saldo_favor_debe;

            return [
                'id' => $mov->id,
                'fecha' => $mov->fecha,
                'tipo' => $mov->tipo,
                'concepto' => $mov->concepto,
                'descripcion_comprobantes' => $mov->descripcion_comprobantes,
                'debe' => (float) $mov->debe,
                'haber' => (float) $mov->haber,
                'saldo_favor_debe' => (float) $mov->saldo_favor_debe,
                'saldo_favor_haber' => (float) $mov->saldo_favor_haber,
                'saldo_deudor' => $saldoDeudor,
                'saldo_favor' => $saldoFavor,
                'documento_tipo' => $mov->documento_tipo,
                'documento_id' => $mov->documento_id,
                'venta_id' => $mov->venta_id,
                'venta_numero' => $mov->venta?->numero,
                'cobro_id' => $mov->cobro_id,
                'cobro_numero' => $mov->cobro?->numero_recibo,
                'created_at' => $mov->created_at,
            ];
        });
    }

    /**
     * Obtiene el extracto de cuenta corriente (versión simplificada para mostrar)
     * Retorna los movimientos en orden de más reciente a más antiguo
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @param int $limite
     * @return Collection
     */
    public function obtenerExtractoResumido(int $clienteId, int $sucursalId, int $limite = 50): Collection
    {
        // Primero obtener todos los movimientos para calcular saldo acumulado
        $todosMovimientos = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->with(['venta', 'cobro'])
            ->orderBy('fecha', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Calcular saldo acumulado
        $saldoDeudor = 0;
        $saldoFavor = 0;

        // Crear mapa de contraasientos -> movimiento original
        // (buscar movimientos que tienen anulado_por_movimiento_id apuntando a otro)
        $contraasientoAOriginal = [];
        foreach ($todosMovimientos as $mov) {
            if ($mov->anulado_por_movimiento_id) {
                $contraasientoAOriginal[$mov->anulado_por_movimiento_id] = $mov->id;
            }
        }

        $conSaldo = $todosMovimientos->map(function ($mov) use (&$saldoDeudor, &$saldoFavor, $contraasientoAOriginal) {
            $saldoDeudor += $mov->debe - $mov->haber;
            $saldoFavor += $mov->saldo_favor_haber - $mov->saldo_favor_debe;

            $esAnulacion = str_contains($mov->tipo ?? '', 'anulacion');
            // Si es un contraasiento, buscar el movimiento original que anula
            $movimientoAnuladoId = $esAnulacion ? ($contraasientoAOriginal[$mov->id] ?? null) : null;

            return [
                'id' => $mov->id,
                'fecha' => $mov->fecha,
                'hora' => $mov->created_at?->format('H:i'),
                'created_at' => $mov->created_at,
                'tipo' => $mov->tipo,
                'concepto' => $mov->concepto,
                'descripcion_comprobantes' => $mov->descripcion_comprobantes,
                'debe' => (float) $mov->debe,
                'haber' => (float) $mov->haber,
                'saldo_deudor' => $saldoDeudor,
                'saldo_favor' => $saldoFavor,
                'venta_id' => $mov->venta_id,
                'venta_numero' => $mov->venta?->numero,
                'cobro_id' => $mov->cobro_id,
                'cobro_numero' => $mov->cobro?->numero_recibo,
                'es_anulacion' => $esAnulacion,
                'movimiento_anulado_id' => $movimientoAnuladoId,
                'anulado_por_movimiento_id' => $mov->anulado_por_movimiento_id,
            ];
        });

        // Retornar los últimos N movimientos en orden inverso (más reciente primero)
        return $conSaldo->reverse()->take($limite)->values();
    }

    /**
     * Obtiene las ventas pendientes de pago de un cliente en una sucursal
     * (agrupadas por VentaPago de cuenta corriente)
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @return Collection
     */
    public function obtenerVentasPendientes(int $clienteId, int $sucursalId): Collection
    {
        return VentaPago::whereHas('venta', function ($q) use ($clienteId, $sucursalId) {
            $q->where('cliente_id', $clienteId)
                ->where('sucursal_id', $sucursalId)
                ->whereIn('estado', ['completada', 'pendiente']);
        })
            ->where('es_cuenta_corriente', true)
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', 'activo')
            ->with(['venta.cliente', 'formaPago'])
            ->orderBy('created_at', 'asc') // FIFO
            ->get()
            ->map(function ($ventaPago) {
                $venta = $ventaPago->venta;

                return [
                    'venta_pago_id' => $ventaPago->id,
                    'venta_id' => $venta->id,
                    'numero' => $venta->numero,
                    'fecha' => $venta->fecha,
                    'fecha_vencimiento' => $venta->fecha_vencimiento,
                    'monto_original' => (float) $ventaPago->monto_final,
                    'saldo_pendiente' => (float) $ventaPago->saldo_pendiente,
                    'dias_mora' => $venta->fecha_vencimiento && now()->gt($venta->fecha_vencimiento)
                        ? now()->diffInDays($venta->fecha_vencimiento)
                        : 0,
                ];
            });
    }

    /**
     * Obtiene los saldos actuales de un cliente en una sucursal
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @return array
     */
    public function obtenerSaldos(int $clienteId, int $sucursalId): array
    {
        return MovimientoCuentaCorriente::obtenerSaldos($clienteId, $sucursalId);
    }

    /**
     * Obtiene los saldos globales de un cliente (todas las sucursales)
     *
     * @param int $clienteId
     * @return array
     */
    public function obtenerSaldosGlobales(int $clienteId): array
    {
        return MovimientoCuentaCorriente::obtenerSaldosGlobales($clienteId);
    }

    // ==================== CACHE ====================

    /**
     * Actualiza el cache de saldos del cliente
     * Usa lock para evitar race conditions
     *
     * @param int $clienteId
     * @param int|null $sucursalId
     */
    public function actualizarCacheCliente(int $clienteId, ?int $sucursalId = null): void
    {
        DB::transaction(function () use ($clienteId, $sucursalId) {
            // Lock en el cliente
            $cliente = Cliente::lockForUpdate()->find($clienteId);

            if (!$cliente) {
                return;
            }

            // Calcular saldos globales
            $saldosGlobales = MovimientoCuentaCorriente::obtenerSaldosGlobales($clienteId);

            // Calcular días de mora máxima
            $diasMoraMax = $this->calcularDiasMoraMax($clienteId);

            // Actualizar cache global
            $cliente->update([
                'saldo_deudor_cache' => max(0, $saldosGlobales['saldo_deudor']),
                'saldo_a_favor_cache' => max(0, $saldosGlobales['saldo_favor']),
                'dias_mora_max' => $diasMoraMax,
                'ultimo_movimiento_cc_at' => now(),
            ]);

            // Si se especifica sucursal, actualizar también el pivot
            if ($sucursalId) {
                $saldosSucursal = MovimientoCuentaCorriente::obtenerSaldos($clienteId, $sucursalId);

                $cliente->sucursales()->updateExistingPivot($sucursalId, [
                    'saldo_actual' => max(0, $saldosSucursal['saldo_deudor']),
                ]);
            }
        });
    }

    /**
     * Calcula los días de mora máxima de un cliente
     *
     * @param int $clienteId
     * @return int
     */
    protected function calcularDiasMoraMax(int $clienteId): int
    {
        $ventaPagoMasAntigua = VentaPago::whereHas('venta', function ($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId)
                ->whereIn('ventas.estado', ['completada', 'pendiente'])
                ->whereNotNull('fecha_vencimiento')
                ->where('fecha_vencimiento', '<', now());
        })
            ->where('venta_pagos.es_cuenta_corriente', true)
            ->where('venta_pagos.saldo_pendiente', '>', 0)
            ->where('venta_pagos.estado', 'activo')
            ->join('ventas', 'venta_pagos.venta_id', '=', 'ventas.id')
            ->orderBy('ventas.fecha_vencimiento', 'asc')
            ->select('venta_pagos.*', 'ventas.fecha_vencimiento')
            ->first();

        if (!$ventaPagoMasAntigua || !$ventaPagoMasAntigua->fecha_vencimiento) {
            return 0;
        }

        return Carbon::now()->diffInDays(Carbon::parse($ventaPagoMasAntigua->fecha_vencimiento));
    }

    // ==================== REPORTES ====================

    /**
     * Genera reporte de antigüedad de deuda
     *
     * @param int $sucursalId
     * @return array
     */
    public function generarReporteAntiguedad(int $sucursalId): array
    {
        $hoy = Carbon::now()->startOfDay();

        // Obtener todos los pagos CC pendientes con su venta
        $pagos = VentaPago::whereHas('venta', function ($q) use ($sucursalId) {
            $q->where('sucursal_id', $sucursalId)
                ->whereIn('estado', ['completada', 'pendiente']);
        })
            ->where('es_cuenta_corriente', true)
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', 'activo')
            ->with(['venta.cliente'])
            ->get();

        $reporte = [
            'clientes' => [],
            'totales' => [
                '0_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                '90_mas' => 0,
                'total' => 0,
            ],
        ];

        foreach ($pagos as $pago) {
            $venta = $pago->venta;
            $clienteId = $venta->cliente_id;

            if (!$clienteId) {
                continue;
            }

            if (!isset($reporte['clientes'][$clienteId])) {
                $reporte['clientes'][$clienteId] = [
                    'cliente' => $venta->cliente,
                    '0_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    '90_mas' => 0,
                    'total' => 0,
                ];
            }

            // Calcular antigüedad desde la fecha de la venta
            $fechaVenta = Carbon::parse($venta->fecha)->startOfDay();
            $diasAntiguedad = $hoy->diffInDays($fechaVenta);
            $saldo = (float) $pago->saldo_pendiente;

            // Clasificar por rango
            if ($diasAntiguedad <= 30) {
                $rango = '0_30';
            } elseif ($diasAntiguedad <= 60) {
                $rango = '31_60';
            } elseif ($diasAntiguedad <= 90) {
                $rango = '61_90';
            } else {
                $rango = '90_mas';
            }

            $reporte['clientes'][$clienteId][$rango] += $saldo;
            $reporte['clientes'][$clienteId]['total'] += $saldo;
            $reporte['totales'][$rango] += $saldo;
            $reporte['totales']['total'] += $saldo;
        }

        // Ordenar clientes por total descendente
        $reporte['clientes'] = collect($reporte['clientes'])
            ->sortByDesc('total')
            ->values()
            ->toArray();

        return $reporte;
    }

    /**
     * Calcula interés por mora para un pago pendiente
     *
     * @param VentaPago $ventaPago
     * @param float|null $tasaMensual
     * @return float
     */
    public function calcularInteresMora(VentaPago $ventaPago, ?float $tasaMensual = null): float
    {
        $venta = $ventaPago->venta;

        if (!$venta->fecha_vencimiento) {
            return 0;
        }

        $hoy = Carbon::now()->startOfDay();
        $vencimiento = Carbon::parse($venta->fecha_vencimiento)->startOfDay();

        if ($hoy <= $vencimiento) {
            return 0;
        }

        $diasMora = $hoy->diffInDays($vencimiento);

        if ($tasaMensual === null) {
            $tasaMensual = $venta->cliente?->tasa_interes_mensual ?? 0;
        }

        if ($tasaMensual <= 0) {
            return 0;
        }

        $tasaDiaria = $tasaMensual / 30;
        $interes = round($ventaPago->saldo_pendiente * ($tasaDiaria / 100) * $diasMora, 2);

        return $interes;
    }

    /**
     * Distribuye un monto entre pagos pendientes usando FIFO
     *
     * @param float $monto
     * @param Collection $ventasPendientes
     * @param float|null $tasaInteresMensual
     * @return array
     */
    public function distribuirMontoFIFO(float $monto, Collection $ventasPendientes, ?float $tasaInteresMensual = null): array
    {
        $distribucion = [];
        $montoRestante = $monto;

        foreach ($ventasPendientes as $ventaPendiente) {
            if ($montoRestante <= 0) {
                break;
            }

            $saldoPendiente = (float) $ventaPendiente['saldo_pendiente'];

            // Calcular interés si aplica
            $interesMora = 0;
            if ($tasaInteresMensual && $ventaPendiente['dias_mora'] > 0) {
                $tasaDiaria = $tasaInteresMensual / 30;
                $interesMora = round($saldoPendiente * ($tasaDiaria / 100) * $ventaPendiente['dias_mora'], 2);
            }

            // Calcular monto a aplicar (sin exceder el saldo pendiente)
            $montoAAplicar = min($montoRestante, $saldoPendiente);

            // Calcular interés proporcional
            $interesAAplicar = $saldoPendiente > 0
                ? round($interesMora * ($montoAAplicar / $saldoPendiente), 2)
                : 0;

            $distribucion[] = [
                'venta_pago_id' => $ventaPendiente['venta_pago_id'],
                'venta_id' => $ventaPendiente['venta_id'],
                'numero' => $ventaPendiente['numero'],
                'saldo_pendiente' => $saldoPendiente,
                'interes_mora' => $interesMora,
                'monto_aplicado' => $montoAAplicar,
                'interes_aplicado' => $interesAAplicar,
            ];

            $montoRestante -= $montoAAplicar;
        }

        return $distribucion;
    }
}
