<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Formas de Pago') }}</h2>
                        <!-- Botones de acción - Solo iconos en móviles -->
                        <div class="sm:hidden flex gap-2">
                            <a
                                href="{{ route('configuracion.formas-pago-sucursal') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Configurar por sucursal') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </a>
                            <button
                                wire:click="crear"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Crear nueva forma de pago') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra las formas de pago y planes de cuotas') }}</p>
                </div>
                <!-- Botones de acciones - Desktop -->
                <div class="hidden sm:flex gap-3">
                    <a
                        href="{{ route('configuracion.formas-pago-sucursal') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Configurar por sucursal') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        {{ __('Por Sucursal') }}
                    </a>
                    <button
                        wire:click="crear"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Crear nueva forma de pago') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nueva Forma de Pago') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Búsqueda -->
                <div>
                    <label for="busqueda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                    <input
                        type="text"
                        id="busqueda"
                        wire:model.live.debounce.300ms="busqueda"
                        :placeholder="__('Nombre, concepto o descripción...')"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    />
                </div>

                <!-- Filtro de estado -->
                <div>
                    <label for="filtroActivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                    <select
                        id="filtroActivo"
                        wire:model.live="filtroActivo"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="todos">{{ __('Todas') }}</option>
                        <option value="activos">{{ __('Activas') }}</option>
                        <option value="inactivos">{{ __('Inactivas') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($formasPago as $formaPago)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $formaPago->nombre }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $formaPago->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $formaPago->activo ? __('Activa') : __('Inactiva') }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if($formaPago->es_mixta)
                                    <span class="text-purple-600 font-medium">{{ __('Mixta') }}</span>
                                @else
                                    {{ __($formaPago->conceptoPago?->nombre ?? ucfirst(str_replace('_', ' ', $formaPago->concepto))) }}
                                @endif
                            </p>
                            @if($formaPago->descripcion)
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $formaPago->descripcion }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                @if($formaPago->permite_cuotas)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                        {{ __('Permite cuotas') }}
                                    </span>
                                @endif
                                @if($formaPago->factura_fiscal)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                        {{ __('Factura fiscal') }}
                                    </span>
                                @endif
                                @if($formaPago->ajuste_porcentaje > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-red-100 text-red-800">
                                        +{{ $formaPago->ajuste_porcentaje }}% {{ __('recargo') }}
                                    </span>
                                @elseif($formaPago->ajuste_porcentaje < 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-800">
                                        {{ $formaPago->ajuste_porcentaje }}% {{ __('descuento') }}
                                    </span>
                                @endif
                            </div>
                            <!-- Sucursales -->
                            @if($formaPago->sucursales->count() > 0)
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($formaPago->sucursales as $sucursal)
                                        @php
                                            $initials = collect(explode(' ', $sucursal->nombre))
                                                ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                                                ->join('');
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800" title="{{ $sucursal->nombre }}">
                                            {{ $initials }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-2">
                                    <span class="text-xs text-gray-400 italic">{{ __('Sin sucursales asignadas') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button
                            wire:click="edit({{ $formaPago->id }})"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Editar') }}
                        </button>
                        @if($formaPago->permite_cuotas)
                            <button
                                wire:click="gestionarCuotas({{ $formaPago->id }})"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-blue-500 text-sm font-medium rounded-md text-blue-500 hover:bg-blue-500 hover:text-white transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                {{ __('Cuotas') }}
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron formas de pago') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $formasPago->links() }}
            </div>
        </div>

        <!-- Tabla de formas de pago (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Forma de Pago') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Concepto') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Características') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Sucursales') }}
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
                        @forelse($formasPago as $formaPago)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $formaPago->nombre }}</div>
                                    @if($formaPago->descripcion)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $formaPago->descripcion }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($formaPago->es_mixta)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            {{ __('Mixta') }}
                                        </span>
                                        @if($formaPago->conceptosPermitidos->count() > 0)
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach($formaPago->conceptosPermitidos->take(3) as $concepto)
                                                    <span class="text-xs text-gray-500">{{ __($concepto->nombre) }}</span>
                                                    @if(!$loop->last)<span class="text-xs text-gray-400">·</span>@endif
                                                @endforeach
                                                @if($formaPago->conceptosPermitidos->count() > 3)
                                                    <span class="text-xs text-gray-400">+{{ $formaPago->conceptosPermitidos->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ __($formaPago->conceptoPago?->nombre ?? ucfirst(str_replace('_', ' ', $formaPago->concepto))) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @if($formaPago->permite_cuotas)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                                {{ __('Cuotas') }}
                                            </span>
                                        @endif
                                        @if($formaPago->factura_fiscal)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                                {{ __('Fiscal') }}
                                            </span>
                                        @endif
                                        @if($formaPago->ajuste_porcentaje > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                +{{ $formaPago->ajuste_porcentaje }}% {{ __('recargo') }}
                                            </span>
                                        @elseif($formaPago->ajuste_porcentaje < 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                {{ $formaPago->ajuste_porcentaje }}% {{ __('descuento') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($formaPago->sucursales->count() > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($formaPago->sucursales as $sucursal)
                                                @php
                                                    $initials = collect(explode(' ', $sucursal->nombre))
                                                        ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                                                        ->join('');
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800" title="{{ $sucursal->nombre }}">
                                                    {{ $initials }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">{{ __('Sin asignar') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        wire:click="toggleStatus({{ $formaPago->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary {{ $formaPago->activo ? 'bg-green-600' : 'bg-gray-300' }}"
                                    >
                                        <span class="sr-only">{{ $formaPago->activo ? __('Desactivar') : __('Activar') }} {{ __('forma de pago') }}</span>
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $formaPago->activo ? 'translate-x-5' : 'translate-x-0' }}"
                                        ></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600">
                                        {{ $formaPago->activo ? __('Activa') : __('Inactiva') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($formaPago->permite_cuotas)
                                            <button
                                                wire:click="gestionarCuotas({{ $formaPago->id }})"
                                                class="inline-flex items-center px-3 py-2 border border-blue-500 rounded-md text-blue-500 hover:bg-blue-500 hover:text-white transition-colors duration-150"
                                                title="{{ __('Gestionar cuotas') }}"
                                            >
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                                {{ __('Cuotas') }}
                                            </button>
                                        @endif
                                        <button
                                            wire:click="edit({{ $formaPago->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-bcn-primary rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                                            title="{{ __('Editar forma de pago') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron formas de pago') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                {{ $formasPago->links() }}
            </div>
        </div>

        <!-- Modal Crear/Editar Forma de Pago -->
        @if($mostrarModal)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarModal"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $modoEdicion ? __('Editar Forma de Pago') : __('Nueva Forma de Pago') }}
                                </h3>
                                <button wire:click="cerrarModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Nombre -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('Nombre') }} *
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="nombre"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            :placeholder="__('Ej: Tarjeta de Crédito Visa')"
                                        />
                                        @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Tipo de forma de pago (Simple o Mixta) -->
                                    <div class="sm:col-span-2 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <label class="flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                id="es_mixta"
                                                wire:model.live="es_mixta"
                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                            />
                                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Es forma de pago mixta') }}</span>
                                        </label>
                                        <p class="mt-1 ml-6 text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Una forma de pago mixta permite combinar múltiples conceptos de pago (efectivo + tarjeta + transferencia, etc.)') }}
                                        </p>
                                    </div>

                                    @if(!$es_mixta)
                                        <!-- FORMA DE PAGO SIMPLE -->
                                        <!-- Concepto -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                {{ __('Concepto de pago') }} *
                                            </label>
                                            <select
                                                wire:model="concepto_pago_id"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            >
                                                <option value="">{{ __('Seleccionar concepto...') }}</option>
                                                @foreach($conceptosPago as $concepto)
                                                    <option value="{{ $concepto->id }}">{{ __($concepto->nombre) }}</option>
                                                @endforeach
                                            </select>
                                            @error('concepto_pago_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <!-- Permite Cuotas y Factura Fiscal -->
                                        <div class="flex items-center gap-6">
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="permite_cuotas"
                                                    wire:model="permite_cuotas"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                />
                                                <label for="permite_cuotas" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Permite cuotas') }}</label>
                                            </div>
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="factura_fiscal"
                                                    wire:model="factura_fiscal"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                />
                                                <label for="factura_fiscal" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Factura fiscal') }}</label>
                                            </div>
                                        </div>

                                        <!-- Ajuste Porcentaje -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                {{ __('Ajuste (%)') }}
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="-100"
                                                    max="100"
                                                    wire:model="ajuste_porcentaje"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ __('Valor positivo = recargo, valor negativo = descuento. Ej: 5 = +5% recargo, -10 = 10% descuento') }}
                                            </p>
                                            @error('ajuste_porcentaje') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    @else
                                        <!-- FORMA DE PAGO MIXTA -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                                {{ __('Conceptos de pago permitidos') }} *
                                            </label>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                                {{ __('Seleccione los conceptos que podrán usarse con esta forma de pago mixta (mínimo 2).') }}
                                            </p>
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                                @foreach($conceptosPago as $concepto)
                                                    <label class="flex items-center p-3 bg-white dark:bg-gray-700 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors {{ in_array($concepto->id, $conceptos_permitidos) ? 'border-bcn-primary bg-bcn-light dark:bg-gray-600' : 'border-gray-200 dark:border-gray-600' }}">
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $concepto->id }}"
                                                            wire:model="conceptos_permitidos"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                        />
                                                        <div class="ml-2">
                                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __($concepto->nombre) }}</span>
                                                            @if($concepto->permite_cuotas)
                                                                <span class="block text-xs text-blue-600">{{ __('Permite cuotas') }}</span>
                                                            @endif
                                                            @if($concepto->permite_vuelto)
                                                                <span class="block text-xs text-green-600">{{ __('Permite vuelto') }}</span>
                                                            @endif
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('conceptos_permitidos') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="sm:col-span-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                            <div class="flex items-start">
                                                <svg class="w-5 h-5 text-amber-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                <div class="text-xs text-amber-700">
                                                    <p class="font-medium">{{ __('Nota sobre formas de pago mixtas:') }}</p>
                                                    <ul class="mt-1 list-disc list-inside space-y-1">
                                                        <li>{{ __('No tienen ajuste (recargo/descuento) propio') }}</li>
                                                        <li>{{ __('Los ajustes se aplican por cada forma de pago usada en el desglose') }}</li>
                                                        <li>{{ __('Las cuotas se configuran en las formas de pago individuales') }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Descripción -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('Descripción') }}
                                        </label>
                                        <textarea
                                            wire:model="descripcion"
                                            rows="3"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            :placeholder="__('Descripción opcional de la forma de pago...')"
                                        ></textarea>
                                        @error('descripcion') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Estado activo -->
                                    <div class="sm:col-span-2 flex items-center">
                                        <input
                                            type="checkbox"
                                            id="activo"
                                            wire:model="activo"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                        />
                                        <label for="activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Forma de pago activa') }}</label>
                                    </div>

                                    <!-- Sucursales -->
                                    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                            {{ __('Sucursales donde estará disponible') }}
                                        </label>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                            @foreach($sucursales as $sucursal)
                                                <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $sucursal->id }}"
                                                        wire:model="sucursales_seleccionadas"
                                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                    />
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $sucursal->nombre }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if($sucursales->count() == 0)
                                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">{{ __('No hay sucursales configuradas') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="guardar"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $modoEdicion ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                wire:click="cerrarModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Modal Gestionar Cuotas -->
        @if($gestionandoCuotas)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cerrarGestionCuotas"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ __('Gestionar Planes de Cuotas') }}
                                </h3>
                                <button wire:click="cerrarGestionCuotas" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Listado de cuotas existentes -->
                            <div class="mt-6">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Planes Configurados') }}</h4>

                                @if(count($cuotas) > 0)
                                    <div class="space-y-2 mb-6">
                                        @foreach($cuotas as $cuota)
                                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ $cuota['cantidad_cuotas'] }} {{ $cuota['cantidad_cuotas'] == 1 ? __('cuota') : __('cuotas') }}
                                                        </span>
                                                        @if($cuota['recargo_porcentaje'] > 0)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                                +{{ $cuota['recargo_porcentaje'] }}% {{ __('recargo') }}
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                                {{ __('Sin recargo') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($cuota['descripcion'])
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $cuota['descripcion'] }}</p>
                                                    @endif
                                                </div>
                                                <button
                                                    wire:click="eliminarCuota({{ $cuota['id'] }})"
                                                    class="ml-4 text-red-600 hover:text-red-800"
                                                    title="{{ __('Eliminar plan') }}"
                                                >
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 italic">{{ __('No hay planes de cuotas configurados') }}</p>
                                @endif

                                <!-- Agregar nuevo plan de cuotas -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Agregar Nuevo Plan') }}</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ __('Cantidad de cuotas') }} *
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="99"
                                                wire:model="nuevaCuota.cantidad_cuotas"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="3"
                                            />
                                            @error('nuevaCuota.cantidad_cuotas') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ __('Recargo (%)') }} *
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                wire:model="nuevaCuota.recargo_porcentaje"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="0.00"
                                            />
                                            @error('nuevaCuota.recargo_porcentaje') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ __('Descripción') }}
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="nuevaCuota.descripcion"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                :placeholder="__('Opcional')"
                                            />
                                            @error('nuevaCuota.descripcion') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button
                                            type="button"
                                            wire:click="agregarCuota"
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary"
                                        >
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            {{ __('Agregar Plan') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="cerrarGestionCuotas"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-gray-500 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cerrar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
