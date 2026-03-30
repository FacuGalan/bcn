<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\CobroPago;
use App\Models\CobroVenta;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Models\VentaPago;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * @param  array  $data  Datos del cobro (sucursal_id, cliente_id, caja_id, observaciones, descuento_aplicado)
     * @param  array  $ventasAAplicar  Array de ['venta_pago_id' => X, 'venta_id' => Y, 'monto_aplicado' => Z, 'interes_aplicado' => W]
     * @param  array  $pagos  Array de pagos con estructura del desglose
     *
     * @throws Exception
     */
    public function registrarCobro(array $data, array $ventasAAplicar, array $pagos): Cobro
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($data, $ventasAAplicar, $pagos) {
            $usuarioId = auth()->id();

            // Determinar tipo de cobro (anticipo si no hay ventas aplicadas)
            $tipo = empty($ventasAAplicar) ? 'anticipo' : 'cobro';

            // Calcular totales de deuda
            $totalAplicadoDeuda = collect($ventasAAplicar)->sum('monto_aplicado');
            $totalInteres = collect($ventasAAplicar)->sum('interes_aplicado');
            $descuentoAplicado = $data['descuento_aplicado'] ?? 0;

            // Calcular total cobrado y excedente
            $totalCobradoBase = collect($pagos)->sum('monto_base');
            $totalExcedente = collect($pagos)->sum('monto_excedente') ?: 0;

            // El monto total cobrado incluye todo lo que el cliente pagó
            $totalCobrado = $totalCobradoBase;

            // El monto a favor es el excedente que no se aplicó a deuda
            $montoAFavor = $tipo === 'anticipo'
                ? $totalCobrado
                : max(0, $totalExcedente);

            // Saldo a favor usado (del saldo disponible del cliente)
            $saldoFavorUsado = (float) ($data['saldo_favor_usado'] ?? 0);

            // Validar que el cliente tiene suficiente saldo a favor
            if ($saldoFavorUsado > 0) {
                $saldoFavorDisponible = MovimientoCuentaCorriente::calcularSaldoFavor($data['cliente_id']);
                if ($saldoFavorUsado > $saldoFavorDisponible) {
                    throw new Exception('El monto de saldo a favor a usar ($'.number_format($saldoFavorUsado, 2).') excede el saldo disponible ($'.number_format($saldoFavorDisponible, 2).')');
                }
            }

            // Generar número de recibo
            $numeroRecibo = $this->generarNumeroRecibo($data['sucursal_id']);

            // Crear el cobro
            $cobro = Cobro::create([
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'],
                'caja_id' => $data['caja_id'] ?? null,
                'numero_recibo' => $numeroRecibo,
                'tipo' => $tipo,
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'monto_cobrado' => $totalCobrado,
                'interes_aplicado' => $totalInteres,
                'descuento_aplicado' => $descuentoAplicado,
                'monto_aplicado_a_deuda' => $totalAplicadoDeuda,
                'monto_a_favor' => $montoAFavor,
                'saldo_favor_usado' => $saldoFavorUsado,
                'estado' => 'activo',
                'observaciones' => $data['observaciones'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            $cobroVentasCreados = [];

            // Aplicar a ventas (ahora trabajamos con VentaPago)
            foreach ($ventasAAplicar as $ventaData) {
                // Obtener el VentaPago específico
                $ventaPagoId = $ventaData['venta_pago_id'] ?? null;
                $ventaId = $ventaData['venta_id'];
                $montoAplicado = $ventaData['monto_aplicado'];
                $interesAplicado = $ventaData['interes_aplicado'] ?? 0;

                // Si no viene venta_pago_id, buscar el pago CC de esa venta (compatibilidad)
                if (! $ventaPagoId) {
                    $ventaPago = VentaPago::where('venta_id', $ventaId)
                        ->where('es_cuenta_corriente', true)
                        ->where('saldo_pendiente', '>', 0)
                        ->first();

                    if ($ventaPago) {
                        $ventaPagoId = $ventaPago->id;
                    }
                } else {
                    $ventaPago = VentaPago::find($ventaPagoId);
                }

                // Obtener saldos
                $saldoAnterior = $ventaPago ? $ventaPago->saldo_pendiente : 0;
                $saldoPosterior = max(0, $saldoAnterior - $montoAplicado);

                // Crear registro en cobro_ventas con venta_pago_id
                $cobroVenta = CobroVenta::create([
                    'cobro_id' => $cobro->id,
                    'venta_id' => $ventaId,
                    'venta_pago_id' => $ventaPagoId,
                    'monto_aplicado' => $montoAplicado,
                    'interes_aplicado' => $interesAplicado,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => $saldoPosterior,
                ]);

                $cobroVentasCreados[] = $cobroVenta;

                // Actualizar saldo del VentaPago
                if ($ventaPago) {
                    $ventaPago->aplicarCobro($montoAplicado);
                }

                // Actualizar saldo_pendiente_cache de la Venta (para compatibilidad)
                $venta = Venta::find($ventaId);
                if ($venta) {
                    // Recalcular saldo pendiente sumando todos los VentaPago CC pendientes
                    $saldoVenta = VentaPago::where('venta_id', $ventaId)
                        ->where('es_cuenta_corriente', true)
                        ->sum('saldo_pendiente');
                    $updateData = ['saldo_pendiente_cache' => $saldoVenta];

                    // Si el saldo quedó en 0 y la venta estaba pendiente, marcarla como completada
                    if ($saldoVenta <= 0 && $venta->estado === 'pendiente') {
                        $updateData['estado'] = 'completada';
                    }

                    $venta->update($updateData);
                }
            }

            // Registrar pagos
            $caja = $data['caja_id'] ? Caja::find($data['caja_id']) : null;

            foreach ($pagos as $pago) {
                $afectaCaja = ($pago['afecta_caja'] ?? false) && $caja;

                $fpMoneda = FormaPago::find($pago['forma_pago_id']);

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
                    'recargo_cuotas_monto' => $pago['monto_recargo_cuotas'] ?? $pago['recargo_cuotas_monto'] ?? 0,
                    'monto_cuota' => $pago['monto_cuota'] ?? (($pago['cuotas'] ?? 1) > 1 ? round(($pago['monto_final'] ?? $pago['monto_base']) / ($pago['cuotas'] ?? 1), 2) : null),
                    'referencia' => $pago['referencia'] ?? null,
                    'observaciones' => $pago['observaciones'] ?? null,
                    'afecta_caja' => $afectaCaja,
                    'estado' => 'activo',
                    'moneda_id' => $pago['moneda_id'] ?? $fpMoneda?->moneda_id ?? Moneda::obtenerPrincipal()?->id,
                    'monto_moneda_original' => $pago['monto_moneda_original'] ?? null,
                    'tipo_cambio_tasa' => $pago['tipo_cambio_tasa'] ?? null,
                ]);

                // Si afecta caja, crear movimiento de caja
                if ($afectaCaja) {
                    $vuelto = (float) ($pago['vuelto'] ?? 0);
                    $esMonedaExtranjera = ! empty($pago['es_moneda_extranjera']) && ! empty($pago['tipo_cambio_tasa']);
                    $montoFinal = $pago['monto_final'] ?? $pago['monto_base'];

                    if ($esMonedaExtranjera && $vuelto > 0) {
                        // Moneda extranjera con vuelto: ingreso por el TOTAL recibido + egreso por vuelto
                        $montoRecibido = (float) ($pago['monto_recibido'] ?? $montoFinal);
                        $movimiento = MovimientoCaja::crearIngresoCobro($caja, $cobro, $montoRecibido, $usuarioId);

                        $monedaPrincipalId = Moneda::obtenerPrincipal()?->id;
                        $tcRecord = $pago['moneda_id'] && $monedaPrincipalId
                            ? TipoCambio::ultimaTasa($pago['moneda_id'], $monedaPrincipalId)
                            : null;
                        $movimiento->update([
                            'moneda_id' => $pago['moneda_id'],
                            'monto_moneda_original' => $pago['monto_moneda_original'],
                            'tipo_cambio_id' => $tcRecord?->id,
                        ]);

                        // Egreso por el vuelto entregado
                        MovimientoCaja::create([
                            'caja_id' => $caja->id,
                            'tipo' => MovimientoCaja::TIPO_EGRESO,
                            'concepto' => "Vuelto Cobro #{$cobro->numero_recibo}",
                            'monto' => $vuelto,
                            'usuario_id' => $usuarioId,
                            'referencia_tipo' => MovimientoCaja::REF_VUELTO_COBRO,
                            'referencia_id' => $cobro->id,
                        ]);
                    } else {
                        // Caso normal: sin vuelto o misma moneda
                        $movimiento = MovimientoCaja::crearIngresoCobro($caja, $cobro, $montoFinal, $usuarioId);

                        if ($esMonedaExtranjera) {
                            $monedaPrincipalId = Moneda::obtenerPrincipal()?->id;
                            $tcRecord = $pago['moneda_id'] && $monedaPrincipalId
                                ? TipoCambio::ultimaTasa($pago['moneda_id'], $monedaPrincipalId)
                                : null;
                            $movimiento->update([
                                'moneda_id' => $pago['moneda_id'],
                                'monto_moneda_original' => $pago['monto_moneda_original'],
                                'tipo_cambio_id' => $tcRecord?->id,
                            ]);
                        }
                    }

                    $cobroPago->update(['movimiento_caja_id' => $movimiento->id]);
                }

                // Si la forma de pago tiene cuenta empresa vinculada, registrar movimiento
                $fpCobro = FormaPago::find($pago['forma_pago_id']);
                if ($fpCobro && $fpCobro->cuenta_empresa_id) {
                    try {
                        $movCuenta = CuentaEmpresaService::registrarMovimientoAutomatico(
                            CuentaEmpresa::find($fpCobro->cuenta_empresa_id),
                            'ingreso', $pago['monto_final'] ?? $pago['monto_base'], 'cobro',
                            'CobroPago', $cobroPago->id,
                            "Cobro #{$cobro->numero_recibo} - {$fpCobro->nombre}",
                            $usuarioId, $cobro->sucursal_id
                        );
                        $cobroPago->update(['movimiento_cuenta_empresa_id' => $movCuenta->id]);
                    } catch (\Exception $e) {
                        Log::warning('Error al registrar movimiento en cuenta empresa desde cobro', ['error' => $e->getMessage()]);
                    }
                }
            }

            // Registrar movimientos en cuenta corriente unificada
            $ccService = new CuentaCorrienteService;
            $ccService->registrarMovimientosCobro($cobro, $cobroVentasCreados, $usuarioId);

            return $cobro;
        });
    }

    /**
     * Registra un anticipo (cobro sin aplicación a ventas)
     *
     * @param  array  $data  Datos del cobro
     * @param  array  $pagos  Array de pagos
     */
    public function registrarAnticipo(array $data, array $pagos): Cobro
    {
        return $this->registrarCobro($data, [], $pagos);
    }

    /**
     * Calcula el interés por mora de una venta
     *
     * @param  Venta|object  $venta  Modelo Venta o objeto con propiedades necesarias
     * @param  float|null  $tasaMensual  Tasa mensual (si null, usa la del cliente)
     */
    public function calcularInteresMora(object $venta, ?float $tasaMensual = null): float
    {
        // Si no tiene fecha de vencimiento, no hay mora
        $fechaVencimiento = $venta->fecha_vencimiento ?? null;
        if (! $fechaVencimiento) {
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
     * Ahora trabaja con VentaPago para mayor precisión
     */
    public function obtenerVentasPendientesFIFO(int $clienteId, ?int $sucursalId = null): Collection
    {
        $query = VentaPago::whereHas('venta', function ($q) use ($clienteId, $sucursalId) {
            $q->where('cliente_id', $clienteId)
                ->whereIn('estado', ['completada', 'pendiente']);

            if ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }
        })
            ->where('es_cuenta_corriente', true)
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', 'activo')
            ->with(['venta.cliente', 'venta.comprobantesFiscales']);

        return $query->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($ventaPago) {
                $venta = $ventaPago->venta;

                // Construir descripcion_comprobantes con la misma lógica que VentaService
                $comprobantes = [];
                $tieneFacturaTotal = $venta->comprobantesFiscales->contains('es_total_venta', true);

                // Calcular monto total de comprobantes fiscales
                $totalFiscal = $venta->comprobantesFiscales->sum('total');
                $montoTicket = max(0, $venta->total_final - $totalFiscal);

                // Solo mostrar ticket si NO hay factura por el total
                if (! $tieneFacturaTotal) {
                    $pv = str_pad($venta->punto_venta ?? 1, 4, '0', STR_PAD_LEFT);
                    $num = str_pad($venta->numero ?? 0, 8, '0', STR_PAD_LEFT);
                    $montoFormateado = '$'.number_format($montoTicket, 2, ',', '.');
                    $comprobantes[] = "Ticket {$pv}-{$num} ({$montoFormateado})";
                }

                // Agregar comprobantes fiscales
                foreach ($venta->comprobantesFiscales as $cf) {
                    $tipoAbrev = match ($cf->tipo) {
                        'factura_a', 'factura_b', 'factura_c' => 'FA',
                        'nota_credito_a', 'nota_credito_b', 'nota_credito_c' => 'NC',
                        'nota_debito_a', 'nota_debito_b', 'nota_debito_c' => 'ND',
                        default => 'CF'
                    };
                    $letra = $cf->letra ?? '';
                    $pv = str_pad($cf->punto_venta_numero ?? 0, 4, '0', STR_PAD_LEFT);
                    $num = str_pad($cf->numero_comprobante ?? 0, 8, '0', STR_PAD_LEFT);
                    $montoFormateado = '$'.number_format($cf->total, 2, ',', '.');
                    $comprobantes[] = "{$tipoAbrev} {$letra} {$pv}-{$num} ({$montoFormateado})";
                }

                // Crear objeto compatible con el formato anterior
                return (object) [
                    'id' => $venta->id,
                    'venta_pago_id' => $ventaPago->id,
                    'numero' => $venta->numero,
                    'fecha' => $venta->fecha,
                    'fecha_vencimiento' => $venta->fecha_vencimiento,
                    'total_final' => $venta->total_final,
                    'saldo_pendiente_cache' => $ventaPago->saldo_pendiente,
                    'monto_original' => $ventaPago->monto_final,
                    'cliente' => $venta->cliente,
                    'descripcion_comprobantes' => implode(' | ', $comprobantes),
                ];
            });
    }

    /**
     * Distribuye un monto entre las ventas usando FIFO (más antigua primero)
     *
     * @param  float  $monto  Monto total a distribuir
     * @param  Collection  $ventas  Ventas ordenadas por antigüedad (con venta_pago_id)
     * @return array Array de ['venta_id' => X, 'venta_pago_id' => Y, 'monto_aplicado' => Z, 'interes_aplicado' => W]
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
                'venta_pago_id' => $venta->venta_pago_id ?? null,
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
     * Usa el nuevo sistema basado en VentaPago
     */
    public function generarReporteAntiguedad(?int $sucursalId = null): array
    {
        $ccService = new CuentaCorrienteService;

        return $ccService->generarReporteAntiguedad($sucursalId);
    }

    /**
     * Anula un cobro y revierte los saldos usando contraasientos
     *
     * Si el cobro generó saldo a favor que ya fue usado, ese saldo se convierte
     * en deuda del cliente (contraasiento de ajuste).
     *
     * @return array ['cobro' => Cobro, 'deuda_generada' => float]
     *
     * @throws Exception
     */
    public function anularCobro(int $cobroId, string $motivo): array
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($cobroId, $motivo) {
            $cobro = Cobro::with(['cobroVentas.venta', 'cobroVentas.ventaPago', 'pagos'])->findOrFail($cobroId);
            $usuarioId = auth()->id();

            if ($cobro->estaAnulado()) {
                throw new Exception('El cobro ya está anulado');
            }

            // Si el cobro está asociado a un cierre de turno, no se puede anular
            if ($cobro->cierre_turno_id) {
                throw new Exception('No se puede anular un cobro que ya fue cerrado en un turno');
            }

            // Anular movimientos en cuenta corriente unificada (con contraasientos)
            $ccService = new CuentaCorrienteService;
            $resultadoCC = $ccService->anularMovimientosCobro($cobro, $motivo, $usuarioId);

            // Revertir saldos en VentaPago
            foreach ($cobro->cobroVentas as $cobroVenta) {
                if ($cobroVenta->ventaPago) {
                    $cobroVenta->ventaPago->revertirCobro($cobroVenta->monto_aplicado);
                }

                // Actualizar saldo_pendiente_cache de la Venta (compatibilidad)
                $venta = $cobroVenta->venta;
                if ($venta) {
                    $saldoVenta = VentaPago::where('venta_id', $venta->id)
                        ->where('es_cuenta_corriente', true)
                        ->sum('saldo_pendiente');
                    $venta->update(['saldo_pendiente_cache' => $saldoVenta]);
                }
            }

            // Anular pagos asociados y revertir movimientos de caja
            foreach ($cobro->pagos as $pago) {
                if ($pago->afecta_caja && $pago->movimiento_caja_id) {
                    $movimiento = MovimientoCaja::find($pago->movimiento_caja_id);
                    $caja = $cobro->caja;
                    if ($movimiento && $caja) {
                        // Crear contra-movimiento (egreso) para anular el ingreso
                        MovimientoCaja::create([
                            'caja_id' => $caja->id,
                            'tipo' => MovimientoCaja::TIPO_EGRESO,
                            'concepto' => "Anulación Cobro #{$cobro->id}",
                            'monto' => $movimiento->monto,
                            'usuario_id' => $usuarioId,
                            'referencia_tipo' => MovimientoCaja::REF_ANULACION_COBRO,
                            'referencia_id' => $cobro->id,
                            'moneda_id' => $movimiento->moneda_id,
                            'tipo_cambio_id' => $movimiento->tipo_cambio_id,
                            'monto_moneda_original' => $movimiento->monto_moneda_original,
                        ]);
                        $caja->disminuirSaldo($movimiento->monto);

                        // Revertir egreso de vuelto por moneda extranjera si existe
                        $movVuelto = MovimientoCaja::where('referencia_tipo', MovimientoCaja::REF_VUELTO_COBRO)
                            ->where('referencia_id', $cobro->id)
                            ->where('caja_id', $caja->id)
                            ->first();
                        if ($movVuelto) {
                            // Crear contra-movimiento (ingreso) para anular el vuelto
                            MovimientoCaja::create([
                                'caja_id' => $caja->id,
                                'tipo' => MovimientoCaja::TIPO_INGRESO,
                                'concepto' => "Anulación vuelto Cobro #{$cobro->id}",
                                'monto' => $movVuelto->monto,
                                'usuario_id' => $usuarioId,
                                'referencia_tipo' => MovimientoCaja::REF_ANULACION_COBRO,
                                'referencia_id' => $cobro->id,
                            ]);
                            $caja->aumentarSaldo($movVuelto->monto);
                        }
                    }
                }
                $pago->update(['estado' => 'anulado']);

                // Revertir movimiento en cuenta empresa si existe
                if ($pago->movimiento_cuenta_empresa_id) {
                    try {
                        CuentaEmpresaService::revertirMovimiento(
                            $pago->movimiento_cuenta_empresa_id,
                            $motivo,
                            $usuarioId
                        );
                    } catch (\Exception $e) {
                        Log::warning('Error al revertir movimiento cuenta empresa en cobro', ['error' => $e->getMessage()]);
                    }
                }
            }

            // Marcar el cobro como anulado
            $cobro->update([
                'estado' => 'anulado',
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return [
                'cobro' => $cobro->fresh(),
                'deuda_generada' => $resultadoCC['deuda_generada'],
            ];
        });
    }

    /**
     * Genera un número de recibo único para la sucursal
     */
    public function generarNumeroRecibo(int $sucursalId): string
    {
        $prefijo = 'RC-'.str_pad($sucursalId, 2, '0', STR_PAD_LEFT).'-';

        $ultimoRecibo = Cobro::where('sucursal_id', $sucursalId)
            ->where('numero_recibo', 'like', $prefijo.'%')
            ->orderBy('id', 'desc')
            ->value('numero_recibo');

        if ($ultimoRecibo) {
            $ultimoNumero = (int) substr($ultimoRecibo, strlen($prefijo));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo.str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Actualiza el cache de saldo deudor del cliente
     * Usa el nuevo sistema de cuenta corriente unificada
     */
    public function actualizarCacheSaldoCliente(Cliente $cliente, ?int $sucursalId = null): void
    {
        $ccService = new CuentaCorrienteService;
        $ccService->actualizarCacheCliente($cliente->id, $sucursalId);
    }

    /**
     * Actualiza el cache de saldo del cliente para una sucursal específica
     * Usa el nuevo sistema de cuenta corriente unificada
     */
    public function actualizarCacheSaldoClienteSucursal(Cliente $cliente, int $sucursalId): void
    {
        $ccService = new CuentaCorrienteService;
        $ccService->actualizarCacheCliente($cliente->id, $sucursalId);
    }

    /**
     * Obtiene el historial de cuenta corriente de un cliente
     * Usa la nueva tabla unificada de movimientos_cuenta_corriente
     */
    public function obtenerMovimientosCuentaCorriente(int $clienteId, ?int $sucursalId = null, int $limit = 50): Collection
    {
        $ccService = new CuentaCorrienteService;

        if ($sucursalId) {
            return $ccService->obtenerExtractoResumido($clienteId, $sucursalId, $limit);
        }

        // Si no hay sucursal, obtener de todas las sucursales
        // Primero obtener todas las sucursales del cliente
        $sucursales = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
            ->where('estado', 'activo')
            ->distinct()
            ->pluck('sucursal_id');

        $todosMovimientos = collect();

        foreach ($sucursales as $sucId) {
            $movimientos = $ccService->obtenerExtractoResumido($clienteId, $sucId, $limit);
            $todosMovimientos = $todosMovimientos->concat($movimientos);
        }

        // Ordenar por fecha y tomar los últimos N
        return $todosMovimientos
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }
}
