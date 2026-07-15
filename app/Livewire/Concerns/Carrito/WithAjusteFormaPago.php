<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\FormaPago;
use App\Models\FormaPagoSucursal;

/**
 * Ajuste (descuento/recargo) por forma de pago + recargo por cuotas y su
 * prorrateo en el desglose de IVA.
 *
 * FUENTE ÚNICA de esta lógica (antes estaba triplicada en NuevaVenta,
 * NuevoPedidoDelivery y NuevoPedidoMostrador): la consumen los tres
 * componentes de carrito Y el cotizador headless de la tienda
 * (CotizadorCarritoTienda) — panel y tienda calculan EXACTAMENTE igual.
 *
 * Dependencias del host (vía $this->):
 * - $formaPagoId, $resultado                 (WithCalculoVenta / host)
 * - $formasPagoSucursal, cargarFormasPagoSucursal(),
 *   $ajusteFormaPagoInfo, $cuotaSeleccionadaId,
 *   $cuotasFormaPagoDisponibles, $infoCuotaSeleccionada,
 *   formatearDescripcionCuota()              (WithPagosDesglose o stubs del host)
 * - $sucursalId
 *
 * Hook overrideable `baseAjusteFormaPago()`: qué parte del total entra en la
 * base del ajuste. Default: todo. Delivery lo overridea para excluir el costo
 * de ENVÍO (D17: el envío es un valor fijo, fuera de ajustes/descuentos).
 */
trait WithAjusteFormaPago
{
    /**
     * Base sobre la que se calculan el ajuste por FP y el recargo de cuotas.
     * Default: el total completo. Overrideable por el host (delivery excluye
     * el envío — D17).
     */
    protected function baseAjusteFormaPago(float $totalBase): float
    {
        return $totalBase;
    }

    protected function calcularAjusteFormaPago(): void
    {
        // Resetear
        $this->ajusteFormaPagoInfo = [
            'nombre' => '',
            'porcentaje' => 0,
            'monto' => 0,
            'total_con_ajuste' => 0,
            'es_mixta' => false,
            'cuotas' => 1,
            'recargo_cuotas_porcentaje' => 0,
            'recargo_cuotas_monto' => 0,
            'valor_cuota' => 0,
        ];

        if (! $this->formaPagoId || ! $this->resultado) {
            return;
        }

        // Cargar formas de pago si no están cargadas
        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (! $fp) {
            // Fallback directo a BD (hosts headless o FP fuera del cache):
            // mismo criterio ajuste de sucursal > ajuste general.
            $formaPago = FormaPago::find($this->formaPagoId);
            if (! $formaPago) {
                return;
            }

            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $this->formaPagoId)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            $ajuste = $configSucursal && $configSucursal->ajuste_porcentaje !== null
                ? $configSucursal->ajuste_porcentaje
                : ($formaPago->ajuste_porcentaje ?? 0);

            $fp = [
                'id' => $formaPago->id,
                'nombre' => $formaPago->nombre,
                'ajuste_porcentaje' => $ajuste,
                'es_mixta' => $formaPago->es_mixta ?? false,
            ];
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajustePorcentaje = $fp['ajuste_porcentaje'] ?? 0;

        // La base puede excluir componentes fijos (delivery: el envío, D17).
        $baseAjuste = $this->baseAjusteFormaPago((float) $totalBase);

        $montoAjuste = round($baseAjuste * ($ajustePorcentaje / 100), 2) + 0;
        $totalConAjuste = round($totalBase + $montoAjuste, 2) + 0;

        // Variables para cuotas
        $cantidadCuotas = 1;
        $recargoCuotasPorcentaje = 0;
        $recargoCuotasMonto = 0;
        $valorCuota = $totalConAjuste;

        // Si hay cuota seleccionada, aplicar recargo de cuotas
        if ($this->cuotaSeleccionadaId && ! empty($this->cuotasFormaPagoDisponibles)) {
            $cuotaInfo = collect($this->cuotasFormaPagoDisponibles)->firstWhere('id', (int) $this->cuotaSeleccionadaId);

            if ($cuotaInfo) {
                $cantidadCuotas = $cuotaInfo['cantidad_cuotas'];
                $recargoCuotasPorcentaje = $cuotaInfo['recargo_porcentaje'];

                // El recargo se aplica sobre la MISMA base del ajuste (con el
                // ajuste FP incluido); los componentes fijos excluidos de la
                // base (envío) tampoco llevan recargo.
                $recargoCuotasMonto = round(($baseAjuste + $montoAjuste) * ($recargoCuotasPorcentaje / 100), 2);
                $totalConAjuste = round($totalConAjuste + $recargoCuotasMonto, 2);
                $valorCuota = $cantidadCuotas > 0 ? round($totalConAjuste / $cantidadCuotas, 2) : $totalConAjuste;

                // Actualizar info de cuota seleccionada con valores recalculados
                $this->infoCuotaSeleccionada = [
                    'cantidad_cuotas' => $cantidadCuotas,
                    'recargo_porcentaje' => $recargoCuotasPorcentaje,
                    'recargo_monto' => $recargoCuotasMonto,
                    'valor_cuota' => $valorCuota,
                    'total_con_recargo' => $totalConAjuste,
                    'descripcion' => $this->formatearDescripcionCuota([
                        'cantidad_cuotas' => $cantidadCuotas,
                        'recargo_porcentaje' => $recargoCuotasPorcentaje,
                        'valor_cuota' => $valorCuota,
                    ]),
                ];
            }
        }

        $this->ajusteFormaPagoInfo = [
            'nombre' => $fp['nombre'],
            'porcentaje' => $ajustePorcentaje,
            'monto' => $montoAjuste,
            'total_con_ajuste' => $totalConAjuste,
            'es_mixta' => $fp['es_mixta'] ?? false,
            'cuotas' => $cantidadCuotas,
            'recargo_cuotas_porcentaje' => $recargoCuotasPorcentaje,
            'recargo_cuotas_monto' => $recargoCuotasMonto,
            'valor_cuota' => $valorCuota,
        ];

        // Recalcular desglose de IVA con el ajuste de forma de pago
        $this->actualizarDesgloseIvaConAjusteFormaPago($montoAjuste, $recargoCuotasMonto);
    }

    /**
     * Actualiza el desglose de IVA considerando el ajuste de forma de pago y
     * el recargo por cuotas: el ajuste se prorratea proporcionalmente entre
     * las alícuotas (sobre el subtotal CON IVA), siguiendo las reglas de AFIP.
     *
     * @param  float  $montoAjusteFormaPago  Monto del ajuste (negativo = descuento)
     * @param  float  $montoRecargoCuotas  Recargo por cuotas (positivo o cero)
     */
    protected function actualizarDesgloseIvaConAjusteFormaPago(float $montoAjusteFormaPago, float $montoRecargoCuotas): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            return;
        }

        $desglose = $this->resultado['desglose_iva'];
        $totalNetoBase = $desglose['total_neto'];

        // Si no hay ajustes ni neto base, no hay nada que hacer
        if ($totalNetoBase == 0 || ($montoAjusteFormaPago == 0 && $montoRecargoCuotas == 0)) {
            $this->resultado['desglose_iva']['ajuste_forma_pago'] = 0;
            $this->resultado['desglose_iva']['recargo_cuotas'] = 0;
            $this->resultado['desglose_iva']['total_con_ajuste_fp'] = $desglose['total'];

            return;
        }

        // Combinar ajustes (el ajuste de forma de pago puede ser negativo)
        $ajusteTotal = $montoAjusteFormaPago + $montoRecargoCuotas;

        // Prorratear sobre el subtotal (con IVA), no sobre el neto
        $totalSubtotalBase = array_sum(array_column($desglose['por_alicuota'], 'subtotal'));

        $nuevoPorAlicuota = [];
        foreach ($desglose['por_alicuota'] as $alicuota) {
            $proporcion = $totalSubtotalBase > 0 ? $alicuota['subtotal'] / $totalSubtotalBase : 0;
            $ajusteAlicuotaConIva = $ajusteTotal * $proporcion;

            // Convertir el ajuste a neto (el ajuste "incluye" IVA proporcionalmente)
            $ajusteNetoAlicuota = $alicuota['porcentaje'] > 0
                ? $ajusteAlicuotaConIva / (1 + $alicuota['porcentaje'] / 100)
                : $ajusteAlicuotaConIva; // Exento o no gravado

            $nuevoNeto = $alicuota['neto'] + $ajusteNetoAlicuota;
            $nuevoIva = $nuevoNeto * ($alicuota['porcentaje'] / 100);

            $nuevoPorAlicuota[] = [
                'codigo' => $alicuota['codigo'],
                'nombre' => $alicuota['nombre'],
                'porcentaje' => $alicuota['porcentaje'],
                'neto_sin_descuento' => $alicuota['neto_sin_descuento'],
                'iva_sin_descuento' => $alicuota['iva_sin_descuento'],
                'subtotal_sin_descuento' => $alicuota['subtotal_sin_descuento'],
                'neto' => round($alicuota['neto'], 3), // Neto post-promociones (sin ajuste FP)
                'iva' => round($alicuota['iva'], 3),
                'subtotal' => round($alicuota['subtotal'], 3),
                'descuento_aplicado' => $alicuota['descuento_aplicado'],
                // Nuevos campos con ajuste de forma de pago
                'neto_con_ajuste_fp' => round($nuevoNeto, 3),
                'iva_con_ajuste_fp' => round($nuevoIva, 3),
                'subtotal_con_ajuste_fp' => round($nuevoNeto + $nuevoIva, 3),
                'ajuste_fp_aplicado' => round($ajusteAlicuotaConIva, 3),
            ];
        }

        $totalNetoConAjuste = array_sum(array_column($nuevoPorAlicuota, 'neto_con_ajuste_fp'));
        $totalIvaConAjuste = array_sum(array_column($nuevoPorAlicuota, 'iva_con_ajuste_fp'));
        $totalConAjuste = array_sum(array_column($nuevoPorAlicuota, 'subtotal_con_ajuste_fp'));

        $this->resultado['desglose_iva'] = [
            'por_alicuota' => $nuevoPorAlicuota,
            'total_neto' => $desglose['total_neto'], // sin ajuste FP
            'total_iva' => $desglose['total_iva'],
            'total' => $desglose['total'],
            'descuento_aplicado' => $desglose['descuento_aplicado'],
            'ajuste_forma_pago' => round($montoAjusteFormaPago, 3),
            'recargo_cuotas' => round($montoRecargoCuotas, 3),
            'total_neto_con_ajuste_fp' => round($totalNetoConAjuste, 3),
            'total_iva_con_ajuste_fp' => round($totalIvaConAjuste, 3),
            'total_con_ajuste_fp' => round($totalConAjuste, 3),
        ];
    }
}
