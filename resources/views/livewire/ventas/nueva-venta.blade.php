{{--
    Vista Livewire: Nueva Venta (POS)

    Sistema completo de punto de venta con:
    - Búsqueda unificada de artículos por nombre, código y código de barras (con detección automática de scanner)
    - Cálculo de precios según lista de precios
    - Aplicación de promociones especiales y comunes
    - Selectores de forma de venta, canal de venta, forma de pago y lista de precios

    @see App\Livewire\Ventas\NuevaVenta
--}}

<div class="h-[calc(100vh-5.5rem)] flex flex-col py-2 overflow-hidden"
     x-data
     @keydown.window="
        if ($event.ctrlKey && $event.key >= '1' && $event.key <= '9') {
            $event.preventDefault();
            const actions = {
                '1': () => $dispatch('focus-busqueda'),
                '2': () => $dispatch('focus-cantidad'),
                '3': () => $wire.activarModoConsulta(),
                '4': () => $wire.activarModoBusqueda(),
                '5': () => $wire.abrirModalConcepto(),
                '6': () => $dispatch('focus-cliente'),
                '7': () => document.getElementById('listaPrecioId')?.focus(),
                '8': () => $wire.abrirModalBusquedaArticulos(),
                '9': () => $wire.abrirModalArticuloRapido(),
            };
            actions[$event.key]?.();
        }
        if ($event.key === 'F2' && !$wire.mostrarModalMonedaExtranjera && !$wire.mostrarModalVuelto) { $event.preventDefault(); $wire.iniciarCobro(); }
        if ($event.key === 'F3') { $event.preventDefault(); $wire.confirmarLimpiarCarrito(); }
        if ($event.key === 'F4' && !$wire.showModalDescuentos) { $event.preventDefault(); $wire.abrirModalDescuentos(); }
     ">

    {{-- Overlay de Caja Operativa Requerida --}}
    <x-caja-operativa-requerida :estado-caja="$estadoCaja" ruta-turno="cajas.turno-actual" permiso-turno="cajas.ver">

    {{-- Contenido Principal del POS --}}
    <div class="flex-1 px-3 sm:px-4 lg:px-6 min-h-0">
        <div class="h-full bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col">
            {{-- Body --}}
            <div class="flex-1 px-4 py-3 min-h-0 overflow-hidden">
                <div class="h-full grid grid-cols-1 lg:grid-cols-4 gap-4">
                    {{-- Columna izquierda: Búsqueda y lista de artículos (75%) --}}
                    <div class="lg:col-span-3 flex flex-col space-y-3 min-h-0">
                        {{-- Bloque de Búsqueda de Artículos (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
                        @include('livewire.carrito._busqueda-articulos')

                        {{-- Lista/tabla de items del carrito con cantidad, ajustes, opcionales (reutilizable) --}}
                        @include('livewire.carrito._detalle-items')

                        {{-- Promociones Aplicadas --}}
                        @if($resultado && (count($resultado['promociones_especiales_aplicadas']) > 0 || count($resultado['promociones_comunes_aplicadas']) > 0))
                            @php
                                $totalPromos = count($resultado['promociones_especiales_aplicadas']) + count($resultado['promociones_comunes_aplicadas']);
                            @endphp
                            <div class="border border-green-200 rounded-lg overflow-hidden bg-green-50">
                                <div class="bg-green-100 px-2 py-1 border-b border-green-200 flex justify-between items-center">
                                    <h4 class="text-xs font-medium text-green-800">{{ __('Promociones') }} ({{ $totalPromos }})</h4>
                                    @if($totalPromos > 4)
                                        <span class="text-[10px] text-green-600">scroll ↓</span>
                                    @endif
                                </div>
                                <div class="px-2 py-1.5 space-y-0.5 max-h-20 overflow-y-auto">
                                    @foreach($resultado['promociones_especiales_aplicadas'] as $promo)
                                        <div class="flex justify-between items-center text-xs">
                                            <div><span class="font-medium text-green-700">{{ $promo['nombre'] }}</span> <span class="text-green-600 text-[10px]">({{ Str::limit($promo['descripcion'], 20) }})</span></div>
                                            <span class="font-semibold text-green-700">-$@precio($promo['descuento'])</span>
                                        </div>
                                    @endforeach
                                    @foreach($resultado['promociones_comunes_aplicadas'] as $promo)
                                        <div class="flex justify-between items-center text-xs">
                                            <div><span class="font-medium text-green-700">{{ $promo['nombre'] }}</span> <span class="text-green-600 text-[10px]">({{ Str::limit($promo['descripcion'], 20) }})</span></div>
                                            <span class="font-semibold text-green-700">-$@precio($promo['descuento'])</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Columna derecha: Resumen y acciones --}}
                    <div class="flex flex-col min-h-0">
                        {{-- Contenido scrolleable --}}
                        <div class="flex-1 overflow-y-auto space-y-2 min-h-0 pr-1">
                        {{-- Sección Cliente: input búsqueda + dropdown + card cliente seleccionado (reutilizable) --}}
                        @include('livewire.carrito._busqueda-cliente')

                        {{-- Lista de Precios --}}
                        <div wire:key="lista-precio-select-{{ $sucursalId }}">
                            <label for="listaPrecioId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">
                                {{ __('Lista de Precios') }}
                            </label>
                            <select
                                wire:model.live="listaPrecioId"
                                id="listaPrecioId"
                                class="block w-full pl-2 pr-8 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                @foreach($listasPreciosDisponibles as $lista)
                                    <option value="{{ $lista['id'] }}" @selected($lista['id'] == $listaPrecioId)>
                                        {{ $lista['nombre'] }}
                                        @if($lista['es_lista_base'])
                                            ({{ __('Base') }})
                                        @elseif($lista['ajuste_porcentaje'] != 0)
                                            ({{ $lista['descripcion_ajuste'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Forma de Venta y Forma de Pago en fila --}}
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="formaVentaId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">{{ __('Forma de Venta') }}</label>
                                <select wire:model.live="formaVentaId" id="formaVentaId" class="block w-full pl-2 pr-6 py-1.5 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                    <option value="">{{ __('Seleccionar...') }}</option>
                                    @foreach($this->formasVenta as $fv)
                                        <option value="{{ $fv['id'] }}">{{ $fv['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="formaPagoId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">{{ __('Forma de Pago') }}</label>
                                <select wire:model.live="formaPagoId" id="formaPagoId" class="block w-full pl-2 pr-6 py-1.5 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                    <option value="">{{ __('Seleccionar...') }}</option>
                                    @foreach($this->formasPago as $fp)
                                        <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Selector de Cuotas (solo si la forma de pago permite cuotas) --}}
                        @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                            <div class="relative">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">{{ __('Cuotas') }}</label>

                                {{-- Opción seleccionada (siempre visible) --}}
                                <div
                                    wire:click="toggleCuotasSelector"
                                    class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                >
                                    @if(!$cuotaSeleccionadaId)
                                        {{-- 1 pago seleccionado --}}
                                        <div class="flex items-center px-2 py-1.5">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                <span class="text-[10px] text-gray-400">—</span>
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold text-gray-900 dark:text-white">$@precio(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0))</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    @else
                                        {{-- Cuota seleccionada --}}
                                        @php
                                            $cuotaSel = collect($cuotasFormaPagoDisponibles)->firstWhere('id', (int) $cuotaSeleccionadaId);
                                        @endphp
                                        @if($cuotaSel)
                                            <div class="flex items-center px-2 py-1.5">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuotaSel['cantidad_cuotas'] }} cuotas</div>
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">de $@precio($cuotaSel['valor_cuota'])</div>
                                                </div>
                                                <div class="text-center px-2">
                                                    @if($cuotaSel['recargo_porcentaje'] > 0)
                                                        <span class="text-[10px] font-medium text-red-600">+{{ $cuotaSel['recargo_porcentaje'] }}%</span>
                                                    @else
                                                        <span class="text-[10px] font-medium text-green-600">0%</span>
                                                    @endif
                                                </div>
                                                <div class="text-right min-w-[70px]">
                                                    <span class="text-xs font-semibold {{ $cuotaSel['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuotaSel['total_con_recargo'])</span>
                                                </div>
                                                <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Dropdown de opciones --}}
                                @if($cuotasSelectorAbierto)
                                    <div class="absolute z-20 w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-40 overflow-y-auto">
                                        {{-- Opción: 1 pago --}}
                                        <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ !$cuotaSeleccionadaId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                            <input type="radio" wire:model.live="cuotaSeleccionadaId" value="" class="sr-only">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                <span class="text-[10px] text-gray-400">—</span>
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold text-gray-900 dark:text-white">$@precio(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0))</span>
                                            </div>
                                            @if(!$cuotaSeleccionadaId)
                                                <svg class="w-3 h-3 text-blue-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @else
                                                <div class="w-4 ml-2"></div>
                                            @endif
                                        </label>

                                        {{-- Opciones de cuotas --}}
                                        @foreach($cuotasFormaPagoDisponibles as $cuota)
                                            <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $cuotaSeleccionadaId == $cuota['id'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                                <input type="radio" wire:model.live="cuotaSeleccionadaId" value="{{ $cuota['id'] }}" class="sr-only">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad_cuotas'] }} cuotas</div>
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">de $@precio($cuota['valor_cuota'])</div>
                                                </div>
                                                <div class="text-center px-2">
                                                    @if($cuota['recargo_porcentaje'] > 0)
                                                        <span class="text-[10px] font-medium text-red-600">+{{ $cuota['recargo_porcentaje'] }}%</span>
                                                    @else
                                                        <span class="text-[10px] font-medium text-green-600">0%</span>
                                                    @endif
                                                </div>
                                                <div class="text-right min-w-[70px]">
                                                    <span class="text-xs font-semibold {{ $cuota['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuota['total_con_recargo'])</span>
                                                </div>
                                                @if($cuotaSeleccionadaId == $cuota['id'])
                                                    <svg class="w-3 h-3 text-blue-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @else
                                                    <div class="w-3 ml-1"></div>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Resumen de Totales --}}
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

                                {{-- Total a pagar --}}
                                <div class="flex justify-between items-center text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2 mt-1">
                                    <span class="text-gray-900 dark:text-white">TOTAL:</span>
                                    @if($ajusteFormaPagoInfo['es_mixta'] && count($desglosePagos) > 0 && $montoPendienteDesglose <= 0.01 && $totalConAjustes > 0)
                                        <span class="text-purple-600">$@precio($totalConAjustes)</span>
                                    @elseif(!$ajusteFormaPagoInfo['es_mixta'] && ($ajusteFormaPagoInfo['porcentaje'] != 0 || ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0))
                                        <span class="{{ ($ajusteFormaPagoInfo['porcentaje'] > 0 || ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0) ? 'text-red-600' : 'text-green-600' }}">$@precio($ajusteFormaPagoInfo['total_con_ajuste'] ?? 0)</span>
                                    @else
                                        <span class="text-indigo-600">$@precio($resultado['total_final'] ?? 0)</span>
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

                        </div>
                        {{-- Fin contenido scrolleable --}}

                        {{-- Botones de acción (fijos abajo) --}}
                        <div class="flex-shrink-0 pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 space-y-1">
                            @php
                                // Calcular el total a mostrar en el botón
                                $totalACobrar = 0;
                                if (!empty($resultado)) {
                                    if (isset($resultado['desglose_iva'])) {
                                        $desglose = $resultado['desglose_iva'];
                                        if (isset($desglose['total_mixto'])) {
                                            $totalACobrar = $desglose['total_mixto'];
                                        } elseif (isset($desglose['total_con_ajuste_fp']) && $desglose['total_con_ajuste_fp'] != ($desglose['total'] ?? 0)) {
                                            $totalACobrar = $desglose['total_con_ajuste_fp'];
                                        } else {
                                            $totalACobrar = $desglose['total'] ?? ($resultado['total_final'] ?? 0);
                                        }
                                    } else {
                                        $totalACobrar = $resultado['total_final'] ?? 0;
                                    }
                                }
                            @endphp

                            {{-- Checkbox de Factura Fiscal (solo si la sucursal NO es automática y NO es mixta) --}}
                            @if(!$sucursalFacturaAutomatica && !($ajusteFormaPagoInfo['es_mixta'] ?? false) && !empty($items))
                                <div class="flex items-center justify-between py-1.5 px-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-md mb-1">
                                    <label for="emitirFacturaFiscal" class="flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="emitirFacturaFiscal"
                                            wire:model.live="emitirFacturaFiscal"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                        >
                                        <span class="ml-2 text-sm font-medium text-indigo-700 dark:text-indigo-300">
                                            {{ __('Emitir factura fiscal') }}
                                        </span>
                                    </label>
                                    @if($emitirFacturaFiscal)
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            @endif

                            {{-- Botón de Descuentos y Beneficios (si tiene items) --}}
                            @if(!empty($items))
                                <button
                                    wire:click="abrirModalDescuentos"
                                    type="button"
                                    class="w-full inline-flex justify-center items-center px-2 py-1.5 border rounded-md shadow-sm text-xs font-medium
                                        {{ ($descuentoGeneralActivo || $cuponAplicado || $canjePuntosActivo)
                                            ? 'border-purple-400 dark:border-purple-500 text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 hover:bg-purple-100 dark:hover:bg-purple-900/50'
                                            : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    {{ __('Descuentos') }}
                                    @if($descuentoGeneralActivo)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-purple-200 dark:bg-purple-700 text-purple-800 dark:text-purple-200 rounded">
                                            {{ $descuentoGeneralTipo === 'porcentaje' ? $descuentoGeneralValor . '%' : '$' . number_format($descuentoGeneralValor, 2, ',', '.') }}
                                        </span>
                                    @endif
                                    @if($cuponAplicado && $cuponInfo)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-amber-200 dark:bg-amber-700 text-amber-800 dark:text-amber-200 rounded">
                                            {{ $cuponInfo['codigo'] }}
                                        </span>
                                    @endif
                                    @if($canjePuntosActivo)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-yellow-200 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-200 rounded">
                                            {{ $canjePuntosUnidades }}pts
                                        </span>
                                    @endif
                                    <kbd class="ml-auto px-1 py-0.5 text-[9px] bg-gray-200 dark:bg-gray-600 rounded">F4</kbd>
                                </button>
                            @endif

                            <div class="flex gap-2 w-full">
                                <button
                                    wire:click="confirmarLimpiarCarrito"
                                    @if(empty($items)) disabled @endif
                                    class="w-[30%] inline-flex justify-center items-center px-2 py-2.5 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ __('Limpiar') }}
                                </button>
                                <button
                                    wire:click="iniciarCobro"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    wire:target="iniciarCobro"
                                    @if(empty($items)) disabled @endif
                                    class="w-[70%] inline-flex justify-center items-center px-3 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-bold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg wire:loading.remove wire:target="iniciarCobro" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <svg wire:loading wire:target="iniciarCobro" class="animate-spin h-4 w-4 text-white mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="iniciarCobro">
                                        {{ __('Cobrar') }}
                                        @if($totalACobrar > 0)
                                            <span class="ml-1">${{ number_format($totalACobrar, 2, ',', '.') }}</span>
                                        @endif
                                    </span>
                                    <span wire:loading wire:target="iniciarCobro">...</span>
                                </button>
                            </div>
                            {{-- Barra de atajos de teclado --}}
                            <div class="w-full mt-1.5 py-1 bg-gray-100 dark:bg-gray-900/40 rounded text-[10px] text-gray-400 dark:text-gray-500 leading-snug text-center select-none">
                                Ctrl: <span class="text-gray-500 dark:text-gray-400">1</span>{{ __('Buscar') }} · <span class="text-gray-500 dark:text-gray-400">2</span>{{ __('Cantidad') }} · <span class="text-gray-500 dark:text-gray-400">3</span>{{ __('Consultar') }} · <span class="text-gray-500 dark:text-gray-400">4</span>{{ __('Buscar det.') }} · <span class="text-gray-500 dark:text-gray-400">5</span>{{ __('Concepto') }} · <span class="text-gray-500 dark:text-gray-400">6</span>{{ __('Cliente') }} · <span class="text-gray-500 dark:text-gray-400">7</span>{{ __('Lista') }} · <span class="text-gray-500 dark:text-gray-400">8</span>{{ __('Buscar art.') }} · <span class="text-gray-500 dark:text-gray-400">9</span>{{ __('Nuevo art.') }}
                                | <span class="text-gray-500 dark:text-gray-400">F2</span>{{ __('Cobrar') }} · <span class="text-gray-500 dark:text-gray-400">F3</span>{{ __('Limpiar') }} · <span class="text-gray-500 dark:text-gray-400">F4</span>{{ __('Desc.') }} · <span class="text-gray-500 dark:text-gray-400">*</span>{{ __('Cantidad') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Consulta de Precios --}}
    @if($mostrarModalConsulta && $articuloConsulta)
        <x-bcn-modal
            :show="$mostrarModalConsulta"
            title="{{ __('Consulta de Precios') }}"
            color="bg-amber-500"
            maxWidth="lg"
            onClose="cerrarModalConsulta"
        >
            <x-slot:body>
                {{-- Info del artículo --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articuloConsulta['nombre'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Código') }}: {{ $articuloConsulta['codigo'] }}
                        @if($articuloConsulta['categoria'])
                            | {{ $articuloConsulta['categoria'] }}
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ __('Precio base') }}: <span class="font-medium">$@precio($articuloConsulta['precio_base'])</span>
                    </div>
                </div>

                {{-- Tabla de precios por lista --}}
                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Lista') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($articuloConsulta['precios'] as $precio)
                                <tr class="{{ $precio['es_lista_base'] ? 'bg-green-50 dark:bg-green-900/30' : '' }} {{ $precio['lista_id'] == $listaPrecioId ? 'ring-2 ring-inset ring-indigo-500' : '' }}">
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $precio['lista_nombre'] }}
                                            @if($precio['es_lista_base'])
                                                <span class="ml-1 text-xs text-green-600">({{ __('Base') }})</span>
                                            @endif
                                            @if($precio['lista_id'] == $listaPrecioId)
                                                <span class="ml-1 text-xs text-indigo-600 dark:text-indigo-400">({{ __('Actual') }})</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($precio['tiene_precio_especifico'])
                                                {{ __('Precio específico') }}
                                            @elseif($precio['ajuste_porcentaje'] != 0)
                                                {{ $precio['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $precio['ajuste_porcentaje'] }}% {{ __('sobre base') }}
                                            @else
                                                {{ __('Sin ajuste') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right">
                                        <span class="font-medium {{ $precio['lista_id'] == $listaPrecioId ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-white' }}">
                                            $@precio($precio['precio'])
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    wire:click="agregarArticuloYCerrarConsulta({{ $articuloConsulta['id'] }})"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Agregar al carrito') }}
                </button>
                <button
                    @click="close()"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Script para scroll y efectos --}}
    {{-- Modal de Agregar Concepto --}}
    @if($mostrarModalConcepto)
        <x-bcn-modal
            :show="$mostrarModalConcepto"
            title="{{ __('Agregar Concepto') }}"
            color="bg-emerald-500"
            maxWidth="md"
            onClose="cerrarModalConcepto"
        >
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Agregue un concepto por importe sin especificar artículo (ej: venta de fiambrería por $5.000)') }}
                </p>

                {{-- Importe (primero) --}}
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Importe') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">$</span>
                        <input
                            wire:model="conceptoImporte"
                            type="number"
                            step="0.01"
                            min="0"
                            class="block w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                            placeholder="0.00">
                    </div>
                </div>

                {{-- Categoría (opcional) --}}
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría (opcional)') }}</label>
                    <select
                        wire:model="conceptoCategoriaId"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        <option value="">{{ __('-- Sin categoría (Varios) --') }}</option>
                        @foreach($categoriasDisponibles as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Descripción (opcional) --}}
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción (opcional)') }}</label>
                    <input
                        wire:model="conceptoDescripcion"
                        type="text"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                        placeholder="{{ __('Ej: Fiambrería variada') }}">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Si está vacío, se usará el nombre de la categoría o "Varios"') }}</p>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    @click="close()"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button
                    wire:click="agregarConcepto"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Agregar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de Pago / Desglose --}}
    @if($mostrarModalPago)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-pago"
            role="dialog"
            aria-modal="true"
            x-data="{
                pagosCount: {{ count($desglosePagos) }},
                init() {
                    this.$nextTick(() => {
                        // Enfocar el input de búsqueda de FP si hay pendiente
                        const busquedaFP = this.$el.querySelector('[x-ref=inputBusquedaFP]');
                        if (busquedaFP) { busquedaFP.focus(); return; }
                        // Si ya no hay pendiente, enfocar primer elemento
                        const firstInput = this.$el.querySelector('input, button[type=button]:not([disabled])');
                        if (firstInput) firstInput.focus();
                    });
                }
            }"
            @keydown.escape.window="$wire.cerrarModalPago()"
            x-on:focus-busqueda-fp.window="setTimeout(() => { const el = $el.querySelector('[x-ref=inputBusquedaFP]'); if (el) el.focus(); }, 150)"
            @keydown.enter.window.prevent="
                const activeEl = document.activeElement;
                // No interceptar Enter si estamos en inputs del selector de FP o monto (lo manejan ellos)
                if (activeEl && (activeEl.hasAttribute('x-ref'))) {
                    const ref = activeEl.getAttribute('x-ref');
                    if (ref === 'inputBusquedaFP' || ref === 'inputMontoDesglose' || ref === 'btnAgregar') return;
                }
                // Si el desglose está completo, confirmar pago
                if ({{ $this->desgloseCompleto() ? 'true' : 'false' }}) $wire.confirmarPago();
            "
            @keydown.tab.prevent="
                const focusables = [...$el.querySelectorAll('input, button:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
                const current = document.activeElement;
                let idx = focusables.indexOf(current);
                idx = $event.shiftKey ? idx - 1 : idx + 1;
                if (idx < 0) idx = focusables.length - 1;
                if (idx >= focusables.length) idx = 0;
                focusables[idx]?.focus();
            "
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalPago"></div>

                {{-- Centrado --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    {{-- Header --}}
                    <div class="bg-green-600 px-3 py-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex-shrink-0 flex items-center justify-center h-8 w-8 rounded-full bg-green-100">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-medium text-white">
                                {{ count($desglosePagos) > 1 || $montoPendienteDesglose > 0 ? __('Desglose de Pagos') : __('Confirmar Pago') }}
                            </h3>
                        </div>
                        <button wire:click="cerrarModalPago" class="text-white hover:text-green-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Contenido --}}
                    <div class="px-3 py-3 space-y-3 max-h-[70vh] overflow-y-auto">
                        {{-- Resumen del total --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 flex justify-between items-center">
                            <div>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total a cobrar') }}:</span>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                    $@precio($resultado['total_final'] ?? 0)
                                </div>
                            </div>
                            @if($montoPendienteDesglose > 0.01)
                                <div class="text-right">
                                    <span class="text-sm text-orange-600">{{ __('Pendiente') }}:</span>
                                    <div class="text-xl font-bold text-orange-600">
                                        $@precio($montoPendienteDesglose)
                                    </div>
                                </div>
                            @else
                                <div class="text-right">
                                    <span class="text-sm text-green-600">{{ __('Total con ajustes') }}:</span>
                                    <div class="text-xl font-bold text-green-600">
                                        $@precio($totalConAjustes)
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Pagos agregados --}}
                        @if(count($desglosePagos) > 0)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1.5 border-b border-gray-200 dark:border-gray-600">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Formas de Pago') }}</h4>
                                </div>
                                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($desglosePagos as $index => $pago)
                                        <div class="px-3 py-2 {{ count($desglosePagos) === 1 && $montoPendienteDesglose <= 0.01 ? '' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }} {{ ($pago['factura_fiscal'] ?? false) ? 'border-l-4 border-indigo-500' : '' }}">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-900 dark:text-white">{{ $pago['nombre'] }}</span>
                                                        @if($pago['ajuste_porcentaje'] != 0)
                                                            <span class="text-xs px-2 py-0.5 rounded {{ $pago['ajuste_porcentaje'] > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                                                {{ $pago['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $pago['ajuste_porcentaje'] }}%
                                                            </span>
                                                        @endif
                                                        {{-- Indicador de Factura Fiscal --}}
                                                        <button
                                                            wire:click="toggleFacturaFiscalDesglose({{ $index }})"
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors {{ ($pago['factura_fiscal'] ?? false) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                                                            :title="($pago['factura_fiscal'] ?? false) ? __('Factura fiscal activada') : __('Clic para activar factura fiscal')"
                                                        >
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                            </svg>
                                                            {{ __('Fiscal') }}
                                                            @if($pago['factura_fiscal'] ?? false)
                                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                        </button>
                                                    </div>

                                                    {{-- Detalle moneda extranjera --}}
                                                    @if(!empty($pago['es_moneda_extranjera']) && !empty($pago['monto_moneda_original']))
                                                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                            {{ $pago['moneda_info']['simbolo'] ?? '' }} {{ number_format($pago['monto_moneda_original'], 2, ',', '.') }}
                                                            {{ $pago['moneda_info']['codigo'] ?? '' }}
                                                            × {{ number_format($pago['tipo_cambio_tasa'], 2, ',', '.') }}
                                                        </div>
                                                    @endif

                                                    {{-- Detalles del monto --}}
                                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        <span>{{ __('Base') }}: $@precio($pago['monto_base'])</span>
                                                        @if($pago['monto_ajuste'] != 0)
                                                            <span class="mx-1">→</span>
                                                            <span class="{{ $pago['monto_ajuste'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                                {{ $pago['monto_ajuste'] > 0 ? '+' : '' }}$@precio($pago['monto_ajuste'])
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Selector de cuotas --}}
                                                    @if($pago['permite_cuotas'] && count($pago['cuotas_disponibles']) > 0)
                                                        <div class="mt-2 flex items-center gap-2">
                                                            <label class="text-xs text-gray-600 dark:text-gray-400">{{ __('Cuotas') }}:</label>
                                                            <select
                                                                wire:change="actualizarCuotasDesglose({{ $index }}, $event.target.value)"
                                                                class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                                                @foreach($pago['cuotas_disponibles'] as $cuota)
                                                                    <option value="{{ $cuota['cantidad'] }}" @selected($pago['cuotas'] == $cuota['cantidad'])>
                                                                        {{ $cuota['cantidad'] }} cuota{{ $cuota['cantidad'] > 1 ? 's' : '' }}
                                                                        @if($cuota['recargo'] > 0)
                                                                            (+{{ $cuota['recargo'] }}%)
                                                                        @else
                                                                            ({{ __('sin interés') }})
                                                                        @endif
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @if($pago['cuotas'] > 1)
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                    $@precio($pago['monto_final'] / $pago['cuotas']) c/u
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    {{-- Input monto recibido para efectivo --}}
                                                    @if($pago['permite_vuelto'])
                                                        <div class="mt-2 flex items-center gap-2"
                                                            x-data="{
                                                                recibido: {{ $pago['monto_recibido'] }},
                                                                montoFinal: {{ $pago['monto_final'] }},
                                                                iniciado: false,
                                                                get vuelto() {
                                                                    return Math.max(0, Math.round((this.recibido - this.montoFinal) * 100) / 100);
                                                                },
                                                                onKeydown(e) {
                                                                    if (!this.iniciado && e.key >= '0' && e.key <= '9') {
                                                                        e.preventDefault();
                                                                        this.iniciado = true;
                                                                        this.recibido = parseFloat(e.key);
                                                                        this.$dispatch('vuelto-updated', { index: {{ $index }}, recibido: this.recibido });
                                                                        this.$nextTick(() => {
                                                                            const input = this.$refs.inputRecibido;
                                                                            if (input) { input.value = e.key; input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
                                                                        });
                                                                    } else if (e.key !== 'Tab' && e.key !== 'Escape') {
                                                                        this.iniciado = true;
                                                                    }
                                                                },
                                                                onInput(e) {
                                                                    this.recibido = parseFloat(e.target.value) || 0;
                                                                    this.iniciado = true;
                                                                    this.$dispatch('vuelto-updated', { index: {{ $index }}, recibido: this.recibido });
                                                                },
                                                                sync() {
                                                                    $wire.actualizarMontoRecibido({{ $index }}, this.recibido);
                                                                }
                                                            }"
                                                            @click.away="sync()"
                                                        >
                                                            <label class="text-xs text-gray-600 dark:text-gray-400">{{ __('Recibido') }}:</label>
                                                            <div class="relative">
                                                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    x-ref="inputRecibido"
                                                                    :value="recibido"
                                                                    @keydown="onKeydown($event)"
                                                                    @input="onInput($event)"
                                                                    @blur="sync()"
                                                                    class="w-28 pl-6 pr-2 py-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                                                    tabindex="-1">
                                                            </div>
                                                            <span class="text-lg font-bold transition-all"
                                                                :class="vuelto > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-300 dark:text-gray-600'"
                                                                x-text="'{{ __('Vuelto') }}: $' + vuelto.toFixed(2).replace('.', ',')"
                                                            ></span>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="flex items-center gap-3">
                                                    <div class="text-right">
                                                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                            $@precio($pago['monto_final'])
                                                        </div>
                                                        @if($pago['recargo_cuotas'] > 0)
                                                            <div class="text-xs text-red-600">
                                                                +$@precio(($pago['monto_base'] + $pago['monto_ajuste']) * $pago['recargo_cuotas'] / 100) cuotas
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <button
                                                        wire:click="eliminarDelDesglose({{ $index }})"
                                                        class="text-red-500 hover:text-red-700 p-1"
                                                        title="{{ __('Eliminar forma de pago') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Agregar forma de pago (solo si hay pendiente o es mixta) --}}
                        @if($montoPendienteDesglose > 0.01)
                            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-gray-50 dark:bg-gray-700"
                                x-data="{
                                    busqueda: '',
                                    fpSeleccionadaId: @entangle('nuevoPago.forma_pago_id').live,
                                    formasPago: @js(collect($formasPagoSucursal)->where('es_mixta', false)->values()->toArray()),
                                    navIndex: -1,
                                    cols: window.innerWidth >= 640 ? 4 : 3,
                                    get filtradas() {
                                        if (!this.busqueda) return this.formasPago;
                                        const q = this.busqueda.trim().toLowerCase();
                                        return this.formasPago.filter(fp =>
                                            String(fp.id) === q ||
                                            fp.nombre.toLowerCase().includes(q) ||
                                            (fp.codigo && fp.codigo.toLowerCase().includes(q))
                                        );
                                    },
                                    seleccionar(fp) {
                                        this.fpSeleccionadaId = fp.id;
                                        this.busqueda = '';
                                        this.navIndex = -1;
                                    },
                                    limpiar() {
                                        this.fpSeleccionadaId = null;
                                        this.busqueda = '';
                                        this.navIndex = -1;
                                        this.$nextTick(() => {
                                            if (this.$refs.inputBusquedaFP) this.$refs.inputBusquedaFP.focus();
                                        });
                                    },
                                    handleBusquedaKeydown(e) {
                                        const len = this.filtradas.length;
                                        if (e.key === 'ArrowDown') {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            if (this.navIndex < 0) { this.navIndex = 0; }
                                            else { this.navIndex = Math.min(this.navIndex + this.cols, len - 1); }
                                        } else if (e.key === 'ArrowUp') {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            if (this.navIndex >= this.cols) { this.navIndex -= this.cols; }
                                            else { this.navIndex = -1; this.$refs.inputBusquedaFP?.focus(); }
                                        } else if (e.key === 'ArrowRight') {
                                            if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.min(this.navIndex + 1, len - 1); }
                                        } else if (e.key === 'ArrowLeft') {
                                            if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.max(this.navIndex - 1, 0); }
                                        } else if (e.key === 'Enter') {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            if (this.navIndex >= 0 && this.navIndex < len) {
                                                this.seleccionar(this.filtradas[this.navIndex]);
                                            } else if (len > 0) {
                                                this.seleccionar(this.filtradas[0]);
                                            }
                                        } else {
                                            this.navIndex = -1;
                                        }
                                    },
                                    handleMontoKeydown(e) {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            $wire.agregarAlDesglose();
                                        }
                                    },
                                    fpNombre(fp) {
                                        return (fp.codigo || fp.nombre.substring(0,3).toUpperCase());
                                    }
                                }"
                                x-init="$nextTick(() => { if (!fpSeleccionadaId && $refs.inputBusquedaFP) $refs.inputBusquedaFP.focus(); })"
                            >
                                {{-- Selector de forma de pago por botones --}}
                                <template x-if="!fpSeleccionadaId">
                                    <div>
                                        {{-- Input de búsqueda --}}
                                        <div class="relative mb-2">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            </span>
                                            <input
                                                type="text"
                                                x-ref="inputBusquedaFP"
                                                x-model="busqueda"
                                                @keydown="handleBusquedaKeydown($event)"
                                                class="w-full pl-8 pr-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                                placeholder="{{ __('Buscar por ID, código o nombre...') }}">
                                        </div>

                                        {{-- Grid de botones de formas de pago --}}
                                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5 max-h-40 overflow-y-auto" x-ref="gridFP">
                                            <template x-for="(fp, idx) in filtradas" :key="fp.id">
                                                <button
                                                    type="button"
                                                    @click="seleccionar(fp)"
                                                    class="flex flex-col items-center justify-center p-2 rounded-lg border-2 transition-all text-center min-h-[52px]"
                                                    :class="navIndex === idx
                                                        ? 'border-green-500 bg-green-50 dark:bg-green-900/30 ring-2 ring-green-400'
                                                        : 'border-gray-200 dark:border-gray-600 hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20'"
                                                    :x-ref="'fp-btn-' + idx"
                                                >
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono leading-none" x-text="fp.id"></span>
                                                    <span class="text-xs font-bold text-green-700 dark:text-green-400 uppercase tracking-wide leading-tight" x-text="fp.codigo || fp.nombre.substring(0, 3).toUpperCase()"></span>
                                                    <span class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight mt-0.5 truncate w-full" x-text="fp.nombre"></span>
                                                    <template x-if="fp.ajuste_porcentaje != 0">
                                                        <span class="text-[9px] font-medium mt-0.5"
                                                            :class="fp.ajuste_porcentaje > 0 ? 'text-red-600' : 'text-green-600'"
                                                            x-text="(fp.ajuste_porcentaje > 0 ? '+' : '') + fp.ajuste_porcentaje + '%'"></span>
                                                    </template>
                                                </button>
                                            </template>
                                        </div>

                                        {{-- Sin resultados --}}
                                        <template x-if="busqueda && filtradas.length === 0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-2">{{ __('No se encontraron formas de pago') }}</p>
                                        </template>
                                    </div>
                                </template>

                                {{-- FP seleccionada: mostrar chip + monto + agregar --}}
                                <template x-if="fpSeleccionadaId">
                                    <div x-data="{
                                        montoLocal: @entangle('nuevoPago.monto').live,
                                        tasaLocal: @entangle('nuevoPago.tipo_cambio_tasa').live,
                                        get fpActual() {
                                            return formasPago.find(fp => fp.id == fpSeleccionadaId) || null;
                                        },
                                        get esMonedaExt() {
                                            return this.fpActual && (this.fpActual.es_moneda_extranjera || false);
                                        },
                                        get simbolo() {
                                            if (this.esMonedaExt && this.fpActual.moneda_info) return this.fpActual.moneda_info.codigo || this.fpActual.moneda_info.simbolo || '$';
                                            return '$';
                                        },
                                        get codigoLabel() {
                                            if (!this.fpActual) return '';
                                            return this.fpActual.codigo || this.fpActual.nombre.substring(0,3).toUpperCase();
                                        },
                                        get equivalente() {
                                            const m = parseFloat(this.montoLocal) || 0;
                                            const t = parseFloat(this.tasaLocal) || 0;
                                            return m * t;
                                        },
                                        get equivalenteFormat() {
                                            return this.equivalente.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        }
                                    }" x-init="$nextTick(() => { $refs.inputMontoDesglose?.focus() })">
                                        <div class="flex items-center gap-2">
                                            {{-- Chip de FP seleccionada --}}
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-lg text-sm font-medium">
                                                <span class="font-bold uppercase" x-text="codigoLabel"></span>
                                                <span class="text-green-600 dark:text-green-400" x-text="fpActual ? fpActual.nombre : ''"></span>
                                                <template x-if="fpActual && fpActual.ajuste_porcentaje != 0">
                                                    <span class="text-xs"
                                                        :class="fpActual.ajuste_porcentaje > 0 ? 'text-red-600' : 'text-green-600'"
                                                        x-text="'(' + (fpActual.ajuste_porcentaje > 0 ? '+' : '') + fpActual.ajuste_porcentaje + '%)'"></span>
                                                </template>
                                                <button type="button" @click="limpiar()" class="ml-0.5 text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>

                                            {{-- Monto --}}
                                            <div class="relative flex-1">
                                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-xs font-medium pointer-events-none" x-text="simbolo"></span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    x-ref="inputMontoDesglose"
                                                    x-model="montoLocal"
                                                    @keydown="handleMontoKeydown($event)"
                                                    class="w-full pr-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                                    :class="esMonedaExt ? 'pl-12' : 'pl-6'"
                                                    :placeholder="esMonedaExt ? '0.00' : '{{ number_format($montoPendienteDesglose, 2, ',', '.') }}'"
                                                    title="{{ __('Vacío = monto pendiente completo') }}">
                                            </div>

                                            {{-- Botón Agregar --}}
                                            <button
                                                wire:click="agregarAlDesglose"
                                                type="button"
                                                x-ref="btnAgregar"
                                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 whitespace-nowrap">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                {{ __('Agregar') }}
                                            </button>
                                        </div>

                                        {{-- Cotización (si moneda extranjera) --}}
                                        <template x-if="esMonedaExt">
                                            <div class="mt-2 flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md px-2 py-1.5">
                                                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('Cotización') }} (<span x-text="fpActual?.moneda_info?.codigo || ''"></span>):</span>
                                                <div class="relative w-24">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        x-model="tasaLocal"
                                                        class="w-full px-2 py-1 text-sm border-amber-300 dark:border-amber-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                                        placeholder="0.00">
                                                </div>
                                                <template x-if="equivalente > 0">
                                                    <span class="text-xs text-amber-600 dark:text-amber-400" x-text="'= $' + equivalenteFormat"></span>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Cuotas (si aplica) - en fila separada, dropdown hacia arriba --}}
                                        @if(count($cuotasDisponibles) > 0)
                                            <div class="relative mt-2">
                                                @php
                                                    $cuotaSelDesglose = collect($cuotasDesgloseConMontos)->firstWhere('cantidad', $nuevoPago['cuotas']);
                                                    $montoBase = (float) ($nuevoPago['monto'] ?? 0) ?: $montoPendienteDesglose;
                                                    $fpDesglose = collect($formasPagoSucursal)->firstWhere('id', (int) $nuevoPago['forma_pago_id']);
                                                    $ajusteDesglose = $fpDesglose ? ($fpDesglose['ajuste_porcentaje'] ?? 0) : 0;
                                                    $montoConAjusteDesglose = round($montoBase + ($montoBase * $ajusteDesglose / 100), 2);
                                                @endphp

                                                {{-- Dropdown de opciones (arriba del selector) --}}
                                                @if($cuotasDesgloseSelectorAbierto)
                                                    <div class="absolute z-30 left-0 right-0 bottom-full mb-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-40 overflow-y-auto">
                                                        {{-- Opción: 1 pago --}}
                                                        <div
                                                            wire:click="seleccionarCuotaDesglose(1)"
                                                            class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $nuevoPago['cuotas'] == 1 ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}"
                                                        >
                                                            <div class="flex-1">
                                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</span>
                                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">{{ __('sin financiación') }}</span>
                                                            </div>
                                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">$@precio($montoConAjusteDesglose)</span>
                                                            @if($nuevoPago['cuotas'] == 1)
                                                                <svg class="w-3 h-3 text-blue-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                        </div>

                                                        {{-- Opciones de cuotas --}}
                                                        @foreach($cuotasDesgloseConMontos as $cuota)
                                                            <div
                                                                wire:click="seleccionarCuotaDesglose({{ $cuota['cantidad'] }})"
                                                                class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $nuevoPago['cuotas'] == $cuota['cantidad'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}"
                                                            >
                                                                <div class="flex-1">
                                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad'] }} cuotas</span>
                                                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">de $@precio($cuota['valor_cuota'])</span>
                                                                </div>
                                                                @if($cuota['recargo'] > 0)
                                                                    <span class="text-xs font-medium text-red-600 mx-2">+{{ $cuota['recargo'] }}%</span>
                                                                @endif
                                                                <span class="text-sm font-semibold {{ $cuota['recargo'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuota['total_con_recargo'])</span>
                                                                @if($nuevoPago['cuotas'] == $cuota['cantidad'])
                                                                    <svg class="w-3 h-3 text-blue-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                {{-- Selector visible --}}
                                                <div
                                                    wire:click="toggleCuotasDesgloseSelector"
                                                    class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                                >
                                                    @if($nuevoPago['cuotas'] == 1 || !$cuotaSelDesglose)
                                                        <div class="flex items-center px-2 py-1">
                                                            <div class="flex-1 min-w-0">
                                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</span>
                                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">{{ __('sin financiación') }}</span>
                                                            </div>
                                                            <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">$@precio($montoConAjusteDesglose)</span>
                                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasDesgloseSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                            </svg>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center px-2 py-1">
                                                            <div class="flex-1 min-w-0">
                                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cuotaSelDesglose['cantidad'] }} cuotas</span>
                                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">de $@precio($cuotaSelDesglose['valor_cuota'])</span>
                                                            </div>
                                                            @if($cuotaSelDesglose['recargo'] > 0)
                                                                <span class="text-xs font-medium text-red-600 mx-2">+{{ $cuotaSelDesglose['recargo'] }}%</span>
                                                            @endif
                                                            <span class="text-sm font-semibold {{ $cuotaSelDesglose['recargo'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuotaSelDesglose['total_con_recargo'])</span>
                                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasDesgloseSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>{{-- /x-data fpActual --}}
                                </template>
                            </div>
                        @endif

                        {{-- Total vuelto general --}}
                        @php
                            $vueltoTotal = collect($desglosePagos)->sum('vuelto');
                            $montoFiscalDesglose = collect($desglosePagos)->where('factura_fiscal', true)->sum('monto_final');
                            $cantidadFiscales = collect($desglosePagos)->where('factura_fiscal', true)->count();
                            $pagosConVuelto = collect($desglosePagos)->filter(fn($p) => $p['permite_vuelto'])->values();
                        @endphp
                        @if($pagosConVuelto->count() > 0)
                            <div
                                x-data="{
                                    vueltoBase: {{ $vueltoTotal }},
                                    pagosVuelto: @js($pagosConVuelto->map(fn($p, $i) => ['index' => collect($desglosePagos)->search($p), 'monto_final' => $p['monto_final'], 'monto_recibido' => $p['monto_recibido']])->values()->toArray()),
                                    overrides: {},
                                    get vueltoTotal() {
                                        let total = 0;
                                        for (const p of this.pagosVuelto) {
                                            const recibido = this.overrides[p.index] !== undefined ? this.overrides[p.index] : p.monto_recibido;
                                            total += Math.max(0, Math.round((recibido - p.monto_final) * 100) / 100);
                                        }
                                        return total;
                                    }
                                }"
                                @vuelto-updated.window="overrides[$event.detail.index] = $event.detail.recibido"
                                x-show="vueltoTotal > 0"
                                x-transition
                                class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 rounded-xl p-5 text-center"
                            >
                                <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wide mb-2">{{ __('Vuelto a entregar') }}</p>
                                <p class="text-5xl font-extrabold text-green-600 dark:text-green-400" x-text="'$' + vueltoTotal.toFixed(2).replace('.', ',')"></p>
                            </div>
                        @endif

                        {{-- Resumen de Facturación Fiscal --}}
                        @if(count($desglosePagos) > 0)
                            <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg p-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ __('Facturación Fiscal') }}</span>
                                            @if($cantidadFiscales > 0)
                                                <span class="text-xs text-indigo-500 dark:text-indigo-400 ml-1">({{ $cantidadFiscales }} FP)</span>
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">({{ __('sin factura') }})</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if($montoFiscalDesglose > 0)
                                            <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($montoFiscalDesglose, 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-lg font-medium text-gray-400">$0,00</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 flex flex-row-reverse gap-2">
                        <button
                            wire:click="confirmarPago"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            @if(!$this->desgloseCompleto()) disabled @endif
                            type="button"
                            class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg wire:loading.remove class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove>{{ __('Confirmar') }}</span>
                            <span wire:loading>{{ __('Procesando...') }}</span>
                        </button>
                        <button
                            wire:click="cerrarModalPago"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-3 py-2 bg-white dark:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Volver') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Simple de Pago en Moneda Extranjera --}}
    @if($mostrarModalMonedaExtranjera)
        @php
            $meEquivPrincipal = (float)($pagoMonedaExtranjera['equivalente_principal'] ?? 0);
            $meTotalVenta = (float)($pagoMonedaExtranjera['total_venta'] ?? 0);
            $meVuelto = (float)($pagoMonedaExtranjera['vuelto'] ?? 0);
            $meEsInsuficiente = $meEquivPrincipal < $meTotalVenta - 0.01;
            $meSinDatos = $meEquivPrincipal <= 0;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-moneda-ext" role="dialog" aria-modal="true"
            x-data="{
                iniciado: false,
                init() {
                    this.$nextTick(() => {
                        const input = this.$refs.inputMontoExtranjera;
                        if (input) { input.focus(); }
                    });
                },
                onKeydown(e) {
                    const input = this.$refs.inputMontoExtranjera;
                    if (!this.iniciado && input && e.key >= '0' && e.key <= '9') {
                        e.preventDefault();
                        this.iniciado = true;
                        $wire.set('pagoMonedaExtranjera.monto_extranjera', e.key);
                        this.$nextTick(() => {
                            input.focus();
                            input.setSelectionRange(input.value.length, input.value.length);
                        });
                    } else if (e.key >= '0' && e.key <= '9' || e.key === '.' || e.key === ',' || e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Tab') {
                        this.iniciado = true;
                    }
                },
                confirmar() {
                    const input = this.$refs.inputMontoExtranjera;
                    const monto = parseFloat(input ? input.value : 0) || 0;
                    const cotizacion = parseFloat($wire.get('pagoMonedaExtranjera.cotizacion') || 0);
                    if (monto > 0 && cotizacion > 0) {
                        // Asegurar que el valor del input llegue al servidor antes de confirmar
                        $wire.set('pagoMonedaExtranjera.monto_extranjera', monto).then(() => {
                            $wire.confirmarPagoMonedaExtranjera();
                        });
                    }
                }
            }"
            @keydown.escape.window="$wire.cerrarModalMonedaExtranjera()"
            @keydown.f2.window.prevent="confirmar()"
            @keydown.enter.window.prevent="confirmar()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalMonedaExtranjera"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full w-full">
                    {{-- Header --}}
                    <div class="bg-amber-600 px-4 py-3 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('Pago en') }} {{ $pagoMonedaExtranjera['moneda_codigo'] }} — {{ $pagoMonedaExtranjera['nombre'] }}
                        </h3>
                    </div>

                    <div class="px-4 py-5 sm:p-6 space-y-5">
                        {{-- Total a pagar --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-5 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Total a pagar') }}</p>
                            <p class="text-4xl font-extrabold text-gray-900 dark:text-white">${{ number_format($pagoMonedaExtranjera['total_venta'], 2, ',', '.') }}</p>
                        </div>

                        {{-- Cotización (informativa) --}}
                        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Cotización') }}: <span class="font-semibold text-gray-700 dark:text-gray-300">1 {{ $pagoMonedaExtranjera['moneda_codigo'] }} = ${{ number_format((float)($pagoMonedaExtranjera['cotizacion'] ?? 0), 2, ',', '.') }}</span>
                        </p>

                        {{-- Monto en moneda extranjera --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('¿Cuántos') }} {{ $pagoMonedaExtranjera['moneda_codigo'] }} {{ __('entrega?') }}
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-amber-600 dark:text-amber-400 font-bold text-lg">{{ $pagoMonedaExtranjera['moneda_simbolo'] }}</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    x-ref="inputMontoExtranjera"
                                    @keydown="onKeydown($event)"
                                    wire:model.live.debounce.200ms="pagoMonedaExtranjera.monto_extranjera"
                                    class="w-full pl-10 pr-3 py-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50 text-2xl font-bold text-right"
                                    placeholder="0.00">
                            </div>
                        </div>

                        {{-- Equivalente en moneda principal --}}
                        @if(!$meSinDatos)
                            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-center">
                                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-1">{{ __('Equivale a') }}</p>
                                <p class="text-2xl font-extrabold text-blue-800 dark:text-blue-200">${{ number_format($meEquivPrincipal, 2, ',', '.') }}</p>
                            </div>
                        @endif

                        {{-- Vuelto / Falta --}}
                        @if(!$meSinDatos)
                            <div class="rounded-xl p-5 text-center {{ $meEsInsuficiente ? 'bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800' : 'bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800' }}">
                                <p class="text-xs uppercase tracking-wide mb-2 {{ $meEsInsuficiente ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $meEsInsuficiente ? __('Falta') : __('Vuelto') }}
                                </p>
                                <p class="text-5xl font-extrabold {{ $meEsInsuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    ${{ number_format($meEsInsuficiente ? ($meTotalVenta - $meEquivPrincipal) : $meVuelto, 2, ',', '.') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                        <button
                            @click="confirmar()"
                            type="button"
                            @if($meSinDatos || $meEsInsuficiente) disabled @endif
                            class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-5 py-2.5 text-base font-medium text-white sm:text-sm transition
                                {{ !$meSinDatos && !$meEsInsuficiente
                                    ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                                    : 'bg-gray-400 cursor-not-allowed' }}
                                focus:outline-none focus:ring-2 focus:ring-offset-2">
                            {{ __('Confirmar Pago') }}
                            <kbd class="ml-2 px-1.5 py-0.5 text-xs {{ !$meSinDatos && !$meEsInsuficiente ? 'bg-green-800' : 'bg-gray-500' }} rounded">F2</kbd>
                        </button>
                        <button
                            wire:click="cerrarModalMonedaExtranjera"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Cobro con Vuelto (Moneda Local) --}}
    @if($mostrarModalVuelto)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-vuelto" role="dialog" aria-modal="true"
            x-data="{
                iniciado: false,
                recibido: {{ (float)($pagoConVuelto['monto_recibido'] ?? 0) }},
                totalAPagar: {{ (float)($pagoConVuelto['total_a_pagar'] ?? 0) }},
                get vuelto() {
                    return Math.max(0, Math.round((this.recibido - this.totalAPagar) * 100) / 100);
                },
                get falta() {
                    return Math.max(0, Math.round((this.totalAPagar - this.recibido) * 100) / 100);
                },
                get esInsuficiente() {
                    return this.recibido < this.totalAPagar - 0.01;
                },
                init() {
                    this.$nextTick(() => {
                        const input = this.$refs.inputMontoRecibido;
                        if (input) input.focus();
                    });
                },
                onKeydown(e) {
                    const input = this.$refs.inputMontoRecibido;
                    if (!this.iniciado && input && e.key >= '0' && e.key <= '9') {
                        e.preventDefault();
                        this.iniciado = true;
                        this.recibido = parseFloat(e.key);
                        this.$nextTick(() => {
                            input.value = e.key;
                            input.focus();
                            input.setSelectionRange(input.value.length, input.value.length);
                        });
                    } else if (e.key !== 'Tab' && e.key !== 'Escape' && e.key !== 'Enter' && e.key !== 'F2') {
                        this.iniciado = true;
                    }
                },
                onInput(e) {
                    this.recibido = parseFloat(e.target.value) || 0;
                    this.iniciado = true;
                },
                confirmar() {
                    if (!this.esInsuficiente) {
                        $wire.set('pagoConVuelto.monto_recibido', this.recibido).then(() => {
                            $wire.confirmarPagoConVuelto();
                        });
                    }
                }
            }"
            @keydown.escape.window="$wire.cerrarModalVuelto()"
            @keydown.f2.window.prevent="confirmar()"
            @keydown.enter.window.prevent="confirmar()"
            @keydown.window="onKeydown($event)">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalVuelto"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full w-full">
                    {{-- Header --}}
                    <div class="bg-green-600 px-4 py-3 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ __('Cobrar') }} — {{ $pagoConVuelto['nombre'] }}
                        </h3>
                    </div>

                    <div class="px-4 py-5 sm:p-6 space-y-5">
                        {{-- Total a pagar --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-5 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Total a pagar') }}</p>
                            <p class="text-4xl font-extrabold text-gray-900 dark:text-white">${{ number_format($pagoConVuelto['total_a_pagar'], 2, ',', '.') }}</p>
                        </div>

                        {{-- Monto recibido --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Monto recibido') }}
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-green-600 dark:text-green-400 font-bold text-lg">$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    x-ref="inputMontoRecibido"
                                    :value="recibido"
                                    @input="onInput($event)"
                                    class="w-full pl-8 pr-3 py-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50 text-2xl font-bold text-right"
                                >
                            </div>
                        </div>

                        {{-- Vuelto / Falta --}}
                        <div class="rounded-xl p-5 text-center border-2 transition-colors"
                            :class="esInsuficiente
                                ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                                : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'"
                        >
                            <p class="text-xs uppercase tracking-wide mb-2"
                                :class="esInsuficiente ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                x-text="esInsuficiente ? '{{ __('Falta') }}' : '{{ __('Vuelto') }}'">
                            </p>
                            <p class="text-5xl font-extrabold"
                                :class="esInsuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                x-text="'$' + (esInsuficiente ? falta : vuelto).toFixed(2).replace('.', ',')">
                            </p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                        <button
                            @click="confirmar()"
                            type="button"
                            :disabled="esInsuficiente"
                            class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-5 py-2.5 text-base font-medium text-white sm:text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="!esInsuficiente
                                ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                                : 'bg-gray-400 cursor-not-allowed'">
                            {{ __('Confirmar Pago') }}
                            <kbd class="ml-2 px-1.5 py-0.5 text-xs rounded" :class="!esInsuficiente ? 'bg-green-800' : 'bg-gray-500'">F2</kbd>
                        </button>
                        <button
                            wire:click="cerrarModalVuelto"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Alta de Cliente (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-cliente-rapido')

    {{-- Modal: Selección de Punto de Venta Fiscal --}}
    @if($showPuntoVentaModal)
        <x-bcn-modal
            :show="$showPuntoVentaModal"
            title="{{ __('Seleccionar Punto de Venta Fiscal') }}"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cancelarSeleccionPuntoVenta"
            zIndex="z-[70]"
        >
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    {{ __('La caja tiene múltiples puntos de venta configurados. Seleccione con cuál desea emitir el comprobante fiscal:') }}
                </p>

                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($puntosVentaDisponibles as $pv)
                        <label
                            class="flex items-center p-3 border rounded-lg cursor-pointer transition-all
                                {{ $puntoVentaSeleccionadoId == $pv['id']
                                    ? 'border-bcn-primary bg-bcn-primary/10 dark:bg-bcn-primary/20'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-bcn-primary/50' }}"
                        >
                            <input
                                type="radio"
                                wire:model="puntoVentaSeleccionadoId"
                                value="{{ $pv['id'] }}"
                                class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 dark:border-gray-600"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                        PV {{ $pv['numero_formateado'] }}
                                        @if($pv['nombre'])
                                            - {{ $pv['nombre'] }}
                                        @endif
                                    </span>
                                    @if($pv['es_defecto'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ __('Por defecto') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    <span class="font-mono">{{ $pv['cuit_numero'] }}</span>
                                    @if($pv['cuit_razon_social'])
                                        <span class="ml-1">- {{ $pv['cuit_razon_social'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    @click="close()"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    wire:click="confirmarPuntoVenta"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Confirmar y Facturar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Script para scroll y efectos --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('scroll-to-item', (data) => {
                const index = data.index;
                const row = document.querySelector(`[data-item-index="${index}"]`);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Efecto de resaltado
                    row.classList.add('animate-pulse', 'bg-yellow-200');
                    setTimeout(() => {
                        row.classList.remove('animate-pulse', 'bg-yellow-200');
                        @this.limpiarResaltado();
                    }, 2000);
                }
            });

            // Listener para impresión automática de venta
            Livewire.on('venta-completada', async (data) => {
                // Verificar que QZIntegration esté disponible
                if (typeof window.QZIntegration === 'undefined') {
                    console.warn('QZIntegration no está disponible. QZ Tray no instalado o no conectado.');
                    return;
                }

                const { ventaId, imprimirTicket, imprimirFactura, comprobanteId } = data[0] || data;

                try {
                    // Conectar a QZ Tray si no está conectado
                    const conectado = await window.QZIntegration.conectar();
                    if (!conectado) {
                        console.warn('No se pudo conectar a QZ Tray');
                        return;
                    }

                    // Imprimir ticket si está habilitado
                    if (imprimirTicket && ventaId) {
                        const ticketResponse = await fetch(`/api/impresion/venta/${ventaId}/ticket`);
                        if (ticketResponse.ok) {
                            const ticketData = await ticketResponse.json();
                            if (ticketData.tipo === 'escpos') {
                                await window.QZIntegration.imprimirESCPOS(
                                    ticketData.impresora,
                                    ticketData.datos,
                                    ticketData.opciones
                                );
                            } else {
                                await window.QZIntegration.imprimirHTML(
                                    ticketData.impresora,
                                    ticketData.datos,
                                    ticketData.opciones
                                );
                            }
                        }
                    }

                    // Imprimir factura si está habilitado
                    if (imprimirFactura && comprobanteId) {
                        const facturaResponse = await fetch(`/api/impresion/factura/${comprobanteId}`);
                        if (facturaResponse.ok) {
                            const facturaData = await facturaResponse.json();
                            if (facturaData.tipo === 'escpos') {
                                await window.QZIntegration.imprimirESCPOS(
                                    facturaData.impresora,
                                    facturaData.datos,
                                    facturaData.opciones
                                );
                            } else {
                                await window.QZIntegration.imprimirHTML(
                                    facturaData.impresora,
                                    facturaData.datos,
                                    facturaData.opciones
                                );
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error al imprimir:', error);
                }
            });
        });
    </script>

    {{-- Modal de Confirmación: Limpiar Carrito --}}
    @if($mostrarConfirmLimpiar)
        <x-bcn-modal
            :show="$mostrarConfirmLimpiar"
            title="{{ __('¿Limpiar el carrito?') }}"
            color="bg-red-600"
            maxWidth="sm"
            onClose="cancelarLimpiarCarrito"
        >
            <x-slot:body>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Se eliminarán todos los artículos del carrito.') }}</p>
            </x-slot:body>

            <x-slot:footer>
                <button
                    @click="close()"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    wire:click="ejecutarLimpiarCarrito"
                    type="button"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Limpiar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    </x-caja-operativa-requerida>

    {{-- Modal de Artículo Pesable (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-pesable')

    {{-- Modal de Alta Rápida de Artículo (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-articulo-rapido')

    {{-- Modal de Búsqueda Avanzada de Artículos (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-busqueda-articulos')

    {{-- Wizard de Opcionales --}}
    @include('livewire.ventas._wizard-opcionales')

    {{-- Modal de Descuentos y Beneficios --}}
    @include('livewire.ventas._modal-descuentos')

    {{-- Modal de Apertura de Turno (desde AperturaTurnoTrait) --}}
    @include('components.modal-apertura-turno')
</div>
