{{-- Vista: Dashboard de Sucursal --}}

<div class="py-6" wire:poll.60s>
    <div class="w-full px-4 sm:px-6 lg:px-8">
        {{-- Header con sucursal y selector de período --}}
        <div class="mb-6 bg-gradient-to-r from-bcn-primary to-bcn-primary/80 rounded-xl shadow-lg p-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 rounded-full p-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold">{{ $sucursal->nombre }}</h1>
                        <div class="flex items-center gap-3 mt-1 flex-wrap">
                            <p class="text-white/80 text-sm">Cod: {{ $sucursal->codigo }}</p>
                            @if($sucursal->es_principal)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-400 text-yellow-900">
                                    Principal
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Selector de período --}}
                    <div class="inline-flex rounded-lg bg-white/20 p-1">
                        <button wire:click="cambiarPeriodo('hoy')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $periodoSeleccionado === 'hoy' ? 'bg-white text-bcn-primary' : 'text-white hover:bg-white/10' }}">
                            Hoy
                        </button>
                        <button wire:click="cambiarPeriodo('semana')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $periodoSeleccionado === 'semana' ? 'bg-white text-bcn-primary' : 'text-white hover:bg-white/10' }}">
                            Semana
                        </button>
                        <button wire:click="cambiarPeriodo('mes')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $periodoSeleccionado === 'mes' ? 'bg-white text-bcn-primary' : 'text-white hover:bg-white/10' }}">
                            Mes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Flash message --}}
        @if(session('message'))
            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-400 p-4 rounded-md" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="ml-3 text-sm font-medium text-green-800 dark:text-green-200">{{ session('message') }}</p>
                </div>
            </div>
        @endif

        {{-- KPIs principales --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            {{-- Ventas totales --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between">
                    <div class="flex-shrink-0 p-3 rounded-lg bg-bcn-primary/10">
                        <svg class="h-6 w-6 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    @if($metricas['variacion'] != 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $metricas['variacion'] > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ $metricas['variacion'] > 0 ? '+' : '' }}{{ $metricas['variacion'] }}%
                        </span>
                    @endif
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ventas {{ $periodoLabel }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">$@precio($metricas['total'])</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $metricas['cantidad'] }} operaciones</p>
                </div>
            </div>

            {{-- Ticket promedio --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex-shrink-0 p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30 w-fit">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                    </svg>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticket Promedio</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">$@precio($metricas['ticket_promedio'])</p>
                    @if($metricas['canceladas'] > 0)
                        <p class="text-xs text-red-500 mt-1">{{ $metricas['canceladas'] }} cancelada(s)</p>
                    @endif
                </div>
            </div>

            {{-- Comprobantes fiscales --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900/30 w-fit">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Facturado AFIP</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">$@precio($fiscal['balance'])</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $fiscal['facturas_cantidad'] }} fact. / {{ $fiscal['nc_cantidad'] }} NC</p>
                </div>
            </div>

            {{-- Cajas --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex-shrink-0 p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30 w-fit">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Cajas Abiertas</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $cajas['abiertas'] }}/{{ $cajas['total'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${{ number_format($cajas['saldo_total'], 2, ',', '.') }} total</p>
                </div>
            </div>
        </div>

        {{-- Segunda fila de métricas --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            {{-- Compras --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 p-2 rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Compras</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">$@precio($compras['total'])</p>
                    </div>
                </div>
            </div>

            {{-- Cuenta Corriente --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 p-2 rounded-lg bg-orange-100 dark:bg-orange-900/30">
                        <svg class="h-5 w-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cta. Cte. Pendiente</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">$@precio($metricas['saldo_pendiente'])</p>
                    </div>
                </div>
            </div>

            {{-- Cobros --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 p-2 rounded-lg bg-teal-100 dark:bg-teal-900/30">
                        <svg class="h-5 w-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cobros Recibidos</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">$@precio($cobros['total_cobrado'])</p>
                    </div>
                </div>
            </div>

            {{-- Promociones --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 p-2 rounded-lg bg-pink-100 dark:bg-pink-900/30">
                        <svg class="h-5 w-5 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Descuentos Promos</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">$@precio($promociones['total_descuentos'])</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sección principal --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Formas de pago --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Formas de Pago</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">${{ number_format($formasPago['total'], 2, ',', '.') }}</span>
                </div>
                <div class="p-5">
                    @if(count($formasPago['detalle']) > 0)
                        <div class="space-y-4">
                            @foreach($formasPago['detalle'] as $fp)
                                @php
                                    $porcentaje = $formasPago['total'] > 0 ? ($fp['total'] / $formasPago['total']) * 100 : 0;
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $fp['nombre'] }}</span>
                                            <span class="text-xs text-gray-400">({{ $fp['cantidad'] }})</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($fp['facturado'] > 0)
                                                <span class="text-xs text-green-600 dark:text-green-400" title="Facturado">
                                                    ${{ number_format($fp['facturado'], 2, ',', '.') }}
                                                </span>
                                            @endif
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">${{ number_format($fp['total'], 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                        @if($fp['facturado'] > 0 && $fp['total'] > 0)
                                            <div class="h-2.5 flex">
                                                <div class="bg-green-500 h-2.5" style="width: {{ ($fp['facturado'] / $formasPago['total']) * 100 }}%"></div>
                                                <div class="bg-bcn-primary h-2.5" style="width: {{ ($fp['no_facturado'] / $formasPago['total']) * 100 }}%"></div>
                                            </div>
                                        @else
                                            <div class="bg-bcn-primary h-2.5 rounded-full" style="width: {{ $porcentaje }}%"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Leyenda --}}
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center gap-4 text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded bg-green-500"></span>
                                <span class="text-gray-500 dark:text-gray-400">Facturado: ${{ number_format($formasPago['facturado_total'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded bg-bcn-primary"></span>
                                <span class="text-gray-500 dark:text-gray-400">Sin facturar: ${{ number_format($formasPago['no_facturado_total'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                            </svg>
                            <p>Sin pagos registrados</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Comprobantes fiscales detalle --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Comprobantes Fiscales
                    </h3>
                </div>
                <div class="p-5">
                    @if(count($fiscal['por_tipo']) > 0)
                        <div class="space-y-3">
                            @foreach($fiscal['por_tipo'] as $tipo)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $tipo['tipo'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tipo['cantidad'] }} emitido(s)</p>
                                    </div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">${{ number_format($tipo['total'], 2, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Neto</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($fiscal['neto_total'], 2, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">IVA</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($fiscal['iva_total'], 2, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p>Sin comprobantes fiscales</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tercera fila --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Últimas ventas --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Últimas Ventas</h3>
                    <a href="{{ route('ventas.index') }}" class="text-sm text-bcn-primary hover:underline">Ver todas</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($ultimasVentas as $venta)
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    @if($venta['estado'] === 'cancelada')
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    @elseif($venta['es_cta_cte'])
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        #{{ $venta['numero'] }}
                                        @if($venta['estado'] === 'cancelada')
                                            <span class="text-xs text-red-500 ml-1">CANCELADA</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venta['cliente'] }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold {{ $venta['estado'] === 'cancelada' ? 'text-red-500 line-through' : 'text-gray-900 dark:text-white' }}">
                                    ${{ number_format($venta['total'], 2, ',', '.') }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venta['fecha'] }} {{ $venta['hora'] }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                            <p>No hay ventas recientes</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Promociones usadas --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Promociones Aplicadas
                    </h3>
                </div>
                <div class="p-5">
                    @if(count($promociones['top_promociones']) > 0)
                        <div class="space-y-3">
                            @foreach($promociones['top_promociones'] as $promo)
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $promo['nombre'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $promo['veces_usada'] }} veces</p>
                                    </div>
                                    <span class="ml-2 text-sm font-semibold text-pink-600 dark:text-pink-400">
                                        -${{ number_format($promo['descuento_total'], 2, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Total aplicaciones:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $promociones['cantidad_aplicaciones'] }}</span>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <p>Sin promociones aplicadas</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ventas por hora (solo para "Hoy") --}}
        @if($periodoSeleccionado === 'hoy' && count($ventasPorHora) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ventas por Hora</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Total: ${{ number_format(collect($ventasPorHora)->sum('total'), 2, ',', '.') }}
                    </span>
                </div>
                <div class="p-5">
                    @php
                        $maxTotal = collect($ventasPorHora)->max('total') ?: 1;
                        $chartHeight = 120; // px
                    @endphp
                    <div class="flex items-end gap-2" style="height: {{ $chartHeight + 30 }}px;">
                        @foreach($ventasPorHora as $hora)
                            @php
                                $barHeight = $maxTotal > 0 ? max(4, ($hora['total'] / $maxTotal) * $chartHeight) : 4;
                            @endphp
                            <div class="flex-1 flex flex-col items-center group cursor-pointer">
                                <div class="relative w-full flex justify-center" style="height: {{ $chartHeight }}px;">
                                    {{-- Tooltip --}}
                                    <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">
                                        <div class="font-semibold">${{ number_format($hora['total'], 0, ',', '.') }}</div>
                                        <div class="text-gray-300">{{ $hora['cantidad'] }} venta(s)</div>
                                    </div>
                                    {{-- Barra --}}
                                    <div
                                        class="w-full max-w-[40px] bg-bcn-primary hover:bg-bcn-primary/80 rounded-t transition-colors self-end"
                                        style="height: {{ $barHeight }}px;"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">{{ substr($hora['hora'], 0, 2) }}h</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Alertas y estado de cajas --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Estado de cajas --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estado de Cajas</h3>
                    <a href="{{ route('cajas.index') }}" class="text-sm text-bcn-primary hover:underline">Gestionar</a>
                </div>
                <div class="p-5">
                    @if(count($cajas['detalle']) > 0)
                        <div class="grid grid-cols-2 gap-3">
                            @foreach($cajas['detalle'] as $caja)
                                <div class="p-3 rounded-lg {{ $caja['estado'] === 'abierta' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium {{ $caja['estado'] === 'abierta' ? 'text-green-800 dark:text-green-200' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $caja['nombre'] }}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $caja['estado'] === 'abierta' ? 'bg-green-100 text-green-700 dark:bg-green-800/50 dark:text-green-300' : 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' }}">
                                            {{ $caja['estado'] === 'abierta' ? 'Abierta' : 'Cerrada' }}
                                        </span>
                                    </div>
                                    @if($caja['estado'] === 'abierta')
                                        <p class="text-lg font-bold text-green-700 dark:text-green-300 mt-1">
                                            ${{ number_format($caja['saldo'], 2, ',', '.') }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p>No hay cajas configuradas</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Alertas de stock --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        @if($alertasStock['bajo_minimo_count'] + $alertasStock['sin_existencia_count'] > 0)
                            <span class="flex h-3 w-3 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                        @endif
                        Alertas de Stock
                    </h3>
                    <a href="{{ route('stock.index') }}" class="text-sm text-bcn-primary hover:underline">Ver stock</a>
                </div>
                <div class="p-5">
                    @if($alertasStock['bajo_minimo_count'] + $alertasStock['sin_existencia_count'] > 0)
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            {{-- Contador sin stock --}}
                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-center">
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $alertasStock['sin_existencia_count'] }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400">Sin stock</p>
                            </div>
                            {{-- Contador bajo mínimo --}}
                            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-center">
                                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $alertasStock['bajo_minimo_count'] }}</p>
                                <p class="text-xs text-amber-600 dark:text-amber-400">Bajo mínimo</p>
                            </div>
                        </div>

                        {{-- Lista de artículos --}}
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            @foreach(array_slice($alertasStock['sin_existencia'], 0, 5) as $item)
                                <div class="flex items-center gap-2 p-2 rounded bg-red-50 dark:bg-red-900/10">
                                    <span class="flex-shrink-0 w-2 h-2 rounded-full bg-red-500"></span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate flex-1">{{ $item['articulo'] }}</span>
                                    <span class="text-xs font-medium text-red-600 dark:text-red-400">0</span>
                                </div>
                            @endforeach
                            @foreach(array_slice($alertasStock['bajo_minimo'], 0, 5 - min(5, count($alertasStock['sin_existencia']))) as $item)
                                <div class="flex items-center gap-2 p-2 rounded bg-amber-50 dark:bg-amber-900/10">
                                    <span class="flex-shrink-0 w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate flex-1">{{ $item['articulo'] }}</span>
                                    <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ $item['cantidad'] }}/{{ $item['minimo'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($alertasStock['bajo_minimo_count'] + $alertasStock['sin_existencia_count'] > 5)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 text-center">
                                y {{ ($alertasStock['bajo_minimo_count'] + $alertasStock['sin_existencia_count']) - 5 }} artículos más...
                            </p>
                        @endif
                    @else
                        <div class="text-center py-6">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-green-600 dark:text-green-400 font-medium">Stock sin alertas</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Todos los artículos tienen stock suficiente</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Accesos rápidos --}}
        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Accesos rápidos</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <a href="{{ route('ventas.create') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-bcn-primary/10 group-hover:bg-bcn-primary/20 transition-colors">
                        <svg class="h-6 w-6 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nueva Venta</span>
                </a>
                <a href="{{ route('ventas.index') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Ventas</span>
                </a>
                <a href="{{ route('cajas.index') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 group-hover:bg-amber-200 dark:group-hover:bg-amber-900/50 transition-colors">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Cajas</span>
                </a>
                <a href="{{ route('stock.index') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-purple-100 dark:bg-purple-900/30 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition-colors">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Stock</span>
                </a>
                <a href="{{ route('articulos.gestionar') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/30 group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Artículos</span>
                </a>
                <a href="{{ route('configuracion.empresa') }}" class="flex flex-col items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-bcn-primary/50 transition-all group">
                    <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 group-hover:bg-gray-200 dark:group-hover:bg-gray-600 transition-colors">
                        <svg class="h-6 w-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Configuración</span>
                </a>
            </div>
        </div>
    </div>
</div>
