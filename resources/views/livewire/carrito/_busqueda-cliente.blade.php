{{-- Sección Cliente: input búsqueda + dropdown + card cliente seleccionado (reutilizable) --}}
<div class="relative" x-data="{ clienteFocused: false, hlIdx: -1 }" @click.outside="clienteFocused = false"
     x-on:focus-cliente.window="$nextTick(() => { if ($refs.inputCliente) $refs.inputCliente.focus(); })">
    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-0.5">
        {{ __('Cliente') }}
    </label>
    @if($clienteSeleccionado)
        {{-- Cliente seleccionado --}}
        <div class="flex items-center gap-2 px-2 py-1.5 bg-indigo-50 dark:bg-indigo-900 border border-indigo-300 dark:border-indigo-700 rounded-md">
            <div class="flex-1 min-w-0">
                <span class="text-sm text-indigo-800 dark:text-indigo-200 truncate block">{{ $clienteNombre }}</span>
                <div class="flex items-center gap-2 text-xs flex-wrap">
                    <span class="text-indigo-600 dark:text-indigo-400">{{ $clienteCondicionIva }}</span>
                    <span class="px-1.5 py-0.5 rounded text-white font-medium
                        {{ $tipoFacturaCliente === 'A' ? 'bg-green-600' : ($tipoFacturaCliente === 'B' ? 'bg-blue-600' : 'bg-gray-500') }}">
                        {{ __('Fact.') }} {{ $tipoFacturaCliente }}
                    </span>
                    @if($puntosDisponibles)
                        <span class="px-1.5 py-0.5 rounded bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 font-medium">
                            <svg class="w-3 h-3 inline -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            {{ number_format($puntosSaldoCliente) }} pts
                        </span>
                    @endif
                </div>
            </div>
            <button
                wire:click="limpiarCliente"
                type="button"
                class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                title="{{ __('Cambiar cliente') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @else
        {{-- Input de búsqueda con botón de alta rápida --}}
        <div class="flex gap-1">
            <input
                x-ref="inputCliente"
                wire:model.live.debounce.300ms="busquedaCliente"
                @keydown.arrow-down.prevent="if (clienteFocused) { hlIdx = Math.min(hlIdx + 1, ($refs.clienteDropdown ? $refs.clienteDropdown.querySelectorAll('button').length - 1 : 0)); $nextTick(() => { const dd = $refs.clienteDropdown; if (dd) { const items = dd.querySelectorAll('button'); if (items[hlIdx]) items[hlIdx].scrollIntoView({ block: 'nearest' }); } }); }"
                @keydown.arrow-up.prevent="if (clienteFocused) { hlIdx = Math.max(hlIdx - 1, 0); $nextTick(() => { const dd = $refs.clienteDropdown; if (dd) { const items = dd.querySelectorAll('button'); if (items[hlIdx]) items[hlIdx].scrollIntoView({ block: 'nearest' }); } }); }"
                @keydown.enter.prevent="if (hlIdx >= 0 && $refs.clienteDropdown) { const items = $refs.clienteDropdown.querySelectorAll('button'); if (items[hlIdx]) items[hlIdx].click(); } else { $wire.seleccionarPrimerCliente(); }"
                @keydown.escape="clienteFocused = false"
                type="text"
                class="block w-full px-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 rounded-l-md"
                placeholder="{{ __('Buscar cliente... (Consumidor Final)') }}"
                @focus="clienteFocused = true; hlIdx = -1">
            <button
                wire:click="abrirModalClienteRapido"
                type="button"
                class="flex-shrink-0 px-2 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md transition-colors"
                title="{{ __('Alta rápida de cliente') }}">
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
                x-ref="clienteDropdown"
                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-48 rounded-md border border-gray-200 dark:border-gray-700 overflow-auto">
                @foreach($clientesResultados as $idx => $cliente)
                    <button
                        wire:click="seleccionarCliente({{ $cliente['id'] }})"
                        @mouseenter="hlIdx = {{ $idx }}"
                        type="button"
                        :class="hlIdx === {{ $idx }} ? 'bg-bcn-primary/10 dark:bg-bcn-primary/20' : 'hover:bg-indigo-50 dark:hover:bg-gray-700'"
                        class="w-full px-3 py-2 text-left focus:outline-none">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente['nombre'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if($cliente['cuit'])
                                {{ __('CUIT') }}: {{ $cliente['cuit'] }}
                            @elseif($cliente['telefono'])
                                {{ __('Tel') }}: {{ $cliente['telefono'] }}
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @elseif(strlen($busquedaCliente) >= 2 && count($clientesResultados) === 0)
            <div
                x-show="clienteFocused"
                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-md border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center">{{ __('No se encontraron clientes') }}</p>
            </div>
        @endif
    @endif
</div>
