<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Monedas') }}</h2>
                        {{-- Mobile: icon-only --}}
                        <div class="sm:hidden flex gap-2">
                            <button wire:click="nuevaMoneda"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                title="{{ __('Nueva moneda') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Gestiona las monedas y cotizaciones del sistema') }}</p>
                </div>
                {{-- Desktop: full button --}}
                <div class="hidden sm:flex gap-3">
                    <button wire:click="nuevaMoneda"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __('Nueva Moneda') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== SECCIÓN MONEDAS ==================== --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="px-4 py-4 sm:px-6 sm:py-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center mb-4">
                    <svg class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Monedas del Sistema') }}
                </h3>

                {{-- Listado de monedas --}}
                <div class="space-y-2">
                    @foreach($monedas as $moneda)
                        <div class="flex items-center justify-between p-3 rounded-lg border {{ $moneda->activo ? 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700/50' : 'border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 opacity-60' }}">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $moneda->es_principal ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-gray-100 dark:bg-gray-600' }}">
                                    <span class="text-sm font-bold {{ $moneda->es_principal ? 'text-yellow-700 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $moneda->simbolo }}</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $moneda->nombre }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300">{{ $moneda->codigo }}</span>
                                        @if($moneda->es_principal)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                {{ __('Principal') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $moneda->decimales }} {{ __('decimales') }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(!$moneda->es_principal)
                                    <button wire:click="marcarPrincipal({{ $moneda->id }})"
                                        class="inline-flex items-center p-1.5 text-gray-400 dark:text-gray-500 hover:text-yellow-600 dark:hover:text-yellow-400 transition"
                                        title="{{ __('Marcar como principal') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>
                                    </button>
                                @endif
                                <button wire:click="editMoneda({{ $moneda->id }})"
                                    class="inline-flex items-center p-1.5 text-gray-400 dark:text-gray-500 hover:text-bcn-primary dark:hover:text-bcn-primary transition"
                                    title="{{ __('Editar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="toggleMoneda({{ $moneda->id }})"
                                    class="relative inline-flex flex-shrink-0 h-5 w-9 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none {{ $moneda->activo ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                    title="{{ $moneda->activo ? __('Desactivar') : __('Activar') }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $moneda->activo ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    @if($monedas->isEmpty())
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p class="text-sm">{{ __('No hay monedas configuradas') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ==================== SECCIÓN TIPOS DE CAMBIO ==================== --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                    {{ __('Tipos de Cambio') }}
                </h3>
                <div class="flex gap-2">
                    <button wire:click="crearTC"
                        class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Nueva cotización') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                    <button wire:click="crearTC"
                        class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nueva Cotización') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Cotizaciones Vigentes --}}
        @if($vigentes->count() > 0)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ __('Cotizaciones Vigentes') }}</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($vigentes as $v)
                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $v->monedaOrigen->codigo }} / {{ $v->monedaDestino->codigo }}
                        </span>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ __('Compra') }}: {{ number_format($v->tasa_compra, 2, ',', '.') }} |
                            {{ __('Venta') }}: {{ number_format($v->tasa_venta, 2, ',', '.') }}
                        </div>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $v->fecha->format('d/m/Y') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Filtros Tipos de Cambio --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda origen') }}</label>
                        <select wire:model.live="filtroMonedaOrigen"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach($monedasActivas as $m)
                                <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda destino') }}</label>
                        <select wire:model.live="filtroMonedaDestino"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach($monedasActivas as $m)
                                <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla Tipos de Cambio --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Par') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Compra') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Venta') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Usuario') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($tiposCambio as $tc)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $tc->fecha->format('d/m/Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $tc->monedaOrigen->codigo }} / {{ $tc->monedaDestino->codigo }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right">{{ number_format($tc->tasa_compra, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right">{{ number_format($tc->tasa_venta, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $tc->usuario?->name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button wire:click="editarTC({{ $tc->id }})"
                                        class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                        title="{{ __('Editar') }}">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        {{ __('Editar') }}
                                    </button>
                                    <button wire:click="eliminarTC({{ $tc->id }})"
                                        wire:confirm="{{ __('¿Está seguro de eliminar esta cotización?') }}"
                                        class="inline-flex items-center justify-center w-9 h-9 border border-red-300 dark:border-red-600 text-xs font-medium rounded text-red-600 dark:text-red-400 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                        title="{{ __('Eliminar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No hay cotizaciones registradas') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tiposCambio->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $tiposCambio->links() }}
            </div>
            @endif
        </div>

        {{-- Mobile: cards tipos de cambio --}}
        <div class="sm:hidden space-y-3">
            @forelse($tiposCambio as $tc)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $tc->monedaOrigen->codigo }} / {{ $tc->monedaDestino->codigo }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tc->fecha->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-300 mb-3">
                    <span>{{ __('Compra') }}: {{ number_format($tc->tasa_compra, 2, ',', '.') }}</span>
                    <span>{{ __('Venta') }}: {{ number_format($tc->tasa_venta, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="editarTC({{ $tc->id }})"
                        class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button wire:click="eliminarTC({{ $tc->id }})"
                        wire:confirm="{{ __('¿Está seguro de eliminar esta cotización?') }}"
                        class="inline-flex items-center justify-center px-3 py-2 border border-red-300 dark:border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white transition-colors duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
            @empty
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No hay cotizaciones registradas') }}</p>
            </div>
            @endforelse

            @if($tiposCambio->hasPages())
            <div class="mt-4">
                {{ $tiposCambio->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Modal Nueva/Editar Moneda --}}
    @if($mostrarFormMoneda)
        <x-bcn-modal
            :title="$monedaEditId ? __('Editar Moneda') : __('Nueva Moneda')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cancelarFormMoneda"
            submit="guardarMoneda"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Código') }} *</label>
                            <input type="text" wire:model="monedaCodigo" maxlength="3"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm uppercase"
                                placeholder="{{ __('Ej: ARS, BRL') }}">
                            @error('monedaCodigo') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Símbolo') }} *</label>
                            <input type="text" wire:model="monedaSimbolo" maxlength="5"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Ej: $, R$') }}">
                            @error('monedaSimbolo') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                        <input type="text" wire:model="monedaNombre" maxlength="50"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            placeholder="{{ __('Ej: Peso Argentino') }}">
                        @error('monedaNombre') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Decimales') }}</label>
                            <input type="number" wire:model="monedaDecimales" min="0" max="4"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Orden') }}</label>
                            <input type="number" wire:model="monedaOrden" min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="monedaActivo"
                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Activa') }}</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="monedaEsPrincipal"
                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Principal') }}</span>
                        </label>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cancelarFormMoneda"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ $monedaEditId ? __('Actualizar') : __('Crear') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Tipo de Cambio --}}
    @if($mostrarModalTC)
        <x-bcn-modal
            :title="$modoEdicionTC ? __('Editar Tipo de Cambio') : __('Nueva Cotización')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cerrarModalTC"
            submit="guardarTC"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Ej: si 1 dólar vale 1.400 pesos, seleccione USD como moneda a cotizar y ARS como moneda de expresión') }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda a cotizar') }} *</label>
                            <select wire:model.live="moneda_origen_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Seleccione...') }}</option>
                                @foreach($monedasActivas as $m)
                                    <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            @error('moneda_origen_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda de expresión') }} *</label>
                            <select wire:model.live="moneda_destino_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Seleccione...') }}</option>
                                @foreach($monedasActivas as $m)
                                    <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            @error('moneda_destino_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tasa compra') }} *</label>
                            <input type="number" wire:model="tasa_compra" step="0.000001" min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('tasa_compra') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tasa venta') }} *</label>
                            <input type="number" wire:model="tasa_venta" step="0.000001" min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('tasa_venta') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha') }} *</label>
                            <input type="date" wire:model="fecha"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('fecha') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarModalTC"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ $modoEdicionTC ? __('Actualizar') : __('Registrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
