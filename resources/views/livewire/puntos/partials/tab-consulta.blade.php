<div class="space-y-6">
    {{-- Buscador de cliente --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ __('Consulta de puntos') }}
        </h3>

        @if(!$clienteSeleccionadoId)
            {{-- Input de búsqueda --}}
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('Buscar cliente') }}
                </label>
                <input type="text" wire:model.live.debounce.300ms="searchCliente"
                    placeholder="{{ __('Nombre, CUIT o teléfono...') }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                {{-- Resultados de búsqueda --}}
                @if(strlen($searchCliente) >= 2)
                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-60 overflow-y-auto">
                    @forelse($this->resultadosBusqueda as $cliente)
                    <button type="button" wire:click="seleccionarCliente({{ $cliente->id }})"
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
            {{-- Cliente seleccionado - Card resumen --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 h-10 w-10 bg-bcn-primary/10 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $this->clienteSeleccionado?->nombre }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->clienteSeleccionado?->cuit }}</p>
                    </div>
                </div>
                <button type="button" wire:click="limpiarCliente"
                    class="text-sm text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Stats cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                    <p class="text-xs text-green-600 dark:text-green-400 font-medium">{{ __('Puntos acumulados') }}</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-300">
                        {{ number_format($this->totalesCliente['acumulados']) }}
                    </p>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                    <p class="text-xs text-orange-600 dark:text-orange-400 font-medium">{{ __('Puntos canjeados') }}</p>
                    <p class="text-2xl font-bold text-orange-700 dark:text-orange-300">
                        {{ number_format($this->totalesCliente['canjeados']) }}
                    </p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                    <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ __('Saldo de puntos') }}</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">
                        {{ number_format($this->totalesCliente['saldo']) }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- Historial de movimientos --}}
    @if($clienteSeleccionadoId)
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ __('Historial de movimientos') }}
        </h3>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Tipo') }}</label>
                <select wire:model.live="filtroTipoMovimiento"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="acumulacion">{{ __('Acumulación') }}</option>
                    <option value="canje_descuento">{{ __('Canje descuento') }}</option>
                    <option value="canje_articulo">{{ __('Canje artículo') }}</option>
                    <option value="canje_cupon">{{ __('Canje cupón') }}</option>
                    <option value="ajuste_manual">{{ __('Ajuste manual') }}</option>
                    <option value="anulacion">{{ __('Anulación') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Desde') }}</label>
                <input type="date" wire:model.live="filtroFechaDesde"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Hasta') }}</label>
                <input type="date" wire:model.live="filtroFechaHasta"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>

        {{-- Mobile: cards --}}
        <div class="sm:hidden space-y-3">
            @forelse($movimientos as $mov)
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $mov->puntos > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ $mov->puntos > 0 ? '+' : '' }}{{ $mov->puntos }}
                        </span>
                        <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ str_replace('_', ' ', ucfirst($mov->tipo)) }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $mov->fecha->format('d/m/Y H:i') }}
                    </span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $mov->concepto }}</p>
                @if($mov->sucursal)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $mov->sucursal->nombre }}</p>
                @endif
            </div>
            @empty
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="mt-2 text-sm">{{ __('No hay movimientos de puntos') }}</p>
            </div>
            @endforelse
        </div>

        {{-- Desktop: tabla --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Tipo') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Puntos') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sucursal') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Venta') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($movimientos as $mov)
                    <tr class="{{ $mov->estaAnulado() ? 'opacity-50 line-through' : '' }}">
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ $mov->fecha->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                            @php
                                $tipoBadges = [
                                    'acumulacion' => ['label' => 'Acumulación', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
                                    'canje_descuento' => ['label' => 'Canje descuento', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'],
                                    'canje_articulo' => ['label' => 'Canje artículo', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'],
                                    'canje_cupon' => ['label' => 'Canje cupón', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'],
                                    'ajuste_manual' => ['label' => 'Ajuste manual', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'],
                                    'anulacion' => ['label' => 'Anulación', 'class' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
                                ];
                                $tipo = $tipoBadges[$mov->tipo] ?? ['label' => $mov->tipo, 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tipo['class'] }}">
                                {{ __($tipo['label']) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold text-right whitespace-nowrap {{ $mov->puntos > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $mov->puntos > 0 ? '+' : '' }}{{ $mov->puntos }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
                            {{ $mov->concepto }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $mov->sucursal?->nombre ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $mov->venta_id ? '#' . $mov->venta_id : '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No hay movimientos de puntos') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $movimientos->links() }}
        </div>
    </div>
    @endif
</div>
