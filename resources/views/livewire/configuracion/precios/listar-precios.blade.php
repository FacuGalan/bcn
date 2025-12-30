<div class="py-4">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">Listas de Precios</h2>
                        {{-- Boton Nueva Lista - Solo icono en moviles --}}
                        <a href="{{ route('configuracion.precios.nuevo') }}"
                           wire:navigate
                           class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                           title="Crear nueva lista">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </a>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">Gestiona listas de precios con ajustes porcentuales y condiciones</p>
                </div>
                {{-- Boton Nueva Lista - Desktop --}}
                <a href="{{ route('configuracion.precios.nuevo') }}"
                   wire:navigate
                   class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva Lista
                </a>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            {{-- Boton de filtros (solo movil) --}}
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="$toggle('showFilters')"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filtros
                        @if($busqueda || $sucursalFiltro || $activoFiltro !== 'todos' || $esListaBaseFiltro !== '')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                Activos
                            </span>
                        @endif
                    </span>
                    <svg class="w-5 h-5 transition-transform {{ $showFilters ?? false ? 'rotate-180' : '' }}"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            {{-- Contenedor de filtros --}}
            <div class="{{ ($showFilters ?? false) ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    {{-- Busqueda --}}
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar lista</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="busqueda"
                               placeholder="Nombre, codigo o descripcion..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    </div>

                    {{-- Sucursal --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sucursal</label>
                        <select wire:model.live="sucursalFiltro"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">Todas</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                        <select wire:model.live="activoFiltro"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="todos">Todos</option>
                            <option value="activos">Activos</option>
                            <option value="inactivos">Inactivos</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 items-center">
                    {{-- Lista Base --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                        <select wire:model.live="esListaBaseFiltro"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">Todas</option>
                            <option value="si">Solo listas base</option>
                            <option value="no">Solo listas especiales</option>
                        </select>
                    </div>

                    <div class="flex-1"></div>

                    <button wire:click="limpiarFiltros"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition">
                        Limpiar Filtros
                    </button>
                </div>
            </div>
        </div>

        {{-- Vista de Tarjetas (Moviles) --}}
        <div class="sm:hidden space-y-3">
            @forelse($listas as $lista)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ $lista->es_lista_base ? 'border-l-4 border-l-blue-500' : '' }}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $lista->nombre }}</span>
                                @if($lista->es_lista_base)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        Base
                                    </span>
                                @endif
                            </div>
                            @if($lista->codigo)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $lista->codigo }}</div>
                            @endif
                        </div>
                        <div class="ml-2">
                            <button wire:click="toggleActivo({{ $lista->id }})"
                                    @if($lista->es_lista_base && $lista->activo) disabled @endif
                                    class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $lista->activo ? 'bg-green-600' : 'bg-gray-300' }} $lista->es_lista_base && 'opacity-50 cursor-not-allowed' '' }}">
                                <span class="sr-only">{{ $lista->activo ? 'Desactivar' : 'Activar' }} lista</span>
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white dark:bg-gray-800 shadow transform ring-0 transition ease-in-out duration-200 {{ $lista->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Sucursal:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $lista->sucursal->nombre }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Ajuste:</span>
                            <span class="font-bold {{ $lista->ajuste_porcentaje > 0 ? 'text-red-600' : ($lista->ajuste_porcentaje < 0 ? 'text-green-600' : 'text-gray-600 dark:text-gray-300') }}">
                                {{ $lista->ajuste_porcentaje > 0 ? '+' : '' }}@porcentaje($lista->ajuste_porcentaje)
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Prioridad:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $lista->prioridad }}</span>
                        </div>

                        @if($lista->condiciones_count > 0 || $lista->articulos_count > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Detalles:</span>
                                <div class="text-right space-x-2">
                                    @if($lista->condiciones_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-50 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 text-xs">
                                            {{ $lista->condiciones_count }} cond.
                                        </span>
                                    @endif
                                    @if($lista->articulos_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-orange-50 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 text-xs">
                                            {{ $lista->articulos_count }} art.
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Acciones movil --}}
                    <div class="flex gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('configuracion.precios.editar', $lista->id) }}"
                           wire:navigate
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Editar
                        </a>
                        <button wire:click="duplicar({{ $lista->id }})"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Duplicar
                        </button>
                        @unless($lista->es_lista_base)
                            <button wire:click="confirmarEliminar({{ $lista->id }})"
                                    class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-600 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="mt-2 text-sm">No se encontraron listas de precios</p>
                </div>
            @endforelse

            {{-- Paginacion Movil --}}
            @if($listas->hasPages())
                <div class="mt-4">
                    {{ $listas->links() }}
                </div>
            @endif
        </div>

        {{-- Tabla de Listas (Desktop) --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('nombre')">
                                Lista
                                @if($ordenarPor === 'nombre')
                                    <span class="ml-1">{{ $ordenDireccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Sucursal
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('ajuste')">
                                Ajuste
                                @if($ordenarPor === 'ajuste')
                                    <span class="ml-1">{{ $ordenDireccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('prioridad')">
                                Prioridad
                                @if($ordenarPor === 'prioridad')
                                    <span class="ml-1">{{ $ordenDireccion === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Detalles
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
                        @forelse($listas as $lista)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ $lista->es_lista_base ? 'bg-blue-50/30 dark:bg-blue-900/20' : '' }}">
                                {{-- Lista --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $lista->nombre }}</div>
                                        @if($lista->es_lista_base)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                Base
                                            </span>
                                        @endif
                                    </div>
                                    @if($lista->codigo)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $lista->codigo }}</div>
                                    @endif
                                    @if($lista->descripcion)
                                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 truncate max-w-xs" title="{{ $lista->descripcion }}">{{ $lista->descripcion }}</div>
                                    @endif
                                </td>

                                {{-- Sucursal --}}
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $lista->sucursal->nombre }}</div>
                                </td>

                                {{-- Ajuste --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold {{ $lista->ajuste_porcentaje > 0 ? 'text-red-600' : ($lista->ajuste_porcentaje < 0 ? 'text-green-600' : 'text-gray-600 dark:text-gray-300') }}">
                                        {{ $lista->ajuste_porcentaje > 0 ? '+' : '' }}@porcentaje($lista->ajuste_porcentaje)
                                    </span>
                                    @if($lista->redondeo !== 'ninguno')
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Redondeo: {{ ucfirst($lista->redondeo) }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Prioridad --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $lista->prioridad <= 50 ? 'bg-red-100 text-red-800' : ($lista->prioridad <= 100 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200') }}">
                                        {{ $lista->prioridad }}
                                    </span>
                                </td>

                                {{-- Detalles --}}
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @if($lista->condiciones_count > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-50 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-700">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ $lista->condiciones_count }} condiciones
                                            </span>
                                        @endif
                                        @if($lista->articulos_count > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-orange-50 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 border border-orange-200 dark:border-orange-700">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                                {{ $lista->articulos_count }} articulos
                                            </span>
                                        @endif
                                        @if(!$lista->aplica_promociones)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                Sin promos
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Estado --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleActivo({{ $lista->id }})"
                                            @if($lista->es_lista_base && $lista->activo) disabled @endif
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $lista->activo ? 'bg-green-600' : 'bg-gray-300' }} $lista->es_lista_base && 'opacity-50 cursor-not-allowed' '' }}">
                                        <span class="sr-only">{{ $lista->activo ? 'Desactivar' : 'Activar' }} lista</span>
                                        <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white dark:bg-gray-800 shadow transform ring-0 transition ease-in-out duration-200 {{ $lista->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $lista->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>

                                {{-- Acciones --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('configuracion.precios.editar', $lista->id) }}"
                                           wire:navigate
                                           title="Editar lista"
                                           class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Editar
                                        </a>
                                        <button wire:click="duplicar({{ $lista->id }})"
                                                title="Duplicar lista"
                                                class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        @unless($lista->es_lista_base)
                                            <button wire:click="confirmarEliminar({{ $lista->id }})"
                                                    title="Eliminar"
                                                    class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-600 transition-colors duration-150">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <p class="mt-2">No se encontraron listas de precios</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginacion Desktop --}}
            @if($listas->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $listas->links() }}
                </div>
            @endif
        </div>

        {{-- Estadisticas rapidas --}}
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Listas</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $listas->total() }}</div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-4">
                <div class="text-xs text-green-600 dark:text-green-400 uppercase">Activas</div>
                <div class="text-2xl font-bold text-green-900 dark:text-green-300">{{ \App\Models\ListaPrecio::where('activo', true)->count() }}</div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4">
                <div class="text-xs text-blue-600 dark:text-blue-400 uppercase">Listas Base</div>
                <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ \App\Models\ListaPrecio::where('es_lista_base', true)->count() }}</div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-4">
                <div class="text-xs text-purple-600 dark:text-purple-400 uppercase">Sucursales</div>
                <div class="text-2xl font-bold text-purple-900 dark:text-purple-300">{{ \App\Models\ListaPrecio::distinct('sucursal_id')->count('sucursal_id') }}</div>
            </div>
        </div>
    </div>

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
                                    Eliminar lista de precios
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ¿Estas seguro de eliminar la lista <span class="font-semibold text-gray-700 dark:text-gray-300">"{{ $nombreListaAEliminar }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Esta accion no eliminara permanentemente los datos, pero la lista dejara de estar disponible en el sistema.
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
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
