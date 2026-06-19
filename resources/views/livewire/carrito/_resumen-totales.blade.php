{{-- Resumen de Totales: subtotales, descuentos, IVA desglosado, total final (reutilizable) --}}
<div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1.5 border-b border-gray-200 dark:border-gray-700">
        <h4 class="text-xs font-medium text-gray-900 dark:text-white">{{ __('Resumen') }}</h4>
    </div>
    <div class="px-3 py-2 space-y-1">
        <div class="flex justify-between items-center">
            <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('Subtotal') }}:</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">$@precio($resultado['subtotal'] ?? 0)</span>
        </div>

        @if($resultado && $resultado['total_descuentos'] > 0)
            <div class="flex justify-between items-center text-green-600">
                <span class="text-xs">{{ __('Desc. promos') }}:</span>
                <span class="text-sm font-medium">-$@precio($resultado['total_descuentos'])</span>
            </div>
        @endif

        @if($descuentoGeneralActivo && $descuentoGeneralTipo === 'monto_fijo' && $descuentoGeneralMonto > 0)
            <div class="flex justify-between items-center text-purple-600">
                <span class="text-xs">{{ __('Descuento general') }}:</span>
                <span class="text-sm font-medium">-$@precio($descuentoGeneralMonto)</span>
            </div>
        @elseif($descuentoGeneralActivo && $descuentoGeneralTipo === 'porcentaje')
            <div class="flex justify-between items-center text-purple-600">
                <span class="text-xs">{{ __('Descuento general') }} ({{ $descuentoGeneralValor }}%):</span>
                <span class="text-sm font-medium">-$@precio($descuentoGeneralMonto)</span>
            </div>
        @endif

        @if($cuponAplicado && $cuponMontoDescuento > 0)
            <div class="flex justify-between items-center text-amber-600">
                <span class="text-xs">{{ __('Cupón') }} ({{ $cuponInfo['codigo'] ?? '' }}):</span>
                <span class="text-sm font-medium">-$@precio($cuponMontoDescuento)</span>
            </div>
        @endif

        @php
            $articulosCanjeadosMonto = $resultado['articulos_canjeados_monto'] ?? 0;
        @endphp
        @if($articulosCanjeadosMonto > 0)
            <div class="flex justify-between items-center text-yellow-600">
                <span class="text-xs">{{ __('Canjeado con puntos') }}:</span>
                <span class="text-sm font-medium">-$@precio($articulosCanjeadosMonto)</span>
            </div>
        @endif

        @if($canjePuntosActivo && $canjePuntosMonto > 0)
            <div class="flex justify-between items-center text-yellow-600">
                <span class="text-xs">{{ __('Pagar con puntos') }} ({{ $canjePuntosUnidades }} pts):</span>
                <span class="text-sm font-medium">-$@precio($canjePuntosMonto)</span>
            </div>
        @endif

        <div class="flex justify-between items-center font-semibold border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
            <span class="text-xs text-gray-700 dark:text-gray-300">{{ __('Total productos') }}:</span>
            <span class="text-sm text-gray-900 dark:text-white">$@precio($resultado['total_final'] ?? 0)</span>
        </div>

        {{-- Ajuste por forma de pago --}}
        @if($ajusteFormaPagoInfo['porcentaje'] != 0 && !$ajusteFormaPagoInfo['es_mixta'])
            <div class="flex justify-between items-center {{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                <span class="text-xs">{{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? __('Recargo') : __('Descuento') }} {{ $ajusteFormaPagoInfo['nombre'] }} ({{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? '+' : '' }}{{ $ajusteFormaPagoInfo['porcentaje'] }}%):</span>
                <span class="text-sm font-medium">{{ $ajusteFormaPagoInfo['monto'] > 0 ? '+' : '' }}$@precio($ajusteFormaPagoInfo['monto'])</span>
            </div>
        @endif

        {{-- Recargo por cuotas --}}
        @if(!$ajusteFormaPagoInfo['es_mixta'] && ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0)
            <div class="flex justify-between items-center text-red-600">
                <span class="text-xs">{{ __('Recargo') }} {{ $ajusteFormaPagoInfo['cuotas'] }} {{ __('cuotas') }} (+{{ $ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] }}%):</span>
                <span class="text-sm font-medium">+$@precio($ajusteFormaPagoInfo['recargo_cuotas_monto'])</span>
            </div>
        @endif

        {{-- Ajuste por pago mixto (cuando hay desglose) --}}
        @if($ajusteFormaPagoInfo['es_mixta'] && count($desglosePagos) > 0 && $montoPendienteDesglose <= 0.01)
            @php
                $ajusteMixto = $totalConAjustes - ($resultado['total_final'] ?? 0);
            @endphp
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-1">
                    <span class="text-xs {{ $ajusteMixto > 0 ? 'text-red-600' : ($ajusteMixto < 0 ? 'text-green-600' : 'text-gray-600') }}">{{ __('Ajustes F.P.') }}:</span>
                    <button wire:click="editarDesglose" type="button" class="inline-flex items-center px-1 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-100 rounded hover:bg-purple-200" title="{{ __('Editar desglose') }}">
                        <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ __('Editar') }}
                    </button>
                </div>
                <span class="text-sm font-medium {{ $ajusteMixto > 0 ? 'text-red-600' : ($ajusteMixto < 0 ? 'text-green-600' : 'text-gray-600') }}">{{ $ajusteMixto != 0 ? (($ajusteMixto > 0 ? '+' : '') . '$') : '$' }}@precio(abs($ajusteMixto))</span>
            </div>
            {{-- Resumen del desglose --}}
            <div class="text-xs text-gray-500 pl-2 border-l border-purple-200">
                @foreach($desglosePagos as $pago)
                    <div class="flex justify-between">
                        <span>{{ $pago['nombre'] }}{{ $pago['cuotas'] > 1 ? ' ('.$pago['cuotas'].'c)' : '' }}</span>
                        <span>$@precio($pago['monto_final'])</span>
                    </div>
                @endforeach
            </div>
        @elseif($ajusteFormaPagoInfo['es_mixta'])
            <div class="flex justify-between items-center text-purple-600">
                <span class="text-xs">{{ __('Pago mixto') }}:</span>
                <span class="text-sm font-medium">{{ __('Desglosar al cobrar') }}</span>
            </div>
        @endif

        {{-- Percepción fiscal (Fase 5b): se cobra de más cuando se factura a un RI
             y el CUIT es agente de percepción. El cliente paga el total con ella. --}}
        @if(($percepcionMonto ?? 0) > 0)
            <div class="flex justify-between items-center text-indigo-600 dark:text-indigo-400">
                <span class="text-xs flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                    {{ __('Percepción') }}@if(!empty($percepcionTributos) && isset($percepcionTributos[0]['alicuota'])) ({{ rtrim(rtrim(number_format($percepcionTributos[0]['alicuota'], 2, ',', '.'), '0'), ',') }}%)@endif:
                </span>
                <span class="text-sm font-medium">+$@precio($percepcionMonto)</span>
            </div>
        @endif

        {{-- Total a pagar --}}
        <div class="flex justify-between items-center text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2 mt-1">
            <span class="text-gray-900 dark:text-white">TOTAL:</span>
            @if($ajusteFormaPagoInfo['es_mixta'] && count($desglosePagos) > 0 && $montoPendienteDesglose <= 0.01 && $totalConAjustes > 0)
                <span class="text-purple-600">$@precio($totalConAjustes + ($percepcionMonto ?? 0))</span>
            @elseif(!$ajusteFormaPagoInfo['es_mixta'] && ($ajusteFormaPagoInfo['porcentaje'] != 0 || ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0))
                <span class="{{ ($ajusteFormaPagoInfo['porcentaje'] > 0 || ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0) ? 'text-red-600' : 'text-green-600' }}">$@precio(($ajusteFormaPagoInfo['total_con_ajuste'] ?? 0) + ($percepcionMonto ?? 0))</span>
            @else
                <span class="text-indigo-600">$@precio(($resultado['total_final'] ?? 0) + ($percepcionMonto ?? 0))</span>
            @endif
        </div>

        {{-- Desglose de IVA (colapsable) --}}
        @if($resultado && isset($resultado['desglose_iva']) && $resultado['desglose_iva']['total_iva'] > 0)
            <div x-data="{ abierto: false }" class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
                <button
                    @click="abierto = !abierto"
                    type="button"
                    class="w-full flex justify-between items-center text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z"/>
                        </svg>
                        {{ __('Desglose IVA') }}
                    </span>
                    <svg :class="{ 'rotate-180': abierto }" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="abierto" x-collapse class="mt-2 space-y-1 text-xs">
                    @php
                        $desglose = $resultado['desglose_iva'];
                        // Prioridad: pago mixto > forma de pago simple > sin ajuste
                        $tienePagoMixto = isset($desglose['total_mixto']);
                        $tieneAjusteFP = !$tienePagoMixto && isset($desglose['total_con_ajuste_fp']) && $desglose['total_con_ajuste_fp'] != $desglose['total'];
                    @endphp

                    {{-- Por alícuota --}}
                    @foreach($desglose['por_alicuota'] as $alicuota)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded p-1.5">
                            <div class="font-medium text-gray-700 dark:text-gray-300 mb-0.5">{{ $alicuota['nombre'] }}</div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>{{ __('Neto') }}:</span>
                                @if($tienePagoMixto && isset($alicuota['neto_mixto']))
                                    <span>${{ number_format($alicuota['neto_mixto'], 3, ',', '.') }}</span>
                                @elseif($tieneAjusteFP && isset($alicuota['neto_con_ajuste_fp']))
                                    <span>${{ number_format($alicuota['neto_con_ajuste_fp'], 3, ',', '.') }}</span>
                                @else
                                    <span>${{ number_format($alicuota['neto'], 3, ',', '.') }}</span>
                                @endif
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>{{ __('IVA') }} ({{ $alicuota['porcentaje'] }}%):</span>
                                @if($tienePagoMixto && isset($alicuota['iva_mixto']))
                                    <span>${{ number_format($alicuota['iva_mixto'], 3, ',', '.') }}</span>
                                @elseif($tieneAjusteFP && isset($alicuota['iva_con_ajuste_fp']))
                                    <span>${{ number_format($alicuota['iva_con_ajuste_fp'], 3, ',', '.') }}</span>
                                @else
                                    <span>${{ number_format($alicuota['iva'], 3, ',', '.') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Totales --}}
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-1 mt-1 space-y-0.5">
                        <div class="flex justify-between font-medium text-gray-700 dark:text-gray-300">
                            <span>{{ __('Total Neto') }}:</span>
                            @if($tienePagoMixto)
                                <span>${{ number_format($desglose['total_neto_mixto'], 3, ',', '.') }}</span>
                            @elseif($tieneAjusteFP)
                                <span>${{ number_format($desglose['total_neto_con_ajuste_fp'], 3, ',', '.') }}</span>
                            @else
                                <span>${{ number_format($desglose['total_neto'], 3, ',', '.') }}</span>
                            @endif
                        </div>
                        <div class="flex justify-between font-medium text-gray-700 dark:text-gray-300">
                            <span>{{ __('Total IVA') }}:</span>
                            @if($tienePagoMixto)
                                <span>${{ number_format($desglose['total_iva_mixto'], 3, ',', '.') }}</span>
                            @elseif($tieneAjusteFP)
                                <span>${{ number_format($desglose['total_iva_con_ajuste_fp'], 3, ',', '.') }}</span>
                            @else
                                <span>${{ number_format($desglose['total_iva'], 3, ',', '.') }}</span>
                            @endif
                        </div>
                        <div class="flex justify-between font-semibold text-gray-900 dark:text-white">
                            <span>{{ __('Total') }}:</span>
                            @if($tienePagoMixto)
                                <span>${{ number_format($desglose['total_mixto'], 3, ',', '.') }}</span>
                            @elseif($tieneAjusteFP)
                                <span>${{ number_format($desglose['total_con_ajuste_fp'], 3, ',', '.') }}</span>
                            @else
                                <span>${{ number_format($desglose['total'], 3, ',', '.') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Info de ajustes si los hay --}}
                    @php
                        $descuentoAplicado = $desglose['descuento_aplicado'] ?? 0;
                        $ajusteFP = $tienePagoMixto ? ($desglose['ajuste_forma_pago_mixto'] ?? 0) : ($desglose['ajuste_forma_pago'] ?? 0);
                        $recargoCuotas = $tienePagoMixto ? ($desglose['recargo_cuotas_mixto'] ?? 0) : ($desglose['recargo_cuotas'] ?? 0);
                    @endphp
                    @if($descuentoAplicado > 0 || $ajusteFP != 0 || $recargoCuotas > 0)
                        <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 italic">
                            @if($descuentoAplicado > 0)
                                {{ __('Desc. promos') }}: -${{ number_format($descuentoAplicado, 3, ',', '.') }}
                            @endif
                            @if($ajusteFP != 0)
                                | Ajuste F.P.: {{ $ajusteFP > 0 ? '+' : '' }}${{ number_format($ajusteFP, 3, ',', '.') }}
                            @endif
                            @if($recargoCuotas > 0)
                                | Cuotas: +${{ number_format($recargoCuotas, 3, ',', '.') }}
                            @endif
                            @if($tienePagoMixto)
                                <span class="text-bcn-primary dark:text-bcn-accent">(Mixto)</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>
</div>
