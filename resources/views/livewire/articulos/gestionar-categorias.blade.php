<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Gestión de Categorías') }}</h2>
                        <!-- Botones móvil -->
                        <div class="sm:hidden flex gap-2">
                            {{-- Menú desplegable con acciones secundarias --}}
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                    title="{{ __('Más acciones') }}"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-transition
                                    x-cloak
                                    class="absolute right-0 z-20 mt-2 w-56 origin-top-right rounded-md bg-white dark:bg-gray-700 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                >
                                    <div class="py-1">
                                        <button
                                            type="button"
                                            wire:click="openPlantillaModal"
                                            @click="open = false"
                                            class="flex w-full items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                        >
                                            <svg class="w-4 h-4 mr-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            {{ __('Descargar plantilla') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="openImportModal"
                                            @click="open = false"
                                            class="flex w-full items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                        >
                                            <svg class="w-4 h-4 mr-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            {{ __('Importar') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button
                                wire:click="create"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                :title="__('Crear nueva categoría')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra las categorías de artículos del sistema') }}</p>
                </div>
                <!-- Botones Desktop -->
                <div class="hidden sm:flex gap-3">
                    <button
                        wire:click="openPlantillaModal"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Descargar plantilla Excel') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {{ __('Plantilla') }}
                    </button>
                    <button
                        wire:click="openImportModal"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Importar desde Excel') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {{ __('Importar') }}
                    </button>
                    <button
                        wire:click="create"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        :title="__('Crear nueva categoría')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nueva Categoría') }}
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
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($search || $filterStatus !== 'all')
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Búsqueda -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Nombre de categoría...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro de estado -->
                    <div>
                        <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                        <select
                            id="filterStatus"
                            wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todas') }}</option>
                            <option value="active">{{ __('Activas') }}</option>
                            <option value="inactive">{{ __('Inactivas') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($categorias as $categoria)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center flex-1">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center" style="background-color: {{ $categoria->color }}20;">
                                @if($categoria->icono)
                                    <x-dynamic-component :component="$categoria->icono" class="h-6 w-6" style="color: {{ $categoria->color }};" />
                                @else
                                    <div class="w-6 h-6 rounded-full" style="background-color: {{ $categoria->color }};"></div>
                                @endif
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $categoria->nombre }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $categoria->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $categoria->activo ? __('Activa') : __('Inactiva') }}
                                    </span>
                                    @if($categoria->prefijo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            {{ $categoria->prefijo }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                wire:click="edit({{ $categoria->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                :title="__('Editar categoría')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                wire:click="confirmarEliminar({{ $categoria->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                :title="__('Eliminar categoría')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron categorías') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $categorias->links() }}
            </div>
        </div>

        <!-- Tabla de categorías (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Categoría') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Prefijo') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Color') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Estado') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Acciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($categorias as $categoria)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center" style="background-color: {{ $categoria->color }}20;">
                                            @if($categoria->icono)
                                                <x-dynamic-component :component="$categoria->icono" class="h-5 w-5" style="color: {{ $categoria->color }};" />
                                            @else
                                                <div class="w-5 h-5 rounded-full" style="background-color: {{ $categoria->color }};"></div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $categoria->nombre }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($categoria->prefijo)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            {{ $categoria->prefijo }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded border border-gray-300 dark:border-gray-600" style="background-color: {{ $categoria->color }};"></div>
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-300 font-mono">{{ $categoria->color }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        wire:click="toggleStatus({{ $categoria->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $categoria->activo ? 'bg-green-600' : 'bg-gray-300' }}"
                                    >
                                        <span class="sr-only">{{ $categoria->activo ? __('Desactivar') : __('Activar') }} {{ __('categoría') }}</span>
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $categoria->activo ? 'translate-x-5' : 'translate-x-0' }}"
                                        ></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $categoria->activo ? __('Activa') : __('Inactiva') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            wire:click="edit({{ $categoria->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                            :title="__('Editar categoría')"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            {{ __('Editar') }}
                                        </button>
                                        <button
                                            wire:click="confirmarEliminar({{ $categoria->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                            :title="__('Eliminar categoría')"
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
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron categorías') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $categorias->links() }}
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar categoría -->
    @if($showModal)
        <x-bcn-modal
            :title="$editMode ? __('Editar Categoría') : __('Nueva Categoría')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cancel"
            submit="save"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <!-- Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                        <input
                            type="text"
                            id="nombre"
                            wire:model="nombre"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                            :placeholder="__('Ej: Bebidas, Alimentos, Electrónica...')"
                            required
                        />
                        @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Prefijo -->
                    <div>
                        <label for="prefijo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Prefijo') }}</label>
                        <input
                            type="text"
                            id="prefijo"
                            wire:model="prefijo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 uppercase"
                            placeholder="{{ __('Ej: MAT, BEB') }}"
                            maxlength="10"
                            style="text-transform: uppercase;"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se usará para generar códigos automáticos en artículos (ej: MAT0001)') }}</p>
                        @error('prefijo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Color -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Color') }} *</label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                wire:model.live="color"
                                class="h-12 w-24 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                            />
                            <input
                                type="text"
                                wire:model.live="color"
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 font-mono text-sm"
                                placeholder="#3B82F6"
                                maxlength="7"
                            />
                        </div>
                        @error('color') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Icono -->
                    <div x-data="{ openCategory: null }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Icono (opcional)') }}
                            @if($icono)
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">- {{ $icono }}</span>
                            @endif
                        </label>

                        {{-- Icono seleccionado actualmente --}}
                        @if($icono)
                            <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-md flex items-center gap-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Seleccionado:') }}</span>
                                <div class="h-10 w-10 flex items-center justify-center rounded border-2 border-bcn-primary bg-bcn-primary bg-opacity-10 text-bcn-primary">
                                    <x-dynamic-component :component="$icono" class="h-5 w-5" />
                                </div>
                                <button
                                    type="button"
                                    wire:click="$set('icono', '')"
                                    class="ml-auto text-xs text-red-600 hover:text-red-800"
                                >
                                    {{ __('Quitar') }}
                                </button>
                            </div>
                        @endif

                        <div class="max-h-80 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700">
                            @php
                                $categorias_iconos = [
                                    __('Gastronomía & Bebidas') => [
                                        'food.pizza', 'food.hamburguesa', 'food.hot-dog', 'food.helado',
                                        'food.galleta', 'food.manzana', 'food.zanahoria', 'food.chile',
                                        'food.pescado', 'food.pollo', 'food.pan', 'food.queso',
                                        'food.huevo', 'food.limon', 'food.camaron', 'food.tocino',
                                        'food.arroz', 'food.cafe', 'food.copa-vino', 'food.botella-vino',
                                        'food.cerveza', 'food.champan', 'food.whiskey', 'food.martini',
                                        'food.coctel', 'food.taza-cafe', 'food.licuado',
                                    ],
                                    __('Comercio & Ventas') => [
                                        'icon.tag', 'icon.shopping-bag', 'icon.shopping-cart',
                                        'icon.credit-card', 'icon.dollar-sign',
                                    ],
                                    __('Celebración') => [
                                        'icon.gift', 'icon.heart', 'icon.star', 'icon.sparkles', 'icon.bolt',
                                    ],
                                    __('Hogar') => [
                                        'icon.house', 'icon.building', 'icon.lightbulb', 'icon.key',
                                    ],
                                    __('Naturaleza') => [
                                        'icon.sun', 'icon.moon', 'icon.cloud',
                                    ],
                                    __('Tecnología') => [
                                        'icon.mobile', 'icon.desktop', 'icon.tv', 'icon.camera',
                                        'icon.printer', 'icon.wifi',
                                    ],
                                    __('Herramientas') => [
                                        'icon.wrench', 'icon.scissors', 'icon.pencil', 'icon.paintbrush',
                                    ],
                                    __('Organización') => [
                                        'icon.folder', 'icon.folder-open', 'icon.box-archive',
                                        'icon.inbox', 'icon.clipboard', 'icon.file', 'icon.bookmark',
                                        'icon.cube', 'icon.table-cells', 'icon.layer-group',
                                    ],
                                    __('Transporte') => [
                                        'icon.truck', 'icon.location-dot', 'icon.map', 'icon.globe',
                                    ],
                                    __('Otros') => [
                                        'icon.music', 'icon.volume-high', 'icon.microphone',
                                        'icon.shield-halved', 'icon.lock', 'icon.eye',
                                        'icon.comment', 'icon.envelope', 'icon.phone',
                                        'icon.chart-column', 'icon.calculator',
                                        'icon.bell', 'icon.clock', 'icon.calendar',
                                        'icon.users', 'icon.user',
                                        'icon.image', 'icon.film', 'icon.play',
                                        'icon.flag', 'icon.book-open', 'icon.briefcase', 'icon.gear',
                                    ],
                                ];
                            @endphp

                            @foreach($categorias_iconos as $categoria => $iconos)
                                <div class="border-b border-gray-200 dark:border-gray-600 last:border-b-0">
                                    {{-- Header de categoría (colapsable) --}}
                                    <button
                                        type="button"
                                        @click="openCategory = openCategory === '{{ $categoria }}' ? null : '{{ $categoria }}'"
                                        class="w-full px-3 py-2 flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    >
                                        <span>{{ $categoria }} ({{ count($iconos) }})</span>
                                        <svg class="w-4 h-4 transition-transform" :class="openCategory === '{{ $categoria }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    {{-- Iconos (solo se renderizan cuando está abierto) --}}
                                    <div x-show="openCategory === '{{ $categoria }}'" x-collapse class="px-3 pb-3">
                                        <div class="grid grid-cols-6 gap-2">
                                            @foreach($iconos as $iconoNombre)
                                                <button
                                                    type="button"
                                                    wire:click="$set('icono', '{{ $iconoNombre }}')"
                                                    class="h-10 w-10 flex items-center justify-center rounded border-2 transition-all {{ $icono === $iconoNombre ? 'border-bcn-primary bg-bcn-primary bg-opacity-10 text-bcn-primary' : 'border-gray-200 dark:border-gray-600 hover:border-bcn-primary hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                                                    title="{{ $iconoNombre }}"
                                                >
                                                    <x-dynamic-component :component="$iconoNombre" class="h-5 w-5" />
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('icono') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Estado activo -->
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="activo"
                            wire:model="activo"
                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                        />
                        <label for="activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Categoría activa') }}</label>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    type="button"
                    @click="close()"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:w-auto sm:text-sm"
                >
                    {{ $editMode ? __('Actualizar') : __('Crear') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de confirmación de eliminación --}}
    @if($showDeleteModal)
        <x-bcn-modal
            :title="__('Eliminar categoria')"
            color="bg-red-600"
            maxWidth="md"
            onClose="cancelarEliminar"
        >
            <x-slot:body>
                <div class="sm:flex sm:items-start">
                    {{-- Icono --}}
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    {{-- Contenido --}}
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('¿Estas seguro de eliminar la categoria') }} <span class="font-semibold text-gray-700 dark:text-gray-300">"{{ $nombreCategoriaAEliminar }}"</span>?
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                {{ __('Esta accion no eliminara permanentemente los datos, pero la categoria dejara de estar disponible en el sistema.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto transition-colors">
                    {{ __('Cancelar') }}
                </button>
                <button type="button"
                        wire:click="eliminar"
                        class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    {{ __('Eliminar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de selección de Plantilla --}}
    @if($showPlantillaModal)
        <x-bcn-modal
            :title="__('Descargar plantilla Excel')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="closePlantillaModal"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Elegí qué tipo de plantilla querés descargar:') }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Opción: Plantilla vacía --}}
                        <button
                            type="button"
                            wire:click="descargarPlantilla(false)"
                            class="group flex flex-col items-start p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-bcn-primary hover:bg-bcn-primary/5 dark:hover:bg-bcn-primary/10 focus:outline-none focus:ring-2 focus:ring-bcn-primary text-left transition-all"
                        >
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 group-hover:bg-bcn-primary group-hover:text-white text-gray-600 dark:text-gray-300 mb-3 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ __('Plantilla vacía') }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Solo los encabezados y filas de ejemplo. Ideal para cargar categorías nuevas desde cero.') }}</p>
                        </button>

                        {{-- Opción: Con datos actuales --}}
                        <button
                            type="button"
                            wire:click="descargarPlantilla(true)"
                            class="group flex flex-col items-start p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-bcn-primary hover:bg-bcn-primary/5 dark:hover:bg-bcn-primary/10 focus:outline-none focus:ring-2 focus:ring-bcn-primary text-left transition-all"
                        >
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 group-hover:bg-bcn-primary group-hover:text-white text-gray-600 dark:text-gray-300 mb-3 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                </svg>
                            </div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ __('Con datos actuales') }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Incluye todas las categorías del sistema. Útil para editar en masa o respaldar.') }}</p>
                        </button>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 text-xs text-amber-800 dark:text-amber-300">
                        <strong>{{ __('Importante:') }}</strong> {{ __('La columna ID (en gris) es gestionada por el sistema. No la modifiques: permite actualizar una categoría aunque le cambies el nombre.') }}
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    type="button"
                    @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de Importación --}}
    @if($showImportModal)
        <x-bcn-modal
            :title="__('Importar Categorías')"
            color="bg-bcn-primary"
            maxWidth="2xl"
            onClose="closeImportModal"
        >
            <x-slot:body>
                <div class="space-y-4">
                    @if(!$importacionPreview && !$importacionProcesada)
                        {{-- Estado 1: selección de archivo --}}
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-medium mb-1">{{ __('Formato del archivo:') }}</p>
                                    <ul class="list-disc list-inside space-y-1 text-xs">
                                        <li>{{ __('Descargá la plantilla haciendo clic en "Plantilla"') }}</li>
                                        <li>{{ __('Columnas: ID (no modificar), Nombre (obligatorio) y Prefijo (opcional, máx. 10 caracteres)') }}</li>
                                        <li>{{ __('Con ID: se actualiza la categoría (permite renombrarla)') }}</li>
                                        <li>{{ __('Sin ID: se crea, o se actualiza el prefijo si el nombre ya existe') }}</li>
                                        <li>{{ __('Al continuar verás una previsualización antes de aplicar los cambios') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Archivo Excel') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="file"
                                wire:model="archivoImportacion"
                                accept=".xlsx,.xls,.csv"
                                class="block w-full text-sm text-gray-500 dark:text-gray-400
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-md file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-bcn-primary file:text-white
                                    hover:file:bg-opacity-90
                                    file:cursor-pointer cursor-pointer"
                            />
                            @error('archivoImportacion')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <div wire:loading wire:target="archivoImportacion" class="mt-2 text-sm text-gray-500">
                                {{ __('Cargando archivo...') }}
                            </div>
                        </div>
                    @else
                        {{-- Estados 2 (preview) y 3 (procesado): mismas stats --}}
                        <div class="space-y-4">
                            @if($importacionPreview)
                                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 text-sm text-amber-800 dark:text-amber-300 flex items-start">
                                    <svg class="h-5 w-5 text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <div>
                                        <p class="font-medium">{{ __('Esto es una previsualización') }}</p>
                                        <p class="text-xs mt-0.5">{{ __('Todavía no se aplicó ningún cambio. Revisá los resultados y confirmá para aplicar.') }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    @if(($importacionResultado['creadas'] ?? 0) + ($importacionResultado['actualizadas'] ?? 0) + ($importacionResultado['sin_cambios'] ?? 0) > 0)
                                        <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @else
                                        <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    @endif
                                </div>
                            @endif

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $importacionResultado['creadas'] ?? 0 }}</p>
                                    <p class="text-xs text-green-700 dark:text-green-300">{{ $importacionPreview ? __('Se crearán') : __('Creadas') }}</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $importacionResultado['actualizadas'] ?? 0 }}</p>
                                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ $importacionPreview ? __('Se actualizarán') : __('Actualizadas') }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-900/40 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $importacionResultado['sin_cambios'] ?? 0 }}</p>
                                    <p class="text-xs text-gray-700 dark:text-gray-300">{{ __('Sin cambios') }}</p>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ count($importacionResultado['errores'] ?? []) }}</p>
                                    <p class="text-xs text-red-700 dark:text-red-300">{{ __('Errores') }}</p>
                                </div>
                            </div>

                            @if(count($importacionResultado['errores'] ?? []) > 0)
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 max-h-40 overflow-y-auto">
                                    <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-2">{{ __('Errores encontrados:') }}</p>
                                    <ul class="text-xs text-red-600 dark:text-red-400 space-y-1">
                                        @foreach($importacionResultado['errores'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </x-slot:body>

            <x-slot:footer>
                @if($importacionPreview)
                    {{-- Estado 2: preview --}}
                    <button
                        type="button"
                        wire:click="volverASeleccion"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Volver') }}
                    </button>
                    <button
                        type="button"
                        wire:click="confirmarImportacion"
                        wire:loading.attr="disabled"
                        wire:target="confirmarImportacion"
                        @if(($importacionResultado['creadas'] ?? 0) + ($importacionResultado['actualizadas'] ?? 0) === 0) disabled @endif
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="confirmarImportacion" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="confirmarImportacion" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ __('Confirmar importación') }}
                    </button>
                @else
                    <button
                        type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                    >
                        {{ $importacionProcesada ? __('Cerrar') : __('Cancelar') }}
                    </button>
                    @if(!$importacionProcesada)
                        {{-- Estado 1: selección --}}
                        <button
                            type="button"
                            wire:click="previsualizarImportacion"
                            wire:loading.attr="disabled"
                            wire:target="previsualizarImportacion,archivoImportacion"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            <svg wire:loading wire:target="previsualizarImportacion" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg wire:loading.remove wire:target="previsualizarImportacion" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{ __('Continuar') }}
                        </button>
                    @endif
                @endif
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
