<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-3 sm:gap-4">
                <div class="flex items-center gap-3">
                    <button
                        wire:click="volver"
                        class="inline-flex items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 hover:text-purple-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                        title="{{ __('Volver al inventario') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Inventario General') }}</h2>
                        <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Inventario general de stock por sucursal') }}</p>
                    </div>
                </div>
                @if(count($cantidadesFisicas) > 0)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                        {{ count($cantidadesFisicas) }} {{ __('con conteo') }}
                    </span>
                @endif
            </div>
        </div>

        <!-- Panel de filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <!-- Búsqueda y toggle filtros -->
            <div class="p-4 sm:p-6">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Buscar por código o nombre...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <button
                        wire:click="toggleFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                        <svg class="w-5 h-5 {{ $showFilters ? 'text-purple-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        @if(count($categoriasSeleccionadas) > 0 || count($etiquetasSeleccionadas) > 0)
                            <span class="ml-1 px-1.5 py-0.5 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 text-xs rounded-full">
                                {{ count($categoriasSeleccionadas) + count($etiquetasSeleccionadas) }}
                            </span>
                        @endif
                    </button>
                </div>
            </div>

            <!-- Filtros expandibles -->
            @if($showFilters)
                <div class="px-4 sm:px-6 pb-4 sm:pb-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Categorías -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Categorías') }}
                                @if(count($categoriasSeleccionadas) > 0)
                                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 text-xs rounded-full">
                                        {{ count($categoriasSeleccionadas) }} {{ __('seleccionadas') }}
                                    </span>
                                @endif
                            </label>
                            <div class="relative mb-2">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaCategoria"
                                    placeholder="{{ __('Buscar categoría...') }}"
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-colors"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 space-y-1">
                                @forelse($categorias as $categoria)
                                    <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model.live="categoriasSeleccionadas"
                                            value="{{ $categoria->id }}"
                                            class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                        />
                                        <span class="ml-2 flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $categoria->color }}"></span>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $categoria->nombre }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400 p-2">{{ __('No hay categorías disponibles') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Etiquetas agrupadas -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Etiquetas') }}
                                @if(count($etiquetasSeleccionadas) > 0)
                                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 text-xs rounded-full">
                                        {{ count($etiquetasSeleccionadas) }} {{ __('seleccionadas') }}
                                    </span>
                                @endif
                            </label>
                            <div class="relative mb-2">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaEtiqueta"
                                    placeholder="{{ __('Buscar grupo o etiqueta...') }}"
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-colors"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                @forelse($gruposEtiquetas as $grupo)
                                    @if($grupo->etiquetas->count() > 0)
                                        <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2 sticky top-0">
                                                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $grupo->color }}"></span>
                                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                            </div>
                                            <div class="p-2 space-y-1">
                                                @foreach($grupo->etiquetas as $etiqueta)
                                                    <label class="flex items-center p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            wire:model.live="etiquetasSeleccionadas"
                                                            value="{{ $etiqueta->id }}"
                                                            class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                                        />
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400 p-3">{{ __('No hay etiquetas disponibles') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-2 pb-56">
            @forelse($articulos as $articulo)
                @php
                    $stockSistema = $stocksPorArticulo[$articulo->id] ?? 0;
                    $tieneConteo = array_key_exists($articulo->id, $cantidadesFisicas);
                    $conteo = $tieneConteo ? $cantidadesFisicas[$articulo->id] : null;
                    $diferencia = $tieneConteo ? $conteo - $stockSistema : null;
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-3 py-2.5 {{ $tieneConteo ? ($diferencia == 0 ? 'ring-1 ring-green-400' : ($diferencia > 0 ? 'ring-1 ring-yellow-400' : 'ring-1 ring-red-400')) : '' }}">
                    <div class="flex items-center gap-3">
                        <!-- Info del artículo -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                @if($articulo->categoriaModel)
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $articulo->categoriaModel->color }}"></span>
                                @endif
                                <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">|</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Sist.') }} <span class="font-semibold text-gray-700 dark:text-gray-200">{{ number_format($stockSistema, 2, ',', '.') }}</span></span>
                                @if($tieneConteo && $diferencia !== null)
                                    <span class="text-xs font-semibold {{ $diferencia > 0 ? 'text-yellow-600' : ($diferencia < 0 ? 'text-red-600' : 'text-green-600') }}">
                                        {{ $diferencia >= 0 ? '+' : '' }}{{ number_format($diferencia, 2, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <!-- Input conteo -->
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value="{{ $tieneConteo ? $conteo : '' }}"
                            wire:change="actualizarCantidad({{ $articulo->id }}, $event.target.value)"
                            placeholder="-"
                            class="w-24 text-center rounded-md shadow-sm text-sm flex-shrink-0
                                {{ $tieneConteo
                                    ? ($diferencia == 0 ? 'border-green-400 focus:border-green-500 focus:ring-green-500' : ($diferencia > 0 ? 'border-yellow-400 focus:border-yellow-500 focus:ring-yellow-500' : 'border-red-400 focus:border-red-500 focus:ring-red-500'))
                                    : 'border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-purple-500'
                                }}
                                dark:bg-gray-700 dark:text-white focus:ring focus:ring-opacity-50"
                        />
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron artículos') }}</p>
                </div>
            @endforelse

            @if($articulos->hasPages())
                <div class="mt-4">
                    {{ $articulos->links() }}
                </div>
            @endif
        </div>

        <!-- Tabla (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-32">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-purple-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-20">{{ __('Código') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-32">{{ __('Stock Sistema') }}</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-40">{{ __('Conteo Físico') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-32">{{ __('Diferencia') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($articulos as $articulo)
                            @php
                                $stockSistema = $stocksPorArticulo[$articulo->id] ?? 0;
                                $tieneConteo = array_key_exists($articulo->id, $cantidadesFisicas);
                                $conteo = $tieneConteo ? $cantidadesFisicas[$articulo->id] : null;
                                $diferencia = $tieneConteo ? $conteo - $stockSistema : null;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($articulo->categoriaModel)
                                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $articulo->categoriaModel->color }}"></span>
                                        @endif
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ number_format($stockSistema, 2, ',', '.') }}</span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value="{{ $tieneConteo ? $conteo : '' }}"
                                        wire:change="actualizarCantidad({{ $articulo->id }}, $event.target.value)"
                                        placeholder="-"
                                        class="w-28 text-center rounded-md shadow-sm text-sm
                                            {{ $tieneConteo
                                                ? ($diferencia == 0 ? 'border-green-400 bg-green-50 dark:bg-green-900/20 focus:border-green-500 focus:ring-green-500' : ($diferencia > 0 ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 focus:border-yellow-500 focus:ring-yellow-500' : 'border-red-400 bg-red-50 dark:bg-red-900/20 focus:border-red-500 focus:ring-red-500'))
                                                : 'border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-purple-500'
                                            }}
                                            dark:text-white focus:ring focus:ring-opacity-50"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($tieneConteo && $diferencia !== null)
                                        <span class="text-sm font-semibold {{ $diferencia > 0 ? 'text-yellow-600 dark:text-yellow-400' : ($diferencia < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400') }}">
                                            {{ $diferencia >= 0 ? '+' : '' }}{{ number_format($diferencia, 2, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron artículos') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($articulos->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $articulos->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Barra inferior sticky -->
    <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-lg z-40">
        <div class="px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex-1">
                    <textarea
                        wire:model="observacionesGlobal"
                        rows="1"
                        placeholder="{{ __('Observaciones del conteo...') }}"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm resize-none"
                    ></textarea>
                </div>
                <button
                    wire:click="confirmarProcesar"
                    @if(count($cantidadesFisicas) === 0) disabled @endif
                    class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    {{ __('Procesar Inventario') }} ({{ count($cantidadesFisicas) }})
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Confirmación --}}
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-confirm" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarConfirmacion"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-purple-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Procesar Inventario') }}</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex-shrink-0 p-2 bg-purple-100 dark:bg-purple-900/30 rounded-full">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ __('Se procesarán') }} <span class="font-bold text-purple-600">{{ count($cantidadesFisicas) }}</span> {{ __('artículos con conteo ingresado') }}.
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('? Esta acción no se puede deshacer.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            wire:click="procesarInventario"
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 sm:w-auto transition-colors disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="procesarInventario">{{ __('Procesar Inventario') }}</span>
                            <span wire:loading wire:target="procesarInventario">{{ __('Procesando inventario...') }}</span>
                        </button>
                        <button
                            wire:click="cancelarConfirmacion"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Resultado --}}
    @if($showResultModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-result" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-green-600 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-semibold text-white">{{ __('Resultado del Inventario') }}</h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="space-y-4">
                            <!-- Total procesados -->
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Procesados') }}</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $resultado['procesados'] ?? 0 }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <!-- Sobrantes -->
                                <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $resultado['sobrantes'] ?? 0 }}</p>
                                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">{{ __('sobrantes') }}</p>
                                </div>
                                <!-- Faltantes -->
                                <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $resultado['faltantes'] ?? 0 }}</p>
                                    <p class="text-xs text-red-700 dark:text-red-300 mt-1">{{ __('faltantes') }}</p>
                                </div>
                                <!-- Sin diferencia -->
                                <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $resultado['sin_diferencia'] ?? 0 }}</p>
                                    <p class="text-xs text-green-700 dark:text-green-300 mt-1">{{ __('sin diferencia') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:justify-end sm:px-6">
                        <button
                            wire:click="cerrarResultado"
                            class="inline-flex w-full justify-center rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 sm:w-auto transition-colors"
                        >
                            {{ __('Aceptar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
