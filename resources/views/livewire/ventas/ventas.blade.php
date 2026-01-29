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
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">Listado de Ventas</h2>
                        <!-- Botón Nueva Venta - Solo icono en móviles -->
                        <div class="sm:hidden">
                            <a
                                href="{{ route('ventas.create') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                title="Nueva Venta"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">Gestión y consulta de ventas realizadas</p>
                </div>
                <!-- Botón Nueva Venta - Desktop -->
                <div class="hidden sm:flex gap-3">
                    <a
                        href="{{ route('ventas.create') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        title="Crear nueva venta"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nueva Venta
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
                        Filtros
                        @if($search || $filterEstado !== 'all' || $filterFormaPago !== 'all' || $filterCaja !== 'actual' || $filterComprobanteFiscal !== 'all' || $filterFechaDesde || $filterFechaHasta)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                Activos
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
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                {{-- Primera fila: Búsqueda y selects --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Búsqueda -->
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="ID venta, ticket, cliente, factura..."
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro Estado -->
                    <div>
                        <label for="filterEstado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                        <select
                            wire:model.live="filterEstado"
                            id="filterEstado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">Todos</option>
                            <option value="completada">Completada</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <!-- Filtro Forma de Pago -->
                    <div>
                        <label for="filterFormaPago" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de Pago</label>
                        <select
                            wire:model.live="filterFormaPago"
                            id="filterFormaPago"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">Todas</option>
                            @foreach($formasPago as $fp)
                                <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro Comprobante Fiscal -->
                    <div>
                        <label for="filterComprobanteFiscal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comprobante Fiscal</label>
                        <select
                            wire:model.live="filterComprobanteFiscal"
                            id="filterComprobanteFiscal"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">Todas</option>
                            <option value="con">Con factura</option>
                            <option value="sin">Sin factura</option>
                        </select>
                    </div>

                    <!-- Filtro Caja -->
                    <div>
                        <label for="filterCaja" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Caja</label>
                        <select
                            wire:model.live="filterCaja"
                            id="filterCaja"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="actual">Caja actual</option>
                            <option value="all">Todas mis cajas</option>
                        </select>
                    </div>
                </div>

                {{-- Segunda fila: Fechas y botón limpiar --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                    <!-- Filtro Fecha Desde -->
                    <div>
                        <label for="filterFechaDesde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Desde</label>
                        <input
                            wire:model.live="filterFechaDesde"
                            type="date"
                            id="filterFechaDesde"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                    </div>

                    <!-- Filtro Fecha Hasta -->
                    <div>
                        <label for="filterFechaHasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Hasta</label>
                        <input
                            wire:model.live="filterFechaHasta"
                            type="date"
                            id="filterFechaHasta"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                    </div>

                    <!-- Espaciador y botón limpiar -->
                    <div class="lg:col-span-3 flex items-end justify-end">
                        @if($search || $filterEstado !== 'all' || $filterFormaPago !== 'all' || $filterCaja !== 'actual' || $filterComprobanteFiscal !== 'all' || $filterFechaDesde || $filterFechaHasta)
                            <button
                                wire:click="resetFilters"
                                class="text-sm text-bcn-primary hover:text-bcn-secondary font-medium inline-flex items-center px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Limpiar filtros
                            </button>
                        @endif
                    </div>
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
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $venta->cliente->nombre ?? 'Consumidor Final' }}</div>
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
                                title="Ver detalle"
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
                                    title="Cancelar venta"
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
                    <p class="mt-2 text-sm font-medium">No hay ventas registradas</p>
                    <p class="text-xs text-gray-400 mt-1">Comienza creando tu primera venta</p>
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
                                ID Venta
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Comprobante
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Cliente
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Forma de Pago
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Caja
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Total
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Acciones
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
                                            title="Click para reimprimir ticket"
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
                                                    title="Click para reimprimir comprobante fiscal"
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
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $venta->cliente->nombre ?? 'Consumidor Final' }}</div>
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
                                            title="Ver detalle"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Ver
                                        </button>
                                        @if($venta->estado !== 'cancelada')
                                            <button
                                                wire:click="cancelarVenta({{ $venta->id }})"
                                                class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150"
                                                title="Cancelar venta"
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
                                    <p class="mt-2 font-medium">No hay ventas registradas</p>
                                    <p class="text-sm text-gray-400 mt-1">Comienza creando tu primera venta</p>
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
                            <h3 class="text-lg font-medium text-white">Punto de Venta (POS)</h3>
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
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar Artículo</label>
                                    <div class="relative">
                                        <input
                                            wire:model.live.debounce.300ms="buscarArticulo"
                                            type="text"
                                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm"
                                            placeholder="Buscar por código o nombre...">
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
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Carrito ({{ count($carrito) }} items)</h4>
                                    </div>

                                    @if(empty($carrito))
                                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <p class="text-sm">El carrito está vacío</p>
                                            <p class="text-xs text-gray-400 mt-1">Busca y agrega artículos para comenzar</p>
                                        </div>
                                    @else
                                        <div class="max-h-96 overflow-y-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Artículo</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Desc.</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
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
                                        Cliente
                                        @if($formaPago === 'cta_cte')
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <select
                                        wire:model="clienteSeleccionado"
                                        id="clienteSeleccionado"
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                        <option value="">Consumidor Final</option>
                                        @foreach($clientes as $cliente)
                                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Forma de Pago --}}
                                <div>
                                    <label for="formaPago" class="block text-sm font-medium text-gray-700 mb-1">Forma de Pago</label>
                                    <select
                                        wire:model.live="formaPago"
                                        id="formaPago"
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="debito">Débito</option>
                                        <option value="credito">Crédito</option>
                                        <option value="cta_cte">Cuenta Corriente</option>
                                    </select>
                                </div>

                                {{-- Caja (solo si no es cta_cte) --}}
                                @if($formaPago !== 'cta_cte')
                                    <div>
                                        <label for="cajaSeleccionada" class="block text-sm font-medium text-gray-700 mb-1">
                                            Caja <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            wire:model="cajaSeleccionada"
                                            id="cajaSeleccionada"
                                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm rounded-md">
                                            <option value="">Seleccione una caja</option>
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
                                    <label for="descuentoGeneral" class="block text-sm font-medium text-gray-700 mb-1">Descuento General (%)</label>
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
                                    <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                                    <textarea
                                        wire:model="observaciones"
                                        id="observaciones"
                                        rows="3"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm"></textarea>
                                </div>

                                {{-- Resumen de Totales --}}
                                <div class="border-t border-gray-200 pt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span class="font-medium text-gray-900">$@precio($subtotal)</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">IVA:</span>
                                        <span class="font-medium text-gray-900">$@precio($totalIva)</span>
                                    </div>
                                    @if($descuentoGeneral > 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Descuento ({{ $descuentoGeneral }}%):</span>
                                            <span class="font-medium text-red-600">-$@precio(($subtotal + $totalIva) * ($descuentoGeneral / 100))</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                                        <span class="text-gray-900">TOTAL:</span>
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
                                        Procesar Venta
                                    </button>
                                    <button
                                        wire:click="cancelarPOS"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                                        Cancelar
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
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="cerrarDetalle"></div>

                {{-- Modal Container --}}
                <div class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    {{-- Header --}}
                    <div class="bg-bcn-primary px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-white">Detalle de Venta #{{ $ventaDetalle->numero }}</h3>
                                <p class="text-sm text-white/70">{{ $ventaDetalle->fecha->format('d/m/Y H:i') }} | {{ $ventaDetalle->usuario->name ?? 'N/A' }}</p>
                            </div>
                            <button
                                wire:click="cerrarDetalle"
                                class="text-white/80 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-5 max-h-[70vh] overflow-y-auto">
                        {{-- Información general --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->cliente->nombre ?? 'Consumidor Final' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Caja</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->caja->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forma de Pago</label>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $ventaDetalle->formaPago->nombre ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</label>
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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Artículos</label>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Artículo</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($ventaDetalle->detalles as $detalle)
                                            <tr>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                                    {{ $detalle->articulo->nombre ?? $detalle->descripcion ?? 'Artículo' }}
                                                    @if($detalle->es_concepto)
                                                        <span class="text-xs text-gray-500">(Concepto)</span>
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
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Promociones Aplicadas</label>
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
                                                        ({{ $promo->esPromocionEspecial() ? 'Especial' : 'Común' }})
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-semibold text-red-600 dark:text-red-400">-$@precio($promo->descuento_aplicado)</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Formas de Pago (Desglose) --}}
                        @if($ventaDetalle->pagos->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Desglose de Pagos</label>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forma de Pago</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ajuste</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Facturado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($ventaDetalle->pagos as $pago)
                                                <tr>
                                                    <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                                        {{ $pago->formaPago->nombre ?? 'N/A' }}
                                                        @if($pago->tieneCuotas())
                                                            <span class="text-xs text-gray-500">({{ $pago->cuotas }} cuotas)</span>
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
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Comprobantes Fiscales --}}
                        @if($ventaDetalle->comprobantesFiscales->count() > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Comprobantes Fiscales</label>
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
                                                    title="Reimprimir comprobante fiscal"
                                                >
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                    Imprimir
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
                                <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                <span class="font-medium text-gray-900 dark:text-white">$@precio($ventaDetalle->subtotal)</span>
                            </div>
                            @if($ventaDetalle->descuento > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Descuento promociones:</span>
                                    <span class="font-medium text-red-600">-$@precio($ventaDetalle->descuento)</span>
                                </div>
                            @endif
                            @if($ventaDetalle->iva > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">IVA:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">$@precio($ventaDetalle->iva)</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Total:</span>
                                <span class="font-medium text-gray-900 dark:text-white">$@precio($ventaDetalle->total)</span>
                            </div>
                            @if($ventaDetalle->ajuste_forma_pago != 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Ajuste forma de pago:</span>
                                    <span class="font-medium {{ $ventaDetalle->ajuste_forma_pago > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $ventaDetalle->ajuste_forma_pago > 0 ? '+' : '' }}$@precio($ventaDetalle->ajuste_forma_pago)
                                    </span>
                                </div>
                            @endif
                            <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-gray-900 dark:text-white">TOTAL FINAL:</span>
                                <span class="text-bcn-primary">$@precio($ventaDetalle->total_final ?? $ventaDetalle->total)</span>
                            </div>
                        </div>

                        @if($ventaDetalle->observaciones)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Observaciones</label>
                                <p class="text-sm text-gray-900 dark:text-white">{{ $ventaDetalle->observaciones }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    @php
                        $detalleComprobanteFiscalTotal = $ventaDetalle->comprobantesFiscales->where('es_total_venta', true)->count() > 0;
                    @endphp
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                        @if(!$detalleComprobanteFiscalTotal)
                            <button
                                wire:click="reimprimirTicket({{ $ventaDetalle->id }})"
                                class="inline-flex items-center px-4 py-2 border border-bcn-primary rounded-md shadow-sm text-sm font-medium text-bcn-primary bg-white dark:bg-gray-800 hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Reimprimir Ticket
                            </button>
                        @else
                            <div></div>
                        @endif
                        <button
                            wire:click="cerrarDetalle"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Confirmar Reimpresión --}}
    @if($showReimprimirModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="cerrarReimprimirModal"></div>

                {{-- Modal Container --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:align-middle">
                    {{-- Header --}}
                    <div class="bg-bcn-primary px-6 py-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-white/20">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                            </div>
                            <h3 class="ml-4 text-lg font-medium text-white">Confirmar Reimpresión</h3>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            ¿Está seguro que desea reimprimir el siguiente documento?
                        </p>
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $reimprimirTitulo }}
                            </p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex justify-end gap-3">
                        <button
                            wire:click="cerrarReimprimirModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            wire:click="ejecutarReimpresion"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Reimprimir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Cancelar Venta --}}
    @if($showCancelarModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto" x-data x-on:keydown.escape.window="$wire.cerrarCancelarModal()">
            <div class="flex items-center justify-center min-h-screen p-4">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" wire:click="cerrarCancelarModal"></div>

                {{-- Modal Container - Más ancho --}}
                <div class="relative w-full max-w-4xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl">
                    {{-- Header compacto --}}
                    <div class="flex items-center justify-between px-5 py-3 bg-red-600 rounded-t-xl">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-white/20 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-white">Cancelar Venta {{ $cancelarVentaInfo['numero'] ?? '' }}</h3>
                        </div>
                        <button wire:click="cerrarCancelarModal" class="p-1 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="p-5">
                        {{-- Fila superior: Info venta + Pagos --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            {{-- Info de la venta --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Datos de la Venta</h4>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Fecha:</span>
                                    <span class="text-gray-900 dark:text-white">{{ $cancelarVentaInfo['fecha'] ?? '' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">Cliente:</span>
                                    <span class="text-gray-900 dark:text-white truncate">{{ $cancelarVentaInfo['cliente'] ?? 'Sin cliente' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">Total:</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($cancelarVentaInfo['total'] ?? 0, 2, ',', '.') }}</span>
                                </div>
                                @if($cancelarVentaInfo['es_cuenta_corriente'] ?? false)
                                    <span class="inline-flex items-center mt-2 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                        Cuenta Corriente
                                    </span>
                                @endif
                            </div>

                            {{-- Pagos --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Formas de Pago</h4>
                                @if(!empty($cancelarVentaInfo['pagos']))
                                    <div class="space-y-1.5 max-h-24 overflow-y-auto">
                                        @foreach($cancelarVentaInfo['pagos'] as $pago)
                                            <div class="flex items-center justify-between text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $pago['forma_pago'] }}</span>
                                                    @if($pago['facturado'])
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                            <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                            Facturado
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                            Sin facturar
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="font-medium text-gray-900 dark:text-white">${{ number_format($pago['monto'], 2, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin pagos registrados</p>
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
                                    <span class="text-sm font-semibold text-green-800 dark:text-green-200">Comprobantes Fiscales Vigentes</span>
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
                            <label for="cancelarMotivo" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo (opcional)</label>
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
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-700/50 rounded-b-xl flex justify-end">
                        <button
                            wire:click="cerrarCancelarModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
</div>
