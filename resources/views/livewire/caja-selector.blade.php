<div class="relative" x-data="{ open: @entangle('mostrarDropdown') }">
    @if($cajasDisponibles && $cajasDisponibles->count() > 1)
        <!-- Botón del selector -->
        <button
            @click="open = !open"
            type="button"
            class="w-full md:w-auto flex items-center justify-center md:justify-start gap-1.5 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-indigo-500"
        >
            <!-- Icono de caja registradora -->
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>

            <!-- Nombre de la caja actual -->
            <span>
                {{ $cajaActual ? $cajaActual->nombre : 'Seleccionar Caja' }}
            </span>

            <!-- Badge de estado operativo -->
            @if($cajaActual)
                @php
                    $estadoOp = $cajaActual->estado_operativo ?? ($cajaActual->estado === 'abierta' ? 'operativa' : 'sin_turno');
                @endphp
                @if($estadoOp === 'operativa')
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-green-800 bg-green-100 dark:text-green-200 dark:bg-green-900/50 rounded-full" title="Operativa">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                    </span>
                @elseif($estadoOp === 'pausada')
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-amber-800 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/50 rounded-full" title="Pausada">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    </span>
                @else
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-700 rounded-full" title="Sin turno">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path></svg>
                    </span>
                @endif
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
                    Cambiar Caja
                </div>

                <!-- Lista de cajas -->
                @foreach($cajasDisponibles as $caja)
                    <button
                        wire:click="cambiarCaja({{ $caja->id }})"
                        class="flex items-center w-full px-4 py-2 text-sm text-left hover:bg-gray-100 dark:hover:bg-gray-700
                               {{ $cajaActual && $cajaActual->id === $caja->id ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300' }}"
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
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ ucfirst($caja->tipo) }}</span>
                                @php
                                    $estadoOpCaja = $caja->estado_operativo ?? ($caja->estado === 'abierta' ? 'operativa' : 'sin_turno');
                                @endphp
                                @if($estadoOpCaja === 'operativa')
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-800 bg-green-100 dark:text-green-200 dark:bg-green-900/50 rounded">
                                        Operativa
                                    </span>
                                @elseif($estadoOpCaja === 'pausada')
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/50 rounded">
                                        Pausada
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-700 rounded">
                                        Sin turno
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($cajaActual)
        <!-- Si solo tiene una caja, mostrar sin dropdown -->
        <div class="w-full md:w-auto flex items-center justify-center md:justify-start gap-1.5 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-900 rounded-md">
            <!-- Icono de caja registradora -->
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>

            <!-- Nombre de la caja -->
            <span>{{ $cajaActual->nombre }}</span>

            @php
                $estadoOpUnica = $cajaActual->estado_operativo ?? ($cajaActual->estado === 'abierta' ? 'operativa' : 'sin_turno');
            @endphp
            @if($estadoOpUnica === 'operativa')
                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-green-800 bg-green-100 dark:text-green-200 dark:bg-green-900/50 rounded-full" title="Operativa">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                </span>
            @elseif($estadoOpUnica === 'pausada')
                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-amber-800 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/50 rounded-full" title="Pausada">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                </span>
            @else
                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-700 rounded-full" title="Sin turno">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path></svg>
                </span>
            @endif
        </div>
    @endif
</div>
