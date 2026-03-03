<div class="h-[calc(100vh-5.5rem)] flex flex-col overflow-hidden">
    {{-- Header --}}
    <div class="px-4 sm:px-6 lg:px-8 pt-4 flex-shrink-0">
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Producir Lote') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Arma un lote de producción con múltiples artículos y controla los ingredientes consolidados.') }}</p>
                </div>
                <a href="{{ route('stock.produccion') }}" wire:navigate class="hidden sm:inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    {{ __('Volver') }}
                </a>
                <a href="{{ route('stock.produccion') }}" wire:navigate class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 transition" title="{{ __('Volver') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Contenido principal: 2 columnas --}}
    <div class="flex-1 min-h-0 px-4 sm:px-6 lg:px-8 pb-3">
        <div class="h-full grid grid-cols-1 lg:grid-cols-5 gap-4">

            {{-- ==================== COLUMNA IZQUIERDA (2/5) ==================== --}}
            <div class="lg:col-span-2 flex flex-col min-h-0 gap-3">

                {{-- Buscador con dropdown (patrón NuevaVenta) --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3 flex-shrink-0"
                     x-data="{
                        inputFocused: false,
                        selectedIndex: 0,
                        get resultCount() {
                            return this.$refs.resultsList ? this.$refs.resultsList.querySelectorAll('[data-result-item]').length : 0;
                        },
                        moveUp() {
                            if (this.selectedIndex > 0) this.selectedIndex--;
                            this.scrollToSelected();
                        },
                        moveDown() {
                            if (this.selectedIndex < this.resultCount - 1) this.selectedIndex++;
                            this.scrollToSelected();
                        },
                        scrollToSelected() {
                            this.$nextTick(() => {
                                const items = this.$refs.resultsList?.querySelectorAll('[data-result-item]');
                                if (items && items[this.selectedIndex]) {
                                    items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
                                }
                            });
                        },
                        selectCurrent() {
                            const items = this.$refs.resultsList?.querySelectorAll('[data-result-item]');
                            if (items && items[this.selectedIndex]) {
                                items[this.selectedIndex].click();
                            }
                        }
                     }"
                     @click.outside="inputFocused = false">
                    <div class="relative">
                        <input
                            x-ref="inputBusqueda"
                            wire:model.live="busquedaArticulo"
                            @keydown.enter.prevent="selectCurrent()"
                            @keydown.arrow-up.prevent="moveUp()"
                            @keydown.arrow-down.prevent="moveDown()"
                            @keydown.escape="inputFocused = false"
                            @focus="inputFocused = true"
                            @input="selectedIndex = 0"
                            type="text"
                            autocomplete="off"
                            class="block w-full pl-9 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-bcn-primary focus:border-bcn-primary text-sm"
                            placeholder="{{ __('Buscar artículo con receta (mín. 3 caracteres)...') }}"
                        >
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center gap-1">
                            <div wire:loading wire:target="busquedaArticulo">
                                <svg class="animate-spin h-4 w-4 text-bcn-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </div>
                            @if(strlen($busquedaArticulo) > 0)
                                <button wire:click="$set('busquedaArticulo', '')" type="button" class="text-gray-400 hover:text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            @endif
                        </div>

                        {{-- Dropdown resultados --}}
                        @if(count($articulosResultados) > 0)
                            <div
                                x-show="inputFocused"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md border border-gray-200 dark:border-gray-700 overflow-auto"
                                x-ref="resultsList">
                                <div class="py-1">
                                    @foreach($articulosResultados as $idx => $articulo)
                                        <button
                                            type="button"
                                            data-result-item
                                            wire:click="seleccionarArticulo({{ $articulo['id'] }})"
                                            @mouseenter="selectedIndex = {{ $idx }}"
                                            :class="selectedIndex === {{ $idx }} ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''"
                                            class="w-full text-left px-3 py-2 hover:bg-indigo-50 dark:hover:bg-gray-700 focus:outline-none border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $articulo['nombre'] }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ __('Código') }}: {{ $articulo['codigo'] }}
                                                        @if($articulo['codigo_barras'])
                                                            | {{ __('Barras') }}: {{ $articulo['codigo_barras'] }}
                                                        @endif
                                                        @if($articulo['categoria_nombre'])
                                                            <span class="ml-2 text-indigo-600 dark:text-indigo-400">{{ $articulo['categoria_nombre'] }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Sin resultados --}}
                        @if(strlen($busquedaArticulo) >= 3 && count($articulosResultados) === 0)
                            <div
                                x-show="inputFocused"
                                wire:loading.remove
                                wire:target="busquedaArticulo"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <p class="text-center text-gray-500 dark:text-gray-400 text-sm">{{ __('No se encontraron artículos con receta') }}</p>
                            </div>
                        @endif
                    </div>
                    @if(strlen($busquedaArticulo) > 0 && strlen($busquedaArticulo) < 3)
                        <p class="mt-1 text-xs text-gray-500">{{ __('Escribe al menos 3 caracteres para buscar...') }}</p>
                    @endif
                </div>

                {{-- Preview de receta (scrollable) --}}
                @if($selectedArticuloId)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg flex flex-col min-h-0 flex-1">
                        <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <button wire:click="$set('selectedArticuloId', null)" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex-shrink-0" title="{{ __('Quitar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $selectedArticuloNombre }}</h3>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Producir') }}</span>
                                    <input
                                        type="number"
                                        wire:model.live.debounce.300ms="cantidadAProducir"
                                        min="0.001"
                                        step="0.001"
                                        class="w-20 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white py-1 text-right"
                                    >
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $selectedArticuloUnidad }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Tabla ingredientes preview (scrollable) --}}
                        <div class="overflow-auto flex-1 min-h-0">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ingrediente') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('x Und') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Total') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cant. usada') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($previewIngredientes as $i => $ing)
                                        <tr>
                                            <td class="px-3 py-1.5 text-gray-900 dark:text-white">
                                                {{ $ing['nombre'] }}
                                                <span class="text-xs text-gray-400 ml-1">{{ $ing['unidad_medida'] }}</span>
                                            </td>
                                            <td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400 text-xs">{{ number_format($ing['cantidad_por_unidad'], 3) }}</td>
                                            <td class="px-3 py-1.5 text-right font-medium text-gray-900 dark:text-white">{{ number_format($ing['cantidad_receta'], 3) }}</td>
                                            <td class="px-3 py-1.5 text-right">
                                                <input
                                                    type="number"
                                                    wire:model.live.debounce.300ms="previewIngredientes.{{ $i }}.cantidad_real"
                                                    min="0"
                                                    step="0.001"
                                                    class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white py-1"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Botón agregar --}}
                        <div class="p-3 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <button
                                wire:click="agregarAlLote"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-md transition"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                {{ __('Agregar al lote') }}
                            </button>
                        </div>
                    </div>
                @else
                    {{-- Placeholder cuando no hay artículo seleccionado --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg flex-1 flex items-center justify-center">
                        <div class="text-center text-gray-400 dark:text-gray-500 p-4">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                            <p class="text-sm">{{ __('Selecciona un artículo para ver su receta') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ==================== COLUMNA DERECHA (3/5) ==================== --}}
            <div class="lg:col-span-3 flex flex-col min-h-0 gap-3">

                {{-- Grilla: Artículos a Producir (scrollable) --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg flex flex-col min-h-0 {{ count($loteIngredientesConsolidados) > 0 ? '' : 'flex-1' }}" style="{{ count($loteIngredientesConsolidados) > 0 ? 'max-height: 40%' : '' }}">
                    <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 flex-shrink-0 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ __('Artículos a Producir') }}
                            @if(count($loteArticulos) > 0)
                                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300">
                                    {{ count($loteArticulos) }}
                                </span>
                            @endif
                        </h3>
                    </div>

                    @if(count($loteArticulos) > 0)
                        <div class="overflow-auto flex-1 min-h-0">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Artículo') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cantidad') }}</th>
                                        <th class="px-3 py-1.5 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($loteArticulos as $index => $item)
                                        <tr>
                                            <td class="px-3 py-1.5 text-gray-900 dark:text-white">{{ $item['nombre'] }}</td>
                                            <td class="px-3 py-1.5 text-right font-medium text-gray-900 dark:text-white">{{ $item['cantidad'] }}</td>
                                            <td class="px-3 py-1.5 text-center">
                                                <button wire:click="quitarDelLote({{ $index }})" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition" title="{{ __('Quitar') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center text-gray-400 dark:text-gray-500 p-4">
                                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                <p class="text-sm">{{ __('Busca y agrega artículos al lote') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Grilla: Resumen de Ingredientes Consolidados (scrollable) --}}
                @if(count($loteIngredientesConsolidados) > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg flex flex-col min-h-0 flex-1">
                        <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Resumen de Ingredientes') }}</h3>
                                @if($hayStockInsuficiente && $modoControlStock === 'bloquea')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
                                        {{ __('Stock insuficiente') }}
                                    </span>
                                @elseif($hayStockInsuficiente && $modoControlStock === 'advierte')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
                                        {{ __('Advertencia') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="overflow-auto flex-1 min-h-0">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ingrediente') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Según receta') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cant. real') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Stock') }}</th>
                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Resultante') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($loteIngredientesConsolidados as $i => $ing)
                                        @php $insuficiente = $ing['stock_resultante'] < 0; @endphp
                                        <tr class="{{ $insuficiente ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                            <td class="px-3 py-1.5 text-gray-900 dark:text-white">
                                                {{ $ing['nombre'] }}
                                                <span class="text-xs text-gray-400 ml-1">{{ $ing['unidad_medida'] }}</span>
                                            </td>
                                            <td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">{{ number_format($ing['total_necesario'], 3) }}</td>
                                            <td class="px-3 py-1.5 text-right">
                                                <input
                                                    type="number"
                                                    wire:model.live.debounce.300ms="loteIngredientesConsolidados.{{ $i }}.cantidad_real_editada"
                                                    min="0"
                                                    step="0.001"
                                                    class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white py-1"
                                                >
                                            </td>
                                            <td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">{{ number_format($ing['stock_actual'], 3) }}</td>
                                            <td class="px-3 py-1.5 text-right font-semibold {{ $insuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                {{ number_format($ing['stock_resultante'], 3) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ==================== FOOTER FIJO ==================== --}}
    <div class="flex-shrink-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-4 sm:px-6 lg:px-8 py-2.5">
        <div class="flex items-center gap-3">
            {{-- Observaciones (expandible) --}}
            <div class="flex-1">
                <input
                    wire:model="observaciones"
                    type="text"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white py-2"
                    placeholder="{{ __('Observaciones (opcional)...') }}"
                >
            </div>

            {{-- Botones --}}
            <button
                wire:click="cancelar"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
                {{ __('Cancelar') }}
            </button>
            <button
                wire:click="confirmarLote"
                wire:loading.attr="disabled"
                @if(empty($loteArticulos) || ($hayStockInsuficiente && $modoControlStock === 'bloquea')) disabled @endif
                class="flex-shrink-0 px-6 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="confirmarLote">
                    {{ __('Confirmar Lote') }}
                </span>
                <span wire:loading wire:target="confirmarLote" class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ __('Procesando...') }}
                </span>
            </button>
        </div>
    </div>
</div>
