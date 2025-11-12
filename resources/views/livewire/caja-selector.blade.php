<div class="relative" x-data="{ open: @entangle('mostrarDropdown') }">
    @if($cajasDisponibles && $cajasDisponibles->count() > 1)
        <!-- Botón del selector -->
        <button
            @click="open = !open"
            type="button"
            class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
            <!-- Icono de caja registradora -->
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>

            <!-- Nombre de la caja actual -->
            <span class="hidden md:inline">
                {{ $cajaActual ? $cajaActual->nombre : 'Seleccionar Caja' }}
            </span>

            <!-- Badge de estado -->
            @if($cajaActual && $cajaActual->estado === 'abierta')
                <span class="hidden md:inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-800 bg-green-100 rounded">
                    Abierta
                </span>
            @elseif($cajaActual)
                <span class="hidden md:inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 rounded">
                    Cerrada
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
                    Cambiar Caja
                </div>

                <!-- Lista de cajas -->
                @foreach($cajasDisponibles as $caja)
                    <button
                        wire:click="cambiarCaja({{ $caja->id }})"
                        class="flex items-center w-full px-4 py-2 text-sm text-left hover:bg-gray-100
                               {{ $cajaActual && $cajaActual->id === $caja->id ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700' }}"
                    >
                        <div class="flex-1">
                            <!-- Nombre de la caja -->
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $caja->nombre }}</span>

                                <!-- Icono de check si es la activa -->
                                @if($cajaActual && $cajaActual->id === $caja->id)
                                    <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </div>

                            <!-- Tipo y estado de la caja -->
                            <div class="text-xs text-gray-500">
                                {{ ucfirst($caja->tipo) }}
                                @if($caja->estado === 'abierta')
                                    <span class="text-green-600">• Abierta</span>
                                @else
                                    <span class="text-gray-400">• Cerrada</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($cajaActual)
        <!-- Si solo tiene una caja, mostrar sin dropdown -->
        <div class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md">
            <!-- Icono de caja registradora -->
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>

            <!-- Nombre de la caja -->
            <span class="hidden md:inline">{{ $cajaActual->nombre }}</span>

            @if($cajaActual->estado === 'abierta')
                <span class="hidden md:inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-800 bg-green-100 rounded">
                    Abierta
                </span>
            @endif
        </div>
    @endif
</div>
