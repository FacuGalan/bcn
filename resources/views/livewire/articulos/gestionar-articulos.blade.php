<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Gestión de Artículos') }}</h2>
                        <!-- Botones de acción - Solo iconos en móviles -->
                        <div class="sm:hidden flex gap-2">
                            <a
                                href="{{ route('configuracion.articulos-sucursal') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                :title="__('Configurar por sucursal')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </a>
                            <a
                                href="{{ route('articulos.cambio-masivo-precios') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                :title="__('Cambio masivo de precios')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </a>
                            <button
                                wire:click="create"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                :title="__('Crear nuevo artículo')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los productos y servicios de tu negocio') }}</p>
                </div>
                <!-- Botones de acciones - Desktop -->
                <div class="hidden sm:flex gap-3">
                    <a
                        href="{{ route('configuracion.articulos-sucursal') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        :title="__('Configurar artículos por sucursal')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        {{ __('Por Sucursal') }}
                    </a>
                    <a
                        href="{{ route('articulos.cambio-masivo-precios') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        :title="__('Cambio masivo de precios')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('Cambiar Precios') }}
                    </a>
                    <button
                        wire:click="create"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        :title="__('Crear nuevo artículo')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nuevo Artículo') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <!-- Botón de filtros (solo móvil) -->
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="toggleFilters"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-bcn-primary transition-colors"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($search || $filterStatus !== 'all' || $filterSucursal !== 'all' || $filterCategory !== 'all')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
                            </span>
                        @endif
                    </span>
                    <svg
                        class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Contenedor de filtros -->
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Búsqueda -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Código, nombre, marca...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro de categoría -->
                    <div>
                        <label for="filterCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría') }}</label>
                        <select
                            id="filterCategory"
                            wire:model.live="filterCategory"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todas') }}</option>
                            <option value="none">{{ __('Sin categoría') }}</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro de sucursal -->
                    <div>
                        <label for="filterSucursal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursal') }}</label>
                        <select
                            id="filterSucursal"
                            wire:model.live="filterSucursal"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todas') }}</option>
                            @foreach($sucursalesUsuario as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro de estado -->
                    <div>
                        <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                        <select
                            id="filterStatus"
                            wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todos') }}</option>
                            <option value="active">{{ __('Activos') }}</option>
                            <option value="inactive">{{ __('Inactivos') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($articulos as $articulo)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Código:') }} {{ $articulo->codigo }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                wire:click="edit({{ $articulo->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                :title="__('Editar artículo')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                wire:click="confirmarEliminar({{ $articulo->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                :title="__('Eliminar artículo')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-3">
                        <div class="flex flex-wrap gap-2">
                            @if($articulo->categoriaModel)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background-color: {{ $articulo->categoriaModel->color }}20; color: {{ $articulo->categoriaModel->color }}; border: 1px solid {{ $articulo->categoriaModel->color }}40;">
                                    {{ $articulo->categoriaModel->nombre }}
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $articulo->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $articulo->activo ? __('Activo') : __('Inactivo') }}
                            </span>
                            @if($articulo->sucursales->count() > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800" title="{{ $articulo->sucursales->pluck('nombre')->join(', ') }}">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    {{ $articulo->sucursales->count() }}/{{ $sucursales->count() }}
                                </span>
                            @endif
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            $@precio($articulo->precio_base ?? 0)
                        </span>
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

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $articulos->links() }}
            </div>
        </div>

        <!-- Tabla de artículos (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Código') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Categoría') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Precio Base') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Sucursales') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($articulos as $articulo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->codigo }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($articulo->categoriaModel)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              style="background-color: {{ $articulo->categoriaModel->color }}20; color: {{ $articulo->categoriaModel->color }}; border: 1px solid {{ $articulo->categoriaModel->color }}40;">
                                            {{ $articulo->categoriaModel->nombre }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Sin categoría') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        $@precio($articulo->precio_base ?? 0)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($articulo->sucursales->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($articulo->sucursales as $sucursal)
                                                @php
                                                    $initials = collect(explode(' ', $sucursal->nombre))
                                                        ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                                                        ->join('');
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200" title="{{ $sucursal->nombre }}">
                                                    {{ $initials }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        wire:click="toggleStatus({{ $articulo->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 {{ $articulo->activo ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                    >
                                        <span class="sr-only">{{ $articulo->activo ? __('Desactivar') : __('Activar') }} {{ __('artículo') }}</span>
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $articulo->activo ? 'translate-x-5' : 'translate-x-0' }}"
                                        ></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-400">
                                        {{ $articulo->activo ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            wire:click="edit({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                            :title="__('Editar artículo')"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            {{ __('Editar') }}
                                        </button>
                                        <button
                                            wire:click="confirmarEliminar({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                            :title="__('Eliminar artículo')"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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

            <!-- Paginación Desktop -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $articulos->links() }}
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar artículo -->
    @if($showModal)
        <div
            x-data="{ show: @entangle('showModal').live }"
            x-show="show"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <!-- Overlay -->
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div
                    @click="show = false; $wire.cancel()"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    aria-hidden="true"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full mt-3 sm:mt-0 text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                        {{ $editMode ? __('Editar Artículo') : __('Nuevo Artículo') }}
                                    </h3>

                                    <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <!-- Código -->
                                            <div>
                                                <label for="codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código') }} *</label>
                                                <input
                                                    type="text"
                                                    id="codigo"
                                                    wire:model="codigo"
                                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    placeholder="Ej: ART-001"
                                                    required
                                                />
                                                @error('codigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Nombre -->
                                            <div>
                                                <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                                                <input
                                                    type="text"
                                                    id="nombre"
                                                    wire:model="nombre"
                                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    placeholder="Ej: Coca Cola 500ml"
                                                    required
                                                />
                                                @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Unidad de Medida -->
                                            <div>
                                                <label for="unidad_medida" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Unidad de Medida') }} *</label>
                                                <select
                                                    id="unidad_medida"
                                                    wire:model="unidad_medida"
                                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    required
                                                >
                                                    <option value="unidad">{{ __('Unidad') }}</option>
                                                    <option value="kg">{{ __('Kilogramo (kg)') }}</option>
                                                    <option value="gr">{{ __('Gramo (gr)') }}</option>
                                                    <option value="lt">{{ __('Litro (lt)') }}</option>
                                                    <option value="ml">{{ __('Mililitro (ml)') }}</option>
                                                    <option value="mt">{{ __('Metro (mt)') }}</option>
                                                    <option value="cm">{{ __('Centímetro (cm)') }}</option>
                                                    <option value="caja">{{ __('Caja') }}</option>
                                                    <option value="paquete">{{ __('Paquete') }}</option>
                                                </select>
                                                @error('unidad_medida') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Categoría -->
                                            <div>
                                                <label for="categoria_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</label>
                                                <select
                                                    id="categoria_id"
                                                    wire:model="categoria_id"
                                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                >
                                                    <option value="">{{ __('Sin categoría') }}</option>
                                                    @foreach($categorias as $categoria)
                                                        <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                @error('categoria_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Tipo IVA -->
                                            <div>
                                                <label for="tipo_iva_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tipo de IVA') }} *</label>
                                                <select
                                                    id="tipo_iva_id"
                                                    wire:model="tipo_iva_id"
                                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    required
                                                >
                                                    <option value="">{{ __('Seleccionar...') }}</option>
                                                    @foreach($tiposIva as $tipoIva)
                                                        <option value="{{ $tipoIva->id }}">{{ $tipoIva->nombre }} ({{ $tipoIva->porcentaje }}%)</option>
                                                    @endforeach
                                                </select>
                                                @error('tipo_iva_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Precio Base -->
                                            <div>
                                                <label for="precio_base" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ __('Precio Base') }} *
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-normal ml-1">({{ __('fallback global') }})</span>
                                                </label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        id="precio_base"
                                                        wire:model="precio_base"
                                                        step="0.01"
                                                        min="0"
                                                        class="block w-full pl-7 pr-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        placeholder="0.00"
                                                        required
                                                    />
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ __('Precio predeterminado usado cuando no hay precio específico configurado') }}
                                                </p>
                                                @error('precio_base') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                        </div>

                                        <!-- Descripción -->
                                        <div>
                                            <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción') }}</label>
                                            <textarea
                                                id="descripcion"
                                                wire:model="descripcion"
                                                rows="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                :placeholder="__('Descripción detallada del artículo...')"
                                            ></textarea>
                                            @error('descripcion') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <!-- Checkboxes -->
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="es_servicio"
                                                    wire:model="es_servicio"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                                />
                                                <label for="es_servicio" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Es Servicio') }}</label>
                                            </div>

                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="controla_stock"
                                                    wire:model="controla_stock"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                                />
                                                <label for="controla_stock" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Controla Stock') }}</label>
                                            </div>

                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="precio_iva_incluido"
                                                    wire:model="precio_iva_incluido"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                                />
                                                <label for="precio_iva_incluido" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('IVA Incluido') }}</label>
                                            </div>

                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="activo"
                                                    wire:model="activo"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                                />
                                                <label for="activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Activo') }}</label>
                                            </div>
                                        </div>

                                        <!-- Disponibilidad en Sucursales y Etiquetas -->
                                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            <!-- Sucursales -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                                    {{ __('Disponibilidad en Sucursales') }}
                                                </label>
                                                <div class="grid grid-cols-1 gap-2">
                                                    @foreach($sucursales as $sucursal)
                                                        <div class="flex items-center p-2 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                                            <input
                                                                type="checkbox"
                                                                id="sucursal_{{ $sucursal->id }}"
                                                                wire:model="sucursales_seleccionadas"
                                                                value="{{ $sucursal->id }}"
                                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 h-4 w-4 dark:bg-gray-600"
                                                            />
                                                            <label for="sucursal_{{ $sucursal->id }}" class="ml-3 block text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                                                {{ $sucursal->nombre }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Etiquetas -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                    {{ __('Etiquetas') }}
                                                    @if(count($etiquetas_seleccionadas) > 0)
                                                        <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                                            {{ count($etiquetas_seleccionadas) }} {{ __('seleccionadas') }}
                                                        </span>
                                                    @endif
                                                </label>
                                                <!-- Buscador de etiquetas -->
                                                <div class="relative mb-2">
                                                    <input
                                                        type="text"
                                                        wire:model.live.debounce.300ms="busquedaEtiqueta"
                                                        :placeholder="__('Buscar grupo o etiqueta...')"
                                                        class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                                    >
                                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                </div>
                                                <div class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                                                    @forelse($gruposEtiquetas as $grupo)
                                                        @if($grupo->etiquetas->count() > 0)
                                                            <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                                                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2 sticky top-0">
                                                                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $grupo->color }};"></div>
                                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                                                </div>
                                                                <div class="p-2 space-y-1">
                                                                    @foreach($grupo->etiquetas as $etiqueta)
                                                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                                            <input
                                                                                type="checkbox"
                                                                                wire:click="toggleEtiqueta({{ $etiqueta->id }})"
                                                                                {{ in_array($etiqueta->id, $etiquetas_seleccionadas) ? 'checked' : '' }}
                                                                                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-600"
                                                                            >
                                                                            <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $etiqueta->color ?? $grupo->color }};"></div>
                                                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @empty
                                                        <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                                            {{ __('No hay etiquetas disponibles') }}
                                                        </div>
                                                    @endforelse
                                                </div>
                                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ __('Asigna etiquetas para clasificar este artículo') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editMode ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                @click="show = false; $wire.cancel()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de confirmación de eliminación --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 wire:click="cancelarEliminar"></div>

            {{-- Modal --}}
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    {{-- Header --}}
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            {{-- Icono --}}
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            {{-- Contenido --}}
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    {{ __('Eliminar articulo') }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('¿Estas seguro de eliminar el articulo') }} <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ $nombreArticuloAEliminar }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('Esta accion no eliminara permanentemente los datos, pero el articulo dejara de estar disponible en el sistema.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Acciones --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button"
                                wire:click="eliminar"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('Eliminar') }}
                        </button>
                        <button type="button"
                                wire:click="cancelarEliminar"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
