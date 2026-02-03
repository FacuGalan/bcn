<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Configurar Artículos por Sucursal') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Selecciona una sucursal y configura qué artículos están disponibles en ella') }}</p>
                </div>
                <!-- Botón Volver -->
                <a
                    href="{{ route('articulos.gestionar') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Volver') }}
                </a>
            </div>
        </div>

        <!-- Selector de sucursal y búsqueda -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Selector de Sucursal -->
                    <div>
                        <label for="sucursal_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursal') }}</label>
                        <select
                            id="sucursal_id"
                            wire:model.live="sucursal_id"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="">{{ __('Seleccionar sucursal...') }}</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro de Categoría -->
                    <div>
                        <label for="filterCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría') }}</label>
                        <select
                            id="filterCategory"
                            wire:model.live="filterCategory"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todas las categorías') }}</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                            @endforeach
                            <option value="none">{{ __('Sin categoría') }}</option>
                        </select>
                    </div>

                    <!-- Búsqueda -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar artículo') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Código, nombre...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                </div>

                @if($sucursal_id)
                    <!-- Acciones rápidas -->
                    <div class="flex gap-3 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            wire:click="selectAll"
                            class="text-sm text-bcn-primary hover:text-opacity-80 font-medium"
                        >
                            {{ __('Seleccionar todos') }}
                        </button>
                        <button
                            type="button"
                            wire:click="deselectAll"
                            class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white font-medium"
                        >
                            {{ __('Deseleccionar todos') }}
                        </button>
                        <span class="ml-auto text-xs text-gray-500 dark:text-gray-400 italic">
                            {{ __('Los cambios se guardan automáticamente') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        @if($sucursal_id)
            <!-- Lista de artículos -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" wire:key="sucursal-{{ $sucursal_id }}">
                <!-- Vista móvil (cards) -->
                <div class="sm:hidden">
                    @forelse($articulos as $articulo)
                        <div class="border-b border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-start gap-3">
                                <!-- Checkbox -->
                                <div class="flex-shrink-0 pt-1">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleArticulo({{ $articulo->id }})"
                                        @checked(in_array($articulo->id, $articulos_activos))
                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary h-5 w-5"
                                    />
                                </div>

                                <!-- Información del artículo -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</h3>
                                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ __('Código') }}: {{ $articulo->codigo }}</p>
                                        </div>
                                    </div>

                                    <!-- Badges -->
                                    @if($articulo->categoriaModel)
                                        <div class="mt-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: {{ $articulo->categoriaModel->color }}20; color: {{ $articulo->categoriaModel->color }}; border: 1px solid {{ $articulo->categoriaModel->color }}40;">
                                                {{ $articulo->categoriaModel->nombre }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="mt-2">{{ __('No se encontraron artículos') }}</p>
                        </div>
                    @endforelse
                </div>

                <!-- Vista desktop (tabla) -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="w-12 px-6 py-3">
                                    <!-- Checkbox header -->
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ __('Código') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ __('Nombre') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ __('Categoría') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($articulos as $articulo)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleArticulo({{ $articulo->id }})"
                                            @checked(in_array($articulo->id, $articulos_activos))
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary h-5 w-5"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->codigo }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</div>
                                        @if($articulo->descripcion)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($articulo->descripcion, 60) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($articulo->categoriaModel)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: {{ $articulo->categoriaModel->color }}20; color: {{ $articulo->categoriaModel->color }}; border: 1px solid {{ $articulo->categoriaModel->color }}40;">
                                                {{ $articulo->categoriaModel->nombre }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="mt-2">{{ __('No se encontraron artículos') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                @if($articulos->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $articulos->links() }}
                    </div>
                @endif
            </div>
        @else
            <!-- Mensaje cuando no hay sucursal seleccionada -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ __('Selecciona una sucursal') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Selecciona una sucursal en el selector de arriba para configurar sus artículos disponibles') }}
                </p>
            </div>
        @endif
    </div>
</div>
