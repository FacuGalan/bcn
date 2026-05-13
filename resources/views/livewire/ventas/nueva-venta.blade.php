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

                        {{-- Promociones Aplicadas (extraído a parcial reusable) --}}
                        @include('livewire.carrito._promociones-aplicadas')
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

                        {{-- Resumen de Totales (extraído a parcial reusable) --}}
                        @include('livewire.carrito._resumen-totales')

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

    {{-- Modal de Pago / Desglose Mixto (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-pago-mixto')

    {{-- Modal Simple de Pago en Moneda Extranjera (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-moneda-extranjera')

    {{-- Modal de Cobro con Vuelto (extraído a parcial reusable) --}}
    @include('livewire.carrito._modal-vuelto')

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
