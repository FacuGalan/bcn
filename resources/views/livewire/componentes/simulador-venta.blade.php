<div class="border dark:border-gray-700 rounded-lg">
    <div class="bg-purple-50 dark:bg-purple-900/20 px-4 py-3 border-b dark:border-gray-700">
        <h3 class="font-semibold text-purple-900 dark:text-purple-300 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            {{ __('Simulador de Venta') }}
        </h3>
        <p class="text-xs text-purple-700 dark:text-purple-400 mt-1">{{ __('Prueba cómo se aplicarían las promociones') }}</p>
    </div>

    <div class="p-3 sm:p-4 space-y-3">
        {{-- Filtros del simulador - Colapsable --}}
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg">
            {{-- Header colapsable --}}
            <button type="button"
                    wire:click="$toggle('mostrarFiltrosSimulador')"
                    class="w-full flex items-center justify-between p-3 text-xs font-medium text-gray-600 dark:text-gray-300 uppercase">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    {{ __('Contexto de la venta') }}
                </span>
                <svg class="w-4 h-4 transition-transform {{ $mostrarFiltrosSimulador ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Contenido de filtros --}}
            <div class="p-3 pt-0 space-y-2 {{ $mostrarFiltrosSimulador ? '' : 'hidden' }}">

                {{-- Grid de filtros: 1 col en móvil, 2 cols en desktop --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {{-- Sucursal --}}
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('Sucursal') }}</label>
                        <select wire:model.live="simuladorSucursalId"
                                class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            @foreach($sucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Lista de Precios --}}
                    <div wire:key="lista-precios-{{ $simuladorSucursalId }}">
                        <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('Lista de Precios') }}</label>
                        <select wire:model.live="simuladorListaPrecioId"
                                class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            @forelse($listasPreciosSimulador as $lista)
                                <option value="{{ $lista['id'] }}" @selected($simuladorListaPrecioId == $lista['id'])>
                                    {{ $lista['nombre'] }}
                                    @if($lista['es_lista_base'])
                                        (Base{{ $lista['ajuste_porcentaje'] != 0 ? ', ' . ($lista['ajuste_porcentaje'] > 0 ? '+' : '') . $lista['ajuste_porcentaje'] . '%' : '' }})
                                    @elseif($lista['ajuste_porcentaje'] != 0)
                                        ({{ $lista['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $lista['ajuste_porcentaje'] }}%)
                                    @endif
                                </option>
                            @empty
                                <option value="">{{ __('Sin listas') }}</option>
                            @endforelse
                        </select>
                    </div>

                    {{-- Forma de Venta --}}
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('Forma Venta') }}</label>
                        <select wire:model.live="simuladorFormaVentaId"
                                class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach($formasVenta as $fv)
                                <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Canal de Venta --}}
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('Canal Venta') }}</label>
                        <select wire:model.live="simuladorCanalVentaId"
                                class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="">{{ __('Todos') }}</option>
                            @foreach($canalesVenta as $cv)
                                <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Forma de Pago --}}
                    <div class="sm:col-span-2">
                        <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('Forma Pago') }}</label>
                        <select wire:model.live="simuladorFormaPagoId"
                                class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach($formasPago as $fp)
                                <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Buscador de artículos --}}
        <div class="relative">
            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="abrirBuscadorArticulos"
                        class="flex-1 flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-bcn-primary transition">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Agregar artículo...') }}</span>
                </button>
            </div>

            {{-- Modal/Dropdown de búsqueda --}}
            @if($mostrarBuscadorArticulos)
                <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded-lg shadow-xl">
                    <div class="p-2 border-b dark:border-gray-700">
                        <input type="text"
                               wire:model.live.debounce.200ms="busquedaArticuloSimulador"
                               wire:keydown.enter="agregarPrimerArticulo"
                               wire:keydown.escape="cerrarBuscadorArticulos"
                               :placeholder="__('Nombre, código o escanear código de barras...')"
                               x-init="$nextTick(() => $el.focus())"
                               class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    <div class="max-h-48 overflow-y-auto">
                        @forelse($articulosSimuladorResultados as $art)
                            @php
                                $precioBase = $art['precio_base'] ?? $art['precio'] ?? 0;
                                $precioLista = $art['precio'] ?? 0;
                                $tieneAjuste = abs($precioLista - $precioBase) > 0.01;
                            @endphp
                            <button type="button"
                                    wire:click="agregarArticuloSimulador({{ $art['id'] }})"
                                    class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-gray-700 border-b dark:border-gray-700 last:border-b-0 flex items-center justify-between text-sm">
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                                    @if($art['codigo'])
                                        <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">({{ $art['codigo'] }})</span>
                                    @endif
                                </div>
                                <div class="text-right flex-shrink-0 ml-2">
                                    @if($tieneAjuste)
                                        <span class="text-gray-400 dark:text-gray-500 text-xs line-through">$@precio($precioBase)</span>
                                        <span class="{{ $precioLista < $precioBase ? 'text-green-600' : 'text-red-600' }} font-medium ml-1">
                                            $@precio($precioLista)
                                        </span>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300 font-medium">$@precio($precioLista)</span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="px-3 py-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                {{ __('No se encontraron artículos') }}
                            </div>
                        @endforelse
                    </div>
                    <div class="p-2 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Enter para agregar primero') }}</span>
                        <button type="button"
                                wire:click="cerrarBuscadorArticulos"
                                class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Items agregados con detalle de promociones integrado --}}
        @if(count($itemsSimulador) > 0)
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículos') }} ({{ count($itemsSimulador) }})</span>
                </div>

                <div class="space-y-1.5 max-h-64 overflow-y-auto">
                    @foreach($itemsSimulador as $index => $item)
                        @php
                            $precioBase = $item['precio_base'] ?? $item['precio'] ?? 0;
                            $precioLista = $item['precio'] ?? 0;
                            $cantidad = $item['cantidad'] ?? 1;
                            $subtotalItem = $precioLista * $cantidad;
                            $tieneAjusteLista = abs($precioLista - $precioBase) > 0.01;

                            // Buscar info de resultado si existe
                            $itemResultado = null;
                            if ($resultadoSimulador && isset($resultadoSimulador['items'][$index])) {
                                $itemResultado = $resultadoSimulador['items'][$index];
                            }
                            $tieneDescuento = $itemResultado && ($itemResultado['total_descuento'] > 0 || $itemResultado['total_recargo'] > 0);
                        @endphp
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                            {{-- Línea principal del artículo con grid fijo --}}
                            <div class="grid grid-cols-12 gap-2 items-center">
                                {{-- Nombre y precio unitario (5 cols) --}}
                                <div class="col-span-5 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item['nombre'] ?? 'Artículo' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if($tieneAjusteLista)
                                            <span class="line-through text-gray-400">$@precio($precioBase)</span>
                                            <span class="{{ $precioLista < $precioBase ? 'text-green-600' : 'text-red-600' }} font-medium">
                                                $@precio($precioLista)
                                            </span> c/u
                                        @else
                                            $@precio($precioLista) c/u
                                        @endif
                                    </p>
                                </div>

                                {{-- Cantidad (2 cols) --}}
                                <div class="col-span-2 flex justify-center">
                                    <input type="number"
                                           wire:model.live.debounce.500ms="itemsSimulador.{{ $index }}.cantidad"
                                           min="1"
                                           class="w-14 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-center py-1.5 px-1">
                                </div>

                                {{-- Subtotal original tachado (2 cols) - reservado siempre --}}
                                <div class="col-span-2 text-right">
                                    @if($tieneDescuento)
                                        <span class="text-sm text-gray-400 dark:text-gray-500 line-through">$@precio($itemResultado['subtotal_original'])</span>
                                    @endif
                                </div>

                                {{-- Subtotal final (2 cols) --}}
                                <div class="col-span-2 text-right">
                                    @if($tieneDescuento)
                                        <span class="text-sm font-bold {{ $itemResultado['total_descuento'] > 0 ? 'text-green-600' : 'text-red-600' }}">$@precio($itemResultado['subtotal_final'])</span>
                                    @else
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">$@precio($subtotalItem)</span>
                                    @endif
                                </div>

                                {{-- Botón eliminar (1 col) --}}
                                <div class="col-span-1 flex justify-end">
                                    <button type="button" wire:click="eliminarItemSimulador({{ $index }})"
                                            class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Promociones aplicadas a este artículo --}}
                            @if($itemResultado && count($itemResultado['promociones_aplicadas']) > 0)
                                <div class="mt-1.5 pt-1.5 border-t border-gray-200 dark:border-gray-600">
                                    @foreach($itemResultado['promociones_aplicadas'] as $pa)
                                        <div class="flex items-center justify-between text-xs {{ $pa['es_nueva'] ? 'text-yellow-700 dark:text-yellow-500' : 'text-green-700 dark:text-green-500' }}">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span class="truncate">{{ $pa['nombre'] }}</span>
                                                @if($pa['es_nueva'])
                                                    <span class="px-1 py-0.5 bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 rounded text-[10px] leading-none flex-shrink-0">NUEVA</span>
                                                @endif
                                            </span>
                                            <span class="flex-shrink-0 ml-1 {{ $pa['tipo_ajuste'] === 'descuento' ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $pa['tipo_ajuste'] === 'descuento' ? '-' : '+' }}$@precio($pa['valor_ajuste'])
                                                @if($pa['porcentaje'])
                                                    <span class="text-gray-400 dark:text-gray-500">({{ $pa['porcentaje'] }}%)</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p>{{ __('Agrega artículos para simular') }}</p>
            </div>
        @endif

        {{-- Resultado de la simulación --}}
        @if($resultadoSimulador && count($resultadoSimulador['items']) > 0)
            <div class="border-t dark:border-gray-700 pt-3 space-y-3">
                {{-- Resumen de promociones --}}
                @if(!empty($resultadoSimulador['promociones_resumen']))
                    @php
                        $promoAplicadas = collect($resultadoSimulador['promociones_resumen'])->where('aplicada', true);
                        $promoNoAplicadas = collect($resultadoSimulador['promociones_resumen'])->where('aplicada', false);
                    @endphp

                    @if($promoAplicadas->count() > 0)
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-2">
                            <p class="text-xs font-semibold text-green-800 dark:text-green-400 mb-1">{{ __('Promociones aplicadas:') }}</p>
                            @foreach($promoAplicadas as $pr)
                                <div class="flex justify-between items-center text-xs {{ $pr['es_nueva'] ? 'bg-yellow-50 dark:bg-yellow-900/20 rounded px-1' : '' }}">
                                    <span class="{{ $pr['es_nueva'] ? 'text-yellow-800 dark:text-yellow-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $pr['nombre'] }}
                                        @if($pr['es_nueva'])
                                            <span class="px-1 bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 rounded">NUEVA</span>
                                        @endif
                                        <span class="text-gray-400 dark:text-gray-500">({{ count($pr['aplicada_en']) }} art.)</span>
                                    </span>
                                    <span class="text-green-600 font-medium">
                                        -$@precio($pr['total_descuento'])
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($promoNoAplicadas->count() > 0)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ __('No aplicadas:') }}</p>
                            @foreach($promoNoAplicadas as $pr)
                                <div class="text-xs text-gray-400 dark:text-gray-500 {{ $pr['es_nueva'] ? 'bg-yellow-50 dark:bg-yellow-900/20 rounded px-1' : '' }}">
                                    {{ $pr['nombre'] }}
                                    @if($pr['es_nueva'])
                                        <span class="px-1 bg-yellow-200 dark:bg-yellow-800 text-yellow-700 dark:text-yellow-300 rounded">NUEVA</span>
                                    @endif
                                    - <span class="italic">{{ $pr['razon'] ?? __('No óptima') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Promociones a nivel de venta (precio fijo) --}}
                @if(!empty($resultadoSimulador['promociones_venta']))
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-2 mt-2">
                        <p class="text-xs font-semibold text-yellow-800 dark:text-yellow-400 mb-1">{{ __('Promociones al total de la venta:') }}</p>
                        @foreach($resultadoSimulador['promociones_venta'] as $pv)
                            <div class="flex justify-between items-center text-xs {{ $pv['es_nueva'] ? 'bg-yellow-100 dark:bg-yellow-800/30 rounded px-1' : '' }}">
                                <span class="{{ $pv['es_nueva'] ? 'text-yellow-800 dark:text-yellow-300' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $pv['nombre'] }}
                                    @if($pv['es_nueva'])
                                        <span class="px-1 bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 rounded">NUEVA</span>
                                    @endif
                                    @if(isset($pv['monto_fijo']))
                                        <span class="text-gray-400 dark:text-gray-500">(Monto: $@precio($pv['monto_fijo']))</span>
                                    @endif
                                </span>
                                <span class="text-green-600 font-medium">
                                    -$@precio($pv['valor_ajuste'])
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Totales --}}
                <div class="border-t dark:border-gray-700 pt-2 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ __('Subtotal:') }}</span>
                        <span class="dark:text-white">$@precio($resultadoSimulador['subtotal'])</span>
                    </div>

                    @if($resultadoSimulador['total_descuentos'] > 0)
                        <div class="flex justify-between text-sm text-green-600">
                            <span>{{ __('Descuentos:') }}</span>
                            <span>-$@precio($resultadoSimulador['total_descuentos'])</span>
                        </div>
                    @endif

                    @if($resultadoSimulador['total_recargos'] > 0)
                        <div class="flex justify-between text-sm text-red-600">
                            <span>{{ __('Recargos:') }}</span>
                            <span>+$@precio($resultadoSimulador['total_recargos'])</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-lg font-bold border-t dark:border-gray-700 pt-2">
                        <span class="dark:text-white">TOTAL:</span>
                        <span class="text-bcn-primary">$@precio($resultadoSimulador['total_final'])</span>
                    </div>

                    @if($resultadoSimulador['total_descuentos'] > 0)
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded p-2 text-center">
                            <span class="text-green-700 dark:text-green-400 text-sm font-medium">
                                {{ __('Ahorro:') }} $@precio($resultadoSimulador['total_descuentos'])
                                @if($resultadoSimulador['subtotal'] > 0)
                                    (@porcentaje(($resultadoSimulador['total_descuentos'] / $resultadoSimulador['subtotal']) * 100))
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
