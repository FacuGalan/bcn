{{-- Vista: Dashboard de Sucursal --}}

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header con indicador prominente de sucursal --}}
        <div class="mb-6 bg-gradient-to-r from-indigo-600 to-indigo-800 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 rounded-full p-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">{{ $sucursal->nombre }}</h1>
                        <div class="flex items-center gap-3 mt-1">
                            <p class="text-indigo-100">Código: {{ $sucursal->codigo }}</p>
                            @if($sucursal->es_principal)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-400 text-yellow-900">
                                    ⭐ Sucursal Principal
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-indigo-200">Dashboard</p>
                    <p class="text-lg font-semibold">{{ now()->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Mensaje flash si hay cambio de sucursal --}}
        @if(session('message'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-md" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Filtro de fecha --}}
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha</label>
            <input wire:model.live="fechaSeleccionada" type="date" class="block w-full md:w-64 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        {{-- Métricas principales --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            {{-- Ventas del día --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Ventas del Día</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">$@precio($totalVentasHoy)</div>
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">{{ $cantidadVentasHoy }} operaciones</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Compras del día --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Compras del Día</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">$@precio($totalComprasHoy)</div>
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">{{ $cantidadComprasHoy }} operaciones</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Estado de cajas --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Cajas Abiertas</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $cajasAbiertas }}/{{ $totalCajas }}</div>
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">$@precio($totalEnCajas) total</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Alertas de stock --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Alertas de Stock</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stockBajoMinimo + $stockSinExistencia }}</div>
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">{{ $stockBajoMinimo }} bajo mínimo</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grid de información --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Ventas por forma de pago --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Ventas por Forma de Pago</h3>
                </div>
                <div class="px-6 py-4">
                    @if($ventasPorFormaPago->count() > 0)
                        <div class="space-y-3">
                            @foreach($ventasPorFormaPago as $item)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', $item->forma_pago)) }}</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">$@precio($item->total)</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ ($item->total / $totalVentasHoy) * 100 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No hay ventas registradas</p>
                    @endif
                </div>
            </div>

            {{-- Últimas ventas --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Últimas Ventas</h3>
                </div>
                <div class="px-6 py-4">
                    @if($ultimasVentas->count() > 0)
                        <div class="space-y-3">
                            @foreach($ultimasVentas as $venta)
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">#{{ $venta->numero_comprobante }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venta->cliente->nombre ?? 'Consumidor Final' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">$@precio($venta->total)</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venta->created_at->format('H:i') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No hay ventas recientes</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Accesos rápidos --}}
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="#" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-shadow">
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Ventas</span>
                </div>
            </a>
            <a href="#" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-shadow">
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Compras</span>
                </div>
            </a>
            <a href="#" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-shadow">
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Stock</span>
                </div>
            </a>
            <a href="#" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-shadow">
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Cajas</span>
                </div>
            </a>
        </div>
    </div>
</div>
