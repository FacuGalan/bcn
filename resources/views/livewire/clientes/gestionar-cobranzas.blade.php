<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Cobranzas') }}</h2>
                        <!-- Botones móvil -->
                        <div class="sm:hidden flex gap-2">
                            <button
                                wire:click="generarReporteAntiguedad"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Reporte de Antigüedad') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Gestiona los cobros de clientes con cuenta corriente') }}</p>
                </div>
                <!-- Botones Desktop -->
                <div class="hidden sm:flex gap-3">
                    <button
                        wire:click="generarReporteAntiguedad"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {{ __('Reporte Antigüedad') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <div class="flex flex-col gap-4">
                <!-- Búsqueda y toggle filtros -->
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Buscar por nombre, CUIT, teléfono...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <button
                        wire:click="toggleFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary"
                    >
                        <svg class="w-5 h-5 {{ $showFilters ? 'text-bcn-primary' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </button>
                </div>

                <!-- Filtros expandibles -->
                @if($showFilters)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <!-- Estado de deuda -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                            <select
                                wire:model.live="filterEstado"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="con_deuda">{{ __('Con deuda') }}</option>
                                <option value="sin_deuda">{{ __('Sin deuda') }}</option>
                            </select>
                        </div>

                        <!-- Antigüedad -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Antigüedad') }}</label>
                            <select
                                wire:model.live="filterAntiguedad"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todas') }}</option>
                                <option value="0_30">{{ __('0-30 días') }}</option>
                                <option value="31_60">{{ __('31-60 días') }}</option>
                                <option value="61_90">{{ __('61-90 días') }}</option>
                                <option value="90_mas">{{ __('Más de 90 días') }}</option>
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($clientes as $cliente)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</h3>
                            @if($cliente->cuit)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CUIT: {{ $cliente->cuit }}</p>
                            @endif
                            @if($cliente->telefono)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cliente->telefono }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            @if($cliente->saldo_deudor_cache > 0)
                                <span class="text-lg font-bold text-red-600">${{ number_format($cliente->saldo_deudor_cache, 2, ',', '.') }}</span>
                                <p class="text-xs text-gray-500">{{ __('Deuda') }}</p>
                            @else
                                <span class="text-sm text-green-600 font-medium">{{ __('Sin deuda') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        @if($cliente->saldo_deudor_cache > 0)
                            <button
                                wire:click="abrirModalCobro({{ $cliente->id }})"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-bcn-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-opacity-90 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                {{ __('Cobrar') }}
                            </button>
                        @endif
                        <button
                            wire:click="verCuentaCorriente({{ $cliente->id }})"
                            class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                            title="{{ __('Ver cuenta corriente') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron clientes con cuenta corriente') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $clientes->links() }}
            </div>
        </div>

        <!-- Tabla Desktop -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Cliente') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Contacto') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Límite Crédito') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Saldo Deudor') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Días Mora') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Acciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($clientes as $cliente)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</span>
                                    @if($cliente->cuit)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">CUIT: {{ $cliente->cuit }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($cliente->telefono)
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $cliente->telefono }}</div>
                                    @endif
                                    @if($cliente->email)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cliente->email }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($cliente->limite_credito > 0)
                                        <span class="text-sm text-gray-700 dark:text-gray-300">${{ number_format($cliente->limite_credito, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">{{ __('Sin límite') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($cliente->saldo_deudor_cache > 0)
                                        <span class="text-sm font-bold text-red-600">${{ number_format($cliente->saldo_deudor_cache, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-sm text-green-600">$0,00</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($cliente->dias_mora_max > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cliente->dias_mora_max > 60 ? 'bg-red-100 text-red-800' : ($cliente->dias_mora_max > 30 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ $cliente->dias_mora_max }} {{ __('días') }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($cliente->saldo_deudor_cache > 0)
                                            <button
                                                wire:click="abrirModalCobro({{ $cliente->id }})"
                                                class="inline-flex items-center px-3 py-2 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 transition-colors duration-150"
                                                title="{{ __('Registrar cobro') }}"
                                            >
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                {{ __('Cobrar') }}
                                            </button>
                                        @endif
                                        <button
                                            wire:click="verCuentaCorriente({{ $cliente->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                                            title="{{ __('Ver cuenta corriente') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron clientes con cuenta corriente') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                {{ $clientes->links() }}
            </div>
        </div>

        {{-- ==================== Modal de Cobro ==================== --}}
        @if($showCobroModal)
        <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="modal-cobro" role="dialog" aria-modal="true">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalCobro"></div>

            {{-- Modal Container --}}
            <div class="fixed inset-4 sm:inset-6 lg:inset-auto lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2 lg:max-w-5xl lg:w-[calc(100%-3rem)] lg:max-h-[calc(100vh-3rem)] flex flex-col bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
                {{-- Header fijo --}}
                <div class="flex-shrink-0 flex items-center justify-between px-4 sm:px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">{{ __('Registrar Cobro') }}</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400 hidden sm:inline">—</span>
                        <span class="text-sm font-medium text-bcn-primary truncate">{{ $clienteCobro?->nombre }}</span>
                    </div>
                    <button type="button" wire:click="cerrarModalCobro" class="flex-shrink-0 p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Body scrolleable --}}
                <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Columna Izquierda: Ventas y Observaciones --}}
                        <div>
                            {{-- Monto a cobrar y Selector de modo en la misma fila --}}
                            <div class="flex items-end gap-3 mb-4">
                                {{-- Monto a cobrar (solo FIFO) --}}
                                @if($modoSeleccion === 'fifo')
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto a cobrar') }}</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model.live.debounce.500ms="montoACobrar"
                                            class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>
                                @else
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ventas a cobrar') }}</span>
                                </div>
                                @endif

                                {{-- Selector de modo --}}
                                <button
                                    type="button"
                                    wire:click="toggleModoSeleccion"
                                    class="inline-flex items-center px-3 py-2 rounded-md text-xs font-medium border {{ $modoSeleccion === 'fifo' ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700' : 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700' }}"
                                >
                                    {{ $modoSeleccion === 'fifo' ? __('FIFO') : __('Manual') }}
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Lista de ventas pendientes --}}
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-64 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            @if($modoSeleccion === 'manual')
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 w-10"></th>
                                            @endif
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Venta') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Saldo') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Interés') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">{{ __('Aplicar') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($ventasPendientes as $index => $venta)
                                        <tr class="{{ $venta['seleccionada'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                            @if($modoSeleccion === 'manual')
                                            <td class="px-3 py-2">
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleVentaSeleccion({{ $index }})"
                                                    {{ $venta['seleccionada'] ? 'checked' : '' }}
                                                    class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                                />
                                            </td>
                                            @endif
                                            <td class="px-3 py-2">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">#{{ $venta['numero'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $venta['fecha'] }}</div>
                                                @if($venta['dias_mora'] > 0)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    {{ $venta['dias_mora'] }} {{ __('días mora') }}
                                                </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right text-sm text-gray-700 dark:text-gray-300">
                                                ${{ number_format($venta['saldo_pendiente'], 2, ',', '.') }}
                                            </td>
                                            <td class="px-3 py-2 text-right text-sm {{ $venta['interes_mora'] > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                                                @if($venta['interes_mora'] > 0)
                                                ${{ number_format($venta['interes_mora'], 2, ',', '.') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                @if($modoSeleccion === 'manual' && $venta['seleccionada'])
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    max="{{ $venta['saldo_pendiente'] }}"
                                                    wire:change="actualizarMontoVenta({{ $index }}, $event.target.value)"
                                                    value="{{ $venta['monto_a_aplicar'] }}"
                                                    class="w-24 text-right text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                                />
                                                @elseif($venta['monto_a_aplicar'] > 0)
                                                <span class="text-sm font-medium text-green-600">${{ number_format($venta['monto_a_aplicar'], 2, ',', '.') }}</span>
                                                @else
                                                <span class="text-sm text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="{{ $modoSeleccion === 'manual' ? 5 : 4 }}" class="px-3 py-6 text-center text-sm text-gray-500">
                                                {{ __('No hay ventas pendientes') }}
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Resumen de aplicación y Descuento en la misma fila --}}
                            <div class="mt-3 flex gap-3 items-stretch">
                                {{-- Aplicado a deuda --}}
                                <div class="flex-1 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">{{ __('Aplicado:') }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${{ number_format($montoACobrar, 2, ',', '.') }}</span>
                                    </div>
                                    @if($interesTotal > 0)
                                    <div class="flex justify-between text-sm mt-1">
                                        <span class="text-gray-600 dark:text-gray-400">{{ __('Interés:') }}</span>
                                        <span class="font-medium text-orange-600">${{ number_format($interesTotal, 2, ',', '.') }}</span>
                                    </div>
                                    @endif
                                </div>

                                {{-- Descuento --}}
                                <div class="w-32">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Descuento') }}</label>
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model.live.debounce.500ms="descuentoAplicado"
                                            class="w-full pl-6 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>
                            </div>

                            {{-- Observaciones --}}
                            <div class="mt-4">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Observaciones') }}</label>
                                <textarea
                                    wire:model="observaciones"
                                    rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                    placeholder="{{ __('Observaciones opcionales...') }}"
                                ></textarea>
                            </div>
                        </div>

                        {{-- Columna Derecha: Pagos --}}
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 block">{{ __('Formas de pago') }}</span>

                            {{-- Agregar pago --}}
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-3">
                                <div class="flex gap-2 items-end">
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Forma de pago') }}</label>
                                        <select
                                            wire:model.live="nuevoPago.forma_pago_id"
                                            wire:change="cargarCuotasParaFormaPago($event.target.value)"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm"
                                        >
                                            <option value="">{{ __('Seleccionar...') }}</option>
                                            @foreach($formasPagoSucursal as $fp)
                                            <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }} @if($fp['ajuste_porcentaje'] != 0)({{ $fp['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $fp['ajuste_porcentaje'] }}%)@endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-28">
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Importe') }}</label>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                wire:model.live="nuevoPago.monto"
                                                class="w-full pl-5 pr-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm"
                                                placeholder="{{ number_format($montoPendienteDesglose, 2) }}"
                                            />
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="agregarAlDesglose"
                                        class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90"
                                        title="{{ __('Agregar') }}"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Selector de Cuotas (si la forma de pago permite cuotas) --}}
                                @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                                <div class="mt-2 relative">
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Cuotas') }}</label>
                                    <div
                                        wire:click="toggleCuotasSelector"
                                        class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                    >
                                        @if(!$cuotaSeleccionadaId)
                                        {{-- 1 pago seleccionado --}}
                                        <div class="flex items-center px-2 py-1.5">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
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
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuotaSel['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuotaSel['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuotaSel['recargo_porcentaje'] > 0)
                                                <span class="text-[10px] font-medium text-red-600">+{{ $cuotaSel['recargo_porcentaje'] }}%</span>
                                                @else
                                                <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-semibold {{ $cuotaSel['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuotaSel['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                        @endif
                                        @endif
                                    </div>

                                    {{-- Dropdown de opciones de cuotas --}}
                                    @if($cuotasSelectorAbierto)
                                    <div class="absolute z-20 w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-40 overflow-y-auto">
                                        {{-- Opción: 1 pago --}}
                                        <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ !$cuotaSeleccionadaId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                            <input type="radio" wire:model.live="cuotaSeleccionadaId" value="" class="sr-only">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                            </div>
                                            @if(!$cuotaSeleccionadaId)
                                            <svg class="w-3 h-3 text-blue-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            @endif
                                        </label>

                                        {{-- Opciones de cuotas --}}
                                        @foreach($cuotasFormaPagoDisponibles as $cuota)
                                        <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $cuotaSeleccionadaId == $cuota['id'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                            <input type="radio" wire:model.live="cuotaSeleccionadaId" value="{{ $cuota['id'] }}" class="sr-only">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuota['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuota['recargo_porcentaje'] > 0)
                                                <span class="text-[10px] font-medium text-red-600">+{{ $cuota['recargo_porcentaje'] }}%</span>
                                                @else
                                                <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-semibold {{ $cuota['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuota['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                            @if($cuotaSeleccionadaId == $cuota['id'])
                                            <svg class="w-3 h-3 text-blue-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            @endif
                                        </label>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                @endif

                                {{-- Checkbox aplicar ajuste --}}
                                @php
                                    $fpSeleccionada = collect($formasPagoSucursal)->firstWhere('id', (int) $nuevoPago['forma_pago_id']);
                                @endphp
                                @if($fpSeleccionada && $fpSeleccionada['ajuste_porcentaje'] != 0)
                                <div class="mt-2 flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="nuevoPago.aplicar_ajuste"
                                        id="aplicar_ajuste_cobro"
                                        class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                    />
                                    <label for="aplicar_ajuste_cobro" class="ml-2 text-xs text-gray-600 dark:text-gray-400">
                                        {{ __('Aplicar') }} {{ $fpSeleccionada['ajuste_porcentaje'] > 0 ? __('recargo') : __('descuento') }} ({{ $fpSeleccionada['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $fpSeleccionada['ajuste_porcentaje'] }}%)
                                    </label>
                                </div>
                                @endif

                                @if($montoPendienteDesglose > 0)
                                <button
                                    type="button"
                                    wire:click="asignarMontoPendiente"
                                    class="mt-2 text-xs text-bcn-primary hover:underline font-medium"
                                >
                                    → {{ __('Usar monto pendiente') }}: ${{ number_format($montoPendienteDesglose, 2, ',', '.') }}
                                </button>
                                @endif
                            </div>

                            {{-- Lista de pagos agregados --}}
                            <div class="space-y-2 max-h-32 overflow-y-auto flex-shrink-0">
                                @forelse($desglosePagos as $index => $pago)
                                <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $pago['nombre'] }}</span>
                                        @if(($pago['ajuste_original'] ?? $pago['ajuste_porcentaje']) != 0)
                                            @if($pago['ajuste_porcentaje'] == 0 && ($pago['ajuste_original'] ?? 0) != 0)
                                            <span class="text-xs text-gray-400 ml-1 line-through">({{ ($pago['ajuste_original'] ?? 0) > 0 ? '+' : '' }}{{ $pago['ajuste_original'] ?? 0 }}%)</span>
                                            <span class="text-xs text-green-600 ml-1">{{ __('sin ajuste') }}</span>
                                            @else
                                            <span class="text-xs {{ $pago['ajuste_porcentaje'] > 0 ? 'text-red-500' : 'text-green-600' }} ml-1">({{ $pago['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $pago['ajuste_porcentaje'] }}%)</span>
                                            @endif
                                        @endif
                                        @if($pago['cuotas'] > 1)
                                        <span class="text-xs text-blue-600 ml-1">{{ $pago['cuotas'] }} {{ __('cuotas') }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-right">
                                            @if($pago['monto_ajuste'] != 0 || ($pago['cuotas'] > 1 && $pago['recargo_cuotas'] > 0))
                                            <span class="text-xs text-gray-500 block">${{ number_format($pago['monto_base'], 2, ',', '.') }}</span>
                                            @endif
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">${{ number_format($pago['monto_final'], 2, ',', '.') }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="quitarDelDesglose({{ $index }})"
                                            class="text-red-500 hover:text-red-700"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Agregue formas de pago') }}
                                </div>
                                @endforelse
                            </div>

                            {{-- Totales (siempre visible al final) --}}
                            <div class="mt-auto pt-3">
                                <div class="p-3 bg-bcn-light dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ __('Subtotal deuda:') }}</span>
                                            <span class="text-gray-900 dark:text-white">${{ number_format($montoACobrar, 2, ',', '.') }}</span>
                                        </div>
                                        @if($interesTotal > 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ __('Interés mora:') }}</span>
                                            <span class="text-orange-600">+${{ number_format($interesTotal, 2, ',', '.') }}</span>
                                        </div>
                                        @endif
                                        @if($descuentoAplicado > 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ __('Descuento:') }}</span>
                                            <span class="text-green-600">-${{ number_format($descuentoAplicado, 2, ',', '.') }}</span>
                                        </div>
                                        @endif
                                        <div class="flex justify-between text-sm font-medium border-t border-gray-300 dark:border-gray-600 pt-1 mt-1">
                                            <span class="text-gray-700 dark:text-gray-300">{{ __('Total a pagar:') }}</span>
                                            <span class="text-gray-900 dark:text-white">${{ number_format($montoACobrar + $interesTotal - $descuentoAplicado, 2, ',', '.') }}</span>
                                        </div>
                                        @if($totalAjustesFP != 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ __('Ajuste formas de pago:') }}</span>
                                            <span class="{{ $totalAjustesFP > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $totalAjustesFP > 0 ? '+' : '' }}${{ number_format($totalAjustesFP, 2, ',', '.') }}</span>
                                        </div>
                                        @endif
                                        <div class="flex justify-between text-base font-bold border-t border-gray-300 dark:border-gray-600 pt-2 mt-1">
                                            <span class="text-gray-900 dark:text-white">{{ __('TOTAL FINAL:') }}</span>
                                            <span class="text-bcn-primary">${{ number_format($montoACobrar + $interesTotal - $descuentoAplicado + $totalAjustesFP, 2, ',', '.') }}</span>
                                        </div>
                                        @if($montoPendienteDesglose > 0.01)
                                        <div class="flex justify-between text-sm text-red-600 font-medium">
                                            <span>{{ __('Pendiente de pago:') }}</span>
                                            <span>${{ number_format($montoPendienteDesglose, 2, ',', '.') }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer fijo --}}
                <div class="flex-shrink-0 px-4 sm:px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                        <button
                            type="button"
                            wire:click="cerrarModalCobro"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            {{ __('Cancelar') }}
                        </button>
                        <button
                            type="button"
                            wire:click="procesarCobro"
                            wire:loading.attr="disabled"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-bcn-primary border border-transparent rounded-lg hover:bg-opacity-90 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="procesarCobro">{{ __('Registrar Cobro') }}</span>
                            <span wire:loading wire:target="procesarCobro" class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Procesando...') }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ==================== Modal de Cuenta Corriente ==================== --}}
        @if($showCuentaCorrienteModal)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarCuentaCorriente"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Cuenta Corriente') }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $clienteCC?->nombre }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Saldo actual') }}</p>
                                    <p class="text-xl font-bold {{ ($clienteCC?->saldo_deudor_cache ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        ${{ number_format($clienteCC?->saldo_deudor_cache ?? 0, 2, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Fecha') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Descripción') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Debe') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Haber') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Saldo') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($movimientosCC as $mov)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                                                    {{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m/Y') }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <span class="inline-flex items-center">
                                                        @if($mov['tipo'] === 'venta')
                                                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                                        @else
                                                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                                        @endif
                                                        {{ $mov['descripcion'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $mov['debe'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                                    {{ $mov['debe'] > 0 ? '$' . number_format($mov['debe'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $mov['haber'] > 0 ? 'text-green-600' : 'text-gray-400' }}">
                                                    {{ $mov['haber'] > 0 ? '$' . number_format($mov['haber'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right font-medium {{ $mov['saldo'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                    ${{ number_format($mov['saldo'], 2, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                                    {{ __('No hay movimientos registrados') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            @if(($clienteCC?->saldo_deudor_cache ?? 0) > 0)
                                <button
                                    type="button"
                                    wire:click="abrirModalCobro({{ $clienteIdCC }})"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    {{ __('Registrar Cobro') }}
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="cerrarCuentaCorriente"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cerrar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== Modal de Reporte de Antigüedad ==================== --}}
        @if($showReporteAntiguedad)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarReporteAntiguedad"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Reporte de Antigüedad de Deuda') }}</h3>
                                <button type="button" wire:click="cerrarReporteAntiguedad" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Resumen de totales --}}
                            <div class="mt-4 grid grid-cols-5 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('0-30 días') }}</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($reporteAntiguedad['totales']['0_30'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg text-center">
                                    <p class="text-xs text-yellow-600 dark:text-yellow-400 uppercase">{{ __('31-60 días') }}</p>
                                    <p class="text-lg font-bold text-yellow-700 dark:text-yellow-300">${{ number_format($reporteAntiguedad['totales']['31_60'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center">
                                    <p class="text-xs text-orange-600 dark:text-orange-400 uppercase">{{ __('61-90 días') }}</p>
                                    <p class="text-lg font-bold text-orange-700 dark:text-orange-300">${{ number_format($reporteAntiguedad['totales']['61_90'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                                    <p class="text-xs text-red-600 dark:text-red-400 uppercase">{{ __('90+ días') }}</p>
                                    <p class="text-lg font-bold text-red-700 dark:text-red-300">${{ number_format($reporteAntiguedad['totales']['90_mas'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="p-4 bg-bcn-light dark:bg-gray-600 rounded-lg text-center">
                                    <p class="text-xs text-bcn-primary dark:text-white uppercase font-semibold">{{ __('Total') }}</p>
                                    <p class="text-lg font-bold text-bcn-primary dark:text-white">${{ number_format($reporteAntiguedad['totales']['total'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                            </div>

                            {{-- Tabla de clientes --}}
                            <div class="mt-4 max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cliente') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('0-30') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('31-60') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('61-90') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('90+') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Total') }}</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($reporteAntiguedad['clientes'] ?? [] as $item)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-2">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['cliente']->nombre ?? '-' }}</span>
                                                    @if($item['cliente']->telefono ?? false)
                                                        <br><span class="text-xs text-gray-500">{{ $item['cliente']->telefono }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $item['0_30'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">
                                                    {{ $item['0_30'] > 0 ? '$' . number_format($item['0_30'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $item['31_60'] > 0 ? 'text-yellow-600' : 'text-gray-400' }}">
                                                    {{ $item['31_60'] > 0 ? '$' . number_format($item['31_60'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $item['61_90'] > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                                                    {{ $item['61_90'] > 0 ? '$' . number_format($item['61_90'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right {{ $item['90_mas'] > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                                    {{ $item['90_mas'] > 0 ? '$' . number_format($item['90_mas'], 2, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-right font-bold text-gray-900 dark:text-white">
                                                    ${{ number_format($item['total'], 2, ',', '.') }}
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <button
                                                        wire:click="abrirModalCobro({{ $item['cliente']->id ?? 0 }})"
                                                        class="inline-flex items-center px-2 py-1 text-xs bg-bcn-primary text-white rounded hover:bg-opacity-90"
                                                    >
                                                        {{ __('Cobrar') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                                                    {{ __('No hay deudas pendientes') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="cerrarReporteAntiguedad"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cerrar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Script para impresión --}}
    @script
    <script>
        Livewire.on('cobro-registrado', ({ cobroId }) => {
            // Disparar impresión de recibo
            if (typeof QZIntegration !== 'undefined' && QZIntegration.estaDisponible()) {
                QZIntegration.imprimirReciboCobro(cobroId);
            }
        });

        Livewire.on('imprimir-recibo-cobro', ({ cobroId }) => {
            if (typeof QZIntegration !== 'undefined' && QZIntegration.estaDisponible()) {
                QZIntegration.imprimirReciboCobro(cobroId);
            } else {
                Livewire.dispatch('toast-error', { message: 'QZ Tray no disponible' });
            }
        });
    </script>
    @endscript
</div>
