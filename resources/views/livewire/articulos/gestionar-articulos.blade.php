<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Listado de Artículos') }}</h2>
                        <!-- Botones de acción - Solo iconos en móviles -->
                        <div class="sm:hidden flex gap-2">
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
            <!-- Búsqueda, estado y toggle filtros -->
            <div class="p-4 sm:p-6">
                {{-- Mobile: búsqueda arriba, selectores + botón abajo --}}
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Código, nombre, categoría...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <div class="flex gap-2">
                        <select
                            wire:model.live="filterTipo"
                            class="flex-1 sm:flex-none rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Tipo') }}</option>
                            <option value="articulo">{{ __('Artículos') }}</option>
                            <option value="materia_prima">{{ __('Materia prima') }}</option>
                        </select>
                        <button
                            wire:click="toggleFilters"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary shrink-0"
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
                                wire:click="abrirDuplicar({{ $articulo->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-indigo-600 text-sm font-medium rounded-md text-indigo-600 hover:bg-indigo-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition-colors duration-150"
                                :title="__('Duplicar artículo')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
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
                            @if($articulo->grupos_opcionales_count > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $articulo->grupos_opcionales_count }} {{ __('Opcionales') }}
                                </span>
                            @endif
                            @if($articulo->tiene_receta_override > 0 || ($articulo->tiene_receta_default > 0 && $articulo->receta_anulada == 0))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    {{ __('Receta') }}
                                </span>
                            @endif
                            @if($articulo->modo_stock_sucursal && $articulo->modo_stock_sucursal !== 'ninguno')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ __('Stock') }}: {{ number_format($articulo->stock_cantidad ?? 0, 2) }}
                                </span>
                            @endif
                        </div>
                        @php
                            $precioEfectivoMobile = $articulo->precio_sucursal !== null ? $articulo->precio_sucursal : ($articulo->precio_base ?? 0);
                        @endphp
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            ${{ number_format($precioEfectivoMobile, 2) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            wire:click="gestionarOpcionales({{ $articulo->id }})"
                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-600 dark:text-indigo-400 dark:hover:bg-indigo-900/20 transition-colors"
                            title="{{ __('Opcionales') }}"
                        >
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            {{ __('Opcionales') }}
                        </button>
                        <button
                            wire:click="editarReceta({{ $articulo->id }})"
                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border border-amber-300 text-amber-600 hover:bg-amber-50 dark:border-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 transition-colors"
                            title="{{ __('Receta') }}"
                        >
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            {{ __('Receta') }}
                        </button>
                        <button
                            wire:click="verHistorial({{ $articulo->id }})"
                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border border-teal-300 text-teal-600 hover:bg-teal-50 dark:border-teal-600 dark:text-teal-400 dark:hover:bg-teal-900/20 transition-colors"
                            title="{{ __('Ver historial') }}"
                        >
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Ver historial') }}
                        </button>
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
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Precio') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Stock') }}</th>
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
                                    @php
                                        $precioEfectivo = $articulo->precio_sucursal !== null ? $articulo->precio_sucursal : ($articulo->precio_base ?? 0);
                                    @endphp
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        ${{ number_format($precioEfectivo, 2) }}
                                    </span>
                                    @if($articulo->precio_sucursal !== null)
                                        <div class="text-xs text-gray-400" title="{{ __('Precio base genérico') }}">
                                            ${{ number_format($articulo->precio_base ?? 0, 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    @if($articulo->modo_stock_sucursal && $articulo->modo_stock_sucursal !== 'ninguno')
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ number_format($articulo->stock_cantidad ?? 0, 2) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1.5">
                                        <button
                                            wire:click="gestionarOpcionales({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-indigo-300 dark:border-indigo-600 text-xs font-medium rounded text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors duration-150"
                                            title="{{ __('Opcionales') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                            @if($articulo->grupos_opcionales_count > 0)
                                                <span class="ml-1">{{ $articulo->grupos_opcionales_count }}</span>
                                            @endif
                                        </button>
                                        <button
                                            wire:click="editarReceta({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-amber-300 dark:border-amber-600 text-xs font-medium rounded text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors duration-150"
                                            title="{{ __('Receta') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                            @if($articulo->tiene_receta_override > 0 || ($articulo->tiene_receta_default > 0 && $articulo->receta_anulada == 0))
                                                <span class="ml-1 w-2 h-2 bg-amber-500 rounded-full"></span>
                                            @endif
                                        </button>
                                        <button
                                            wire:click="verHistorial({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-teal-300 dark:border-teal-600 text-xs font-medium rounded text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors duration-150"
                                            title="{{ __('Ver historial') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </button>
                                        <button
                                            wire:click="edit({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-bcn-primary text-xs font-medium rounded text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                                            title="{{ __('Editar') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="abrirDuplicar({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-indigo-600 text-xs font-medium rounded text-indigo-600 hover:bg-indigo-600 hover:text-white transition-colors duration-150"
                                            title="{{ __('Duplicar') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="confirmarEliminar({{ $articulo->id }})"
                                            class="inline-flex items-center justify-center px-2 py-1.5 border border-red-600 text-xs font-medium rounded text-red-600 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                            title="{{ __('Eliminar') }}"
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
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400" >

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
        <x-bcn-modal
            :title="$editMode ? __('Editar Artículo') : __('Nuevo Artículo')"
            color="bg-bcn-primary"
            maxWidth="5xl"
            onClose="cancel"
            submit="save"
        >
            <x-slot:body>
                <div class="space-y-4">
                                <!-- Nombre (full width) -->
                                <div>
                                    <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                                    <input
                                        type="text"
                                        id="nombre"
                                        wire:model="nombre"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                        placeholder="{{ __('Ej: Coca Cola 500ml') }}"
                                        required
                                    />
                                    @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Categoría (combobox con búsqueda) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</label>
                                        <div class="mt-1 flex">
                                            <div
                                                class="relative w-full"
                                                x-data="{
                                                    open: false,
                                                    search: '',
                                                    highlightIndex: -1,
                                                    categorias: @js($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'color' => $c->color])->values()),
                                                    get filtered() {
                                                        if (!this.search) return this.categorias;
                                                        const terms = this.search.toLowerCase().split(/\s+/);
                                                        return this.categorias.filter(c => {
                                                            const nombre = c.nombre.toLowerCase();
                                                            return terms.every(t => nombre.includes(t));
                                                        });
                                                    },
                                                    select(cat) {
                                                        $wire.set('categoria_id', cat.id);
                                                        this.search = cat.nombre;
                                                        this.open = false;
                                                        this.highlightIndex = -1;
                                                        // Pasar foco al campo código tras seleccionar
                                                        this.$nextTick(() => {
                                                            const codigoInput = document.getElementById('codigo');
                                                            if (codigoInput) { codigoInput.focus(); codigoInput.select(); }
                                                        });
                                                    },
                                                    clear() {
                                                        $wire.set('categoria_id', null);
                                                        this.search = '';
                                                        this.open = false;
                                                        this.highlightIndex = -1;
                                                    },
                                                    scrollToHighlighted() {
                                                        this.$nextTick(() => {
                                                            const dd = this.$refs.catDropdown;
                                                            if (!dd) return;
                                                            const items = dd.querySelectorAll('button');
                                                            if (items[this.highlightIndex]) items[this.highlightIndex].scrollIntoView({ block: 'nearest' });
                                                        });
                                                    },
                                                    handleKey(e) {
                                                        if (!this.open) { this.open = true; return; }
                                                        if (e.key === 'ArrowDown') {
                                                            e.preventDefault();
                                                            this.highlightIndex = Math.min(this.highlightIndex + 1, this.filtered.length - 1);
                                                            this.scrollToHighlighted();
                                                        } else if (e.key === 'ArrowUp') {
                                                            e.preventDefault();
                                                            this.highlightIndex = Math.max(this.highlightIndex - 1, 0);
                                                            this.scrollToHighlighted();
                                                        } else if (e.key === 'Enter') {
                                                            e.preventDefault();
                                                            // Si ya hay categoría seleccionada y el search coincide, saltar al código sin reseleccionar
                                                            const currentId = $wire.get('categoria_id');
                                                            if (currentId && this.highlightIndex < 0) {
                                                                const current = this.categorias.find(c => c.id == currentId);
                                                                if (current && this.search === current.nombre) {
                                                                    this.open = false;
                                                                    this.$nextTick(() => {
                                                                        const codigoInput = document.getElementById('codigo');
                                                                        if (codigoInput) { codigoInput.focus(); codigoInput.select(); }
                                                                    });
                                                                    return;
                                                                }
                                                            }
                                                            const idx = this.highlightIndex >= 0 ? this.highlightIndex : 0;
                                                            if (this.filtered[idx]) {
                                                                this.select(this.filtered[idx]);
                                                            }
                                                        } else if (e.key === 'Escape') {
                                                            this.open = false;
                                                        }
                                                    }
                                                }"
                                                x-init="
                                                    const selId = $wire.get('categoria_id');
                                                    if (selId) {
                                                        const found = categorias.find(c => c.id == selId);
                                                        if (found) search = found.nombre;
                                                    }
                                                    $watch('$wire.categoria_id', (val) => {
                                                        if (val) {
                                                            const found = categorias.find(c => c.id == val);
                                                            if (found) search = found.nombre;
                                                        } else {
                                                            search = '';
                                                        }
                                                    });
                                                "
                                                @categoria-creada.window="
                                                    categorias.push({ id: $event.detail.id, nombre: $event.detail.nombre, color: $event.detail.color });
                                                    categorias.sort((a, b) => a.nombre.localeCompare(b.nombre));
                                                    search = $event.detail.nombre;
                                                "
                                                @click.away="open = false; if (!$wire.get('categoria_id')) search = ''; else { const f = categorias.find(c => c.id == $wire.get('categoria_id')); if (f) search = f.nombre; }"
                                            >
                                                <div class="relative">
                                                    <input
                                                        type="text"
                                                        x-model="search"
                                                        @focus="open = true; highlightIndex = -1;"
                                                        @keydown="handleKey($event)"
                                                        placeholder="{{ __('Buscar categoría...') }}"
                                                        autocomplete="off"
                                                        class="block w-full rounded-l-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-8"
                                                    />
                                                    {{-- Botón limpiar --}}
                                                    <button
                                                        type="button"
                                                        x-show="$wire.get('categoria_id')"
                                                        @click="clear()"
                                                        class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                {{-- Dropdown de resultados --}}
                                                <div
                                                    x-show="open && filtered.length > 0"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="opacity-0 scale-95"
                                                    x-transition:enter-end="opacity-100 scale-100"
                                                    x-transition:leave="transition ease-in duration-75"
                                                    x-transition:leave-start="opacity-100 scale-100"
                                                    x-transition:leave-end="opacity-0 scale-95"
                                                    x-ref="catDropdown"
                                                    class="absolute z-50 mt-1 w-full max-h-48 overflow-auto bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg"
                                                    style="display: none;"
                                                >
                                                    <template x-for="(cat, index) in filtered" :key="cat.id">
                                                        <button
                                                            type="button"
                                                            @click="select(cat)"
                                                            @mouseenter="highlightIndex = index"
                                                            :class="highlightIndex === index ? 'bg-bcn-primary/10 dark:bg-bcn-primary/20' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                                                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-300"
                                                        >
                                                            <span
                                                                class="w-3 h-3 rounded-full flex-shrink-0"
                                                                :style="'background-color: ' + (cat.color || '#9CA3AF')"
                                                            ></span>
                                                            <span x-text="cat.nombre"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                                {{-- Sin resultados --}}
                                                <div
                                                    x-show="open && search && filtered.length === 0"
                                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                                    style="display: none;"
                                                >
                                                    {{ __('Sin resultados') }}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="$toggle('showAltaRapidaCategoria')"
                                                class="flex-shrink-0 inline-flex items-center justify-center px-2 self-stretch bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                title="{{ __('Alta rápida de categoría') }}"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                            </button>
                                        </div>
                                        @error('categoria_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                                        {{-- Mini-formulario alta rápida de categoría --}}
                                        @if($showAltaRapidaCategoria)
                                            <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md space-y-2" x-init="$nextTick(() => $refs.nuevaCategoriaNombre.focus())">
                                                <p class="text-xs font-medium text-blue-700 dark:text-blue-300">{{ __('Nueva categoría') }}</p>
                                                <input
                                                    type="text"
                                                    x-ref="nuevaCategoriaNombre"
                                                    wire:model="nuevaCategoriaNombre"
                                                    wire:keydown.enter.prevent="crearCategoriaRapida"
                                                    placeholder="{{ __('Nombre') }}"
                                                    class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                />
                                                @error('nuevaCategoriaNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                <input
                                                    type="text"
                                                    wire:model="nuevaCategoriaPrefijo"
                                                    wire:keydown.enter.prevent="crearCategoriaRapida"
                                                    placeholder="{{ __('Prefijo (opcional, ej: BEB)') }}"
                                                    class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                />
                                                <div class="flex gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="crearCategoriaRapida"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    >
                                                        {{ __('Crear') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('showAltaRapidaCategoria', false)"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400"
                                                    >
                                                        {{ __('Cancelar') }}
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

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
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se propone automáticamente si la categoría tiene prefijo') }}</p>
                                        @error('codigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Código de Barras -->
                                    <div>
                                        <label for="codigo_barras" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código de barras') }}</label>
                                        <input
                                            type="text"
                                            id="codigo_barras"
                                            wire:model="codigo_barras"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                            placeholder="EAN-13, UPC..."
                                            maxlength="50"
                                        />
                                        @error('codigo_barras') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
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
                                        @error('precio_base') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Descripción -->
                                <div>
                                    <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción') }}</label>
                                    <textarea
                                        id="descripcion"
                                        wire:model="descripcion"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                        placeholder="{{ __('Descripción detallada del artículo...') }}"
                                    ></textarea>
                                    @error('descripcion') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <!-- Toggles -->
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <!-- Materia Prima -->
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
                                        <label for="es_materia_prima" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('Es Materia Prima') }}</label>
                                        <button
                                            type="button"
                                            wire:click="$toggle('es_materia_prima')"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $es_materia_prima ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                            role="switch"
                                            aria-checked="{{ $es_materia_prima ? 'true' : 'false' }}"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $es_materia_prima ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </div>

                                    <!-- IVA Incluido -->
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
                                        <label for="precio_iva_incluido" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('IVA Incluido') }}</label>
                                        <button
                                            type="button"
                                            wire:click="$toggle('precio_iva_incluido')"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $precio_iva_incluido ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                            role="switch"
                                            aria-checked="{{ $precio_iva_incluido ? 'true' : 'false' }}"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $precio_iva_incluido ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </div>

                                    <!-- Pesable -->
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
                                        <label for="pesable" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('Pesable') }}</label>
                                        <button
                                            type="button"
                                            wire:click="$toggle('pesable')"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $pesable ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                            role="switch"
                                            aria-checked="{{ $pesable ? 'true' : 'false' }}"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $pesable ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </div>

                                    <!-- Activo -->
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
                                        <label for="activo" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('Activo') }}</label>
                                        <button
                                            type="button"
                                            wire:click="$toggle('activo')"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $activo ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                            role="switch"
                                            aria-checked="{{ $activo ? 'true' : 'false' }}"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Configuración de stock y sucursal -->
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    @multiSucursal
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                            {{ __('Configuración sucursal activa') }}
                                        </label>
                                    @endmultiSucursal
                                    <div class="grid grid-cols-1 sm:grid-cols-2 {{ es_multi_sucursal() ? 'lg:grid-cols-3' : '' }} gap-4">
                                        <!-- Precio sucursal (solo multi-sucursal) -->
                                        @multiSucursal
                                        <div>
                                            <label for="precio_sucursal" class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Precio sucursal') }}</label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 dark:text-gray-400 text-xs">$</span>
                                                </div>
                                                <input
                                                    type="number"
                                                    id="precio_sucursal"
                                                    wire:model="precio_sucursal"
                                                    step="0.01"
                                                    min="0"
                                                    class="block w-full pl-7 pr-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                                    placeholder="{{ __('Usar genérico') }}"
                                                />
                                            </div>
                                            @error('precio_sucursal') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        @endmultiSucursal

                                        <!-- Modo stock -->
                                        <div>
                                            <label for="modo_stock" class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Modo de stock') }}</label>
                                            <select
                                                id="modo_stock"
                                                wire:model="modo_stock"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                            >
                                                <option value="ninguno">{{ __('Ninguno') }}</option>
                                                <option value="unitario">{{ __('Unitario') }}</option>
                                                <option value="receta">{{ __('Receta') }}</option>
                                            </select>
                                            @error('modo_stock') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <!-- Vendible -->
                                        <div class="flex items-end">
                                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between w-full">
                                                <label for="vendible" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('Vendible') }}</label>
                                                <button
                                                    type="button"
                                                    wire:click="$toggle('vendible')"
                                                    class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $vendible ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                                    role="switch"
                                                    aria-checked="{{ $vendible ? 'true' : 'false' }}"
                                                >
                                                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $vendible ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Etiquetas -->
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
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
                                                placeholder="{{ __('Buscar grupo o etiqueta...') }}"
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
            </x-slot:body>

            <x-slot:footer>
                <button
                    type="button"
                    @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm"
                >
                    {{ $editMode ? __('Actualizar') : __('Crear') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de confirmación de eliminación --}}
    @if($showDeleteModal)
        <x-bcn-modal
            :show="$showDeleteModal"
            :title="__('Eliminar Artículo')"
            color="bg-red-600"
            maxWidth="md"
            onClose="cancelarEliminar"
        >
            <x-slot:body>
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
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
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button"
                        wire:click="eliminar"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    {{ __('Eliminar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Opcionales --}}
    @if($showOpcionalesModal)
        <x-bcn-modal
            :show="$showOpcionalesModal"
            :title="$mostrandoAgregarGrupo ? __('Agregar grupo a') . ': ' . $opcionalesArticuloNombre : __('Opcionales de') . ': ' . $opcionalesArticuloNombre"
            color="bg-bcn-primary"
            maxWidth="4xl"
            onClose="cancelarOpcionales"
            zIndex="z-[55]"
        >
            <x-slot:body>
                @if(!$mostrandoAgregarGrupo)
                    <div class="flex justify-end mb-4">
                        <button
                            type="button"
                            wire:click="abrirAgregarGrupo"
                            class="inline-flex items-center px-3 py-2 bg-bcn-primary border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-opacity-90 transition"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Agregar Grupo') }}
                        </button>
                    </div>
                @endif

                {{-- Vista: Agregar Grupo (inline) --}}
                @if($mostrandoAgregarGrupo)
                    <div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="busquedaGrupo"
                            placeholder="{{ __('Buscar grupo por nombre...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            autofocus
                        />
                    </div>

                    <div class="mt-3 max-h-[55vh] overflow-y-auto space-y-2 pr-1">
                        @forelse($this->gruposDisponibles as $grupo)
                            <button
                                type="button"
                                wire:click="asignarGrupo({{ $grupo['id'] }})"
                                class="w-full text-left px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-bcn-primary hover:bg-bcn-primary/5 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $grupo['nombre'] }}</span>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs {{ $grupo['tipo'] === 'seleccionable' ? 'text-blue-600 dark:text-blue-400' : 'text-purple-600 dark:text-purple-400' }}">
                                                {{ $grupo['tipo'] === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                            </span>
                                            @if($grupo['obligatorio'])
                                                <span class="text-xs text-red-600 dark:text-red-400">{{ __('Obligatorio') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $grupo['opcionales_count'] }} {{ __('opciones') }}</span>
                                        <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                </div>
                            </button>
                        @empty
                            <div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                {{ __('No hay grupos disponibles para asignar') }}
                            </div>
                        @endforelse
                    </div>

                {{-- Vista: Grupos Asignados --}}
                @else
                    <div class="max-h-[55vh] overflow-y-auto space-y-3 pr-1">
                        @if(count($gruposAsignados) > 0)
                            @foreach($gruposAsignados as $index => $grupo)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $grupo['nombre'] }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $grupo['tipo'] === 'seleccionable' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                {{ $grupo['tipo'] === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                            </span>
                                            @if($grupo['obligatorio'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Obligatorio') }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" wire:click="moverGrupoArriba({{ $index }})" @if($index === 0) disabled @endif class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors {{ $index === 0 ? 'opacity-30 cursor-not-allowed' : 'text-gray-500 dark:text-gray-400' }}" title="{{ __('Subir') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            </button>
                                            <button type="button" wire:click="moverGrupoAbajo({{ $index }})" @if($index === count($gruposAsignados) - 1) disabled @endif class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors {{ $index === count($gruposAsignados) - 1 ? 'opacity-30 cursor-not-allowed' : 'text-gray-500 dark:text-gray-400' }}" title="{{ __('Bajar') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <button type="button" wire:click="confirmarDesasignar({{ $grupo['grupo_id'] }}, '{{ addslashes($grupo['nombre']) }}')" class="text-red-500 hover:text-red-700 p-1 ml-1" title="{{ __('Quitar grupo') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    @if(count($grupo['opciones']) > 0)
                                        <div class="px-4 py-2 space-y-1">
                                            @foreach($grupo['opciones'] as $opIdx => $opcion)
                                                <div class="flex items-center gap-3 text-sm py-1.5 {{ !$opcion['activo'] ? 'opacity-50' : '' }}">
                                                    {{-- Toggle activo --}}
                                                    <button
                                                        type="button"
                                                        wire:click="actualizarOpcion({{ $index }}, {{ $opIdx }}, 'activo', null)"
                                                        class="relative inline-flex flex-shrink-0 h-5 w-9 border-2 border-transparent rounded-full cursor-pointer transition-colors {{ $opcion['activo'] ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
                                                        title="{{ $opcion['activo'] ? __('Desactivar') : __('Activar') }}"
                                                    >
                                                        <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition {{ $opcion['activo'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                                    </button>
                                                    {{-- Nombre --}}
                                                    <span class="flex-1 text-gray-700 dark:text-gray-300 truncate">{{ $opcion['nombre'] }}</span>
                                                    {{-- Disponible --}}
                                                    <button
                                                        type="button"
                                                        wire:click="actualizarOpcion({{ $index }}, {{ $opIdx }}, 'disponible', null)"
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium transition-colors {{ $opcion['disponible'] ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' }}"
                                                        title="{{ $opcion['disponible'] ? __('Marcar como no disponible') : __('Marcar como disponible') }}"
                                                    >
                                                        {{ $opcion['disponible'] ? __('Disp.') : __('No disp.') }}
                                                    </button>
                                                    {{-- Precio extra --}}
                                                    <div class="w-24 relative">
                                                        <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-400 text-xs pointer-events-none">+$</span>
                                                        <input
                                                            type="number"
                                                            wire:change="actualizarOpcion({{ $index }}, {{ $opIdx }}, 'precio_extra', $event.target.value)"
                                                            value="{{ $opcion['precio_extra'] }}"
                                                            step="0.01"
                                                            min="0"
                                                            class="w-full pl-7 pr-1 py-1 text-xs text-right rounded border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        />
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 italic">
                                            {{ __('Sin opciones') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                </svg>
                                <p class="mt-2 text-sm">{{ __('Este artículo no tiene grupos opcionales asignados') }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ __('Haz click en "Agregar Grupo" para asignar uno') }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </x-slot:body>

            <x-slot:footer>
                @if($mostrandoAgregarGrupo)
                    <button type="button" wire:click="cancelarAgregarGrupo" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        {{ __('Volver') }}
                    </button>
                @else
                    <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                        {{ __('Cerrar') }}
                    </button>
                @endif
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Submodal Confirmar Desasignación --}}
    @if($showDesasignarModal)
        <x-bcn-modal
            :show="$showDesasignarModal"
            :title="__('Quitar grupo opcional')"
            color="bg-red-600"
            maxWidth="lg"
            onClose="cancelarDesasignar"
            zIndex="z-[60]"
        >
            <x-slot:body>
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('¿Estás seguro de quitar el grupo') }} <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ $nombreGrupoADesasignar }}"</span>?
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            {{ __('Se eliminará de TODAS las sucursales, incluyendo precios y configuraciones personalizadas.') }}
                        </p>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="desasignarGrupo" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm">
                    {{ __('Quitar grupo') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Receta --}}
    @if($showRecetaModal)
        <x-bcn-modal
            :show="$showRecetaModal"
            :title="__('Receta de') . ': ' . $recetaArticuloNombre"
            color="bg-bcn-primary"
            maxWidth="2xl"
            onClose="cancelarReceta"
            zIndex="z-[55]"
        >
            <x-slot:body>
                @include('livewire.articulos._receta-editor')
            </x-slot:body>

            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                @if($recetaId)
                    <button type="button" wire:click="confirmarEliminarReceta" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm">
                        {{ __('Eliminar') }}
                    </button>
                @endif
                <button type="button" wire:click="guardarReceta" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ $recetaId ? __('Actualizar') : __('Guardar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Submodal Confirmar Eliminar Receta --}}
    @if($showDeleteRecetaModal)
        <x-bcn-modal
            :show="$showDeleteRecetaModal"
            :title="__('Eliminar receta')"
            color="bg-red-600"
            maxWidth="lg"
            onClose="cancelarEliminarReceta"
            zIndex="z-[60]"
        >
            <x-slot:body>
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('¿Estás seguro de eliminar la receta de este artículo? Se eliminarán todos los ingredientes asociados.') }}
                        </p>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="eliminarReceta" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm">
                    {{ __('Eliminar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    <!-- Modal Historial de Precios -->
    @if($showHistorialModal)
        @php
            $historial = $this->getHistorial();
            $articuloHistorial = $historialArticuloId ? \App\Models\Articulo::find($historialArticuloId) : null;
        @endphp
        <x-bcn-modal
            :show="$showHistorialModal"
            :title="__('Historial de precios') . ' - ' . ($articuloHistorial?->nombre ?? '')"
            color="bg-bcn-primary"
            maxWidth="4xl"
            onClose="cerrarHistorial"
            zIndex="z-[55]"
        >
            <x-slot:body>
                @if(empty($historial))
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="mt-2 text-sm">{{ __('Sin cambios registrados') }}</p>
                    </div>
                @else
                    <!-- Vista móvil: cards -->
                    <div class="sm:hidden space-y-3">
                        @foreach($historial as $registro)
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $registro['fecha'] }}</span>
                                    @php
                                        $origenClasses = match($registro['origen']) {
                                            'articulo_crear' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'articulo_editar' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'override_sucursal' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                            'restablecer_sucursal' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                            'masivo_global' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'masivo_sucursal' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
                                        };
                                        $origenLabel = match($registro['origen']) {
                                            'articulo_crear' => __('Crear'),
                                            'articulo_editar' => __('Editar'),
                                            'override_sucursal' => __('Override'),
                                            'restablecer_sucursal' => __('Restablecer'),
                                            'masivo_global' => __('Masivo global'),
                                            'masivo_sucursal' => __('Masivo sucursal'),
                                            default => $registro['origen'],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $origenClasses }}">
                                        {{ $origenLabel }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 line-through">${{ number_format($registro['precio_anterior'], 2) }}</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ number_format($registro['precio_nuevo'], 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $registro['usuario'] }}</span>
                                    <span>{{ $registro['sucursal'] ?? __('Genérico') }}</span>
                                </div>
                                @if($registro['detalle'])
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">{{ $registro['detalle'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Vista desktop: tabla -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Fecha') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Usuario') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Anterior') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Nuevo') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Origen') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Sucursal') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Detalle') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($historial as $registro)
                                    @php
                                        $origenClasses = match($registro['origen']) {
                                            'articulo_crear' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'articulo_editar' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'override_sucursal' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                            'restablecer_sucursal' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                            'masivo_global' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'masivo_sucursal' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
                                        };
                                        $origenLabel = match($registro['origen']) {
                                            'articulo_crear' => __('Crear'),
                                            'articulo_editar' => __('Editar'),
                                            'override_sucursal' => __('Override'),
                                            'restablecer_sucursal' => __('Restablecer'),
                                            'masivo_global' => __('Masivo global'),
                                            'masivo_sucursal' => __('Masivo sucursal'),
                                            default => $registro['origen'],
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ $registro['fecha'] }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ $registro['usuario'] }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400 line-through">${{ number_format($registro['precio_anterior'], 2) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">${{ number_format($registro['precio_nuevo'], 2) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $origenClasses }}">
                                                {{ $origenLabel }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ $registro['sucursal'] ?? __('Genérico') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $registro['detalle'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Duplicar Artículo --}}
    @if($showDuplicarModal)
        <x-bcn-modal
            :show="$showDuplicarModal"
            :title="__('Duplicar artículo')"
            color="bg-indigo-600"
            maxWidth="lg"
            onClose="cancelarDuplicar"
            submit="duplicarArticulo"
        >
            <x-slot:body>
                <div class="space-y-4">
                    {{-- Aviso del origen --}}
                    <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg p-3">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-indigo-800 dark:text-indigo-200">
                                {{ __('Se duplicará') }} <strong>{{ $duplicarOrigenNombre }}</strong>
                                {{ __('con todos sus datos: precio, sucursales, recetas, opcionales, etiquetas y precios en listas. El stock arranca en 0.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Nombre --}}
                    <div>
                        <label for="duplicar_nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                        <input
                            type="text"
                            id="duplicar_nombre"
                            wire:model="duplicarNombre"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
                            required
                        />
                        @error('duplicarNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Categoría (combobox con búsqueda) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</label>
                        <div
                            class="relative mt-1"
                            x-data="{
                                open: false,
                                search: '',
                                highlightIndex: -1,
                                categorias: @js($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'color' => $c->color])->values()),
                                get filtered() {
                                    if (!this.search) return this.categorias;
                                    const terms = this.search.toLowerCase().split(/\s+/);
                                    return this.categorias.filter(c => {
                                        const nombre = c.nombre.toLowerCase();
                                        return terms.every(t => nombre.includes(t));
                                    });
                                },
                                select(cat) {
                                    $wire.set('duplicarCategoriaId', cat.id);
                                    this.search = cat.nombre;
                                    this.open = false;
                                    this.highlightIndex = -1;
                                    this.$nextTick(() => {
                                        const codigoInput = document.getElementById('duplicar_codigo');
                                        if (codigoInput) { codigoInput.focus(); codigoInput.select(); }
                                    });
                                },
                                clear() {
                                    $wire.set('duplicarCategoriaId', null);
                                    this.search = '';
                                    this.open = false;
                                    this.highlightIndex = -1;
                                },
                                scrollToHighlighted() {
                                    this.$nextTick(() => {
                                        const dd = this.$refs.catDropdown;
                                        if (!dd) return;
                                        const items = dd.querySelectorAll('button');
                                        if (items[this.highlightIndex]) items[this.highlightIndex].scrollIntoView({ block: 'nearest' });
                                    });
                                },
                                handleKey(e) {
                                    if (!this.open) { this.open = true; return; }
                                    if (e.key === 'ArrowDown') {
                                        e.preventDefault();
                                        this.highlightIndex = Math.min(this.highlightIndex + 1, this.filtered.length - 1);
                                        this.scrollToHighlighted();
                                    } else if (e.key === 'ArrowUp') {
                                        e.preventDefault();
                                        this.highlightIndex = Math.max(this.highlightIndex - 1, 0);
                                        this.scrollToHighlighted();
                                    } else if (e.key === 'Enter') {
                                        e.preventDefault();
                                        const currentId = $wire.get('duplicarCategoriaId');
                                        if (currentId && this.highlightIndex < 0) {
                                            const current = this.categorias.find(c => c.id == currentId);
                                            if (current && this.search === current.nombre) {
                                                this.open = false;
                                                this.$nextTick(() => {
                                                    const codigoInput = document.getElementById('duplicar_codigo');
                                                    if (codigoInput) { codigoInput.focus(); codigoInput.select(); }
                                                });
                                                return;
                                            }
                                        }
                                        const idx = this.highlightIndex >= 0 ? this.highlightIndex : 0;
                                        if (this.filtered[idx]) {
                                            this.select(this.filtered[idx]);
                                        }
                                    } else if (e.key === 'Escape') {
                                        this.open = false;
                                    }
                                }
                            }"
                            x-init="
                                const selId = $wire.get('duplicarCategoriaId');
                                if (selId) {
                                    const found = categorias.find(c => c.id == selId);
                                    if (found) search = found.nombre;
                                }
                                $watch('$wire.duplicarCategoriaId', (val) => {
                                    if (val) {
                                        const found = categorias.find(c => c.id == val);
                                        if (found) search = found.nombre;
                                    } else {
                                        search = '';
                                    }
                                });
                            "
                            @click.away="open = false; if (!$wire.get('duplicarCategoriaId')) search = ''; else { const f = categorias.find(c => c.id == $wire.get('duplicarCategoriaId')); if (f) search = f.nombre; }"
                        >
                            <div class="relative">
                                <input
                                    type="text"
                                    x-model="search"
                                    @focus="open = true; highlightIndex = -1;"
                                    @keydown="handleKey($event)"
                                    placeholder="{{ __('Buscar categoría...') }}"
                                    autocomplete="off"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50 pr-8"
                                />
                                <button
                                    type="button"
                                    x-show="$wire.get('duplicarCategoriaId')"
                                    @click="clear()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            {{-- Dropdown de resultados --}}
                            <div
                                x-show="open && filtered.length > 0"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                x-ref="catDropdown"
                                class="absolute z-50 mt-1 w-full max-h-48 overflow-auto bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg"
                                style="display: none;"
                            >
                                <template x-for="(cat, index) in filtered" :key="cat.id">
                                    <button
                                        type="button"
                                        @click="select(cat)"
                                        @mouseenter="highlightIndex = index"
                                        :class="highlightIndex === index ? 'bg-indigo-500/10 dark:bg-indigo-500/20' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                                        class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-300"
                                    >
                                        <span
                                            class="w-3 h-3 rounded-full flex-shrink-0"
                                            :style="'background-color: ' + (cat.color || '#9CA3AF')"
                                        ></span>
                                        <span x-text="cat.nombre"></span>
                                    </button>
                                </template>
                            </div>
                            {{-- Sin resultados --}}
                            <div
                                x-show="open && search && filtered.length === 0"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                style="display: none;"
                            >
                                {{ __('Sin resultados') }}
                            </div>
                        </div>
                        @error('duplicarCategoriaId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Código --}}
                    <div>
                        <label for="duplicar_codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código') }} *</label>
                        <input
                            type="text"
                            id="duplicar_codigo"
                            wire:model="duplicarCodigo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se propone automáticamente según la categoría') }}</p>
                        @error('duplicarCodigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-500 sm:w-auto sm:text-sm">
                    {{ __('Duplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
