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

<div class="py-6">
    {{-- Header: Título y botón Nueva Venta --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Ventas</h1>
                <p class="text-sm text-gray-600 mt-1">Gestión de ventas y punto de venta (POS)</p>
            </div>
            <button
                wire:click="abrirPOS"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Nueva Venta
            </button>
        </div>
    </div>

    {{-- Barra de búsqueda y filtros --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            {{-- Búsqueda principal --}}
            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                <div class="flex-1">
                    <label for="search" class="sr-only">Buscar</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            id="search"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            placeholder="Buscar por número de comprobante o cliente...">
                    </div>
                </div>

                {{-- Botón toggle filtros (móvil) --}}
                <button
                    wire:click="toggleFilters"
                    type="button"
                    class="sm:hidden inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filtros
                </button>
            </div>

            {{-- Filtros (ocultos en móvil si showFilters = false) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4" x-show="@js($showFilters) || window.innerWidth >= 640" x-data>
                {{-- Filtro Estado --}}
                <div>
                    <label for="filterEstado" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select
                        wire:model.live="filterEstado"
                        id="filterEstado"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="all">Todos</option>
                        <option value="completada">Completada</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>

                {{-- Filtro Forma de Pago --}}
                <div>
                    <label for="filterFormaPago" class="block text-sm font-medium text-gray-700 mb-1">Forma de Pago</label>
                    <select
                        wire:model.live="filterFormaPago"
                        id="filterFormaPago"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="all">Todas</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="debito">Débito</option>
                        <option value="credito">Crédito</option>
                        <option value="cta_cte">Cuenta Corriente</option>
                    </select>
                </div>

                {{-- Filtro Caja --}}
                <div>
                    <label for="filterCaja" class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                    <select
                        wire:model.live="filterCaja"
                        id="filterCaja"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="actual">Caja Actual</option>
                        <option value="all">Todas las Cajas</option>
                    </select>
                </div>

                {{-- Filtro Fecha Desde --}}
                <div>
                    <label for="filterFechaDesde" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                    <input
                        wire:model.live="filterFechaDesde"
                        type="date"
                        id="filterFechaDesde"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                {{-- Filtro Fecha Hasta --}}
                <div>
                    <label for="filterFechaHasta" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                    <input
                        wire:model.live="filterFechaHasta"
                        type="date"
                        id="filterFechaHasta"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>

            {{-- Botón resetear filtros --}}
            @if($search || $filterEstado !== 'all' || $filterFormaPago !== 'all' || $filterCaja !== 'actual')
                <div class="mt-4">
                    <button
                        wire:click="resetFilters"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Limpiar filtros
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabla de ventas --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Comprobante
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Cliente
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Forma de Pago
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Caja
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($ventas as $venta)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $venta->numero_comprobante }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $venta->cliente->nombre ?? 'Consumidor Final' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $venta->fecha->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $venta->forma_pago === 'efectivo' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $venta->forma_pago === 'debito' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $venta->forma_pago === 'credito' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $venta->forma_pago === 'cta_cte' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $venta->forma_pago)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $venta->caja->nombre ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    ${{ number_format($venta->total, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $venta->estado === 'completada' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $venta->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $venta->estado === 'cancelada' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ ucfirst($venta->estado) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button
                                        wire:click="verDetalle({{ $venta->id }})"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        Ver
                                    </button>
                                    @if($venta->estado !== 'cancelada')
                                        <button
                                            wire:click="cancelarVenta({{ $venta->id }})"
                                            wire:confirm="¿Está seguro de cancelar esta venta?"
                                            class="text-red-600 hover:text-red-900">
                                            Cancelar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="text-gray-900 font-medium mb-1">No hay ventas registradas</p>
                                    <p class="text-gray-500">Comienza creando tu primera venta</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($ventas->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
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
                <div class="inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl">
                    {{-- Header --}}
                    <div class="bg-indigo-600 px-6 py-4">
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
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Buscar Artículo</label>
                                    <div class="relative">
                                        <input
                                            wire:model.live.debounce.300ms="buscarArticulo"
                                            type="text"
                                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            placeholder="Buscar por código o nombre...">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Carrito --}}
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                        <h4 class="text-sm font-medium text-gray-900">Carrito ({{ count($carrito) }} items)</h4>
                                    </div>

                                    @if(empty($carrito))
                                        <div class="p-8 text-center text-gray-500">
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
                                                                ${{ number_format($item['precio_unitario'], 2) }}
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
                                                                ${{ number_format($item['subtotal'], 2) }}
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
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>

                                {{-- Observaciones --}}
                                <div>
                                    <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                                    <textarea
                                        wire:model="observaciones"
                                        id="observaciones"
                                        rows="3"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                </div>

                                {{-- Resumen de Totales --}}
                                <div class="border-t border-gray-200 pt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span class="font-medium text-gray-900">${{ number_format($subtotal, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">IVA:</span>
                                        <span class="font-medium text-gray-900">${{ number_format($totalIva, 2) }}</span>
                                    </div>
                                    @if($descuentoGeneral > 0)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Descuento ({{ $descuentoGeneral }}%):</span>
                                            <span class="font-medium text-red-600">-${{ number_format(($subtotal + $totalIva) * ($descuentoGeneral / 100), 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                                        <span class="text-gray-900">TOTAL:</span>
                                        <span class="text-indigo-600">${{ number_format($total, 2) }}</span>
                                    </div>
                                </div>

                                {{-- Botones de acción --}}
                                <div class="space-y-2">
                                    <button
                                        wire:click="procesarVenta"
                                        class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Procesar Venta
                                    </button>
                                    <button
                                        wire:click="cancelarPOS"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
                <div class="inline-block w-full max-w-3xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl">
                    {{-- Header --}}
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Detalle de Venta #{{ $ventaDetalle->numero_comprobante }}</h3>
                            <button
                                wire:click="cerrarDetalle"
                                class="text-gray-400 hover:text-gray-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-4">
                        {{-- Información general --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $ventaDetalle->cliente->nombre ?? 'Consumidor Final' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fecha</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $ventaDetalle->fecha->format('d/m/Y H:i') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Forma de Pago</label>
                                <p class="mt-1 text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $ventaDetalle->forma_pago)) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estado</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $ventaDetalle->estado === 'completada' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $ventaDetalle->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $ventaDetalle->estado === 'cancelada' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($ventaDetalle->estado) }}
                                </span>
                            </div>
                        </div>

                        {{-- Detalles de items --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Artículos</label>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Artículo</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($ventaDetalle->detalles as $detalle)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $detalle->articulo->nombre }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $detalle->cantidad }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 text-right">${{ number_format($detalle->precio_unitario, 2) }}</td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">${{ number_format($detalle->subtotal, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Totales --}}
                        <div class="border-t border-gray-200 pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium text-gray-900">${{ number_format($ventaDetalle->subtotal, 2) }}</span>
                            </div>
                            @if($ventaDetalle->descuento > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Descuento:</span>
                                    <span class="font-medium text-red-600">-${{ number_format($ventaDetalle->descuento, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                                <span class="text-gray-900">TOTAL:</span>
                                <span class="text-indigo-600">${{ number_format($ventaDetalle->total, 2) }}</span>
                            </div>
                        </div>

                        @if($ventaDetalle->observaciones)
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $ventaDetalle->observaciones }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-end">
                        <button
                            wire:click="cerrarDetalle"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
