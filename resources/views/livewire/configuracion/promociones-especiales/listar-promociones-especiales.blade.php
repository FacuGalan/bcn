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
                            {{ __('Promociones Especiales') }}
                        </h2>
                        {{-- Boton Nueva Promocion - Solo icono en moviles --}}
                        <a href="{{ route('configuracion.promociones-especiales.nueva') }}"
                           wire:navigate
                           class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                           :title="__('Crear nueva promocion especial')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </a>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('NxM, Combos/Packs y Menus del dia') }}</p>
                </div>
                {{-- Boton Nueva Promocion - Desktop --}}
                <a href="{{ route('configuracion.promociones-especiales.nueva') }}"
                   wire:navigate
                   class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Nueva Promocion') }}
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
                    {{-- Busqueda --}}
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar promocion') }}</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="busqueda"
                               :placeholder="__('Nombre o descripcion...')"
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
                            <option value="nxm">{{ __('NxM Basico') }}</option>
                            <option value="nxm_avanzado">{{ __('NxM Avanzado') }}</option>
                            <option value="combo">{{ __('Combo/Pack') }}</option>
                            <option value="menu">{{ __('Menu') }}</option>
                        </select>
                    </div>
                </div>

                <details class="mt-2">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition">
                        {{ __('Filtros Avanzados') }}
                    </summary>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t">
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

        @php
            $tipoConfig = [
                'nxm' => ['label' => __('NxM'), 'bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
                'nxm_avanzado' => ['label' => __('NxM Avanzado'), 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-800'],
                'combo' => ['label' => __('Combo/Pack'), 'bg' => 'bg-orange-100', 'text' => 'text-orange-800'],
                'menu' => ['label' => __('Menu'), 'bg' => 'bg-green-100', 'text' => 'text-green-800'],
            ];
        @endphp

        {{-- Vista de Tarjetas (Moviles) --}}
        <div class="sm:hidden space-y-3">
            @forelse($promociones as $promo)
                @php
                    $config = $tipoConfig[$promo->tipo] ?? ['label' => $promo->tipo, 'bg' => 'bg-gray-100', 'text' => 'text-gray-800'];
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $config['bg'] }} {{ $config['text'] }}">
                                    {{ $config['label'] }}
                                </span>
                                <span class="text-xs font-bold text-purple-600">
                                    P{{ $promo->prioridad }}
                                </span>
                                @if($promo->vigencia_hasta && $promo->vigencia_hasta->isPast())
                                    <span class="text-xs text-red-500 font-medium">{{ __('Vencida') }}</span>
                                @elseif($promo->vigencia_desde && $promo->vigencia_desde->isFuture())
                                    <span class="text-xs text-yellow-600 font-medium">{{ __('Proxima') }}</span>
                                @endif
                            </div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $promo->nombre }}</div>
                            @if(!$sucursalFiltro)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    {{ $promo->sucursal->nombre ?? '-' }}
                                </div>
                            @endif
                        </div>
                        <div class="ml-2">
                            <button wire:click="toggleActivo({{ $promo->id }})"
                                    class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $promo->activo ? 'bg-green-600' : 'bg-gray-300' }}">
                                <span class="sr-only">{{ $promo->activo ? __('Desactivar') : __('Activar') }}</span>
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $promo->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        {{-- Detalle segun tipo --}}
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">{{ __('Detalle:') }}</span>
                            <div class="text-right">
                                @if($promo->tipo === 'nxm' || $promo->tipo === 'nxm_avanzado')
                                    @if($promo->usa_escalas)
                                        <span class="text-sm text-gray-600">{{ $promo->escalas->count() }} {{ __('escalas') }}</span>
                                    @else
                                        <span class="font-medium">
                                            {{ __('Lleva') }} {{ $promo->nxm_lleva }} {{ __('paga') }} {{ $promo->nxm_lleva - $promo->nxm_bonifica }}
                                        </span>
                                    @endif
                                @elseif($promo->tipo === 'combo' || $promo->tipo === 'menu')
                                    @if($promo->precio_tipo === 'fijo')
                                        <span class="text-lg font-bold text-green-600">$@precio($promo->precio_valor)</span>
                                    @else
                                        <span class="text-lg font-bold text-green-600">{{ $promo->precio_valor }}% {{ __('dto') }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if($promo->tipo === 'nxm')
                            <div class="text-xs text-gray-500 pt-1 border-t">
                                @if($promo->articuloNxM)
                                    {{ __('Art:') }} {{ $promo->articuloNxM->nombre }}
                                @elseif($promo->categoriaNxM)
                                    {{ __('Cat:') }} {{ $promo->categoriaNxM->nombre }}
                                @endif
                            </div>
                        @elseif($promo->tipo === 'nxm_avanzado')
                            <div class="text-xs text-gray-500 pt-1 border-t">
                                {{ $promo->gruposTrigger->count() }} trigger(s) &rarr; {{ $promo->gruposReward->count() }} reward(s)
                            </div>
                        @elseif($promo->tipo === 'combo' || $promo->tipo === 'menu')
                            <div class="text-xs text-gray-500 pt-1 border-t">
                                {{ $promo->grupos->count() }} {{ $promo->tipo === 'menu' ? __('grupo(s)') : __('articulo(s)') }}
                            </div>
                        @endif

                        @if($promo->vigencia_desde || $promo->vigencia_hasta)
                            <div class="flex justify-between pt-2 border-t">
                                <span class="text-gray-500">{{ __('Vigencia:') }}</span>
                                <div class="text-right">
                                    @if($promo->vigencia_desde && $promo->vigencia_hasta)
                                        <div>{{ $promo->vigencia_desde->format('d/m/Y') }}</div>
                                        <div class="text-gray-400">{{ __('al') }} {{ $promo->vigencia_hasta->format('d/m/Y') }}</div>
                                    @elseif($promo->vigencia_desde)
                                        <div>{{ __('Desde') }} {{ $promo->vigencia_desde->format('d/m/Y') }}</div>
                                    @elseif($promo->vigencia_hasta)
                                        <div>{{ __('Hasta') }} {{ $promo->vigencia_hasta->format('d/m/Y') }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 pt-3 border-t flex gap-2">
                        <a href="{{ route('configuracion.promociones-especiales.editar', $promo->id) }}"
                           wire:navigate
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-blue-600 text-sm font-medium rounded-md text-blue-600 hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ __('Editar') }}
                        </a>
                        <button wire:click="duplicar({{ $promo->id }})"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            {{ __('Duplicar') }}
                        </button>
                        <button @click="promocionAEliminar = {{ $promo->id }}; nombrePromocion = '{{ addslashes($promo->nombre) }}'; mostrarModalEliminar = true"
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('No se encontraron promociones') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Intenta ajustar los filtros o crea una nueva promocion') }}</p>
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
                                    @if(($ordenarPor ?? '') === 'prioridad')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ ($ordenDireccion ?? 'asc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
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
                                    {{ __('Promocion') }}
                                    @if(($ordenarPor ?? '') === 'nombre')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ ($ordenDireccion ?? 'asc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('tipo')">
                                <div class="flex items-center gap-1">
                                    {{ __('Tipo') }}
                                    @if(($ordenarPor ?? '') === 'tipo')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ ($ordenDireccion ?? 'asc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Detalle') }}
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                wire:click="ordenar('vigencia')">
                                <div class="flex items-center gap-1">
                                    {{ __('Vigencia') }}
                                    @if(($ordenarPor ?? '') === 'vigencia')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ ($ordenDireccion ?? 'asc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                        </svg>
                                    @endif
                                </div>
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
                        @forelse($promociones as $promo)
                            @php
                                $config = $tipoConfig[$promo->tipo] ?? ['label' => $promo->tipo, 'bg' => 'bg-gray-100', 'text' => 'text-gray-800'];
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                {{-- Prioridad --}}
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                        {{ $promo->prioridad }}
                                    </span>
                                </td>

                                {{-- Sucursal --}}
                                @if(!$sucursalFiltro)
                                    <td class="px-3 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $promo->sucursal->nombre ?? '-' }}</span>
                                    </td>
                                @endif

                                {{-- Promocion --}}
                                <td class="px-3 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $promo->nombre }}</div>
                                    @if($promo->descripcion)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($promo->descripcion, 40) }}</div>
                                    @endif
                                </td>

                                {{-- Tipo --}}
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $config['bg'] }} {{ $config['text'] }}">
                                        {{ $config['label'] }}
                                    </span>
                                </td>

                                {{-- Detalle --}}
                                <td class="px-3 py-4">
                                    @if($promo->tipo === 'nxm')
                                        @if($promo->usa_escalas)
                                            <span class="text-sm text-gray-600">{{ __('Escalas') }} ({{ $promo->escalas->count() }})</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                                {{ __('Lleva') }} {{ $promo->nxm_lleva }} &rarr; {{ $promo->nxm_bonifica }}
                                                @if($promo->beneficio_tipo === 'gratis')
                                                    {{ __('gratis') }}
                                                @else
                                                    {{ $promo->beneficio_porcentaje }}% {{ __('dto') }}
                                                @endif
                                            </span>
                                        @endif
                                        <div class="text-xs text-gray-500 mt-1">
                                            @if($promo->articuloNxM)
                                                {{ Str::limit($promo->articuloNxM->nombre, 25) }}
                                            @elseif($promo->categoriaNxM)
                                                {{ __('Cat:') }} {{ $promo->categoriaNxM->nombre }}
                                            @endif
                                        </div>

                                    @elseif($promo->tipo === 'nxm_avanzado')
                                        @if($promo->usa_escalas)
                                            <span class="text-sm text-gray-600">{{ __('Escalas') }} ({{ $promo->escalas->count() }})</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap">
                                                {{ __('Lleva') }} {{ $promo->nxm_lleva }} &rarr; {{ $promo->nxm_bonifica }}
                                                @if($promo->beneficio_tipo === 'gratis')
                                                    {{ __('gratis') }}
                                                @else
                                                    {{ $promo->beneficio_porcentaje }}% {{ __('dto') }}
                                                @endif
                                            </span>
                                        @endif
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $promo->gruposTrigger->count() }} trigger(s) &rarr; {{ $promo->gruposReward->count() }} reward(s)
                                        </div>

                                    @elseif($promo->tipo === 'combo')
                                        @if($promo->precio_tipo === 'fijo')
                                            <span class="text-sm font-bold text-green-600">$@precio($promo->precio_valor)</span>
                                        @else
                                            <span class="text-sm font-bold text-green-600">{{ $promo->precio_valor }}% {{ __('dto') }}</span>
                                        @endif
                                        <div class="text-xs text-gray-500 mt-1">{{ $promo->grupos->count() }} {{ __('articulo(s)') }}</div>

                                    @elseif($promo->tipo === 'menu')
                                        @if($promo->precio_tipo === 'fijo')
                                            <span class="text-sm font-bold text-green-600">$@precio($promo->precio_valor)</span>
                                        @else
                                            <span class="text-sm font-bold text-green-600">{{ $promo->precio_valor }}% {{ __('dto') }}</span>
                                        @endif
                                        <div class="text-xs text-gray-500 mt-1">{{ $promo->grupos->count() }} {{ __('grupo(s)') }}</div>
                                    @endif
                                </td>

                                {{-- Vigencia --}}
                                <td class="px-3 py-4 whitespace-nowrap text-xs">
                                    @if($promo->vigencia_desde || $promo->vigencia_hasta)
                                        @if($promo->vigencia_desde && $promo->vigencia_hasta)
                                            <div>{{ $promo->vigencia_desde->format('d/m/Y') }}</div>
                                            <div class="text-gray-500">{{ __('al') }} {{ $promo->vigencia_hasta->format('d/m/Y') }}</div>
                                        @elseif($promo->vigencia_desde)
                                            <div>{{ __('Desde') }} {{ $promo->vigencia_desde->format('d/m/Y') }}</div>
                                        @else
                                            <div>{{ __('Hasta') }} {{ $promo->vigencia_hasta->format('d/m/Y') }}</div>
                                        @endif
                                        @if($promo->vigencia_hasta && $promo->vigencia_hasta->isPast())
                                            <span class="text-red-500 font-medium">{{ __('Vencida') }}</span>
                                        @elseif($promo->vigencia_desde && $promo->vigencia_desde->isFuture())
                                            <span class="text-yellow-600 font-medium">{{ __('Proxima') }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500">{{ __('Permanente') }}</span>
                                    @endif
                                </td>

                                {{-- Estado --}}
                                <td class="px-3 py-4 whitespace-nowrap text-center">
                                    <button wire:click="toggleActivo({{ $promo->id }})"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $promo->activo ? 'bg-green-600' : 'bg-gray-300' }}">
                                        <span class="sr-only">{{ $promo->activo ? __('Desactivar') : __('Activar') }}</span>
                                        <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $promo->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>

                                {{-- Acciones --}}
                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('configuracion.promociones-especiales.editar', $promo->id) }}"
                                           wire:navigate
                                           :title="__('Editar')"
                                           class="inline-flex items-center justify-center p-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="duplicar({{ $promo->id }})"
                                                :title="__('Duplicar')"
                                                class="inline-flex items-center justify-center p-2 border border-green-600 text-green-600 rounded-md hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                        <button @click="promocionAEliminar = {{ $promo->id }}; nombrePromocion = '{{ addslashes($promo->nombre) }}'; mostrarModalEliminar = true"
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
                                <td colspan="{{ $sucursalFiltro ? '7' : '8' }}" class="px-6 py-12 text-center">
                                    <div class="text-gray-400 dark:text-gray-500">
                                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                        <p class="text-sm font-medium dark:text-white">{{ __('No se encontraron promociones especiales') }}</p>
                                        <p class="text-xs mt-1 dark:text-gray-400">{{ __('Intenta ajustar los filtros o crea una nueva promocion') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginacion --}}
            @if($promociones->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $promociones->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal de Confirmacion de Eliminacion --}}
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
                            {{ __('Eliminar Promocion Especial') }}
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Estas a punto de eliminar la promocion:') }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white" x-text="nombrePromocion"></p>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Esta accion se puede revertir. La promocion quedara marcada como eliminada pero se mantendra en el sistema para propositos de estadisticas.') }}
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
