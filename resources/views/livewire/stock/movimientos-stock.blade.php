<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Movimientos de Stock') }}</h2>
                        <!-- Botones mobile -->
                        <div class="sm:hidden flex gap-2">
                            <button wire:click="abrirModalCarga" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-green-400 dark:border-green-600 rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 transition" title="{{ __('Carga de Stock') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                            </button>
                            <button wire:click="abrirModalDescarga" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-red-400 dark:border-red-600 rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="{{ __('Descarga de Stock') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                            </button>
                            <button wire:click="abrirModalInventario" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-purple-400 dark:border-purple-600 rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition" title="{{ __('Inventario Físico') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Historial de entradas y salidas de inventario') }}</p>
                </div>
                <!-- Botones Desktop -->
                <div class="hidden sm:flex gap-3">
                    <button wire:click="abrirModalCarga" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-green-400 dark:border-green-600 rounded-md font-semibold text-xs text-green-700 dark:text-green-400 uppercase tracking-widest hover:bg-green-50 dark:hover:bg-green-900/20 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        {{ __('Carga de Stock') }}
                    </button>
                    <button wire:click="abrirModalDescarga" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-red-400 dark:border-red-600 rounded-md font-semibold text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                        {{ __('Descarga de Stock') }}
                    </button>
                    <button wire:click="abrirModalInventario" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-purple-400 dark:border-purple-600 rounded-md font-semibold text-xs text-purple-700 dark:text-purple-400 uppercase tracking-widest hover:bg-purple-50 dark:hover:bg-purple-900/20 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        {{ __('Inventario Físico') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Tarjetas de resumen - Una fila en mobile y desktop -->
        <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="hidden sm:block flex-shrink-0 p-2 bg-green-100 dark:bg-green-900/30 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Entradas Hoy') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalEntradasHoy, 2) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="hidden sm:block flex-shrink-0 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" /></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Salidas Hoy') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($totalSalidasHoy, 2) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="hidden sm:block flex-shrink-0 p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Movimientos Hoy') }}</p>
                        <p class="text-lg sm:text-xl font-bold text-blue-600 dark:text-blue-400">{{ $totalMovimientosHoy }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtro de artículo activo (kardex) -->
        @if($articuloSeleccionado)
            @php $articuloFiltrado = \App\Models\Articulo::find($articuloSeleccionado); @endphp
            @if($articuloFiltrado)
                <div class="mb-4 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-lg p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                        <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                            {{ __('Kardex de') }}: <strong>{{ $articuloFiltrado->nombre }}</strong> ({{ $articuloFiltrado->codigo }})
                        </span>
                    </div>
                    <button wire:click="limpiarFiltroArticulo" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            @endif
        @endif

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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                            <select wire:model.live="filterTipo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach($tiposMovimiento as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                            <input wire:model.live="filterFechaDesde" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                            <input wire:model.live="filterFechaHasta" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vista de Tarjetas (Mobile) -->
        <div class="sm:hidden space-y-3">
            @forelse($movimientos as $mov)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $mov->articulo->nombre ?? '-' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $mov->articulo->codigo ?? '' }}</p>
                        </div>
                        @include('livewire.stock._movimiento-badge', ['tipo' => $mov->tipo, 'label' => $mov->tipo_label])
                    </div>
                    <div class="flex justify-between items-center text-sm mb-1">
                        <span class="text-gray-500 dark:text-gray-400">{{ $mov->fecha->format('d/m/Y') }} {{ $mov->created_at->format('H:i') }}</span>
                        <div class="flex gap-3">
                            @if($mov->entrada > 0)
                                <span class="text-green-600 dark:text-green-400 font-semibold">+{{ number_format($mov->entrada, 2) }}</span>
                            @endif
                            @if($mov->salida > 0)
                                <span class="text-red-600 dark:text-red-400 font-semibold">-{{ number_format($mov->salida, 2) }}</span>
                            @endif
                            <span class="font-medium text-gray-700 dark:text-gray-300">= {{ number_format($mov->stock_resultante, 2) }}</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-700 dark:text-gray-300 mb-1">{{ $mov->concepto }}</p>
                    @if($mov->observaciones)
                        <p class="text-xs text-gray-500 dark:text-gray-400 italic mb-1">{{ $mov->observaciones }}</p>
                    @endif
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-xs text-gray-400">{{ $mov->usuario->name ?? '-' }}</span>
                        <button wire:click="filtrarPorArticulo({{ $mov->articulo_id }})" class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            {{ __('Kardex') }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron movimientos de stock') }}</p>
                </div>
            @endforelse

            @if($movimientos->hasPages())
                <div class="mt-4">
                    {{ $movimientos->links() }}
                </div>
            @endif
        </div>

        <!-- Tabla (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha/Hora') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Entrada') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Salida') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Stock') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Detalle') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Usuario') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($movimientos as $mov)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $mov->fecha->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $mov->created_at->format('H:i:s') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $mov->articulo->nombre ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $mov->articulo->codigo ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @include('livewire.stock._movimiento-badge', ['tipo' => $mov->tipo, 'label' => $mov->tipo_label])
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($mov->entrada > 0)
                                        <span class="text-green-600 dark:text-green-400 font-semibold">+{{ number_format($mov->entrada, 2) }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($mov->salida > 0)
                                        <span class="text-red-600 dark:text-red-400 font-semibold">-{{ number_format($mov->salida, 2) }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($mov->stock_resultante, 2) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $mov->concepto }}</div>
                                    @if($mov->observaciones)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate italic" title="{{ $mov->observaciones }}">{{ $mov->observaciones }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $mov->usuario->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <button wire:click="filtrarPorArticulo({{ $mov->articulo_id }})" class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline" title="{{ __('Ver Kardex') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                        {{ __('Ver Kardex') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron movimientos de stock') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($movimientos->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $movimientos->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== MODAL: Carga de Stock ==================== --}}
    @if($showCargaModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showCargaModal', false)"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-green-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Carga de Stock') }}</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <!-- Buscar artículo -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Artículo') }}</label>
                            <input wire:model.live.debounce.300ms="cargaSearchArticulo" type="text" placeholder="{{ __('Buscar artículo...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if($this->articulosCarga->count() > 0 && !$cargaArticuloId)
                                <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    @foreach($this->articulosCarga as $art)
                                        <button wire:click="seleccionarArticuloCarga({{ $art->id }})" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-white">
                                            <span class="font-medium">{{ $art->codigo }}</span> - {{ $art->nombre }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @error('cargaArticuloId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad') }}</label>
                            <input wire:model="cargaCantidad" type="number" step="0.01" min="0.01" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @error('cargaCantidad') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }}</label>
                            <input wire:model="cargaConcepto" type="text" placeholder="{{ __('Motivo de la carga...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @error('cargaConcepto') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button wire:click="procesarCarga" class="inline-flex w-full justify-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 sm:w-auto transition-colors">
                            {{ __('Registrar Carga') }}
                        </button>
                        <button wire:click="$set('showCargaModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== MODAL: Descarga de Stock ==================== --}}
    @if($showDescargaModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDescargaModal', false)"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-red-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Descarga de Stock') }}</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <!-- Buscar artículo -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Artículo') }}</label>
                            <input wire:model.live.debounce.300ms="descargaSearchArticulo" type="text" placeholder="{{ __('Buscar artículo...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if($this->articulosDescarga->count() > 0 && !$descargaArticuloId)
                                <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    @foreach($this->articulosDescarga as $art)
                                        <button wire:click="seleccionarArticuloDescarga({{ $art->id }})" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-white">
                                            <span class="font-medium">{{ $art->codigo }}</span> - {{ $art->nombre }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @error('descargaArticuloId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad') }}</label>
                            <input wire:model="descargaCantidad" type="number" step="0.01" min="0.01" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @error('descargaCantidad') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }}</label>
                            <input wire:model="descargaConcepto" type="text" placeholder="{{ __('Motivo de la descarga...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @error('descargaConcepto') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button wire:click="procesarDescarga" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                            {{ __('Registrar Descarga') }}
                        </button>
                        <button wire:click="$set('showDescargaModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== MODAL: Inventario Físico ==================== --}}
    @if($showInventarioModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showInventarioModal', false)"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-purple-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Inventario Físico') }}</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <!-- Buscar artículo -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Artículo') }}</label>
                            <input wire:model.live.debounce.300ms="inventarioSearchArticulo" type="text" placeholder="{{ __('Buscar artículo...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if($this->articulosInventario->count() > 0 && !$inventarioArticuloId)
                                <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    @foreach($this->articulosInventario as $art)
                                        <button wire:click="seleccionarArticuloInventario({{ $art->id }})" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-white">
                                            <span class="font-medium">{{ $art->codigo }}</span> - {{ $art->nombre }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @error('inventarioArticuloId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        @if($inventarioArticuloId)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-md p-3">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ __('Stock actual en sistema') }}: <strong class="text-gray-900 dark:text-white">{{ number_format($inventarioStockActual, 2) }}</strong>
                                </p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad Física Contada') }}</label>
                            <input wire:model="inventarioCantidadFisica" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm" />
                            @error('inventarioCantidadFisica') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                            @if($inventarioArticuloId)
                                @php $diferencia = $inventarioCantidadFisica - $inventarioStockActual; @endphp
                                @if($diferencia != 0)
                                    <p class="text-xs mt-1 {{ $diferencia > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ __('Diferencia') }}: {{ $diferencia > 0 ? '+' : '' }}{{ number_format($diferencia, 2) }}
                                        ({{ $diferencia > 0 ? __('Sobrante') : __('Faltante') }})
                                    </p>
                                @endif
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones') }}</label>
                            <textarea wire:model="inventarioObservaciones" rows="2" placeholder="{{ __('Observaciones del conteo...') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm"></textarea>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button wire:click="procesarInventario" class="inline-flex w-full justify-center rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 sm:w-auto transition-colors">
                            {{ __('Registrar Inventario') }}
                        </button>
                        <button wire:click="$set('showInventarioModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
