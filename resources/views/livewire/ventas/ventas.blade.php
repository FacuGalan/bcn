{{--
    Vista Livewire: Ventas / POS (Point of Sale)

    DESCRIPCIÓN:
    ===========
    Vista principal del módulo de ventas con interfaz POS (Point of Sale).
    Permite listar ventas existentes, crear nuevas ventas mediante un
    sistema de carrito, aplicar descuentos, y gestionar el flujo completo
    de una venta desde la selección de productos hasta el cobro.

    SECCIONES PRINCIPALES:
    =====================
    1. Barra de búsqueda y botón "Nueva Venta"
    2. Filtros (estado, forma de pago, fechas)
    3. Tabla de ventas existentes con paginación
    4. Modal POS para crear nueva venta:
       - Búsqueda y selección de artículos
       - Carrito con artículos seleccionados
       - Cálculo automático de totales
       - Selección de cliente y forma de pago
       - Selección de caja
    5. Modal de detalles de venta

    COMPONENTE LIVEWIRE:
    ===================
    @see App\Livewire\Ventas\Ventas

    FASE 4 - Sistema Multi-Sucursal (Vistas Livewire)
--}}

<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Listado de Ventas') }}</h2>
                        <!-- Botón Nueva Venta - Solo icono en móviles -->
                        <div class="sm:hidden">
                            <a
                                href="{{ route('ventas.create') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                :title="__('Nueva Venta')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Gestión y consulta de ventas realizadas') }}</p>
                </div>
                <!-- Botón Nueva Venta - Desktop -->
                <div class="hidden sm:flex gap-3">
                    <a
                        href="{{ route('ventas.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        :title="__('Crear nueva venta')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nueva Venta') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <!-- Botón de filtros (solo móvil) -->
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="toggleFilters"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($search || $filterEstado !== 'all' || $filterFormaPago !== 'all' || $filterCaja !== 'actual' || $filterComprobanteFiscal !== 'all' || $filterFechaDesde || $filterFechaHasta)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
                            </span>
                        @endif
                    </span>
                    <svg
                        class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Contenedor de filtros -->
            <div x-data="{ showAdvanced: false }" class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                {{-- Filtros principales --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Búsqueda -->
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('ID venta, ticket, cliente, factura...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro Fecha Desde -->
                    <div>
                        <label for="filterFechaDesde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha Desde') }}</label>
                        <input
                            wire:model.live="filterFechaDesde"
                            type="date"
                            id="filterFechaDesde"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                    </div>

                    <!-- Filtro Fecha Hasta -->
                    <div>
                        <label for="filterFechaHasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha Hasta') }}</label>
                        <input
                            wire:model.live="filterFechaHasta"
                            type="date"
                            id="filterFechaHasta"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                    </div>

                    <!-- Filtro Estado -->
                    <div>
                        <label for="filterEstado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                        <select
                            wire:model.live="filterEstado"
                            id="filterEstado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todos') }}</option>
                            <option value="completada">{{ __('Completada') }}</option>
                            <option value="pendiente">{{ __('Pendiente') }}</option>
                            <option value="cancelada">{{ __('Cancelada') }}</option>
                        </select>
                    </div>

                    <!-- Filtro Forma de Pago -->
                    <div>
                        <label for="filterFormaPago" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de Pago') }}</label>
                        <select
                            wire:model.live="filterFormaPago"
                            id="filterFormaPago"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todas') }}</option>
                            @foreach($formasPago as $fp)
                                <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Filtros avanzados (colapsables) --}}
                <div x-show="showAdvanced" x-collapse>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                        <!-- Filtro Comprobante Fiscal -->
                        <div>
                            <label for="filterComprobanteFiscal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Comprobante Fiscal') }}</label>
                            <select
                                wire:model.live="filterComprobanteFiscal"
                                id="filterComprobanteFiscal"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="all">{{ __('Todas') }}</option>
                                <option value="con">{{ __('Con factura') }}</option>
                                <option value="sin">{{ __('Sin factura') }}</option>
                            </select>
                        </div>

                        <!-- Filtro Caja -->
                        <div>
                            <label for="filterCaja" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Caja') }}</label>
                            <select
                                wire:model.live="filterCaja"
                                id="filterCaja"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="actual">{{ __('Caja actual') }}</option>
                                <option value="all">{{ __('Todas mis cajas') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Acciones: toggle avanzados + limpiar --}}
                <div class="flex items-center justify-between gap-2 mt-4">
                    <button
                        type="button"
                        @click="showAdvanced = !showAdvanced"
                        class="inline-flex items-center text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-bcn-primary transition-colors"
                    >
                        <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-180': showAdvanced }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        <span x-text="showAdvanced ? '{{ __('Ocultar filtros avanzados') }}' : '{{ __('Mostrar filtros avanzados') }}'"></span>
                        @if($filterComprobanteFiscal !== 'all' || $filterCaja !== 'actual')
                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
                            </span>
                        @endif
                    </button>

                    @if($search || $filterEstado !== 'all' || $filterFormaPago !== 'all' || $filterCaja !== 'actual' || $filterComprobanteFiscal !== 'all' || $filterFechaDesde || $filterFechaHasta)
                        <button
                            wire:click="resetFilters"
                            class="text-sm text-bcn-primary hover:text-bcn-secondary font-medium inline-flex items-center px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ __('Limpiar filtros') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($ventas as $venta)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $venta->numero }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $venta->cliente->nombre ?? __('Consumidor Final') }}</div>
                            @if($venta->comprobantesFiscales->count() > 0)
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($venta->comprobantesFiscales as $cf)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                            <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            {{ $cf->letra }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button
                                wire:click="verDetalle({{ $venta->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                :title="__('Ver detalle')"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                            @if($venta->estado !== 'cancelada')
                                <button
                                    wire:click="cancelarVenta({{ $venta->id }})"
                                    class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                    :title="__('Cancelar venta')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-3">
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $venta->estado === 'completada' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                {{ $venta->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                {{ $venta->estado === 'cancelada' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}">
                                {{ ucfirst($venta->estado) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                {{ $venta->formaPago->nombre ?? 'N/A' }}
                            </span>
                            @if($venta->caja)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                    {{ $venta->caja->nombre }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $venta->fecha->format('d/m/Y H:i') }}
                        </span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            $@precio($venta->total_final ?? $venta->total)
                        </span>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="mt-2 text-sm font-medium">{{ __('No hay ventas registradas') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('Comienza creando tu primera venta') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            @if($ventas->hasPages())
                <div class="mt-4">
                    {{ $ventas->links() }}
                </div>
            @endif
        </div>

        <!-- Tabla de ventas (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('ID Venta') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Comprobante') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Cliente') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Fecha') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Forma de Pago') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Caja') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Total') }}
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
                        @forelse($ventas as $venta)
                            @php
                                // Verificar si tiene comprobante fiscal por el total de la venta
                                $tieneComprobanteFiscalTotal = $venta->comprobantesFiscales->where('es_total_venta', true)->count() > 0;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $venta->id }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    {{-- Ticket de venta - solo mostrar si NO hay comprobante fiscal por el total --}}
                                    @if(!$tieneComprobanteFiscalTotal)
                                        <button
                                            wire:click="confirmarReimprimirTicket({{ $venta->id }}, '{{ $venta->numero }}')"
                                            class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors cursor-pointer"
                                            :title="__('Click para reimprimir ticket')"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            {{ $venta->numero }}
                                        </button>
                                    @endif
                                    {{-- Comprobantes fiscales - clickeables para reimprimir --}}
                                    @if($venta->comprobantesFiscales->count() > 0)
                                        <div class="flex flex-wrap gap-1 {{ !$tieneComprobanteFiscalTotal ? 'mt-1.5' : '' }}">
                                            @foreach($venta->comprobantesFiscales as $cf)
                                                <button
                                                    wire:click="confirmarReimprimirFiscal({{ $cf->id }}, '{{ $cf->tipo_legible }}', '{{ $cf->numero_formateado }}')"
                                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 hover:bg-emerald-200 dark:hover:bg-emerald-800 transition-colors cursor-pointer"
                                                    :title="__('Click para reimprimir comprobante fiscal')"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    {{ $cf->tipo_legible }} {{ $cf->numero_formateado }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $venta->cliente->nombre ?? __('Consumidor Final') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $venta->fecha->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($venta->pagos->count() > 1)
                                        {{-- Venta con múltiples formas de pago (mixta) --}}
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($venta->pagos->where('estado', '!=', 'anulado')->unique('forma_pago_id') as $pago)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    {{ $pago->formaPago->nombre ?? 'N/A' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        {{-- Venta con una sola forma de pago --}}
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            {{ $venta->pagos->first()->formaPago->nombre ?? $venta->formaPago->nombre ?? 'N/A' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                        {{ $venta->caja->nombre ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">$@precio($venta->total_final ?? $venta->total)</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $venta->estado === 'completada' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                        {{ $venta->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                        {{ $venta->estado === 'cancelada' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}">
                                        {{ ucfirst($venta->estado) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            wire:click="verDetalle({{ $venta->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                            :title="__('Ver detalle')"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {{ __('Ver') }}
                                        </button>
                                        @if($venta->estado !== 'cancelada')
                                            <button
                                                wire:click="cancelarVenta({{ $venta->id }})"
                                                class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                                :title="__('Cancelar venta')"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="mt-2 font-medium">{{ __('No hay ventas registradas') }}</p>
                                    <p class="text-sm text-gray-400 mt-1">{{ __('Comienza creando tu primera venta') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            @if($ventas->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $ventas->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal POS --}}
    @if($showPosModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ tab: 'carrito' }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="cancelarPOS"></div>

                {{-- Modal Container --}}
                <div class="inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    {{-- Header --}}
                    <div class="bg-bcn-primary px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-white">{{ __('Punto de Venta (POS)') }}</h3>
                            <button
                                wire:click="cancelarPOS"
                                class="text-white hover:text-gray-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {{-- Columna izquierda: Búsqueda y selección de artículos --}}
                            <div class="lg:col-span-2 space-y-4">
                                {{-- Búsqueda de artículos --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Buscar Artículo') }}</label>
                                    <div class="relative">
                                        <input
                                            wire:model.live.debounce.300ms="buscarArticulo"
                                            type="text"
                                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm"
                                            :placeholder="__('Buscar por código o nombre...')">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Carrito --}}
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Carrito') }} ({{ count($carrito) }} {{ __('items') }})</h4>
                                    </div>

                                    @if(empty($carrito))
                                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <p class="text-sm">{{ __('El carrito está vacío') }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ __('Busca y agrega artículos para comenzar') }}</p>
                                        </div>
                                    @else
                                        <div class="max-h-96 overflow-y-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Artículo') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Cant.') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Precio') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Desc.') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Subtotal') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    @foreach($carrito as $index => $item)
                                                        <tr>
                                                            <td class="px-3 py-3">
                                                                <div class="text-sm font-medium text-gray-900">{{ $item['articulo']->nombre }}</div>
                                                                <div class="text-xs text-gray-500">{{ $item['articulo']->codigo }}</div>
                                                            </td>
                                                            <td class="px-3 py-3 text-right">
                                                                <input
                                                                    wire:model.blur="carrito.{{ $index }}.cantidad"
                                                                    wire:change="actualizarCantidad({{ $index }}, $event.target.value)"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    class="w-20 px-2 py-1 text-sm border border-gray-300 rounded text-right">
                                                            </td>
                                                            <td class="px-3 py-3 text-right text-sm text-gray-900">
                                                                $@precio($item['precio_unitario'])
                                                            </td>
                                                            <td class="px-3 py-3 text-right">
                                                                <input
                                                                    wire:model.blur="carrito.{{ $index }}.descuento"
                                                                    wire:change="actualizarDescuento({{ $index }}, $event.target.value)"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    class="w-20 px-2 py-1 text-sm border border-gray-300 rounded text-right">
                                                            </td>
                                                            <td class="px-3 py-3 text-right text-sm font-medium text-gray-900">
                                                                $@precio($item['subtotal'])
                                                            </td>
                                                            <td class="px-3 py-3 text-right">
                                                                <button
                                                                    wire:click="eliminarDelCarrito({{ $index }})"
                                                                    class="text-red-600 hover:text-red-800">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Columna derecha: Resumen y acciones --}}
                            <div class="space-y-4">
                                {{-- Cliente --}}
                                <div>
                                    <label for="clienteSeleccionado" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Cliente') }}
                                        @if($formaPago === 'cta_cte')
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <select
                                        wire:model="clienteSeleccionado"
                                        id="clienteSeleccionado"
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                        <option value="">{{ __('Consumidor Final') }}</option>
                                        @foreach($clientes as $cliente)
                                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Forma de Pago --}}
                                <div>
                                    <label for="formaPago" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Forma de Pago') }}</label>
                                    <select
                                        wire:model.live="formaPago"
                                        id="formaPago"
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                        <option value="efectivo">{{ __('Efectivo') }}</option>
                                        <option value="debito">{{ __('Débito') }}</option>
                                        <option value="credito">{{ __('Crédito') }}</option>
                                        <option value="cta_cte">{{ __('Cuenta Corriente') }}</option>
                                    </select>
                                </div>

                                {{-- Caja (solo si no es cta_cte) --}}
                                @if($formaPago !== 'cta_cte')
                                    <div>
                                        <label for="cajaSeleccionada" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Caja') }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            wire:model="cajaSeleccionada"
                                            id="cajaSeleccionada"
                                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                            <option value="">{{ __('Seleccione una caja') }}</option>
                                            @foreach($cajas as $caja)
                                                @if($caja->estaAbierta())
                                                    <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Descuento General --}}
                                <div>
                                    <label for="descuentoGeneral" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Descuento General (%)') }}</label>
                                    <input
                                        wire:model.live.debounce.300ms="descuentoGeneral"
                                        type="number"
                                        id="descuentoGeneral"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm">
                                </div>

                                {{-- Observaciones --}}
                                <div>
                                    <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Observaciones') }}</label>
                                    <textarea
                                        wire:model="observaciones"
                                        id="observaciones"
                                        rows="3"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm"></textarea>
                                </div>

                                {{-- Resumen de Totales --}}
                                <div class="border-t border-gray-200 pt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">{{ __('Subtotal') }}:</span>
                                        <span class="font-medium text-gray-900">$@precio($subtotal)</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">{{ __('IVA') }}:</span>
                                        <span class="font-medium text-gray-900">$@precio($totalIva)</span>
                                    </div>
                                    @if($descuentoGeneral > 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">{{ __('Descuento') }} ({{ $descuentoGeneral }}%):</span>
                                            <span class="font-medium text-red-600">-$@precio(($subtotal + $totalIva) * ($descuentoGeneral / 100))</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                                        <span class="text-gray-900">{{ __('TOTAL') }}:</span>
                                        <span class="text-bcn-primary">$@precio($total)</span>
                                    </div>
                                </div>

                                {{-- Botones de acción --}}
                                <div class="space-y-2">
                                    <button
                                        wire:click="procesarVenta"
                                        class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-bcn-primary hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ __('Procesar Venta') }}
                                    </button>
                                    <button
                                        wire:click="cancelarPOS"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                                        {{ __('Cancelar') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Detalle Venta --}}
    @if($showDetalleModal && $ventaDetalle)
        <x-bcn-modal
            :show="$showDetalleModal"
            :title="__('Detalle de Venta') . ' #' . $ventaDetalle->numero"
            color="bg-bcn-primary"
            maxWidth="4xl"
            onClose="cerrarDetalle"
        >
            <x-slot:body>
                <p class="text-sm text-gray-500 dark:text-gray-400 -mt-2 mb-4">{{ $ventaDetalle->fecha->format('d/m/Y H:i') }} | {{ $ventaDetalle->usuario->name ?? 'N/A' }}</p>
                <div class="space-y-5">
                        {{-- Información general --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cliente') }}</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->cliente->nombre ?? __('Consumidor Final') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Caja') }}</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->caja->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Forma de Pago') }}</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->formaPago->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $ventaDetalle->estado === 'completada' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $ventaDetalle->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                    {{ $ventaDetalle->estado === 'cancelada' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}">
                                    {{ ucfirst($ventaDetalle->estado) }}
                                </span>
                            </div>
                        </div>

                        {{-- Detalles de items --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Artículos') }}</label>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículo') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cant.') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Subtotal') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($ventaDetalle->detalles as $detalle)
                                            <tr>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                                    {{ $detalle->obtenerNombre() }}
                                                    @if($detalle->es_concepto)
                                                        <span class="text-xs text-gray-500">({{ __('Concepto') }})</span>
                                                    @endif
                                                    @if($detalle->pagado_con_puntos)
                                                        <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                            <svg class="w-2.5 h-2.5 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                            {{ $detalle->puntos_usados }} pts
                                                        </span>
                                                    @endif
                                                    @if($detalle->descuento_cupon > 0)
                                                        <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                            {{ __('Cupón') }} -$@precio($detalle->descuento_cupon)
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white text-right">{{ $detalle->cantidad }}</td>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white text-right">$@precio($detalle->precio_unitario)</td>
                                                <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-white text-right">$@precio($detalle->subtotal)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Promociones Aplicadas --}}
                        @if($ventaDetalle->promociones->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Promociones Aplicadas') }}</label>
                                <div class="space-y-2">
                                    @foreach($ventaDetalle->promociones as $promo)
                                        <div class="flex items-center justify-between bg-amber-50 dark:bg-amber-900/20 rounded-lg px-4 py-2 border border-amber-200 dark:border-amber-800">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                                                </svg>
                                                <div>
                                                    <span class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ $promo->descripcion_promocion }}</span>
                                                    <span class="text-xs text-amber-600 dark:text-amber-400 ml-2">
                                                        ({{ $promo->esPromocionEspecial() ? __('Especial') : __('Común') }})
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-semibold text-red-600 dark:text-red-400">-$@precio($promo->descuento_aplicado)</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Cupón aplicado --}}
                        @if($ventaDetalle->cupon_id && $ventaDetalle->monto_cupon > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Cupón aplicado') }}</label>
                                <div class="flex items-center justify-between bg-amber-50 dark:bg-amber-900/20 rounded-lg px-4 py-2 border border-amber-200 dark:border-amber-800">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                                {{ $ventaDetalle->cupon->codigo ?? __('Cupón') }}
                                            </span>
                                            @if($ventaDetalle->cupon)
                                                <span class="text-xs text-amber-600 dark:text-amber-400 ml-2">
                                                    ({{ $ventaDetalle->cupon->esPorcentaje() ? $ventaDetalle->cupon->valor_descuento . '%' : '$' . number_format($ventaDetalle->cupon->valor_descuento, 2, ',', '.') }}
                                                    {{ $ventaDetalle->cupon->aplicaATotal() ? __('sobre el total') : __('en artículos específicos') }})
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-red-600 dark:text-red-400">-$@precio($ventaDetalle->monto_cupon)</span>
                                </div>
                            </div>
                        @endif

                        {{-- Puntos usados --}}
                        @if($ventaDetalle->puntos_usados > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Puntos canjeados') }}</label>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg px-4 py-3 border border-yellow-200 dark:border-yellow-800 space-y-2">
                                    @php
                                        $articulosConPuntos = $ventaDetalle->detalles->where('pagado_con_puntos', true);
                                        $pagoPuntos = $ventaDetalle->pagos->where('es_pago_puntos', true)->first();
                                    @endphp
                                    @if($articulosConPuntos->isNotEmpty())
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            <span class="text-sm text-yellow-800 dark:text-yellow-200">
                                                {{ __('Artículos canjeados') }}:
                                                {{ $articulosConPuntos->sum('puntos_usados') }} pts
                                                ({{ $articulosConPuntos->count() }} {{ $articulosConPuntos->count() === 1 ? __('artículo') : __('artículos') }})
                                            </span>
                                        </div>
                                    @endif
                                    @if($pagoPuntos)
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                <span class="text-sm text-yellow-800 dark:text-yellow-200">
                                                    {{ __('Pago con puntos') }}: {{ $pagoPuntos->puntos_utilizados }} pts
                                                </span>
                                            </div>
                                            <span class="text-sm font-semibold text-yellow-700 dark:text-yellow-300">-$@precio($pagoPuntos->monto_final)</span>
                                        </div>
                                    @endif
                                    <div class="flex items-center justify-between pt-1 border-t border-yellow-200 dark:border-yellow-700">
                                        <span class="text-xs font-medium text-yellow-700 dark:text-yellow-400">{{ __('Total puntos usados') }}</span>
                                        <span class="text-sm font-bold text-yellow-800 dark:text-yellow-200">{{ number_format($ventaDetalle->puntos_usados) }} pts</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Puntos ganados --}}
                        @if($ventaDetalle->puntos_ganados > 0)
                            <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 rounded-lg px-4 py-2.5 border border-green-200 dark:border-green-800">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span class="text-sm text-green-800 dark:text-green-200">
                                    {{ __('Puntos ganados en esta venta') }}: <span class="font-bold">+{{ number_format($ventaDetalle->puntos_ganados) }} pts</span>
                                </span>
                            </div>
                        @endif

                        {{-- Formas de Pago (Desglose) --}}
                        @if($ventaDetalle->pagos->count() > 0)
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Desglose de Pagos') }}</label>
                                </div>

                                {{-- Cards móvil --}}
                                <div class="sm:hidden space-y-2">
                                    @foreach($ventaDetalle->pagos as $pago)
                                        @php
                                            $pagoAnulado = $pago->estado === 'anulado';
                                            $tieneCobros = $pago->cobrosAplicados->filter(fn($c) => $c->cobro && $c->cobro->estado !== 'anulado')->isNotEmpty();
                                            $turnoCerrado = $pago->cierre_turno_id !== null;
                                            $bloqueoTooltip = '';
                                            if ($tieneCobros) {
                                                $bloqueoTooltip = __('Este pago tiene cobranzas aplicadas');
                                            } elseif ($turnoCerrado && !auth()->user()?->hasPermissionTo('func.cambiar_forma_pago_turno_cerrado')) {
                                                $bloqueoTooltip = __('No tenés permiso para modificar pagos de turnos cerrados');
                                            }
                                        @endphp
                                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 {{ $pagoAnulado ? 'opacity-60' : '' }}">
                                            {{-- Encabezado card: FP + total --}}
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $pago->formaPago->nombre ?? 'N/A' }}</p>
                                                    @if($pago->tieneCuotas())
                                                        <p class="text-xs text-gray-500">{{ $pago->cuotas }} {{ __('cuotas') }}</p>
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-bold text-gray-900 dark:text-white">$@precio($pago->monto_final)</p>
                                                </div>
                                            </div>

                                            {{-- Badges de estado --}}
                                            <div class="flex items-center gap-1 flex-wrap mb-2">
                                                @if($pagoAnulado)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Anulado') }}</span>
                                                @endif
                                                @if($pago->es_pago_puntos)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">{{ $pago->puntos_utilizados }} pts</span>
                                                @endif
                                                @if($turnoCerrado && !$pagoAnulado)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Turno cerrado') }}</span>
                                                @endif
                                                @if(!$pagoAnulado && $pago->estado_facturacion === 'pendiente_de_facturar')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">{{ __('Pendiente de facturar') }}</span>
                                                @elseif(!$pagoAnulado && $pago->estado_facturacion === 'error_arca')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Error ARCA') }}</span>
                                                @endif
                                                @if($pago->comprobante_fiscal_id)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">{{ __('Factura') }} {{ $pago->comprobanteFiscal->letra ?? 'F' }}</span>
                                                @endif
                                            </div>

                                            {{-- Detalles: base + ajuste --}}
                                            <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-400 mb-2">
                                                <div>
                                                    <span class="block text-[10px] uppercase">{{ __('Monto base') }}</span>
                                                    <span class="text-gray-900 dark:text-white font-medium">$@precio($pago->monto_base)</span>
                                                </div>
                                                <div>
                                                    <span class="block text-[10px] uppercase">{{ __('Ajuste') }}</span>
                                                    @if($pago->monto_ajuste != 0)
                                                        <span class="font-medium {{ $pago->monto_ajuste > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                            {{ $pago->monto_ajuste > 0 ? '+' : '' }}$@precio($pago->monto_ajuste)
                                                            <span class="text-[10px]">({{ $pago->ajuste_porcentaje }}%)</span>
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Acciones --}}
                                            @if(!$pagoAnulado && !$ventaDetalle->estaCancelada() && auth()->user()?->hasPermissionTo('func.cambiar_forma_pago_venta'))
                                                <div class="flex items-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                                    <button type="button" wire:click="abrirCambiarPago({{ $pago->id }})"
                                                        @if($bloqueoTooltip) disabled title="{{ $bloqueoTooltip }}" @endif
                                                        class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700 dark:hover:bg-blue-900/50 disabled:opacity-40 disabled:cursor-not-allowed">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        {{ __('Modificar') }}
                                                    </button>
                                                    @if($pago->estado_facturacion === 'pendiente_de_facturar' && auth()->user()?->hasPermissionTo('func.reintentar_facturacion'))
                                                        <button type="button" wire:click="reintentarFacturacionPago({{ $pago->id }})"
                                                            class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-md bg-yellow-50 text-yellow-700 border border-yellow-200 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700 dark:hover:bg-yellow-900/50">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                            {{ __('Reintentar') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Cobros aplicados --}}
                                            @if($tieneCobros)
                                                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                                    <p class="text-[10px] uppercase text-gray-500 mb-1">{{ __('Ver cobros aplicados') }}</p>
                                                    <ul class="space-y-0.5 text-xs text-gray-700 dark:text-gray-300">
                                                        @foreach($pago->cobrosAplicados->filter(fn($c) => $c->cobro && $c->cobro->estado !== 'anulado') as $cv)
                                                            <li>
                                                                {{ $cv->cobro?->fecha?->format('d/m/Y') ?? '-' }} — {{ __('Recibo') }} {{ $cv->cobro?->numero_recibo ?? '-' }} — $@precio($cv->monto_aplicado)
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Tabla desktop --}}
                                <div class="hidden sm:block border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Forma de Pago') }}</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Monto') }}</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ajuste') }}</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Facturado') }}</th>
                                                @if(!$ventaDetalle->estaCancelada() && auth()->user()?->hasPermissionTo('func.cambiar_forma_pago_venta'))
                                                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Acciones') }}</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($ventaDetalle->pagos as $pago)
                                                @php
                                                    $pagoAnulado = $pago->estado === 'anulado';
                                                    $tieneCobros = $pago->cobrosAplicados->filter(fn($c) => $c->cobro && $c->cobro->estado !== 'anulado')->isNotEmpty();
                                                    $turnoCerrado = $pago->cierre_turno_id !== null;
                                                @endphp
                                                <tr class="{{ $pagoAnulado ? 'opacity-60 bg-gray-50 dark:bg-gray-900/50' : '' }}">
                                                    <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                                        {{ $pago->formaPago->nombre ?? 'N/A' }}
                                                        @if($pagoAnulado)
                                                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Anulado') }}</span>
                                                        @endif
                                                        @if($pago->es_pago_puntos)
                                                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                                {{ $pago->puntos_utilizados }} pts
                                                            </span>
                                                        @endif
                                                        @if($pago->tieneCuotas())
                                                            <span class="text-xs text-gray-500">({{ $pago->cuotas }} cuotas)</span>
                                                        @endif
                                                        @if($turnoCerrado && !$pagoAnulado)
                                                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" title="{{ __('Pertenece a turno cerrado') }}">
                                                                {{ __('Turno cerrado') }}
                                                            </span>
                                                        @endif
                                                        @if(!$pagoAnulado && $pago->estado_facturacion === 'pendiente_de_facturar')
                                                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400" title="{{ __('La emisión de factura falló y quedó pendiente') }}">
                                                                {{ __('Pendiente de facturar') }}
                                                            </span>
                                                        @elseif(!$pagoAnulado && $pago->estado_facturacion === 'error_arca')
                                                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400" title="{{ __('Marcado como error ARCA') }}">
                                                                {{ __('Error ARCA') }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white text-right">$@precio($pago->monto_base)</td>
                                                    <td class="px-4 py-2.5 text-sm text-right">
                                                        @if($pago->monto_ajuste != 0)
                                                            <span class="{{ $pago->monto_ajuste > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                                {{ $pago->monto_ajuste > 0 ? '+' : '' }}$@precio($pago->monto_ajuste)
                                                                <span class="text-xs">({{ $pago->ajuste_porcentaje }}%)</span>
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-white text-right">$@precio($pago->monto_final)</td>
                                                    <td class="px-4 py-2.5 text-center">
                                                        @if($pago->comprobante_fiscal_id)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                                                {{ $pago->comprobanteFiscal->letra ?? 'F' }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                    @if(!$ventaDetalle->estaCancelada() && auth()->user()?->hasPermissionTo('func.cambiar_forma_pago_venta'))
                                                        <td class="px-2 py-2.5 text-center whitespace-nowrap">
                                                            @if(!$pagoAnulado)
                                                                @php
                                                                    $bloqueoTooltip = '';
                                                                    if ($tieneCobros) {
                                                                        $bloqueoTooltip = __('Este pago tiene cobranzas aplicadas');
                                                                    } elseif ($turnoCerrado && !auth()->user()->hasPermissionTo('func.cambiar_forma_pago_turno_cerrado')) {
                                                                        $bloqueoTooltip = __('No tenés permiso para modificar pagos de turnos cerrados');
                                                                    }
                                                                @endphp
                                                                <button type="button" wire:click="abrirCambiarPago({{ $pago->id }})"
                                                                    @if($bloqueoTooltip) disabled title="{{ $bloqueoTooltip }}" @else title="{{ __('Cambiar forma de pago') }}" @endif
                                                                    class="inline-flex items-center p-1.5 rounded text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30 disabled:opacity-40 disabled:cursor-not-allowed">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                </button>
                                                                @if($pago->estado_facturacion === 'pendiente_de_facturar' && auth()->user()?->hasPermissionTo('func.reintentar_facturacion'))
                                                                    <button type="button" wire:click="reintentarFacturacionPago({{ $pago->id }})"
                                                                        title="{{ __('Reintentar facturación') }}"
                                                                        class="inline-flex items-center p-1.5 rounded text-yellow-600 hover:bg-yellow-50 dark:text-yellow-400 dark:hover:bg-yellow-900/30">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                                    </button>
                                                                @endif
                                                            @else
                                                                <span class="text-xs text-gray-400">-</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                                @if($tieneCobros)
                                                    <tr class="bg-gray-50 dark:bg-gray-900/30">
                                                        <td colspan="{{ (!$ventaDetalle->estaCancelada() && auth()->user()?->hasPermissionTo('func.cambiar_forma_pago_venta')) ? 6 : 5 }}" class="px-4 py-2 text-xs">
                                                            <div class="font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Ver cobros aplicados') }}:</div>
                                                            <ul class="space-y-1 text-gray-700 dark:text-gray-300">
                                                                @foreach($pago->cobrosAplicados->filter(fn($c) => $c->cobro && $c->cobro->estado !== 'anulado') as $cv)
                                                                    <li>
                                                                        {{ $cv->cobro?->fecha?->format('d/m/Y') ?? '-' }} —
                                                                        {{ __('Recibo') }} {{ $cv->cobro?->numero_recibo ?? '-' }} —
                                                                        $@precio($cv->monto_aplicado)
                                                                        @if($cv->interes_aplicado > 0)
                                                                            <span class="text-orange-600">(+$@precio($cv->interes_aplicado) int.)</span>
                                                                        @endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Historial de ajustes de pagos --}}
                        @if($ajustesPagos && $ajustesPagos->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Historial de cambios en pagos') }}</label>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @foreach($ajustesPagos as $aj)
                                        <div class="p-3 text-sm">
                                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-medium text-gray-900 dark:text-white">{{ $aj->descripcion_auto }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        {{ $aj->created_at->format('d/m/Y H:i') }} —
                                                        {{ $aj->usuario->name ?? __('Usuario') }}
                                                    </p>
                                                    @if($aj->motivo)
                                                        <p class="text-xs text-gray-600 dark:text-gray-400 italic mt-1">"{{ $aj->motivo }}"</p>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1 flex-wrap justify-end">
                                                    @if($aj->es_post_cierre)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('Post-cierre') }}</span>
                                                    @endif
                                                    @if($aj->nc_emitida_flag && $aj->ncEmitida)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                                            NC {{ $aj->ncEmitida->numero_formateado ?? '' }}
                                                        </span>
                                                    @endif
                                                    @if($aj->delta_total != 0)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $aj->delta_total > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
                                                            ΔTotal: {{ $aj->delta_total > 0 ? '+' : '' }}$@precio($aj->delta_total)
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Comprobantes Fiscales --}}
                        @if($ventaDetalle->comprobantesFiscales->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Comprobantes Fiscales') }}</label>
                                <div class="space-y-2">
                                    @foreach($ventaDetalle->comprobantesFiscales as $cf)
                                        <div class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-4 py-3 border border-emerald-200 dark:border-emerald-800">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-shrink-0">
                                                    <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                                                        {{ $cf->tipo_legible }} {{ $cf->numero_formateado }}
                                                    </p>
                                                    <p class="text-xs text-emerald-600 dark:text-emerald-400">
                                                        CAE: {{ $cf->cae }} | Vto: {{ $cf->cae_vencimiento?->format('d/m/Y') ?? 'N/A' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                                    $@precio($cf->total)
                                                </span>
                                                <button
                                                    wire:click="reimprimirComprobanteFiscal({{ $cf->id }})"
                                                    class="inline-flex items-center px-2.5 py-1.5 border border-emerald-300 dark:border-emerald-700 text-xs font-medium rounded text-emerald-700 dark:text-emerald-300 bg-white dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                                                    :title="__('Reimprimir comprobante fiscal')"
                                                >
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                    {{ __('Imprimir') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Totales --}}
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ __('Subtotal') }}:</span>
                                <span class="font-medium text-gray-900 dark:text-white">$@precio($ventaDetalle->subtotal)</span>
                            </div>
                            @if($ventaDetalle->descuento > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Descuento promociones') }}:</span>
                                    <span class="font-medium text-red-600">-$@precio($ventaDetalle->descuento)</span>
                                </div>
                            @endif
                            @if($ventaDetalle->descuento_general_monto > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Descuento general') }} ({{ $ventaDetalle->descuento_general_tipo === 'porcentaje' ? $ventaDetalle->descuento_general_valor . '%' : '$' . number_format($ventaDetalle->descuento_general_valor, 2, ',', '.') }}):</span>
                                    <span class="font-medium text-red-600">-$@precio($ventaDetalle->descuento_general_monto)</span>
                                </div>
                            @endif
                            @if($ventaDetalle->monto_cupon > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Cupón') }} ({{ $ventaDetalle->cupon->codigo ?? '' }}):</span>
                                    <span class="font-medium text-red-600">-$@precio($ventaDetalle->monto_cupon)</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ __('Total') }}:</span>
                                <span class="font-medium text-gray-900 dark:text-white">$@precio($ventaDetalle->total)</span>
                            </div>
                            @if($ventaDetalle->ajuste_forma_pago != 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">{{ __('Ajuste forma de pago') }}:</span>
                                    <span class="font-medium {{ $ventaDetalle->ajuste_forma_pago > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $ventaDetalle->ajuste_forma_pago > 0 ? '+' : '' }}$@precio($ventaDetalle->ajuste_forma_pago)
                                    </span>
                                </div>
                            @endif
                            <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-gray-900 dark:text-white">{{ __('TOTAL FINAL') }}:</span>
                                <span class="text-bcn-primary">$@precio($ventaDetalle->total_final ?? $ventaDetalle->total)</span>
                            </div>
                        </div>

                        @if($ventaDetalle->observaciones)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">{{ __('Observaciones') }}</label>
                                <p class="text-sm text-gray-900 dark:text-white">{{ $ventaDetalle->observaciones }}</p>
                            </div>
                        @endif
                    </div>
            </x-slot:body>

            <x-slot:footer>
                @php
                    $detalleComprobanteFiscalTotal = $ventaDetalle->comprobantesFiscales->where('es_total_venta', true)->count() > 0;
                @endphp
                @if(!$detalleComprobanteFiscalTotal)
                    <button
                        wire:click="reimprimirTicket({{ $ventaDetalle->id }})"
                        class="inline-flex items-center px-4 py-2 border border-bcn-primary rounded-md shadow-sm text-sm font-medium text-bcn-primary bg-white dark:bg-gray-800 hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        {{ __('Reimprimir Ticket') }}
                    </button>
                @endif
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Confirmar Reimpresión --}}
    @if($showReimprimirModal)
        <x-bcn-modal
            :show="$showReimprimirModal"
            :title="__('Confirmar Reimpresión')"
            color="bg-bcn-primary"
            maxWidth="md"
            onClose="cerrarReimprimirModal"
            zIndex="z-[60]"
        >
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('¿Está seguro que desea reimprimir el siguiente documento?') }}
                </p>
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $reimprimirTitulo }}
                    </p>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    wire:click="ejecutarReimpresion"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    {{ __('Reimprimir') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Cancelar Venta --}}
    @if($showCancelarModal)
        <x-bcn-modal
            :show="$showCancelarModal"
            :title="__('Cancelar Venta') . ' ' . ($cancelarVentaInfo['numero'] ?? '')"
            color="bg-red-600"
            maxWidth="4xl"
            onClose="cerrarCancelarModal"
            zIndex="z-[60]"
        >
            <x-slot:body>
                {{-- Fila superior: Info venta + Pagos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- Info de la venta --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Datos de la Venta') }}</h4>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Fecha') }}:</span>
                            <span class="text-gray-900 dark:text-white">{{ $cancelarVentaInfo['fecha'] ?? '' }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Cliente') }}:</span>
                            <span class="text-gray-900 dark:text-white truncate">{{ $cancelarVentaInfo['cliente'] ?? __('Sin cliente') }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Total') }}:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($cancelarVentaInfo['total'] ?? 0, 2, ',', '.') }}</span>
                        </div>
                        @if($cancelarVentaInfo['es_cuenta_corriente'] ?? false)
                            <span class="inline-flex items-center mt-2 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ __('Cuenta Corriente') }}
                            </span>
                        @endif
                    </div>

                    {{-- Pagos --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Formas de Pago') }}</h4>
                        @if(!empty($cancelarVentaInfo['pagos']))
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($cancelarVentaInfo['pagos'] as $pago)
                                    <div class="flex items-start justify-between text-sm gap-2 {{ $pago['anulado'] ? 'opacity-60' : '' }}">
                                        <div class="flex flex-col gap-0.5 min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $pago['forma_pago'] }}</span>

                                                {{-- Estado del pago --}}
                                                @if($pago['anulado'])
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                        {{ __('Anulado') }}
                                                    </span>
                                                @endif

                                                {{-- Estado de facturación --}}
                                                @if($pago['facturado'])
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                        <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                        {{ __('Facturado') }}
                                                    </span>
                                                @elseif($pago['estado_facturacion'] === 'pendiente_de_facturar')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                        {{ __('Pendiente de facturar') }}
                                                    </span>
                                                @elseif($pago['estado_facturacion'] === 'error_arca')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                        {{ __('Error ARCA') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                        {{ __('Sin facturar') }}
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Comprobante asociado --}}
                                            @if($pago['comprobante_numero'])
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                                    {{ __('Comprobante') }}: {{ $pago['comprobante_numero'] }}
                                                    @if($pago['monto_facturado'] > 0 && abs($pago['monto_facturado'] - $pago['monto']) > 0.01)
                                                        — {{ __('facturado') }}: ${{ number_format($pago['monto_facturado'], 2, ',', '.') }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        <span class="font-semibold text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($pago['monto'], 2, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sin pagos registrados') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Comprobantes fiscales (si hay) --}}
                @if($cancelarTieneComprobanteFiscal)
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-semibold text-green-800 dark:text-green-200">{{ __('Comprobantes Fiscales Vigentes') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($cancelarComprobantesFiscales as $cf)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-100 dark:bg-green-800/30 rounded text-xs font-medium text-green-700 dark:text-green-300">
                                    {{ $cf['tipo'] }} {{ $cf['numero'] }}
                                    <span class="text-green-600 dark:text-green-400">${{ number_format($cf['total'], 2, ',', '.') }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Motivo --}}
                <div class="mb-4">
                    <label for="cancelarMotivo" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo (opcional)') }}</label>
                    <input
                        type="text"
                        wire:model="cancelarMotivo"
                        id="cancelarMotivo"
                        class="block w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-red-500 focus:border-red-500"
                        placeholder="Ingrese el motivo de la cancelación..."
                    />
                </div>

                {{-- Opciones de cancelación - Horizontal --}}
                @php
                    $numOpciones = 1 + ($cancelarPermiteCtaCte ? 1 : 0) + ($cancelarTieneComprobanteFiscal ? 1 : 0);
                    $gridCols = $numOpciones === 3 ? 'md:grid-cols-3' : ($numOpciones === 2 ? 'md:grid-cols-2' : 'md:grid-cols-1');
                @endphp
                <div class="grid grid-cols-1 {{ $gridCols }} gap-3">
                    {{-- Opción 1: Cancelar completo --}}
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 flex flex-col">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <h4 class="text-sm font-semibold text-red-800 dark:text-red-200">Cancelar completo</h4>
                        </div>
                        <p class="text-xs text-red-600 dark:text-red-400 mb-3 flex-1">
                            Revierte stock, anula pagos{{ ($cancelarVentaInfo['es_cuenta_corriente'] ?? false) ? ', revierte saldo cliente' : '' }} y registra la venta como cancelada.
                            @if($cancelarTieneComprobanteFiscal)
                                <strong>Emite NC.</strong>
                            @endif
                        </p>
                        <button
                            wire:click="ejecutarCancelacionCompleta"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 transition-colors"
                        >
                            <svg wire:loading wire:target="ejecutarCancelacionCompleta" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="ejecutarCancelacionCompleta">{{ $cancelarTieneComprobanteFiscal ? 'Cancelar + NC' : 'Cancelar venta' }}</span>
                            <span wire:loading wire:target="ejecutarCancelacionCompleta">Procesando...</span>
                        </button>
                    </div>

                    {{-- Opción 2: Pasar a Cta Cte --}}
                    @if($cancelarPermiteCtaCte)
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 flex flex-col">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-200">Pasar a Cta. Cte.</h4>
                            </div>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mb-3 flex-1">
                                Anula pagos, cliente deberá el total. No revierte stock.
                                @if($cancelarTieneComprobanteFiscal)
                                    <em>Factura vigente.</em>
                                @endif
                            </p>
                            <button
                                wire:click="ejecutarConversionACtaCte"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg disabled:opacity-50 transition-colors"
                            >
                                <svg wire:loading wire:target="ejecutarConversionACtaCte" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="ejecutarConversionACtaCte">A Cuenta Corriente</span>
                                <span wire:loading wire:target="ejecutarConversionACtaCte">Procesando...</span>
                            </button>
                        </div>
                    @endif

                    {{-- Opción 3: Solo parte fiscal --}}
                    @if($cancelarTieneComprobanteFiscal)
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 flex flex-col">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-green-800 dark:text-green-200">Solo anular fiscal</h4>
                            </div>
                            <p class="text-xs text-green-600 dark:text-green-400 mb-3 flex-1">
                                Emite NC. No cancela venta, no revierte stock ni pagos.
                            </p>
                            <button
                                wire:click="ejecutarAnulacionFiscal"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 transition-colors"
                            >
                                <svg wire:loading wire:target="ejecutarAnulacionFiscal" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="ejecutarAnulacionFiscal">Emitir NC</span>
                                <span wire:loading wire:target="ejecutarAnulacionFiscal">Procesando...</span>
                            </button>
                        </div>
                    @endif
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button
                    @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
                >
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Scripts de impresión --}}
    @script
    <script>
        // Listener para reimprimir ticket de venta
        Livewire.on('imprimir-ticket', async (data) => {
            // Verificar que QZIntegration esté disponible
            if (typeof window.QZIntegration === 'undefined') {
                console.warn('QZIntegration no está disponible. QZ Tray no instalado o no conectado.');
                // Intentar abrir en nueva ventana como fallback
                window.open(`/api/impresion/venta/${data.ventaId}/ticket?preview=1`, '_blank');
                return;
            }

            const ventaId = data.ventaId;

            try {
                // Conectar a QZ Tray si no está conectado
                const conectado = await window.QZIntegration.conectar();
                if (!conectado) {
                    console.warn('No se pudo conectar a QZ Tray');
                    $wire.dispatch('toast-error', { message: 'No se pudo conectar a QZ Tray' });
                    return;
                }

                // Obtener datos de impresión del ticket
                const ticketResponse = await fetch(`/api/impresion/venta/${ventaId}/ticket`);
                if (ticketResponse.ok) {
                    const ticketData = await ticketResponse.json();

                    if (ticketData.error) {
                        console.error('Error al obtener datos del ticket:', ticketData.message);
                        $wire.dispatch('toast-error', { message: ticketData.message || 'Error al obtener datos del ticket' });
                        return;
                    }

                    if (ticketData.tipo === 'escpos') {
                        await window.QZIntegration.imprimirESCPOS(
                            ticketData.impresora,
                            ticketData.datos,
                            ticketData.opciones
                        );
                    } else {
                        await window.QZIntegration.imprimirHTML(
                            ticketData.impresora,
                            ticketData.datos,
                            ticketData.opciones
                        );
                    }

                    $wire.dispatch('toast-success', { message: 'Ticket enviado a la impresora' });
                } else {
                    console.error('Error HTTP al obtener ticket:', ticketResponse.status);
                    $wire.dispatch('toast-error', { message: 'Error al obtener datos del ticket' });
                }
            } catch (error) {
                console.error('Error al reimprimir ticket:', error);
                $wire.dispatch('toast-error', { message: 'Error al reimprimir: ' + error.message });
            }
        });

        // Listener para reimprimir comprobante fiscal
        Livewire.on('imprimir-comprobante-fiscal', async (data) => {
            // Verificar que QZIntegration esté disponible
            if (typeof window.QZIntegration === 'undefined') {
                console.warn('QZIntegration no está disponible. QZ Tray no instalado o no conectado.');
                // Intentar abrir en nueva ventana como fallback
                window.open(`/api/impresion/factura/${data.comprobanteId}?preview=1`, '_blank');
                return;
            }

            const comprobanteId = data.comprobanteId;

            try {
                // Conectar a QZ Tray si no está conectado
                const conectado = await window.QZIntegration.conectar();
                if (!conectado) {
                    console.warn('No se pudo conectar a QZ Tray');
                    $wire.dispatch('toast-error', { message: 'No se pudo conectar a QZ Tray' });
                    return;
                }

                // Obtener datos de impresión del comprobante fiscal
                const facturaResponse = await fetch(`/api/impresion/factura/${comprobanteId}`);
                if (facturaResponse.ok) {
                    const facturaData = await facturaResponse.json();

                    if (facturaData.error) {
                        console.error('Error al obtener datos del comprobante:', facturaData.message);
                        $wire.dispatch('toast-error', { message: facturaData.message || 'Error al obtener datos del comprobante' });
                        return;
                    }

                    if (facturaData.tipo === 'escpos') {
                        await window.QZIntegration.imprimirESCPOS(
                            facturaData.impresora,
                            facturaData.datos,
                            facturaData.opciones
                        );
                    } else {
                        await window.QZIntegration.imprimirHTML(
                            facturaData.impresora,
                            facturaData.datos,
                            facturaData.opciones
                        );
                    }

                    $wire.dispatch('toast-success', { message: 'Comprobante fiscal enviado a la impresora' });
                } else {
                    console.error('Error HTTP al obtener comprobante:', facturaResponse.status);
                    $wire.dispatch('toast-error', { message: 'Error al obtener datos del comprobante fiscal' });
                }
            } catch (error) {
                console.error('Error al reimprimir comprobante fiscal:', error);
                $wire.dispatch('toast-error', { message: 'Error al reimprimir: ' + error.message });
            }
        });
    </script>
    @endscript

    {{-- ======================================================= --}}
    {{-- MODAL: CAMBIAR FORMA DE PAGO (mixto)                       --}}
    {{-- ======================================================= --}}
    @if($showCambiarPagoModal && $pagoEditandoId)
        @php
            $pagoViejo = \App\Models\VentaPago::with('formaPago','comprobanteFiscal','cierreTurno')->find($pagoEditandoId);
            $formasPagoCambio = $this->obtenerFormasPagoConCuotas();
            $previewC = $previewCambio ?? [];
            $pendienteC = (float) ($previewC['pendiente'] ?? ($pagoViejo->monto_final ?? 0));
            $sumaActualC = (float) ($previewC['suma_nueva'] ?? 0);
            $completoC = (bool) ($previewC['completo'] ?? false);
            $cuotasFpSeleccionada = collect($formasPagoCambio)->firstWhere('id', (int) ($nuevoPagoForm['forma_pago_id'] ?? 0))['cuotas'] ?? [];
        @endphp
        @if($pagoViejo)
            <x-bcn-modal :show="$showCambiarPagoModal" :title="__('Modificar forma de pago')" color="bg-blue-600" max-width="3xl" onClose="cerrarCambiarPago" wire:model="showCambiarPagoModal">
                <x-slot:body>
                <div class="space-y-3">
                    {{-- Banner turno cerrado --}}
                    @if($pagoViejo->cierre_turno_id)
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800">
                            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div class="text-sm text-amber-800 dark:text-amber-200">
                                <p class="font-medium">{{ __('Esta venta pertenece a un turno cerrado') }}</p>
                                <p class="text-xs mt-0.5">{{ __('Se registrará como movimiento post-cierre. El cierre histórico no se modifica; los contraasientos irán al turno actual.') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- A. Pago original --}}
                    <div class="bg-gray-50 dark:bg-gray-900/40 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm">
                                <h4 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">{{ __('Pago original') }}</h4>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $pagoViejo->formaPago->nombre ?? '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ __('Facturado') }}: {{ $pagoViejo->comprobante_fiscal_id ? 'Sí ('.$pagoViejo->comprobanteFiscal?->numero_formateado.')' : 'No' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-500 dark:text-gray-400 block">{{ __('Monto a cubrir') }}</span>
                                <span class="text-2xl font-bold text-gray-900 dark:text-white">$@precio($pagoViejo->monto_final)</span>
                            </div>
                        </div>
                    </div>

                    {{-- B. Resumen pendiente / cubierto --}}
                    <div class="rounded-lg border-2 {{ $completoC ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/20' : 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20' }} p-3">
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Cubierto') }}: </span>
                                <span class="font-bold text-gray-900 dark:text-white">$@precio($sumaActualC)</span>
                            </div>
                            <div>
                                @if($completoC)
                                    <span class="inline-flex items-center gap-1 text-green-700 dark:text-green-300 font-medium">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        {{ __('Cubierto completamente') }}
                                    </span>
                                @else
                                    <span class="text-blue-700 dark:text-blue-300 font-medium">{{ __('Pendiente') }}: $@precio($pendienteC)</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- C. Lista de pagos agregados --}}
                    @if(count($desglosePagosNuevos) > 0)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($desglosePagosNuevos as $idx => $pn)
                                    <div class="flex items-center justify-between gap-2 p-2.5 bg-white dark:bg-gray-800">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ $pn['fp_nombre'] ?? '-' }}</span>
                                                @if(($pn['fp_ajuste_porcentaje'] ?? 0) != 0 && ($pn['aplicar_ajuste'] ?? false))
                                                    <span class="text-xs {{ $pn['fp_ajuste_porcentaje'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                        ({{ $pn['fp_ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $pn['fp_ajuste_porcentaje'] }}%)
                                                    </span>
                                                @endif
                                                @if(($pn['cuotas'] ?? 1) > 1)
                                                    <span class="text-xs text-gray-500">({{ $pn['cuotas'] }} cuotas{{ ($pn['recargo_cuotas_porcentaje'] ?? 0) > 0 ? ' +'.$pn['recargo_cuotas_porcentaje'].'%' : '' }})</span>
                                                @endif
                                                <button type="button" wire:click="toggleFacturarEnDesglose({{ $idx }})"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border transition-colors
                                                        {{ ($pn['facturar'] ?? false) ? 'bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700' : 'bg-gray-100 text-gray-500 border-gray-300 dark:bg-gray-700 dark:text-gray-400 dark:border-gray-600' }}">
                                                    {{ ($pn['facturar'] ?? false) ? '✓ ' . __('Facturar') : __('No facturar') }}
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Base') }} $@precio($pn['monto_base'])</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="block text-sm font-bold text-gray-900 dark:text-white">$@precio($pn['monto_final'])</span>
                                        </div>
                                        <button type="button" wire:click="eliminarDelDesgloseCambio({{ $idx }})"
                                            class="p-1 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30" :title="__('Quitar')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- D. Form para agregar nuevo pago (Alpine search + grid) --}}
                    @if($pendienteC > 0.01)
                        <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-gray-50 dark:bg-gray-700/50"
                            x-data="{
                                busqueda: '',
                                fpSeleccionadaId: @entangle('nuevoPagoForm.forma_pago_id').live,
                                aplicarAjuste: @entangle('nuevoPagoForm.aplicar_ajuste').live,
                                facturar: @entangle('nuevoPagoForm.facturar').live,
                                formasPago: @js($formasPagoCambio),
                                navIndex: -1,
                                cols: window.innerWidth >= 640 ? 4 : 3,
                                get filtradas() {
                                    if (!this.busqueda) return this.formasPago;
                                    const q = this.busqueda.trim().toLowerCase();
                                    return this.formasPago.filter(fp =>
                                        String(fp.id) === q ||
                                        fp.nombre.toLowerCase().includes(q) ||
                                        (fp.codigo && fp.codigo.toLowerCase().includes(q))
                                    );
                                },
                                seleccionar(fp) {
                                    // Seteo inmediato de los defaults en cliente (sin flash visual)
                                    this.aplicarAjuste = false;
                                    this.facturar = !!fp.factura_fiscal;
                                    this.fpSeleccionadaId = fp.id;
                                    this.busqueda = '';
                                    this.navIndex = -1;
                                },
                                limpiar() {
                                    this.fpSeleccionadaId = null;
                                    this.busqueda = '';
                                    this.navIndex = -1;
                                    this.$nextTick(() => { if (this.$refs.inputBusquedaFP) this.$refs.inputBusquedaFP.focus(); });
                                },
                                handleBusquedaKeydown(e) {
                                    const len = this.filtradas.length;
                                    if (e.key === 'ArrowDown') { e.preventDefault(); e.stopPropagation();
                                        if (this.navIndex < 0) { this.navIndex = 0; }
                                        else { this.navIndex = Math.min(this.navIndex + this.cols, len - 1); }
                                    } else if (e.key === 'ArrowUp') { e.preventDefault(); e.stopPropagation();
                                        if (this.navIndex >= this.cols) { this.navIndex -= this.cols; }
                                        else { this.navIndex = -1; this.$refs.inputBusquedaFP?.focus(); }
                                    } else if (e.key === 'ArrowRight') {
                                        if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.min(this.navIndex + 1, len - 1); }
                                    } else if (e.key === 'ArrowLeft') {
                                        if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.max(this.navIndex - 1, 0); }
                                    } else if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation();
                                        if (this.navIndex >= 0 && this.navIndex < len) { this.seleccionar(this.filtradas[this.navIndex]); }
                                        else if (len > 0) { this.seleccionar(this.filtradas[0]); }
                                    } else { this.navIndex = -1; }
                                },
                                handleMontoKeydown(e) {
                                    if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); $wire.agregarAlDesgloseCambio(); }
                                }
                            }"
                            x-init="$nextTick(() => { if (!fpSeleccionadaId && $refs.inputBusquedaFP) $refs.inputBusquedaFP.focus(); })">

                            {{-- Selector FP por botones --}}
                            <template x-if="!fpSeleccionadaId">
                                <div>
                                    <div class="relative mb-2">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </span>
                                        <input type="text" x-ref="inputBusquedaFP" x-model="busqueda" @keydown="handleBusquedaKeydown($event)"
                                            class="w-full pl-8 pr-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="{{ __('Buscar por ID, código o nombre...') }}">
                                    </div>
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5 max-h-40 overflow-y-auto">
                                        <template x-for="(fp, idx) in filtradas" :key="fp.id">
                                            <button type="button" @click="seleccionar(fp)"
                                                class="flex flex-col items-center justify-center p-2 rounded-lg border-2 transition-all text-center min-h-[52px]"
                                                :class="navIndex === idx
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 ring-2 ring-blue-400'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20'">
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono leading-none" x-text="fp.id"></span>
                                                <span class="text-xs font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wide leading-tight" x-text="fp.codigo || fp.nombre.substring(0, 3).toUpperCase()"></span>
                                                <span class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight mt-0.5 truncate w-full" x-text="fp.nombre"></span>
                                                <template x-if="fp.ajuste_porcentaje != 0">
                                                    <span class="text-[9px] font-medium mt-0.5"
                                                        :class="fp.ajuste_porcentaje > 0 ? 'text-red-600' : 'text-green-600'"
                                                        x-text="(fp.ajuste_porcentaje > 0 ? '+' : '') + fp.ajuste_porcentaje + '%'"></span>
                                                </template>
                                            </button>
                                        </template>
                                    </div>
                                    <template x-if="busqueda && filtradas.length === 0">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-2">{{ __('No se encontraron formas de pago') }}</p>
                                    </template>
                                </div>
                            </template>

                            {{-- FP seleccionada: chip + monto + agregar --}}
                            <template x-if="fpSeleccionadaId">
                                <div x-data="{ get fpActual() { return formasPago.find(fp => fp.id == fpSeleccionadaId) || null; } }" x-init="$nextTick(() => $refs.inputMontoCambio?.focus())">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-100 dark:bg-blue-900/40 border border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-300 rounded-lg text-sm font-medium">
                                            <span class="font-bold uppercase" x-text="fpActual?.codigo || fpActual?.nombre?.substring(0,3).toUpperCase()"></span>
                                            <span x-text="fpActual?.nombre"></span>
                                            <button type="button" @click="limpiar()" class="ml-0.5 hover:text-blue-900">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="relative flex-1 min-w-[120px]">
                                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 text-xs">$</span>
                                            <input type="number" step="0.01" x-ref="inputMontoCambio"
                                                wire:model="nuevoPagoForm.monto_base" @keydown="handleMontoKeydown($event)"
                                                class="w-full pl-6 pr-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                placeholder="{{ number_format($pendienteC, 2, ',', '.') }}">
                                        </div>
                                        <button wire:click="agregarAlDesgloseCambio" type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            {{ __('Agregar') }}
                                        </button>
                                    </div>

                                    {{-- Aplicar ajuste + facturar (toggles) --}}
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <template x-if="fpActual && fpActual.ajuste_porcentaje != 0">
                                            <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-300">
                                                <input type="checkbox" x-model="aplicarAjuste" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                                                <span class="ml-1.5">{{ __('Aplicar ajuste') }} <span x-text="(fpActual?.ajuste_porcentaje > 0 ? '+' : '') + fpActual?.ajuste_porcentaje + '%'"></span></span>
                                            </label>
                                        </template>
                                        <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" x-model="facturar" class="rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
                                            <span class="ml-1.5">{{ __('Facturar este pago') }}</span>
                                        </label>
                                    </div>

                                    {{-- Cuotas --}}
                                    @if(count($cuotasFpSeleccionada) > 0)
                                        <div class="mt-2">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Cuotas') }}</label>
                                            <select wire:change="seleccionarCuotasCambio($event.target.value, $event.target.options[$event.target.selectedIndex].dataset.recargo)"
                                                class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                                <option value="1" data-recargo="0">{{ __('1 pago (sin financiación)') }}</option>
                                                @foreach($cuotasFpSeleccionada as $cuota)
                                                    <option value="{{ $cuota['cantidad'] }}" data-recargo="{{ $cuota['recargo'] }}"
                                                        {{ $nuevoPagoForm['cuotas'] == $cuota['cantidad'] ? 'selected' : '' }}>
                                                        {{ $cuota['cantidad'] }} cuotas{{ $cuota['recargo'] > 0 ? ' (+'.$cuota['recargo'].'%)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </template>
                        </div>
                    @endif

                    {{-- E. Preview fiscal --}}
                    @if(! empty($previewC) && ! empty($previewC['preview_texto']))
                        @php
                            $colorP = 'gray';
                            if ($previewC['emitir_nc'] === true) $colorP = 'blue';
                            elseif ($previewC['emitir_nc'] === 'preguntar' || ($previewC['emitir_fc_nueva'] ?? false) === 'preguntar') $colorP = 'amber';
                            elseif (($previewC['emitir_fc_nueva'] ?? false) === true) $colorP = 'emerald';
                        @endphp
                        <div class="rounded-lg p-2.5 border border-{{ $colorP }}-200 dark:border-{{ $colorP }}-800 bg-{{ $colorP }}-50 dark:bg-{{ $colorP }}-900/20 text-sm text-{{ $colorP }}-800 dark:text-{{ $colorP }}-200">
                            <p>{{ $previewC['preview_texto'] }}</p>
                            @if($previewC['emitir_nc'] === 'preguntar')
                                <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-300 mt-1.5">
                                    <input type="checkbox" wire:model="opcionesFiscales.emitir_nc" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-1.5">{{ __('Confirmo emitir Nota de Crédito') }}</span>
                                </label>
                            @endif
                        </div>
                    @endif

                    {{-- F. Motivo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo del cambio') }} <span class="text-red-500">*</span></label>
                        <textarea wire:model="motivoCambio" rows="2" minlength="10" maxlength="500"
                            placeholder="{{ __('Ej: Cajero tildó débito por error, era transferencia') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                        @error('motivoCambio') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                </x-slot:body>

                <x-slot:footer>
                    <button type="button" wire:click="cerrarCambiarPago" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="button" wire:click="confirmarCambioPago" wire:loading.attr="disabled"
                        @if(!$completoC) disabled @endif
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="confirmarCambioPago">
                            @if($completoC) {{ __('Confirmar cambio') }} @else {{ __('Falta cubrir $').number_format($pendienteC, 2, ',', '.') }} @endif
                        </span>
                        <span wire:loading wire:target="confirmarCambioPago">{{ __('Procesando...') }}</span>
                    </button>
                </x-slot:footer>
            </x-bcn-modal>
        @endif
    @endif

</div>
