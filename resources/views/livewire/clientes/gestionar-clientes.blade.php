<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Gestión de Clientes') }}</h2>
                        <!-- Botones móvil -->
                        <div class="sm:hidden flex gap-2">
                            <button
                                wire:click="openImportModal"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Importar') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </button>
                            <button
                                wire:click="exportarExcel"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Exportar') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </button>
                            <button
                                wire:click="create"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Nuevo Cliente') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los clientes de tu negocio') }}</p>
                </div>
                <!-- Botones Desktop -->
                <div class="hidden sm:flex gap-3">
                    <button
                        wire:click="openImportModal"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Importar desde CSV') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {{ __('Importar') }}
                    </button>
                    <button
                        wire:click="exportarExcel"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Exportar a Excel') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {{ __('Exportar') }}
                    </button>
                    <button
                        wire:click="create"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nuevo Cliente') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <div class="flex flex-col gap-4">
                <!-- Búsqueda y toggle filtros -->
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Buscar por nombre, CUIT, email, teléfono...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <button
                        wire:click="toggleFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary"
                    >
                        <svg class="w-5 h-5 {{ $showFilters ? 'text-bcn-primary' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </button>
                </div>

                <!-- Filtros expandibles -->
                @if($showFilters)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                            <select
                                wire:model.live="filterStatus"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="active">{{ __('Activos') }}</option>
                                <option value="inactive">{{ __('Inactivos') }}</option>
                            </select>
                        </div>

                        <!-- Condición IVA -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Condición IVA') }}</label>
                            <select
                                wire:model.live="filterCondicionIva"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todas') }}</option>
                                @foreach($condicionesIva as $condicion)
                                    <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Cuenta Corriente -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuenta Corriente') }}</label>
                            <select
                                wire:model.live="filterCuentaCorriente"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="con_cc">{{ __('Con Cta. Cte.') }}</option>
                                <option value="sin_cc">{{ __('Sin Cta. Cte.') }}</option>
                                <option value="con_deuda">{{ __('Con deuda') }}</option>
                            </select>
                        </div>

                        <!-- Sucursal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursal') }}</label>
                            <select
                                wire:model.live="filterSucursal"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todas') }}</option>
                                @foreach($sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Vinculación -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Vinculación') }}</label>
                            <select
                                wire:model.live="filterVinculacion"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todos') }}</option>
                                <option value="con_proveedor">{{ __('Es proveedor') }}</option>
                                <option value="sin_proveedor">{{ __('Solo cliente') }}</option>
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($clientes as $cliente)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ !$cliente->activo ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center flex-wrap gap-2">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cliente->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $cliente->activo ? __('Activo') : __('Inactivo') }}
                                </span>
                                @if($cliente->proveedor)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                        <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                                        </svg>
                                        {{ __('Prov.') }}
                                    </span>
                                @endif
                            </div>
                            @if($cliente->cuit)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CUIT: {{ $cliente->cuit }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $cliente->condicionIva?->nombre ?? __('Consumidor Final') }}</p>

                            @if($cliente->tiene_cuenta_corriente || $cliente->proveedor)
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if($cliente->tiene_cuenta_corriente)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ __('Cta. Cte.') }}
                                        </span>
                                        @if($cliente->saldo_deudor_cache > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                {{ __('Deuda') }}: ${{ number_format($cliente->saldo_deudor_cache, 2, ',', '.') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button
                            wire:click="edit({{ $cliente->id }})"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Editar') }}
                        </button>
                        <button
                            wire:click="openSucursalesConfig({{ $cliente->id }})"
                            class="inline-flex items-center justify-center px-3 py-2 border border-green-300 dark:border-green-600 text-sm font-medium rounded-md text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-gray-700 transition-colors duration-150"
                            title="{{ __('Configurar sucursales') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </button>
                        <button
                            wire:click="showHistorial({{ $cliente->id }})"
                            class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                            title="{{ __('Ver historial') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron clientes') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $clientes->links() }}
            </div>
        </div>

        <!-- Tabla Desktop -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Cliente') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Contacto') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Condición IVA') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Cuenta Corriente') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Estado') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Acciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($clientes as $cliente)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ !$cliente->activo ? 'opacity-60' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</span>
                                        @if($cliente->proveedor)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300" title="{{ __('También es proveedor') }}">
                                                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                                                </svg>
                                                {{ __('Prov.') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($cliente->razon_social && $cliente->razon_social !== $cliente->nombre)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cliente->razon_social }}</div>
                                    @endif
                                    @if($cliente->cuit)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">CUIT: {{ $cliente->cuit }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($cliente->email)
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $cliente->email }}</div>
                                    @endif
                                    @if($cliente->telefono)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cliente->telefono }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                        {{ $cliente->condicionIva?->nombre ?? __('Consumidor Final') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($cliente->tiene_cuenta_corriente)
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 w-fit">
                                                {{ __('Cta. Cte.') }}
                                            </span>
                                            @if($cliente->saldo_deudor_cache > 0)
                                                <span class="text-xs text-red-600 font-medium">
                                                    {{ __('Deuda') }}: ${{ number_format($cliente->saldo_deudor_cache, 2, ',', '.') }}
                                                </span>
                                            @elseif($cliente->saldo_a_favor_cache > 0)
                                                <span class="text-xs text-green-600 font-medium">
                                                    {{ __('A favor') }}: ${{ number_format($cliente->saldo_a_favor_cache, 2, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-500">{{ __('Sin saldo') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">{{ __('No habilitada') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <button
                                        wire:click="toggleStatus({{ $cliente->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $cliente->activo ? 'bg-green-600' : 'bg-gray-300' }}"
                                    >
                                        <span class="sr-only">{{ $cliente->activo ? __('Desactivar') : __('Activar') }}</span>
                                        <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $cliente->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            wire:click="openSucursalesConfig({{ $cliente->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-green-300 dark:border-green-600 rounded-md text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-gray-700 transition-colors duration-150"
                                            title="{{ __('Configurar sucursales y precios') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="showHistorial({{ $cliente->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                                            title="{{ __('Ver historial de ventas') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="edit({{ $cliente->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-bcn-primary rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                                            title="{{ __('Editar cliente') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="confirmDelete({{ $cliente->id }})"
                                            class="inline-flex items-center px-3 py-2 border border-red-300 dark:border-red-600 rounded-md text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-700 transition-colors duration-150"
                                            title="{{ __('Eliminar cliente') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron clientes') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                {{ $clientes->links() }}
            </div>
        </div>

        <!-- Modal Crear/Editar Cliente -->
        @if($showModal)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                        <form wire:submit="save">
                            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {{ $editMode ? __('Editar Cliente') : __('Nuevo Cliente') }}
                                    </h3>
                                    <button type="button" wire:click="closeModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-6 space-y-6">
                                    <!-- Datos básicos -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                                            <input
                                                type="text"
                                                wire:model="nombre"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="{{ __('Nombre del cliente') }}"
                                            />
                                            @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Razón Social') }}</label>
                                            <input
                                                type="text"
                                                wire:model="razon_social"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="{{ __('Razón social (si difiere del nombre)') }}"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('CUIT') }}</label>
                                            <input
                                                type="text"
                                                wire:model="cuit"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="20-12345678-9"
                                            />
                                            @error('cuit') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email') }}</label>
                                            <input
                                                type="email"
                                                wire:model="email"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="cliente@email.com"
                                            />
                                            @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Teléfono') }}</label>
                                            <input
                                                type="text"
                                                wire:model="telefono"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="{{ __('Ej: +54 11 1234-5678') }}"
                                            />
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Dirección') }}</label>
                                            <input
                                                type="text"
                                                wire:model="direccion"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                placeholder="{{ __('Dirección completa') }}"
                                            />
                                        </div>
                                    </div>

                                    <!-- Configuración fiscal -->
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Configuración Fiscal') }}</h4>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Condición IVA') }}</label>
                                            <select
                                                wire:model="condicion_iva_id"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                            >
                                                @foreach($condicionesIva as $condicion)
                                                    <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ __('Las listas de precios se configuran por sucursal después de guardar el cliente.') }}
                                        </p>
                                    </div>

                                    <!-- Cuenta Corriente -->
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                        <div class="flex items-center mb-3">
                                            <input
                                                type="checkbox"
                                                id="tiene_cuenta_corriente"
                                                wire:model.live="tiene_cuenta_corriente"
                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                            />
                                            <label for="tiene_cuenta_corriente" class="ml-2 text-sm font-medium text-gray-900 dark:text-white">
                                                {{ __('Habilitar Cuenta Corriente') }}
                                            </label>
                                        </div>

                                        @if($tiene_cuenta_corriente)
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Límite de Crédito') }}</label>
                                                    <div class="relative">
                                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            wire:model="limite_credito"
                                                            class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                            placeholder="0.00"
                                                        />
                                                    </div>
                                                    <p class="text-xs text-gray-500 mt-1">{{ __('0 = sin límite') }}</p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Días de Crédito') }}</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="365"
                                                        wire:model="dias_credito"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Interés Mensual (%)') }}</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        wire:model="tasa_interes_mensual"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Vinculación Proveedor -->
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                        <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    id="tambien_es_proveedor"
                                                    wire:model.live="tambien_es_proveedor"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500"
                                                />
                                                <label for="tambien_es_proveedor" class="ml-2 text-sm font-medium text-purple-900 dark:text-purple-300">
                                                    {{ __('También es Proveedor') }}
                                                </label>
                                            </div>

                                            @if($tambien_es_proveedor)
                                                <div class="mt-3">
                                                    <label class="block text-sm font-medium text-purple-900 dark:text-purple-300 mb-1">{{ __('Vincular a') }}</label>
                                                    <select
                                                        wire:model="proveedor_opcion"
                                                        class="w-full rounded-md border-purple-300 dark:border-purple-600 dark:bg-purple-900/30 dark:text-white shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm"
                                                    >
                                                        <option value="crear_nuevo">{{ __('+ Crear nuevo proveedor') }}</option>
                                                        @foreach($proveedoresDisponibles as $prov)
                                                            <option value="{{ $prov->id }}">
                                                                {{ $prov->nombre }}
                                                                @if($prov->cuit) ({{ $prov->cuit }}) @endif
                                                                @if($prov->cliente_id == $clienteId) - {{ __('Vinculado') }} @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <p class="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                                        @if($proveedor_opcion === 'crear_nuevo')
                                                            {{ __('Se creará un nuevo proveedor con los datos de este cliente.') }}
                                                        @else
                                                            {{ __('Se vinculará al proveedor seleccionado para cuentas corrientes unificadas.') }}
                                                        @endif
                                                    </p>
                                                </div>
                                            @else
                                                <p class="mt-2 text-xs text-purple-600 dark:text-purple-400">
                                                    {{ __('Active esta opción para vincular este cliente con un proveedor existente o crear uno nuevo.') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Sucursales -->
                                    @if($sucursales->count() > 0)
                                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Sucursales donde estará disponible') }}</label>
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
                                        </div>
                                    @endif

                                    <!-- Estado activo -->
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="activo"
                                            wire:model="activo"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                        />
                                        <label for="activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Cliente activo') }}</label>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button
                                    type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ $editMode ? __('Actualizar') : __('Crear') }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="closeModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ __('Cancelar') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Modal Confirmar Eliminación -->
        @if($showDeleteModal)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeDeleteModal"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">{{ __('Eliminar Cliente') }}</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('¿Estás seguro de eliminar el cliente') }} <strong>{{ $nombreClienteAEliminar }}</strong>? {{ __('Esta acción no se puede deshacer.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="delete"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Eliminar') }}
                            </button>
                            <button
                                type="button"
                                wire:click="closeDeleteModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Modal Historial de Ventas -->
        @if($showHistorialModal)
            <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeHistorialModal"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ __('Historial de Ventas') }} - {{ $nombreClienteHistorial }}
                                </h3>
                                <button type="button" wire:click="closeHistorialModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-4 max-h-96 overflow-y-auto">
                                @if($ventasHistorial->count() > 0)
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Fecha') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Sucursal') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Forma Pago') }}</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Total') }}</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Estado') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($ventasHistorial as $venta)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $venta->sucursal?->nombre ?? '-' }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $venta->formaPago?->nombre ?? '-' }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right font-medium">${{ number_format($venta->total, 2, ',', '.') }}</td>
                                                    <td class="px-4 py-2 text-center">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $venta->estado === 'completada' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                            {{ ucfirst($venta->estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="mt-2 text-sm">{{ __('No hay ventas registradas para este cliente') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                wire:click="closeHistorialModal"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cerrar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal de Configuración de Sucursales (Listas de Precios) --}}
        @if($showSucursalesModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeSucursalesModal"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ __('Configuración por Sucursal') }} - {{ $clienteConfigNombre }}
                                </h3>
                                <button type="button" wire:click="closeSucursalesModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-4 space-y-4 max-h-96 overflow-y-auto">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('Configure la lista de precios para cada sucursal donde el cliente está activo.') }}
                                </p>

                                @foreach($sucursales as $sucursal)
                                    @php
                                        $isActive = isset($sucursalesConfig[$sucursal->id]) && $sucursalesConfig[$sucursal->id]['activo'];
                                        $listasPrecio = $this->getListasPrecioSucursal($sucursal->id);
                                        $listaPrecioSeleccionada = $sucursalesConfig[$sucursal->id]['lista_precio_id'] ?? null;
                                    @endphp
                                    <div class="p-4 border rounded-lg {{ $isActive ? 'border-indigo-300 dark:border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50' }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        class="sr-only peer"
                                                        wire:click="toggleSucursalConfig({{ $sucursal->id }})"
                                                        {{ $isActive ? 'checked' : '' }}
                                                    >
                                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-indigo-600"></div>
                                                </label>
                                                <div>
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $sucursal->nombre }}</span>
                                                    @if($sucursal->direccion)
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sucursal->direccion }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full {{ $isActive ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                                {{ $isActive ? __('Activo') : __('Inactivo') }}
                                            </span>
                                        </div>

                                        @if($isActive)
                                            <div class="mt-3 pl-14">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    {{ __('Lista de Precios') }}
                                                </label>
                                                <select
                                                    wire:model.live="sucursalesConfig.{{ $sucursal->id }}.lista_precio_id"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                >
                                                    <option value="">{{ __('Sin lista asignada') }}</option>
                                                    @foreach($listasPrecio as $lista)
                                                        <option value="{{ $lista->id }}">
                                                            {{ $lista->nombre }}
                                                            @if($lista->es_lista_base) ({{ __('Base') }}) @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                @if($sucursales->isEmpty())
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <p class="mt-2 text-sm">{{ __('No hay sucursales configuradas') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button
                                type="button"
                                wire:click="saveSucursalesConfig"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ __('Guardar Configuración') }}
                            </button>
                            <button
                                type="button"
                                wire:click="closeSucursalesModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal de Importación --}}
        @if($showImportModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeImportModal"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ __('Importar Clientes') }}
                                </h3>
                                <button type="button" wire:click="closeImportModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-4 space-y-4">
                                @if(!$importacionProcesada)
                                    {{-- Instrucciones --}}
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                        <div class="flex">
                                            <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                                <p class="font-medium mb-1">{{ __('Formato del archivo CSV:') }}</p>
                                                <ul class="list-disc list-inside space-y-1 text-xs">
                                                    <li>{{ __('Columnas: Nombre, Razón Social, CUIT, Email, Teléfono, Dirección, Condición IVA, Cuenta Corriente, Límite Crédito, Estado') }}</li>
                                                    <li>{{ __('Separador: punto y coma (;) o coma (,)') }}</li>
                                                    <li>{{ __('Puede usar el archivo exportado como plantilla') }}</li>
                                                    <li>{{ __('Los clientes con CUIT duplicado serán omitidos') }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Archivo --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('Archivo CSV') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="file"
                                            wire:model="archivoImportacion"
                                            accept=".csv,.txt"
                                            class="block w-full text-sm text-gray-500 dark:text-gray-400
                                                file:mr-4 file:py-2 file:px-4
                                                file:rounded-md file:border-0
                                                file:text-sm file:font-semibold
                                                file:bg-bcn-primary file:text-white
                                                hover:file:bg-opacity-90
                                                file:cursor-pointer cursor-pointer"
                                        />
                                        @error('archivoImportacion')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                        <div wire:loading wire:target="archivoImportacion" class="mt-2 text-sm text-gray-500">
                                            {{ __('Cargando archivo...') }}
                                        </div>
                                    </div>

                                    {{-- Sucursales --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('Dar de alta en las siguientes sucursales:') }} <span class="text-red-500">*</span>
                                        </label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                                            @foreach($sucursales as $sucursal)
                                                <label class="flex items-center space-x-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="sucursales_importacion"
                                                        value="{{ $sucursal->id }}"
                                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                    >
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $sucursal->nombre }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('sucursales_importacion')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Los clientes se darán de alta con la lista de precios base de cada sucursal seleccionada.') }}
                                        </p>
                                    </div>
                                @else
                                    {{-- Resultado de la importación --}}
                                    <div class="space-y-4">
                                        <div class="text-center py-4">
                                            @if($importacionResultado['exitosos'] > 0)
                                                <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @else
                                                <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-3 gap-4 text-center">
                                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $importacionResultado['exitosos'] }}</p>
                                                <p class="text-xs text-green-700 dark:text-green-300">{{ __('Importados') }}</p>
                                            </div>
                                            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">
                                                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $importacionResultado['omitidos'] }}</p>
                                                <p class="text-xs text-yellow-700 dark:text-yellow-300">{{ __('Omitidos') }}</p>
                                            </div>
                                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                                                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ count($importacionResultado['errores']) }}</p>
                                                <p class="text-xs text-red-700 dark:text-red-300">{{ __('Errores') }}</p>
                                            </div>
                                        </div>

                                        @if(count($importacionResultado['errores']) > 0)
                                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 max-h-40 overflow-y-auto">
                                                <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-2">{{ __('Errores encontrados:') }}</p>
                                                <ul class="text-xs text-red-600 dark:text-red-400 space-y-1">
                                                    @foreach($importacionResultado['errores'] as $error)
                                                        <li>• {{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            @if(!$importacionProcesada)
                                <button
                                    type="button"
                                    wire:click="importarClientes"
                                    wire:loading.attr="disabled"
                                    wire:target="importarClientes,archivoImportacion"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:w-auto sm:text-sm disabled:opacity-50"
                                >
                                    <svg wire:loading wire:target="importarClientes" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg wire:loading.remove wire:target="importarClientes" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    {{ __('Importar') }}
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="closeImportModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ $importacionProcesada ? __('Cerrar') : __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
