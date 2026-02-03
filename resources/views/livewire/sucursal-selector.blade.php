<div class="relative" x-data="{ open: @entangle('mostrarDropdown') }">
    @if($sucursalesDisponibles && $sucursalesDisponibles->count() > 1)
        <!-- Botón del selector -->
        <button
            @click="open = !open"
            type="button"
            class="w-full md:w-auto flex items-center justify-center md:justify-start gap-1.5 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-indigo-500"
        >
            <!-- Icono de tienda -->
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>

            <!-- Nombre de la sucursal actual -->
            <span>
                {{ $sucursalActual ? $sucursalActual->nombre : __('Seleccionar Sucursal') }}
            </span>

            <!-- Badge si es principal (estrella a la derecha) -->
            @if($sucursalActual && $sucursalActual->es_principal)
                <span class="flex items-center px-1.5 py-1 text-amber-500 bg-amber-50 rounded" title="Sucursal Principal">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                </span>
            @endif
        </button>

        <!-- Dropdown menu -->
        <div
            x-show="open"
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute left-0 md:right-0 md:left-auto z-50 w-full md:w-64 max-md:bottom-full max-md:mb-2 md:mt-2 origin-bottom-left md:origin-top-right bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            style="display: none;"
        >
            <div class="py-1">
                <!-- Header del dropdown -->
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase border-b dark:border-gray-700">
                    {{ __('Cambiar Sucursal') }}
                </div>

                <!-- Lista de sucursales -->
                @foreach($sucursalesDisponibles as $sucursal)
                    <button
                        wire:click="cambiarSucursal({{ $sucursal->id }})"
                        class="flex items-center w-full px-4 py-2 text-sm text-left hover:bg-gray-100 dark:hover:bg-gray-700
                               {{ $sucursalActual && $sucursalActual->id === $sucursal->id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300' }}"
                    >
                        <div class="flex-1">
                            <!-- Nombre de la sucursal -->
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $sucursal->nombre }}</span>

                                <!-- Icono de check si es la activa -->
                                @if($sucursalActual && $sucursalActual->id === $sucursal->id)
                                    <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </div>

                            <!-- Código de la sucursal -->
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $sucursal->codigo }}</span>
                                @if($sucursal->es_principal)
                                    <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20" title="Sucursal Principal">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($sucursalActual)
        <!-- Si solo tiene una sucursal, mostrar sin dropdown -->
        <div class="w-full md:w-auto flex items-center justify-center md:justify-start gap-1.5 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-900 rounded-md">
            <!-- Icono de tienda -->
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>

            <!-- Nombre de la sucursal -->
            <span>{{ $sucursalActual->nombre }}</span>

            @if($sucursalActual->es_principal)
                <span class="flex items-center px-1.5 py-1 text-amber-500 bg-amber-50 rounded" title="Sucursal Principal">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                </span>
            @endif
        </div>
    @endif
</div>
