<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Inventario de Stock') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Control de inventario y alertas por sucursal') }}</p>
                </div>
                <a
                    href="{{ route('stock.inventario-general') }}"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    {{ __('Inventario General') }}
                </a>
            </div>
        </div>

        <!-- Tarjetas de resumen -->
        <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">{{ $totalArticulos }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Bajo Mínimo') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $alertasBajoMinimo }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Sin Stock') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">{{ $articulosSinStock }}</p>
                    </div>
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
                            placeholder="{{ __('Buscar por código o nombre...') }}"
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
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Alertas') }}</label>
                            <select
                                wire:model.live="filterAlerta"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="bajo_minimo">{{ __('Bajo Mínimo') }}</option>
                                <option value="sin_stock">{{ __('Sin Stock') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Modo de stock') }}</label>
                            <select
                                wire:model.live="filterModoStock"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="unitario">{{ __('Unitario') }}</option>
                                <option value="receta">{{ __('Por receta') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                            <select
                                wire:model.live="filterTipo"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="articulo">{{ __('Artículos') }}</option>
                                <option value="materia_prima">{{ __('Materia prima') }}</option>
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($stocks as $stock)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $stock->articulo->nombre }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $stock->articulo->codigo }}</div>
                        </div>
                        <div class="ml-2">
                            @if($stock->estaBajoMinimo())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">{{ __('Bajo Mínimo') }}</span>
                            @elseif($stock->cantidad <= 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ __('Sin Stock') }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Normal') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('Mín:') }} {{ $stock->cantidad_minima !== null ? number_format($stock->cantidad_minima, 2, ',', '.') : '-' }}</span>
                            <span class="mx-1">|</span>
                            <span>{{ __('Máx:') }} {{ $stock->cantidad_maxima !== null ? number_format($stock->cantidad_maxima, 2, ',', '.') : '-' }}</span>
                        </div>
                        <span class="text-base font-bold {{ $stock->cantidad <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            @cantidad($stock->cantidad)
                        </span>
                    </div>

                    <div class="flex gap-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                        <button
                            wire:click="abrirModalAjuste({{ $stock->id }})"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-xs font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                        >
                            {{ __('Ajustar') }}
                        </button>
                        <button
                            wire:click="abrirModalInventario({{ $stock->id }})"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-purple-600 text-xs font-medium rounded-md text-purple-600 hover:bg-purple-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-600 transition-colors duration-150"
                        >
                            {{ __('Inventario') }}
                        </button>
                        <button
                            wire:click="abrirModalUmbrales({{ $stock->id }})"
                            class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors duration-150"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No hay stock registrado') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            @if($stocks->hasPages())
                <div class="mt-4">
                    {{ $stocks->links() }}
                </div>
            @endif
        </div>

        <!-- Tabla (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Cantidad') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Mínimo') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Máximo') }}</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($stocks as $stock)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $stock->articulo->nombre }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stock->articulo->codigo }}</div>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <span class="text-sm font-bold {{ $stock->cantidad <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                        @cantidad($stock->cantidad)
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $stock->cantidad_minima !== null ? number_format($stock->cantidad_minima, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $stock->cantidad_maxima !== null ? number_format($stock->cantidad_maxima, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @if($stock->estaBajoMinimo())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">{{ __('Bajo Mínimo') }}</span>
                                    @elseif($stock->cantidad <= 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ __('Sin Stock') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Normal') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            wire:click="abrirModalAjuste({{ $stock->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                            title="{{ __('Ajustar Stock') }}"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            {{ __('Ajustar') }}
                                        </button>
                                        <button
                                            wire:click="abrirModalInventario({{ $stock->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-purple-600 text-sm font-medium rounded-md text-purple-600 hover:bg-purple-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-600 transition-colors duration-150"
                                            title="{{ __('Inventario Físico') }}"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                            </svg>
                                            {{ __('Inventario') }}
                                        </button>
                                        <button
                                            wire:click="abrirModalUmbrales({{ $stock->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors duration-150"
                                            title="{{ __('Configurar Umbrales') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <p class="mt-2">{{ __('No hay stock registrado') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            @if($stocks->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $stocks->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Ajuste de Stock --}}
    @if($showAjusteModal && $stockAjuste)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-ajuste" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModal('showAjusteModal')"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <!-- Header -->
                    <div class="bg-bcn-primary px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Ajustar Stock') }}</h3>
                    </div>
                    <!-- Body -->
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $stockAjuste->articulo->nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Código:') }} {{ $stockAjuste->articulo->codigo }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Stock actual:') }} <span class="font-semibold">@cantidad($stockAjuste->cantidad)</span></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad de Ajuste') }}</label>
                            <input
                                wire:model="cantidadAjuste"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Positivo aumenta, negativo disminuye') }}"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ingrese un valor positivo para aumentar o negativo para disminuir') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }}</label>
                            <textarea
                                wire:model="motivoAjuste"
                                rows="3"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Describa el motivo del ajuste...') }}"
                            ></textarea>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            wire:click="procesarAjuste"
                            class="inline-flex w-full justify-center rounded-md bg-bcn-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 sm:w-auto transition-colors"
                        >
                            {{ __('Procesar Ajuste') }}
                        </button>
                        <button
                            wire:click="cerrarModal('showAjusteModal')"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Inventario Físico --}}
    @if($showInventarioModal && $stockInventario)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-inventario" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModal('showInventarioModal')"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <!-- Header -->
                    <div class="bg-purple-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Inventario Físico') }}</h3>
                    </div>
                    <!-- Body -->
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $stockInventario->articulo->nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Código:') }} {{ $stockInventario->articulo->codigo }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Stock en sistema:') }} <span class="font-semibold">@cantidad($stockInventario->cantidad)</span></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad Física Contada') }}</label>
                            <input
                                wire:model="cantidadFisica"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones') }}</label>
                            <textarea
                                wire:model="observacionesInventario"
                                rows="3"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Observaciones del conteo...') }}"
                            ></textarea>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            wire:click="procesarInventario"
                            class="inline-flex w-full justify-center rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 sm:w-auto transition-colors"
                        >
                            {{ __('Registrar') }}
                        </button>
                        <button
                            wire:click="cerrarModal('showInventarioModal')"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Umbrales --}}
    @if($showUmbralesModal && $stockUmbrales)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-umbrales" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModal('showUmbralesModal')"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <!-- Header -->
                    <div class="bg-gray-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Configurar Umbrales') }}</h3>
                    </div>
                    <!-- Body -->
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $stockUmbrales->articulo->nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Código:') }} {{ $stockUmbrales->articulo->codigo }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Stock actual:') }} <span class="font-semibold">@cantidad($stockUmbrales->cantidad)</span></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad Mínima') }}</label>
                            <input
                                wire:model="cantidadMinima"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Sin mínimo') }}"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se alertará cuando el stock baje de este valor') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad Máxima') }}</label>
                            <input
                                wire:model="cantidadMaxima"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Sin máximo') }}"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se alertará cuando el stock supere este valor') }}</p>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            wire:click="actualizarUmbrales"
                            class="inline-flex w-full justify-center rounded-md bg-bcn-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 sm:w-auto transition-colors"
                        >
                            {{ __('Guardar') }}
                        </button>
                        <button
                            wire:click="cerrarModal('showUmbralesModal')"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
