<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\CobroPago;
use App\Models\CobroVenta;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\MovimientoCaja;
use App\Models\Caja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

/**
 * Servicio de Cobranzas
 *
 * Maneja la lógica de negocio para cobros de cuentas corrientes:
 * - Registro de cobros con aplicación a ventas
 * - Cálculo de interés por mora
 * - Distribución FIFO de montos
 * - Reportes de antigüedad de deuda
 * - Anulación de cobros
 */
class CobroService
{
    /**
     * Registra un cobro completo con aplicación a ventas y formas de pago
     *
     * @param array $data Datos del cobro (sucursal_id, cliente_id, caja_id, observaciones, descuento_aplicado)
     * @param array $ventasAAplicar Array de ['venta_id' => X, 'monto_aplicado' => Y, 'interes_aplicado' => Z]
     * @param array $pagos Array de pagos con estructura del desglose
     * @return Cobro
     * @throws Exception
     */
    public function registrarCobro(array $data, array $ventasAAplicar, array $pagos): Cobro
    {
        return DB::transaction(function () use ($data, $ventasAAplicar, $pagos) {
            $usuarioId = auth()->id();

            // Calcular totales
            $totalAplicadoDeuda = collect($ventasAAplicar)->sum('monto_aplicado');
            $totalInteres = collect($ventasAAplicar)->sum('interes_aplicado');
            $descuentoAplicado = $data['descuento_aplicado'] ?? 0;
            $totalCobrado = collect($pagos)->sum('monto_base');
            $montoAFavor = max(0, $totalCobrado - $totalAplicadoDeuda - $totalInteres + $descuentoAplicado);

            // Generar número de recibo
            $numeroRecibo = $this->generarNumeroRecibo($data['sucursal_id']);

            // Crear el cobro
            $cobro = Cobro::create([
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'],
                'caja_id' => $data['caja_id'] ?? null,
                'numero_recibo' => $numeroRecibo,
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'monto_cobrado' => $totalCobrado,
                'interes_aplicado' => $totalInteres,
                'descuento_aplicado' => $descuentoAplicado,
                'monto_aplicado_a_deuda' => $totalAplicadoDeuda,
                'monto_a_favor' => $montoAFavor,
                'estado' => 'activo',
                'observaciones' => $data['observaciones'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            // Aplicar a ventas
            foreach ($ventasAAplicar as $ventaData) {
                $venta = Venta::findOrFail($ventaData['venta_id']);
                $saldoAnterior = $venta->saldo_pendiente_cache;
                $montoAplicado = $ventaData['monto_aplicado'];
                $interesAplicado = $ventaData['interes_aplicado'] ?? 0;
                $saldoPosterior = max(0, $saldoAnterior - $montoAplicado);

                // Crear registro en cobro_ventas
                CobroVenta::create([
                    'cobro_id' => $cobro->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => $montoAplicado,
                    'interes_aplicado' => $interesAplicado,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => $saldoPosterior,
                ]);

                // Actualizar saldo de la venta
                $venta->update(['saldo_pendiente_cache' => $saldoPosterior]);
            }

            // Registrar pagos
            $caja = $data['caja_id'] ? Caja::find($data['caja_id']) : null;

            foreach ($pagos as $pago) {
                $afectaCaja = ($pago['afecta_caja'] ?? false) && $caja;

                $cobroPago = CobroPago::create([
                    'cobro_id' => $cobro->id,
                    'forma_pago_id' => $pago['forma_pago_id'],
                    'concepto_pago_id' => $pago['concepto_pago_id'] ?? null,
                    'monto_base' => $pago['monto_base'],
                    'ajuste_porcentaje' => $pago['ajuste_porcentaje'] ?? 0,
                    'monto_ajuste' => $pago['monto_ajuste'] ?? 0,
                    'monto_final' => $pago['monto_final'] ?? $pago['monto_base'],
                    'monto_recibido' => $pago['monto_recibido'] ?? null,
                    'vuelto' => $pago['vuelto'] ?? 0,
                    'cuotas' => $pago['cuotas'] ?? 1,
                    'recargo_cuotas_porcentaje' => $pago['recargo_cuotas'] ?? 0,
                    'recargo_cuotas_monto' => $pago['recargo_cuotas_monto'] ?? 0,
                    'monto_cuota' => $pago['monto_cuota'] ?? null,
                    'referencia' => $pago['referencia'] ?? null,
                    'observaciones' => $pago['observaciones'] ?? null,
                    'afecta_caja' => $afectaCaja,
                    'estado' => 'activo',
                ]);

                // Si afecta caja, crear movimiento de caja
                if ($afectaCaja) {
                    $movimiento = MovimientoCaja::crearIngresoCobro(
                        $caja,
                        $cobro,
                        $pago['monto_final'] ?? $pago['monto_base'],
                        $usuarioId
                    );
                    $cobroPago->update(['movimiento_caja_id' => $movimiento->id]);
                }
            }

            // Actualizar cache de saldo del cliente
            $this->actualizarCacheSaldoCliente(Cliente::find($data['cliente_id']));

            return $cobro;
        });
    }

    /**
     * Calcula el interés por mora de una venta
     *
     * @param Venta|object $venta Modelo Venta o objeto con propiedades necesarias
     * @param float|null $tasaMensual Tasa mensual (si null, usa la del cliente)
     * @return float
     */
    public function calcularInteresMora(object $venta, ?float $tasaMensual = null): float
    {
        // Si no tiene fecha de vencimiento, no hay mora
        $fechaVencimiento = $venta->fecha_vencimiento ?? null;
        if (!$fechaVencimiento) {
            return 0;
        }

        // Calcular días de mora
        $hoy = Carbon::now()->startOfDay();
        $vencimiento = Carbon::parse($fechaVencimiento)->startOfDay();

        if ($hoy <= $vencimiento) {
            return 0; // No está vencido
        }

        $diasMora = $hoy->diffInDays($vencimiento);

        // Obtener tasa mensual
        if ($tasaMensual === null) {
            // Si es modelo Venta, obtener del cliente; si es stdClass, intentar obtener de la propiedad cliente
            if ($venta instanceof Venta) {
                $tasaMensual = $venta->cliente?->tasa_interes_mensual ?? 0;
            } else {
                $tasaMensual = $venta->cliente?->tasa_interes_mensual ?? 0;
            }
        }

        if ($tasaMensual <= 0) {
            return 0;
        }

        // Calcular interés proporcional por días
        $tasaDiaria = $tasaMensual / 30;
        $saldoPendiente = $venta->saldo_pendiente_cache ?? 0;
        $interes = round($saldoPendiente * ($tasaDiaria / 100) * $diasMora, 2);

        return $interes;
    }

    /**
     * Obtiene las ventas pendientes de un cliente ordenadas por antigüedad (FIFO)
     *
     * @param int $clienteId
     * @param int|null $sucursalId
     * @return Collection
     */
    public function obtenerVentasPendientesFIFO(int $clienteId, ?int $sucursalId = null): Collection
    {
        $query = Venta::where('cliente_id', $clienteId)
            ->where('es_cuenta_corriente', true)
            ->where('saldo_pendiente_cache', '>', 0)
            ->whereIn('estado', ['completada', 'pendiente']); // Incluir pendiente para CC

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        return $query->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Distribuye un monto entre las ventas usando FIFO (más antigua primero)
     *
     * @param float $monto Monto total a distribuir
     * @param Collection $ventas Ventas ordenadas por antigüedad
     * @return array Array de ['venta_id' => X, 'monto_aplicado' => Y, 'interes_aplicado' => Z]
     */
    public function distribuirMontoFIFO(float $monto, Collection $ventas): array
    {
        $distribucion = [];
        $montoRestante = $monto;

        foreach ($ventas as $venta) {
            if ($montoRestante <= 0) {
                break;
            }

            $saldoPendiente = (float) $venta->saldo_pendiente_cache;
            $interesMora = $this->calcularInteresMora($venta);
            $totalConInteres = $saldoPendiente + $interesMora;

            // Calcular cuánto se puede aplicar
            $montoAAplicar = min($montoRestante, $saldoPendiente);

            // Calcular interés proporcional si no se paga todo
            $interesAAplicar = $saldoPendiente > 0
                ? round($interesMora * ($montoAAplicar / $saldoPendiente), 2)
                : 0;

            $distribucion[] = [
                'venta_id' => $venta->id,
                'venta_numero' => $venta->numero,
                'fecha' => $venta->fecha,
                'saldo_pendiente' => $saldoPendiente,
                'interes_mora' => $interesMora,
                'monto_aplicado' => $montoAAplicar,
                'interes_aplicado' => $interesAAplicar,
            ];

            $montoRestante -= $montoAAplicar;
        }

        return $distribucion;
    }

    /**
     * Genera el reporte de antigüedad de deuda
     *
     * @param int|null $sucursalId
     * @return array
     */
    public function generarReporteAntiguedad(?int $sucursalId = null): array
    {
        $hoy = Carbon::now()->startOfDay();

        // Obtener ventas pendientes con cliente
        $query = Venta::with('cliente')
            ->where('es_cuenta_corriente', true)
            ->where('saldo_pendiente_cache', '>', 0)
            ->where('estado', 'completada');

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        $ventas = $query->get();

        // Agrupar por cliente y por rango de antigüedad
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

        foreach ($ventas as $venta) {
            $clienteId = $venta->cliente_id;

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

            // Calcular antigüedad
            $fechaVenta = Carbon::parse($venta->fecha)->startOfDay();
            $diasAntiguedad = $hoy->diffInDays($fechaVenta);
            $saldo = (float) $venta->saldo_pendiente_cache;

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

        // Convertir a array indexado y ordenar por total descendente
        $reporte['clientes'] = collect($reporte['clientes'])
            ->sortByDesc('total')
            ->values()
            ->toArray();

        return $reporte;
    }

    /**
     * Anula un cobro y revierte los saldos
     *
     * @param int $cobroId
     * @param string $motivo
     * @return Cobro
     * @throws Exception
     */
    public function anularCobro(int $cobroId, string $motivo): Cobro
    {
        return DB::transaction(function () use ($cobroId, $motivo) {
            $cobro = Cobro::with(['cobroVentas.venta', 'pagos'])->findOrFail($cobroId);

            if ($cobro->estaAnulado()) {
                throw new Exception('El cobro ya está anulado');
            }

            // Si el cobro está asociado a un cierre de turno, no se puede anular
            if ($cobro->cierre_turno_id) {
                throw new Exception('No se puede anular un cobro que ya fue cerrado en un turno');
            }

            // Usar el método del modelo que ya tiene la lógica
            $cobro->anular(auth()->id(), $motivo);

            // Actualizar cache del cliente
            $this->actualizarCacheSaldoCliente($cobro->cliente);

            return $cobro->fresh();
        });
    }

    /**
     * Genera un número de recibo único para la sucursal
     *
     * @param int $sucursalId
     * @return string
     */
    public function generarNumeroRecibo(int $sucursalId): string
    {
        $prefijo = 'RC-' . str_pad($sucursalId, 2, '0', STR_PAD_LEFT) . '-';

        $ultimoRecibo = Cobro::where('sucursal_id', $sucursalId)
            ->where('numero_recibo', 'like', $prefijo . '%')
            ->orderBy('id', 'desc')
            ->value('numero_recibo');

        if ($ultimoRecibo) {
            $ultimoNumero = (int) substr($ultimoRecibo, strlen($prefijo));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo . str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Actualiza el cache de saldo deudor del cliente
     *
     * @param Cliente $cliente
     * @return void
     */
    public function actualizarCacheSaldoCliente(Cliente $cliente): void
    {
        $saldoDeudor = Venta::where('cliente_id', $cliente->id)
            ->where('es_cuenta_corriente', true)
            ->where('estado', 'completada')
            ->sum('saldo_pendiente_cache');

        // Calcular saldo a favor (montos pagados de más)
        $saldoAFavor = Cobro::where('cliente_id', $cliente->id)
            ->where('estado', 'activo')
            ->sum('monto_a_favor');

        // Calcular días máximos de mora
        $ventaVencida = Venta::where('cliente_id', $cliente->id)
            ->where('es_cuenta_corriente', true)
            ->where('estado', 'completada')
            ->where('saldo_pendiente_cache', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->orderBy('fecha_vencimiento', 'asc')
            ->first();

        $diasMoraMax = 0;
        if ($ventaVencida && $ventaVencida->fecha_vencimiento) {
            $diasMoraMax = Carbon::now()->diffInDays(Carbon::parse($ventaVencida->fecha_vencimiento));
        }

        $cliente->update([
            'saldo_deudor_cache' => $saldoDeudor,
            'saldo_a_favor_cache' => $saldoAFavor,
            'dias_mora_max' => $diasMoraMax,
            'ultimo_movimiento_cc_at' => now(),
        ]);
    }

    /**
     * Obtiene el historial de cuenta corriente de un cliente
     *
     * @param int $clienteId
     * @param int|null $sucursalId
     * @param int $limit
     * @return Collection
     */
    public function obtenerMovimientosCuentaCorriente(int $clienteId, ?int $sucursalId = null, int $limit = 50): Collection
    {
        // Obtener ventas a cuenta corriente
        $queryVentas = Venta::where('cliente_id', $clienteId)
            ->where('es_cuenta_corriente', true)
            ->where('estado', 'completada');

        if ($sucursalId) {
            $queryVentas->where('sucursal_id', $sucursalId);
        }

        $ventas = $queryVentas->get()->map(function ($venta) {
            return [
                'tipo' => 'venta',
                'fecha' => $venta->fecha,
                'descripcion' => "Venta #{$venta->numero}",
                'debe' => $venta->total_final,
                'haber' => 0,
                'referencia_id' => $venta->id,
                'referencia_tipo' => 'venta',
            ];
        });

        // Obtener cobros
        $queryCobros = Cobro::with('cobroVentas')
            ->where('cliente_id', $clienteId)
            ->where('estado', 'activo');

        if ($sucursalId) {
            $queryCobros->where('sucursal_id', $sucursalId);
        }

        $cobros = $queryCobros->get()->map(function ($cobro) {
            return [
                'tipo' => 'cobro',
                'fecha' => Carbon::parse($cobro->fecha),
                'descripcion' => "Recibo #{$cobro->numero_recibo}",
                'debe' => 0,
                'haber' => $cobro->monto_aplicado_a_deuda + $cobro->interes_aplicado,
                'referencia_id' => $cobro->id,
                'referencia_tipo' => 'cobro',
            ];
        });

        // Unir y ordenar por fecha descendente
        return $ventas->concat($cobros)
            ->sortByDesc('fecha')
            ->take($limit)
            ->values();
    }
}
