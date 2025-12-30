<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary flex items-center h-10 sm:h-auto">Gestión de Categorías</h2>
                        <!-- Botón Nueva Categoría - Solo icono en móviles -->
                        <button
                            wire:click="create"
                            class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                            title="Crear nueva categoría"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">Administra las categorías de artículos del sistema</p>
                </div>
                <!-- Botón Nueva Categoría - Desktop -->
                <button
                    wire:click="create"
                    class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                    title="Crear nueva categoría"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nueva Categoría
                </button>
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
                        Filtros
                        @if($search || $filterStatus !== 'all')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                Activos
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
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Nombre de categoría..."
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro de estado -->
                    <div>
                        <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                        <select
                            id="filterStatus"
                            wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">Todas</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
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
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $categoria->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $categoria->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                wire:click="edit({{ $categoria->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                title="Editar categoría"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                wire:click="confirmarEliminar({{ $categoria->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                title="Eliminar categoría"
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
                    <p class="mt-2 text-sm">No se encontraron categorías</p>
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
                                Categoría
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Color
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Acciones
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
                                        <span class="sr-only">{{ $categoria->activo ? 'Desactivar' : 'Activar' }} categoría</span>
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $categoria->activo ? 'translate-x-5' : 'translate-x-0' }}"
                                        ></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $categoria->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            wire:click="edit({{ $categoria->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                            title="Editar categoría"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </button>
                                        <button
                                            wire:click="confirmarEliminar({{ $categoria->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                            title="Eliminar categoría"
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
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    <p class="mt-2">No se encontraron categorías</p>
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

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full mt-3 sm:mt-0 text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                        {{ $editMode ? 'Editar Categoría' : 'Nueva Categoría' }}
                                    </h3>

                                    <div class="space-y-4">
                                        <!-- Nombre -->
                                        <div>
                                            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre *</label>
                                            <input
                                                type="text"
                                                id="nombre"
                                                wire:model="nombre"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                placeholder="Ej: Bebidas, Alimentos, Electrónica..."
                                                required
                                            />
                                            @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <!-- Color -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Color *</label>
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
                                                Icono (opcional)
                                                @if($icono)
                                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">- {{ $icono }}</span>
                                                @endif
                                            </label>

                                            {{-- Icono seleccionado actualmente --}}
                                            @if($icono)
                                                <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-md flex items-center gap-2">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">Seleccionado:</span>
                                                    <div class="h-10 w-10 flex items-center justify-center rounded border-2 border-bcn-primary bg-bcn-primary bg-opacity-10 text-bcn-primary">
                                                        <x-dynamic-component :component="$icono" class="h-5 w-5" />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('icono', '')"
                                                        class="ml-auto text-xs text-red-600 hover:text-red-800"
                                                    >
                                                        Quitar
                                                    </button>
                                                </div>
                                            @endif

                                            <div class="max-h-80 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700">
                                                @php
                                                    $categorias_iconos = [
                                                        'Gastronomía & Bebidas' => [
                                                            'food.pizza', 'food.hamburguesa', 'food.hot-dog', 'food.helado',
                                                            'food.galleta', 'food.manzana', 'food.zanahoria', 'food.chile',
                                                            'food.pescado', 'food.pollo', 'food.pan', 'food.queso',
                                                            'food.huevo', 'food.limon', 'food.camaron', 'food.tocino',
                                                            'food.arroz', 'food.cafe', 'food.copa-vino', 'food.botella-vino',
                                                            'food.cerveza', 'food.champan', 'food.whiskey', 'food.martini',
                                                            'food.coctel', 'food.taza-cafe', 'food.licuado',
                                                        ],
                                                        'Comercio & Ventas' => [
                                                            'icon.tag', 'icon.shopping-bag', 'icon.shopping-cart',
                                                            'icon.credit-card', 'icon.dollar-sign',
                                                        ],
                                                        'Celebración' => [
                                                            'icon.gift', 'icon.heart', 'icon.star', 'icon.sparkles', 'icon.bolt',
                                                        ],
                                                        'Hogar' => [
                                                            'icon.house', 'icon.building', 'icon.lightbulb', 'icon.key',
                                                        ],
                                                        'Naturaleza' => [
                                                            'icon.sun', 'icon.moon', 'icon.cloud',
                                                        ],
                                                        'Tecnología' => [
                                                            'icon.mobile', 'icon.desktop', 'icon.tv', 'icon.camera',
                                                            'icon.printer', 'icon.wifi',
                                                        ],
                                                        'Herramientas' => [
                                                            'icon.wrench', 'icon.scissors', 'icon.pencil', 'icon.paintbrush',
                                                        ],
                                                        'Organización' => [
                                                            'icon.folder', 'icon.folder-open', 'icon.box-archive',
                                                            'icon.inbox', 'icon.clipboard', 'icon.file', 'icon.bookmark',
                                                            'icon.cube', 'icon.table-cells', 'icon.layer-group',
                                                        ],
                                                        'Transporte' => [
                                                            'icon.truck', 'icon.location-dot', 'icon.map', 'icon.globe',
                                                        ],
                                                        'Otros' => [
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
                                            <label for="activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Categoría activa</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editMode ? 'Actualizar' : 'Crear' }}
                            </button>
                            <button
                                type="button"
                                @click="show = false; $wire.cancel()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancelar
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
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            {{-- Contenido --}}
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    Eliminar categoria
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ¿Estas seguro de eliminar la categoria <span class="font-semibold text-gray-700 dark:text-gray-300">"{{ $nombreCategoriaAEliminar }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Esta accion no eliminara permanentemente los datos, pero la categoria dejara de estar disponible en el sistema.
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
                            Eliminar
                        </button>
                        <button type="button"
                                wire:click="cancelarEliminar"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
