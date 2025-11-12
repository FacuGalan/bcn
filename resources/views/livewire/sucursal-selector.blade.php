<div class="relative" x-data="{ open: @entangle('mostrarDropdown') }">
    @if($sucursalesDisponibles && $sucursalesDisponibles->count() > 1)
        <!-- Botón del selector -->
        <button
            @click="open = !open"
            type="button"
            class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
            <!-- Icono de tienda -->
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>

            <!-- Nombre de la sucursal actual -->
            <span class="hidden md:inline">
                {{ $sucursalActual ? $sucursalActual->nombre : 'Seleccionar Sucursal' }}
            </span>

            <!-- Badge si es principal -->
            @if($sucursalActual && $sucursalActual->es_principal)
                <span class="hidden md:inline-flex items-center px-2 py-0.5 text-xs font-medium text-indigo-800 bg-indigo-100 rounded">
                    Principal
                </span>
            @endif

            <!-- Icono de flecha -->
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
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
            class="absolute right-0 z-50 w-64 mt-2 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            style="display: none;"
        >
            <div class="py-1">
                <!-- Header del dropdown -->
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase border-b">
                    Cambiar Sucursal
                </div>

                <!-- Lista de sucursales -->
                @foreach($sucursalesDisponibles as $sucursal)
                    <button
                        wire:click="cambiarSucursal({{ $sucursal->id }})"
                        class="flex items-center w-full px-4 py-2 text-sm text-left hover:bg-gray-100
                               {{ $sucursalActual && $sucursalActual->id === $sucursal->id ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700' }}"
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
                            <div class="text-xs text-gray-500">
                                {{ $sucursal->codigo }}
                                @if($sucursal->es_principal)
                                    <span class="text-indigo-600">• Principal</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($sucursalActual)
        <!-- Si solo tiene una sucursal, mostrar sin dropdown -->
        <div class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md">
            <!-- Icono de tienda -->
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>

            <!-- Nombre de la sucursal -->
            <span class="hidden md:inline">{{ $sucursalActual->nombre }}</span>

            @if($sucursalActual->es_principal)
                <span class="hidden md:inline-flex items-center px-2 py-0.5 text-xs font-medium text-indigo-800 bg-indigo-100 rounded">
                    Principal
                </span>
            @endif
        </div>
    @endif
</div>
