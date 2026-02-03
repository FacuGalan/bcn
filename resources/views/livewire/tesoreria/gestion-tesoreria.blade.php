<div class="py-4 px-4 sm:px-6">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Tesoreria') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Gestion de fondos y movimientos de efectivo') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button
                wire:click="cargarDatos"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('Actualizar') }}
            </button>
        </div>
    </div>

    @if($tesoreria)
    {{-- Panel de Saldo y Estadisticas --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
        {{-- Saldo Actual --}}
        <div class="lg:col-span-1 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg p-5 text-white">
            <div class="flex items-center justify-between mb-2">
                <span class="text-blue-100 text-sm font-medium">{{ __('Saldo Actual') }}</span>
                <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <p class="text-3xl font-bold">${{ number_format($tesoreria->saldo_actual, 2, ',', '.') }}</p>
            <p class="text-blue-200 text-sm mt-1">{{ $tesoreria->nombre }}</p>

            @if($tesoreria->estaBajoMinimo())
            <div class="mt-3 p-2 bg-red-500/20 rounded-lg">
                <p class="text-xs flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {{ __('Por debajo del minimo') }} (${{ number_format($tesoreria->saldo_minimo, 0) }})
                </p>
            </div>
            @elseif($tesoreria->estaSobreMaximo())
            <div class="mt-3 p-2 bg-amber-500/20 rounded-lg">
                <p class="text-xs flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Sugerido depositar') }} ${{ number_format($tesoreria->montoSugeridoDeposito(), 0) }}
                </p>
            </div>
            @endif
        </div>

        {{-- Estadisticas del Dia --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">{{ __('Movimientos de Hoy') }}</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Provisiones') }}</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400">-${{ number_format($estadisticasHoy['provisiones'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400">{{ $estadisticasHoy['cantidad_provisiones'] ?? 0 }} {{ __('operaciones') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Rendiciones') }}</p>
                    <p class="text-xl font-bold text-green-600 dark:text-green-400">+${{ number_format($estadisticasHoy['rendiciones'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400">{{ $estadisticasHoy['cantidad_rendiciones'] ?? 0 }} {{ __('operaciones') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Depositos') }}</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">-${{ number_format($estadisticasHoy['depositos'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Neto del Dia') }}</p>
                    @php
                        $netoHoy = ($estadisticasHoy['rendiciones'] ?? 0) - ($estadisticasHoy['provisiones'] ?? 0) - ($estadisticasHoy['depositos'] ?? 0);
                    @endphp
                    <p class="text-xl font-bold {{ $netoHoy >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $netoHoy >= 0 ? '+' : '' }}${{ number_format($netoHoy, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Acciones Rapidas --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <button
            wire:click="abrirModalProvision"
            class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-red-300 dark:hover:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
        >
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-2">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Provisionar') }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('A caja') }}</span>
        </button>

        <button
            wire:click="abrirModalRendicion"
            class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-700 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors relative"
        >
            @if(count($rendicionesPendientes) > 0)
            <span class="absolute top-2 right-2 w-5 h-5 bg-amber-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                {{ count($rendicionesPendientes) }}
            </span>
            @endif
            <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-2">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Rendiciones') }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Desde cajas') }}</span>
        </button>

        <button
            wire:click="abrirModalDeposito"
            class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
        >
            <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-2">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Deposito') }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Bancario') }}</span>
        </button>

        <button
            wire:click="abrirModalArqueo"
            class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors"
        >
            <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-2">
                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Arqueo') }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Verificar saldo') }}</span>
        </button>
    </div>

    {{-- Tabs de navegación --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="flex overflow-x-auto border-b border-gray-200 dark:border-gray-700">
            <button wire:click="$set('vistaActiva', 'movimientos')"
                    class="flex-shrink-0 px-4 sm:px-6 py-3 text-sm font-medium border-b-2 transition-colors {{ $vistaActiva === 'movimientos' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                {{ __('Movimientos') }}
            </button>
            <button wire:click="$set('vistaActiva', 'cajas')"
                    class="flex-shrink-0 px-4 sm:px-6 py-3 text-sm font-medium border-b-2 transition-colors {{ $vistaActiva === 'cajas' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                {{ __('Estado Cajas') }}
            </button>
            <button wire:click="$set('vistaActiva', 'depositos')"
                    class="flex-shrink-0 px-4 sm:px-6 py-3 text-sm font-medium border-b-2 transition-colors relative {{ $vistaActiva === 'depositos' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                {{ __('Depositos') }}
                @if($depositosPendientes->count() > 0)
                <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                    {{ $depositosPendientes->count() }}
                </span>
                @endif
            </button>
            <button wire:click="$set('vistaActiva', 'arqueos')"
                    class="flex-shrink-0 px-4 sm:px-6 py-3 text-sm font-medium border-b-2 transition-colors {{ $vistaActiva === 'arqueos' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                {{ __('Arqueos') }}
            </button>
        </div>
    </div>

    {{-- Contenido de Movimientos --}}
    @if($vistaActiva === 'movimientos')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        {{-- Filtros --}}
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Tipo') }}</label>
                    <select
                        wire:model.live="filtroTipo"
                        class="w-full sm:w-40 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"
                    >
                        <option value="">{{ __('Todos') }}</option>
                        <option value="ingreso">{{ __('Ingresos') }}</option>
                        <option value="egreso">{{ __('Egresos') }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Desde') }}</label>
                    <input
                        type="date"
                        wire:model.live="filtroFechaDesde"
                        class="w-full sm:w-40 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"
                    >
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Hasta') }}</label>
                    <input
                        type="date"
                        wire:model.live="filtroFechaHasta"
                        class="w-full sm:w-40 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"
                    >
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Buscar') }}</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="filtroConcepto"
                        :placeholder="__('Buscar en concepto...')"
                        class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"
                    >
                </div>
                <button
                    wire:click="limpiarFiltros"
                    class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                >
                    {{ __('Limpiar') }}
                </button>
            </div>
        </div>

        {{-- Tabla de Movimientos --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuario') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Monto') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($movimientos as $mov)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $mov->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ $mov->concepto }}
                            @if($mov->observaciones)
                            <p class="text-xs text-gray-400 mt-0.5">{{ Str::limit($mov->observaciones, 50) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ $mov->usuario->name ?? __('Sistema') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right whitespace-nowrap font-medium {{ $mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">
                            ${{ number_format($mov->saldo_posterior, 2, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ __('No hay movimientos en el periodo seleccionado') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginacion --}}
        @if($movimientos->hasPages())
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
            {{ $movimientos->links() }}
        </div>
        @endif
    </div>
    @endif

    {{-- Contenido de Estado de Cajas --}}
    @if($vistaActiva === 'cajas')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Estado de Cajas de la Sucursal') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Saldos actuales y estado operativo') }}</p>
        </div>

        @if($estadoCajas->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
            @foreach($estadoCajas as $caja)
            <div class="p-4 rounded-lg border {{ $caja['esta_abierta'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50' }}">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $caja['nombre'] }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">#{{ $caja['numero'] }}</p>
                    </div>
                    @if($caja['esta_abierta'])
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-800/50 dark:text-green-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5 animate-pulse"></span>
                        {{ __('Abierta') }}
                    </span>
                    @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                        {{ __('Cerrada') }}
                    </span>
                    @endif
                </div>

                <div class="text-center mb-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($caja['saldo_actual'], 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo actual') }}</p>
                </div>

                @if($caja['ultimo_usuario'])
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Ultimo usuario:') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $caja['ultimo_usuario'] }}</span>
                </div>
                @endif

                @if($caja['ultimo_movimiento'])
                <div class="flex items-center justify-between text-xs mt-1">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Ultimo mov:') }}</span>
                    <span class="text-gray-600 dark:text-gray-400">{{ $caja['ultimo_movimiento']->diffForHumans() }}</span>
                </div>
                @endif

                {{-- Acciones rápidas --}}
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 flex gap-2">
                    <button wire:click="$set('cajaProvisionId', {{ $caja['id'] }}); $set('showProvisionModal', true)"
                            class="flex-1 px-2 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/40">
                        {{ __('Provisionar') }}
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Totales --}}
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total en Cajas') }}</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($estadoCajas->sum('saldo_actual'), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Cajas Abiertas') }}</p>
                    <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $estadoCajas->where('esta_abierta', true)->count() }} / {{ $estadoCajas->count() }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Tesoreria + Cajas') }}</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">${{ number_format(($tesoreria->saldo_actual ?? 0) + $estadoCajas->sum('saldo_actual'), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        @else
        <div class="p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400">{{ __('No hay cajas configuradas en esta sucursal') }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Contenido de Depositos Pendientes --}}
    @if($vistaActiva === 'depositos')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Depositos Bancarios Pendientes') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Depositos registrados pendientes de confirmacion') }}</p>
        </div>

        @if($depositosPendientes->count() > 0)
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($depositosPendientes as $deposito)
            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ __('Pendiente') }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $deposito->fecha_deposito->format('d/m/Y') }}</span>
                        </div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mt-1">{{ $deposito->cuentaBancaria->nombre_completo }}</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Registrado por') }} {{ $deposito->usuario->name ?? __('Usuario') }}
                            @if($deposito->numero_comprobante)
                            - {{ __('Comp.') }} #{{ $deposito->numero_comprobante }}
                            @endif
                        </p>
                        @if($deposito->observaciones)
                        <p class="text-xs text-gray-400 mt-1">{{ $deposito->observaciones }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($deposito->monto, 0, ',', '.') }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <button wire:click="confirmarDepositoBancario({{ $deposito->id }})"
                                    wire:confirm="{{ __('¿Confirmar la recepcion del deposito por') }} ${{ number_format($deposito->monto, 0, ',', '.') }}?"
                                    class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                                {{ __('Confirmar') }}
                            </button>
                            <button wire:click="cancelarDepositoBancario({{ $deposito->id }})"
                                    wire:confirm="{{ __('¿Cancelar este deposito? El monto sera devuelto a tesoreria.') }}"
                                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/40">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('Sin depositos pendientes') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Todos los depositos han sido confirmados') }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Contenido de Historial de Arqueos --}}
    @if($vistaActiva === 'arqueos')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Historial de Arqueos') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Ultimos arqueos realizados en tesoreria') }}</p>
            </div>
            <button wire:click="abrirModalArqueo"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Nuevo Arqueo') }}
            </button>
        </div>

        @if($arqueos->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sistema') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Contado') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Diferencia') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuario') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($arqueos as $arqueo)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $arqueo->fecha->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">
                            ${{ number_format($arqueo->saldo_sistema, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-medium">
                            ${{ number_format($arqueo->saldo_contado, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                            @if($arqueo->diferencia == 0)
                            <span class="text-green-600 dark:text-green-400 font-medium">OK</span>
                            @elseif($arqueo->diferencia > 0)
                            <span class="text-blue-600 dark:text-blue-400 font-medium">+${{ number_format($arqueo->diferencia, 0, ',', '.') }}</span>
                            @else
                            <span class="text-red-600 dark:text-red-400 font-medium">-${{ number_format(abs($arqueo->diferencia), 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($arqueo->estado === 'pendiente')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ __('Pendiente') }}
                            </span>
                            @elseif($arqueo->estado === 'aprobado')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                {{ __('Aprobado') }}
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                {{ __('Rechazado') }}
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ $arqueo->usuario->name ?? __('Sistema') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="verDetalleArqueo({{ $arqueo->id }})"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium">
                                {{ __('Ver') }}
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('Sin arqueos registrados') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Realiza el primer arqueo de tesoreria') }}</p>
            <button wire:click="abrirModalArqueo" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                {{ __('Realizar Arqueo') }}
            </button>
        </div>
        @endif
    </div>
    @endif

    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">{{ __('No hay tesoreria configurada') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Seleccione una sucursal para comenzar.') }}</p>
    </div>
    @endif

    {{-- Modal de Provision --}}
    @if($showProvisionModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="$set('showProvisionModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Provisionar Fondo a Caja') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Caja destino') }}</label>
                        <select wire:model="cajaProvisionId" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
                            <option value="">{{ __('Seleccionar caja...') }}</option>
                            @foreach($cajasDisponibles as $caja)
                            <option value="{{ $caja->id }}">{{ $caja->nombre }} (#{{ $caja->numero_formateado }})</option>
                            @endforeach
                        </select>
                        @error('cajaProvisionId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto') }}</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input type="number" step="0.01" min="0" wire:model="montoProvision" class="w-full pl-8 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg" placeholder="0.00">
                        </div>
                        @error('montoProvision') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones (opcional)') }}</label>
                        <textarea wire:model="observacionesProvision" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"></textarea>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Saldo disponible:') }} <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($tesoreria->saldo_actual ?? 0, 2, ',', '.') }}</span></p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end gap-3 rounded-b-lg">
                    <button wire:click="$set('showProvisionModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">{{ __('Cancelar') }}</button>
                    <button wire:click="procesarProvision" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">{{ __('Provisionar') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Rendiciones Pendientes --}}
    @if($showRendicionModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="$set('showRendicionModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Rendiciones Pendientes') }}</h3>
                </div>
                <div class="p-6">
                    @if(count($rendicionesPendientes) > 0)
                    <div class="space-y-3">
                        @foreach($rendicionesPendientes as $rendicion)
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ $rendicion['caja']['nombre'] ?? __('Caja') }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $rendicion['usuario_entrega']['name'] ?? __('Usuario') }} -
                                        {{ \Carbon\Carbon::parse($rendicion['fecha'])->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-green-600 dark:text-green-400">${{ number_format($rendicion['monto_entregado'], 2, ',', '.') }}</p>
                                    @if($rendicion['diferencia'] != 0)
                                    <p class="text-xs {{ $rendicion['diferencia'] > 0 ? 'text-blue-600' : 'text-red-600' }}">
                                        {{ $rendicion['diferencia'] > 0 ? __('Sobrante') : __('Faltante') }}: ${{ number_format(abs($rendicion['diferencia']), 2, ',', '.') }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                @if($rendicion['puede_revertir'] ?? false)
                                <button
                                    wire:click="abrirModalRechazo({{ $rendicion['id'] }})"
                                    class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
                                >
                                    {{ __('Rechazar y Revertir') }}
                                </button>
                                @endif
                                <button
                                    wire:click="confirmarRendicion({{ $rendicion['id'] }})"
                                    class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"
                                >
                                    {{ __('Confirmar Recepcion') }}
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-center text-gray-500 dark:text-gray-400 py-8">{{ __('No hay rendiciones pendientes') }}</p>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end rounded-b-lg">
                    <button wire:click="$set('showRendicionModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">{{ __('Cerrar') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Rechazo y Reversion --}}
    @if($showRechazoModal)
    <div class="fixed inset-0 z-[60] overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="$set('showRechazoModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">{{ __('Rechazar y Revertir Cierre') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                        <p class="text-sm text-red-700 dark:text-red-300">
                            {{ __('Esta accion rechazara la rendicion y revertira completamente el cierre de turno asociado. Las cajas seran reabiertas y los saldos restaurados al estado anterior al cierre.') }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo del rechazo (opcional)') }}</label>
                        <textarea wire:model="motivoRechazo" rows="3"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm"
                            :placeholder="__('Ingrese el motivo del rechazo...')"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end gap-3 rounded-b-lg">
                    <button wire:click="$set('showRechazoModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">
                        {{ __('Cancelar') }}
                    </button>
                    <button
                        wire:click="rechazarYRevertirCierre"
                        wire:confirm="{{ __('Esta seguro? Esta accion revertira el cierre de turno completo y reabrira las cajas.') }}"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                        {{ __('Rechazar y Revertir') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Deposito Bancario --}}
    @if($showDepositoModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="$set('showDepositoModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Registrar Deposito Bancario') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuenta bancaria') }}</label>
                        <select wire:model="cuentaBancariaId" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
                            <option value="">{{ __('Seleccionar cuenta...') }}</option>
                            @foreach($cuentasBancarias as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->nombre_completo }}</option>
                            @endforeach
                        </select>
                        @error('cuentaBancariaId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto') }}</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input type="number" step="0.01" min="0" wire:model="montoDeposito" class="w-full pl-8 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg" placeholder="0.00">
                        </div>
                        @error('montoDeposito') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha del deposito') }}</label>
                        <input type="date" wire:model="fechaDeposito" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
                        @error('fechaDeposito') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Numero de comprobante (opcional)') }}</label>
                        <input type="text" wire:model="numeroComprobante" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg" :placeholder="__('Ej: 123456')">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones (opcional)') }}</label>
                        <textarea wire:model="observacionesDeposito" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end gap-3 rounded-b-lg">
                    <button wire:click="$set('showDepositoModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">{{ __('Cancelar') }}</button>
                    <button wire:click="procesarDeposito" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">{{ __('Registrar Deposito') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Arqueo --}}
    @if($showArqueoModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="$set('showArqueoModal', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Realizar Arqueo de Tesoreria') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-blue-700 dark:text-blue-400">
                            <strong>{{ __('Saldo segun sistema:') }}</strong> ${{ number_format($tesoreria->saldo_actual ?? 0, 2, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Saldo contado fisicamente') }}</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input type="number" step="0.01" min="0" wire:model="saldoContado" class="w-full pl-8 text-lg font-semibold border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg" placeholder="0.00">
                        </div>
                        @error('saldoContado') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    @if($saldoContado > 0)
                    @php $diferencia = $saldoContado - ($tesoreria->saldo_actual ?? 0); @endphp
                    <div class="p-3 rounded-lg {{ $diferencia == 0 ? 'bg-green-50 dark:bg-green-900/20' : ($diferencia > 0 ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-red-50 dark:bg-red-900/20') }}">
                        <p class="text-sm font-medium {{ $diferencia == 0 ? 'text-green-700 dark:text-green-400' : ($diferencia > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-red-700 dark:text-red-400') }}">
                            @if($diferencia == 0)
                                {{ __('Caja cuadrada') }}
                            @elseif($diferencia > 0)
                                {{ __('Sobrante:') }} +${{ number_format($diferencia, 2, ',', '.') }}
                            @else
                                {{ __('Faltante:') }} -${{ number_format(abs($diferencia), 2, ',', '.') }}
                            @endif
                        </p>
                    </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones (opcional)') }}</label>
                        <textarea wire:model="observacionesArqueo" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end gap-3 rounded-b-lg">
                    <button wire:click="$set('showArqueoModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">{{ __('Cancelar') }}</button>
                    <button wire:click="procesarArqueo" class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">{{ __('Registrar Arqueo') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Detalle de Arqueo --}}
    @if($showArqueoDetalleModal && $arqueoDetalle)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75" wire:click="cerrarDetalleArqueo"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Detalle del Arqueo') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $arqueoDetalle->fecha->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($arqueoDetalle->estado === 'pendiente')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        {{ __('Pendiente') }}
                    </span>
                    @elseif($arqueoDetalle->estado === 'aprobado')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        {{ __('Aprobado') }}
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                        {{ __('Rechazado') }}
                    </span>
                    @endif
                </div>
                <div class="p-6 space-y-4">
                    {{-- Montos --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Sistema') }}</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($arqueoDetalle->saldo_sistema, 0, ',', '.') }}</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Contado') }}</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($arqueoDetalle->saldo_contado, 0, ',', '.') }}</p>
                        </div>
                        <div class="text-center p-3 rounded-lg {{ $arqueoDetalle->diferencia == 0 ? 'bg-green-50 dark:bg-green-900/20' : ($arqueoDetalle->diferencia > 0 ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-red-50 dark:bg-red-900/20') }}">
                            <p class="text-xs {{ $arqueoDetalle->diferencia == 0 ? 'text-green-600' : ($arqueoDetalle->diferencia > 0 ? 'text-blue-600' : 'text-red-600') }} uppercase">{{ __('Diferencia') }}</p>
                            <p class="text-lg font-bold {{ $arqueoDetalle->diferencia == 0 ? 'text-green-700 dark:text-green-400' : ($arqueoDetalle->diferencia > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-red-700 dark:text-red-400') }}">
                                @if($arqueoDetalle->diferencia == 0)
                                    OK
                                @elseif($arqueoDetalle->diferencia > 0)
                                    +${{ number_format($arqueoDetalle->diferencia, 0, ',', '.') }}
                                @else
                                    -${{ number_format(abs($arqueoDetalle->diferencia), 0, ',', '.') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Usuarios --}}
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('Realizado por') }}</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $arqueoDetalle->usuario->name ?? 'N/A' }}</p>
                        </div>
                        @if($arqueoDetalle->supervisor)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('Aprobado por') }}</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $arqueoDetalle->supervisor->name }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Observaciones --}}
                    @if($arqueoDetalle->observaciones)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ __('Observaciones') }}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">{{ $arqueoDetalle->observaciones }}</p>
                    </div>
                    @endif

                    {{-- Acciones para arqueo pendiente --}}
                    @if($arqueoDetalle->estado === 'pendiente')
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-sm text-amber-700 dark:text-amber-400 mb-3">{{ __('Este arqueo esta pendiente de aprobacion.') }}</p>
                        @if($arqueoDetalle->diferencia != 0)
                        <p class="text-xs text-amber-600 dark:text-amber-500 mb-3">
                            {{ __('Si aprueba con ajuste, se') }} {{ $arqueoDetalle->diferencia > 0 ? __('sumara') : __('restara') }} ${{ number_format(abs($arqueoDetalle->diferencia), 0, ',', '.') }} {{ __('al saldo de tesoreria.') }}
                        </p>
                        @endif
                        <div class="flex gap-2">
                            <button wire:click="aprobarArqueo({{ $arqueoDetalle->id }}, false)"
                                    class="flex-1 px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                                {{ __('Aprobar') }}
                            </button>
                            @if($arqueoDetalle->diferencia != 0)
                            <button wire:click="aprobarArqueo({{ $arqueoDetalle->id }}, true)"
                                    wire:confirm="{{ __('¿Aprobar y aplicar el ajuste de') }} ${{ number_format(abs($arqueoDetalle->diferencia), 0, ',', '.') }}?"
                                    class="flex-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                {{ __('Aprobar + Ajustar') }}
                            </button>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex justify-end rounded-b-lg">
                    <button wire:click="cerrarDetalleArqueo" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500">{{ __('Cerrar') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
