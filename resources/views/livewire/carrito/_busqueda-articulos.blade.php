{{-- Bloque de Búsqueda de Artículos (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
<div class="relative"
     x-data="{
        inputFocused: false,
        selectedIndex: 0,
        lastKeyTime: 0,
        scannerDetected: false,
        scanKeyCount: 0,
        searchTimeout: null,
        scanQueue: [],
        processingScan: false,
        init() {
            this.$nextTick(() => this.$refs.inputBusqueda.focus());
            this.$watch('inputFocused', (v) => { if (!v) this.selectedIndex = 0; });
        },
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
            } else {
                $wire.agregarPrimerArticulo();
            }
        },
        handleKeydown(e) {
            const now = Date.now();
            const elapsed = now - this.lastKeyTime;
            this.lastKeyTime = now;
            if (e.key.length === 1 && elapsed < 50) {
                this.scanKeyCount++;
                if (this.scanKeyCount >= 3) {
                    this.scannerDetected = true;
                }
            } else if (e.key.length === 1) {
                this.scanKeyCount = 1;
            }
        },
        handleInput() {
            this.selectedIndex = 0;
            clearTimeout(this.searchTimeout);
            if (this.scannerDetected) return;
            this.searchTimeout = setTimeout(() => {
                $wire.$refresh();
            }, 350);
        },
        handleEnter() {
            clearTimeout(this.searchTimeout);
            if (this.scannerDetected) {
                this.scannerDetected = false;
                this.scanKeyCount = 0;
                const codigo = this.$refs.inputBusqueda.value.trim();
                this.$refs.inputBusqueda.value = '';
                if (codigo) {
                    this.scanQueue.push(codigo);
                    this.processScanQueue();
                }
                return;
            }
            this.selectCurrent();
        },
        async processScanQueue() {
            if (this.processingScan) return;
            this.processingScan = true;
            while (this.scanQueue.length > 0) {
                const codigo = this.scanQueue.shift();
                await $wire.agregarPorCodigo(codigo);
            }
            this.processingScan = false;
            this.$refs.inputBusqueda.focus();
        }
     }"
     @click.outside="inputFocused = false"
     x-on:focus-busqueda.window="$refs.inputBusqueda.focus()">
    <div class="flex gap-2">
        {{-- Input de búsqueda con botones integrados --}}
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar Artículo') }}</label>
            <div class="flex">
                <div class="relative flex-1">
                    <input
                        x-ref="inputBusqueda"
                        wire:model="busquedaArticulo"
                        wire:keydown.escape="desactivarModos"
                        @keydown.enter.prevent="handleEnter()"
                        @keydown.arrow-up.prevent="moveUp()"
                        @keydown.arrow-down.prevent="moveDown()"
                        @keydown="handleKeydown($event); if($event.key === '*') { $event.preventDefault(); $dispatch('focus-cantidad'); }"
                        @focus="inputFocused = true"
                        @input="handleInput()"
                        type="text"
                        autocomplete="off"
                        class="block w-full pl-10 pr-3 py-2 border rounded-l-md leading-5 bg-white dark:bg-gray-700 dark:text-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:border-indigo-500 text-sm
                            {{ $modoConsulta ? 'border-amber-500 ring-2 ring-amber-200 focus:ring-amber-500' : ($modoBusqueda ? 'border-blue-500 ring-2 ring-blue-200 focus:ring-blue-500' : 'border-gray-300 dark:border-gray-600 focus:ring-indigo-500') }}"
                        placeholder="{{ $modoConsulta ? __('Buscar artículo para CONSULTAR PRECIOS...') : ($modoBusqueda ? __('Buscar artículo en el DETALLE...') : __('Buscar por nombre, código o código de barras...')) }}">
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
                        title="{{ __('Cancelar modo (Esc)') }}">
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
                {{-- Botón buscar artículos --}}
                <button
                    wire:click="abrirModalBusquedaArticulos"
                    type="button"
                    class="flex-shrink-0 px-2 py-2 bg-gray-500 hover:bg-gray-600 text-white transition-colors border border-gray-500"
                    title="{{ __('Buscar artículos') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
                {{-- Botón alta rápida de artículo --}}
                <button
                    wire:click="abrirModalArticuloRapido"
                    type="button"
                    class="flex-shrink-0 px-2 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md transition-colors border border-indigo-600"
                    title="{{ __('Alta rápida de artículo') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Input de cantidad --}}
        <div x-data
             x-on:focus-cantidad.window="$refs.inputCantidad.focus(); $refs.inputCantidad.select()">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad') }}</label>
            <div class="flex items-center">
                <button
                    type="button"
                    @click="if($wire.cantidadAgregar > 1) $wire.cantidadAgregar--"
                    class="px-1.5 py-2 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-md bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-500 text-sm font-bold leading-5">
                    &minus;
                </button>
                <input
                    x-ref="inputCantidad"
                    wire:model.number="cantidadAgregar"
                    @keydown.enter.prevent="$dispatch('focus-busqueda')"
                    @keydown="if($event.key === '*') { $event.preventDefault(); $dispatch('focus-busqueda'); }"
                    @focus="$el.select()"
                    type="number"
                    min="1"
                    class="block w-12 px-1 py-2 border-y border-gray-300 dark:border-gray-600 leading-5 bg-white dark:bg-gray-700 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    placeholder="1">
                <button
                    type="button"
                    @click="$wire.cantidadAgregar++"
                    class="px-1.5 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-500 text-sm font-bold leading-5">
                    +
                </button>
            </div>
        </div>

        {{-- Botones de acción --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Acciones') }}</label>
            <div class="flex items-center gap-1">
                {{-- Botón Consulta de Precios --}}
                <button
                    wire:click="activarModoConsulta"
                    type="button"
                    class="p-2 rounded-md transition-colors border
                        {{ $modoConsulta ? 'bg-amber-500 text-white border-amber-600' : 'bg-amber-50 text-amber-700 border-amber-300 hover:bg-amber-100' }}"
                    title="{{ __('Consultar precios (Ctrl+3)') }}">
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
                    title="{{ __('Buscar en detalle (Ctrl+4)') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </button>

                {{-- Botón Agregar Concepto --}}
                <button
                    wire:click="abrirModalConcepto"
                    type="button"
                    class="p-2 rounded-md transition-colors border bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100"
                    title="{{ __('Agregar concepto (Ctrl+5)') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

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
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-80 rounded-md border border-gray-200 dark:border-gray-700 overflow-auto"
            x-ref="resultsList">
            <div class="py-1">
                @foreach($articulosResultados as $idx => $articulo)
                    <button
                        type="button"
                        data-result-item
                        wire:click="seleccionarArticulo({{ $articulo['id'] }})"
                        @mouseenter="selectedIndex = {{ $idx }}"
                        :class="selectedIndex === {{ $idx }} ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''"
                        class="w-full text-left px-4 py-3 hover:bg-indigo-50 dark:hover:bg-gray-700 focus:outline-none border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $articulo['nombre'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Código') }}: {{ $articulo['codigo'] }}
                                    @if($articulo['codigo_barras'])
                                        | {{ __('Barras') }}: {{ $articulo['codigo_barras'] }}
                                    @endif
                                    @if($articulo['categoria_nombre'])
                                        <span class="ml-2 text-indigo-600">{{ $articulo['categoria_nombre'] }}</span>
                                    @endif
                                </p>
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
                {{ __('No se encontraron artículos') }}
            </div>
        </div>
    @endif
</div>
