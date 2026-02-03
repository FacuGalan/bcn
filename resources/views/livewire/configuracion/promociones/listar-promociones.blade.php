<div class="py-4" x-data="{
    mostrarModalEliminar: false,
    promocionAEliminar: null,
    nombrePromocion: ''
}">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary flex items-center h-10 sm:h-auto">
                            {{ __('Gestión de Promociones') }}
                        </h2>
                        {{-- Botón Nueva Promoción - Solo icono en móviles --}}
                        <a href="{{ route('configuracion.promociones.nueva') }}"
                           wire:navigate
                           class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                           :title="__('Crear nueva promoción')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </a>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra descuentos, ofertas y promociones especiales') }}</p>
                </div>
                {{-- Botón Nueva Promoción - Desktop --}}
                <a href="{{ route('configuracion.promociones.nueva') }}"
                   wire:navigate
                   class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Nueva Promoción') }}
                </a>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            {{-- Botón de filtros (solo móvil) --}}
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="$toggle('showFilters')"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($busqueda || $sucursalFiltro || $tipoFiltro || $activoFiltro !== 'todos')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
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
                    {{-- Búsqueda --}}
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar promoción') }}</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="busqueda"
                               :placeholder="__('Nombre, descripción o código cupón...')"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    </div>

                    {{-- Sucursal --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursal') }}</label>
                        <select wire:model.live="sucursalFiltro"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                        <select wire:model.live="tipoFiltro"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todos') }}</option>
                            @foreach($tiposPromocion as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <details class="mt-2">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition">
                        {{ __('Filtros Avanzados') }}
                    </summary>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t">
                        {{-- Estado --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                            <select wire:model.live="activoFiltro"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="todos">{{ __('Todos') }}</option>
                                <option value="activos">{{ __('Activos') }}</option>
                                <option value="inactivos">{{ __('Inactivos') }}</option>
                            </select>
                        </div>

                        {{-- Vigencia --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Vigencia') }}</label>
                            <select wire:model.live="vigenteFiltro"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="todos">{{ __('Todas') }}</option>
                                <option value="vigentes">{{ __('Vigentes') }}</option>
                                <option value="vencidas">{{ __('Vencidas') }}</option>
                            </select>
                        </div>

                        {{-- Cupón --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cupón') }}</label>
                            <select wire:model.live="cuponFiltro"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="todos">{{ __('Todas') }}</option>
                                <option value="con_cupon">{{ __('Con cupón') }}</option>
                                <option value="sin_cupon">{{ __('Sin cupón (automáticas)') }}</option>
                            </select>
                        </div>
                    </div>
                </details>

                <div class="mt-4 flex justify-end">
                    <button wire:click="limpiarFiltros"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition">
                        {{ __('Limpiar Filtros') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Vista de Tarjetas (Móviles) --}}
        <div class="sm:hidden space-y-3">
            @forelse($promociones as $promocion)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $this->getColorTipo($promocion->tipo) }}">
                                    {{ $tiposPromocion[$promocion->tipo] ?? $promocion->tipo }}
                                </span>
                                <span class="text-xs font-bold text-purple-600">
                                    P{{ $promocion->prioridad }}
                                </span>
                            </div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $promocion->nombre }}</div>
                            @if(!$sucursalFiltro)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    {{ $promocion->sucursal->nombre }}
                                </div>
                            @endif
                            @if($promocion->codigo_cupon)
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-yellow-50 text-yellow-800 mt-1">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                    </svg>
                                    {{ $promocion->codigo_cupon }}
                                </div>
                            @endif
                        </div>
                        <div class="ml-2">
                            <button wire:click="toggleActivo({{ $promocion->id }})"
                                    class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $promocion->activo ? 'bg-green-600' : 'bg-gray-300' }}">
                                <span class="sr-only">{{ $promocion->activo ? __('Desactivar') : __('Activar') }}</span>
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $promocion->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        @if($promocion->tipo === 'descuento_escalonado' && $promocion->escalas->count() > 0)
                            <div class="pt-2 border-t">
                                <span class="text-gray-500 block mb-2">{{ __('Escalas:') }}</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($promocion->escalas as $escala)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700 border border-purple-200">
                                            @numero($escala->cantidad_desde)
                                            @if($escala->cantidad_hasta)
                                                -@numero($escala->cantidad_hasta)
                                            @else
                                                +
                                            @endif:
                                            @if($escala->tipo_descuento === 'porcentaje')
                                                @porcentaje($escala->valor)
                                            @elseif($escala->tipo_descuento === 'monto')
                                                -$@precio($escala->valor)
                                            @else
                                                $@precio($escala->valor)
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">{{ __('Descuento:') }}</span>
                                <span class="text-lg font-bold text-green-600">
                                    @if(str_contains($promocion->tipo, 'porcentaje'))
                                        @porcentaje($promocion->valor)
                                    @elseif(str_contains($promocion->tipo, 'monto'))
                                        $@precio($promocion->valor)
                                    @else
                                        $@precio($promocion->valor)
                                    @endif
                                </span>
                            </div>
                        @endif

                        @if($promocion->vigencia_desde || $promocion->vigencia_hasta)
                            <div class="flex justify-between pt-2 border-t">
                                <span class="text-gray-500">{{ __('Vigencia:') }}</span>
                                <div class="text-right">
                                    @if($promocion->vigencia_desde && $promocion->vigencia_hasta)
                                        <div>{{ $promocion->vigencia_desde->format('d/m/Y') }}</div>
                                        <div class="text-gray-400">{{ __('al') }} {{ $promocion->vigencia_hasta->format('d/m/Y') }}</div>
                                    @elseif($promocion->vigencia_desde)
                                        <div>{{ __('Desde') }} {{ $promocion->vigencia_desde->format('d/m/Y') }}</div>
                                    @elseif($promocion->vigencia_hasta)
                                        <div>{{ __('Hasta') }} {{ $promocion->vigencia_hasta->format('d/m/Y') }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($promocion->condiciones->count() > 0)
                            <div class="pt-2 border-t">
                                <span class="text-blue-600 font-medium">
                                    {{ $promocion->condiciones->count() }} {{ __('condición(es)') }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 pt-3 border-t flex gap-2">
                        <a href="{{ route('configuracion.promociones.editar', $promocion->id) }}"
                           wire:navigate
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-blue-600 text-sm font-medium rounded-md text-blue-600 hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ __('Editar') }}
                        </a>
                        <button wire:click="duplicar({{ $promocion->id }})"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            {{ __('Duplicar') }}
                        </button>
                        <button @click="promocionAEliminar = {{ $promocion->id }}; nombrePromocion = '{{ addslashes($promocion->nombre) }}'; mostrarModalEliminar = true"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('Eliminar') }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('No se encontraron promociones') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Intenta ajustar los filtros o crea una nueva promoción') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Vista de Tabla (Desktop) --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('prioridad')">
                                <div class="flex items-center gap-1">
                                    {{ __('Prioridad') }}
                                    @if($ordenarPor === 'prioridad')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ordenDireccion === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            @if(!$sucursalFiltro)
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Sucursal') }}
                                </th>
                            @endif
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('nombre')">
                                <div class="flex items-center gap-1">
                                    {{ __('Promoción') }}
                                    @if($ordenarPor === 'nombre')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ordenDireccion === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('tipo')">
                                <div class="flex items-center gap-1">
                                    {{ __('Tipo') }}
                                    @if($ordenarPor === 'tipo')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ordenDireccion === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Descuento/Valor') }}
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('vigencia')">
                                <div class="flex items-center gap-1">
                                    {{ __('Vigencia') }}
                                    @if($ordenarPor === 'vigencia')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ordenDireccion === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Condiciones') }}
                            </th>
                            <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Estado') }}
                            </th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Acciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($promociones as $promocion)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                {{-- Prioridad --}}
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                        {{ $promocion->prioridad }}
                                    </span>
                                </td>

                                {{-- Sucursal --}}
                                @if(!$sucursalFiltro)
                                    <td class="px-3 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $promocion->sucursal->nombre }}</span>
                                    </td>
                                @endif

                                {{-- Promoción --}}
                                <td class="px-3 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $promocion->nombre }}</div>
                                    @if($promocion->codigo_cupon)
                                        <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-yellow-50 text-yellow-800 mt-1">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                            </svg>
                                            {{ $promocion->codigo_cupon }}
                                        </div>
                                    @endif
                                    @if($promocion->descripcion)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($promocion->descripcion, 40) }}</div>
                                    @endif
                                </td>

                                {{-- Tipo --}}
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $this->getColorTipo($promocion->tipo) }}">
                                        {{ $tiposPromocion[$promocion->tipo] ?? $promocion->tipo }}
                                    </span>
                                    @if($promocion->combinable)
                                        <span class="block mt-1 text-xs text-blue-600">{{ __('Combinable') }}</span>
                                    @endif
                                </td>

                                {{-- Descuento/Valor --}}
                                <td class="px-3 py-4">
                                    @if($promocion->tipo === 'descuento_escalonado' && $promocion->escalas->count() > 0)
                                        <div class="flex flex-col gap-1">
                                            @foreach($promocion->escalas as $escala)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                                    @numero($escala->cantidad_desde)
                                                    @if($escala->cantidad_hasta)
                                                        -@numero($escala->cantidad_hasta)
                                                    @else
                                                        +
                                                    @endif:
                                                    @if($escala->tipo_descuento === 'porcentaje')
                                                        @porcentaje($escala->valor)
                                                    @elseif($escala->tipo_descuento === 'monto')
                                                        -$@precio($escala->valor)
                                                    @else
                                                        $@precio($escala->valor)
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm font-bold text-green-600">
                                            @if(str_contains($promocion->tipo, 'porcentaje'))
                                                @porcentaje($promocion->valor)
                                            @else
                                                $@precio($promocion->valor)
                                            @endif
                                        </span>
                                    @endif
                                </td>

                                {{-- Vigencia --}}
                                <td class="px-3 py-4 whitespace-nowrap text-xs">
                                    @if($promocion->vigencia_desde || $promocion->vigencia_hasta)
                                        @if($promocion->vigencia_desde && $promocion->vigencia_hasta)
                                            <div>{{ $promocion->vigencia_desde->format('d/m/Y') }}</div>
                                            <div class="text-gray-500">{{ __('al') }} {{ $promocion->vigencia_hasta->format('d/m/Y') }}</div>
                                        @elseif($promocion->vigencia_desde)
                                            <div>{{ __('Desde') }} {{ $promocion->vigencia_desde->format('d/m/Y') }}</div>
                                        @else
                                            <div>{{ __('Hasta') }} {{ $promocion->vigencia_hasta->format('d/m/Y') }}</div>
                                        @endif
                                    @else
                                        <span class="text-gray-500">{{ __('Permanente') }}</span>
                                    @endif

                                    @if($promocion->dias_semana && count($promocion->dias_semana) > 0)
                                        <div class="text-gray-500 mt-1">
                                            {{ count($promocion->dias_semana) < 7 ? count($promocion->dias_semana) . ' ' . __('días') : __('Todos los días') }}
                                        </div>
                                    @endif

                                    @if($promocion->hora_desde || $promocion->hora_hasta)
                                        <div class="text-gray-500 mt-1">
                                            {{ substr($promocion->hora_desde ?? '00:00', 0, 5) }}-{{ substr($promocion->hora_hasta ?? '23:59', 0, 5) }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Condiciones --}}
                                <td class="px-3 py-4">
                                    @if($promocion->condiciones->count() > 0)
                                        <div class="text-xs space-y-1">
                                            @foreach($promocion->condiciones->take(2) as $condicion)
                                                <div class="text-gray-700 dark:text-gray-300">{{ $condicion->obtenerDescripcion() }}</div>
                                            @endforeach
                                            @if($promocion->condiciones->count() > 2)
                                                <div class="text-blue-600 font-medium">
                                                    +{{ $promocion->condiciones->count() - 2 }} {{ __('más') }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Sin condiciones') }}</span>
                                    @endif
                                </td>

                                {{-- Estado --}}
                                <td class="px-3 py-4 whitespace-nowrap text-center">
                                    <button wire:click="toggleActivo({{ $promocion->id }})"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $promocion->activo ? 'bg-green-600' : 'bg-gray-300' }}">
                                        <span class="sr-only">{{ $promocion->activo ? __('Desactivar') : __('Activar') }}</span>
                                        <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $promocion->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>

                                {{-- Acciones --}}
                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('configuracion.promociones.editar', $promocion->id) }}"
                                           wire:navigate
                                           :title="__('Editar')"
                                           class="inline-flex items-center justify-center p-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="duplicar({{ $promocion->id }})"
                                                :title="__('Duplicar')"
                                                class="inline-flex items-center justify-center p-2 border border-green-600 text-green-600 rounded-md hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        <button @click="promocionAEliminar = {{ $promocion->id }}; nombrePromocion = '{{ addslashes($promocion->nombre) }}'; mostrarModalEliminar = true"
                                                :title="__('Eliminar')"
                                                class="inline-flex items-center justify-center p-2 border border-red-600 text-red-600 rounded-md hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $sucursalFiltro ? '8' : '9' }}" class="px-6 py-12 text-center">
                                    <div class="text-gray-400 dark:text-gray-500">
                                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                        <p class="text-sm font-medium dark:text-white">{{ __('No se encontraron promociones') }}</p>
                                        <p class="text-xs mt-1 dark:text-gray-400">{{ __('Intenta ajustar los filtros o crea una nueva promoción') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($promociones->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $promociones->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal de Confirmación de Eliminación --}}
    <div x-show="mostrarModalEliminar"
         x-cloak
         @keydown.escape.window="mostrarModalEliminar = false"
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div x-show="mostrarModalEliminar"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="mostrarModalEliminar = false"
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 aria-hidden="true"></div>

            {{-- Truco para centrar verticalmente --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Panel del modal --}}
            <div x-show="mostrarModalEliminar"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                            {{ __('Eliminar Promoción') }}
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Estás a punto de eliminar la promoción:') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white" x-text="nombrePromocion"></p>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Esta acción se puede revertir. La promoción quedará marcada como eliminada pero se mantendrá en el sistema para propósitos de estadísticas.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                    <button type="button"
                            @click="$wire.eliminar(promocionAEliminar); mostrarModalEliminar = false"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        {{ __('Eliminar') }}
                    </button>
                    <button type="button"
                            @click="mostrarModalEliminar = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:w-auto sm:text-sm transition-colors">
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
