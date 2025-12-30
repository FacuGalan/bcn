<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary flex items-center h-10 sm:h-auto">Formas de Pago por Sucursal</h2>
                        <!-- Botón volver - Solo icono en móviles -->
                        <a
                            href="{{ route('configuracion.formas-pago') }}"
                            wire:navigate
                            class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                            title="Volver a Formas de Pago"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </a>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">Configura qué formas de pago están disponibles en cada sucursal y sus ajustes</p>
                </div>
                <!-- Botón volver - Desktop -->
                <a
                    href="{{ route('configuracion.formas-pago') }}"
                    wire:navigate
                    class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    title="Volver a Formas de Pago"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver
                </a>
            </div>
        </div>

        @if($sucursal_id)
            <!-- Filtros y Acciones -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <!-- Selector de Sucursal -->
                    <div class="flex-1 sm:max-w-xs">
                        <label for="sucursal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sucursal</label>
                        <select
                            id="sucursal"
                            wire:model.live="sucursal_id"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Acciones Masivas -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            wire:click="activarTodas"
                            class="inline-flex items-center px-3 py-2 border border-green-500 rounded-md text-sm font-medium text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-green-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Activar todas
                        </button>
                        <button
                            wire:click="desactivarTodas"
                            class="inline-flex items-center px-3 py-2 border border-red-500 rounded-md text-sm font-medium text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Desactivar todas
                        </button>
                    </div>
                </div>
            </div>

            <!-- Vista de Tarjetas (Móviles) -->
            <div class="sm:hidden space-y-3">
                @forelse($formasPago as $fp)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ !$fp['activo_sucursal'] ? 'opacity-60' : '' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $fp['nombre'] }}</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $fp['activo_sucursal'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-gray-900 text-gray-800' }}">
                                        {{ $fp['activo_sucursal'] ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if($fp['es_mixta'])
                                        <span class="text-purple-600 font-medium">Mixta</span>
                                    @else
                                        {{ $fp['concepto_nombre'] ?? ucfirst(str_replace('_', ' ', $fp['concepto'])) }}
                                    @endif
                                </p>

                                <!-- Ajuste -->
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    @if($fp['es_mixta'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-100 text-purple-800">
                                            Sin ajuste propio
                                        </span>
                                    @elseif($fp['ajuste_efectivo'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded {{ $fp['tiene_ajuste_especifico'] ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800' }}">
                                            +{{ $fp['ajuste_efectivo'] }}% recargo
                                            @if($fp['tiene_ajuste_especifico'])
                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Ajuste específico para esta sucursal">
                                                    <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </span>
                                    @elseif($fp['ajuste_efectivo'] < 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded {{ $fp['tiene_ajuste_especifico'] ? 'bg-teal-100 text-teal-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $fp['ajuste_efectivo'] }}% descuento
                                            @if($fp['tiene_ajuste_especifico'])
                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Ajuste específico para esta sucursal">
                                                    <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </span>
                                    @endif

                                    @if($fp['permite_cuotas'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                            Permite cuotas
                                        </span>
                                    @endif
                                    @if($fp['factura_fiscal_efectivo'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded {{ $fp['tiene_factura_fiscal_especifica'] ? 'bg-violet-100 text-violet-800' : 'bg-indigo-100 text-indigo-800' }}">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                            Factura fiscal
                                            @if($fp['tiene_factura_fiscal_especifica'])
                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Específico para esta sucursal">
                                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Toggle -->
                            <button
                                wire:click="toggleFormaPago({{ $fp['id'] }})"
                                class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none {{ $fp['activo_sucursal'] ? 'bg-green-600' : 'bg-gray-300' }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white dark:bg-gray-800 shadow transform ring-0 transition {{ $fp['activo_sucursal'] ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>

                        @if(!$fp['es_mixta'])
                            <div class="mt-3 flex items-center gap-2">
                                <button
                                    wire:click="configurarAjuste({{ $fp['id'] }})"
                                    class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                    Ajuste
                                </button>
                                @if($fp['permite_cuotas'])
                                    <button
                                        wire:click="configurarCuotas({{ $fp['id'] }})"
                                        class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-blue-500 text-sm font-medium rounded-md text-blue-500 hover:bg-blue-500 hover:text-white transition-colors duration-150"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                        Cuotas
                                    </button>
                                @endif
                            </div>
                        @else
                            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded bg-purple-50 text-purple-600">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    Ajustes y cuotas se configuran en el desglose
                                </span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                        <p class="text-sm">No hay formas de pago activas en el sistema</p>
                    </div>
                @endforelse
            </div>

            <!-- Tabla Desktop -->
            <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-bcn-light dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Forma de Pago
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Concepto
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Ajuste
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($formasPago as $fp)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ !$fp['activo_sucursal'] ? 'opacity-60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $fp['nombre'] }}</div>
                                        @if($fp['descripcion'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $fp['descripcion'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($fp['es_mixta'])
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Mixta
                                            </span>
                                            @if(count($fp['conceptos_permitidos']) > 0)
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ implode(', ', $fp['conceptos_permitidos']) }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-900 text-gray-800">
                                                {{ $fp['concepto_nombre'] ?? ucfirst(str_replace('_', ' ', $fp['concepto'])) }}
                                            </span>
                                            @if($fp['permite_cuotas'])
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    Cuotas
                                                </span>
                                            @endif
                                            @if($fp['factura_fiscal_efectivo'])
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $fp['tiene_factura_fiscal_especifica'] ? 'bg-violet-100 text-violet-800' : 'bg-indigo-100 text-indigo-800' }}">
                                                    Fiscal
                                                    @if($fp['tiene_factura_fiscal_especifica'])
                                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Específico">
                                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($fp['es_mixta'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                Sin ajuste propio
                                            </span>
                                        @elseif($fp['ajuste_efectivo'] > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $fp['tiene_ajuste_especifico'] ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800' }}">
                                                +{{ $fp['ajuste_efectivo'] }}% recargo
                                                @if($fp['tiene_ajuste_especifico'])
                                                    <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Específico">
                                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </span>
                                        @elseif($fp['ajuste_efectivo'] < 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $fp['tiene_ajuste_especifico'] ? 'bg-teal-100 text-teal-800' : 'bg-green-100 text-green-800' }}">
                                                {{ $fp['ajuste_efectivo'] }}% descuento
                                                @if($fp['tiene_ajuste_especifico'])
                                                    <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="Específico">
                                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Sin ajuste</span>
                                        @endif
                                        @if(!$fp['es_mixta'] && $fp['tiene_ajuste_especifico'] && $fp['ajuste_general'] != 0)
                                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                General: {{ $fp['ajuste_general'] > 0 ? '+' : '' }}{{ $fp['ajuste_general'] }}%
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button
                                            wire:click="toggleFormaPago({{ $fp['id'] }})"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $fp['activo_sucursal'] ? 'bg-green-600' : 'bg-gray-300' }}"
                                        >
                                            <span class="sr-only">{{ $fp['activo_sucursal'] ? 'Desactivar' : 'Activar' }}</span>
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white dark:bg-gray-800 shadow transform ring-0 transition ease-in-out duration-200 {{ $fp['activo_sucursal'] ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        @if(!$fp['es_mixta'])
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    wire:click="configurarAjuste({{ $fp['id'] }})"
                                                    class="inline-flex items-center px-3 py-2 border border-bcn-primary rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                                                    title="Configurar ajuste"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                                    </svg>
                                                </button>
                                                @if($fp['permite_cuotas'])
                                                    <button
                                                        wire:click="configurarCuotas({{ $fp['id'] }})"
                                                        class="inline-flex items-center px-3 py-2 border border-blue-500 rounded-md text-blue-500 hover:bg-blue-500 hover:text-white transition-colors duration-150"
                                                        title="Configurar cuotas"
                                                    >
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500 italic">
                                                N/A
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No hay formas de pago activas en el sistema
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                <p class="text-sm">Selecciona una sucursal para configurar las formas de pago</p>
            </div>
        @endif

        <!-- Modal Configurar Ajuste -->
        @if($mostrarModalConfig)
            <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarModalConfig"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Configurar Forma de Pago por Sucursal
                                </h3>
                                <button wire:click="cerrarModalConfig" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 dark:text-gray-400">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-6 space-y-6">
                                <!-- Sección: Ajuste porcentual -->
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Ajuste (Recargo/Descuento)</h4>

                                    <!-- Checkbox usar ajuste general -->
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="usar_ajuste_general"
                                            wire:model.live="usar_ajuste_general"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                        />
                                        <label for="usar_ajuste_general" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            Usar ajuste general de la forma de pago
                                        </label>
                                    </div>

                                    <!-- Campo de ajuste específico -->
                                    <div class="{{ $usar_ajuste_general ? 'opacity-50' : '' }}">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Ajuste específico para esta sucursal (%)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="-100"
                                            max="100"
                                            wire:model="ajuste_porcentaje_sucursal"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            placeholder="0.00"
                                            {{ $usar_ajuste_general ? 'disabled' : '' }}
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Valor positivo = recargo, valor negativo = descuento
                                        </p>
                                    </div>
                                </div>

                                <!-- Sección: Factura Fiscal -->
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-4">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Facturación Fiscal</h4>

                                    <!-- Checkbox usar factura fiscal general -->
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="usar_factura_fiscal_general"
                                            wire:model.live="usar_factura_fiscal_general"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                        />
                                        <label for="usar_factura_fiscal_general" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            Usar configuración general de la forma de pago
                                        </label>
                                    </div>

                                    <!-- Campo de factura fiscal específico -->
                                    <div class="{{ $usar_factura_fiscal_general ? 'opacity-50' : '' }}">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="factura_fiscal_sucursal"
                                                wire:model="factura_fiscal_sucursal"
                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                {{ $usar_factura_fiscal_general ? 'disabled' : '' }}
                                            />
                                            <label for="factura_fiscal_sucursal" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                                Generar factura fiscal automáticamente
                                            </label>
                                        </div>
                                        <p class="mt-1 ml-6 text-xs text-gray-500 dark:text-gray-400">
                                            Si está activado, las ventas con esta forma de pago generarán factura fiscal por defecto
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="guardarAjuste"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Guardar
                            </button>
                            <button
                                type="button"
                                wire:click="cerrarModalConfig"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Modal Configurar Cuotas -->
        @if($mostrarModalCuotas)
            <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarModalCuotas"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Configurar Cuotas por Sucursal
                                </h3>
                                <button wire:click="cerrarModalCuotas" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 dark:text-gray-400">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-6">
                                @if(count($cuotasConfig) > 0)
                                    <div class="space-y-4">
                                        @foreach($cuotasConfig as $cuotaId => $config)
                                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ $config['cantidad_cuotas'] }} {{ $config['cantidad_cuotas'] == 1 ? 'cuota' : 'cuotas' }}
                                                        </span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 dark:text-gray-300">
                                                            General: {{ $config['recargo_general'] }}%
                                                        </span>
                                                    </div>

                                                    <!-- Toggle activo -->
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $config['activo'] ? 'Activa' : 'Inactiva' }}</span>
                                                        <button
                                                            type="button"
                                                            wire:click="$set('cuotasConfig.{{ $cuotaId }}.activo', {{ $config['activo'] ? 'false' : 'true' }})"
                                                            class="relative inline-flex flex-shrink-0 h-5 w-9 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none {{ $config['activo'] ? 'bg-green-600' : 'bg-gray-300' }}"
                                                        >
                                                            <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white dark:bg-gray-800 shadow transform ring-0 transition {{ $config['activo'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                                        </button>
                                                    </div>
                                                </div>

                                                @if($config['descripcion'])
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ $config['descripcion'] }}</p>
                                                @endif

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <!-- Usar recargo general -->
                                                    <div class="flex items-center">
                                                        <input
                                                            type="checkbox"
                                                            id="usar_recargo_{{ $cuotaId }}"
                                                            wire:model.live="cuotasConfig.{{ $cuotaId }}.usar_recargo_general"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                        />
                                                        <label for="usar_recargo_{{ $cuotaId }}" class="ml-2 block text-xs text-gray-700 dark:text-gray-300">
                                                            Usar recargo general
                                                        </label>
                                                    </div>

                                                    <!-- Recargo específico -->
                                                    <div class="{{ $config['usar_recargo_general'] ? 'opacity-50' : '' }}">
                                                        <div class="flex items-center gap-2">
                                                            <label class="text-xs text-gray-700 dark:text-gray-300">Recargo sucursal:</label>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                max="100"
                                                                wire:model="cuotasConfig.{{ $cuotaId }}.recargo_sucursal"
                                                                class="w-24 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm"
                                                                placeholder="0.00"
                                                                {{ $config['usar_recargo_general'] ? 'disabled' : '' }}
                                                            />
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                        No hay planes de cuotas configurados para esta forma de pago
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            @if(count($cuotasConfig) > 0)
                                <button
                                    type="button"
                                    wire:click="guardarCuotas"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    Guardar
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="cerrarModalCuotas"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
