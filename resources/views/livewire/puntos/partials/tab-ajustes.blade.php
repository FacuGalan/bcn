<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
        {{ __('Ajuste manual') }}
    </h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        {{ __('Sumar o restar puntos manualmente a un cliente con motivo obligatorio') }}
    </p>

    {{-- Buscador de cliente --}}
    @if(!$clienteAjusteId)
        <div class="relative max-w-lg">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ __('Buscar cliente') }}
            </label>
            <input type="text" wire:model.live.debounce.300ms="searchClienteAjuste"
                placeholder="{{ __('Nombre, CUIT o teléfono...') }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">

            @if(strlen($searchClienteAjuste) >= 2)
            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-60 overflow-y-auto">
                @forelse($this->resultadosBusquedaAjuste as $cliente)
                <button type="button" wire:click="seleccionarClienteAjuste({{ $cliente->id }})"
                    class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</span>
                            @if($cliente->cuit)
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $cliente->cuit }}</span>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-bcn-primary">
                            {{ number_format($cliente->puntos_saldo_cache) }} pts
                        </span>
                    </div>
                </button>
                @empty
                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('No se encontraron clientes') }}
                </div>
                @endforelse
            </div>
            @endif
        </div>
    @else
        {{-- Cliente seleccionado --}}
        <div class="max-w-lg">
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg mb-6">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 h-10 w-10 bg-bcn-primary/10 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->clienteAjuste?->nombre }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Saldo de puntos') }}: <span class="font-bold text-bcn-primary">{{ number_format($this->clienteAjuste?->puntos_saldo_cache ?? 0) }}</span>
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="limpiarClienteAjuste"
                    class="text-sm text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Formulario de ajuste --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('Puntos a ajustar') }}
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        {{ __('Positivo para sumar, negativo para restar') }}
                    </p>
                    <input type="number" wire:model="ajustePuntos"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="Ej: 50 o -20">
                    @error('ajustePuntos') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('Motivo del ajuste') }}
                    </label>
                    <input type="text" wire:model="ajusteMotivo"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="{{ __('Ej: Compensación por error en venta #123') }}">
                    @error('ajusteMotivo') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Preview del resultado --}}
                @if($ajustePuntos != 0)
                <div class="rounded-lg p-3 border {{ $ajustePuntos > 0 ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }}">
                    <p class="text-sm {{ $ajustePuntos > 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ __('Saldo resultante') }}:
                        <span class="font-bold">
                            {{ number_format(($this->clienteAjuste?->puntos_saldo_cache ?? 0) + $ajustePuntos) }} pts
                        </span>
                    </p>
                </div>
                @endif

                <div class="flex justify-end">
                    <button type="button" wire:click="confirmarAjuste"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary"
                        {{ $ajustePuntos == 0 || empty($ajusteMotivo) ? 'disabled' : '' }}>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        {{ __('Realizar ajuste') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
