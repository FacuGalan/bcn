<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-2 sm:gap-4">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg sm:text-2xl font-bold text-bcn-secondary dark:text-white truncate">{{ __('Artículos por Sucursal') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden sm:block">{{ __('Configura disponibilidad, modo de stock y vendibilidad por sucursal') }}</p>
                </div>
                <a
                    href="{{ route('articulos.gestionar') }}"
                    wire:navigate
                    class="flex-shrink-0 inline-flex items-center justify-center p-2 sm:px-4 sm:py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                >
                    <svg class="w-5 h-5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    <span class="hidden sm:inline">{{ __('Volver') }}</span>
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-3 sm:p-6">
                <div class="flex gap-2 sm:gap-3">
                    <div class="flex-1 min-w-0">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Código, nombre...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <select
                        wire:model.live="filterTipo"
                        class="hidden sm:block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Tipo') }}</option>
                        <option value="articulo">{{ __('Artículos') }}</option>
                        <option value="materia_prima">{{ __('Materia prima') }}</option>
                    </select>
                    <button
                        wire:click="toggleFilters"
                        class="flex-shrink-0 inline-flex items-center px-2.5 sm:px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary"
                    >
                        <svg class="w-5 h-5 {{ $showFilters ? 'text-bcn-primary' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        @if(count($categoriasSeleccionadas) > 0 || count($etiquetasSeleccionadasFiltro) > 0)
                            <span class="ml-1 px-1.5 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                {{ count($categoriasSeleccionadas) + count($etiquetasSeleccionadasFiltro) }}
                            </span>
                        @endif
                    </button>
                </div>

                {{-- Filtro tipo en mobile (debajo del buscador) --}}
                <div class="sm:hidden mt-2">
                    <select
                        wire:model.live="filterTipo"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Todos los tipos') }}</option>
                        <option value="articulo">{{ __('Artículos') }}</option>
                        <option value="materia_prima">{{ __('Materia prima') }}</option>
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" wire:click="selectAll" class="text-xs sm:text-sm text-bcn-primary hover:text-opacity-80 font-medium">{{ __('Seleccionar todos') }}</button>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <button type="button" wire:click="deselectAll" class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white font-medium">{{ __('Deseleccionar todos') }}</button>
                    <span class="hidden sm:inline ml-auto text-xs text-gray-500 dark:text-gray-400 italic">{{ __('Los cambios se guardan automáticamente') }}</span>
                </div>
            </div>

            <!-- Filtros expandibles (categorías y etiquetas) -->
            @if($showFilters)
                <div class="px-4 sm:px-6 pb-4 sm:pb-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Categorías -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Categorías') }}
                                @if(count($categoriasSeleccionadas) > 0)
                                    <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                        {{ count($categoriasSeleccionadas) }} {{ __('seleccionadas') }}
                                    </span>
                                @endif
                            </label>
                            <div class="relative mb-2">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaCategoriaFiltro"
                                    placeholder="{{ __('Buscar categoría...') }}"
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 space-y-1">
                                @forelse($categoriasFiltro as $cat)
                                    <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model.live="categoriasSeleccionadas"
                                            value="{{ $cat->id }}"
                                            class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                        />
                                        <span class="ml-2 flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $cat->color }}"></span>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $cat->nombre }}</span>
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
                                @if(count($etiquetasSeleccionadasFiltro) > 0)
                                    <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                        {{ count($etiquetasSeleccionadasFiltro) }} {{ __('seleccionadas') }}
                                    </span>
                                @endif
                            </label>
                            <div class="relative mb-2">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaEtiquetaFiltro"
                                    placeholder="{{ __('Buscar grupo o etiqueta...') }}"
                                    class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                @forelse($gruposEtiquetasFiltro as $grupo)
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
                                                            wire:model.live="etiquetasSeleccionadasFiltro"
                                                            value="{{ $etiqueta->id }}"
                                                            class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
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

        @if(sucursal_activa())
            <!-- Vista móvil (cards) -->
            <div class="sm:hidden space-y-3" wire:key="sucursal-{{ sucursal_activa() }}-mobile">
                @forelse($articulos as $articulo)
                    @php $config = $articulosConfig[$articulo->id] ?? ['activo' => true, 'modo_stock' => 'ninguno', 'vendible' => true]; @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 {{ !$config['activo'] ? 'opacity-50' : '' }}">
                        <!-- Header: nombre, código, badges -->
                        <div class="flex items-start gap-2.5 px-3 py-3 pb-2">
                            <input type="checkbox" wire:click="toggleArticulo({{ $articulo->id }})" @checked($config['activo']) class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary h-5 w-5 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</span>
                                    @if($articulo->es_materia_prima)
                                        <span class="flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">{{ __('MP') }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</span>
                                    @if($articulo->categoriaModel)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium" style="background-color: {{ $articulo->categoriaModel->color ?? '#e5e7eb' }}20; color: {{ $articulo->categoriaModel->color ?? '#6b7280' }};">
                                            {{ $articulo->categoriaModel->nombre }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if($config['modo_stock'] !== 'ninguno')
                                @php $stockActual = $articulo->stocks->first(); @endphp
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-[10px] uppercase text-gray-400 dark:text-gray-500 font-medium">{{ __('Stock') }}</div>
                                    <div class="text-sm font-bold {{ ($stockActual->cantidad ?? 0) <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                        {{ number_format($stockActual->cantidad ?? 0, 2, ',', '.') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Controles -->
                        <div class="flex items-center gap-2 px-3 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
                            <select wire:change="cambiarModoStock({{ $articulo->id }}, $event.target.value)" class="text-[11px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1 pl-1.5 pr-6 flex-shrink-0">
                                <option value="ninguno" @selected($config['modo_stock'] === 'ninguno')>{{ __('Sin stock') }}</option>
                                <option value="unitario" @selected($config['modo_stock'] === 'unitario')>{{ __('Unitario') }}</option>
                                <option value="receta" @selected($config['modo_stock'] === 'receta')>{{ __('Por receta') }}</option>
                            </select>
                            <label class="flex items-center gap-1 flex-shrink-0">
                                <input type="checkbox" wire:click="toggleVendible({{ $articulo->id }})" @checked($config['vendible']) class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary h-3.5 w-3.5" />
                                <span class="text-[11px] text-gray-600 dark:text-gray-400">{{ __('Vend.') }}</span>
                            </label>
                            <button wire:click="abrirConfiguracion({{ $articulo->id }})" class="ml-auto flex-shrink-0 inline-flex items-center p-1.5 border border-gray-300 dark:border-gray-600 text-xs rounded text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        <p class="text-sm">{{ __('No se encontraron artículos') }}</p>
                    </div>
                @endforelse
                @if($articulos->hasPages())
                    <div class="mt-4">{{ $articulos->links() }}</div>
                @endif
            </div>

            <!-- Vista desktop (tabla) -->
            <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" wire:key="sucursal-{{ sucursal_activa() }}-desktop">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Código') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Categoría') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Modo Stock') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Stock') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Vendible') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($articulos as $articulo)
                                @php $config = $articulosConfig[$articulo->id] ?? ['activo' => true, 'modo_stock' => 'ninguno', 'vendible' => true]; @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ !$config['activo'] ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" wire:click="toggleArticulo({{ $articulo->id }})" @checked($config['activo']) class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary h-5 w-5" />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $articulo->codigo }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $articulo->nombre }}
                                            @if($articulo->es_materia_prima)
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">{{ __('MP') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($articulo->categoriaModel)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $articulo->categoriaModel->color ?? '#e5e7eb' }}20; color: {{ $articulo->categoriaModel->color ?? '#6b7280' }};">
                                                {{ $articulo->categoriaModel->nombre }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <select wire:change="cambiarModoStock({{ $articulo->id }}, $event.target.value)" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1 pl-2 pr-7 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            <option value="ninguno" @selected($config['modo_stock'] === 'ninguno')>{{ __('Ninguno') }}</option>
                                            <option value="unitario" @selected($config['modo_stock'] === 'unitario')>{{ __('Unitario') }}</option>
                                            <option value="receta" @selected($config['modo_stock'] === 'receta')>{{ __('Por receta') }}</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($config['modo_stock'] !== 'ninguno')
                                            @php $stockActual = $articulo->stocks->first(); @endphp
                                            <span class="text-sm font-semibold {{ ($stockActual->cantidad ?? 0) <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                                {{ number_format($stockActual->cantidad ?? 0, 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <input type="checkbox" wire:click="toggleVendible({{ $articulo->id }})" @checked($config['vendible']) class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <button wire:click="abrirConfiguracion({{ $articulo->id }})" class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="{{ __('Configurar opcionales y receta') }}">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                            {{ __('Detalle') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <p>{{ __('No se encontraron artículos') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($articulos->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">{{ $articulos->links() }}</div>
                @endif
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 sm:p-12 text-center">
                <svg class="mx-auto h-12 w-12 sm:h-16 sm:w-16 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                <h3 class="mt-3 sm:mt-4 text-base sm:text-lg font-medium text-gray-900 dark:text-white">{{ __('Selecciona una sucursal') }}</h3>
                <p class="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ __('Selecciona una sucursal en el menú superior para configurar sus artículos disponibles') }}</p>
            </div>
        @endif
    </div>

    <!-- Modal Configuración Detallada -->
    @if($showConfigModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cerrarConfiguracion" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-t-lg sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full max-h-[95vh]">
                    <div class="bg-white dark:bg-gray-800 px-3 pt-4 pb-3 sm:px-6 sm:pt-5 sm:pb-4">
                        {{-- Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3 sm:mb-4 flex-shrink-0">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white truncate">
                                {{ __('Configuración de') }}: {{ $configArticuloNombre }}
                            </h3>
                            <button wire:click="abrirRecetaOverride" class="self-start sm:self-auto flex-shrink-0 inline-flex items-center px-3 py-1.5 border border-amber-500 text-xs font-medium rounded text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                {{ __('Receta de sucursal') }}
                            </button>
                        </div>

                        {{-- Contenido scrollable --}}
                        <div class="max-h-[60vh] overflow-y-auto space-y-3 sm:space-y-4 pr-1 -mr-1">
                            @if(count($configGrupos) > 0)
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Grupos Opcionales') }}</h4>
                                @foreach($configGrupos as $grupo)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                        <div class="flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-50 dark:bg-gray-700">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <input type="checkbox" wire:click="toggleGrupoActivo({{ $grupo['asignacion_id'] }})" @checked($grupo['activo']) class="flex-shrink-0 rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary" />
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white truncate {{ !$grupo['activo'] ? 'line-through opacity-50' : '' }}">{{ $grupo['nombre'] }}</span>
                                                <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $grupo['tipo'] === 'seleccionable' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                    {{ $grupo['tipo'] === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                                </span>
                                            </div>
                                            <button wire:click="restablecerDefaults({{ $grupo['asignacion_id'] }})" class="flex-shrink-0 ml-2 p-1 text-gray-500 hover:text-bcn-primary" title="{{ __('Restablecer valores por defecto') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            </button>
                                        </div>
                                        @if(count($grupo['opciones']) > 0 && $grupo['activo'])
                                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach($grupo['opciones'] as $opcion)
                                                    <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 text-sm">
                                                        <input type="checkbox" wire:click="toggleOpcionActivo({{ $opcion['opcion_id'] }})" @checked($opcion['activo']) class="flex-shrink-0 rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary" title="{{ __('Activo') }}" />
                                                        <span class="text-gray-700 dark:text-gray-300 truncate text-xs sm:text-sm flex-1 min-w-0 {{ !$opcion['activo'] ? 'line-through opacity-50' : '' }}">{{ $opcion['nombre'] }}</span>
                                                        <div class="relative w-20 sm:w-24 flex-shrink-0">
                                                            <span class="absolute inset-y-0 left-0 pl-1.5 sm:pl-2 flex items-center text-gray-500 text-xs">$</span>
                                                            <input type="number" value="{{ $opcion['precio_extra'] }}" wire:change="actualizarPrecioOpcion({{ $opcion['opcion_id'] }}, $event.target.value)" step="0.01" min="0" class="w-full pl-4 sm:pl-5 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white py-1 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                                        </div>
                                                        <button wire:click="toggleOpcionDisponible({{ $opcion['opcion_id'] }})" class="flex-shrink-0 px-1.5 sm:px-2 py-1 rounded text-[10px] sm:text-xs font-medium {{ $opcion['disponible'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}" title="{{ $opcion['disponible'] ? __('Disponible') : __('Agotado') }}">
                                                            {{ $opcion['disponible'] ? __('Disp.') : __('Agotado') }}
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                                    <p class="text-sm">{{ __('Este artículo no tiene grupos opcionales asignados en esta sucursal') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-3 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="cerrarConfiguracion" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Receta Override -->
    @if($showRecetaModal)
        <div class="fixed inset-0 z-[55] overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelarReceta" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-t-lg sm:rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[95vh]">
                    <div class="bg-white dark:bg-gray-800 px-3 pt-4 pb-3 sm:px-6 sm:pt-5 sm:pb-4">
                        <h3 class="text-base sm:text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2 truncate">
                            {{ __('Receta de') }}: {{ $configArticuloNombre }}
                        </h3>
                        @if(!$recetaEsOverride && $recetaId === null && count($recetaIngredientes) > 0)
                            <p class="text-xs text-blue-600 dark:text-blue-400 mb-3 sm:mb-4">{{ __('Mostrando receta default. Al guardar se creará un override para esta sucursal.') }}</p>
                        @elseif(!$recetaEsOverride && count($recetaIngredientes) === 0)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 sm:mb-4">{{ __('No hay receta default. Agregue ingredientes para crear un override.') }}</p>
                        @endif

                        <div class="max-h-[60vh] overflow-y-auto pr-1 -mr-1">
                            @include('livewire.articulos._receta-editor')
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-3 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" wire:click="cancelarReceta" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto">
                            {{ __('Cancelar') }}
                        </button>
                        @if($recetaId && $recetaEsOverride)
                            <button type="button" wire:click="confirmarEliminarRecetaOverride" class="w-full inline-flex justify-center rounded-md border border-red-600 shadow-sm px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-600 hover:text-white sm:w-auto transition-colors">
                                {{ __('Restablecer default') }}
                            </button>
                        @endif
                        <button type="button" wire:click="guardarRecetaOverride" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-sm font-medium text-white hover:bg-opacity-90 sm:w-auto">
                            {{ $recetaEsOverride ? __('Actualizar Override') : __('Crear Override') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminar Override -->
    @if($showDeleteRecetaModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminarReceta"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Restablecer receta default') }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Se eliminará la receta personalizada de esta sucursal y se usará la receta default.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button" wire:click="eliminarRecetaOverride" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">{{ __('Eliminar override') }}</button>
                        <button type="button" wire:click="cancelarEliminarReceta" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">{{ __('Cancelar') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
