{{--
    Vista Livewire: Nueva Venta (POS)

    Sistema completo de punto de venta con:
    - Búsqueda de artículos por nombre, código y código de barras
    - Cálculo de precios según lista de precios
    - Aplicación de promociones especiales y comunes
    - Selectores de forma de venta, canal de venta, forma de pago y lista de precios

    @see App\Livewire\Ventas\NuevaVenta
--}}

<div class="h-[calc(100vh-5.5rem)] flex flex-col py-2 overflow-hidden"
     x-data
     @keydown.window.prevent.ctrl.p="$wire.activarModoConsulta()"
     @keydown.window.prevent.ctrl.b="$wire.activarModoBusqueda()"
     @keydown.window.prevent.ctrl.c="$wire.abrirModalConcepto()"
     @keydown.window.prevent.ctrl.a="$dispatch('focus-busqueda')">
    {{-- Contenido Principal del POS --}}
    <div class="flex-1 px-3 sm:px-4 lg:px-6 min-h-0">
        <div class="h-full bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col">
            {{-- Body --}}
            <div class="flex-1 px-4 py-3 min-h-0 overflow-hidden">
                <div class="h-full grid grid-cols-1 lg:grid-cols-4 gap-4">
                    {{-- Columna izquierda: Búsqueda y lista de artículos (75%) --}}
                    <div class="lg:col-span-3 flex flex-col space-y-3 min-h-0">
                        {{-- Búsqueda de artículos --}}
                        <div class="relative"
                             x-data="{ inputFocused: false, init() { this.$nextTick(() => this.$refs.inputBusqueda.focus()) } }"
                             @click.outside="inputFocused = false"
                             x-on:focus-busqueda.window="$refs.inputBusqueda.focus()">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar Artículo</label>
                            <div class="flex gap-2">
                                {{-- Input de búsqueda --}}
                                <div class="relative flex-1">
                                    <input
                                        x-ref="inputBusqueda"
                                        wire:model.live.debounce.300ms="busquedaArticulo"
                                        wire:keydown.enter="agregarPrimerArticulo"
                                        wire:keydown.escape="desactivarModos"
                                        @focus="inputFocused = true"
                                        type="text"
                                        autocomplete="off"
                                        class="block w-full pl-10 pr-3 py-2 border rounded-md leading-5 bg-white dark:bg-gray-700 dark:text-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:border-indigo-500 text-sm
                                            {{ $modoConsulta ? 'border-amber-500 ring-2 ring-amber-200 focus:ring-amber-500' : ($modoBusqueda ? 'border-blue-500 ring-2 ring-blue-200 focus:ring-blue-500' : 'border-gray-300 dark:border-gray-600 focus:ring-indigo-500') }}"
                                        placeholder="{{ $modoConsulta ? 'Buscar artículo para CONSULTAR PRECIOS...' : ($modoBusqueda ? 'Buscar artículo en el DETALLE...' : 'Buscar por nombre, código o código de barras (mín. 3 caracteres)...') }}">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        @if($modoConsulta)
                                            <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        @elseif($modoBusqueda)
                                            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                            </svg>
                                        @else
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
                                        <div wire:loading wire:target="busquedaArticulo">
                                            <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                        @if($modoConsulta || $modoBusqueda)
                                            <button
                                                wire:click="desactivarModos"
                                                type="button"
                                                class="text-gray-400 hover:text-gray-600"
                                                title="Cancelar modo (Esc)">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        @elseif(strlen($busquedaArticulo) > 0)
                                            <button
                                                wire:click="$set('busquedaArticulo', '')"
                                                type="button"
                                                class="text-gray-400 hover:text-gray-600">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Botón Consulta de Precios --}}
                                <button
                                    wire:click="activarModoConsulta"
                                    type="button"
                                    class="p-2 rounded-md transition-colors border
                                        {{ $modoConsulta ? 'bg-amber-500 text-white border-amber-600' : 'bg-amber-50 text-amber-700 border-amber-300 hover:bg-amber-100' }}"
                                    title="Consultar precios (Ctrl+P)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>

                                {{-- Botón Buscar en Detalle --}}
                                <button
                                    wire:click="activarModoBusqueda"
                                    type="button"
                                    class="p-2 rounded-md transition-colors border
                                        {{ $modoBusqueda ? 'bg-blue-500 text-white border-blue-600' : 'bg-blue-50 text-blue-700 border-blue-300 hover:bg-blue-100' }}"
                                    title="Buscar en detalle (Ctrl+B)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                </button>

                                {{-- Botón Agregar Concepto --}}
                                <button
                                    wire:click="abrirModalConcepto"
                                    type="button"
                                    class="p-2 rounded-md transition-colors border bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100"
                                    title="Agregar concepto (Ctrl+C)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Indicador de búsqueda --}}
                            @if(strlen($busquedaArticulo) > 0 && strlen($busquedaArticulo) < 3)
                                <p class="mt-1 text-xs text-gray-500">Escribe al menos 3 caracteres para buscar...</p>
                            @endif

                            {{-- Dropdown de resultados --}}
                            @if(count($articulosResultados) > 0)
                                <div
                                    x-show="inputFocused"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-80 rounded-md border border-gray-200 dark:border-gray-700 overflow-auto">
                                    <div class="py-1">
                                        @foreach($articulosResultados as $articulo)
                                            <button
                                                type="button"
                                                wire:click="agregarArticulo({{ $articulo['id'] }})"
                                                class="w-full text-left px-4 py-3 hover:bg-indigo-50 dark:hover:bg-gray-700 focus:bg-indigo-50 dark:focus:bg-gray-700 focus:outline-none border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                            {{ $articulo['nombre'] }}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            Código: {{ $articulo['codigo'] }}
                                                            @if($articulo['codigo_barras'])
                                                                | Barras: {{ $articulo['codigo_barras'] }}
                                                            @endif
                                                            @if($articulo['categoria_nombre'])
                                                                <span class="ml-2 text-indigo-600">{{ $articulo['categoria_nombre'] }}</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <div class="ml-4 flex-shrink-0 text-right">
                                                        <p class="text-sm font-semibold text-indigo-600">
                                                            $@precio($articulo['precio'])
                                                        </p>
                                                        @if($articulo['tiene_ajuste'])
                                                            <p class="text-xs text-gray-400 line-through">
                                                                $@precio($articulo['precio_base'])
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Mensaje cuando no hay resultados --}}
                            @if(strlen($busquedaArticulo) >= 3 && count($articulosResultados) === 0)
                                <div
                                    x-show="inputFocused"
                                    wire:loading.remove
                                    wire:target="busquedaArticulo"
                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-md border border-gray-200 dark:border-gray-700 p-4">
                                    <div class="text-center text-gray-500 dark:text-gray-400 text-sm">
                                        <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        No se encontraron artículos
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Lista de artículos en el carrito --}}
                        <div class="flex-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden flex flex-col min-h-0">
                            <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <h4 class="text-xs font-medium text-gray-900 dark:text-white">Items ({{ count($items) }})</h4>
                                @if($resultado && $resultado['subtotal'] > 0)
                                    <span class="text-xs text-gray-600">Subt: $@precio($resultado['subtotal'])</span>
                                @endif
                            </div>

                            @if(empty($items))
                                <div class="py-4 text-center text-gray-500">
                                    <svg class="mx-auto h-8 w-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p class="text-xs">Agrega artículos para comenzar</p>
                                </div>
                            @else
                                <div class="flex-1 overflow-y-auto min-h-0">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th class="px-2 py-1 w-8"></th>
                                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Artículo</th>
                                                <th class="px-2 py-1 text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase w-20">Cant.</th>
                                                <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">P.Unit</th>
                                                <th class="px-1 py-1 w-12"></th>{{-- Columna para ajuste manual --}}
                                                <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Subt.</th>
                                                <th class="px-2 py-1 text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase w-16">Promo</th>
                                                <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($items as $index => $item)
                                                @php
                                                    $itemResultado = $resultado['items'][$index] ?? null;
                                                    $tienePromoEspecial = $itemResultado && $itemResultado['unidades_consumidas'] > 0;
                                                    $tienePromoComun = $itemResultado && !empty($itemResultado['promociones_comunes']);
                                                    $tienePromo = $tienePromoEspecial || $tienePromoComun;
                                                    $excluido = $itemResultado && $itemResultado['excluido_promociones'];
                                                    $tieneAjuste = $item['tiene_ajuste'] ?? false;
                                                    $esDescuento = $tieneAjuste && $item['precio'] < $item['precio_base'];
                                                    $esRecargo = $tieneAjuste && $item['precio'] > $item['precio_base'];
                                                @endphp
                                                <tr data-item-index="{{ $index }}"
                                                    class="transition-colors duration-300 {{ $tienePromo ? 'bg-green-50 dark:bg-green-900/30' : ($excluido ? 'bg-yellow-50 dark:bg-yellow-900/30' : '') }} {{ $itemResaltado === $index ? 'bg-yellow-200 dark:bg-yellow-700 animate-pulse' : '' }}">
                                                    {{-- Eliminar --}}
                                                    <td class="px-2 py-1.5 text-center">
                                                        <button wire:click="eliminarItem({{ $index }})" class="text-red-600 hover:text-red-800">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                    {{-- Artículo --}}
                                                    <td class="px-2 py-1.5">
                                                        <div class="text-xs font-medium text-gray-900 dark:text-white truncate max-w-[200px]" title="{{ $item['nombre'] }}">{{ $item['nombre'] }}</div>
                                                        <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $item['codigo'] }}@if($item['categoria_nombre']) <span class="text-indigo-600 dark:text-indigo-400">| {{ $item['categoria_nombre'] }}</span>@endif</div>
                                                        @if($tienePromoEspecial && !empty($itemResultado['promociones_especiales']))
                                                            <div class="text-[10px] text-green-600">{{ implode(', ', array_map(fn($p) => is_array($p) ? ($p['nombre'] ?? '') : $p, $itemResultado['promociones_especiales'])) }}</div>
                                                        @endif
                                                        @if($tienePromoComun && !empty($itemResultado['promociones_comunes']))
                                                            <div class="text-[10px] text-blue-600">{{ implode(', ', array_map(fn($p) => is_array($p) ? ($p['nombre'] ?? '') : $p, $itemResultado['promociones_comunes'])) }}</div>
                                                        @endif
                                                        @if($excluido)
                                                            <div class="text-[10px] text-yellow-600">Sin promos</div>
                                                        @endif
                                                    </td>
                                                    {{-- Cantidad --}}
                                                    <td class="px-2 py-1.5 text-center">
                                                        <input wire:change="actualizarCantidad({{ $index }}, $event.target.value)" type="number" min="1" value="{{ $item['cantidad'] }}" class="w-16 px-1 py-0.5 text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-center">
                                                    </td>
                                                    {{-- Precio --}}
                                                    <td class="px-2 py-1.5 text-right">
                                                        @php
                                                            $tieneAjusteManual = ($item['ajuste_manual_tipo'] ?? null) !== null;
                                                            $precioMostrarTachado = $tieneAjusteManual
                                                                ? ($item['precio_sin_ajuste_manual'] ?? $item['precio_base'])
                                                                : $item['precio_base'];
                                                        @endphp
                                                        @if($tieneAjuste || $tieneAjusteManual)
                                                            <div class="text-xs font-medium {{ $item['precio'] < $precioMostrarTachado ? 'text-green-600' : 'text-red-600' }}">
                                                                $@precio($item['precio'])
                                                            </div>
                                                            <div class="text-[10px] text-gray-400 line-through">$@precio($precioMostrarTachado)</div>
                                                        @else
                                                            <div class="text-xs text-gray-900 dark:text-white">$@precio($item['precio'])</div>
                                                        @endif
                                                    </td>
                                                    {{-- Ajuste Manual --}}
                                                    <td class="px-1 py-1.5 relative">
                                                        @if(!$tieneAjusteManual)
                                                            {{-- Botones de ajuste $ y % --}}
                                                            <div class="flex flex-col gap-0.5">
                                                                <button
                                                                    wire:click="abrirAjusteManual({{ $index }}, 'monto')"
                                                                    class="w-5 h-4 flex items-center justify-center text-[9px] font-bold rounded bg-blue-100 hover:bg-blue-200 text-blue-700 border border-blue-300"
                                                                    title="Establecer precio fijo">
                                                                    $
                                                                </button>
                                                                <button
                                                                    wire:click="abrirAjusteManual({{ $index }}, 'porcentaje')"
                                                                    class="w-5 h-4 flex items-center justify-center text-[9px] font-bold rounded bg-green-100 hover:bg-green-200 text-green-700 border border-green-300"
                                                                    title="Aplicar descuento %">
                                                                    %
                                                                </button>
                                                            </div>
                                                        @else
                                                            {{-- Badge Manual (clickeable para quitar) --}}
                                                            <button
                                                                wire:click="quitarAjusteManual({{ $index }})"
                                                                class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[9px] font-medium bg-purple-100 text-purple-700 hover:bg-purple-200 border border-purple-300 cursor-pointer"
                                                                title="Clic para quitar ajuste manual">
                                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                                manual
                                                            </button>
                                                        @endif
                                                        {{-- Popover de ajuste manual --}}
                                                        @if($ajusteManualPopoverIndex === $index)
                                                            <div class="fixed z-[100] w-44 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2"
                                                                 x-data="{
                                                                     init() {
                                                                         this.$nextTick(() => {
                                                                             const btn = this.$el.previousElementSibling || this.$el.parentElement.querySelector('button');
                                                                             const rect = this.$el.parentElement.getBoundingClientRect();
                                                                             this.$el.style.top = (rect.bottom) + 'px';
                                                                             this.$el.style.right = (window.innerWidth - rect.right - 50) + 'px';
                                                                             this.$refs.ajusteInput.focus();
                                                                         });
                                                                     }
                                                                 }"
                                                                 @click.outside="$wire.cerrarAjusteManual()">
                                                                <div class="text-[10px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                                    @if($ajusteManualTipo === 'monto')
                                                                        Nuevo precio ($)
                                                                    @else
                                                                        Descuento % (+ desc / - rec)
                                                                    @endif
                                                                </div>
                                                                <div class="flex gap-1">
                                                                    <input
                                                                        x-ref="ajusteInput"
                                                                        type="number"
                                                                        wire:model="ajusteManualValor"
                                                                        wire:keydown.enter="aplicarAjusteManual"
                                                                        wire:keydown.escape="cerrarAjusteManual"
                                                                        class="flex-1 w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                                        step="{{ $ajusteManualTipo === 'monto' ? '0.01' : '1' }}"
                                                                        placeholder="{{ $ajusteManualTipo === 'monto' ? 'Ej: 1500' : 'Ej: 10' }}">
                                                                    <button
                                                                        wire:click="aplicarAjusteManual"
                                                                        class="px-2 py-1 text-[10px] font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">
                                                                        OK
                                                                    </button>
                                                                </div>
                                                                <div class="text-[9px] text-gray-500 dark:text-gray-400 mt-1">
                                                                    @if($ajusteManualTipo === 'monto')
                                                                        Base: $@precio($item['precio_base'])
                                                                    @else
                                                                        Ej: 10 = -10% desc
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    {{-- Subtotal --}}
                                                    <td class="px-2 py-1.5 text-right text-xs font-medium text-gray-900 dark:text-white">$@precio($item['precio'] * $item['cantidad'])</td>
                                                    {{-- Promo --}}
                                                    <td class="px-2 py-1.5 text-center">
                                                        @php
                                                            $subtotalItem = $item['precio'] * $item['cantidad'];
                                                            $descuentoComun = $itemResultado['descuento_comun'] ?? 0;
                                                            $totalItem = $subtotalItem - $descuentoComun;
                                                        @endphp
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            @if($tienePromoEspecial)
                                                                <span class="inline-flex items-center px-1 py-0 rounded text-[10px] font-medium bg-green-100 text-green-800">{{ $itemResultado['unidades_consumidas'] }}/{{ $item['cantidad'] }}</span>
                                                            @endif
                                                            @if($tienePromoComun && $descuentoComun > 0)
                                                                <span class="inline-flex items-center px-1 py-0 rounded text-[10px] font-medium bg-blue-100 text-blue-800">-$@precio($descuentoComun)</span>
                                                            @endif
                                                            @if($excluido)
                                                                <span class="text-[10px] font-medium text-yellow-600">N/A</span>
                                                            @endif
                                                            @if(!$tienePromoEspecial && !$tienePromoComun && !$excluido)
                                                                <span class="text-gray-400 text-[10px]">-</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    {{-- Total --}}
                                                    <td class="px-2 py-1.5 text-right text-xs font-medium">
                                                        @if($tienePromoComun && $descuentoComun > 0)
                                                            <span class="text-green-600">$@precio($totalItem)</span>
                                                        @else
                                                            <span class="text-gray-900 dark:text-white">$@precio($subtotalItem)</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Promociones Aplicadas --}}
                        @if($resultado && (count($resultado['promociones_especiales_aplicadas']) > 0 || count($resultado['promociones_comunes_aplicadas']) > 0))
                            @php
                                $totalPromos = count($resultado['promociones_especiales_aplicadas']) + count($resultado['promociones_comunes_aplicadas']);
                            @endphp
                            <div class="border border-green-200 rounded-lg overflow-hidden bg-green-50">
                                <div class="bg-green-100 px-2 py-1 border-b border-green-200 flex justify-between items-center">
                                    <h4 class="text-xs font-medium text-green-800">Promociones ({{ $totalPromos }})</h4>
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
                        {{-- Cliente --}}
                        <div class="relative" x-data="{ clienteFocused: false }" @click.outside="clienteFocused = false">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">
                                Cliente
                            </label>
                            @if($clienteSeleccionado)
                                {{-- Cliente seleccionado --}}
                                <div class="flex items-center gap-2 px-2 py-1.5 bg-indigo-50 dark:bg-indigo-900 border border-indigo-300 dark:border-indigo-700 rounded-md">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm text-indigo-800 dark:text-indigo-200 truncate block">{{ $clienteNombre }}</span>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-indigo-600 dark:text-indigo-400">{{ $clienteCondicionIva }}</span>
                                            <span class="px-1.5 py-0.5 rounded text-white font-medium
                                                {{ $tipoFacturaCliente === 'A' ? 'bg-green-600' : ($tipoFacturaCliente === 'B' ? 'bg-blue-600' : 'bg-gray-500') }}">
                                                Fact. {{ $tipoFacturaCliente }}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        wire:click="limpiarCliente"
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                        title="Cambiar cliente">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @else
                                {{-- Input de búsqueda con botón de alta rápida --}}
                                <div class="flex gap-1">
                                    <input
                                        wire:model.live.debounce.300ms="busquedaCliente"
                                        wire:keydown.enter="seleccionarPrimerCliente"
                                        type="text"
                                        class="block w-full px-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 rounded-l-md"
                                        placeholder="Buscar cliente... (Consumidor Final)"
                                        @focus="clienteFocused = true">
                                    <button
                                        wire:click="abrirModalClienteRapido"
                                        type="button"
                                        class="flex-shrink-0 px-2 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md transition-colors"
                                        title="Alta rápida de cliente">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Dropdown de resultados --}}
                                @if(count($clientesResultados) > 0)
                                    <div
                                        x-show="clienteFocused"
                                        x-transition
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-48 rounded-md border border-gray-200 dark:border-gray-700 overflow-auto">
                                        <div class="py-1">
                                            @foreach($clientesResultados as $cliente)
                                                <button
                                                    wire:click="seleccionarCliente({{ $cliente['id'] }})"
                                                    type="button"
                                                    class="w-full px-3 py-2 text-left hover:bg-indigo-50 dark:hover:bg-gray-700 focus:bg-indigo-50 dark:focus:bg-gray-700 focus:outline-none">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente['nombre'] }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        @if($cliente['cuit'])
                                                            CUIT: {{ $cliente['cuit'] }}
                                                        @elseif($cliente['telefono'])
                                                            Tel: {{ $cliente['telefono'] }}
                                                        @endif
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif(strlen($busquedaCliente) >= 2 && count($clientesResultados) === 0)
                                    <div
                                        x-show="clienteFocused"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center">No se encontraron clientes</p>
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- Lista de Precios --}}
                        <div wire:key="lista-precio-select-{{ $sucursalId }}">
                            <label for="listaPrecioId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">
                                Lista de Precios
                            </label>
                            <select
                                wire:model.live="listaPrecioId"
                                id="listaPrecioId"
                                class="block w-full pl-2 pr-8 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                @foreach($listasPreciosDisponibles as $lista)
                                    <option value="{{ $lista['id'] }}" @selected($lista['id'] == $listaPrecioId)>
                                        {{ $lista['nombre'] }}
                                        @if($lista['es_lista_base'])
                                            (Base)
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
                                <label for="formaVentaId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">Forma de Venta</label>
                                <select wire:model.live="formaVentaId" id="formaVentaId" class="block w-full pl-2 pr-6 py-1.5 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                    <option value="">Seleccionar...</option>
                                    @foreach($formasVenta as $fv)
                                        <option value="{{ $fv['id'] }}">{{ $fv['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="formaPagoId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">Forma de Pago</label>
                                <select wire:model.live="formaPagoId" id="formaPagoId" class="block w-full pl-2 pr-6 py-1.5 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
                                    <option value="">Seleccionar...</option>
                                    @foreach($formasPago as $fp)
                                        <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Selector de Cuotas (solo si la forma de pago permite cuotas) --}}
                        @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                            <div class="relative">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">Cuotas</label>

                                {{-- Opción seleccionada (siempre visible) --}}
                                <div
                                    wire:click="toggleCuotasSelector"
                                    class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                >
                                    @if(!$cuotaSeleccionadaId)
                                        {{-- 1 pago seleccionado --}}
                                        <div class="flex items-center px-2 py-1.5">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">1 pago</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">sin financiación</div>
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
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">1 pago</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">sin financiación</div>
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
                                <h4 class="text-xs font-medium text-gray-900 dark:text-white">Resumen</h4>
                            </div>
                            <div class="px-3 py-2 space-y-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Subtotal:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">$@precio($resultado['subtotal'] ?? 0)</span>
                                </div>

                                @if($resultado && $resultado['total_descuentos'] > 0)
                                    <div class="flex justify-between items-center text-green-600">
                                        <span class="text-xs">Desc. promos:</span>
                                        <span class="text-sm font-medium">-$@precio($resultado['total_descuentos'])</span>
                                    </div>
                                @endif

                                <div class="flex justify-between items-center font-semibold border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
                                    <span class="text-xs text-gray-700 dark:text-gray-300">Total productos:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">$@precio($resultado['total_final'] ?? 0)</span>
                                </div>

                                {{-- Ajuste por forma de pago --}}
                                @if($ajusteFormaPagoInfo['porcentaje'] != 0 && !$ajusteFormaPagoInfo['es_mixta'])
                                    <div class="flex justify-between items-center {{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        <span class="text-xs">{{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? 'Recargo' : 'Descuento' }} {{ $ajusteFormaPagoInfo['nombre'] }} ({{ $ajusteFormaPagoInfo['porcentaje'] > 0 ? '+' : '' }}{{ $ajusteFormaPagoInfo['porcentaje'] }}%):</span>
                                        <span class="text-sm font-medium">{{ $ajusteFormaPagoInfo['monto'] > 0 ? '+' : '' }}$@precio($ajusteFormaPagoInfo['monto'])</span>
                                    </div>
                                @endif

                                {{-- Recargo por cuotas --}}
                                @if(!$ajusteFormaPagoInfo['es_mixta'] && ($ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0) > 0)
                                    <div class="flex justify-between items-center text-red-600">
                                        <span class="text-xs">Recargo {{ $ajusteFormaPagoInfo['cuotas'] }} cuotas (+{{ $ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] }}%):</span>
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
                                            <span class="text-xs {{ $ajusteMixto > 0 ? 'text-red-600' : ($ajusteMixto < 0 ? 'text-green-600' : 'text-gray-600') }}">Ajustes F.P.:</span>
                                            <button wire:click="editarDesglose" type="button" class="inline-flex items-center px-1 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-100 rounded hover:bg-purple-200" title="Editar desglose">
                                                <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Editar
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
                                        <span class="text-xs">Pago mixto:</span>
                                        <span class="text-sm font-medium">Desglosar al cobrar</span>
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
                                                Desglose IVA
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
                                                        <span>Neto:</span>
                                                        @if($tienePagoMixto && isset($alicuota['neto_mixto']))
                                                            <span>${{ number_format($alicuota['neto_mixto'], 3, ',', '.') }}</span>
                                                        @elseif($tieneAjusteFP && isset($alicuota['neto_con_ajuste_fp']))
                                                            <span>${{ number_format($alicuota['neto_con_ajuste_fp'], 3, ',', '.') }}</span>
                                                        @else
                                                            <span>${{ number_format($alicuota['neto'], 3, ',', '.') }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                                        <span>IVA ({{ $alicuota['porcentaje'] }}%):</span>
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
                                                    <span>Total Neto:</span>
                                                    @if($tienePagoMixto)
                                                        <span>${{ number_format($desglose['total_neto_mixto'], 3, ',', '.') }}</span>
                                                    @elseif($tieneAjusteFP)
                                                        <span>${{ number_format($desglose['total_neto_con_ajuste_fp'], 3, ',', '.') }}</span>
                                                    @else
                                                        <span>${{ number_format($desglose['total_neto'], 3, ',', '.') }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex justify-between font-medium text-gray-700 dark:text-gray-300">
                                                    <span>Total IVA:</span>
                                                    @if($tienePagoMixto)
                                                        <span>${{ number_format($desglose['total_iva_mixto'], 3, ',', '.') }}</span>
                                                    @elseif($tieneAjusteFP)
                                                        <span>${{ number_format($desglose['total_iva_con_ajuste_fp'], 3, ',', '.') }}</span>
                                                    @else
                                                        <span>${{ number_format($desglose['total_iva'], 3, ',', '.') }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex justify-between font-semibold text-gray-900 dark:text-white">
                                                    <span>Total:</span>
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
                                                        Desc. promos: -${{ number_format($descuentoAplicado, 3, ',', '.') }}
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
                                            Emitir factura fiscal
                                        </span>
                                    </label>
                                    @if($emitirFacturaFiscal)
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            @endif

                            <button
                                wire:click="iniciarCobro"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                                @if(empty($items)) disabled @endif
                                class="w-full inline-flex justify-center items-center px-3 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg wire:loading.remove class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <svg wire:loading class="animate-spin h-4 w-4 text-white mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove>
                                    Cobrar
                                    @if($totalACobrar > 0)
                                        <span class="ml-1">${{ number_format($totalACobrar, 2, ',', '.') }}</span>
                                    @endif
                                </span>
                                <span wire:loading>...</span>
                            </button>
                            <button
                                wire:click="limpiarCarrito"
                                @if(empty($items)) disabled @endif
                                class="w-full inline-flex justify-center items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Consulta de Precios --}}
    @if($mostrarModalConsulta && $articuloConsulta)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-title"
             role="dialog"
             aria-modal="true"
             x-data
             @keydown.window.escape="$wire.cerrarModalConsulta()"
             @keydown.window.enter="$wire.agregarArticuloYCerrarConsulta({{ $articuloConsulta['id'] }})"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalConsulta"></div>

                {{-- Centrado --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    {{-- Header --}}
                    <div class="bg-amber-500 px-4 py-3 flex items-center gap-3">
                        <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-white" id="modal-title">
                            Consulta de Precios
                        </h3>
                    </div>

                    {{-- Contenido --}}
                    <div class="px-4 py-4">
                        {{-- Info del artículo --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articuloConsulta['nombre'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Código: {{ $articuloConsulta['codigo'] }}
                                @if($articuloConsulta['categoria'])
                                    | {{ $articuloConsulta['categoria'] }}
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Precio base: <span class="font-medium">$@precio($articuloConsulta['precio_base'])</span>
                            </div>
                        </div>

                        {{-- Tabla de precios por lista --}}
                        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lista</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($articuloConsulta['precios'] as $precio)
                                        <tr class="{{ $precio['es_lista_base'] ? 'bg-green-50 dark:bg-green-900/30' : '' }} {{ $precio['lista_id'] == $listaPrecioId ? 'ring-2 ring-inset ring-indigo-500' : '' }}">
                                            <td class="px-3 py-2 text-sm">
                                                <div class="font-medium text-gray-900 dark:text-white">
                                                    {{ $precio['lista_nombre'] }}
                                                    @if($precio['es_lista_base'])
                                                        <span class="ml-1 text-xs text-green-600">(Base)</span>
                                                    @endif
                                                    @if($precio['lista_id'] == $listaPrecioId)
                                                        <span class="ml-1 text-xs text-indigo-600 dark:text-indigo-400">(Actual)</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    @if($precio['tiene_precio_especifico'])
                                                        Precio específico
                                                    @elseif($precio['ajuste_porcentaje'] != 0)
                                                        {{ $precio['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $precio['ajuste_porcentaje'] }}% sobre base
                                                    @else
                                                        Sin ajuste
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
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex flex-row-reverse gap-2">
                        <button
                            wire:click="cerrarModalConsulta"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Cerrar
                        </button>
                        <button
                            wire:click="agregarArticuloYCerrarConsulta({{ $articuloConsulta['id'] }})"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Script para scroll y efectos --}}
    {{-- Modal de Agregar Concepto --}}
    @if($mostrarModalConcepto)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-concepto"
             role="dialog"
             aria-modal="true"
             x-data="{ init() { this.$nextTick(() => this.$refs.inputImporte.focus()) } }"
             @keydown.window.escape="$wire.cerrarModalConcepto()"
             @keydown.window.enter="$wire.agregarConcepto()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalConcepto"></div>

                {{-- Centrado --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    {{-- Header --}}
                    <div class="bg-emerald-500 px-4 py-3 flex items-center gap-3">
                        <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-emerald-100">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-white">
                            Agregar Concepto
                        </h3>
                    </div>

                    {{-- Contenido --}}
                    <div class="px-4 py-4 space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Agregue un concepto por importe sin especificar artículo (ej: venta de fiambrería por $5.000)
                        </p>

                        {{-- Importe (primero) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Importe</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">$</span>
                                <input
                                    x-ref="inputImporte"
                                    wire:model="conceptoImporte"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="block w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                    placeholder="0.00">
                            </div>
                        </div>

                        {{-- Categoría (opcional) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría (opcional)</label>
                            <select
                                wire:model="conceptoCategoriaId"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                <option value="">-- Sin categoría (Varios) --</option>
                                @foreach($categoriasDisponibles as $cat)
                                    <option value="{{ $cat['id'] }}">{{ $cat['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Descripción (opcional) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción (opcional)</label>
                            <input
                                wire:model="conceptoDescripcion"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                placeholder="Ej: Fiambrería variada">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si está vacío, se usará el nombre de la categoría o "Varios"</p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex flex-row-reverse gap-2">
                        <button
                            wire:click="agregarConcepto"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm">
                            Agregar
                        </button>
                        <button
                            wire:click="cerrarModalConcepto"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Pago / Desglose --}}
    @if($mostrarModalPago)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-pago"
            role="dialog"
            aria-modal="true"
            x-data="{
                focusIndex: -1,
                pagosCount: {{ count($desglosePagos) }},
                init() {
                    this.$nextTick(() => {
                        // Enfocar el primer elemento interactivo
                        const firstInput = this.$el.querySelector('select, input, button[type=button]:not([disabled])');
                        if (firstInput) firstInput.focus();
                    });
                }
            }"
            @keydown.escape.window="$wire.cerrarModalPago()"
            @keydown.enter.window.prevent="
                const activeEl = document.activeElement;
                // Si el foco está en el botón Agregar, dejar que su handler maneje el evento
                if (activeEl && activeEl.hasAttribute('x-ref') && activeEl.getAttribute('x-ref') === 'btnAgregar') {
                    return;
                }
                // Si el desglose está completo, confirmar pago
                if ({{ $this->desgloseCompleto() ? 'true' : 'false' }}) $wire.confirmarPago();
            "
            @keydown.tab.prevent="
                const focusables = [...$el.querySelectorAll('select, input, button:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
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
                                {{ count($desglosePagos) > 1 || $montoPendienteDesglose > 0 ? 'Desglose de Pagos' : 'Confirmar Pago' }}
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
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total a cobrar:</span>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                    $@precio($resultado['total_final'] ?? 0)
                                </div>
                            </div>
                            @if($montoPendienteDesglose > 0.01)
                                <div class="text-right">
                                    <span class="text-sm text-orange-600">Pendiente:</span>
                                    <div class="text-xl font-bold text-orange-600">
                                        $@precio($montoPendienteDesglose)
                                    </div>
                                </div>
                            @else
                                <div class="text-right">
                                    <span class="text-sm text-green-600">Total con ajustes:</span>
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
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Formas de Pago</h4>
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
                                                            title="{{ ($pago['factura_fiscal'] ?? false) ? 'Factura fiscal activada' : 'Clic para activar factura fiscal' }}"
                                                        >
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                            </svg>
                                                            Fiscal
                                                            @if($pago['factura_fiscal'] ?? false)
                                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                        </button>
                                                    </div>

                                                    {{-- Detalles del monto --}}
                                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        <span>Base: $@precio($pago['monto_base'])</span>
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
                                                            <label class="text-xs text-gray-600 dark:text-gray-400">Cuotas:</label>
                                                            <select
                                                                wire:change="actualizarCuotasDesglose({{ $index }}, $event.target.value)"
                                                                class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                                                @foreach($pago['cuotas_disponibles'] as $cuota)
                                                                    <option value="{{ $cuota['cantidad'] }}" @selected($pago['cuotas'] == $cuota['cantidad'])>
                                                                        {{ $cuota['cantidad'] }} cuota{{ $cuota['cantidad'] > 1 ? 's' : '' }}
                                                                        @if($cuota['recargo'] > 0)
                                                                            (+{{ $cuota['recargo'] }}%)
                                                                        @else
                                                                            (sin interés)
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
                                                        <div class="mt-2 flex items-center gap-2">
                                                            <label class="text-xs text-gray-600 dark:text-gray-400">Recibido:</label>
                                                            <div class="relative">
                                                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    value="{{ $pago['monto_recibido'] }}"
                                                                    wire:change="actualizarMontoRecibido({{ $index }}, $event.target.value)"
                                                                    class="w-28 pl-6 pr-2 py-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                                            </div>
                                                            @if($pago['vuelto'] > 0)
                                                                <span class="text-sm font-medium text-blue-600">
                                                                    Vuelto: $@precio($pago['vuelto'])
                                                                </span>
                                                            @endif
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
                                                        title="Eliminar forma de pago">
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
                            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-gray-50 dark:bg-gray-700">
                                {{-- Fila única: Select + Monto + Agregar --}}
                                <div class="flex items-center gap-2">
                                    {{-- Forma de pago --}}
                                    <select
                                        wire:model.live="nuevoPago.forma_pago_id"
                                        class="flex-1 min-w-0 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 py-1.5">
                                        <option value="">Forma de pago...</option>
                                        @foreach($formasPagoSucursal as $fp)
                                            @if(!$fp['es_mixta'])
                                                <option value="{{ $fp['id'] }}">
                                                    {{ $fp['nombre'] }}
                                                    @if($fp['ajuste_porcentaje'] != 0)
                                                        ({{ $fp['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $fp['ajuste_porcentaje'] }}%)
                                                    @endif
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>

                                    {{-- Monto (vacío = todo el pendiente) --}}
                                    <div class="relative w-28">
                                        <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="nuevoPago.monto"
                                            class="w-full pl-6 pr-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                            placeholder="{{ number_format($montoPendienteDesglose, 2, ',', '.') }}"
                                            title="Vacío = monto pendiente completo">
                                    </div>

                                    {{-- Botón Agregar --}}
                                    <button
                                        wire:click="agregarAlDesglose"
                                        type="button"
                                        x-ref="btnAgregar"
                                        @keydown.enter.prevent="$wire.agregarAlDesglose()"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 whitespace-nowrap">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Agregar
                                    </button>
                                </div>

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
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">1 pago</span>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">sin financiación</span>
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
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">1 pago</span>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">sin financiación</span>
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
                            </div>
                        @endif

                        {{-- Total vuelto general --}}
                        @php
                            $vueltoTotal = collect($desglosePagos)->sum('vuelto');
                            $montoFiscalDesglose = collect($desglosePagos)->where('factura_fiscal', true)->sum('monto_final');
                            $cantidadFiscales = collect($desglosePagos)->where('factura_fiscal', true)->count();
                        @endphp
                        @if($vueltoTotal > 0)
                            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-800 dark:text-blue-300 font-medium">Vuelto total a entregar:</span>
                                    <span class="text-xl font-bold text-blue-600">$@precio($vueltoTotal)</span>
                                </div>
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
                                            <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">Facturación Fiscal</span>
                                            @if($cantidadFiscales > 0)
                                                <span class="text-xs text-indigo-500 dark:text-indigo-400 ml-1">({{ $cantidadFiscales }} FP)</span>
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">(sin factura)</span>
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
                            <span wire:loading.remove>Confirmar</span>
                            <span wire:loading>Procesando...</span>
                        </button>
                        <button
                            wire:click="cerrarModalPago"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-3 py-2 bg-white dark:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Volver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Alta Rápida de Cliente --}}
    @if($mostrarModalClienteRapido)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-cliente-rapido"
             role="dialog"
             aria-modal="true"
             x-data="{ init() { this.$nextTick(() => this.$refs.inputNombre.focus()) } }"
             @keydown.window.escape="$wire.cerrarModalClienteRapido()"
             @keydown.window.enter="$wire.guardarClienteRapido()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalClienteRapido"></div>

                {{-- Centrado --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    {{-- Header --}}
                    <div class="bg-indigo-500 px-4 py-3 flex items-center gap-3">
                        <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-white">
                            Alta Rápida de Cliente
                        </h3>
                    </div>

                    {{-- Contenido --}}
                    <div class="px-4 py-4 space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Complete los datos básicos para crear un nuevo cliente rápidamente.
                        </p>

                        {{-- Nombre --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre <span class="text-red-500">*</span></label>
                            <input
                                x-ref="inputNombre"
                                wire:model="clienteRapidoNombre"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                placeholder="Nombre del cliente">
                            @error('clienteRapidoNombre')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Teléfono --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                            <input
                                wire:model="clienteRapidoTelefono"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                placeholder="Teléfono (opcional)">
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            El cliente se creará como Consumidor Final. Puede completar los demás datos después desde la gestión de clientes.
                        </p>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex flex-row-reverse gap-2">
                        <button
                            wire:click="guardarClienteRapido"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Crear Cliente
                        </button>
                        <button
                            wire:click="cerrarModalClienteRapido"
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
        });
    </script>
</div>
